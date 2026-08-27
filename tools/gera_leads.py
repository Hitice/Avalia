"""Transcreve a base de leads de docs/leads.html para o seeder.

Gera database/seeders/dados/leads_base_2026_08.php. Rode de novo quando a
extracao dos PDFs produzir uma base nova; nao edite o PHP a mao.

    python tools/gera_leads.py

O prototipo guarda a base numa constante SEED do proprio HTML, no formato em
que ela saiu dos PDFs: CNPJ com mascara, UF as vezes vazia, e o marcador
"(INATIVO)" colado no comeco do nome de quem ja nao opera. Aqui isso vira
coluna: CNPJ so com os digitos, para casar com o que a tabela de clientes
guarda, e o marcador vira o booleano `ativo`.
"""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ORIGEM = ROOT / 'docs' / 'leads.html'
OUTPUT = ROOT / 'database' / 'seeders' / 'dados' / 'leads_base_2026_08.php'

INATIVO = re.compile(r'\(\s*INATIVO\s*\)', re.I)


def php_texto(valor):
    if valor in (None, ''):
        return 'null'

    return "'" + valor.replace('\\', '\\\\').replace("'", "\\'") + "'"


def le():
    html = ORIGEM.read_text(encoding='utf-8')
    bruto = re.search(r'const SEED = (\[.*?\]);\n', html, re.S)

    if not bruto:
        raise SystemExit('Nao achei a constante SEED em docs/leads.html.')

    leads = []
    vistos = set()

    for linha in json.loads(bruto.group(1)):
        nome = INATIVO.sub('', linha['nome']).strip()
        codigo = str(linha['id']).strip()

        if not nome or codigo in vistos:
            continue

        vistos.add(codigo)

        leads.append({
            'codigo': codigo or None,
            'nome': nome[:160],
            'cnpj': (re.sub(r'[^0-9A-Z]', '', linha['cnpj'].upper()) or None),
            'cidade': linha['cidade'].strip() or None,
            'uf': linha['uf'].strip().upper() or None,
            'telefone': linha['tel'].strip()[:60] or None,
            'email': linha['email'].strip().lower()[:160] or None,
            'origem': linha['src'].strip() or None,
            'ativo': not INATIVO.search(linha['nome']),
        })

    return leads


def php(leads):
    linhas = [
        '<?php',
        '',
        '/*',
        ' * Base de leads extraida dos PDFs da prospeccao, 08/2026.',
        ' *',
        ' * ARQUIVO GERADO por tools/gera_leads.py. Nao edite a mao: rode o script de',
        ' * novo quando a extracao produzir uma base nova.',
        ' *',
        ' * CNPJ so com os digitos, do mesmo jeito que a tabela de clientes guarda: e o',
        ' * que permite comparar com a carteira e descobrir que o lead ja e cliente.',
        ' * `ativo` falso e o marcador "(INATIVO)" que vinha colado no nome.',
        ' */',
        '',
        'return [',
    ]

    for lead in leads:
        campos = ', '.join([
            f"'codigo' => {php_texto(lead['codigo'])}",
            f"'nome' => {php_texto(lead['nome'])}",
            f"'cnpj' => {php_texto(lead['cnpj'])}",
            f"'cidade' => {php_texto(lead['cidade'])}",
            f"'uf' => {php_texto(lead['uf'])}",
            f"'telefone' => {php_texto(lead['telefone'])}",
            f"'email' => {php_texto(lead['email'])}",
            f"'origem' => {php_texto(lead['origem'])}",
            f"'ativo' => {'true' if lead['ativo'] else 'false'}",
        ])
        linhas.append(f'    [{campos}],')

    linhas += ['];', '']

    return '\n'.join(linhas)


def main():
    leads = le()

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    # newline='\n': o Pint exige LF, e o padrao do Windows gravaria CRLF.
    with open(OUTPUT, 'w', encoding='utf-8', newline='\n') as arquivo:
        arquivo.write(php(leads))

    print(f'{len(leads)} leads gravados em {OUTPUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()
