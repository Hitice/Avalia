<?php

namespace App\Models;

use App\Enums\SituacaoNegocio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um negocio local atendido pela frente de marketing.
 *
 * Reune o que antes era texto solto na plaquinha. Serve a mais de um produto:
 * hoje plaquinha e cadastro no Google Meu Negocio, e o que vier depois usa o
 * mesmo cadastro em vez de pedir os dados de novo.
 */
class Negocio extends Model
{
    use HasFactory;

    protected $table = 'negocios';

    protected $fillable = [
        'nome', 'categoria', 'descricao', 'site', 'instagram',
        'responsavel', 'email', 'whatsapp', 'telefone', 'documento',
        'place_id', 'link_avaliacao_id',
        'atende_no_endereco', 'cep', 'logradouro', 'numero', 'complemento',
        'bairro', 'cidade', 'uf', 'horarios',
        'situacao', 'origem', 'lead_id', 'staff_id', 'vendedor_id',
        'cadastrado_no_google_em', 'observacao',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoNegocio::class,
            'atende_no_endereco' => 'boolean',
            'cadastrado_no_google_em' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** Quem trouxe o cliente, pelo link de cadastro dele. */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'vendedor_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function linkAvaliacao(): BelongsTo
    {
        return $this->belongsTo(Link::class, 'link_avaliacao_id');
    }

    public function etiquetas(): HasMany
    {
        return $this->hasMany(Etiqueta::class);
    }

    /**
     * O endereco em uma linha, do jeito que se cola no Google.
     *
     * Vazio quando o negocio atende no cliente e nao informou endereco, e nao
     * uma linha de virgulas soltas.
     */
    public function enderecoEmLinha(): string
    {
        $rua = trim(($this->logradouro ?? '').' '.($this->numero ?? ''));

        $partes = array_filter([
            $rua,
            $this->complemento,
            $this->bairro,
            trim(($this->cidade ?? '').($this->uf ? ' - '.$this->uf : '')),
            $this->cep,
        ], fn ($p) => trim((string) $p) !== '');

        return implode(', ', $partes);
    }

    /**
     * O que falta para conseguir cadastrar no Google.
     *
     * O Google recusa perfil sem nome, categoria e um jeito de ser achado. Dizer
     * isso na lista evita abrir o cadastro para descobrir no meio que falta dado,
     * que e o que faz o atendimento voltar ao cliente duas vezes.
     *
     * @return list<string>
     */
    public function faltaPara(): array
    {
        $falta = [];

        if (trim((string) $this->categoria) === '') {
            $falta[] = 'categoria';
        }

        if ($this->atende_no_endereco && $this->enderecoEmLinha() === '') {
            $falta[] = 'endereço';
        }

        if (trim((string) $this->telefone) === '' && trim((string) $this->site) === '') {
            $falta[] = 'telefone ou site';
        }

        if (trim((string) $this->horarios) === '') {
            $falta[] = 'horários';
        }

        return $falta;
    }
}
