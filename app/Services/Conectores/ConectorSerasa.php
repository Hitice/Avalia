<?php

namespace App\Services\Conectores;

use App\Contracts\ConectorBureau;
use App\Models\Conexao;
use App\Models\Servico;
use App\Support\RespostaConsulta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Conector real da Serasa Experian (familia de relatorios de credito).
 *
 * Escrito sobre o swagger e o manual oficiais do portal do desenvolvedor:
 * token de 1 hora em POST /security/iam/v1/client-identities/login com Basic
 * base64(client_id:client_secret); relatorio em GET com o documento no header
 * X-Document-Id e o produto no query reportName. PF e PJ tem endpoints
 * distintos, escolhidos pelo tamanho do documento.
 *
 * O contrato de ConectorBureau vale inteiro: falha do fornecedor NUNCA vira
 * excecao, vira RespostaConsulta::falha com mensagem que o operador entende.
 * A resposta e traduzida para o laudo canonico (score com modelo, negativos
 * com contagens e valores), porque cada fornecedor entrega uma coisa e a tela
 * so fala uma lingua.
 */
class ConectorSerasa implements ConectorBureau
{
    private const CAMINHO_TOKEN = '/security/iam/v1/client-identities/login';

    private const CAMINHO_PF = '/credit-services/person-information-report/v1/creditreport';

    private const CAMINHO_PJ = '/credit-services/business-information-report/v1/reports';

    public function consultar(Servico $servico, string $documento, string $finalidade): RespostaConsulta
    {
        $inicio = microtime(true);
        $duracao = fn () => (int) ((microtime(true) - $inicio) * 1000);

        if (! $servico->codigo_fornecedor) {
            return RespostaConsulta::falha(
                "O serviço {$servico->nome} ainda não tem o relatório do fornecedor configurado no catálogo.",
                null,
                $duracao(),
            );
        }

        $base = Conexao::urlBase('serasa');

        if (! $base || ! Conexao::segredo('serasa', 'client_id')) {
            return RespostaConsulta::falha('A conexão com o bureau não está configurada.', null, $duracao());
        }

        try {
            $token = $this->token($base);

            $caminho = strlen($documento) === 11 ? self::CAMINHO_PF : self::CAMINHO_PJ;

            $resposta = Http::baseUrl($base)
                ->withToken($token)
                ->withHeaders(['X-Document-Id' => $documento])
                ->timeout(30)
                ->get($caminho, ['reportName' => $servico->codigo_fornecedor]);

            if (! $resposta->successful()) {
                return RespostaConsulta::falha($this->motivo($resposta->status()), null, $duracao());
            }

            $relatorio = $resposta->json('reports.0') ?? [];

            return RespostaConsulta::sucesso(
                $this->laudo($relatorio, $resposta->json('optionalFeatures') ?? []),
                $relatorio['reportId'] ?? ('SER-'.strtoupper(substr(hash('sha256', $documento.now()->timestamp), 0, 12))),
                $duracao(),
            );
        } catch (\Throwable $e) {
            report($e);

            return RespostaConsulta::falha('O fornecedor não respondeu. Tente novamente em instantes.', null, $duracao());
        }
    }

    public function nome(): string
    {
        return 'serasa';
    }

    /**
     * Token de acesso, com folga sobre a validade oficial de 1 hora.
     *
     * O cache evita um login por consulta, que a Serasa trata como variacao
     * brusca de volume e pode bloquear.
     */
    private function token(string $base): string
    {
        return Cache::remember('serasa-token', now()->addMinutes(50), function () use ($base) {
            $credencial = base64_encode(
                Conexao::segredo('serasa', 'client_id').':'.Conexao::segredo('serasa', 'client_secret'),
            );

            return Http::baseUrl($base)
                ->withHeaders(['Authorization' => 'Basic '.$credencial, 'Content-Type' => 'application/json'])
                ->timeout(15)
                ->post(self::CAMINHO_TOKEN)
                ->throw()
                ->json('accessToken');
        });
    }

    /**
     * Traducao do payload da Serasa para o laudo canonico da tela.
     *
     * A ORDEM dos blocos e a do mercado, nao a do payload: resumo de decisao
     * no topo (score), depois identidade, depois as restricoes da mais grave
     * para a menos (pendencia comercial, bancaria, protesto, cheque), e por
     * fim o contexto. E a ordem do relatorio classico do fornecedor, com a
     * camada de decisao promovida para cima como os produtos novos fazem:
     * quem le decide nos primeiros cinco segundos, e o resto e prova.
     *
     * So entram numeros que vieram: bloco ausente vira "nao incluido nesta
     * consulta" na tela, nunca zero fingindo dado.
     */
    private function laudo(array $relatorio, array $features): array
    {
        $laudo = ['laudo' => 1, 'fornecido_em' => now()->toIso8601String()];

        // 1. Decisao primeiro. O score diz QUAL score e: modelos diferentes
        // nao se comparam, e omitir o modelo faz o numero mentir.
        $score = $relatorio['score'] ?? $features['score'] ?? null;

        if ($score) {
            $laudo['score'] = $score['score'] ?? null;
            $laudo['modelo_do_score'] = $score['scoreModel'] ?? null;
            $laudo['faixa_do_score'] = $score['range'] ?? null;
            $laudo['probabilidade_de_inadimplencia'] = $score['defaultRate'] ?? null;
        }

        // 2. Identidade.
        if ($cadastro = $relatorio['registration'] ?? null) {
            $laudo['nome'] = $cadastro['consumerName'] ?? $cadastro['companyName'] ?? null;
            $laudo['situacao_cadastral'] = $cadastro['statusRegistration'] ?? null;
        }

        // 3. Restricoes, da mais grave para a menos.
        if ($negativos = $relatorio['negativeData'] ?? null) {
            $laudo['pendencias_comerciais'] = $negativos['pefin']['summary']['count'] ?? 0;
            $laudo['valor_pendencias_comerciais_cents'] = (int) round(($negativos['pefin']['summary']['balance'] ?? 0) * 100);
            $laudo['pendencias_bancarias'] = $negativos['refin']['summary']['count'] ?? 0;
            $laudo['valor_pendencias_bancarias_cents'] = (int) round(($negativos['refin']['summary']['balance'] ?? 0) * 100);
            $laudo['protestos'] = $negativos['notary']['summary']['count'] ?? 0;
            $laudo['cheques_sem_fundo'] = $negativos['check']['summary']['count'] ?? 0;
        }

        // 4. Contexto. Quantas vezes o documento foi consultado e' leitura de
        // mercado: consulta demais sinaliza busca por credito em varios
        // lugares, consulta de menos sinaliza inatividade.
        if ($consultas = $relatorio['facts']['inquirySummary'] ?? null) {
            $laudo['consultas_recentes'] = $consultas['quantityLastThirtyDays']
                ?? $consultas['quantity'] ?? null;
        }

        return array_filter($laudo, fn ($v) => $v !== null);
    }

    /** A tabela de erros do manual, em lingua de operador. */
    private function motivo(int $status): string
    {
        return match ($status) {
            401 => 'O fornecedor recusou as credenciais. Confira a conexão com o bureau.',
            403 => 'Informação indisponível para este documento (proteção legal).',
            404 => 'Documento não encontrado na base do fornecedor.',
            412 => 'O fornecedor recusou a consulta: documento inválido ou relatório não contratado.',
            429 => 'O fornecedor limitou o volume de consultas. Aguarde alguns minutos.',
            default => 'O fornecedor respondeu com erro ('.$status.').',
        };
    }
}
