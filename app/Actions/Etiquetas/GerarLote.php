<?php

namespace App\Actions\Etiquetas;

use App\Enums\SituacaoEtiqueta;
use App\Models\Etiqueta;
use App\Models\LoteEtiqueta;
use App\Support\Auditar;
use App\Support\CodigoCurto;
use App\Support\Dono;
use Illuminate\Support\Facades\DB;

/**
 * Abre uma tiragem de plaquinhas.
 *
 * A tiragem inteira nasce numa transacao so. Gerar metade e falhar deixaria
 * codigos orfaos no banco que nunca serao impressos, e eles ficariam ocupando
 * numero de sequencia num lote que ninguem consegue fechar.
 *
 * Nenhuma imagem e guardada. O desenho do QR e determinado pelo codigo, entao
 * o pacote da tiragem pode ser refeito identico daqui a cinco anos a partir
 * desta mesma lista, quando a grafica pedir de novo ou o HD queimar.
 */
class GerarLote
{
    /** Quantas rodadas de sorteio antes de desistir. */
    private const TENTATIVAS = 20;

    /**
     * @param  array{titulo: string, quantidade: int, tipo: string, observacao: ?string}  $dados
     */
    public function __invoke(array $dados): LoteEtiqueta
    {
        $quantidade = (int) $dados['quantidade'];
        $codigos = $this->codigosIneditos($quantidade);

        return DB::transaction(function () use ($dados, $quantidade, $codigos) {
            $lote = LoteEtiqueta::create([
                // Sequencial e visivel de proposito: e o numero que a bancada
                // usa para falar da tiragem ("o lote 7"), e ninguem decora
                // codigo sorteado. Nada aqui e adivinhavel de fora, porque a
                // tela e da administracao.
                'codigo' => 'LT-'.str_pad((string) ((LoteEtiqueta::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT),
                'titulo' => $dados['titulo'],
                'quantidade' => $quantidade,
                'tipo' => $dados['tipo'],
                'observacao' => $dados['observacao'] ?? null,
                'staff_id' => auth('staff')->id(),
            ]);

            $agora = now();

            // Insercao em bloco, e nao mil `create`. Mil viagens ao banco numa
            // hospedagem compartilhada estouram o tempo da requisicao muito
            // antes de a tiragem terminar.
            collect($codigos)
                ->map(fn (string $codigo, int $posicao) => Dono::carimbo() + [
                    'codigo' => $codigo,
                    'lote_id' => $lote->id,
                    'sequencia' => $posicao + 1,
                    'tipo' => $lote->tipo,
                    'situacao' => SituacaoEtiqueta::EmBranco->value,
                    'staff_id' => $lote->staff_id,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ])
                ->chunk(200)
                ->each(fn ($bloco) => Etiqueta::insert($bloco->values()->all()));

            Auditar::registrar('etiquetas.lote.gerado', $lote, ['quantidade' => $quantidade]);

            return $lote;
        });
    }

    /**
     * Codigos sorteados que ainda nao existem.
     *
     * Sao 1,07 bilhao de combinacoes, entao repeticao e rara, mas nao e
     * impossivel: o aniversario de mil sorteios num bilhao ja da chance
     * pequena e real. Conferir em bloco custa uma consulta por rodada; deixar
     * passar custa duas plaquinhas com o mesmo endereco em balcoes diferentes,
     * e nao ha conserto depois de impressas.
     *
     * @return list<string>
     */
    private function codigosIneditos(int $quantidade): array
    {
        $escolhidos = [];

        for ($rodada = 0; $rodada < self::TENTATIVAS; $rodada++) {
            $faltam = $quantidade - count($escolhidos);

            if ($faltam <= 0) {
                return array_slice($escolhidos, 0, $quantidade);
            }

            $candidatos = array_values(array_diff(
                array_unique(array_map(fn () => CodigoCurto::sortear(), range(1, $faltam))),
                $escolhidos,
            ));

            $usados = Etiqueta::whereIn('codigo', $candidatos)->pluck('codigo')->all();

            $escolhidos = array_merge($escolhidos, array_values(array_diff($candidatos, $usados)));
        }

        throw new \RuntimeException('Não foi possível sortear códigos inéditos suficientes.');
    }
}
