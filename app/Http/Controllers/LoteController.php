<?php

namespace App\Http\Controllers;

use App\Actions\Etiquetas\GerarLote;
use App\Models\LoteEtiqueta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * As tiragens de plaquinha.
 *
 * A tela de uma tiragem nao guarda arquivo nenhum: ela entrega a lista de
 * codigos ao navegador, que desenha os QR, monta o ZIP e baixa. O servidor da
 * Hostinger nao tem composer para instalar biblioteca de imagem, e nao
 * precisa: o desenho e determinado pelo codigo, e refazer o pacote em 2031 da
 * exatamente o mesmo resultado.
 */
class LoteController extends Controller
{
    /** Os tipos de plaquinha que uma tiragem pode ter. */
    private const TIPOS = ['qr' => 'Só QR Code', 'qr_nfc' => 'QR Code e tag NFC', 'nfc' => 'Só tag NFC'];

    public function index()
    {
        return view('paginas.etiquetas.lotes.index', [
            'lotes' => LoteEtiqueta::withCount('etiquetas')->orderByDesc('id')->paginate(20),
            'tipos' => self::TIPOS,
        ]);
    }

    public function criar()
    {
        return view('paginas.etiquetas.lotes.formulario', ['tipos' => self::TIPOS]);
    }

    public function salvar(Request $pedido, GerarLote $gerar)
    {
        $dados = $pedido->validate([
            'titulo' => ['required', 'string', 'max:120'],
            // O teto existe porque quem desenha e o navegador de quem pediu:
            // acima disto a aba fica presa por minutos montando imagem.
            'quantidade' => ['required', 'integer', 'min:1', 'max:'.config('etiquetas.lote_maximo')],
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
            'observacao' => ['nullable', 'string', 'max:255'],
        ]);

        $lote = $gerar($dados);

        return redirect()->route('etiquetas.lotes.ficha', $lote)
            ->with('ok', "Tiragem {$lote->codigo} aberta com {$lote->quantidade} plaquinhas.");
    }

    /**
     * A tiragem, com tudo que a bancada precisa.
     *
     * Os codigos vao para a tela em JSON, e nao numa nova requisicao: sao mil
     * linhas de tres campos, ja carregadas aqui, e mais uma ida ao servidor no
     * meio da geracao e mais um jeito de a tiragem falhar pela metade.
     */
    public function ficha(LoteEtiqueta $lote)
    {
        $etiquetas = $lote->etiquetas()->get()->map(fn ($etiqueta) => [
            'sequencia' => $etiqueta->sequencia,
            'codigo' => $etiqueta->codigo,
            // Maiuscula pelo modo alfanumerico do QR. Ver App\Support\CodigoCurto.
            'url' => $etiqueta->urlParaQr(),
            'arquivo' => $etiqueta->nomeDeArquivo(),
        ]);

        return view('paginas.etiquetas.lotes.ficha', [
            'lote' => $lote,
            'etiquetas' => $etiquetas,
            'tipos' => self::TIPOS,
        ]);
    }
}
