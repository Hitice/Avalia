<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ConviteDeAcesso;
use App\Models\Cliente;
use App\Models\Staff;
use App\Support\Convite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Esqueci minha senha, sem depender de ninguem.
 *
 * Reusa o convite de acesso inteiro: o link assinado de 48h que morre no uso.
 * Nao existe um segundo mecanismo de redefinicao para divergir do primeiro.
 *
 * A resposta e a mesma exista ou nao a conta. Formulario de recuperacao que
 * responde diferente e um verificador gratuito de quem e cliente de quem, e
 * essa lista e exatamente o que um concorrente ou golpista quer montar.
 */
class RecuperacaoController extends Controller
{
    public function mostrar()
    {
        return view('paginas.acesso.esqueci');
    }

    public function enviar(Request $pedido)
    {
        $dados = $pedido->validate(['email' => ['required', 'email']]);

        [$conta, $guarda] = $this->contaPor($dados['email']);

        if ($conta !== null) {
            try {
                Mail::to($conta->email)->send(new ConviteDeAcesso(
                    $conta->nome ?? ($conta->responsavel_nome ?: $conta->razao_social),
                    Convite::link($conta, $guarda),
                    ehEmpresa: $guarda === 'empresa',
                ));
            } catch (\Throwable $e) {
                // Falha de envio nao pode virar resposta diferente: seria o
                // mesmo vazamento por outro caminho. Fica no log.
                report($e);
            }
        }

        return back()->with('ok', 'Se este e-mail estiver cadastrado, o link de redefinição foi enviado. Ele vale por '.Convite::HORAS_DE_VALIDADE.' horas.');
    }

    /** @return array{0: Staff|Cliente|null, 1: string} */
    private function contaPor(string $email): array
    {
        $staff = Staff::where('email', $email)->where('ativo', true)->first();

        if ($staff) {
            return [$staff, 'staff'];
        }

        $empresa = Cliente::where('email', $email)->first();

        if ($empresa && $empresa->podeEntrar()) {
            return [$empresa, 'empresa'];
        }

        return [null, ''];
    }
}
