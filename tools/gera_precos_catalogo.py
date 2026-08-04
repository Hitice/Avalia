"""Transcreve as tabelas de preco dos PDFs de temp/ para o seeder do catalogo.

Gera database/seeders/dados/precos_bancredi_2026_04.php. Rode de novo quando o
fornecedor publicar uma tabela nova; nao edite o PHP a mao.

    python tools/gera_precos_catalogo.py

Os PDFs tem 7 colunas de preco: sem consumo minimo, depois as seis faixas
(75, 200, 500, 900, 1.500 e 5.000 reais). O extrator precisa do modo layout —
sem ele as colunas saem embaralhadas e os precos vao parar na faixa errada.
"""

import re
from pathlib import Path

from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / 'database' / 'seeders' / 'dados' / 'precos_bancredi_2026_04.php'

VALOR = re.compile(r'R\$\s+(\d+,\d{2})')
ACENTOS = str.maketrans('ÁÂÃÀÇÉÊÍÓÔÕÚ', 'AAAACEEIOOOU')

# Faixas de consumo minimo, em centavos. A primeira e "sem consumo minimo".
FAIXAS = [0, 7_500, 20_000, 50_000, 90_000, 150_000, 500_000]

# Nome cru do PDF (normalizado) -> codigo, nome comercial, exige liberacao.
# Os servicos de SCR ficam com exige_liberacao: dependem de homologacao
# juridica e contratual antes de qualquer chamada externa.
SERVICOS = {
    'CHEQUES SEM FUNDOS - BANCO CENTRAL PF / PJ': (
        'cheques-sem-fundos', 'Cheques sem fundos — Banco Central PF/PJ', False),
    'ACOES JUDICIAIS - NACIONAL PF / PJ': (
        'acoes-judiciais', 'Ações judiciais — nacional PF/PJ', False),
    'BANCREDI - 16 - SCPC BVS PF / PJ - BASE I I I': (
        'scpc-bvs', 'SCPC BVS PF/PJ — Base III', False),
    'RELATORIO PLUS PF/PJ + (CARTORIOS E CCF BACEN) - BASE I I I': (
        'relatorio-plus', 'Relatório Plus PF/PJ + cartórios e CCF Bacen — Base III', False),
    'CREDITO NET BASICA PF / PJ - BASE I': (
        'credito-net-basica', 'Crédito Net Básica PF/PJ — Base I', False),
    'BANCREDI MIX PF / PJ - BASE I E I I': (
        'mix', 'Mix PF/PJ — Base I e II', False),
    'CREDITO NET PF / PJ - BASE I E I I I': (
        'credito-net', 'Crédito Net PF/PJ — Base I e III', False),
    'CREDITO NET TOP + (CARTORIOS E CCF BACEN) - BASE I E I I I': (
        'credito-net-top', 'Crédito Net Top + cartórios e CCF Bacen — Base I e III', False),
    'RELATORIO SCORE POSITIVO + (FILTROS EXTRAS) - BASE I I I': (
        'score-positivo', 'Relatório Score Positivo + filtros — Base III', False),
    'RISCO DE CREDITO TOP PF / PJ + (FILTROS EXTRAS) - BASE I': (
        'risco-credito-top', 'Risco de Crédito Top PF/PJ + filtros — Base I', False),
    'RELATORIO TOP PF / PJ + (FILTROS EXTRAS) - BASE I E I I I': (
        'relatorio-top', 'Relatório Top PF/PJ + filtros — Base I e III', False),
    'RELATORIO TOP + SCR BACEN - BASE I E I I I': (
        'relatorio-top-scr', 'Relatório Top + SCR — Base I e III', True),
    'BANCREDI MAXI TOP PF / PJ + SCORE (FILTROS EXTRAS) BASE I E II': (
        'maxi-top', 'Maxi Top PF/PJ + score e filtros — Base I e II', False),
    'RELATORIO PRIME BASICA + CARTORIOS E CCF BACEN BASE I, II E III': (
        'prime-basica', 'Relatório Prime Básica + cartórios e CCF Bacen — Base I, II e III', False),
    'RELATORIO PRIME COMPLETA + (FILTROS EXTRAS) - BASE I, II E III': (
        'prime-completa', 'Relatório Prime Completa + filtros — Base I, II e III', False),
    'RELATORIO PRIME COMPLETA + SCR BACEN BASE I, II E III': (
        'prime-completa-scr', 'Relatório Prime Completa + SCR — Base I, II e III', True),
    'BANCREDI SCR BACEN + SCORE PF / PJ': (
        'scr-score', 'SCR + score PF/PJ', True),
    'CADASTRO ESPECIAL PF - MOSTRA END, TEL, EMAIL - FONTE I': (
        'cadastro-especial-pf', 'Cadastro especial PF — endereço, telefone, e-mail, trabalho, renda', False),
    'CADASTRO ESPECIAL PJ - MOSTRA DADOS DA EMPRESA, SOCIOS,': (
        'cadastro-especial-pj', 'Cadastro especial PJ — dados da empresa, sócios, regime fiscal, faturamento', False),
    'ENCONTRA TELEFONES ATRAVES DO CPF OU CNPJ - FONTE I': (
        'telefones-por-documento', 'Telefones por CPF/CNPJ', False),
    'ENCONTRA ENDERECOS ATRAVES DO CPF OU CNPJ - FONTE I': (
        'enderecos-por-documento', 'Endereços por CPF/CNPJ', False),
    'INFOBUSCA POR CPF / CNPJ - FONTE I I': (
        'infobusca-por-documento', 'InfoBusca por CPF/CNPJ — telefone, endereço e e-mails', False),
    'INFOBUSCA POR NOME (MOSTRA CPF) - FONTE I I': (
        'infobusca-por-nome', 'InfoBusca por nome (mostra CPF)', False),
    'LOCALIZADOR POR TELEFONE (MOSTRA NOME E CPF / CNPJ)': (
        'localizador-por-telefone', 'Localizador por telefone (mostra nome e CPF/CNPJ)', False),
    'LOCALIZADOR POR CEP (MOSTRA NOMES, CPF / CNPJ) FONTE I I': (
        'localizador-por-cep', 'Localizador por CEP (mostra nomes e CPF/CNPJ)', False),
    'NEGATIVACAO': (
        'negativacao', 'Negativação', False),
    'LOCALIZA VEICULOS POR CPF / CNPJ': (
        'localiza-veiculos', 'Localiza veículos por CPF/CNPJ', False),
    'HISTORICO PROPRIETARIO SO DE SAO PAULO': (
        'historico-proprietario-sp', 'Histórico de proprietário (somente São Paulo)', False),
    'PROPRIETARIO ATUAL': (
        'proprietario-atual', 'Proprietário atual', False),
    'AGREGADOS': (
        'agregados', 'Agregados', False),
    'RENAJUD': (
        'renajud', 'RenaJud', False),
    'RENAINF - INFRACOES COMPLETA': (
        'renainf', 'RenaInf — infrações completa', False),
    'CRLV - DOCUMENTO DE LICENCIAMENTO DO VEICULO': (
        'crlv', 'CRLV — documento de licenciamento', False),
    'BIN OU BASE ESTADUAL E NACIONAL': (
        'bin', 'BIN — base estadual e nacional', False),
    'LEILAO - BASE I': (
        'leilao', 'Leilão — Base I', False),
    'LEILAO CONJUGADO COMPLETO + SCORE DO VEICULO - BASE II': (
        'leilao-conjugado', 'Leilão conjugado completo + score do veículo — Base II', False),
    'CSV - CERTIFICADO DE SEGURANCA DO VEICULAR': (
        'csv-veicular', 'CSV — certificado de segurança veicular', False),
    'HISTORICO DE ROUBO E FURTO': (
        'roubo-e-furto', 'Histórico de roubo e furto', False),
    'GRAVAME INDICATIVO': (
        'gravame', 'Gravame indicativo', False),
    'GRAVAME INDICATIVO + AGREGADOS': (
        'gravame-agregados', 'Gravame indicativo + agregados', False),
    'CONFERE RG/CNH': (
        'confere-rg-cnh', 'Confere RG/CNH', False),
    'PRECIFICADOR / DECODIFICADOR': (
        'precificador', 'Precificador / decodificador', False),
    'VIP CAR - INFORMACAO COMPLETA DO VEICULO': (
        'vip-car', 'VIP Car — informação completa do veículo', False),
}


def normaliza(nome: str) -> str:
    return re.sub(r'\s+', ' ', nome.translate(ACENTOS)).strip()


def centavos(valor: str) -> int:
    return int(valor.replace('.', '').replace(',', ''))


def le(pdf: Path, categoria: str) -> list[dict]:
    servicos = []
    reader = PdfReader(pdf)

    for page in reader.pages:
        for linha in page.extract_text(extraction_mode='layout').splitlines():
            precos = VALOR.findall(linha)
            if len(precos) != len(FAIXAS):
                continue

            bruto = normaliza(VALOR.split(linha)[0])
            if bruto not in SERVICOS:
                raise SystemExit(f'Servico sem mapeamento em {pdf.name}: {bruto!r}')

            codigo, nome, liberacao = SERVICOS[bruto]
            servicos.append({
                'codigo': codigo,
                'nome': nome,
                'categoria': categoria,
                'exige_liberacao': liberacao,
                'precos': [centavos(preco) for preco in precos],
            })

    if not servicos:
        raise SystemExit(f'Nenhuma linha de preco reconhecida em {pdf.name}.')

    return servicos


def php(servicos: list[dict]) -> str:
    linhas = [
        '<?php',
        '',
        '/*',
        ' * Tabela de referencia Bancredi 04/2026, transcrita dos PDFs de temp/.',
        ' *',
        ' * ARQUIVO GERADO por tools/gera_precos_catalogo.py. Nao edite a mao: rode o',
        ' * script de novo quando o fornecedor publicar tabela nova.',
        ' *',
        ' * Precos em centavos, na ordem das faixas. Sao preco de VENDA ao cliente da',
        ' * Avalia; o custo do fornecedor e cadastrado separadamente e nao vem daqui.',
        ' */',
        '',
        'return [',
        '    // Consumo minimo de cada faixa, em centavos. A primeira e "sem minimo".',
        '    \'faixas\' => [' + ', '.join(f'{f:_}' for f in FAIXAS) + '],',
        '',
        '    \'servicos\' => [',
    ]

    for servico in servicos:
        precos = ', '.join(f'{preco:_}' for preco in servico['precos'])
        linhas += [
            '        [',
            f"            'codigo' => '{servico['codigo']}',",
            f"            'nome' => '{servico['nome']}',",
            f"            'categoria' => '{servico['categoria']}',",
            f"            'exige_liberacao' => {'true' if servico['exige_liberacao'] else 'false'},",
            f"            'precos' => [{precos}],",
            '        ],',
        ]

    linhas += ['    ],', '];', '']

    return '\n'.join(linhas)


def main():
    servicos = []

    for pdf in sorted((ROOT / 'temp').glob('*.pdf')):
        if 'CREDITO' in pdf.name:
            servicos += le(pdf, 'credito')
        elif 'VEICULAR' in pdf.name:
            servicos += le(pdf, 'veicular')

    codigos = [servico['codigo'] for servico in servicos]
    if len(codigos) != len(set(codigos)):
        raise SystemExit('Codigo de servico repetido; ajuste o mapa SERVICOS.')

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    # newline='\n': o Pint exige LF, e o padrao do Windows gravaria CRLF.
    OUTPUT.write_text(php(servicos), encoding='utf-8', newline='\n')

    print(f'{len(servicos)} servicos gravados em {OUTPUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()
