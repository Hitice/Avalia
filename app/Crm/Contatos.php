<?php

namespace App\Crm;

use App\Models\Contato;
use App\Models\Interacao;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Model;

/**
 * Acha ou cria o contato de um registro de frente e amarra os dois.
 *
 * Deduplica por documento, depois por WhatsApp ou telefone, depois por e-mail,
 * nesta ordem: documento ninguem inventa, telefone muda pouco, e-mail e o que
 * mais se repete entre pessoas diferentes de uma mesma empresa. Nome nunca
 * deduplica sozinho: duas padarias do Ze existem.
 */
final class Contatos
{
    /** @param  Model&TemContato  $entidade */
    public static function vincular(Model $entidade, ?string $origem = null): Contato
    {
        $dados = self::normalizar($entidade->dadosDeContato());
        $contato = self::achar($dados) ?? Contato::create($dados + ['origem' => $origem]);

        // O contato ganha o que ainda nao tinha, e nunca perde o que ja sabia.
        $contato->fill(array_filter($dados, fn ($v, $k) => $v !== null && $v !== '' && empty($contato->{$k}), ARRAY_FILTER_USE_BOTH))->save();

        $vinculo = Vinculo::firstOrCreate(
            ['entidade_tipo' => $entidade->getMorphClass(), 'entidade_id' => $entidade->getKey()],
            ['contato_id' => $contato->id, 'papel' => $entidade->papelNoCrm()],
        );

        if ($vinculo->wasRecentlyCreated) {
            self::anotar($contato, 'cadastro', 'Cadastrado como '.$entidade->papelNoCrm().($origem ? " ({$origem})" : ''));
        }

        if ($entidade->getAttribute('contato_id') !== $contato->id) {
            $entidade->forceFill(['contato_id' => $contato->id])->saveQuietly();
        }

        return $contato;
    }

    public static function anotar(Contato $contato, string $tipo, string $descricao, ?\DateTimeInterface $quando = null): Interacao
    {
        return $contato->interacoes()->create([
            'tipo' => $tipo,
            'descricao' => mb_substr($descricao, 0, 300),
            'ocorrido_em' => $quando ?? now(),
            'staff_id' => auth('staff')->id(),
        ]);
    }

    private static function achar(array $dados): ?Contato
    {
        foreach (['documento', 'whatsapp', 'email'] as $chave) {
            if (! empty($dados[$chave]) && ($achado = Contato::where($chave, $dados[$chave])->first())) {
                return $achado;
            }
        }

        if (! empty($dados['telefone']) && ($achado = Contato::where('whatsapp', $dados['telefone'])->orWhere('telefone', $dados['telefone'])->first())) {
            return $achado;
        }

        return null;
    }

    private static function normalizar(array $dados): array
    {
        $digitos = fn ($v) => ($d = preg_replace('/\D/', '', (string) $v)) !== '' && strlen($d) >= 8 ? $d : null;

        return [
            'nome' => mb_substr(trim((string) ($dados['nome'] ?? '')) ?: 'Sem nome', 0, 200),
            'documento' => ($d = preg_replace('/\D/', '', (string) ($dados['documento'] ?? ''))) !== '' ? $d : null,
            'email' => ($e = mb_strtolower(trim((string) ($dados['email'] ?? '')))) !== '' ? $e : null,
            'whatsapp' => $digitos($dados['whatsapp'] ?? null),
            'telefone' => $digitos($dados['telefone'] ?? null),
            'cidade' => trim((string) ($dados['cidade'] ?? '')) ?: null,
            'uf' => mb_strtoupper(trim((string) ($dados['uf'] ?? ''))) ?: null,
        ];
    }
}
