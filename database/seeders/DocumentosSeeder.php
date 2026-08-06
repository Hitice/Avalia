<?php

namespace Database\Seeders;

use App\Models\DocumentoLegal;
use Illuminate\Database\Seeder;

/**
 * Documentos que a empresa aceita antes de operar.
 *
 * São bases de trabalho, não peças jurídicas prontas: o texto precisa passar
 * por advogado antes de valer contra alguém. O que já está certo aqui é a
 * estrutura, que é o que o sistema usa: tipo, versão e se exige aceite.
 *
 * Documento que exige aceite trava a consulta enquanto não for aceito, então
 * publicar versão nova de um deles interrompe a operação de quem ainda não
 * aceitou. É proposital, e é o motivo de a versão fazer parte da identidade.
 *
 * Idempotente por tipo e versão: rodar de novo atualiza o texto em vez de
 * duplicar.
 */
class DocumentosSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->documentos() as $documento) {
            DocumentoLegal::updateOrCreate(
                ['tipo' => $documento['tipo'], 'versao' => $documento['versao']],
                $documento,
            );
        }

        $this->command->info(count($this->documentos()).' documentos publicados.');
    }

    /** @return list<array<string, mixed>> */
    private function documentos(): array
    {
        return [
            [
                'tipo' => 'contrato',
                'versao' => '1.0',
                'titulo' => 'Contrato de prestação de serviços',
                'exige_aceite' => true,
                'ativo' => true,
                'conteudo' => $this->contrato(),
            ],
            [
                'tipo' => 'privacidade',
                'versao' => '1.0',
                'titulo' => 'Política de privacidade e tratamento de dados',
                'exige_aceite' => true,
                'ativo' => true,
                'conteudo' => $this->privacidade(),
            ],
            [
                'tipo' => 'uso-consciente',
                'versao' => '1.0',
                'titulo' => 'Termo de uso consciente da consulta',
                'exige_aceite' => true,
                'ativo' => true,
                'conteudo' => $this->usoConsciente(),
            ],
            [
                'tipo' => 'confidencialidade',
                'versao' => '1.0',
                'titulo' => 'Termo de confidencialidade',
                'exige_aceite' => false,
                'ativo' => true,
                'conteudo' => $this->confidencialidade(),
            ],
        ];
    }

    private function contrato(): string
    {
        return <<<'TEXTO'
        ## Objeto

        A Avalia disponibiliza à empresa contratante o acesso a consultas de
        informações de crédito e cadastrais, na modalidade e nos serviços
        definidos no plano contratado.

        ## Plano, mensalidade e consumo

        A contratante paga uma mensalidade fixa, devida independentemente de uso,
        e o consumo do mês. O consumo é cobrado pelo maior valor entre o
        efetivamente consumido e o consumo mínimo da faixa contratada.

        As quantidades incluídas na mensalidade, quando houver, são definidas por
        serviço e não são cumulativas entre meses.

        ## Faturamento e pagamento

        A competência é mensal. A fatura vence no dia 10 do mês seguinte.

        O atraso suspende as consultas a partir do décimo dia após o vencimento.
        O acesso à plataforma permanece disponível para consulta da fatura e
        regularização. Liquidado o débito, as consultas são liberadas na mesma
        competência.

        ## Uso das informações

        A contratante declara que utilizará as informações obtidas apenas para as
        finalidades declaradas em cada consulta, e que possui base legal para o
        tratamento dos dados pessoais a que tiver acesso.

        A contratante é responsável pelo uso feito por seus colaboradores e pelo
        controle das credenciais de acesso.

        ## Vigência e encerramento

        A vigência é a definida na proposta comercial. O encerramento não
        dispensa o pagamento das competências já apuradas.

        ## Foro

        A definir com o jurídico.
        TEXTO;
    }

    private function privacidade(): string
    {
        return <<<'TEXTO'
        ## Quais dados são tratados

        A Avalia trata dados cadastrais da empresa contratante e de seus
        responsáveis, dados de acesso à plataforma, e os dados pessoais de
        terceiros consultados pela contratante.

        ## Para quê

        Os dados cadastrais são usados para execução do contrato e cobrança. Os
        dados de acesso são usados para segurança e auditoria. Os dados
        consultados são tratados por conta e ordem da contratante, para a
        finalidade que ela declara em cada consulta.

        ## Por quanto tempo

        O resultado de cada consulta é apagado após 180 dias. São preservados os
        metadados que comprovam a operação e sustentam a cobrança: data, serviço,
        finalidade declarada, responsável, preço e competência.

        Registros fiscais e contábeis são mantidos pelos prazos exigidos em lei.

        ## Direitos do titular

        O titular dos dados pode solicitar confirmação de tratamento e acesso às
        informações sobre consultas realizadas a seu respeito, pelos canais
        indicados nesta política.

        ## Segurança

        O acesso é controlado por perfil, toda ação sensível é registrada em
        trilha de auditoria, e o tráfego é cifrado.

        ## Contato

        A definir com o encarregado de dados.
        TEXTO;
    }

    private function usoConsciente(): string
    {
        return <<<'TEXTO'
        ## O que a contratante se compromete a fazer

        Declarar, em cada consulta, a finalidade real e específica do uso.

        Consultar apenas quando houver relação ou tratativa que justifique, como
        análise de crédito, verificação cadastral em negociação, ou prevenção a
        fraude.

        Não consultar por curiosidade, para fins pessoais, ou a pedido de
        terceiro sem relação com a contratante.

        Não repassar a terceiros o resultado obtido, salvo quando o repasse fizer
        parte da própria finalidade declarada.

        ## Responsabilidade

        Cada consulta fica registrada com data, hora, finalidade declarada,
        responsável e identificação da contratante. Esse registro é a prova do
        uso, e pode ser exigido em fiscalização ou em pedido de titular.

        O uso indevido é de responsabilidade da contratante e pode implicar
        suspensão imediata do acesso, além das consequências legais aplicáveis.
        TEXTO;
    }

    private function confidencialidade(): string
    {
        return <<<'TEXTO'
        ## Informações confidenciais

        São confidenciais os dados de terceiros obtidos pelas consultas, as
        condições comerciais praticadas, e qualquer informação técnica ou
        cadastral a que as partes tenham acesso em razão da relação.

        ## Obrigações

        Cada parte se compromete a manter sigilo, a limitar o acesso às pessoas
        que precisem conhecer a informação para executar o contrato, e a não
        utilizar a informação para finalidade diversa da contratada.

        ## Prazo

        A obrigação de sigilo permanece após o encerramento do contrato, pelo
        prazo definido com o jurídico.
        TEXTO;
    }
}
