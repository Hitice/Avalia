"""Despeja o texto dos PDFs de temp/ para leitura humana.

    python tools/extract_pdf.py

Usa extraction_mode='layout' de proposito. No modo padrao o pypdf devolve as
celulas na ordem interna do PDF, sem preservar colunas: as tabelas de preco
saem embaralhadas, linhas cujo nome ocupa duas linhas somem, e o cabecalho
"SEM CONSUMO MINIMO" se perde — foi assim que a primeira transcricao jogou
todo preco uma faixa para o lado.

Para gerar dado de catalogo use tools/gera_precos_catalogo.py; este script e
so para conferir o PDF com o olho.
"""

from pathlib import Path

from pypdf import PdfReader

for file in sorted(Path('temp').glob('*.pdf')):
    print('---', file.name)
    reader = PdfReader(file)

    for index, page in enumerate(reader.pages, start=1):
        print(f'PAGE {index}')
        print(page.extract_text(extraction_mode='layout') or '')
        print()
