<?php

namespace App\Http\Requests;

use App\Models\Catalogo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Imposto, margem alvo e degrau da escada.
 *
 * A validacao cruzada vive aqui e nao no controller: quem decide se a
 * combinacao e possivel e a regra, nao o fluxo HTTP.
 */
class ParametrosCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'imposto' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'margem_alvo' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'degrau_margem' => ['required', 'numeric', 'min:0', 'max:20'],
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador) {
            $catalogo = $this->route('catalogo');

            if (! $catalogo instanceof Catalogo) {
                return;
            }

            // A faixa mais baixa acumula todos os degraus, entao e ela que
            // precisa caber em 100% junto com imposto e comissao. Testar so a
            // margem alvo deixaria passar uma escada impossivel.
            $degraus = max(0, count($catalogo->faixas()) - 1);
            $maior = $this->bps('margem_alvo') + $this->bps('degrau_margem') * $degraus;

            if ($this->bps('imposto') + $catalogo->comissaoBps() + $maior >= 10_000) {
                $validador->errors()->add('degrau_margem', sprintf(
                    'Na faixa mais baixa a margem chegaria a %s%%, e com imposto e comissao passa de 100%%.',
                    number_format($maior / 100, 1, ',', '.'),
                ));
            }
        });
    }

    public function bps(string $campo): int
    {
        return (int) round((float) $this->input($campo) * 100);
    }
}
