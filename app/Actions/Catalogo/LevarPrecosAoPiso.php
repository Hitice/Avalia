<?php

namespace App\Actions\Catalogo;

use App\Models\Catalogo;
use App\Models\Preco;
use App\Support\Auditar;
use App\Support\Margem;
use Illuminate\Support\Facades\DB;

/**
 * Sobe ao piso os precos que estao vendendo no prejuizo.
 *
 * A tela de edicao ja recusa preco abaixo do piso, mas essa guarda so vale
 * para quem edita servico a servico. Preco que entrou pela carga inicial da
 * tabela de referencia nunca passou por ela, e mudanca de imposto, de comissao
 * ou de custo do fornecedor move o piso de todos os servicos de uma vez, sem
 * reavaliar linha nenhuma. Por isso existe prejuizo em tabela que ninguem
 * editou.
 *
 * Nunca roda sozinha. Subir preco de tabela e decisao comercial, e ninguem
 * descobre um reajuste depois que o cliente reclamou: quem clica assume, e a
 * trilha guarda o que mudou.
 *
 * So o preco de venda muda. Custo do fornecedor e contrato dele, e nao se
 * conserta mexendo no nosso lado.
 */
class LevarPrecosAoPiso
{
    /**
     * Os precos que nao cobrem custo, imposto e comissao.
     *
     * @return \Illuminate\Support\Collection<int, array{preco: Preco, piso: int}>
     */
    public static function abaixoDoPiso(Catalogo $catalogo): \Illuminate\Support\Collection
    {
        return $catalogo->precos()
            ->with('servico')
            ->get()
            ->map(fn (Preco $preco) => [
                'preco' => $preco,
                'piso' => Margem::pisoCents($preco->custo_cents, $catalogo->imposto_bps, $catalogo->comissaoBps()),
            ])
            // Sem custo cadastrado nao ha piso conhecido, e chutar um seria
            // inventar prejuizo onde talvez nao exista.
            ->filter(fn (array $linha) => $linha['piso'] !== null && $linha['preco']->preco_cents < $linha['piso'])
            ->values();
    }

    /** @return int quantos precos foram corrigidos */
    public function __invoke(Catalogo $catalogo): int
    {
        $furados = self::abaixoDoPiso($catalogo);

        if ($furados->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($furados, $catalogo) {
            foreach ($furados as $linha) {
                $preco = $linha['preco'];
                $antes = $preco->preco_cents;

                $preco->update(['preco_cents' => $linha['piso']]);

                // Uma entrada por preco, e nao uma por lote: quem for conferir
                // um servico depois procura por ele, nao pela operacao.
                Auditar::registrar('preco.piso', $preco, [
                    'servico' => $preco->servico?->codigo,
                    'faixa_cents' => $preco->consumo_minimo_cents,
                    'de_cents' => $antes,
                    'para_cents' => $linha['piso'],
                ]);
            }

            $catalogo->touch();
        });

        return $furados->count();
    }
}
