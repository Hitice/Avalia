<?php

namespace App\Services\Google;

use App\Exceptions\Recusa;
use App\Models\Conexao;
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

        $lugares = collect($resposta->json('places') ?? [])
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

        if ($lugares === []) {
            throw new Recusa('O Google não achou esse estabelecimento. Confira o nome como está no perfil dele, e tente com a cidade.');
        }

        return $lugares;
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
