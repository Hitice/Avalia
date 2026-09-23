import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

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
