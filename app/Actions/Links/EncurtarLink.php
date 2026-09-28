<?php

namespace App\Actions\Links;

use App\Exceptions\Recusa;
use App\Models\Link;
use App\Support\Apelido;
use App\Support\Auditar;
use App\Support\CodigoCurto;
use App\Support\Destino;
use App\Support\Dono;

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

    public function __invoke(string $destino, ?string $titulo = null, ?string $apelido = null): Link
    {
        $endereco = Destino::normalizar($destino);

        if ($problema = Destino::problema($endereco)) {
            throw new Recusa($problema);
        }

        $apelido = $this->apelidoLivre($apelido);

        $existente = Dono::limitar(Link::query())->where('destino', $endereco)->first();

        // So reaproveita o que e da propria conta. Um endereco publico
        // encurtado por duas contas devolveria a segunda o codigo da primeira,
        // com os cliques dela junto.
        if ($existente) {
            // Endereco repetido com apelido novo ganha o apelido, em vez de
            // ser recusado: quem quer o nome bonito para um link que ja tem
            // nao esta pedindo um segundo link.
            if ($apelido && ! $existente->apelido) {
                $existente->update(['apelido' => $apelido]);
            }

            return $existente;
        }

        $link = Link::create(Dono::carimbo() + [
            'codigo' => $this->codigoInedito(),
            'apelido' => $apelido,
            'destino' => $endereco,
            'titulo' => $titulo,
            'staff_id' => auth('staff')->id(),
        ]);

        Auditar::registrar('links.encurtado', $link, ['destino' => $endereco]);

        return $link;
    }

    /**
     * O apelido, conferido contra as regras e contra quem ja o tem.
     *
     * A conferencia de quem ja tem e feita na aplicacao, e nao so pelo indice
     * unico: o indice erraria a resposta, porque MySQL compara sem diferenciar
     * caixa e o SQLite dos testes compara diferenciando. Aqui os dois se
     * comportam igual.
     */
    private function apelidoLivre(?string $entrada): ?string
    {
        $apelido = Apelido::normalizar($entrada);

        if ($apelido === '') {
            return null;
        }

        if ($problema = Apelido::problema($apelido)) {
            throw new Recusa($problema);
        }

        if (Link::whereRaw('LOWER(apelido) = ?', [mb_strtolower($apelido)])->exists()) {
            throw new Recusa('O apelido "'.$apelido.'" já está em uso por outro link.');
        }

        return $apelido;
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
