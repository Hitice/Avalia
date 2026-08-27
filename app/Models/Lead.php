<?php

namespace App\Models;

use App\Enums\SituacaoLead;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Empresa da base de prospeccao, antes de existir contrato.
 *
 * Nao e conta: lead nao entra na plataforma, nao tem senha e nao consulta nada.
 * E cadastro de terceiro que a Avalia guarda para trabalhar a venda, e por isso
 * toda saida dele em arquivo vai para a trilha de auditoria.
 *
 * A ficha tem os mesmos campos que `clientes` pede, e nao um subconjunto: e o
 * que faz a conversao ser copia, e nao entrevista de novo com o cliente na
 * linha.
 */
class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'leads';

    protected $fillable = [
        'codigo', 'nome', 'cnpj', 'cidade', 'uf', 'telefone', 'email',
        'responsavel_nome', 'responsavel_cpf', 'cep', 'logradouro', 'numero',
        'complemento', 'bairro', 'origem', 'situacao', 'agendado_para', 'observacao',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoLead::class,
            'agendado_para' => 'datetime',
            'convertido_em' => 'datetime',
        ];
    }

    /**
     * Os vendedores com quem a administracao compartilhou este lead.
     *
     * Muitos para muitos porque a mesma empresa pode ser trabalhada por mais de
     * um vendedor, e porque a data de cada compartilhamento importa.
     */
    public function vendedores(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'lead_staff', 'lead_id', 'staff_id')
            ->withPivot(['compartilhado_em', 'compartilhado_por']);
    }

    /** A empresa que este lead virou, quando virou. */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Os leads de um vendedor.
     *
     * Vive no model porque tres telas fazem a mesma pergunta (a lista, a
     * edicao e a conversao), e o recorte precisa sair do vinculo nas tres. Um
     * `whereHas` escrito de novo em cada controller e onde nasce o esquecido
     * que deixa o vendedor abrir o lead de outro pela URL.
     */
    public function scopeDoVendedor(Builder $consulta, int $staffId): Builder
    {
        return $consulta->whereHas(
            'vendedores',
            fn (Builder $q) => $q->where('staff.id', $staffId),
        );
    }

    /** Leads que ainda dao trabalho: novo, em atendimento ou agendado. */
    public function scopeEmAberto(Builder $consulta): Builder
    {
        return $consulta->whereIn('situacao', array_map(
            fn (SituacaoLead $s) => $s->value,
            array_filter(SituacaoLead::cases(), fn (SituacaoLead $s) => $s->emAberto()),
        ));
    }

    /** Agendamento que ja passou da hora. A fila de quem esta atrasado. */
    public function scopeAgendamentoVencido(Builder $consulta): Builder
    {
        return $consulta->where('situacao', SituacaoLead::Agendado->value)
            ->whereNotNull('agendado_para')
            ->where('agendado_para', '<', now());
    }

    /** Cidade e UF juntas, como a tela mostra. */
    public function cidadeRotulo(): string
    {
        return trim($this->cidade.($this->cidade && $this->uf ? '/' : '').$this->uf);
    }

    public function cnpjRotulo(): string
    {
        return Documento::formatarCnpj($this->cnpj);
    }

    /**
     * Tem por onde ligar ou escrever.
     *
     * Lead sem telefone e sem e-mail nao da trabalho ao vendedor: ele precisa
     * de enriquecimento antes, e por isso a tela sabe separar os dois grupos.
     */
    public function temContato(): bool
    {
        return $this->telefone !== null || $this->email !== null;
    }

    public function jaEhCliente(): bool
    {
        return $this->situacao === SituacaoLead::Convertido;
    }

    /**
     * O que falta para este lead poder abrir um cadastro de cliente.
     *
     * Sao os dois campos que `EmpresaRequest` exige e que ninguem consegue
     * inventar: o CNPJ e quem vai ser cobrado, e o e-mail e o login da empresa.
     * Devolver a lista, em vez de um booleano, e o que permite a tela dizer o
     * que falta em vez de apenas desabilitar o botao. Botao desabilitado sem
     * explicacao vira chamado no atendimento.
     *
     * O digito verificador e conferido aqui de proposito: CNPJ inventado passa
     * pelo cadastro e reaparece na primeira cobranca, quando ja e problema
     * financeiro.
     *
     * @return list<string>
     */
    public function faltaParaVirarCliente(): array
    {
        $falta = [];

        if (! Documento::cnpjValido($this->cnpj)) {
            $falta[] = $this->cnpj === null ? 'o CNPJ' : 'um CNPJ válido';
        }

        if ($this->email === null) {
            $falta[] = 'o e-mail';
        }

        return $falta;
    }

    public function podeVirarCliente(): bool
    {
        return ! $this->jaEhCliente() && $this->faltaParaVirarCliente() === [];
    }

    /**
     * A ficha do lead traduzida para os campos do cadastro de empresa.
     *
     * Vive aqui, e nao no controller, porque e a correspondencia entre as duas
     * tabelas: quem acrescentar coluna na ficha do lead mexe em um lugar so, e
     * a conversao continua completa. `nome` vira `razao_social` porque e o mesmo
     * dado com o nome que cada lado usa.
     *
     * @return array<string, mixed>
     */
    public function paraCadastroDeCliente(): array
    {
        return [
            'razao_social' => $this->nome,
            'cnpj' => $this->cnpj,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'responsavel_nome' => $this->responsavel_nome,
            'responsavel_cpf' => $this->responsavel_cpf,
            'cep' => $this->cep,
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'uf' => $this->uf,
        ];
    }
}
