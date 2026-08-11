<?php

namespace App\Services\Conectores;

use App\Contracts\ConectorBureau;
use App\Models\Conexao;
use App\Models\Servico;
use App\Services\BoaVistaClient;
use App\Support\RespostaConsulta;

/**
 * Conector real da Equifax Boa Vista (Reporting Orchestrator).
 *
 * Escrito sobre a referencia da API do portal: um endpoint so, POST /consulta
 * no escopo dos relatorios, e o PRODUTO decide o que volta. Cada servico do
 * catalogo declara o produto no campo "relatorio no fornecedor"
 * (SCPC_NET_PF, ACERTA_COMPLETO_POSITIVO, SCORE_PJ e assim por diante), entao
 * ampliar o portfolio e cadastro, nao codigo.
 *
 * Os dois cabecalhos `app` e `secondaryCode` sao exigidos pelo orquestrador e
 * vem da conexao, porque sao do contrato e nao do codigo.
 *
 * O contrato de ConectorBureau vale inteiro: falha do fornecedor nunca vira
 * excecao, vira RespostaConsulta::falha com mensagem que o operador entende.
 */
class ConectorBoaVista implements ConectorBureau
{
    private const RECURSO = '/consulta';

    public function __construct(private readonly BoaVistaClient $cliente) {}

    public function consultar(Servico $servico, string $documento, string $finalidade): RespostaConsulta
    {
        $inicio = microtime(true);
        $duracao = fn () => (int) ((microtime(true) - $inicio) * 1000);

        $produto = self::produto($servico->codigo_fornecedor, $documento);

        if (! $produto) {
            return RespostaConsulta::falha(
                "O serviço {$servico->nome} ainda não tem o produto do fornecedor configurado no catálogo.",
                null,
                $duracao(),
            );
        }

        $escopo = Conexao::segredo('boa-vista', 'escopo_relatorios');

        if (! $escopo || ! $this->cliente->configurado()) {
            return RespostaConsulta::falha('A conexão com o bureau não está configurada.', null, $duracao());
        }

        try {
            $resposta = $this->cliente->consultar($escopo, self::RECURSO, [
                'produto' => $produto,
                'documento' => $documento,
                'versao' => 'v1',
                'tipoCredito' => Conexao::segredo('boa-vista', 'tipo_credito') ?? 'CD',
            ], [
                'app' => Conexao::segredo('boa-vista', 'app') ?? 'API',
                'secondaryCode' => Conexao::segredo('boa-vista', 'codigo_secundario') ?? '00000000',
            ]);

            if (! $resposta->successful()) {
                return RespostaConsulta::falha($this->motivo($resposta), null, $duracao());
            }

            $corpo = $resposta->json() ?? [];

            return RespostaConsulta::sucesso(
                $this->laudo($corpo),
                $this->protocolo($corpo, $documento),
                $duracao(),
            );
        } catch (\Throwable $e) {
            report($e);

            return RespostaConsulta::falha('O fornecedor não respondeu. Tente novamente em instantes.', null, $duracao());
        }
    }

    public function nome(): string
    {
        return 'boa-vista';
    }

    /**
     * O produto a pedir, considerando se o documento e CPF ou CNPJ.
     *
     * O catalogo vende "Credito Net PF/PJ" numa linha so, mas a Equifax tem
     * produtos SEPARADOS para pessoa fisica e juridica (SCPC_NET_PF e
     * SCPC_NET_PJ, SCORE_PF e SCORE_PJ). Uma linha comercial, dois produtos no
     * fornecedor.
     *
     * Entao o campo aceita os dois, separados por barra vertical, na ordem
     * PF|PJ:
     *
     *     SCPC_NET_PF|SCPC_NET_PJ
     *
     * Um valor sozinho continua valendo para os dois tipos de documento, que e
     * o caso dos produtos que nao se dividem. Documento com 14 digitos e CNPJ;
     * qualquer outro tamanho cai no lado PF, porque CPF e o caso comum e um
     * documento malformado ja e recusado antes de chegar aqui.
     */
    public static function produto(?string $configurado, string $documento): ?string
    {
        $configurado = trim((string) $configurado);

        if ($configurado === '') {
            return null;
        }

        if (! str_contains($configurado, '|')) {
            return $configurado;
        }

        [$pf, $pj] = array_pad(array_map('trim', explode('|', $configurado, 2)), 2, '');
        $ehCnpj = strlen(preg_replace('/\D/', '', $documento) ?? '') === 14;

        // Lado vazio cai no outro: quem preencheu so um dos dois quis dizer que
        // aquele serve para ambos, e nao que o outro documento nao consulta.
        return ($ehCnpj ? ($pj ?: $pf) : ($pf ?: $pj)) ?: null;
    }

    /**
     * Traducao para o laudo canonico, na mesma ordem do conector da Serasa.
     *
     * A busca por chave e por NOME e nao por caminho fixo: cada produto do
     * orquestrador devolve um recorte diferente (score, cadastral, completo),
     * e amarrar a um caminho faria o conector quebrar a cada produto novo. O
     * que nao for reconhecido vai inteiro para "informacoes adicionais", entao
     * nada se perde enquanto o mapa amadurece com respostas reais.
     */
    private function laudo(array $corpo): array
    {
        $laudo = ['laudo' => 1, 'fornecido_em' => now()->toIso8601String()];

        // 1. Decisao. O modelo do score vai junto: numero de score sem o
        // modelo que o produziu nao se compara com nada.
        if ($score = $this->buscar($corpo, ['score', 'pontuacao', 'scoreValue'])) {
            $laudo['score'] = is_array($score) ? ($this->buscar($score, ['valor', 'value', 'score']) ?? null) : $score;
            $laudo['modelo_do_score'] = is_array($score)
                ? ($this->buscar($score, ['modelo', 'model', 'tipo']) ?? null)
                : null;
        }

        // 2. Identidade.
        $laudo['nome'] = $this->buscar($corpo, ['nome', 'razaoSocial', 'nomeCompleto']);
        $laudo['situacao_cadastral'] = $this->buscar($corpo, ['situacaoCadastral', 'statusReceita', 'situacao']);

        // 3. Restricoes.
        $laudo['pendencias'] = $this->contar($corpo, ['pendencias', 'pendenciasFinanceiras', 'debitos']);
        $laudo['protestos'] = $this->contar($corpo, ['protestos']);
        $laudo['cheques_sem_fundo'] = $this->contar($corpo, ['chequesSemFundo', 'cheques']);

        // 4. Contexto.
        $laudo['consultas_recentes'] = $this->contar($corpo, ['consultas', 'consultasAnteriores']);

        $laudo = array_filter($laudo, fn ($v) => $v !== null);

        // O que o mapa ainda nao reconhece vai inteiro, para o operador ver o
        // que veio e para o mapa crescer com dado real, nao com suposicao.
        $laudo['informacoes_adicionais'] = $corpo;

        return $laudo;
    }

    /** Primeira chave encontrada, em qualquer profundidade. */
    private function buscar(array $dados, array $chaves): mixed
    {
        foreach ($chaves as $chave) {
            if (array_key_exists($chave, $dados) && $dados[$chave] !== null && $dados[$chave] !== '') {
                return $dados[$chave];
            }
        }

        foreach ($dados as $valor) {
            if (is_array($valor) && ($achado = $this->buscar($valor, $chaves)) !== null) {
                return $achado;
            }
        }

        return null;
    }

    /** Quantos itens uma lista de ocorrencias tem, ou null se ela nem veio. */
    private function contar(array $dados, array $chaves): ?int
    {
        $bloco = $this->buscar($dados, $chaves);

        if ($bloco === null) {
            return null;
        }

        if (is_array($bloco)) {
            return array_is_list($bloco) ? count($bloco) : (int) ($this->buscar($bloco, ['quantidade', 'total', 'count']) ?? 0);
        }

        return is_numeric($bloco) ? (int) $bloco : null;
    }

    private function protocolo(array $corpo, string $documento): string
    {
        $protocolo = $this->buscar($corpo, ['protocolo', 'idConsulta', 'reportId', 'transactionId']);

        return is_scalar($protocolo) && (string) $protocolo !== ''
            ? (string) $protocolo
            : 'BV-'.strtoupper(substr(hash('sha256', $documento.now()->timestamp), 0, 12));
    }

    /**
     * O motivo da recusa, escrito para quem vai resolver.
     *
     * Le o CODIGO do fornecedor antes do status HTTP, e a diferenca custou uma
     * investigacao: 404 aqui pode ser "documento nao existe na base" ou "esta
     * API nao existe neste ambiente", que sao problemas opostos. Traduzir todo
     * 404 como documento nao encontrado mandava o operador conferir um CNPJ
     * correto enquanto o problema estava na aprovacao da API no portal.
     *
     * Codigo desconhecido devolve a descricao do proprio fornecedor em vez de
     * uma frase generica: quem escreveu o erro sabe mais sobre ele do que nos.
     */
    private function motivo(\Illuminate\Http\Client\Response $resposta): string
    {
        $codigo = (string) ($resposta->json('efxErrorCode') ?? '');
        $descricao = trim((string) ($resposta->json('description') ?? ''));

        // 404.01 e "recurso nao existe", ou seja, o ENDPOINT. Documento sem
        // ocorrencia volta com resposta vazia, e nao com 404.
        if (str_starts_with($codigo, '404.01')) {
            return 'A API deste produto não está publicada no ambiente configurado. '
                .'Confira no portal do fornecedor se ela já foi aprovada para o seu aplicativo.';
        }

        // 401.04 e "No product match found": a credencial VALE, o endereco
        // existe, e o que falta e o produto estar liberado para o aplicativo.
        // E o oposto de credencial recusada, e mandar conferir a credencial
        // faria alguem trocar uma chave que esta certa.
        if (str_starts_with($codigo, '401.04')) {
            return 'A credencial foi aceita, mas este produto não está liberado para o seu '
                .'aplicativo no fornecedor. É liberação de contrato, não configuração daqui.';
        }

        $conhecido = match ($resposta->status()) {
            400 => 'O fornecedor recusou a consulta: confira o produto e o documento informados.',
            401, 403 => 'O fornecedor recusou as credenciais. Confira a conexão com o bureau.',
            404 => 'Documento não encontrado na base do fornecedor.',
            429 => 'O fornecedor limitou o volume de consultas. Aguarde alguns minutos.',
            default => null,
        };

        if ($conhecido) {
            return $conhecido;
        }

        return $descricao !== ''
            ? 'O fornecedor recusou a consulta: '.$descricao
            : 'O fornecedor respondeu com erro ('.$resposta->status().').';
    }
}
