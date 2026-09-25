<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;

/**
 * De quem e o codigo, quando a ferramenta atende mais de um tipo de conta.
 *
 * O QR dinamico abre para administracao, vendedor, cliente e produtor. Sao
 * quatro naturezas em tres tabelas de conta, e sem um lugar so para responder
 * "quem esta logado e o que ele pode ver", cada tela responderia do seu jeito
 * e uma delas responderia errado.
 *
 * A administracao ve tudo porque opera o negocio: e ela que gera a tiragem,
 * atende no telefone e conserta o que o cliente errou. Todo o resto ve o que e
 * seu, e so.
 */
final class Dono
{
    /** Na ordem em que se procura. Staff primeiro: e a base menor. */
    private const GUARDAS = ['staff', 'empresa', 'produtor'];

    public static function tipo(): ?string
    {
        foreach (self::GUARDAS as $guarda) {
            if (Auth::guard($guarda)->check()) {
                return $guarda;
            }
        }

        return null;
    }

    public static function id(): ?int
    {
        $tipo = self::tipo();

        return $tipo ? (int) Auth::guard($tipo)->id() : null;
    }

    /**
     * Quem enxerga o que nao e seu.
     *
     * So a administracao. O vendedor nao entra aqui de proposito: ele vende, e
     * o codigo que ele vendeu e dele; os dos outros vendedores nao sao da
     * conta dele.
     */
    public static function veTudo(): bool
    {
        return Auth::guard('staff')->check() && Auth::guard('staff')->user()?->papel === 'admin';
    }

    /** @return array{dono_tipo: ?string, dono_id: ?int} */
    public static function carimbo(): array
    {
        return ['dono_tipo' => self::tipo(), 'dono_id' => self::id()];
    }

    /**
     * Limita a consulta ao que a conta pode ver.
     *
     * Aceita consulta ou relacao. As duas sabem filtrar, e exigir conversao em
     * cada chamada seria pedir que quem usa lembre da diferenca.
     *
     * @template T of \Illuminate\Database\Eloquent\Builder|Relation
     *
     * @param  T  $consulta
     * @return T
     */
    public static function limitar($consulta)
    {
        if (self::veTudo()) {
            return $consulta;
        }

        return $consulta->where('dono_tipo', self::tipo())->where('dono_id', self::id());
    }

    public static function pode(Model $registro): bool
    {
        return self::veTudo()
            || ($registro->dono_tipo === self::tipo() && (int) $registro->dono_id === self::id());
    }
}
