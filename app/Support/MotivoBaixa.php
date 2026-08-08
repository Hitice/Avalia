<?php

namespace App\Support;

/**
 * Os motivos de baixa de uma negativacao, como o fornecedor os define.
 *
 * A tabela e do fornecedor e nao nossa: ele aceita o nome ou o codigo, e a
 * baixa exige um deles. Fica aqui como dado, e nao espalhada em Blade, porque
 * a mesma lista aparece na tela de baixa, no relatorio e na trilha.
 *
 * A ordem nao e a numerica: os motivos que a operacao usa todo dia vem
 * primeiro. Pagamento e renegociacao respondem pela quase totalidade das
 * baixas; o resto e correcao e caso excepcional.
 */
final class MotivoBaixa
{
    /** @var array<string, string> */
    private const ROTINA = [
        'PAID' => 'Pagamento da dívida',
        'WRITE_OFF_BY_NEGOTIATION' => 'Baixa por negociação',
        'CREDIT_SETTLEMENT' => 'Liquidação do crédito',
        'REGULAR' => 'Baixa regular',
        'EXPIRATION_OF_TERM' => 'Decurso de prazo',
    ];

    /** @var array<string, string> */
    private const CORRECAO = [
        'UNDUE_INCLUSION' => 'Inclusão indevida',
        'WRONG_DEBTOR' => 'Devedor errado',
        'WRONG_DOCUMENT' => 'Documento errado',
        'NAME_OR_DOCUMENT_CHANGED' => 'Nome ou documento alterado',
        'DEBIT_DATA_CHANGED' => 'Dados do débito alterados',
        'DOCUMENT_CHANGED' => 'Documento alterado',
        'DEBIT_CHANGED_FOR_SAME_DOCUMENT' => 'Débito alterado para o mesmo documento',
        'DEBIT_TRANSFER_TO_ANOTHER_DOCUMENT' => 'Débito transferido para outro documento',
        'BASE_UPDATED' => 'Base atualizada',
        'INFORMANT_CHANGE' => 'Troca de informante',
    ];

    /** @var array<string, string> */
    private const EXCEPCIONAL = [
        'JUDICIAL_DETERMINATION' => 'Determinação judicial',
        'JUDICIAL_ACTION' => 'Ação judicial',
        'LEGAL_DEPT_ORDER' => 'Ordem do departamento jurídico',
        'PROOF_OF_PAYMENT' => 'Comprovante de pagamento apresentado',
        'STOLEN_DOC_FRAUD' => 'Documento roubado ou fraude',
        'DEBTOR_DECEASED' => 'Devedor falecido',
        'STOPPED_CHECK' => 'Cheque sustado',
        'JURIDIC_RNE' => 'RNE de pessoa jurídica',
        'ASSOCIATE_REMOVAL' => 'Remoção do associado',
        'ASSIGNMENT_OF_OWNERSHIP_EFFECTIVE_SALE_PORTFOLIO' => 'Cessão de titularidade da carteira',
        'INHIBITED_PAYMENT' => 'Pagamento inibido',
        'PUBLIC_CALAMITY' => 'Calamidade pública',
        'COMPULSORY_WRITE_OFF' => 'Baixa compulsória',
        'CANCELED_BY_EFX' => 'Cancelada pelo fornecedor',
    ];

    /** @return array<string, array<string, string>> Grupos na ordem de uso. */
    public static function agrupados(): array
    {
        return [
            'Rotina' => self::ROTINA,
            'Correção de dado' => self::CORRECAO,
            'Caso excepcional' => self::EXCEPCIONAL,
        ];
    }

    /** @return array<string, string> */
    public static function todos(): array
    {
        return self::ROTINA + self::CORRECAO + self::EXCEPCIONAL;
    }

    public static function existe(string $codigo): bool
    {
        return array_key_exists($codigo, self::todos());
    }

    public static function rotulo(string $codigo): string
    {
        return self::todos()[$codigo] ?? $codigo;
    }
}
