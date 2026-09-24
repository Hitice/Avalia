<?php

namespace App\Http\Controllers;

use App\Actions\Etiquetas\AlternarEtiqueta;
use App\Actions\Etiquetas\BaixarEtiqueta;
use App\Actions\Etiquetas\CriarEtiquetaAvulsa;
use App\Actions\Etiquetas\RenovarEtiqueta;
use App\Actions\Etiquetas\VenderEtiqueta;
use App\Enums\SituacaoEtiqueta;
use App\Models\Etiqueta;
use App\Models\LoteEtiqueta;
use App\Support\CodigoCurto;
use App\Support\Destino;
use App\Support\Dinheiro;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * As plaquinhas, uma a uma.
 *
 * E a tela onde se decide para onde aponta uma placa que esta no balcao de um
 * cliente. Cada acao daqui muda o que um desconhecido ve ao encostar o celular
 * numa plaquinha em campo, entao todas passam por Action, todas ficam na
 * auditoria, e nenhuma apaga nada.
 */
class EtiquetaController extends Controller
{
    private const TIPOS = ['qr' => 'Só QR Code', 'qr_nfc' => 'QR Code e tag NFC', 'nfc' => 'Só tag NFC'];

    public function index(Request $pedido)
    {
        $busca = trim((string) $pedido->query('busca'));
        $situacao = SituacaoEtiqueta::tentar($pedido->query('situacao'));

        $etiquetas = Etiqueta::query()
            ->with('lote')
            ->when($situacao, fn ($consulta) => $consulta->where('situacao', $situacao))
            ->when($pedido->query('lote'), fn ($consulta, $lote) => $consulta->where('lote_id', $lote))
            ->when($busca !== '', function ($consulta) use ($busca) {
                // O codigo e procurado normalizado: quem copia da placa digita
                // minusculo, e quem le do acrilico troca 1 por I.
                $codigo = CodigoCurto::normalizar($busca);

                $consulta->where(fn ($ou) => $ou
                    ->when($codigo !== '', fn ($q) => $q->orWhere('codigo', $codigo))
                    ->orWhere('titulo', 'like', "%{$busca}%")
                    ->orWhere('cliente_nome', 'like', "%{$busca}%")
                    ->orWhere('destino', 'like', "%{$busca}%"));
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('paginas.etiquetas.index', [
            'etiquetas' => $etiquetas,
            'lotes' => LoteEtiqueta::orderByDesc('id')->get(),
            'situacoes' => SituacaoEtiqueta::rotulos(),
            'tipos' => self::TIPOS,
            'filtros' => ['busca' => $busca, 'situacao' => $pedido->query('situacao'), 'lote' => $pedido->query('lote')],
        ]);
    }

    /** Um codigo so, sem tiragem: o QR dinamico vendido sem placa. */
    public function avulsa(Request $pedido, CriarEtiquetaAvulsa $criar)
    {
        $dados = $pedido->validate([
            'titulo' => ['nullable', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
        ]);

        $etiqueta = $criar($dados);

        return redirect()->route('etiquetas.ficha', $etiqueta)
            ->with('ok', "Código {$etiqueta->codigo} criado. Aponte para onde ele deve levar.");
    }

    public function ficha(Etiqueta $etiqueta)
    {
        return view('paginas.etiquetas.ficha', [
            'etiqueta' => $etiqueta->load(['lote', 'destinos' => fn ($q) => $q->orderByDesc('id'), 'renovacoes']),
            'tipos' => self::TIPOS,
            'acessos' => $etiqueta->acessos()->orderByDesc('dia')->limit(30)->get(),
        ]);
    }

    /** Vende e aponta. Serve a primeira vez e a troca de destino de anos depois. */
    public function apontar(Request $pedido, Etiqueta $etiqueta, VenderEtiqueta $vender)
    {
        $dados = $pedido->validate([
            // O motivo da recusa vem do proprio Destino: "endereco invalido"
            // faz a pessoa tentar a mesma coisa de novo.
            'destino' => ['required', 'string', 'max:'.Destino::TAMANHO_MAXIMO, function ($campo, $valor, $recusa) {
                if ($problema = Destino::problema($valor)) {
                    $recusa($problema);
                }
            }],
            'titulo' => ['nullable', 'string', 'max:120'],
            'cliente_nome' => ['nullable', 'string', 'max:150'],
            'cliente_contato' => ['nullable', 'string', 'max:150'],
            'valor' => ['nullable', 'string', 'max:20'],
        ]);

        $vender($etiqueta, [
            'destino' => $dados['destino'],
            'titulo' => $dados['titulo'] ?? null,
            'cliente_nome' => $dados['cliente_nome'] ?? null,
            'cliente_contato' => $dados['cliente_contato'] ?? null,
            'valor_cents' => Dinheiro::paraCentavos($dados['valor'] ?? null),
        ]);

        return back()->with('ok', 'Plaquinha apontada.');
    }

    public function alternar(Etiqueta $etiqueta, AlternarEtiqueta $alternar)
    {
        $alternar($etiqueta);

        return back()->with('ok', $etiqueta->situacao === SituacaoEtiqueta::Ativa
            ? 'Plaquinha no ar de novo.'
            : 'Plaquinha suspensa. Quem ler a placa vê o aviso, e não um erro.');
    }

    public function renovar(Request $pedido, Etiqueta $etiqueta, RenovarEtiqueta $renovar)
    {
        $pedido->validate(['valor' => ['nullable', 'string', 'max:20']]);

        $renovar($etiqueta, Dinheiro::paraCentavos($pedido->input('valor')));

        return back()->with('ok', 'Renovada até '.$etiqueta->refresh()->vence_em->format('d/m/Y').'.');
    }

    public function baixar(Request $pedido, Etiqueta $etiqueta, BaixarEtiqueta $baixar)
    {
        $dados = $pedido->validate(['motivo' => ['nullable', 'string', 'max:150']]);

        $baixar($etiqueta, $dados['motivo'] ?? null);

        return redirect()->route('etiquetas.index')
            ->with('ok', "Plaquinha {$etiqueta->codigo} baixada. O código não volta a circular.");
    }
}
