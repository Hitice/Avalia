<?php

namespace App\Http\Controllers;

use App\Models\Conexao;
use App\Services\AsaasClient;
use App\Support\Auditar;
use App\Support\Fornecedores;
use Illuminate\Http\Request;

/**
 * As conexoes com servicos externos, num lugar so.
 *
 * Credencial entra criptografada no banco e nunca volta para a tela: campo
 * secreto preenchido mostra so que existe, e em branco na edicao significa
 * "manter o que esta". A auditoria registra QUAIS campos mudaram, nunca o
 * valor. Na hospedagem compartilhada o .env so se edita por SSH, entao trocar
 * uma chave de API nao pode depender de acesso ao servidor.
 */
class ConexaoController extends Controller
{
    public function index()
    {
        return view('paginas.conexoes.index', [
            'fornecedores' => Fornecedores::todos(),
            'conexoes' => Conexao::all()->keyBy('fornecedor'),
        ]);
    }

    public function atualizar(Request $pedido, string $fornecedor)
    {
        abort_unless(Fornecedores::existe($fornecedor), 404);

        $definicao = Fornecedores::de($fornecedor);
        $conexao = Conexao::firstOrNew(['fornecedor' => $fornecedor]);
        $credenciais = $conexao->credenciais ?? [];
        $mudou = [];

        foreach ($definicao['campos'] as $campo) {
            $valor = trim((string) $pedido->input('campo_'.$campo['chave'], ''));

            // Segredo em branco mantem o atual: a tela nunca devolve o valor,
            // entao branco e "nao mexi", nao "apague".
            if ($valor === '' && ! empty($campo['secreto'])) {
                continue;
            }

            if (($credenciais[$campo['chave']] ?? '') !== $valor) {
                $credenciais[$campo['chave']] = $valor;
                $mudou[] = $campo['chave'];
            }
        }

        $ambientes = array_keys($definicao['ambientes'] ?? []);
        $ambiente = $pedido->input('ambiente', $conexao->ambiente ?? 'homologacao');

        if ($ambientes !== [] && in_array($ambiente, $ambientes, true) && $ambiente !== $conexao->ambiente) {
            $conexao->ambiente = $ambiente;
            $mudou[] = 'ambiente';
        }

        $conexao->credenciais = $credenciais;

        // Credencial mudou, teste anterior nao vale mais nada.
        if ($mudou !== []) {
            $conexao->forceFill(['testada_em' => null, 'teste_ok' => null, 'teste_detalhe' => null]);
        }

        $conexao->save();

        if ($mudou !== []) {
            Auditar::registrar('conexao.atualizada', $conexao, ['fornecedor' => $fornecedor, 'campos' => $mudou]);
        }

        return back()->with('ok', $mudou === []
            ? 'Nada para alterar em '.$definicao['nome'].'.'
            : 'Conexão com '.$definicao['nome'].' atualizada.');
    }

    public function alternar(string $fornecedor)
    {
        abort_unless(Fornecedores::existe($fornecedor), 404);

        $conexao = Conexao::firstOrNew(['fornecedor' => $fornecedor]);
        $nome = Fornecedores::de($fornecedor)['nome'];

        if (! $conexao->ativa && ! $conexao->configurada()) {
            return back()->with('erro', 'Preencha as credenciais de '.$nome.' antes de ativar.');
        }

        $conexao->ativa = ! $conexao->ativa;
        $conexao->save();
        Auditar::registrar($conexao->ativa ? 'conexao.ativada' : 'conexao.desativada', $conexao, ['fornecedor' => $fornecedor]);

        return back()->with('ok', $conexao->ativa
            ? 'Conexão com '.$nome.' ativada.'
            : 'Conexão com '.$nome.' desativada.');
    }

    /**
     * Prova a credencial contra o servico de verdade e grava o resultado.
     *
     * "Funcionava as 14h de ontem" e informacao de diagnostico: o carimbo fica
     * na tela para o operador saber quando a conexao foi conferida por ultimo.
     */
    public function testar(string $fornecedor, AsaasClient $asaas, \App\Services\BoaVistaClient $boaVista)
    {
        abort_unless(Fornecedores::existe($fornecedor), 404);

        $conexao = Conexao::firstOrNew(['fornecedor' => $fornecedor]);

        if (! $conexao->configurada()) {
            return back()->with('erro', 'Preencha as credenciais antes de testar.');
        }

        [$ok, $detalhe] = match ($fornecedor) {
            'asaas' => $asaas->testar(),
            'boa-vista' => $boaVista->testar(),
            default => [null, 'Este fornecedor ainda não tem teste automático: a primeira consulta real fará a prova.'],
        };

        if ($ok === null) {
            return back()->with('erro', $detalhe);
        }

        $conexao->forceFill(['testada_em' => now(), 'teste_ok' => $ok, 'teste_detalhe' => $detalhe])->save();
        Auditar::registrar('conexao.testada', $conexao, ['fornecedor' => $fornecedor, 'ok' => $ok]);

        return back()->with($ok ? 'ok' : 'erro', $detalhe);
    }
}
