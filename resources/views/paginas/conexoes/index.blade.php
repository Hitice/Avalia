@extends('layouts.app', ['title' => 'Conexões'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Conexões</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Credenciais dos serviços que a Avalia usa: cobrança, bureaus e consulta veicular.
            Ficam criptografadas e nunca voltam para a tela.
        </p>
    </div>

    @if (session('ok'))
        <div class="aviso aviso-ok mb-6">{{ session('ok') }}</div>
    @endif
    @if (session('erro'))
        <div class="aviso aviso-erro mb-6">{{ session('erro') }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        @foreach ($fornecedores as $slug => $definicao)
            @php $conexao = $conexoes->get($slug); @endphp
            <div class="cartao p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-medium text-gray-800 dark:text-white/90">{{ $definicao['nome'] }}</h2>
                        <span class="ajuda-campo">{{ $definicao['categoria'] }}</span>
                    </div>

                    @if ($conexao?->ativa)
                        <span class="etiqueta etiqueta-sucesso">Ativa</span>
                    @elseif ($conexao?->configurada())
                        <span class="etiqueta etiqueta-alerta">Configurada, inativa</span>
                    @else
                        <span class="etiqueta etiqueta-neutra">Não configurada</span>
                    @endif
                </div>

                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $definicao['descricao'] }}</p>

                <form method="POST" action="{{ route('conexoes.atualizar', $slug) }}" class="mt-5 grid gap-4">
                    @csrf
                    @method('PUT')

                    @if (! empty($definicao['ambientes']))
                        <div>
                            <label class="rotulo-campo" for="ambiente-{{ $slug }}">Ambiente</label>
                            <select class="campo" id="ambiente-{{ $slug }}" name="ambiente">
                                @foreach ($definicao['ambientes'] as $ambiente => $url)
                                    <option value="{{ $ambiente }}" @selected(($conexao->ambiente ?? 'homologacao') === $ambiente)>
                                        @switch($ambiente)
                                            @case('producao') Produção @break
                                            @case('sandbox') Sandbox (livre, sem custo) @break
                                            @default Homologação (teste)
                                        @endswitch
                                    </option>
                                @endforeach
                            </select>
                            <span class="ajuda-campo">A chave e o endereço andam juntos: chave de teste só vale no ambiente de teste.</span>
                        </div>
                    @endif

                    @foreach ($definicao['campos'] as $campo)
                        @php $definida = filled($conexao->credenciais[$campo['chave']] ?? null); @endphp
                        <div>
                            <label class="rotulo-campo" for="{{ $slug }}-{{ $campo['chave'] }}">{{ $campo['rotulo'] }}</label>

                            @if (! empty($campo['secreto']))
                                <input class="campo" type="password" autocomplete="new-password"
                                       id="{{ $slug }}-{{ $campo['chave'] }}" name="campo_{{ $campo['chave'] }}"
                                       placeholder="{{ $definida ? 'Definida. Preencha só para trocar.' : '' }}">
                            @else
                                <input class="campo" type="text"
                                       id="{{ $slug }}-{{ $campo['chave'] }}" name="campo_{{ $campo['chave'] }}"
                                       value="{{ $conexao->credenciais[$campo['chave']] ?? '' }}">
                            @endif

                            @if (! empty($campo['ajuda']))
                                <span class="ajuda-campo">{{ $campo['ajuda'] }}</span>
                            @endif
                        </div>
                    @endforeach

                    <div class="flex flex-wrap items-center gap-3">
                        <x-avalia.botao tamanho="sm">Salvar</x-avalia.botao>
                        <x-avalia.botao variante="secundario" tamanho="sm" :href="$definicao['doc']" target="_blank" rel="noopener noreferrer">
                            Documentação
                        </x-avalia.botao>
                    </div>
                </form>

                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <form method="POST" action="{{ route('conexoes.alternar', $slug) }}" class="inline">
                        @csrf
                        <x-avalia.botao variante="secundario" tamanho="sm">
                            {{ $conexao?->ativa ? 'Desativar' : 'Ativar' }}
                        </x-avalia.botao>
                    </form>

                    <form method="POST" action="{{ route('conexoes.testar', $slug) }}" class="inline">
                        @csrf
                        <x-avalia.botao variante="secundario" tamanho="sm">Testar conexão</x-avalia.botao>
                    </form>

                    @if ($conexao?->testada_em)
                        <span class="text-xs {{ $conexao->teste_ok ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">
                            {{ $conexao->teste_ok ? 'Conferida' : 'Falhou' }} em {{ $conexao->testada_em->format('d/m H:i') }}
                        </span>
                    @endif
                </div>

                @if (! empty($definicao['webhook']))
                    {{-- A URL que o fornecedor precisa conhecer para avisar a
                         Avalia. O token vai junto, cadastrado la e aqui. --}}
                    <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-900/60 dark:text-gray-300">
                        Cadastre no painel do fornecedor a URL
                        <code class="font-mono text-gray-800 dark:text-white/90">{{ route($definicao['webhook']) }}</code>
                        com o token do webhook acima.
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endsection
