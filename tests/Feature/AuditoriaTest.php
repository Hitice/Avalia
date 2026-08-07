<?php

use App\Models\Cliente;
use App\Support\Auditar;
use App\Support\Rotulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * A trilha precisa ser legivel por quem administra, nao por quem programou.
 *
 * O que se trava aqui: toda acao registrada tem nome de negocio em Rotulos
 * (o teste varre o codigo, entao acao nova sem rotulo derruba a suite), e o
 * registro congela o nome da entidade para a linha se explicar sozinha mesmo
 * depois de a entidade sumir.
 */
it('tem rotulo de negocio para toda acao registrada no codigo', function () {
    $registradas = [];

    foreach (File::allFiles(app_path()) as $arquivo) {
        preg_match_all(
            "/Auditar::registrar\\([^;]*?'([a-z_.]+)'/s",
            $arquivo->getContents(),
            $achados,
        );

        // Ternario registra dois nomes na mesma chamada; o \G nao pega, entao
        // varre a linha inteira por todo literal com cara de acao.
        preg_match_all('/Auditar::registrar\\(.*/', $arquivo->getContents(), $linhas);

        foreach ($linhas[0] as $linha) {
            preg_match_all("/'([a-z_]+\\.[a-z_.]*)'/", $linha, $slugs);
            $registradas = [...$registradas, ...$slugs[1]];
        }
    }

    $registradas = array_unique($registradas);
    expect($registradas)->not->toBeEmpty();

    $rotuladas = array_keys(Rotulos::acoesDaTrilha());
    $semRotulo = [];

    foreach ($registradas as $acao) {
        // 'consulta.' e prefixo dinamico: vale se alguma acao rotulada comeca
        // com ele.
        $coberta = str_ends_with($acao, '.')
            ? collect($rotuladas)->contains(fn ($r) => str_starts_with($r, $acao))
            : in_array($acao, $rotuladas, true);

        if (! $coberta) {
            $semRotulo[] = $acao;
        }
    }

    expect($semRotulo)->toBe([]);
});

it('congela o nome da entidade no registro', function () {
    $empresa = Cliente::factory()->create(['razao_social' => 'Padaria Estrela LTDA']);

    $registro = Auditar::registrar('empresa.removida', $empresa);

    expect($registro->entidade_rotulo)->toBe('Padaria Estrela LTDA');

    // A linha continua se explicando depois de a empresa sumir de vez.
    $empresa->forceDelete();
    expect($registro->fresh()->entidade_rotulo)->toBe('Padaria Estrela LTDA');
});

it('mostra a trilha em linguagem de negocio, sem chave de codigo', function () {
    $empresa = Cliente::factory()->create(['razao_social' => 'Padaria Estrela LTDA']);
    Auditar::registrar('fatura.liquidada', $empresa, [
        'competencia' => '2026-07',
        'total_cents' => 97_990,
        'origem' => 'manual',
    ]);

    $html = admin()->get(route('auditoria'))->assertOk()->getContent();

    // A chave crua pode existir em URL de filtro; o que nao pode e virar
    // TEXTO exibido, dai a comparacao com os sinais de tag em volta.
    expect($html)->toContain('Pagamento confirmado')
        ->toContain('Padaria Estrela LTDA')
        ->toContain('Período')
        ->not->toContain('Ação registrada')
        ->not->toContain('total_cents')
        ->not->toContain('>fatura.liquidada<');
});
