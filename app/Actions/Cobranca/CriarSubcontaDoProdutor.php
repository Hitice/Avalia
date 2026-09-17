<?php

namespace App\Actions\Cobranca;

use App\Models\Produtor;
use App\Services\AsaasClient;
use App\Support\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Abre a subconta do produtor no provedor e guarda o que ela devolve.
 *
 * Roda uma vez por produtor. Chamar de novo com subconta ja criada devolve a
 * que existe em vez de abrir outra: subconta duplicada divide o historico do
 * mesmo produtor em dois lugares, e o split passa a mandar dinheiro para a
 * carteira errada sem erro nenhum aparecer.
 *
 * A chave da subconta e gravada na MESMA transacao em que o produtor e
 * aprovado. Se a gravacao falhar depois de o provedor ter criado a conta, a
 * transacao volta e o produtor continua pendente: melhor repetir a criacao do
 * que ficar com uma subconta orfa que ninguem sabe que existe.
 */
class CriarSubcontaDoProdutor
{
    public function __construct(private readonly AsaasClient $asaas) {}

    public function __invoke(Produtor $produtor, array $endereco): Produtor
    {
        if (filled($produtor->asaas_wallet_id)) {
            return $produtor;
        }

        $documento = Documento::normalizarCnpj($produtor->documento);

        $resposta = $this->asaas->criarSubconta([
            'name' => $produtor->nome,
            'email' => $produtor->email,
            'cpfCnpj' => $documento,
            'mobilePhone' => $produtor->whatsapp,
            'address' => $endereco['logradouro'] ?? null,
            'addressNumber' => $endereco['numero'] ?? null,
            'province' => $endereco['bairro'] ?? null,
            'postalCode' => preg_replace('/\D/', '', (string) ($endereco['cep'] ?? '')),
            // Pessoa juridica tem quatorze digitos; o provedor exige o tipo.
            'companyType' => strlen($documento) === 14 ? 'LIMITED' : null,
        ]);

        if (blank($resposta['walletId'] ?? null)) {
            throw new \RuntimeException('O provedor criou a conta sem devolver a carteira. Sem ela não há como repassar.');
        }

        return DB::transaction(function () use ($produtor, $resposta) {
            $produtor->update([
                'asaas_account_id' => $resposta['id'] ?? null,
                'asaas_wallet_id' => $resposta['walletId'],
                'asaas_api_key' => $resposta['apiKey'] ?? null,
                'situacao' => 'aprovado',
                'aprovado_em' => now(),
            ]);

            return $produtor->fresh();
        });
    }
}
