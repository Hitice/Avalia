<?php

namespace App\Services\Google;

use App\Exceptions\Recusa;
use App\Models\Conexao;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Acha o Place ID de um estabelecimento pelo nome, na Places API do Google.
 *
 * Devolve CANDIDATOS, e nao um resultado. Nome de loja repete muito ("Pizzaria
 * do Centro" existe em toda cidade), e escolher o primeiro sozinho produziria um
 * link de avaliacao que manda a freguesia de um cliente avaliar o concorrente.
 * Quem escolhe e quem conhece o cliente.
 *
 * Cada chamada e COBRADA pelo Google. Por isso a conferencia de formato acontece
 * antes, o resultado e gravado no negocio, e nada aqui tenta de novo sozinho.
 */
class BuscarLugar
{
    private const CAMPOS = 'places.id,places.displayName,places.formattedAddress';

    /*
     * Quanto tempo a mesma busca vale sem ser cobrada de novo.
     *
     * Place ID nao muda quando o dono renomeia o estabelecimento, entao trinta
     * dias e conservador. Existe porque a ferramenta e publica: sem cache, cem
     * visitantes pesquisando a mesma pizzaria da cidade viram cem cobrancas do
     * Google por uma resposta identica.
     */
    private const DIAS_DE_CACHE = 30;

    /**
     * @return list<array{place_id: string, nome: string, endereco: string}>
     */
    public function __invoke(string $nome, ?string $cidade = null): array
    {
        $termo = trim($nome.' '.(string) $cidade);

        if (mb_strlen($termo) < 3) {
            throw new Recusa('Diga o nome do estabelecimento como ele aparece no Google.');
        }

        $chave = Conexao::segredo('google', 'api_key');

        // Recusa em vez de devolver vazio, pela mesma razao do bureau: tela que
        // diz "nenhum resultado" sem credencial ensina a operacao a achar que o
        // estabelecimento nao existe no Google.
        if ($chave === null) {
            throw new Recusa('A conexão com o Google não está ativa. Cadastre a chave da API em Conexões antes de buscar.');
        }

        $base = Conexao::urlBase('google') ?? 'https://places.googleapis.com/v1';

        $lugares = Cache::remember(
            self::chave($termo),
            now()->addDays(self::DIAS_DE_CACHE),
            fn () => $this->perguntarAoGoogle($base, $chave, $termo),
        );

        // A recusa fica FORA do cache, e a lista vazia dentro: o Google cobra a
        // busca que nao achou nada igual, e sem guardar o vazio quem insiste no
        // mesmo nome errado paga de novo a cada tentativa.
        if ($lugares === []) {
            throw new Recusa('O Google não achou esse estabelecimento. Confira o nome como está no perfil dele, e tente com a cidade.');
        }

        return $lugares;
    }

    /** @return list<array{place_id: string, nome: string, endereco: string}> */
    private function perguntarAoGoogle(string $base, string $chave, string $termo): array
    {
        $resposta = Http::withHeaders([
            'X-Goog-Api-Key' => $chave,
            'X-Goog-FieldMask' => self::CAMPOS,
        ])
            ->timeout(12)
            ->asJson()
            ->post($base.'/places:searchText', [
                'textQuery' => $termo,

                // Idioma e regiao mudam o que volta: sem eles a busca por um nome
                // brasileiro traz homonimo de outro pais antes do da esquina.
                'languageCode' => 'pt-BR',
                'regionCode' => 'BR',
            ]);

        if ($resposta->failed()) {
            throw new Recusa($this->motivo($resposta->status(), $resposta->json('error.message')));
        }

        return collect($resposta->json('places') ?? [])
            ->map(fn (array $lugar) => [
                'place_id' => (string) ($lugar['id'] ?? ''),
                'nome' => (string) ($lugar['displayName']['text'] ?? ''),
                'endereco' => (string) ($lugar['formattedAddress'] ?? ''),
            ])
            ->filter(fn (array $lugar) => $lugar['place_id'] !== '')

            // Cinco basta para reconhecer o certo. Lista longa de homonimo e
            // onde se clica errado.
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * A chave do cache, pelo termo normalizado.
     *
     * Minuscula e sem espaco repetido, para "Pizzaria do Centro" e
     * "pizzaria  do centro" nao virarem duas cobrancas.
     */
    private static function chave(string $termo): string
    {
        return 'google:lugar:'.md5(preg_replace('/\s+/', ' ', mb_strtolower(trim($termo))));
    }

    /** O que dizer a quem esta na tela, por codigo de erro do Google. */
    private function motivo(int $status, ?string $doGoogle): string
    {
        return match (true) {
            $status === 403 => 'O Google recusou a chave. Confira em Conexões se ela está ativa e se a Places API está habilitada no projeto.',
            $status === 429 => 'O Google recusou por excesso de consultas. Tente de novo em alguns minutos.',

            // O detalhe do Google entra porque e ele que diz qual campo esta
            // errado, e sem isso a tela repete "nao deu" sem dizer o que fazer.
            $status === 400 => 'O Google recusou a busca'.($doGoogle ? ': '.$doGoogle : '.'),
            default => 'A busca no Google falhou (HTTP '.$status.'). Nada foi cobrado se a chamada não chegou.',
        };
    }
}
