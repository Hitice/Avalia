<?php

namespace App\Services\Conectores;

use App\Contracts\ConectorBureau;
use App\Models\Servico;
use App\Support\Documento;
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

        return RespostaConsulta::sucesso([
            'simulado' => true,
            'documento' => Documento::formatarCnpj($documento) ?: $documento,
            'servico' => $servico->nome,
            'finalidade' => $finalidade,
            'consultado_em' => now()->toIso8601String(),
            'score' => 300 + ($semente % 701),
            'restricoes' => $semente % 3,
            'valor_restricoes_cents' => ($semente % 3) === 0 ? 0 : ($semente % 900_000),
        ], $this->protocolo($documento, $servico), (int) max($duracao, (microtime(true) - $inicio) * 1000));
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
