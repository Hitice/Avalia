<?php

namespace App\Http\Controllers;

use App\Support\Artigos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * O site institucional da casa.
 *
 * Paginas de leitura, sem sessao e sem estado: quem chega pelo dominio ve o
 * que a Avalia faz, quais negocios ela opera e por onde se fala com ela. Nao
 * redireciona quem tem sessao aberta, de proposito. A raiz e o site da
 * empresa, e nao a porta do sistema: um cliente logado que abre o endereco
 * para mostrar a empresa a alguem nao deve ser jogado dentro do proprio
 * painel. Quem quer trabalhar entra pela area do produtor.
 */
class SiteController extends Controller
{
    public function inicio()
    {
        return view('paginas.site.inicio', ['softwares' => config('softwares')]);
    }

    public function softwares()
    {
        return view('paginas.site.softwares', [
            'softwares' => config('softwares'),
            // Mesma lista da aba Serviços, lida aqui tambem: o dia em que um
            // servico entrar, ele aparece nos dois lugares sem ninguem mexer
            // em duas telas.
            'servicos' => self::servicosPublicos(),
        ]);
    }

    /**
     * Os servicos digitais, vendidos direto e com preco de tabela.
     *
     * Uma lista so, em config/servicos-digitais.php, alimenta a aba, este
     * indice e a secao dentro de /softwares. O servico ainda nao construido
     * fica registrado la e some daqui: vitrine com cartao "em breve" promete
     * data que ninguem marcou.
     */
    public function digitais()
    {
        return view('paginas.site.digitais.index', ['servicos' => self::servicosPublicos()]);
    }

    public function plaquinhas()
    {
        return view('paginas.site.digitais.plaquinhas', [
            'servico' => config('servicos-digitais.plaquinhas'),
        ]);
    }

    public function avaliacaoGoogle()
    {
        return view('paginas.site.digitais.avaliacao-google', [
            'servico' => config('servicos-digitais.avaliacao-google'),
        ]);
    }

    public function qrCode()
    {
        return view('paginas.site.digitais.qr-code', [
            'servico' => config('servicos-digitais.qr-code'),
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    public static function servicosPublicos(): array
    {
        return array_filter(config('servicos-digitais'), fn (array $servico) => $servico['publico']);
    }

    public function quemSomos()
    {
        return view('paginas.site.quem-somos');
    }

    public function blog()
    {
        return view('paginas.site.blog', ['artigos' => Artigos::todos()]);
    }

    public function artigo(string $artigo)
    {
        // Endereco que nao existe cai no 404 do site, e nao numa pagina meio
        // montada: o slug vem da URL, e sem esta guarda um endereco inventado
        // chegaria ao @include procurando uma view que nao existe.
        if (! Artigos::existe($artigo)) {
            throw new NotFoundHttpException;
        }

        return view('paginas.site.artigo', ['artigo' => Artigos::ficha($artigo)]);
    }

    public function contato()
    {
        return view('paginas.site.contato', ['assuntos' => self::ASSUNTOS]);
    }

    public function perguntas()
    {
        return view('paginas.site.perguntas');
    }

    public function privacidade()
    {
        return view('paginas.site.privacidade');
    }

    public function termos()
    {
        return view('paginas.site.termos');
    }

    /**
     * As paginas publicas de leitura, com a view de cada uma: o sitemap tira
     * dai o endereco e a data da ultima alteracao (a do arquivo da view). A
     * area do produtor e as telas de login ficam de fora: nao ha o que
     * indexar numa porta.
     */
    private const PAGINAS = [
        'inicio' => 'paginas.site.inicio',
        'site.softwares' => 'paginas.site.softwares',
        'digitais.index' => 'paginas.site.digitais.index',
        'digitais.plaquinhas' => 'paginas.site.digitais.plaquinhas',
        'digitais.qr' => 'paginas.site.digitais.qr-code',
        'digitais.avaliacao' => 'paginas.site.digitais.avaliacao-google',
        'site.quem-somos' => 'paginas.site.quem-somos',
        'site.blog' => 'paginas.site.blog',
        'site.contato' => 'paginas.site.contato',
        'site.perguntas' => 'paginas.site.perguntas',
        'site.privacidade' => 'paginas.site.privacidade',
        'site.termos' => 'paginas.site.termos',
        'cadastro-negocio' => 'paginas.site.cadastro-negocio',
        'credito' => 'paginas.credito',
        'cobranca' => 'paginas.cobranca',
    ];

    /**
     * O mapa do site para os buscadores.
     *
     * Gerado da mesma lista que a navegacao usa, e nao escrito a mao: o
     * sitemap escrito a parte e o primeiro arquivo a ficar velho, porque
     * ninguem lembra dele ao publicar uma pagina nova.
     */
    public function sitemap()
    {
        $enderecos = [];

        foreach (self::PAGINAS as $rota => $view) {
            $enderecos[] = ['loc' => route($rota), 'lastmod' => date('Y-m-d', (int) filemtime(view($view)->getPath()))];
        }

        foreach (Artigos::todos() as $artigo) {
            $enderecos[] = ['loc' => route('site.artigo', $artigo['slug']), 'lastmod' => Carbon::parse($artigo['data'])->toDateString()];
        }

        return response()
            ->view('paginas.site.sitemap', ['enderecos' => $enderecos])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * O robots.txt sai das rotas, e nao de uma lista escrita a mao: toda rota
     * atras de uma porta (auth, guest, assinada) ou fora do grupo web (as
     * leituras de placa e link) tem o primeiro segmento bloqueado. Rota nova
     * atras de login nasce bloqueada sem ninguem lembrar do arquivo.
     */
    public function robots()
    {
        $publicos = $fechados = [];

        foreach (Route::getRoutes() as $rota) {
            $segmento = strtok($rota->uri(), '/');

            if (! in_array('GET', $rota->methods(), true) || $segmento === false || str_starts_with($segmento, '{')) {
                continue;
            }

            $meios = $rota->gatherMiddleware();
            $fechada = ! in_array('web', $meios, true)
                || $rota->getName() === 'token'
                || collect($meios)->contains(fn (string $m) => preg_match('/^(auth:|guest:|sessao:|signed$|admin$)/', $m) === 1);

            $fechada ? $fechados['/'.$segmento] = true : $publicos['/'.$segmento] = true;
        }

        $bloqueios = [];

        foreach (array_diff(array_keys($fechados), array_keys($publicos)) as $caminho) {
            // "/termos" bloquearia "/termos-de-uso" por prefixo: quando um
            // caminho publico comeca igual, o bloqueio e exato ou com barra.
            $temPrefixoPublico = collect(array_keys($publicos))->contains(fn (string $p) => str_starts_with($p, $caminho.'-') || str_starts_with($p, $caminho.'.'));
            array_push($bloqueios, ...($temPrefixoPublico ? [$caminho.'$', $caminho.'/'] : [$caminho]));
        }

        sort($bloqueios);

        return response()
            ->view('paginas.site.robots', ['bloqueios' => $bloqueios])
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** O resumo da casa para assistentes de IA, no padrao llmstxt.org. */
    public function llms()
    {
        return response()
            ->view('paginas.site.llms', ['softwares' => config('softwares'), 'servicos' => self::servicosPublicos()])
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Os assuntos do formulario de contato.
     *
     * Lista fechada, e nao campo livre: o assunto decide para quem o pedido
     * vai, e "outro assunto" escrito de dez jeitos diferentes nao encaminha
     * nada. Mora na constante porque a tela desenha as opcoes e a validacao
     * confere a resposta contra a mesma lista.
     */
    public const ASSUNTOS = [
        'Chat e atendimento humanizados',
        'Automação de processos',
        'Análise de mercado',
        'Automação de cobranças',
        'Integração de sistemas',
        'Controle de produção e CRM',
        'Sites, SaaS e web apps',
        'Outro assunto',
    ];
}
