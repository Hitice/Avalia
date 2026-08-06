<?php

namespace Database\Seeders;

use App\Actions\Consumo\ExecutarConsulta;
use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Documentos\RegistrarAceiteDocumento;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\Catalogo;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\DocumentoLegal;
use App\Models\Plano;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * Dados ficticios para exercitar login, papeis e carteiras.
 *
 * Tres vendedores, tres empresas, uma carteira para cada um, e movimento
 * suficiente para que as telas nao aparecam vazias: uma empresa com
 * competencia fechada e paga, uma consumindo o mes corrente e uma sem uso.
 *
 * Nao roda em producao. A senha e a mesma para todo mundo e esta escrita aqui,
 * o que so e aceitavel porque estas contas existem para teste.
 *
 * E idempotente: roda de novo e atualiza em vez de duplicar.
 */
class DemonstracaoSeeder extends Seeder
{
    private const SENHA = 'admin123';

    /** Comissao diferente em cada um, para a taxa por vendedor ficar visivel. */
    private const VENDEDORES = [
        ['nome' => 'Vendedor 1', 'email' => 'vendedor1@avalia.com.br', 'comissao_pct' => 10],
        ['nome' => 'Vendedor 2', 'email' => 'vendedor2@avalia.com.br', 'comissao_pct' => 15],
        ['nome' => 'Vendedor 3', 'email' => 'vendedor3@avalia.com.br', 'comissao_pct' => 20],
    ];

    /** CNPJ com digito verificador valido, para o cadastro aceitar de volta. */
    private const EMPRESAS = [
        ['nome' => 'Empresa 1', 'cnpj' => '11111111000191', 'email' => 'empresa1@avalia.com.br'],
        ['nome' => 'Empresa 2', 'cnpj' => '22222222000191', 'email' => 'empresa2@avalia.com.br'],
        ['nome' => 'Empresa 3', 'cnpj' => '33333333000191', 'email' => 'empresa3@avalia.com.br'],
    ];

    public function run(): void
    {
        if (App::environment('production')) {
            $this->command->warn('Ambiente de producao: nenhum dado ficticio criado.');

            return;
        }

        $catalogo = Catalogo::vigente();

        if (! $catalogo) {
            $this->command->error('Sem catalogo. Rode o CatalogoSeeder antes.');

            return;
        }

        $planos = Plano::where('ativo', true)->orderBy('consumo_minimo_cents')->get();

        if ($planos->isEmpty()) {
            $this->command->error('Sem planos. Rode o PlanosSeeder antes.');

            return;
        }

        $vendedores = $this->vendedores();
        $empresas = $this->empresas($vendedores, $planos);

        // Aceite antes do movimento: documento obrigatorio pendente faz a acao
        // de consumo recusar a consulta, e a demonstracao nasceria sem uma
        // consulta sequer. Foi o que aconteceu enquanto isto nao existia.
        $this->aceites($empresas);

        $this->movimento($empresas);

        $this->command->info(sprintf(
            '%d vendedores e %d empresas prontos. Senha de todos: %s',
            count($vendedores),
            count($empresas),
            self::SENHA,
        ));
    }

    /** @return list<Staff> */
    private function vendedores(): array
    {
        return array_map(function (array $dados) {
            $vendedor = Staff::withTrashed()->firstOrNew(['email' => $dados['email']]);

            $vendedor->fill([
                'nome' => $dados['nome'],
                'papel' => 'vendedor',
                'comissao_pct' => $dados['comissao_pct'],
                'ativo' => true,
                'deleted_at' => null,
            ]);

            // So define a senha ao criar: rodar de novo nao pode reverter uma
            // senha que alguem trocou durante o teste.
            if (! $vendedor->exists) {
                $vendedor->senha = self::SENHA;
            }

            $vendedor->save();

            return $vendedor;
        }, self::VENDEDORES);
    }

    /**
     * Uma empresa por vendedor, em faixas diferentes.
     *
     * @param  list<Staff>  $vendedores
     * @return list<Cliente>
     */
    private function empresas(array $vendedores, $planos): array
    {
        return array_map(function (array $dados, int $i) use ($vendedores, $planos) {
            $empresa = Cliente::withTrashed()->firstOrNew(['email' => $dados['email']]);

            $empresa->fill([
                'razao_social' => $dados['nome'].' LTDA',
                'cnpj' => $dados['cnpj'],
                'situacao' => 'ativo',
                'plano_id' => $planos[$i % $planos->count()]->id,
                'vendedor_id' => $vendedores[$i]->id,
                'deleted_at' => null,
            ]);

            if (! $empresa->exists) {
                $empresa->senha = self::SENHA;
            }

            $empresa->save();

            return $empresa;
        }, self::EMPRESAS, array_keys(self::EMPRESAS));
    }

    /**
     * Movimento suficiente para as telas terem o que mostrar.
     *
     * Empresa 1 fecha e paga o mes passado, entao o financeiro tem baixa e a
     * carteira do Vendedor 1 tem comissao liberada. Empresa 2 consome o mes
     * corrente sem fechar. Empresa 3 fica sem uso, que e o caso do cliente que
     * paga o minimo sem consultar.
     *
     * @param  list<Cliente>  $empresas
     */
    private function movimento(array $empresas): void
    {
        [$primeira, $segunda] = $empresas;

        $mesPassado = now()->subMonth()->format('Y-m');

        if ($primeira->faturas()->where('competencia', $mesPassado)->doesntExist()) {
            $this->consultar($primeira, 40, $mesPassado);

            $fatura = app(FecharCompetencia::class)($primeira, $mesPassado)['fatura'];

            if ($fatura) {
                app(RegistrarLiquidacao::class)($fatura);
            }
        }

        if ($segunda->consultas()->where('competencia', Consulta::competenciaDe())->doesntExist()) {
            $this->consultar($segunda, 25, Consulta::competenciaDe());
        }
    }

    /**
     * Aceita, por cada empresa da demonstracao, os documentos obrigatorios.
     *
     * Empresa que nao aceitou nao consulta, e demonstracao que nao consulta nao
     * demonstra nada. Passa pela mesma acao que a tela usa, entao a trilha de
     * auditoria fica igual a de um aceite de verdade.
     *
     * @param  array<int, Cliente>  $empresas
     */
    private function aceites(array $empresas): void
    {
        $registrar = app(RegistrarAceiteDocumento::class);

        $obrigatorios = DocumentoLegal::where('ativo', true)->where('exige_aceite', true)->get();

        foreach ($empresas as $empresa) {
            foreach ($obrigatorios as $documento) {
                $registrar($empresa, $documento);
            }
        }
    }

    /**
     * Consultas passando pelo conector, como o cliente faria pelo portal.
     *
     * Uma em cada dez usa um documento que o simulado recusa, para as telas
     * mostrarem tambem o caso que nao deu certo: base de demonstracao so com
     * sucesso esconde justamente o que precisa ser conferido.
     */
    private function consultar(Cliente $empresa, int $quantidade, string $competencia): void
    {
        $precos = $empresa->plano->catalogo
            ->precos()
            ->where('consumo_minimo_cents', $empresa->plano->consumo_minimo_cents)
            ->whereHas('servico', fn ($q) => $q->where('ativo', true)->where('exige_liberacao', false))
            ->with('servico')
            ->take(4)
            ->get();

        if ($precos->isEmpty()) {
            return;
        }

        $executar = app(ExecutarConsulta::class);
        $antiga = $competencia !== Consulta::competenciaDe();

        for ($i = 0; $i < $quantidade; $i++) {
            $servico = $precos[$i % $precos->count()]->servico;

            // Documento valido termina em digito diferente de zero; um em cada
            // dez termina em zero e o simulado recusa.
            $documento = str_pad((string) (11144477730 + $i), 11, '0', STR_PAD_LEFT);

            $resultado = $executar(
                $empresa,
                $servico,
                $documento,
                'Análise de crédito para venda a prazo',
                'Operação da '.$empresa->razao_social,
            );

            // Recusa nao pode passar em silencio: a demonstracao existe para as
            // telas terem conteudo, e tela vazia parece funcionar.
            if ($resultado['erro']) {
                $this->command->error('Consulta recusada para '.$empresa->razao_social.': '.$resultado['erro']);

                return;
            }

            // Competência antiga: a consulta nasce no mês corrente e precisa ser
            // reposicionada, porque não há como pedir ao conector uma data.
            if ($antiga && $resultado['consulta']) {
                $resultado['consulta']->update([
                    'competencia' => $competencia,
                    'created_at' => now()->subMonth(),
                ]);
            }
        }
    }
}
