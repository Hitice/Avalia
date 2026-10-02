{{-- So a tabela: a busca pede este pedaco e troca na pagina, sem recarregar. --}}
@php use App\Support\Dinheiro; @endphp
<div id="tabela-etiquetas">
        <div class="tabela-rolagem">
            <table class="tabela min-w-[56rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th scope="col" class="tabela-th text-left">QR</th>
                        <th scope="col" class="tabela-th text-left">Código</th>
                        <th scope="col" class="tabela-th text-left">Cliente</th>
                        <th scope="col" class="tabela-th text-left">Vendido por</th>
                        <th scope="col" class="tabela-th text-left">Aponta para</th>
                        <th scope="col" class="tabela-th text-left">Situação</th>
                        <th scope="col" class="tabela-th text-left">Vence</th>
                        <th scope="col" class="tabela-th text-right">Leituras</th>
                        <th scope="col" class="tabela-th text-right">Baixar</th>
                        <th scope="col" class="tabela-th text-right">Editar</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800"
                       x-data="miniaturas(@js($miniaturas))">
                    @forelse ($etiquetas as $etiqueta)
                        @php $estado = $etiqueta->estado(); @endphp
                        <tr>
                            {{-- A miniatura desenhada na hora. O codigo manda
                                 no desenho, entao guardar imagem seria manter
                                 copia de algo que se refaz num milissegundo. --}}
                            <td class="tabela-td w-16">
                                {{-- Branco nos dois temas, de proposito: um QR
                                     sobre fundo escuro nao le, e a miniatura
                                     precisa parecer com o que sai impresso. --}}
                                <div id="qr-{{ $etiqueta->codigo }}" class="size-14 rounded bg-white p-0.5 dark:bg-white"></div>
                            </td>

                            <td class="tabela-td">
                                <a href="{{ route('etiquetas.ficha', $etiqueta) }}"
                                   class="font-mono font-medium tracking-wider text-gray-800 hover:text-brand-500 dark:text-white/90">
                                    {{ $etiqueta->codigo }}
                                </a>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $etiqueta->lote?->titulo ?? 'Sem campanha' }}
                                </span>
                            </td>

                            <td class="tabela-td text-gray-600 dark:text-gray-300">
                                {{ $etiqueta->cliente_nome ?? '—' }}
                            </td>

                            {{-- Placa em branco nao tem vendedor porque nao foi
                                 vendida; vendida sem vendedor e o caso da que
                                 o proprio cliente apontou. Sao coisas
                                 diferentes e a coluna diz qual e qual. --}}
                            <td class="tabela-td text-gray-600 dark:text-gray-300">
                                @if ($etiqueta->vendida_em === null)
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @else
                                    {{ $etiqueta->vendedor?->nome ?? 'Não identificado' }}
                                @endif
                            </td>

                            <td class="tabela-td max-w-[22rem] truncate text-gray-600 dark:text-gray-300">
                                {{ $etiqueta->destino ?? '—' }}
                            </td>

                            <td class="tabela-td">
                                {{-- Carencia e vencida nao existem no banco: sao
                                     conta de data, e e o estado calculado que a
                                     leitura da plaquinha enxerga. --}}
                                <span @class([
                                    'etiqueta',
                                    'etiqueta-sucesso' => $estado === 'ativa',
                                    'etiqueta-alerta' => in_array($estado, ['carencia', 'em_branco'], true),
                                    'etiqueta-erro' => in_array($estado, ['vencida', 'suspensa'], true),
                                    'etiqueta-neutra' => $estado === 'baixada',
                                ])>
                                    @switch($estado)
                                        @case('carencia') Em carência @break
                                        @case('vencida') Vencida @break
                                        @default {{ $etiqueta->situacao->rotulo() }}
                                    @endswitch
                                </span>
                            </td>

                            <td class="tabela-td text-gray-600 dark:text-gray-300">
                                {{ $etiqueta->vence_em?->format('d/m/Y') ?? '—' }}
                            </td>

                            <td class="tabela-td text-right tabular-nums">{{ $etiqueta->total_acessos }}</td>

                            <td class="tabela-td text-right whitespace-nowrap">
                                <button type="button" x-on:click="baixar('{{ $etiqueta->codigo }}', 'svg')"
                                        class="botao botao-secundario botao-sm">SVG</button>
                                <button type="button" x-on:click="baixar('{{ $etiqueta->codigo }}', 'png')"
                                        class="botao botao-secundario botao-sm ml-1">PNG</button>
                            </td>

                            {{-- Um botao Editar por linha, que e o padrao da
                                 casa. O codigo continua sendo link, mas link em
                                 texto nao se anuncia como a acao da linha: quem
                                 abre a tela pela primeira vez nao descobre que e
                                 ali que se edita. --}}
                            <td class="tabela-td text-right whitespace-nowrap">
                                <a href="{{ route('etiquetas.ficha', $etiqueta) }}"
                                   class="botao botao-secundario botao-sm">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="tabela-vazia">
                                Gere os primeiros códigos acima. Eles nascem em branco, e ganham
                                destino depois da venda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$etiquetas" />
</div>
