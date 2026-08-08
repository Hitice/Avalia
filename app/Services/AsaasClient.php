<?php

namespace App\Services;

use App\Models\Conexao;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente isolado para que cobrança não dependa de tela ou webhook.
 *
 * A credencial vem da tela de Conexões (banco, criptografada); o .env continua
 * valendo como reserva para instalações que ainda o usam. A chave e a URL
 * andam juntas: chave de teste só funciona na URL de teste, e é o ambiente da
 * conexão que escolhe as duas.
 */
class AsaasClient
{
    public function configurado(): bool
    {
        return filled($this->chave());
    }

    public function criarCliente(array $dados): array
    {
        return $this->enviar('/customers', $dados);
    }

    public function criarCobranca(array $dados): array
    {
        return $this->enviar('/payments', $dados);
    }

    /** Os dados atuais de uma cobranca, para reemitir link e boleto. */
    public function cobranca(string $id): array
    {
        $resposta = $this->http()->get('/payments/'.$id);

        return $resposta->successful() ? ($resposta->json() ?? []) : [];
    }

    /**
     * POST que transforma recusa do provedor em mensagem legivel.
     *
     * O `throw()` seco perdia justamente a parte util: o Asaas responde 400 com
     * a lista de erros e a descricao de cada um, e sem ela o log so dizia
     * "status code 400". Custou uma investigacao inteira descobrir que faltava
     * o cliente no corpo, coisa que a resposta dizia com todas as letras.
     */
    private function enviar(string $recurso, array $dados): array
    {
        $resposta = $this->http()->post($recurso, $dados);

        if ($resposta->successful()) {
            return $resposta->json() ?? [];
        }

        $descricoes = collect($resposta->json('errors') ?? [])
            ->pluck('description')
            ->filter()
            ->implode(' ');

        throw new \RuntimeException($descricoes !== ''
            ? $descricoes
            : 'O provedor de cobrança respondeu com erro '.$resposta->status().'.');
    }

    /**
     * Prova a credencial com a menor leitura possivel.
     *
     * @return array{0: bool, 1: string}
     */
    public function testar(): array
    {
        try {
            $resposta = $this->http()->get('/customers', ['limit' => 1]);

            if ($resposta->successful()) {
                return [true, 'Conexão com o Asaas conferida.'];
            }

            return [false, match ($resposta->status()) {
                401 => 'O Asaas recusou a chave. Confira se ela é do ambiente escolhido e se não expirou.',
                403 => 'O Asaas recusou o acesso deste servidor. Confira a lista de IPs da chave no painel.',
                default => 'O Asaas respondeu com erro '.$resposta->status().'.',
            }];
        } catch (\Throwable $e) {
            return [false, 'Não foi possível falar com o Asaas: '.$e->getMessage()];
        }
    }

    /** Token esperado no header asaas-access-token dos webhooks. */
    public function tokenDoWebhook(): string
    {
        return Conexao::segredo('asaas', 'webhook_token')
            ?? (string) config('services.asaas.webhook_token');
    }

    private function chave(): ?string
    {
        return Conexao::segredo('asaas', 'api_key') ?? config('services.asaas.api_key');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(Conexao::urlBase('asaas') ?? config('services.asaas.base_url'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'access_token' => $this->chave(),
                // Obrigatorio para contas Asaas novas; inofensivo nas antigas.
                'User-Agent' => 'Avalia',
            ])
            ->timeout(15)
            // Repete so quando a conexao nao chegou a acontecer, e sem lancar.
            //
            // Duas razoes, e as duas custaram caro. Repetir por causa da
            // RESPOSTA do provedor num POST arrisca criar a mesma cobranca
            // duas vezes, que e o pior erro possivel aqui. E o `throw` embutido
            // do retry engolia o corpo da resposta antes de alguem poder ler o
            // motivo da recusa, deixando no log so "status code 400".
            ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, false);
    }
}
