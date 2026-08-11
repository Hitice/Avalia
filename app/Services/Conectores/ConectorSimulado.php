<?php

namespace App\Services\Conectores;

use App\Contracts\ConectorBureau;
use App\Models\Servico;
use App\Support\RespostaConsulta;

/**
 * Conector de homologacao: responde sem falar com fornecedor nenhum.
 *
 * Serve para exercitar a cadeia inteira, do clique no portal ate a fatura,
 * antes de existir contrato e credencial. Nao e um atalho para producao: o
 * container so o amarra quando nao ha conector real configurado, e a resposta
 * vem marcada como simulada, entao ninguem confunde com dado de bureau.
 *
 * As respostas sao DETERMINISTICAS pelo documento: o mesmo CPF devolve sempre o
 * mesmo resultado. Aleatoriedade aqui faria o teste passar hoje e falhar
 * amanha, e faria o operador em homologacao duvidar do que esta vendo.
 *
 * Documento terminado em zero falha de proposito. Sem um jeito previsivel de
 * provocar falha, o caminho de erro so seria exercitado em producao.
 */
class ConectorSimulado implements ConectorBureau
{
    public function consultar(Servico $servico, string $documento, string $finalidade): RespostaConsulta
    {
        $documento = preg_replace('/\D/', '', $documento) ?? '';
        $inicio = microtime(true);

        // Latencia plausivel, derivada do documento: sempre a mesma para o
        // mesmo numero, entre 120 e 800 ms.
        $duracao = 120 + (crc32($documento) % 680);

        if ($documento === '' || str_ends_with($documento, '0')) {
            return RespostaConsulta::falha(
                'O fornecedor não retornou dados para este documento.',
                $this->protocolo($documento, $servico),
                $duracao,
            );
        }

        $semente = crc32($documento.$servico->codigo);
        $restricoes = $semente % 3;
        $ehCnpj = strlen($documento) === 14;

        // As chaves sao as CANONICAS do laudo (App\Support\Laudo::BLOCOS), e
        // isso nao e capricho: com chave propria o resultado caia inteiro em
        // "Outras informacoes" e a tela dizia "nao contempla restricoes" com a
        // restricao na linha de cima. O simulado existe para exercitar o fluxo
        // real, entao precisa produzir o formato real.
        //
        // O que a tela ja mostra (documento, servico, finalidade, data) fica de
        // fora do laudo: campo repetido em bloco de resultado e ruido.
        return RespostaConsulta::sucesso(array_filter([
            'simulado' => true,
            'score' => 300 + ($semente % 701),
            'modelo_do_score' => 'SIMULADO_V1',
            'nome' => $ehCnpj ? 'Empresa Simulada '.($semente % 90 + 10).' LTDA' : 'Titular Simulado '.($semente % 90 + 10),
            'situacao_cadastral' => 'Regular',
            'pendencias_financeiras' => $restricoes,
            'protestos' => $restricoes > 1 ? 1 : 0,
            'valor_total_das_restricoes_cents' => $restricoes === 0 ? null : ($semente % 900_000),
            'consultas_recentes' => $semente % 7,
        ], fn ($v) => $v !== null), $this->protocolo($documento, $servico), (int) max($duracao, (microtime(true) - $inicio) * 1000));
    }

    public function nome(): string
    {
        return 'simulado';
    }

    /** Protocolo estavel, para o operador citar o mesmo numero em suporte. */
    private function protocolo(string $documento, Servico $servico): string
    {
        return 'SIM-'.strtoupper(substr(hash('sha256', $documento.$servico->id), 0, 12));
    }
}
