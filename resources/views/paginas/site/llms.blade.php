@php use App\Support\Empresa; @endphp
# {{ Empresa::marca() }}

> Software house de {{ config('empresa.endereco.cidade') }} ({{ config('empresa.endereco.uf') }}) e {{ config('empresa.braco.cidade') }} ({{ config('empresa.braco.uf') }}) que cria software sob medida, automação de processos, atendimento com IA e cobrança para empresas do Brasil. Mantém as plataformas {{ Empresa::marcaCredito() }} (pesquisa de score para venda a prazo), {{ Empresa::marcaCobranca() }} (venda parcelada com cobrança automática) e {{ Empresa::marcaVendas() }} (QR Code dinâmico e link de avaliação do Google).

Razão social {{ Empresa::razaoSocial() }}, CNPJ {{ Empresa::cnpj() }}. Site em português do Brasil.

## Softwares
@foreach ($softwares as $slug => $software)
- [{{ $software['titulo'] }}]({{ route('site.softwares') }}#{{ $slug }}): {{ $software['resumo'] }}
@endforeach

## Serviços digitais
@foreach ($servicos as $servico)
@if ($servico['rota'])
- [{{ $servico['titulo'] }}]({{ route($servico['rota']) }})
@endif
@endforeach
- [{{ Empresa::marcaCredito() }}]({{ route('credito') }}): pesquisa de score para venda a prazo
- [{{ Empresa::marcaCobranca() }}]({{ route('cobranca') }}): venda parcelada com cobrança automática

## Sobre
- [Quem somos]({{ route('site.quem-somos') }})
- [Perguntas frequentes]({{ route('site.perguntas') }})
- [Blog]({{ route('site.blog') }})
- [Política de privacidade]({{ route('site.privacidade') }})
- [Termos de uso]({{ route('site.termos') }})

## Contato
- [Formulário de contato]({{ route('site.contato') }})
- E-mail: {{ Empresa::email() }}
- {{ Empresa::rotulo() }}: {{ Empresa::endereco() }}
- {{ Empresa::bracoRotulo() }}: {{ Empresa::bracoEndereco() }}
