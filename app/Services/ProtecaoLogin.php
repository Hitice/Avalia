<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Motor de protecao contra forca bruta.
 *
 * Conta falhas em duas chaves ao mesmo tempo:
 *   conta:<email>  -> impede martelar uma conta especifica
 *   origem:<ip>    -> impede varrer muitas contas a partir de uma origem
 *
 * Basta uma das duas estar de castigo para barrar. Sem as duas, quem ataca
 * escolhe o eixo que nao e vigiado.
 *
 * O castigo dobra a cada falha depois do limite, ate um teto:
 *   castigo = min(TETO, BASE * 2^(falhas - LIMITE))
 */
class ProtecaoLogin
{
    /** Falhas toleradas antes do primeiro castigo. */
    public const LIMITE = 5;

    /** Primeiro castigo, em segundos. */
    public const BASE_SEGUNDOS = 60;

    /** Teto do castigo: 30 minutos. */
    public const TETO_SEGUNDOS = 1800;

    /** Sem falha nova por este periodo, a contagem zera sozinha. */
    public const JANELA_SEGUNDOS = 3600;

    /**
     * Segundos restantes de bloqueio, ou null se pode tentar.
     * Devolve o maior castigo entre conta e origem.
     */
    public function bloqueadoPor(string $email, Request $req): ?int
    {
        $restantes = 0;

        foreach ($this->registros($this->chaves($email, $req)) as $reg) {
            if ($reg->bloqueado_ate) {
                $falta = Carbon::parse($reg->bloqueado_ate)->diffInSeconds(now(), false);
                // diffInSeconds negativo = ainda no futuro = ainda de castigo.
                if ($falta < 0) {
                    $restantes = max($restantes, (int) abs($falta));
                }
            }
        }

        return $restantes > 0 ? $restantes : null;
    }

    /** Registra uma tentativa falha nas duas chaves e aplica o castigo. */
    public function falhou(string $email, Request $req): void
    {
        $chaves = $this->chaves($email, $req);
        $registros = $this->registros($chaves);
        $linhas = [];

        foreach ($chaves as $chave) {
            $reg = $registros[$chave] ?? null;

            // Silencio prolongado zera a contagem: quem errou a senha em
            // marco nao deve pagar por isso em agosto.
            $expirou = $reg && $reg->ultima_falha_em
                && Carbon::parse($reg->ultima_falha_em)->addSeconds(self::JANELA_SEGUNDOS)->isPast();

            $falhas = ($expirou || ! $reg) ? 1 : $reg->falhas + 1;

            $linhas[] = [
                'chave' => $chave,
                'falhas' => $falhas,
                'bloqueado_ate' => $this->castigo($falhas),
                'ultima_falha_em' => now(),
                'updated_at' => now(),
                'created_at' => $reg->created_at ?? now(),
            ];
        }

        // Uma escrita para as duas chaves. Contra banco remoto cada ida e
        // volta custa caro, e a tela de login paga esse preco na cara do
        // usuario.
        DB::table('tentativas_login')->upsert(
            $linhas,
            ['chave'],
            ['falhas', 'bloqueado_ate', 'ultima_falha_em', 'updated_at'],
        );

        Log::channel('seguranca')->warning('login.falhou', [
            'conta_hash' => hash('sha256', mb_strtolower(trim($email))),
            'bloqueado' => collect($linhas)->contains(fn (array $linha) => $linha['bloqueado_ate'] !== null),
        ]);
    }

    /** Login certo limpa o castigo da conta e da origem. */
    public function acertou(string $email, Request $req): void
    {
        DB::table('tentativas_login')
            ->whereIn('chave', $this->chaves($email, $req))
            ->delete();

        Log::channel('seguranca')->info('login.sucesso', [
            'conta_hash' => hash('sha256', mb_strtolower(trim($email))),
        ]);
    }

    /** Quantas falhas acumuladas na conta. Usado para avisar antes de travar. */
    public function falhasDaConta(string $email): int
    {
        return (int) ($this->registro($this->chaveConta($email))->falhas ?? 0);
    }

    /**
     * Castigo em data futura, ou null enquanto dentro da tolerancia.
     * O expoente e limitado porque 2^n estoura rapido.
     */
    private function castigo(int $falhas): ?Carbon
    {
        if ($falhas <= self::LIMITE) {
            return null;
        }

        $expoente = min($falhas - self::LIMITE, 20);
        $segundos = min(self::TETO_SEGUNDOS, self::BASE_SEGUNDOS * (2 ** $expoente));

        return now()->addSeconds($segundos);
    }

    /** @return array{0:string,1:string} */
    private function chaves(string $email, Request $req): array
    {
        return [$this->chaveConta($email), 'origem:'.$req->ip()];
    }

    private function chaveConta(string $email): string
    {
        return 'conta:'.mb_strtolower(trim($email));
    }

    private function registro(string $chave): ?object
    {
        return $this->registros([$chave])[$chave] ?? null;
    }

    /**
     * Le as duas chaves de uma vez, indexadas pela propria chave.
     *
     * Uma consulta por chave dobrava o custo do login contra banco remoto,
     * onde cada ida e volta passa de 400ms.
     *
     * @param  array<int, string>  $chaves
     * @return array<string, object>
     */
    private function registros(array $chaves): array
    {
        return DB::table('tentativas_login')
            ->whereIn('chave', $chaves)
            ->get()
            ->keyBy('chave')
            ->all();
    }
}
