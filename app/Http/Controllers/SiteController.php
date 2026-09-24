<?php

namespace App\Http\Controllers;

use App\Support\Artigos;
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
     * O mapa do site para os buscadores.
     *
     * Gerado da mesma lista que a navegacao usa, e nao escrito a mao: o
     * sitemap escrito a parte e o primeiro arquivo a ficar velho, porque
     * ninguem lembra dele ao publicar uma pagina nova.
     *
     * So paginas publicas de leitura. A area do produtor e as telas de login
     * ficam de fora: nao ha o que indexar numa porta.
     */
    public function sitemap()
    {
        $enderecos = array_map(fn (string $rota) => route($rota), [
            'inicio',
            'site.softwares',
            'digitais.index',
            'digitais.plaquinhas',
            'digitais.qr',
            'site.quem-somos',
            'site.blog',
            'site.contato',
            'site.perguntas',
            'site.privacidade',
            'site.termos',
            'credito',
            'cobranca',
        ]);

        foreach (Artigos::todos() as $artigo) {
            $enderecos[] = route('site.artigo', $artigo['slug']);
        }

        return response()
            ->view('paginas.site.sitemap', ['enderecos' => $enderecos])
            ->header('Content-Type', 'application/xml');
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
