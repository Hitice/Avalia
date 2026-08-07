<?php

namespace App\Support;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/** Centraliza o formato do rastro de auditoria e evita registros incompletos. */
final class Auditar
{
    /** @param array<string, mixed> $dados */
    public static function registrar(string $acao, ?Model $entidade = null, array $dados = []): Auditoria
    {
        $request = app()->runningInConsole() ? null : request();

        $campos = [
            'staff_id' => auth('staff')->id(),
            'acao' => $acao,
            'entidade_tipo' => $entidade ? $entidade::class : null,
            'entidade_id' => $entidade?->getKey(),
            'entidade_rotulo' => self::rotuloDe($entidade),
            'dados' => $dados,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'ocorreu_em' => now(),
        ];

        // Corrente de resumos: cada registro carrega o do anterior e o proprio.
        // Alterar ou remover uma linha no meio quebra a verificacao de todas as
        // seguintes. Nao impede a alteracao, porque a tabela continua sendo uma
        // tabela; impede que ela passe despercebida.
        $anterior = Auditoria::orderByDesc('id')->value('resumo');

        $campos['resumo_anterior'] = $anterior;
        $campos['resumo'] = self::resumo($campos, $anterior);

        $registro = Auditoria::create($campos);

        Log::channel('auditoria')->info($acao, [
            'entidade_tipo' => $registro->entidade_tipo,
            'entidade_id' => $registro->entidade_id,
        ]);

        return $registro;
    }

    /**
     * O nome da entidade, congelado no momento do registro.
     *
     * "Empresa #1" nao diz nada a quem le, e resolver o nome na leitura
     * falharia para registro de coisa ja removida. Nunca entra dado pessoal de
     * consumidor consultado: a trilha vive para sempre, e o documento
     * consultado tem prazo de retencao. Falha aqui nao pode derrubar a acao
     * de negocio que pediu o registro, dai o try/catch largo.
     *
     * Fica FORA do resumo encadeado: os registros antigos nao tem o campo, e
     * inclui-lo quebraria a corrente de tudo que ja foi escrito.
     */
    private static function rotuloDe(?Model $entidade): ?string
    {
        try {
            $rotulo = match (true) {
                $entidade instanceof \App\Models\Cliente => $entidade->razao_social,
                $entidade instanceof \App\Models\Staff => $entidade->nome,
                $entidade instanceof \App\Models\DocumentoLegal => $entidade->titulo,
                $entidade instanceof \App\Models\Campanha => $entidade->nome,
                $entidade instanceof \App\Models\Interessado => $entidade->empresa,
                $entidade instanceof \App\Models\Fatura => trim(($entidade->cliente?->razao_social ?? '').' · '.$entidade->competenciaRotulo(), ' ·'),
                $entidade instanceof \App\Models\CobrancaAsaas => $entidade->fatura?->cliente?->razao_social,
                $entidade instanceof \App\Models\Consulta => $entidade->servico?->nome,
                $entidade instanceof \App\Models\Adesao => $entidade->cliente?->razao_social,
                $entidade instanceof \App\Models\AceiteDocumento => $entidade->documento?->titulo,
                $entidade instanceof \App\Models\Conexao => \App\Support\Fornecedores::todos()[$entidade->fornecedor]['nome'] ?? $entidade->fornecedor,
                default => null,
            };

            return $rotulo !== null ? mb_substr($rotulo, 0, 150) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resumo de um registro, amarrado ao resumo do anterior.
     *
     * Entram so os campos que definem O QUE aconteceu. Ficam de fora ip e
     * navegador: eles descrevem a origem, mudam de formato entre ambientes e
     * fariam a corrente quebrar por motivo que nao e adulteracao.
     *
     * @param  array<string, mixed>  $campos
     */
    public static function resumo(array $campos, ?string $anterior): string
    {
        return hash('sha256', implode('|', [
            $anterior ?? '',
            $campos['staff_id'] ?? '',
            $campos['acao'],
            $campos['entidade_tipo'] ?? '',
            $campos['entidade_id'] ?? '',
            json_encode($campos['dados'] ?? [], JSON_UNESCAPED_UNICODE),
            $campos['ocorreu_em'] instanceof \DateTimeInterface
                ? $campos['ocorreu_em']->format('Y-m-d H:i:s')
                : (string) $campos['ocorreu_em'],
        ]));
    }

    /**
     * Percorre a trilha e devolve os registros cuja corrente nao fecha.
     *
     * Vazio significa que nada foi alterado nem removido desde que cada linha
     * foi escrita. Serve para conferir antes de entregar a trilha a alguem de
     * fora, que e quando a integridade dela e cobrada.
     *
     * Um limite honesto: apagar o ULTIMO registro nao quebra corrente nenhuma,
     * porque nao sobra elo seguinte para apontar para ele. Detectar isso exige
     * ancora fora do banco, como publicar o ultimo resumo em outro lugar todo
     * dia. Enquanto isso nao existir, a corrente protege o passado, e nao o
     * instante mais recente.
     *
     * @return list<int>
     */
    public static function conferir(): array
    {
        $quebrados = [];
        $anterior = null;

        Auditoria::orderBy('id')->chunk(500, function ($registros) use (&$quebrados, &$anterior) {
            foreach ($registros as $registro) {
                $esperado = self::resumo([
                    'staff_id' => $registro->staff_id,
                    'acao' => $registro->acao,
                    'entidade_tipo' => $registro->entidade_tipo,
                    'entidade_id' => $registro->entidade_id,
                    'dados' => $registro->dados,
                    'ocorreu_em' => $registro->ocorreu_em,
                ], $anterior);

                if ($registro->resumo !== $esperado || $registro->resumo_anterior !== $anterior) {
                    $quebrados[] = $registro->id;
                }

                $anterior = $registro->resumo;
            }
        });

        return $quebrados;
    }
}
