<?php

namespace App\Console\Commands;

use App\Actions\Etiquetas\VenderEtiqueta;
use App\Enums\SituacaoNegocio;
use App\Models\Etiqueta;
use App\Models\Link;
use App\Models\Negocio;
use Illuminate\Console\Command;

/**
 * Poe na base de negocios o que a casa ja ativou antes de a lista existir:
 * os links de avaliacao gerados soltos, e as placas vendidas com nome de
 * cliente e sem negocio. Repetir nao duplica.
 */
class LastrearNegocios extends Command
{
    protected $signature = 'avalia:lastrear-negocios {--simular : mostra as contagens, sem gravar}';

    protected $description = 'Cria negocios para os links de avaliacao e as placas vendidas que ainda nao tem um';

    public function handle(VenderEtiqueta $vender): int
    {
        $links = Link::where('titulo', 'like', 'Avaliação no Google: %')
            ->whereNotIn('id', Negocio::whereNotNull('link_avaliacao_id')->select('link_avaliacao_id'))->get();
        $placas = Etiqueta::whereNotNull('vendida_em')->whereNull('negocio_id')->whereNotNull('cliente_nome')->get();

        $this->line($links->count().' links sem negócio, '.$placas->count().' placas vendidas sem negócio');

        if ($this->option('simular')) {
            return self::SUCCESS;
        }

        foreach ($links as $link) {
            parse_str((string) parse_url((string) $link->destino, PHP_URL_QUERY), $query);
            $placeId = (string) ($query['placeid'] ?? '');
            $nome = trim(substr((string) $link->titulo, strlen('Avaliação no Google: ')));

            $negocio = ($placeId !== '' ? Negocio::firstWhere('place_id', $placeId) : null)
                ?? Negocio::create(['nome' => $nome ?: 'Sem nome', 'situacao' => SituacaoNegocio::Recebido->value, 'origem' => 'link', 'vendedor_id' => $link->staff_id]);
            $negocio->update(['place_id' => $placeId ?: $negocio->place_id, 'link_avaliacao_id' => $link->id]);
        }

        foreach ($placas as $placa) {
            $vender->registrarNaBase($placa);
        }

        $this->line('negócios agora: '.Negocio::count());

        return self::SUCCESS;
    }
}
