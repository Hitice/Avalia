<?php

namespace App\Models;

use App\Contracts\ContaAutenticavel;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Quem vende parcelado pelo Avalia 360.
 *
 * A subconta no provedor e o que separa produtor cadastrado de produtor que
 * pode vender: sem `asaas_wallet_id` nao ha para onde o split mandar a parte
 * dele, e a venda ficaria com o dinheiro na conta errada.
 */
class Produtor extends Authenticatable implements ContaAutenticavel
{
    use SoftDeletes;

    protected $table = 'produtores';

    protected $fillable = [
        'pai_id', 'nome', 'documento', 'whatsapp', 'email', 'senha', 'situacao',
        'percentual_bps', 'profundidade',
        'asaas_account_id', 'asaas_wallet_id', 'asaas_api_key',
        'interessado_cobranca_id', 'aprovado_em',
    ];

    protected $hidden = ['senha', 'asaas_api_key', 'sessao_versao', 'remember_token'];

    protected function casts(): array
    {
        return [
            'senha' => 'hashed',
            'sessao_versao' => 'integer',
            'percentual_bps' => 'integer',
            'profundidade' => 'integer',
            'documento' => 'encrypted',
            'whatsapp' => 'encrypted',
            'asaas_api_key' => 'encrypted',
            'aprovado_em' => 'datetime',
        ];
    }

    /** Quem trouxe este parceiro para a rede. */
    public function pai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pai_id');
    }

    public function indicados(): HasMany
    {
        return $this->hasMany(self::class, 'pai_id');
    }

    /**
     * A linha de comissao desta venda: este parceiro, depois quem o trouxe, e
     * assim ate o topo.
     *
     * O limite de dez niveis nao e estetico. Sem ele, um ciclo no cadastro
     * (alguem virando avo de si mesmo) faria esta funcao rodar para sempre
     * dentro de uma requisicao de checkout, e o cliente veria a tela pendurada
     * ate o servidor desistir.
     *
     * @return list<array{wallet: string, bps: int, nome: string}>
     */
    public function linhaDeComissao(int $limite = 10): array
    {
        $linha = [];
        $atual = $this;
        $vistos = [];

        while ($atual !== null && count($linha) < $limite) {
            if (in_array($atual->id, $vistos, true)) {
                break;
            }

            $vistos[] = $atual->id;

            $linha[] = [
                'id' => $atual->id,
                'wallet' => (string) $atual->asaas_wallet_id,
                'bps' => (int) $atual->percentual_bps,
                'nome' => $atual->nome,
            ];

            $atual = $atual->pai;
        }

        return $linha;
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto360::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido360::class);
    }

    /** Pode publicar oferta e receber: aprovado e com subconta pronta. */
    public function podeVender(): bool
    {
        return $this->situacao === 'aprovado' && filled($this->asaas_wallet_id);
    }

    public function documentoRotulo(): string
    {
        return Documento::formatar($this->documento);
    }

    public function getAuthPassword(): string
    {
        return (string) $this->senha;
    }

    /**
     * Produtor pendente entra, e e de proposito.
     *
     * Quem acabou de se cadastrar precisa ver em que pe esta a aprovacao e
     * completar o que falta. O que ele nao pode e publicar oferta e receber, e
     * isso e decidido em `podeVender()`, nao aqui: misturar as duas perguntas
     * faria a tela de "cadastro em analise" ser inalcancavel justamente para
     * quem precisa dela.
     */
    public function podeEntrar(): bool
    {
        return $this->situacao !== 'bloqueado' && $this->deleted_at === null;
    }

    public function motivoSuspensao(): ?string
    {
        return match ($this->situacao) {
            'bloqueado' => 'Acesso bloqueado pela administração. Fale com a Avalia 360.',
            'pendente' => 'Cadastro em análise. Você ainda não pode publicar ofertas.',
            default => null,
        };
    }

    public function revogaSessoes(): void
    {
        $this->increment('sessao_versao');
        $this->forceFill(['remember_token' => null])->saveQuietly();
    }
}
