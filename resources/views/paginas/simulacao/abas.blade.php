{{-- Navegacao do modulo de simulacao. Recebe $atual por @include.

     Duas perguntas diferentes sobre o mesmo contrato, e por isso duas abas:
     a Calculadora responde "quanto isto rende para a Avalia", e a Proposta
     responde "quanto isto custa para o cliente e quanto sobra para o vendedor".
     Misturar as duas numa tela so obrigava a ler a resposta errada primeiro. --}}

<x-avalia.segmentado
    class="mb-6"
    :atual="$atual"
    :itens="[
        'calculadora' => ['rotulo' => 'Calculadora', 'url' => route('simulacao.calculadora')],
        'proposta' => ['rotulo' => 'Proposta', 'url' => route('simulacao.proposta')],
    ]" />
