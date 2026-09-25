<?php

namespace App\Actions\Links;

use App\Exceptions\Recusa;
use App\Models\Link;
use App\Support\Auditar;
use App\Support\CodigoCurto;
use App\Support\Destino;

/**
 * Guarda um endereco longo atras de um codigo curto.
 *
 * O destino passa pela mesma conferencia da etiqueta, e pela mesma razao: e um
 * campo de texto que manda um desconhecido para fora do dominio sem clique
 * intermediario. `javascript:` e `data:` nao entram, endereco sem dominio nao
 * entra, e link apontando para link e laco.
 *
 * Endereco repetido devolve o codigo que ja existe, em vez de criar outro.
 * Dois codigos para o mesmo lugar dividiriam a contagem de cliques ao meio e
 * ninguem saberia por que os numeros nao batem.
 */
class EncurtarLink
{
    private const TENTATIVAS = 20;

    public function __invoke(string $destino, ?string $titulo = null): Link
    {
        $endereco = Destino::normalizar($destino);

        if ($problema = Destino::problema($endereco)) {
            throw new Recusa($problema);
        }

        if ($existente = Link::firstWhere('destino', $endereco)) {
            return $existente;
        }

        $link = Link::create([
            'codigo' => $this->codigoInedito(),
            'destino' => $endereco,
            'titulo' => $titulo,
            'staff_id' => auth('staff')->id(),
        ]);

        Auditar::registrar('links.encurtado', $link, ['destino' => $endereco]);

        return $link;
    }

    private function codigoInedito(): string
    {
        for ($tentativa = 0; $tentativa < self::TENTATIVAS; $tentativa++) {
            $codigo = CodigoCurto::sortear();

            if (! Link::where('codigo', $codigo)->exists()) {
                return $codigo;
            }
        }

        throw new \RuntimeException('Não foi possível sortear um código inédito.');
    }
}
