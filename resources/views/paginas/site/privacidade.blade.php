@extends('layouts.site', [
    'titulo' => 'Política de privacidade',
    'descricao' => 'Como a Avalia coleta, usa e protege dados pessoais, conforme a Lei Geral de Proteção de Dados (LGPD).',
])

@php
    use App\Support\Empresa;
@endphp

@section('content')
    <x-site.cabecalho selo="Institucional" titulo="Política de privacidade">
        <x-slot:rodape>
            <p class="text-sm text-white/50">Atualizada em 23 de setembro de 2026</p>
        </x-slot:rodape>
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <article class="prosa">
                <p>
                    Esta política explica como a {{ Empresa::marca() }} trata dados pessoais de quem
                    visita este site, entra em contato conosco ou usa uma das nossas plataformas, em
                    conformidade com a Lei nº 13.709/2018, a Lei Geral de Proteção de Dados (LGPD).
                </p>

                <h2>1. Quem é o controlador</h2>
                {{-- Razao social, CNPJ e endereco saem do cadastro, e nao
                     escritos aqui: documento juridico com CNPJ desatualizado e
                     exatamente o erro que config/empresa.php existe para
                     impedir. --}}
                <p>
                    {{ Empresa::razaoSocial() }}, CNPJ {{ Empresa::cnpj() }}, com sede em
                    {{ Empresa::endereco() }}. A empresa mantém ainda uma unidade de
                    {{ Empresa::bracoRotulo() }} em {{ Empresa::bracoEndereco() }}, que integra a mesma
                    pessoa jurídica e não possui inscrição própria.
                </p>
                <p>
                    Para exercer seus direitos ou tirar dúvidas sobre esta política, escreva para
                    <a href="mailto:{{ Empresa::email() }}">{{ Empresa::email() }}</a>.
                </p>

                <h2>2. Quais dados tratamos</h2>
                <p>
                    <strong>Contato pelo site.</strong> Quando você preenche o formulário da página de
                    contato, guardamos o que foi preenchido: nome, empresa, telefone, endereço de
                    e-mail, assunto e o conteúdo da mensagem. Esses dados ficam registrados no nosso
                    sistema para que a equipe responda e acompanhe o atendimento.
                </p>
                <p>
                    <strong>Plataformas da casa.</strong> O {{ Empresa::marcaCredito() }} e o
                    {{ Empresa::marcaCobranca() }} têm áreas com login e tratam dados próprios de cada
                    contratação, descritos nos contratos e nos termos aceitos dentro de cada
                    plataforma. Esta política cobre o site institucional e o primeiro contato.
                </p>
                <p>
                    <strong>Navegação.</strong> Utilizamos apenas os cookies necessários para manter a
                    sessão e proteger os formulários contra envio forjado. Não há cookies de
                    publicidade nem de rastreamento de terceiros neste site.
                </p>

                <h2>3. Para que usamos os dados</h2>
                <ul>
                    <li>responder às suas solicitações e dúvidas;</li>
                    <li>elaborar e enviar propostas comerciais que você solicitou;</li>
                    <li>dar andamento à relação comercial, quando houver contratação;</li>
                    <li>medir por qual canal os pedidos de contato chegam, em números agregados.</li>
                </ul>

                <h2>4. Bases legais</h2>
                <p>
                    Tratamos os dados para a execução de procedimentos preliminares relacionados a
                    contrato, a pedido do titular (art. 7º, V, da LGPD), e, quando aplicável, com base
                    no legítimo interesse da {{ Empresa::marca() }} em responder e acompanhar contatos
                    comerciais (art. 7º, IX).
                </p>

                <h2>5. Compartilhamento</h2>
                <p>
                    Não vendemos dados pessoais. As comunicações passam pelos provedores de e-mail e
                    pelo WhatsApp, que tratam os dados segundo as suas próprias políticas, e a
                    hospedagem do site é feita por fornecedor contratado, que atua como operador. Os
                    dados também podem ser compartilhados quando houver obrigação legal ou ordem de
                    autoridade competente.
                </p>

                <h2>6. Recursos de terceiros</h2>
                <p>
                    As fontes tipográficas são carregadas do Google Fonts, que recebe dados técnicos
                    da conexão, como o endereço IP, necessários para entregar os arquivos.
                </p>

                <h2>7. Por quanto tempo guardamos</h2>
                <p>
                    Mantemos os pedidos de contato pelo tempo necessário para atender à finalidade do
                    contato e cumprir obrigações legais. Depois disso, os dados são eliminados ou
                    anonimizados.
                </p>

                <h2>8. Seus direitos</h2>
                <p>
                    Nos termos do art. 18 da LGPD, você pode solicitar a qualquer momento: confirmação
                    da existência de tratamento, acesso aos dados, correção de dados incompletos ou
                    desatualizados, anonimização, bloqueio ou eliminação de dados desnecessários,
                    portabilidade, informação sobre compartilhamento e revogação do consentimento,
                    quando aplicável.
                </p>
                <p>
                    Você também pode apresentar reclamação à Autoridade Nacional de Proteção de Dados
                    (ANPD).
                </p>

                <h2>9. Alterações</h2>
                <p>
                    Esta política pode ser atualizada para refletir mudanças no site ou na legislação.
                    A data da última atualização aparece no início da página.
                </p>
            </article>
        </div>
    </section>
@endsection
