<?php

namespace App\Services;

use App\Models\Conexao;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Acesso a Equifax Boa Vista.
 *
 * A autenticacao e o padrao OAuth2 da Equifax, confirmado nos tres ambientes:
 * POST /v2/oauth/token com grant_type client_credentials, o par client_id e
 * client_secret em Basic no header, e o escopo no corpo. Um token por escopo:
 * o SCPC e os relatorios sao produtos distintos e cada token vale para um so.
 *
 * O host vem do ambiente escolhido na tela (sandbox, homologacao, producao),
 * e as credenciais sao proprias de cada um: a de sandbox nao vale em producao.
 */
class BoaVistaClient
{
    private const CAMINHO_TOKEN = '/v2/oauth/token';

    public function configurado(): bool
    {
        return filled(Conexao::segredo('boa-vista', 'client_id'))
            && filled(Conexao::segredo('boa-vista', 'client_secret'));
    }

    /**
     * Token de acesso para um escopo.
     *
     * O cache e por escopo e com folga sobre a validade devolvida: pedir token
     * a cada consulta e desperdicio e chama atencao de qualquer gateway.
     */
    public function token(string $escopo): string
    {
        return Cache::remember('boa-vista-token-'.md5($escopo), now()->addMinutes(50), function () use ($escopo) {
            $credencial = base64_encode(
                Conexao::segredo('boa-vista', 'client_id').':'.Conexao::segredo('boa-vista', 'client_secret'),
            );

            return Http::baseUrl($this->base())
                ->withHeaders(['Authorization' => 'Basic '.$credencial])
                ->asForm()
                ->timeout(15)
                ->post(self::CAMINHO_TOKEN, ['grant_type' => 'client_credentials', 'scope' => $escopo])
                ->throw()
                ->json('access_token');
        });
    }

    /**
     * Prova a credencial pedindo um token de verdade.
     *
     * E o teste mais honesto possivel sem consumir consulta: se o token sai, a
     * credencial e o ambiente estao certos. O caminho do recurso e o passo
     * seguinte, e depende do que o contrato liberou.
     *
     * @return array{0: bool, 1: string}
     */
    public function testar(): array
    {
        $escopo = Conexao::segredo('boa-vista', 'escopo_scpc')
            ?? Conexao::segredo('boa-vista', 'escopo_relatorios');

        if (! $escopo) {
            return [false, 'Informe ao menos um escopo, copiado da sua app no portal do fornecedor.'];
        }

        try {
            Cache::forget('boa-vista-token-'.md5($escopo));
            $this->token($escopo);

            return [true, 'Credencial conferida: o fornecedor emitiu o token para este escopo.'];
        } catch (\Throwable $e) {
            $status = method_exists($e, 'response') ? $e->response?->status() : null;

            return [false, match ($status) {
                400 => 'O fornecedor recusou o pedido. Confira o escopo copiado do portal.',
                401 => 'O fornecedor recusou as credenciais. Confira se são as do ambiente escolhido.',
                default => 'Não foi possível falar com o fornecedor: '.$e->getMessage(),
            }];
        }
    }

    private function base(): string
    {
        return Conexao::urlBase('boa-vista') ?? 'https://api.sandbox.equifax.com';
    }
}
