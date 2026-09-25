<?php

namespace App\Http\Controllers;

use App\Actions\Links\EncurtarLink;
use App\Models\Link;
use App\Support\Auditar;
use App\Support\Destino;
use App\Support\Dono;
use Illuminate\Http\Request;

/**
 * O encurtador de links da bancada.
 *
 * Mesma porta do QR dinamico, e de proposito: encurtador aberto a qualquer um
 * vira alvo de phishing em dias, e o dia em que `avaliaone.com.br` entrar numa
 * lista de bloqueio de antivirus ou de mensageiro, todas as etiquetas vendidas
 * param de abrir junto. O dominio e o mesmo, e o risco tambem.
 */
class LinkController extends Controller
{
    public function index(Request $pedido)
    {
        $busca = trim((string) $pedido->query('busca'));

        return view('paginas.etiquetas.links', [
            'links' => Dono::limitar(Link::query())
                ->when($busca !== '', fn ($consulta) => $consulta
                    ->where('codigo', mb_strtoupper($busca))
                    ->orWhere('titulo', 'like', "%{$busca}%")
                    ->orWhere('destino', 'like', "%{$busca}%"))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'busca' => $busca,
            // O limite util das tags que a casa usa. Fica no config porque e
            // numero de hardware, e muda quando a tag mudar.
            'bytesDaTag' => (int) config('etiquetas.bytes_da_tag'),
        ]);
    }

    public function salvar(Request $pedido, EncurtarLink $encurtar)
    {
        $dados = $pedido->validate([
            'destino' => ['required', 'string', 'max:'.Destino::TAMANHO_MAXIMO],
            'titulo' => ['nullable', 'string', 'max:120'],
        ]);

        $link = $encurtar($dados['destino'], $dados['titulo'] ?? null);

        return back()->with('ok', "Link {$link->codigo} pronto: {$link->url()} ({$link->bytes()} bytes).");
    }

    /**
     * Apaga o link que nunca foi usado.
     *
     * So com zero cliques. Link ja clicado esta gravado em alguma tag, num
     * cartao ou numa mensagem, e apagar devolveria o codigo ao sorteio: quem
     * encostasse o celular naquela tag depois cairia no destino de outra
     * pessoa. Para esse existe desligar, que tira do ar e mantem o numero.
     */
    public function excluir(Link $link)
    {
        abort_unless(Dono::pode($link), 404);

        if ($link->cliques > 0) {
            throw new \App\Exceptions\Recusa(
                'Este link já foi aberto '.$link->cliques.' vez(es), então pode estar gravado em alguma tag. Use "Desligar" em vez de apagar.'
            );
        }

        $codigo = $link->codigo;

        // Antes de apagar: depois nao ha entidade para o rastro apontar.
        Auditar::registrar('links.excluido', $link, ['codigo' => $codigo, 'destino' => $link->destino]);

        $link->delete();

        return back()->with('ok', "Link {$codigo} apagado.");
    }

    /** Liga e desliga. Nao apaga: o codigo pode estar gravado numa tag. */
    public function alternar(Link $link)
    {
        abort_unless(Dono::pode($link), 404);

        $link->update(['ativo' => ! $link->ativo]);

        Auditar::registrar('links.alternado', $link, ['ativo' => $link->ativo]);

        return back()->with('ok', $link->ativo ? 'Link no ar de novo.' : 'Link desligado.');
    }
}
