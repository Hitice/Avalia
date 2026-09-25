<?php

namespace App\Http\Controllers;

use App\Actions\Links\EncurtarLink;
use App\Models\Link;
use App\Support\Auditar;
use App\Support\Destino;
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
            'links' => Link::query()
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

    /** Liga e desliga. Nao apaga: o codigo pode estar gravado numa tag. */
    public function alternar(Link $link)
    {
        $link->update(['ativo' => ! $link->ativo]);

        Auditar::registrar('links.alternado', $link, ['ativo' => $link->ativo]);

        return back()->with('ok', $link->ativo ? 'Link no ar de novo.' : 'Link desligado.');
    }
}
