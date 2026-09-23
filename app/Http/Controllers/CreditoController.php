<?php

namespace App\Http\Controllers;

use App\Models\Campanha;

/**
 * A pagina do produto de pesquisa de score.
 *
 * Era a raiz do dominio. Virou pagina propria quando o site institucional
 * assumiu a porta: o visitante que chega pelo endereco esta conhecendo a casa,
 * e nao necessariamente este produto. Quem cai aqui ja escolheu o assunto, na
 * home ou na area do produtor, e por isso a pagina pode falar so dele.
 *
 * A pagina nao mostra preco, nome de fornecedor nem numero inventado de
 * operacao. E apresentacao, nao proposta: proposta tem dono, contrato e tabela
 * vigente, e mora atras do login.
 */
class CreditoController extends Controller
{
    public function __invoke()
    {
        // A campanha vigente veste o banner; sem campanha, o texto fixo. A
        // primeira que passa no filtro da vitrine: texto com preco ou
        // fornecedor nao sobe para a pagina publica.
        return view('paginas.credito', [
            'campanha' => Campanha::vigente()
                ->orderByDesc('inicio')
                ->get()
                ->first(fn (Campanha $c) => $c->seguraParaVitrine()),
        ]);
    }
}
