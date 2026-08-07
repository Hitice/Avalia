@extends('layouts.app', ['title' => 'Novo documento'])

@section('content')
    <div class="mb-6"><h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Novo documento</h1><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">A publicação substitui a versão vigente do mesmo tipo.</p></div>
    <form method="POST" action="{{ route('documentos.salvar') }}" class="cartao grid gap-5 p-6">@csrf
        <div><label class="rotulo-campo" for="titulo">Título</label><input class="campo" id="titulo" name="titulo" value="{{ old('titulo') }}" required></div>
        <div class="grid gap-5 sm:grid-cols-2"><div><label class="rotulo-campo" for="tipo">Tipo</label><input class="campo" id="tipo" name="tipo" placeholder="confidencialidade" value="{{ old('tipo') }}" required></div><div><label class="rotulo-campo" for="versao">Versão</label><input class="campo" id="versao" name="versao" placeholder="2026.08" value="{{ old('versao') }}" required></div></div>
        <div><label class="rotulo-campo" for="conteudo">Conteúdo</label><textarea class="campo min-h-64" id="conteudo" name="conteudo" required>{{ old('conteudo') }}</textarea></div>
        <fieldset><legend class="rotulo-campo">Quem recebe este documento</legend>
            <div class="grid gap-2 sm:grid-cols-3">
                <label class="rotulo-opcao"><input type="checkbox" name="para_empresa" value="1" @checked(old('para_empresa', true)) class="text-brand-500 focus:ring-brand-500/20 size-4 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"> Empresa (conta master)</label>
                <label class="rotulo-opcao"><input type="checkbox" name="para_operador" value="1" @checked(old('para_operador', true)) class="text-brand-500 focus:ring-brand-500/20 size-4 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"> Operadores da empresa</label>
                <label class="rotulo-opcao"><input type="checkbox" name="para_vendedor" value="1" @checked(old('para_vendedor', false)) class="text-brand-500 focus:ring-brand-500/20 size-4 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"> Vendedores da equipe</label>
            </div>
        </fieldset>
        <label class="rotulo-opcao"><input type="checkbox" name="exige_aceite" value="1" @checked(old('exige_aceite', true)) class="text-brand-500 focus:ring-brand-500/20 size-4 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"> Exigir aceite na entrada</label>
        <p class="ajuda-campo -mt-3">Sem aceite exigido, o documento fica como leitura e apoio para os públicos marcados.</p>
        <div class="flex gap-3"><x-avalia.botao>Publicar versão</x-avalia.botao><x-avalia.botao variante="secundario" :href="route('documentos.index')">Cancelar</x-avalia.botao></div>
    </form>
@endsection
