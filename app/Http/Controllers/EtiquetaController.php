<?php

namespace App\Http\Controllers;

use App\Actions\Etiquetas\AlternarEtiqueta;
use App\Actions\Etiquetas\GerarLote;
use App\Actions\Etiquetas\RenovarEtiqueta;
use App\Actions\Etiquetas\VenderEtiqueta;
use App\Enums\SituacaoEtiqueta;
use App\Models\Etiqueta;
use App\Models\LoteEtiqueta;
use App\Support\CodigoCurto;
use App\Support\Destino;
use App\Support\Dinheiro;
use Illuminate\Http\Request;

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
    /*
     * So QR, por enquanto.
     *
     * A coluna `tipo` continua na tabela e a tag NFC continua prevista: ela
     * usaria o MESMO endereco do QR, entao nada do que esta gravado aqui muda
     * quando ela voltar. O que saiu foi a escolha na tela, que pedia uma
     * decisao sobre algo que ainda nao existe.
     */
    private const TIPO = 'qr';

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

        // Com uma campanha escolhida, a tabela ganha o pacote dela: o ZIP
        // precisa da tiragem INTEIRA, e a tabela mostra 25 por pagina.
        $campanha = LoteEtiqueta::find($pedido->query('lote'));

        return view('paginas.etiquetas.index', [
            'etiquetas' => $etiquetas,
            // So os da pagina atual: a miniatura e desenhada no navegador, e
            // mandar mil codigos para desenhar 25 seria trabalho jogado fora.
            'miniaturas' => $etiquetas->getCollection()->map(fn (Etiqueta $etiqueta) => [
                'codigo' => $etiqueta->codigo,
                'url' => $etiqueta->urlParaQr(),
                'arquivo' => $etiqueta->nomeDeArquivo(),
            ])->values(),
            'lotes' => LoteEtiqueta::orderByDesc('id')->get(),
            'situacoes' => SituacaoEtiqueta::rotulos(),
            'campanha' => $campanha,
            'pacote' => $campanha?->etiquetas()->get()->map(fn (Etiqueta $etiqueta) => [
                'sequencia' => $etiqueta->sequencia,
                'codigo' => $etiqueta->codigo,
                // Maiuscula pelo modo alfanumerico do QR. Ver CodigoCurto.
                'url' => $etiqueta->urlParaQr(),
                'arquivo' => $etiqueta->nomeDeArquivo(),
            ]),
            'filtros' => ['busca' => $busca, 'situacao' => $pedido->query('situacao'), 'lote' => $pedido->query('lote')],
        ]);
    }

    /**
     * Gera os codigos de uma campanha.
     *
     * SEMPRE campanha, mesmo para um codigo so. O caminho e o mesmo dos cem:
     * a pessoa fica na tabela, escolhe a campanha no seletor e baixa o pacote.
     * Abrir uma tela diferente quando a quantidade e um faria a mesma tarefa
     * ter dois roteiros, e o de uma unidade seria o que ninguem lembra.
     */
    public function gerar(Request $pedido, GerarLote $lote)
    {
        $dados = $pedido->validate([
            'quantidade' => ['required', 'integer', 'min:1', 'max:'.config('etiquetas.lote_maximo')],
            'titulo' => ['nullable', 'string', 'max:120'],
        ]);

        $campanha = $lote([
            // Nome automatico quando nao vem um: a campanha precisa de rotulo
            // para aparecer no seletor, e "Campanha 7" e melhor que vazio.
            'titulo' => ($dados['titulo'] ?? null) ?: 'Campanha '.(LoteEtiqueta::count() + 1),
            'quantidade' => (int) $dados['quantidade'],
            'tipo' => self::TIPO,
            'observacao' => null,
        ]);

        return redirect()->route('etiquetas.index', ['lote' => $campanha->id])
            ->with('ok', "{$campanha->quantidade} código(s) gerado(s) em {$campanha->titulo}. Baixe o pacote e mande imprimir.");
    }

    /**
     * O passo de depois da venda: o codigo impresso, e para onde ele leva.
     *
     * Existe porque e assim que o trabalho acontece de verdade. Quem acabou de
     * vender tem a plaquinha na mao e le o codigo dela; procurar essa placa
     * numa lista de mil seria o caminho longo para a unica coisa que ele quer
     * fazer.
     */
    public function apontarPorCodigo(Request $pedido, VenderEtiqueta $vender)
    {
        $pedido->validate([
            'codigo' => ['required', 'string', 'max:20'],
            'destino' => ['required', 'string', 'max:'.Destino::TAMANHO_MAXIMO],
            'cliente_nome' => ['nullable', 'string', 'max:150'],
        ]);

        $codigo = CodigoCurto::normalizar($pedido->input('codigo'));
        $etiqueta = $codigo === '' ? null : Etiqueta::firstWhere('codigo', $codigo);

        if (! $etiqueta) {
            return back()->withInput()->with('erro',
                'Não achei o código '.mb_strtoupper(trim((string) $pedido->input('codigo')))
                .'. Confira na plaquinha: as letras I, L, O e U não são usadas.');
        }

        $vender($etiqueta, [
            'destino' => $pedido->input('destino'),
            'titulo' => null,
            'cliente_nome' => $pedido->input('cliente_nome'),
            'cliente_contato' => null,
            'valor_cents' => null,
        ]);

        return redirect()->route('etiquetas.ficha', $etiqueta)
            ->with('ok', "Pronto. {$etiqueta->codigo} agora leva para {$etiqueta->refresh()->destino}.");
    }

    public function ficha(Etiqueta $etiqueta)
    {
        return view('paginas.etiquetas.ficha', [
            'etiqueta' => $etiqueta->load(['lote', 'destinos' => fn ($q) => $q->orderByDesc('id'), 'renovacoes']),
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
}
