<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/**
 * A area do produtor: as portas das plataformas da casa, num lugar so.
 *
 * Pagina publica de proposito. Ela existe para quem ainda nao sabe por onde
 * entrar, e exigir login antes de mostrar as opcoes seria pedir a senha de uma
 * porta para dizer quais portas existem.
 *
 * Quem chega com sessao aberta ve primeiro um atalho para o proprio painel.
 * Atalho, e nao redirect: a mesma pessoa pode operar os dois negocios com
 * contas diferentes, e mandar ela direto para um painel esconderia a entrada
 * do outro.
 */
class AreaController extends Controller
{
    public function __invoke()
    {
        // Cada guard com sessao vira um atalho. A ordem segue a da pagina:
        // gestao, empresa e produtor.
        $painel = [
            'staff' => ['rotulo' => 'Ir para a gestão', 'rota' => 'painel'],
            'empresa' => ['rotulo' => 'Ir para o meu painel', 'rota' => 'empresa.painel'],
            'produtor' => ['rotulo' => 'Ir para o painel do produtor', 'rota' => 'produtor.painel'],
        ];

        $sessoes = [];

        foreach ($painel as $guarda => $destino) {
            if (Auth::guard($guarda)->check()) {
                $sessoes[] = ['rotulo' => $destino['rotulo'], 'href' => route($destino['rota'])];
            }
        }

        return view('paginas.site.area', ['sessoes' => $sessoes]);
    }
}
