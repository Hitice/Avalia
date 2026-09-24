import qrcode from 'qrcode-generator';

/*
 * O desenho do QR das plaquinhas.
 *
 * Roda no navegador, e nao no servidor, por imposicao da hospedagem: la nao ha
 * composer para instalar biblioteca PHP, e `vendor` nao vai no git. O que o
 * Vite empacota, por outro lado, chega pronto, porque `public/build` e
 * versionado. De quebra, tira cem renderizacoes de imagem do CPU compartilhado
 * e da previa instantanea na tela de quem esta montando a tiragem.
 *
 * Nada do que sai daqui e guardado. O desenho e determinado pelo codigo, entao
 * a tiragem de 2026 pode ser refeita identica em 2031 a partir da mesma lista.
 */

/** Modulos de silencio de cada lado, dentro da geometria. */
const QUIETA = 4;

/** Lado do logo, em modulos. Em H (30% de correcao) sobra margem de sobra. */
const LOGO = 7;

/**
 * Lado do vao aberto no miolo, em modulos.
 *
 * O espaco do logo e ABERTO na grade, e nao tapado com um quadrado branco por
 * cima. Tapar deixa a marca dentro de uma caixa com borda propria, que aparece
 * na placa como um adesivo colado no codigo; abrindo, a marca fica no vazio e
 * o fundo da peca passa por baixo dela.
 *
 * DOIS modulos de folga de cada lado do logo. Com um so, a marca encostava nos
 * modulos escuros e parecia adesivo colado por cima do codigo; com dois ela
 * respira e o olho entende que o vao faz parte do desenho.
 *
 * Sao 121 dos 841 modulos da versao 3, ou 14,4%. O nivel H recupera ate 30%,
 * entao ainda sobra margem para risco de acrilico e reflexo de vitrine, que e
 * justamente para o que a correcao existe.
 */
const VAO = LOGO + 4;

/**
 * A marca da Avalia, em coordenadas de 32 unidades.
 *
 * Copiada de public/favicon.svg. Fica aqui, e nao carregada por rede, porque o
 * ZIP precisa sair pronto sem depender de mais uma requisicao dar certo no
 * meio de mil arquivos.
 */
const MARCA = [
    '<path d="M4.5 22.5a11.5 11.5 0 0 1 23 0" stroke="#fb6514" stroke-opacity="0.35" stroke-width="3.6" stroke-linecap="round" fill="none"/>',
    '<path d="M4.5 22.5A11.5 11.5 0 0 1 16 11" stroke="#fb6514" stroke-width="3.6" stroke-linecap="round" fill="none"/>',
    '<path d="M16 22.5 22.3 14.6" stroke="#fb6514" stroke-width="3.6" stroke-linecap="round" fill="none"/>',
    '<circle cx="16" cy="22.5" r="3" fill="#fb6514"/>',
].join('');

/** O intervalo de modulos que o logo ocupa, no centro da grade. */
function vaoDoMiolo(n) {
    const inicio = Math.floor((n - VAO) / 2);

    return { de: inicio, ate: inicio + VAO - 1 };
}

function noVao(vao, linha, coluna) {
    return vao !== null
        && linha >= vao.de && linha <= vao.ate
        && coluna >= vao.de && coluna <= vao.ate;
}

/**
 * A grade de modulos de um texto.
 *
 * Tenta o modo alfanumerico primeiro: ele so aceita maiuscula, digito e alguns
 * simbolos, e e bem mais compacto que o modo byte. A URL da plaquinha e feita
 * para caber nele. O `catch` existe para o resto (um endereco de teste em
 * minusculo, um dominio com underline), que cai no modo byte e apenas rende um
 * QR maior.
 */
export function grade(texto, nivel = 'H') {
    try {
        return montar(texto, nivel, 'Alphanumeric');
    } catch {
        return montar(texto, nivel, 'Byte');
    }
}

function montar(texto, nivel, modo) {
    const qr = qrcode(0, nivel);
    qr.addData(texto, modo);
    qr.make();

    return qr;
}

/**
 * O SVG que vai para o CorelDRAW.
 *
 * Cada detalhe aqui responde a um problema da bancada, e nenhum e estetico:
 *
 * UM caminho so, e nao um retangulo por modulo. Mil retangulos viram mil
 * objetos no Corel e travam a selecao; um caminho vira uma curva unica.
 *
 * Medida em MILIMETROS no `width`, com `viewBox` em modulos. Assim o arquivo
 * cai no Corel no tamanho fisico da placa, e nao em pixels de 96dpi que
 * alguem vai ter que reescalar no olho.
 *
 * ZONA DE SILENCIO dentro do desenho. Sem ela na geometria, quem monta a arte
 * encosta o QR na borda da placa e o codigo para de ler.
 *
 * O MIOLO JA SAI VAZIO quando ha logo: os modulos do centro nao sao
 * desenhados, em vez de serem cobertos depois. A marca fica sobre o vazio, sem
 * caixa branca e sem borda propria.
 *
 * O logo e o fundo saem em grupos marcados, para serem apagados ou
 * recoloridos no Corel quando a placa for de uma cor so.
 */
export function svg(texto, { mm = 30, nivel = 'H', logo = true, pixels = null } = {}) {
    const qr = grade(texto, nivel);
    const n = qr.getModuleCount();
    const lado = n + QUIETA * 2;

    const vao = logo ? vaoDoMiolo(n) : null;
    let d = '';

    for (let linha = 0; linha < n; linha++) {
        for (let coluna = 0; coluna < n; coluna++) {
            if (qr.isDark(linha, coluna) && ! noVao(vao, linha, coluna)) {
                d += `M${coluna + QUIETA} ${linha + QUIETA}h1v1h-1z`;
            }
        }
    }

    // Em pixels quando o destino e o canvas do PNG; em milimetros quando o
    // destino e a mesa do Corel.
    const medida = pixels ? `width="${pixels}" height="${pixels}"` : `width="${mm}mm" height="${mm}mm"`;

    return [
        `<svg xmlns="http://www.w3.org/2000/svg" ${medida} viewBox="0 0 ${lado} ${lado}" shape-rendering="crispEdges">`,
        `<rect id="fundo" width="${lado}" height="${lado}" fill="#ffffff"/>`,
        `<path d="${d}" fill="#000000" fill-rule="evenodd"/>`,
        logo ? miolo(lado) : '',
        '</svg>',
    ].join('');
}

/** A marca no centro, sobre o vao que ja foi aberto na grade. */
function miolo(lado) {
    const escala = LOGO / 32;
    const canto = (lado - LOGO) / 2;

    return `<g id="logo" transform="translate(${canto} ${canto}) scale(${escala})">${MARCA}</g>`;
}

/**
 * O PNG de prova.
 *
 * Os modulos sao pintados direto no canvas, e nao convertidos do SVG: e
 * sincrono, nao depende de o navegador carregar imagem nenhuma, e nao ha como
 * mil arquivos falharem no meio do ZIP por causa disso. So o logo passa por
 * imagem, e se ele falhar o codigo sai sem logo em vez de o lote inteiro
 * quebrar: QR sem marca ainda le; ZIP pela metade nao serve para nada.
 *
 * O vao do miolo e aberto aqui tambem, e pelo mesmo motivo do SVG: a marca
 * fica sobre o fundo da peca, e nao dentro de um quadrado branco.
 */
export async function png(texto, { pixels = 1200, nivel = 'H', logo = true } = {}) {
    const qr = grade(texto, nivel);
    const n = qr.getModuleCount();
    const lado = n + QUIETA * 2;

    const tela = document.createElement('canvas');
    tela.width = pixels;
    tela.height = pixels;

    const pincel = tela.getContext('2d');
    const passo = pixels / lado;

    pincel.fillStyle = '#ffffff';
    pincel.fillRect(0, 0, pixels, pixels);
    pincel.fillStyle = '#000000';

    const vao = logo ? vaoDoMiolo(n) : null;

    for (let linha = 0; linha < n; linha++) {
        for (let coluna = 0; coluna < n; coluna++) {
            if (qr.isDark(linha, coluna) && ! noVao(vao, linha, coluna)) {
                // Ceil no tamanho fecha a fresta de subpixel entre modulos
                // vizinhos, que na impressao vira listra branca no codigo.
                pincel.fillRect(
                    Math.floor((coluna + QUIETA) * passo),
                    Math.floor((linha + QUIETA) * passo),
                    Math.ceil(passo),
                    Math.ceil(passo),
                );
            }
        }
    }

    if (logo) {
        try {
            await pintarMarca(pincel, lado, passo);
        } catch {
            // Segue sem marca.
        }
    }

    return new Promise((resolva) => tela.toBlob(resolva, 'image/png'));
}

function pintarMarca(pincel, lado, passo) {
    const fonte = `<svg xmlns="http://www.w3.org/2000/svg" width="${LOGO * passo}" height="${LOGO * passo}" viewBox="0 0 32 32">${MARCA}</svg>`;
    const imagem = new Image;

    return new Promise((resolva, recuse) => {
        imagem.onload = () => {
            const em = ((lado - LOGO) / 2) * passo;
            pincel.drawImage(imagem, em, em, LOGO * passo, LOGO * passo);
            resolva();
        };
        imagem.onerror = recuse;
        imagem.src = 'data:image/svg+xml;charset=utf-8,'.concat(encodeURIComponent(fonte));
    });
}

/**
 * A planilha que o Print Merge do CorelDRAW le.
 *
 * Ponto e virgula e BOM porque e assim que o Excel em portugues abre um CSV
 * sem transformar tudo em uma coluna so e sem estragar acento. As colunas de
 * arquivo existem para o Corel achar a imagem de cada linha sozinho: monta-se
 * a placa uma vez, e ele numera e troca o QR na tiragem inteira.
 */
export function csv(etiquetas) {
    const linhas = [['sequencia', 'codigo', 'url', 'arquivo_svg', 'arquivo_png']];

    etiquetas.forEach((etiqueta) => linhas.push([
        etiqueta.sequencia ?? '',
        etiqueta.codigo,
        etiqueta.url,
        `${etiqueta.arquivo}.svg`,
        `${etiqueta.arquivo}.png`,
    ]));

    return '﻿'.concat(linhas.map((linha) => linha.join(';')).join('\r\n'));
}

/** O pacote da tiragem inteira, pronto para a bancada. */
export async function pacote(pasta, etiquetas, opcoes = {}, aoAndar = () => {}) {
    const JSZip = (await import('jszip')).default;
    const zip = new JSZip;
    const dentro = zip.folder(pasta);

    for (let i = 0; i < etiquetas.length; i++) {
        const etiqueta = etiquetas[i];

        dentro.file(`${etiqueta.arquivo}.svg`, svg(etiqueta.url, opcoes));
        dentro.file(`${etiqueta.arquivo}.png`, await png(etiqueta.url, opcoes));

        aoAndar(i + 1, etiquetas.length);
    }

    dentro.file(`${pasta}.csv`, csv(etiquetas));

    return zip.generateAsync({ type: 'blob' });
}

/** Entrega o arquivo ao navegador e solta a memoria em seguida. */
export function baixar(conteudo, nome) {
    const endereco = URL.createObjectURL(conteudo);
    const link = document.createElement('a');

    link.href = endereco;
    link.download = nome;
    link.click();

    URL.revokeObjectURL(endereco);
}
