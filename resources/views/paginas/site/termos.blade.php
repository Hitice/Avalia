@extends('layouts.site', [
    'titulo' => 'Termos de uso',
    'descricao' => 'Condições de uso do site da Avalia.',
])

@php
    use App\Support\Empresa;
@endphp

@section('content')
    <x-site.cabecalho selo="Institucional" titulo="Termos de uso">
        <x-slot:rodape>
            <p class="text-sm text-white/50">Atualizados em 23 de setembro de 2026</p>
        </x-slot:rodape>
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <article class="prosa">
                <p>
                    Estes termos regulam o uso do site da {{ Empresa::marca() }}, mantido por
                    {{ Empresa::razaoSocial() }}, CNPJ {{ Empresa::cnpj() }}. Ao navegar pelo site,
                    você concorda com as condições abaixo.
                </p>

                <h2>1. Finalidade do site</h2>
                <p>
                    O site apresenta a {{ Empresa::marca() }}, suas soluções e conteúdos informativos.
                    As informações publicadas têm caráter geral e não constituem proposta comercial.
                    Condições de contratação, escopo e investimento são definidos em proposta
                    específica.
                </p>

                <h2>2. Plataformas com login</h2>
                <p>
                    O {{ Empresa::marcaCredito() }} e o {{ Empresa::marcaCobranca() }} são plataformas
                    com acesso restrito a contratantes. O uso de cada uma é regido pelo contrato e
                    pelos termos aceitos dentro da própria plataforma, que prevalecem sobre estes
                    termos no que for específico.
                </p>

                <h2>3. Conteúdo do blog</h2>
                <p>
                    Os artigos têm objetivo informativo e não substituem orientação contábil, fiscal
                    ou jurídica aplicada ao caso concreto da sua empresa.
                </p>

                <h2>4. Propriedade intelectual</h2>
                <p>
                    As marcas da casa, o logotipo, os textos, o layout e demais elementos do site
                    pertencem à {{ Empresa::marca() }} ou são usados com autorização. A reprodução sem
                    autorização prévia não é permitida.
                </p>

                <h2>5. Links externos</h2>
                <p>
                    O site pode conter links para serviços de terceiros, como WhatsApp e provedores de
                    e-mail. A {{ Empresa::marca() }} não se responsabiliza pelo conteúdo nem pelas
                    práticas desses serviços.
                </p>

                <h2>6. Disponibilidade</h2>
                <p>
                    Trabalhamos para manter o site disponível e atualizado, mas podemos alterar,
                    suspender ou encerrar conteúdos a qualquer momento, sem aviso prévio.
                </p>

                <h2>7. Privacidade</h2>
                <p>
                    O tratamento de dados pessoais segue a nossa
                    <a href="{{ route('site.privacidade') }}">Política de privacidade</a>.
                </p>

                <h2>8. Legislação aplicável</h2>
                <p>Estes termos são regidos pela legislação brasileira.</p>

                <h2>9. Contato</h2>
                <p>
                    Dúvidas sobre estes termos podem ser enviadas para
                    <a href="mailto:{{ Empresa::email() }}">{{ Empresa::email() }}</a>.
                </p>
            </article>
        </div>
    </section>
@endsection
