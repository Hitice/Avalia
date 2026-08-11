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
        $temScr = stripos($servico->nome, 'SCR') !== false;
        $profundo = (bool) preg_match('/prime|completo|completa|top|plus/i', $servico->nome);

        // As chaves sao as CANONICAS do laudo (App\Support\Laudo::BLOCOS), e
        // isso nao e capricho: com chave propria o resultado caia inteiro em
        // "Outras informacoes" e a tela dizia "nao contempla restricoes" com a
        // restricao na linha de cima. O simulado existe para exercitar o fluxo
        // real, entao precisa produzir o formato real.
        //
        // O que a tela ja mostra (documento, servico, finalidade, data) fica de
        // fora do laudo: campo repetido em bloco de resultado e ruido.
        // O laudo profundo espelha a ESTRUTURA do relatorio de mercado
        // (Receita, quadro societario, protestos com valor, SCR), sem a
        // redundancia dele: contagem de contatos em vez de vinte paginas de
        // telefone. Servico raso continua entregando o recorte raso, porque o
        // simulado existe para o formato de cada produto parecer o real.
        $protestos = $restricoes > 1 ? 1 + ($semente % 2) : 0;
        $valorProtestos = $protestos > 0 ? 120_000 + ($semente % 800_000) : null;

        $laudo = [
            'simulado' => true,
            'score' => 300 + ($semente % 701),
            'modelo_do_score' => 'SIMULADO_V1',
            'nome' => $ehCnpj ? 'Empresa Simulada '.($semente % 90 + 10).' LTDA' : 'Titular Simulado '.($semente % 90 + 10),
            'situacao_cadastral' => 'Regular',
            'pendencias_financeiras' => $restricoes,
            'protestos' => $protestos,
            'valor_dos_protestos_cents' => $valorProtestos,
            'ultimo_protesto_em' => $protestos > 0 ? now()->subDays(30 + $semente % 400)->format('d/m/Y') : null,
            'valor_total_das_restricoes_cents' => $restricoes === 0 ? null : ($semente % 900_000) + (int) $valorProtestos,
            'consultas_recentes' => $semente % 7,
        ];

        if ($profundo && $ehCnpj) {
            $laudo += [
                'nome_fantasia' => 'Marca Simulada '.($semente % 90 + 10),
                'data_de_fundacao' => (2000 + $semente % 20).'-0'.(1 + $semente % 9).'-15',
                'cnae_principal' => '6190-6/01 · Provedores de acesso às redes de comunicações',
                'natureza_juridica' => 'Sociedade Empresária Limitada',
                'capital_social_cents' => (50 + $semente % 400) * 100_000,
                'faixa_de_funcionarios' => ['Até 19', 'De 20 a 49', 'De 50 a 99', 'Acima de 100'][$semente % 4].' colaboradores',
                'tempo_de_atuacao' => ['5 a 10', '11 a 20', '21 a 30'][$semente % 3].' anos',
                'matriz_ou_filial' => 'Matriz',
                'faturamento_presumido' => ['Até R$ 1 milhão', 'De R$ 1 a 2,5 milhões', 'De R$ 2,5 a 10 milhões'][$semente % 3],
                'quadro_societario' => (1 + $semente % 3).' administradores · '.(1 + $semente % 4).' sócios',
                'contatos_localizados' => (2 + $semente % 40).' telefones · '.(1 + $semente % 20).' e-mails · '.(1 + $semente % 4).' endereços',
            ];
        }

        if ($profundo && ! $ehCnpj) {
            $laudo += [
                'data_de_nascimento' => (1960 + $semente % 40).'-0'.(1 + $semente % 9).'-20',
                'contatos_localizados' => (1 + $semente % 6).' telefones · '.(1 + $semente % 3).' e-mails',
            ];
        }

        if ($temScr) {
            $laudo += [
                'scr_score' => 300 + (($semente >> 3) % 701),
                'relacionamento_bancario_desde' => (string) (2005 + $semente % 18),
                'instituicoes_com_relacionamento' => 1 + $semente % 6,
                'operacoes_de_credito' => 5 + $semente % 30,
                'carteira_ativa_cents' => (200 + $semente % 2000) * 100_000,
                'creditos_vencidos_cents' => ($semente % 5) === 0 ? ($semente % 90) * 10_000 : 0,
                'prejuizo_cents' => 0,
                'limites_de_credito_cents' => (100 + (($semente >> 2) % 900)) * 100_000,
            ];
        }

        return RespostaConsulta::sucesso(
            array_filter($laudo, fn ($v) => $v !== null),
            $this->protocolo($documento, $servico),
            (int) max($duracao, (microtime(true) - $inicio) * 1000),
        );
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
