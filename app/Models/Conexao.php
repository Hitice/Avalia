<?php

namespace App\Models;

use App\Support\Fornecedores;
use Illuminate\Database\Eloquent\Model;

/**
 * A conexao com um servico externo, com as credenciais criptografadas.
 *
 * O resto do sistema pergunta por Conexao::segredo() e Conexao::urlBase(), e
 * recebe null quando nao ha conexao ativa: quem chama decide o que fazer sem
 * credencial (o AsaasClient cai para o .env, o bureau cai para o simulado).
 *
 * Credencial nunca sai daqui em claro para log, trilha ou tela: a auditoria
 * registra QUAIS campos mudaram, nunca o valor.
 */
class Conexao extends Model
{
    protected $table = 'conexoes';

    protected $fillable = [
        'fornecedor', 'ambiente', 'credenciais', 'ativa',
        'testada_em', 'teste_ok', 'teste_detalhe',
    ];

    protected function casts(): array
    {
        return [
            'credenciais' => 'encrypted:array',
            'ativa' => 'boolean',
            'testada_em' => 'datetime',
            'teste_ok' => 'boolean',
        ];
    }

    /** Uma credencial da conexao ATIVA do fornecedor, ou null. */
    public static function segredo(string $fornecedor, string $chave): ?string
    {
        $valor = static::ativaDe($fornecedor)?->credenciais[$chave] ?? null;

        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    /** URL base da conexao ativa: a do ambiente escolhido, ou a contratada. */
    public static function urlBase(string $fornecedor): ?string
    {
        $conexao = static::ativaDe($fornecedor);

        if (! $conexao) {
            return null;
        }

        $ambientes = Fornecedores::todos()[$fornecedor]['ambientes'] ?? [];

        return $ambientes[$conexao->ambiente] ?? ($conexao->credenciais['base_url'] ?? null);
    }

    /**
     * A conexao ativa de um fornecedor.
     *
     * Engole banco indisponivel de proposito: a resolucao acontece dentro de
     * bindings do container, e um artisan rodando antes da primeira migration
     * nao pode quebrar por causa de uma tabela que ainda nao existe.
     */
    public static function ativaDe(string $fornecedor): ?self
    {
        try {
            return static::query()->where('fornecedor', $fornecedor)->where('ativa', true)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function configurada(): bool
    {
        return collect($this->credenciais ?? [])->filter(fn ($v) => is_string($v) && $v !== '')->isNotEmpty();
    }
}
