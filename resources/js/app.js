import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// O balao nativo de validacao do navegador nao respeita o tema e nao aceita
// estilo. Com novalidate em todo formulario, quem valida e o servidor, e a
// mensagem volta no nosso padrao (erro-campo), no nosso tom e nas nossas
// cores. O required continua no HTML pela semantica e pelos leitores de tela.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach((f) => f.setAttribute('novalidate', ''));
});

/*
 * Aparecer ao rolar.
 *
 * A marca no <html> e posta antes de qualquer coisa: o CSS so esconde o que
 * vai ser revelado quando ela existe, entao uma falha aqui deixa a pagina sem
 * animacao, e nao invisivel.
 *
 * O observador solta o elemento depois de revela-lo. Bloco que some e volta a
 * aparecer a cada rolagem vira piscada, e quem sobe e desce a pagina uma vez
 * ja viu o efeito.
 */
document.documentElement.classList.add('com-js');

document.addEventListener('DOMContentLoaded', () => {
    const alvos = document.querySelectorAll('[data-revelar]');

    if (alvos.length === 0) {
        return;
    }

    // Sem IntersectionObserver, tudo aparece de uma vez: a alternativa seria
    // deixar o conteudo escondido para sempre.
    if (! ('IntersectionObserver' in window)) {
        alvos.forEach((alvo) => alvo.classList.add('revelado'));

        return;
    }

    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach((entrada) => {
            if (! entrada.isIntersecting) {
                return;
            }

            entrada.target.classList.add('revelado');
            observador.unobserve(entrada.target);
        });
        // A margem negativa embaixo segura o gatilho ate o bloco entrar de
        // verdade: disparando na primeira fatia de pixel, a animacao terminava
        // antes de o bloco estar visivel.
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.05 });

    alvos.forEach((alvo) => observador.observe(alvo));
});

/*
 * A tela de uma tiragem de plaquinhas.
 *
 * O desenho dos QR, o ZIP e o CSV sao carregados sob demanda: quem abre
 * qualquer outra tela do sistema nao paga pelo peso da biblioteca de imagem
 * nem pela de compactacao, que so esta pagina usa.
 */
Alpine.data('tiragem', (dados) => ({
    etiquetas: dados.etiquetas,
    pasta: dados.pasta,
    // 50mm sempre. O tamanho do arquivo nao decide o tamanho da peca: quem
    // monta a arte no Corel escala o vetor para o que a placa pedir, e um
    // campo a mais na tela so pedia uma decisao que nao muda nada.
    mm: 50,
    logo: true,
    gerando: false,
    feito: 0,
    erro: '',

    init() {
        this.desenhar();
        this.$watch('mm', () => this.desenhar());
        this.$watch('logo', () => this.desenhar());
    },

    /** A prova na tela, sempre do primeiro codigo da tiragem. */
    async desenhar() {
        if (this.etiquetas.length === 0) {
            return;
        }

        const { svg } = await import('./qr.js');

        this.$refs.prova.innerHTML = svg(this.etiquetas[0].url, {
            mm: Math.max(10, Math.min(200, Number(this.mm) || 50)),
            logo: this.logo,
        });
    },

    async baixar() {
        this.erro = '';
        this.feito = 0;
        this.gerando = true;

        try {
            const qr = await import('./qr.js');

            const zip = await qr.pacote(
                this.pasta,
                this.etiquetas,
                { mm: Number(this.mm) || 50, logo: this.logo },
                (feito) => { this.feito = feito; },
            );

            qr.baixar(zip, `${this.pasta}.zip`);
        } catch (erro) {
            // Falha no meio de mil arquivos precisa aparecer na tela: o
            // download simplesmente nao acontece, e sem aviso quem esta na
            // bancada acha que o navegador travou.
            this.erro = `Não foi possível montar o pacote: ${erro.message}`;
        } finally {
            this.gerando = false;
        }
    },
}));

/*
 * O gerador de QR estatico do site.
 *
 * Tudo acontece aqui, no navegador de quem digitou: o endereco nao e enviado
 * para lugar nenhum, e e isso que a pagina promete. A biblioteca de desenho
 * entra sob demanda, porque so esta pagina do site precisa dela.
 */
Alpine.data('gerador', (inicial = {}) => ({
    // Sem argumento e o gerador publico, onde a pessoa digita. Com argumento e
    // a ficha de uma plaquinha, cujo endereco ja existe e nao se digita.
    conteudo: inicial.conteudo ?? '',
    mm: inicial.mm ?? 50,
    logo: inicial.logo ?? false,
    nome: inicial.nome ?? 'qrcode',

    init() {
        this.desenhar();
        this.$watch('conteudo', () => this.desenhar());
        this.$watch('mm', () => this.desenhar());
        this.$watch('logo', () => this.desenhar());
    },

    tamanho() {
        return Math.max(10, Math.min(200, Number(this.mm) || 50));
    },

    async desenhar() {
        if (! this.conteudo) {
            this.$refs.prova.innerHTML = '';

            return;
        }

        const { svg } = await import('./qr.js');

        // No gerador publico a marca fica de fora: o codigo e de quem gerou, e
        // carimbar a nossa marca no QR de outra pessoa seria a marca d'agua
        // que a pagina promete nao ter. Na plaquinha da casa ela entra.
        this.$refs.prova.innerHTML = svg(this.conteudo, { mm: this.tamanho(), logo: this.logo });
    },

    async baixarSvg() {
        const qr = await import('./qr.js');

        qr.baixar(
            new Blob([qr.svg(this.conteudo, { mm: this.tamanho(), logo: this.logo })], { type: 'image/svg+xml' }),
            `${this.nome}.svg`,
        );
    },

    async baixarPng() {
        const qr = await import('./qr.js');

        qr.baixar(await qr.png(this.conteudo, { pixels: 1200, logo: this.logo }), `${this.nome}.png`);
    },
}));

/*
 * As miniaturas do QR na tabela.
 *
 * Desenhadas aqui, e nao guardadas como imagem: o desenho e determinado pelo
 * codigo, entao guardar arquivo seria manter copia de algo que se refaz em um
 * milissegundo. Uma pagina tem 25 linhas, e as 25 saem juntas.
 */
Alpine.data('miniaturas', (lista) => ({
    async init() {
        const { svg } = await import('./qr.js');

        lista.forEach((item) => {
            const alvo = document.getElementById(`qr-${item.codigo}`);

            if (alvo) {
                alvo.innerHTML = svg(item.url, { mm: 14, logo: true });
            }
        });
    },

    /** O arquivo de verdade sai em 50mm, nao no tamanho da miniatura. */
    async baixar(codigo, tipo) {
        const item = lista.find((linha) => linha.codigo === codigo);

        if (! item) {
            return;
        }

        const qr = await import('./qr.js');

        if (tipo === 'png') {
            qr.baixar(await qr.png(item.url, { pixels: 1200, logo: true }), `${item.arquivo}.png`);

            return;
        }

        qr.baixar(
            new Blob([qr.svg(item.url, { mm: 50, logo: true })], { type: 'image/svg+xml' }),
            `${item.arquivo}.svg`,
        );
    },
}));

/*
 * Por ultimo, e nunca antes.
 *
 * `Alpine.data` registrado depois do `start` nao existe: o Alpine ja varreu a
 * pagina, e todo `x-data` que aponta para um componente registrado tarde
 * quebra com "is not defined". A tela nao mostra erro nenhum, so para de
 * funcionar, e foi exatamente o que aconteceu com a tiragem e com o gerador.
 *
 * Componente novo entra ACIMA desta linha.
 */
Alpine.start();
