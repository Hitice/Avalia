<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Mail\ConviteDeAcesso;
use App\Models\Cliente;
use App\Models\Staff;
use App\Support\Auditar;
use App\Support\Convite;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Quem trabalha na Avalia: administracao e vendedores.
 *
 * E aqui que se define o percentual de comissao de cada vendedor. A taxa vale
 * do proximo fechamento em diante, porque a fatura congela o percentual usado
 * na emissao (PDD.md, secao 9).
 *
 * Nao ha exclusao, so desativacao: fatura, carteira e trilha de auditoria
 * apontam para o membro, e apagar deixaria historico orfao.
 */
class EquipeController extends Controller
{
    public function index()
    {
        return view('paginas.equipe.index', [
            // Removidos aparecem na lista com o proprio estado: sumir sem
            // rastro e como se perde a nocao de quem ja passou pela equipe.
            'membros' => Staff::withTrashed()->withCount('clientes')->orderBy('nome')->get(),
        ]);
    }

    /**
     * Tira a pessoa de circulacao, sem apagar: fatura, carteira e trilha
     * continuam apontando para ela, e o historico de comissao fica de pe.
     *
     * Nao remove a si mesmo (a sessao que remove nao pode serrar o proprio
     * galho) nem o superusuario, que e a chave reserva da operacao.
     */
    public function remover(Staff $membro)
    {
        abort_if($membro->id === auth('staff')->id(), 403, 'Você não pode remover a si mesmo.');
        abort_if($membro->ehSuper(), 403, 'O superusuário não pode ser removido pela tela.');

        $membro->revogaSessoes();
        $membro->delete();

        Auditar::registrar('equipe.removida', $membro, $this->rastreavel($membro));

        return redirect()->route('equipe.index')->with('ok', "{$membro->nome} removido da equipe.");
    }

    /**
     * Exclusao definitiva, so para quem ja foi removido e nunca operou.
     *
     * Vendedor com carteira ou fatura fica como removido para sempre: comissao
     * e historico apontam para ele. O botao existe para o cadastro de teste ou
     * duplicado.
     */
    public function excluir(int $id)
    {
        $removido = Staff::onlyTrashed()->findOrFail($id);

        $temHistorico = Cliente::withTrashed()->where('vendedor_id', $removido->id)->exists()
            || \App\Models\Fatura::where('vendedor_id', $removido->id)->exists()
            || \App\Models\Auditoria::where('staff_id', $removido->id)->exists();

        if ($temHistorico) {
            return back()->with('erro', 'Esta pessoa tem carteira, faturas ou ações registradas: o histórico aponta para ela e não se apaga. Mantenha como removida.');
        }

        Auditar::registrar('equipe.excluida', $removido, ['email' => $removido->email]);
        $removido->forceDelete();

        return back()->with('ok', "Cadastro de '{$removido->nome}' excluído em definitivo.");
    }

    public function restaurar(int $id)
    {
        $membro = Staff::withTrashed()->findOrFail($id);
        $membro->restore();

        Auditar::registrar('equipe.restaurada', $membro, $this->rastreavel($membro));

        return redirect()->route('equipe.index')->with('ok', "{$membro->nome} de volta à equipe.");
    }

    public function criar()
    {
        return view('paginas.equipe.formulario', [
            'membro' => new Staff(['papel' => 'vendedor', 'ativo' => true, 'comissao_pct' => 10]),
        ]);
    }

    public function salvar(StaffRequest $request)
    {
        $dados = $request->dados();

        // A conta nasce com uma senha aleatoria que ninguem conhece; o convite
        // por e-mail entrega o link para a propria pessoa definir a dela.
        $dados['senha'] = Str::password(40);

        $membro = Staff::create($dados);

        Auditar::registrar('equipe.criada', $membro, $this->rastreavel($membro));

        $aviso = $this->enviarConvite(true, $membro->email, $membro->nome, 'staff', $membro);

        return redirect()
            ->route('equipe.index')
            ->with('ok', "{$membro->nome} cadastrado.".($aviso ? '' : ' Convite de acesso enviado por e-mail.'))
            ->with('erro', $aviso);
    }

    /**
     * Reenvia o convite de redefinicao de senha, a pedido da administracao.
     *
     * Gera link novo de 48h. Nao mexe na senha atual: quem tem acesso continua
     * entrando ate definir a nova pelo link.
     */
    public function convite(Staff $membro)
    {
        $aviso = $this->enviarConvite(true, $membro->email, $membro->nome, 'staff', $membro);

        Auditar::registrar('equipe.convite_enviado', $membro, ['email' => $membro->email]);

        return back()
            ->with('ok', $aviso ? null : "Convite de redefinição enviado para {$membro->email}.")
            ->with('erro', $aviso);
    }

    /**
     * Envia o convite e devolve o aviso de falha, se houver.
     *
     * Falha de e-mail nao desfaz o cadastro: a conta existe e da para definir
     * uma senha manualmente pela edicao. O que nao pode e falhar em silencio,
     * com o admin certo de que a pessoa recebeu.
     */
    private function enviarConvite(bool $convidar, string $email, string $nome, string $guarda, $conta): ?string
    {
        if (! $convidar) {
            return null;
        }

        try {
            Mail::to($email)->send(new ConviteDeAcesso($nome, Convite::link($conta, $guarda), $guarda === 'empresa'));

            return null;
        } catch (\Throwable $e) {
            report($e);

            return 'O convite por e-mail não pôde ser enviado. Defina uma senha pela edição do cadastro ou tente de novo.';
        }
    }

    /**
     * O que muda de mao e precisa de rastro: papel, comissao e permissao
     * financeira. Corrigir um nome nao vira registro, para a trilha nao virar
     * ruido e esconder o que importa.
     *
     * @return array<string, mixed>
     */
    private function rastreavel(Staff $membro): array
    {
        return [
            'papel' => $membro->papel,
            'comissao_pct' => $membro->comissao_pct,
            'pode_financeiro' => (bool) $membro->pode_financeiro,
        ];
    }

    public function editar(Staff $membro)
    {
        return view('paginas.equipe.formulario', ['membro' => $membro]);
    }

    public function atualizar(StaffRequest $request, Staff $membro)
    {
        $antes = $this->rastreavel($membro);

        $membro->update($request->dados());

        // Trocar papel ou desativar muda o que a pessoa alcanca. A sessao aberta
        // continuaria com a permissao antiga ate o cookie expirar.
        if (! $membro->podeEntrar() || $antes['papel'] !== $membro->papel) {
            $membro->revogaSessoes();
        }

        if ($antes !== $this->rastreavel($membro)) {
            Auditar::registrar('equipe.alterada', $membro, $this->rastreavel($membro));
        }

        return redirect()
            ->route('equipe.index')
            ->with('ok', "Cadastro de {$membro->nome} atualizado.");
    }
}
