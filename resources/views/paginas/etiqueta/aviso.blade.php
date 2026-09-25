@php
    use App\Support\Dinheiro;
    use App\Support\Empresa;
    use App\Support\Suporte;

    /*
     * O que cada estado da plaquinha diz a quem acabou de ler o codigo.
     *
     * Quem esta do outro lado quase nunca e o dono da placa: e um fregues
     * parado no balcao de uma loja, com o celular na mao. Por isso o titulo
     * nunca cobra nada dele, e a conversa sobre pagamento fica embaixo,
     * endereçada a quem é responsavel. Cobranca na cara do fregues constrange
     * o nosso cliente na frente do cliente dele.
     */
    $textos = [
        'nao_encontrada' => [
            'selo' => 'Código não encontrado',
            'titulo' => 'Este código não existe.',
            'texto' => 'Confira o código impresso na plaquinha: as letras I, L, O e U não são usadas, '
                .'e o que parece um deles costuma ser 1 ou 0.',
        ],
        'em_branco' => [
            'selo' => 'Plaquinha nova',
            'titulo' => 'Esta plaquinha ainda não foi ativada.',
            'texto' => 'Ela já tem endereço próprio e reservado, mas ainda não aponta para lugar nenhum. '
                .'Quem comprou pode pedir a ativação a qualquer momento.',
        ],
        'suspensa' => [
            'selo' => 'Fora do ar',
            'titulo' => 'Este endereço está fora do ar no momento.',
            'texto' => 'A plaquinha existe e continua reservada. Ela volta a funcionar assim que o '
                .'responsável pedir.',
        ],
        'vencida' => [
            'selo' => 'Fora do ar',
            'titulo' => 'Este endereço está fora do ar no momento.',
            'texto' => 'A plaquinha existe e continua reservada, com o mesmo código de sempre.',
        ],
        'baixada' => [
            'selo' => 'Encerrado',
            'titulo' => 'Esta plaquinha foi encerrada.',
            'texto' => 'O endereço não aponta mais para nenhum lugar, e o código não será usado de novo '
                .'por ninguém.',
        ],
    ];

    $ficha = $textos[$estado] ?? $textos['nao_encontrada'];
@endphp

@extends('layouts.site', [
    'titulo' => $ficha['selo'],
    'descricao' => $ficha['titulo'],
    // A leitura de uma plaquinha nao e conteudo, e nao entra em buscador.
    'robots' => 'noindex, nofollow',
])

@section('content')
    <x-site.cabecalho :selo="$ficha['selo']" :titulo="$ficha['titulo']"
                      icone="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 4h2m-2-4h6m-2 4v2">
        {{ $ficha['texto'] }}

        <x-slot:rodape>
            @if ($estado !== 'nao_encontrada')
                <p class="text-sm text-white/50">
                    Código <span class="font-semibold tracking-widest text-white/80">{{ $codigo }}</span>
                </p>
            @endif
        </x-slot:rodape>
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[42rem] px-6">
            {{-- A conversa de dinheiro, so no estado que a pede, e so depois
                 do aviso neutro la em cima. --}}
            @if ($estado === 'vencida')
                <div class="cartao p-6 lg:p-8" data-revelar>
                    <h2 class="rotulo-grupo">É o responsável por esta plaquinha?</h2>

                    <p class="mt-4 leading-relaxed text-gray-600">
                        O serviço de redirecionamento venceu. A renovação custa
                        <strong class="text-gray-800">{{ Dinheiro::brl((int) config('etiquetas.precos.renovacao_cents')) }} por ano</strong>,
                        e o endereço volta a funcionar no mesmo dia, com o mesmo código: nada precisa
                        ser reimpresso.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <x-avalia.botao :href="Suporte::whatsapp('Renovação de plaquinha', $codigo)">
                            Renovar pelo WhatsApp
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </x-avalia.botao>

                        <a href="{{ route('site.contato') }}" class="botao botao-secundario">Falar com a Avalia</a>
                    </div>
                </div>
            @else
                <div class="cartao p-6 lg:p-8" data-revelar>
                    <h2 class="rotulo-grupo">Precisa resolver isto?</h2>

                    <p class="mt-4 leading-relaxed text-gray-600">
                        Os códigos QR da {{ Empresa::marca() }} têm destino editável: o endereço impresso
                        é permanente, e para onde ele leva pode mudar a qualquer momento, sem
                        reimpressão.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <x-avalia.botao :href="route('site.contato')">
                            Falar com a {{ Empresa::marca() }}
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </x-avalia.botao>

                        <a href="{{ route('inicio') }}" class="botao botao-secundario">Conhecer a {{ Empresa::marca() }}</a>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
