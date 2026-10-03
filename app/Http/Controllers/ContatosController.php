<?php

namespace App\Http\Controllers;

use App\Models\Contato;
use Illuminate\Http\Request;

class ContatosController extends Controller
{
    public function index(Request $pedido)
    {
        $busca = trim((string) $pedido->query('busca', ''));
        $papel = (string) $pedido->query('papel', '');
        $digitos = preg_replace('/\D/', '', $busca);

        $contatos = Contato::query()
            ->when($busca !== '', fn ($q) => $q->where(fn ($b) => $b
                ->where('nome', 'like', "%{$busca}%")
                ->orWhere('email', 'like', "%{$busca}%")
                ->when(strlen($digitos) >= 4, fn ($d) => $d->orWhere('documento', 'like', "%{$digitos}%")->orWhere('whatsapp', 'like', "%{$digitos}%"))))
            ->when($papel !== '', fn ($q) => $q->whereHas('vinculos', fn ($v) => $v->where('papel', $papel)))
            ->with('vinculos')->withCount('interacoes')
            ->orderBy('nome')->paginate(30)->appends($pedido->except('page'));

        return view('paginas.crm.contatos', [
            'contatos' => $contatos,
            'filtros' => ['busca' => $busca, 'papel' => $papel],
            'papeis' => ['cliente' => 'Cliente do One', 'negocio' => 'Negócio do Sales', 'produtor' => 'Produtor do Gestor', 'lead' => 'Lead', 'interessado' => 'Interessado'],
        ]);
    }

    public function ver(Contato $contato)
    {
        return view('paginas.crm.contato', [
            'contato' => $contato->load(['vinculos.entidade', 'interacoes.staff:id,nome']),
        ]);
    }
}
