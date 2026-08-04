"""Exporta PDD.md para um DOCX simples, sem bibliotecas externas."""

from pathlib import Path
from xml.etree import ElementTree as ET
from zipfile import ZIP_DEFLATED, ZipFile


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'PDD.md'
OUTPUT = ROOT / 'docs' / 'PDD-Avalia.docx'

W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
ET.register_namespace('w', W)
ET.register_namespace('r', R)


def tag(name: str) -> str:
    return f'{{{W}}}{name}'


def text(value: str) -> str:
    return value.replace('**', '').replace('`', '').replace('[', '').replace(']', '')


def paragraph(parent, value: str = '', style: str | None = None):
    node = ET.SubElement(parent, tag('p'))
    if style:
        properties = ET.SubElement(node, tag('pPr'))
        ET.SubElement(properties, tag('pStyle'), {tag('val'): style})
    run = ET.SubElement(node, tag('r'))
    ET.SubElement(run, tag('t')).text = text(value)
    return node


def add_table(parent, rows: list[list[str]]):
    table = ET.SubElement(parent, tag('tbl'))
    properties = ET.SubElement(table, tag('tblPr'))
    ET.SubElement(properties, tag('tblStyle'), {tag('val'): 'TableGrid'})

    for row_index, row in enumerate(rows):
        row_node = ET.SubElement(table, tag('tr'))
        for cell in row:
            cell_node = ET.SubElement(row_node, tag('tc'))
            cell_props = ET.SubElement(cell_node, tag('tcPr'))
            if row_index == 0:
                shading = ET.SubElement(cell_props, tag('shd'))
                shading.set(tag('fill'), 'D9EAF7')
            paragraph(cell_node, cell.strip())


def parse_document(body, lines: list[str]):
    index = 0
    in_code = False

    while index < len(lines):
        line = lines[index].rstrip()

        if line.startswith('```'):
            in_code = not in_code
            index += 1
            continue

        if in_code or line.startswith('    '):
            paragraph(body, line.strip(), 'NoSpacing')
            index += 1
            continue

        if line.startswith('|'):
            rows = []
            while index < len(lines) and lines[index].startswith('|'):
                cells = [cell.strip() for cell in lines[index].strip().strip('|').split('|')]
                if not all(set(cell) <= {'-', ':', ' '} for cell in cells):
                    rows.append(cells)
                index += 1
            if rows:
                add_table(body, rows)
            continue

        if line.startswith('#'):
            level = len(line) - len(line.lstrip('#'))
            paragraph(body, line[level:].strip(), f'Heading{min(level, 3)}')
        elif line.startswith('- '):
            paragraph(body, line[2:], 'ListBullet')
        elif len(line) > 3 and line[0].isdigit() and '. ' in line[:4]:
            paragraph(body, line.split('. ', 1)[1], 'ListNumber')
        elif line.startswith('> '):
            paragraph(body, line[2:], 'Quote')
        elif line:
            paragraph(body, line)

        index += 1


def document_xml(markdown: str) -> bytes:
    document = ET.Element(tag('document'))
    body = ET.SubElement(document, tag('body'))
    parse_document(body, markdown.splitlines())
    section = ET.SubElement(body, tag('sectPr'))
    ET.SubElement(section, tag('pgSz'), {tag('w'): '11906', tag('h'): '16838'})
    ET.SubElement(section, tag('pgMar'), {
        tag('top'): '1134', tag('right'): '1134', tag('bottom'): '1134', tag('left'): '1134',
    })
    return ET.tostring(document, encoding='utf-8', xml_declaration=True)


def main():
    OUTPUT.parent.mkdir(exist_ok=True)
    markdown = SOURCE.read_text(encoding='utf-8')

    with ZipFile(OUTPUT, 'w', ZIP_DEFLATED) as archive:
        archive.writestr('[Content_Types].xml', '''<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>''')
        archive.writestr('_rels/.rels', '''<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>''')
        archive.writestr('word/_rels/document.xml.rels', '''<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>''')
        archive.writestr('word/document.xml', document_xml(markdown))
        archive.writestr('word/styles.xml', '''<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/></w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/></w:style>
  <w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/></w:style>
  <w:style w:type="paragraph" w:styleId="ListBullet"><w:name w:val="List Bullet"/></w:style>
  <w:style w:type="paragraph" w:styleId="ListNumber"><w:name w:val="List Number"/></w:style>
  <w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/></w:style>
  <w:style w:type="paragraph" w:styleId="NoSpacing"><w:name w:val="No Spacing"/></w:style>
  <w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/></w:style>
</w:styles>''')

    print(f'Arquivo criado: {OUTPUT}')


if __name__ == '__main__':
    main()
