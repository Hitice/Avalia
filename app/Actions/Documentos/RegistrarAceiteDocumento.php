<?php

namespace App\Actions\Documentos;

use App\Models\AceiteDocumento;
use App\Models\Cliente;
use App\Models\DocumentoLegal;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/** Registra aceite idempotente com a versão e o hash do conteúdo exibido. */
class RegistrarAceiteDocumento
{
    public function __invoke(Cliente $cliente, DocumentoLegal $documento, ?string $responsavel = null): AceiteDocumento
    {
        abort_unless($documento->ativo && $documento->exige_aceite, 404);

        return DB::transaction(function () use ($cliente, $documento, $responsavel) {
            $aceite = AceiteDocumento::firstOrCreate(
                ['documento_id' => $documento->id, 'cliente_id' => $cliente->id],
                [
                    'responsavel' => $responsavel,
                    'versao' => $documento->versao,
                    'hash_conteudo' => $documento->hashConteudo(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'aceito_em' => now(),
                ],
            );

            if ($aceite->wasRecentlyCreated) {
                Auditar::registrar('documento.aceito', $documento, [
                    'cliente_id' => $cliente->id,
                    'versao' => $aceite->versao,
                    'hash_conteudo' => $aceite->hash_conteudo,
                ]);
            }

            return $aceite;
        });
    }
}
