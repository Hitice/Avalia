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
