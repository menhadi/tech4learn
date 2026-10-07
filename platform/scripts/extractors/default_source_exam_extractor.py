#!/usr/bin/env python3
import argparse, contextlib, io, json, re, shutil, sys, zipfile
from collections import Counter
from pathlib import Path
from xml.etree import ElementTree as ET

# Shared deterministic PDF parsing lives one directory above all selectable
# extractors. Keep this explicit so the script runs identically from CLI/PHP.
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import source_pdf_extractor_core as pdf_core

Q_RE = re.compile(r'^\s*(?:Q(?:uestion)?\s*)?\.?\s*(\d+)\s*[.)-]?\s*(.*)$', re.I)
OPT_RE = re.compile(r'^\s*\(?([A-F])\)?\s*[.)-]\s*(.*)$', re.I)
ANSWER_RE = re.compile(r'(?:Q(?:uestion)?\s*)?(\d+)\s*[.)=:>-]+\s*(?:option\s*)?([A-F]|true|false|[-+]?\d+(?:\.\d+)?)\b', re.I)
IMAGE_MARKER_RE = re.compile(r'\[\s*image\s*:\s*[^\]]+\]', re.I)
PAGE_COUNTER_RE = re.compile(r'^\s*(?:page\s*)?\d+\s*(?:of|/)\s*\d+\s*$', re.I)

def docx_text_and_images(path, output):
    ns = {'w':'http://schemas.openxmlformats.org/wordprocessingml/2006/main','a':'http://schemas.openxmlformats.org/drawingml/2006/main','r':'http://schemas.openxmlformats.org/officeDocument/2006/relationships'}
    rel_ns = {'pr':'http://schemas.openxmlformats.org/package/2006/relationships'}
    lines, images = [], []
    with zipfile.ZipFile(path) as archive:
        rels = {}
        if 'word/_rels/document.xml.rels' in archive.namelist():
            root = ET.fromstring(archive.read('word/_rels/document.xml.rels'))
            rels = {node.attrib.get('Id'): node.attrib.get('Target') for node in root.findall('pr:Relationship', rel_ns)}
        root = ET.fromstring(archive.read('word/document.xml'))
        for paragraph in root.findall('.//w:body/w:p', ns):
            text = ''.join(node.text or '' for node in paragraph.findall('.//w:t', ns)).strip()
            refs = [node.attrib.get('{%s}embed' % ns['r']) for node in paragraph.findall('.//a:blip', ns)]
            attached = []
            for ref in refs:
                target = rels.get(ref, '')
                member = 'word/' + target.lstrip('/')
                if member in archive.namelist():
                    suffix = Path(target).suffix.lower() or '.png'
                    name = f'docx_image_{len(images)+1}{suffix}'
                    dest = output / name
                    dest.write_bytes(archive.read(member)); images.append(name); attached.append(name)
            if text or attached: lines.append({'text': text, 'images': attached})
    return lines, images

def parse_docx(path, output):
    lines, _ = docx_text_and_images(path, output)
    questions, current, option = [], None, None
    for row in lines:
        text = row['text']; qm = Q_RE.match(text) if text else None; om = OPT_RE.match(text) if text else None
        if qm and (text.lower().startswith(('q','question')) or current is None):
            if current: questions.append(current)
            current = {'question_no': qm.group(1), 'question_text': qm.group(2), 'options': {}, 'question_images': row['images'], 'source_pages': []}; option = None
        elif current and om:
            option = om.group(1).upper(); current['options'][option] = {'text': om.group(2), 'images': row['images']}
        elif current:
            target = current['options'].get(option) if option else current
            key = 'text' if option else 'question_text'
            target[key] = (target.get(key,'') + '\n' + text).strip()
            (target['images'] if option else current['question_images']).extend(row['images'])
    if current: questions.append(current)
    return questions

def parse_pdf(path, output, code, profile):
    # The shared audit extractor writes human-readable progress to stdout.
    # Keep stdout reserved for this command's single JSON response.
    diagnostics = io.StringIO()
    with contextlib.redirect_stdout(diagnostics):
        json_path, _, image_dir, _ = pdf_core.extract_pdf(path, output / 'pdf', code, 35, 810, 0.70, True,
            line_mode=profile.get('reading_order','auto') != 'rows', renumber_resets=profile.get('question_numbering','auto') != 'continuous')
    if diagnostics.getvalue().strip():
        print(diagnostics.getvalue().strip(), file=sys.stderr)
    payload = json.loads(Path(json_path).read_text(encoding='utf-8'))
    for source in image_dir.glob('*'):
        if source.is_file(): shutil.copy2(source, output / source.name)
    return payload.get('questions', [])

def normalized_line(value):
    return re.sub(r'\s+', ' ', value or '').strip().casefold()
def canonical_header(value):
    """Normalize punctuation-heavy table headings such as Q. No. and Q. Type."""
    return re.sub(r'[^a-z0-9]+', ' ', normalized_line(value)).strip()


def pdf_margin_noise(path):
    """Find repeated page-margin text without assuming a particular PDF template."""
    if not path or Path(path).suffix.lower() != '.pdf':
        return set()
    import fitz
    doc = fitz.open(path)
    occurrences = Counter()
    for page in doc:
        height = max(float(page.rect.height), 1.0)
        seen = set()
        for block in page.get_text('blocks'):
            y0, y1, value = float(block[1]), float(block[3]), str(block[4] or '')
            if y1 > height * .14 and y0 < height * .86:
                continue
            for line in value.splitlines():
                key = normalized_line(line)
                if key and len(key) <= 180:
                    seen.add(key)
        occurrences.update(seen)
    threshold = max(2, int((doc.page_count * .20) + .999))
    return {line for line, count in occurrences.items() if count >= threshold}

def clean_text(value, margin_noise=None):
    value = IMAGE_MARKER_RE.sub('', value or '')
    kept = []
    for line in value.splitlines():
        stripped = line.strip()
        if not stripped or PAGE_COUNTER_RE.match(stripped):
            continue
        if margin_noise and normalized_line(stripped) in margin_noise:
            continue
        kept.append(stripped)
    return '\n'.join(kept).strip()

def plain_source(path):
    if not path: return ''
    path = Path(path)
    if path.suffix.lower() == '.docx':
        temporary = path.parent / ('tmp_' + path.stem)
        temporary.mkdir(parents=True, exist_ok=True)
        return '\n'.join(row['text'] for row in docx_text_and_images(path, temporary)[0])
    if path.suffix.lower() == '.pdf':
        import fitz
        doc = fitz.open(path); return '\n'.join(page.get_text('text') for page in doc)
    return path.read_text(encoding='utf-8', errors='ignore')

def source_question_type(value):
    normalized = normalized_line(value or '')
    if any(token in normalized for token in ('nat', 'numerical answer', 'numerical')):
        return 'NAT'
    if any(token in normalized for token in ('fill in', 'fill blank')):
        return 'B'
    if any(token in normalized for token in ('true false', 'true/false')):
        return 'T'
    if any(token in normalized for token in ('subjective', 'descriptive')):
        return 'S'
    if any(token in normalized for token in ('mcq', 'msq', 'multiple choice', 'multiple select')):
        return 'M'
    return None

def nat_config_from(value):
    raw = re.sub(r'\s+', ' ', str(value or '')).strip()
    number = r'[-+]?\d+(?:\.\d+)?'
    range_match = re.fullmatch(rf'\s*[\[(]?\s*({number})\s*(?:to|[-–—,])\s*({number})\s*[\])]?\s*', raw, re.IGNORECASE)
    if range_match:
        lower, upper = float(range_match.group(1)), float(range_match.group(2))
        return {'version': 1, 'mode': 'range', 'min': min(lower, upper), 'max': max(lower, upper)}
    if re.fullmatch(number, raw):
        return {'version': 1, 'mode': 'exact', 'value': float(raw)}
    return None

def pdf_table_answer_records(path):
    results = {}
    if not path or Path(path).suffix.lower() != '.pdf':
        return results
    import fitz
    doc = fitz.open(path)
    for page in doc:
        try:
            tables = page.find_tables().tables
        except Exception:
            tables = []
        for table in tables:
            rows = table.extract() or []
            if not rows:
                continue
            headers = [canonical_header(cell or '') for cell in rows[0]]
            question_index = next((i for i, value in enumerate(headers) if any(key in value for key in ('question no', 'question number', 'q no', 'ques no'))), None)
            answer_index = next((i for i, value in enumerate(headers) if any(key in value for key in ('key range', 'correct option', 'correct answer', 'answer key', 'answer', 'key'))), None)
            type_index = next((i for i, value in enumerate(headers) if any(key in value for key in ('q type', 'question type', 'type'))), None)
            marks_index = next((i for i, value in enumerate(headers) if 'mark' in value), None)
            if question_index is None or answer_index is None:
                continue
            for row in rows[1:]:
                cells = [re.sub(r'\s+', ' ', str(cell or '')).strip() for cell in row]
                if max(question_index, answer_index) >= len(cells):
                    continue
                no_match = re.search(r'\d+', cells[question_index])
                if not no_match or not cells[answer_index]:
                    continue
                record = {'answer': cells[answer_index].strip()}
                if type_index is not None and type_index < len(cells):
                    record['question_type'] = source_question_type(cells[type_index])
                if marks_index is not None and marks_index < len(cells):
                    marks_match = re.search(r'[-+]?\d+(?:\.\d+)?', cells[marks_index])
                    if marks_match:
                        record['marks'] = float(marks_match.group())
                if record.get('question_type') == 'NAT':
                    record['nat_config'] = nat_config_from(record['answer'])
                results[int(no_match.group())] = record
    return results

def pdf_positioned_answer_records(path):
    """Recover typed answer-key rows when PDF table borders are not detectable."""
    results = {}
    if not path or Path(path).suffix.lower() != '.pdf':
        return results
    import fitz
    type_pattern = r'(MCQ|MSQ|NAT|NUMERICAL(?:\s+ANSWER(?:\s+TYPE)?)?|FILL(?:\s+IN\s+THE\s+BLANKS?)?|TRUE\s*[/|-]?\s*FALSE|SUBJECTIVE|DESCRIPTIVE)'
    patterns = [
        re.compile(rf'^\s*(\d+)\s+\S+\s+{type_pattern}\s+\S+\s+(.+?)\s+([-+]?\d+(?:\.\d+)?)\s*$', re.I),
        re.compile(rf'^\s*(\d+)\s+{type_pattern}\s+\S+\s+(.+?)\s+([-+]?\d+(?:\.\d+)?)\s*$', re.I),
        re.compile(rf'^\s*(\d+)\s+{type_pattern}\s+(.+?)\s+([-+]?\d+(?:\.\d+)?)\s*$', re.I),
    ]
    doc = fitz.open(path)
    for page in doc:
        words = sorted(page.get_text('words'), key=lambda word: (round(float(word[1]) / 3), float(word[0])))
        lines = []
        for word in words:
            center = (float(word[1]) + float(word[3])) / 2
            line = next((entry for entry in reversed(lines[-4:]) if abs(entry['center'] - center) <= 3.5), None)
            if line is None:
                line = {'center': center, 'words': []}
                lines.append(line)
            line['words'].append((float(word[0]), str(word[4] or '')))
        for line in lines:
            value = ' '.join(text for _, text in sorted(line['words'])).strip()
            if not re.search(type_pattern, value, re.I):
                continue
            for pattern in patterns:
                match = pattern.match(value)
                if not match:
                    continue
                question_number, raw_type, answer, marks = match.groups()
                record = {'answer': answer.strip(), 'question_type': source_question_type(raw_type), 'marks': float(marks)}
                if record['question_type'] == 'NAT':
                    record['nat_config'] = nat_config_from(record['answer'])
                results[int(question_number)] = record
                break
    return results
def answer_records_from(path):
    text = plain_source(path)
    records = pdf_positioned_answer_records(path)
    records.update(pdf_table_answer_records(path))
    def set_fallback_answer(question_number, value):
        """Structured answer-key table values always outrank loose PDF text."""
        record = records.setdefault(int(question_number), {})
        if not str(record.get('answer') or '').strip():
            record['answer'] = value.upper()


    for no, value in ANSWER_RE.findall(text):
        set_fallback_answer(no, value)
    lines = [re.sub(r'\s+', ' ', line).strip() for line in text.splitlines() if line.strip()]
    pair_re = re.compile(r'(?i)(?:Q(?:uestion)?\s*)?(\d+)\s*[.)\]:=-]*\s*(?:ans(?:wer)?\s*[:=-]?\s*)?(?:option\s*)?([A-F]|true|false)\b')
    for line in lines:
        for no, value in pair_re.findall(line):
            set_fallback_answer(no, value)
        tokens = re.findall(r'(?i)\b(?:[1-9]\d*|[A-F]|true|false)\b', line)
        if len(tokens) >= 4 and len(tokens) % 2 == 0:
            for index in range(0, len(tokens), 2):
                if tokens[index].isdigit() and re.fullmatch(r'(?i)[A-F]|true|false', tokens[index + 1]):
                    set_fallback_answer(tokens[index], tokens[index + 1])
    for index in range(len(lines) - 1):
        numbers = re.findall(r'\b\d+\b', lines[index])
        values = re.findall(r'(?i)\b(?:[A-F]|true|false)\b', lines[index + 1])
        if len(numbers) >= 2 and len(numbers) == len(values):
            for no, value in zip(numbers, values):
                set_fallback_answer(no, value)
    return records
def numbered_sections(path):
    text = plain_source(path)
    matches = list(re.finditer(r'(?im)^\s*(?:Q(?:uestion)?\s*)?\.?\s*(\d+)\s*[.)-]\s*', text))
    sections = {}
    for index, match in enumerate(matches):
        end = matches[index + 1].start() if index + 1 < len(matches) else len(text)
        value = text[match.end():end].strip()
        if value: sections[int(match.group(1))] = '<p>' + value.replace('\n', '<br>') + '</p>'
    return sections

def normalize_image_files(questions, output):
    """Use the same deterministic image names as the audit/repair workflow."""
    for ordinal, question in enumerate(questions, 1):
        paper_no = re.sub(r'[^0-9A-Za-z_-]+', '-', str(question.get('question_no') or ordinal)).strip('-') or str(ordinal)
        targets = [(question, 'question_images', f'q_{paper_no}_img')]
        for option_index, label in enumerate('ABCDEF', 1):
            option = question.get('options', {}).get(label)
            if option:
                targets.append((option, 'images', f'q_{paper_no}_option{option_index}_image'))
        for owner, key, stem in targets:
            renamed = []
            for image_index, image in enumerate(owner.get(key, []) or [], 1):
                source = output / Path(image).name
                if not source.exists():
                    continue
                destination = output / f'{stem}{image_index}.png'
                if source.resolve() != destination.resolve():
                    try:
                        from PIL import Image
                        with Image.open(source) as opened:
                            opened.convert('RGB').save(destination, 'PNG')
                    except Exception:
                        if source.suffix.lower() == '.png':
                            shutil.copy2(source, destination)
                        else:
                            destination = output / f'{stem}{image_index}{source.suffix.lower()}'
                            shutil.copy2(source, destination)
                renamed.append(destination.name)
            owner[key] = renamed

def normalize_mcq_answer(answer, options):
    if answer is None:
        return None
    value = str(answer).strip()
    populated = {label: clean_text((options.get(label) or {}).get('text', '')) for label in 'ABCDEF'}
    if re.fullmatch(r'[1-6]', value) and populated.get(chr(64 + int(value))):
        return chr(64 + int(value))
    if re.fullmatch(r'(?i)[A-F]', value):
        return value.upper()
    needle = normalized_line(re.sub(r'<[^>]+>', ' ', value))
    for label, option_text in populated.items():
        if needle and needle == normalized_line(option_text):
            return label
    return value.upper()

SUPERSCRIPT_TO_TEX = str.maketrans({'⁰':'0','¹':'1','²':'2','³':'3','⁴':'4','⁵':'5','⁶':'6','⁷':'7','⁸':'8','⁹':'9','⁺':'+','⁻':'-','⁼':'=','⁽':'(','⁾':')'})
SUBSCRIPT_TO_TEX = str.maketrans({'₀':'0','₁':'1','₂':'2','₃':'3','₄':'4','₅':'5','₆':'6','₇':'7','₈':'8','₉':'9','₊':'+','₋':'-','₌':'=','₍':'(','₎':')'})

def stacked_partial_derivative_mathjax(value):
    """Rebuild PDE fractions when PDF extraction lists numerators before denominators."""
    lines = [re.sub(r'\s+', ' ', line).strip() for line in (value or '').splitlines() if line.strip()]
    numerator_re = re.compile(r'^\s*\u2202\s*(?:\u00b2|\^?2)?\s*([A-Za-z])\s*$', re.I)
    numerators = [match.group(1) for line in lines if (match := numerator_re.match(line))]
    if len(numerators) < 2:
        return None

    denominator_re = re.compile(r'^\s*\u2202\s*([A-Za-z])\s*(?:\u00b2|\u2082|\^?2)?\s*(.*)$', re.I)
    mixed_re = re.compile(r'^\s*\u2202\s*([A-Za-z])\s*\u2202\s*([A-Za-z])\s*(.*)$', re.I)
    denominators = []
    consumed = set()
    for index, line in enumerate(lines):
        if numerator_re.match(line):
            consumed.add(index)
            continue
        mixed = mixed_re.match(line)
        if mixed:
            variables = mixed.group(1).lower() + mixed.group(2).lower()
            denominators.append((1, rf'\partial {mixed.group(1)}\partial {mixed.group(2)}', mixed.group(3).strip()))
            consumed.add(index)
            continue
        match = denominator_re.match(line)
        if match:
            variable = match.group(1)
            rank = 0 if variable.lower() == 'x' else 2 if variable.lower() == 'y' else 3
            denominators.append((rank, rf'\partial {variable}^{{2}}', match.group(2).strip()))
            consumed.add(index)
    if len(denominators) != len(numerators) or len(denominators) < 2:
        return None

    denominators.sort(key=lambda item: item[0])
    terms = []
    next_coefficient = ''
    final_relation = ''
    final_suffix = ''
    for position, (_, denominator, remainder) in enumerate(denominators):
        prefix = '' if position == 0 else (' + ' + (next_coefficient + r'\,' if next_coefficient else ''))
        terms.append(prefix + rf'\frac{{\partial^{{2}} {numerators[min(position, len(numerators)-1)]}}}{{{denominator}}}')
        coefficient = re.search(r'^\s*\+\s*([-+]?\d+(?:\.\d+)?)\s*$', remainder)
        next_coefficient = coefficient.group(1) if coefficient else ''
        relation = re.search(r'(=|<=|>=|<|>)\s*([-+]?\d+(?:\.\d+)?)\s*(.*)$', remainder)
        if relation:
            final_relation = ' ' + relation.group(1) + ' ' + relation.group(2).strip()
            final_suffix = relation.group(3).strip()
    prose = ' '.join(line for index, line in enumerate(lines) if index not in consumed)
    equation = r'\[\displaystyle ' + ''.join(terms) + final_relation + r'\]'
    return ((prose + ' ') if prose else '') + equation + ((' ' + final_suffix) if final_suffix else '')
def mathjax_text(value):
    text = value or ''
    stacked = stacked_partial_derivative_mathjax(text)
    if stacked:
        return stacked
    # Rebuild the common vertically typeset limit/fraction pattern emitted by PDF text extraction.
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    if any(line.casefold() == 'lim' for line in lines):
        approach = next((line for line in lines if re.search(r'[A-Za-z]\s*(?:→|->|\\to)\s*[-+]?\d+', line)), None)
        denominator = next((line for line in lines if re.fullmatch(r'[A-Za-z][⁰¹²³⁴⁵⁶⁷⁸⁹]+', line)), None)
        numerator = next((line for line in lines if line.casefold() != 'lim' and line != approach and line != denominator and line not in ('=', '____', '___')), None)
        if approach and denominator and numerator:
            variable, target = re.split(r'\s*(?:→|->|\\to)\s*', approach, maxsplit=1)
            denominator_tex = re.sub(r'([A-Za-z])([⁰¹²³⁴⁵⁶⁷⁸⁹]+)', lambda m: m.group(1)+'^{'+m.group(2).translate(SUPERSCRIPT_TO_TEX)+'}', denominator)
            remainder = [line for line in lines if line not in (approach, denominator, numerator, 'lim')]
            numerator = re.sub(r'(?<!\\)\b(cos|sin|tan|log|ln|exp)\b', r'\\\1', numerator)
            expression = r'\(\displaystyle \lim_{'+variable+r'\to '+target+r'} \frac{'+numerator+r'}{'+denominator_tex+r'}\)'
            return expression + (' ' + ' '.join(remainder) if remainder else '')

    protected = []
    def protect(match):
        protected.append(match.group(0))
        return f'@@MATHJAX_{len(protected)-1}@@'
    text = re.sub(r'\\\([\s\S]*?\\\)|\\\[[\s\S]*?\\\]|\$\$[\s\S]*?\$\$', protect, text)
    text = re.sub(r'([A-Za-z0-9)]+)([⁰¹²³⁴⁵⁶⁷⁸⁹⁺⁻⁼⁽⁾]+)', lambda m: r'\('+m.group(1)+'^{'+m.group(2).translate(SUPERSCRIPT_TO_TEX)+r'}\)', text)
    text = re.sub(r'([A-Za-z0-9)]+)([₀₁₂₃₄₅₆₇₈₉₊₋₌₍₎]+)', lambda m: r'\('+m.group(1)+'_{'+m.group(2).translate(SUBSCRIPT_TO_TEX)+r'}\)', text)
    for line in text.splitlines():
        if ('\\frac' in line or '\\lim' in line) and not re.search(r'\\\(|\\\[|\$\$', line):
            text = text.replace(line, r'\('+line+r'\)', 1)
    for index, math in enumerate(protected):
        text = text.replace(f'@@MATHJAX_{index}@@', math)
    return text

def html_value(text, images, public_prefix, margin_noise=None):
    chunks = []
    text = mathjax_text(clean_text(text, margin_noise))
    if text: chunks.append('<p>' + text.replace('\n','<br>') + '</p>')
    chunks.extend(f'<p><img src="{public_prefix}/{Path(image).name}" alt="Question source image"></p>' for image in images)
    return ''.join(chunks)

def main():
    parser=argparse.ArgumentParser(); parser.add_argument('--questions',required=True); parser.add_argument('--answers'); parser.add_argument('--solutions'); parser.add_argument('--out',required=True); parser.add_argument('--public-prefix',required=True); parser.add_argument('--paper-code',required=True); parser.add_argument('--profile',default='{}'); parser.add_argument('--result-file')
    args=parser.parse_args(); output=Path(args.out); output.mkdir(parents=True,exist_ok=True); profile=json.loads(args.profile)
    source=Path(args.questions)
    questions = parse_docx(source, output) if source.suffix.lower()=='.docx' else parse_pdf(source, output, args.paper_code, profile)
    normalize_image_files(questions, output)
    margin_noise = pdf_margin_noise(source)
    answer_records=answer_records_from(args.answers); solutions=numbered_sections(args.solutions) if args.solutions else {}
    result=[]
    for ordinal, q in enumerate(questions,1):
        printed=str(q.get('question_no') or ordinal); options=q.get('options',{})
        answer_record=answer_records.get(int(printed) if printed.isdigit() else ordinal, {})
        answer=answer_record.get('answer')
        option_count = sum(bool(clean_text((options.get(label) or {}).get('text', '')) or (options.get(label) or {}).get('images')) for label in 'ABCDEF')
        answer = normalize_mcq_answer(answer, options) if answer_record.get('question_type') == 'M' or (not answer_record.get('question_type') and option_count >= 2) else answer
        result.append({'paper_question_number':ordinal,'printed_question_number':printed,
          'question':html_value(q.get('question_text',''),q.get('question_images',[]),args.public_prefix,margin_noise),
          'options':[html_value(options.get(label,{}).get('text',''),options.get(label,{}).get('images',[]),args.public_prefix,margin_noise) for label in 'ABCDEF'],
          'correct_answer':answer,'question_type':answer_record.get('question_type'),'nat_config':answer_record.get('nat_config'),'marks':answer_record.get('marks'),'explanation':solutions.get(int(printed) if printed.isdigit() else ordinal),'source_pages':q.get('source_pages',[]),
          'needs_ai':not bool(answer),'solution_source_available':bool(solutions)})
    response = json.dumps({'ok':True,'questions':result}, ensure_ascii=False)
    if args.result_file:
        result_path = Path(args.result_file)
        result_path.parent.mkdir(parents=True, exist_ok=True)
        temporary = result_path.with_suffix(result_path.suffix + '.tmp')
        temporary.write_text(response, encoding='utf-8')
        temporary.replace(result_path)
    else:
        print(response)

if __name__=='__main__':
    try: main()
    except Exception as error:
        print(json.dumps({'ok':False,'error':str(error)})); sys.exit(2)
