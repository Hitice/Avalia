<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use Illuminate\Support\Facades\Auth;

/**
 * A porta do dominio.
 *
 * Visitante ve a apresentacao; quem tem sessao segue para o proprio painel sem
 * passar por ela. O redirect nao valida a sessao de proposito: quem valida e o
 * middleware da rota de destino, e validar aqui tambem seria a mesma regra em
 * dois lugares, esperando divergir.
 *
 * A pagina nao mostra preco, nome de fornecedor nem numero inventado de
 * operacao. E apresentacao, nao proposta: proposta tem dono, contrato e tabela
 * vigente, e mora atras do login.
 */
class InicioController extends Controller
{
    public function __invoke()
    {
        if (Auth::guard('staff')->check()) {
            return redirect()->route('painel');
        }

        if (Auth::guard('empresa')->check()) {
            return redirect()->route('empresa.painel');
        }

        // A campanha vigente veste o banner; sem campanha, o texto fixo. A
        // primeira que passa no filtro da vitrine: texto com preco ou
        // fornecedor nao sobe para a pagina publica.
        return view('paginas.inicio', [
            'campanha' => Campanha::vigente()
                ->orderByDesc('inicio')
                ->get()
                ->first(fn (Campanha $c) => $c->seguraParaVitrine()),
        ]);
    }
}
