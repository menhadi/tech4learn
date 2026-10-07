import base64
import csv
import hashlib
import io
import json
import os
import re
import urllib.error
import urllib.request
from html.parser import HTMLParser
from pathlib import Path

import fitz
from PIL import Image


BROKEN_GLYPH_RE = re.compile(r"[\ue000-\uf8ff]")
COMPLEX_OPERATOR_RE = re.compile(r"[\u222b\u2211\u220f]")
MATH_STYLED_RUN_RE = re.compile(r"[\U0001d400-\U0001d7ff]{3,}")
MATRIX_BRACKET_GLYPH_RE = re.compile(r"[\uf0e9-\uf0eb\uf0f9-\uf0fb]")
STACKED_MATH_RE = re.compile(
    r"(?m)^[\U0001d400-\U0001d7ffA-Za-z0-9()+\-\u2212]{1,4}\s*\n"
    r"[\U0001d400-\U0001d7ffA-Za-z0-9()+\-\u2212]{1,12}\s*(?:=|$)"
)
def has_stacked_math(value):
    for match in STACKED_MATH_RE.finditer(str(value or "")):
        lines = [line.strip() for line in match.group(0).splitlines() if line.strip()]
        if len(lines) < 2:
            continue
        combined = "".join(lines)
        if re.search(r"[\U0001d400-\U0001d7ff]", combined):
            return True
        if all(len(re.sub(r"\s*(?:=|$)", "", line)) <= 2 for line in lines[:2]):
            return True
        math_signal = lambda line: bool(re.search(r"[0-9()+\-\u2212=]", line))
        if math_signal(lines[0]) and math_signal(lines[1]):
            return True
    return False

STRUCTURED_NOTATION_RE = re.compile(
    r"\b(?:dy/dx|d2y|differential|derivative|matrix|determinant|eigen(?:value|vector)?|laplace|fourier|integral|piecewise)\b",
    re.IGNORECASE,
)
PRESERVED_TEX_RE = re.compile(
    r"\\(?:frac|dfrac|sqrt|sum|prod|int|begin\{(?:array|matrix|cases)|times|beta|theta|lambda|mu|sigma|Omega)\b"
)
MATH_HINT = re.compile(
    "|".join((
        BROKEN_GLYPH_RE.pattern,
        COMPLEX_OPERATOR_RE.pattern,
        STRUCTURED_NOTATION_RE.pattern,
    )),
    re.IGNORECASE,
)
FIELDS = ["question", "option1", "option2", "option3", "option4"]
PROMPT = """Return one JSON object with keys question, option1, option2, option3, option4, and visual_regions.
Each value must be a CKEditor 4 HTML fragment. Put normal prose in <p>...</p>. Prefer exact Unicode for simple one-line mathematics, including operators, Greek letters, and available superscript/subscript characters, for example Pe\u02e3 = Qe\u207b\u02e3, H\u2082O, \u03b2, \u00d7, \u2212, and \u2264. Use MathJax only when Unicode cannot faithfully preserve the structure, such as fractions, roots with grouped expressions, matrices, determinants, cases, integrals, summations, differential equations, or multi-level scripts. Put inline MathJax exactly inside <span class="math-tex">\\( ... \\)</span>. Put display MathJax in its own paragraph exactly as <p><span class="math-tex">\\[ ... \\]</span></p>. Never nest <p> tags. Split prose into separate paragraphs before and after a display equation. Every TeX command, including constants such as \\pi, must be inside a math-tex span. Use \\frac{dy}{dx} for derivatives and never invent commands such as \\dx. Never use dollar-sign math delimiters.Preserve every word, value, sign, equation, operator, Greek letter, subscript, superscript, unit, chemical formula, ionic charge, isotope and reaction arrow exactly. Preserve a source table once as HTML <table> markup and do not repeat its cells as plain text. Preserve every [image: ...] marker exactly. Do not include a leading question number because it is stored separately. When an image is supplied, it is authoritative for every coefficient, sign, index, exponent, matrix dimension, case branch and differential term; extracted PDF text may be corrupt. Do not transcribe labels or text that belong inside an extracted figure marker. Do not solve, explain, translate, renumber, or invent content. An empty source option must remain an empty string. Never generate, infer, solve, or suggest answer options that are absent from the source. Use an empty string only when the corresponding option is absent. visual_regions must be an object whose keys are field names and values are arrays of [x0,y0,x1,y1] boxes normalized from 0 to 1000; return empty arrays unless specifically requested below."""
PROMPT += """
Use Unicode only for simple, unambiguous one-line mathematics, including simple ratios such as P/Q and units such as m/s. Use MathJax whenever grouped or two-dimensional structure matters. A slash whose numerator or denominator contains parentheses or addition/subtraction must be encoded with \\frac{numerator}{denominator}; never flatten it as (...)/(...), (...)⁄(...), or a chain of slashes. A mathematical matrix must use a MathJax matrix environment such as bmatrix or pmatrix and must never be represented by an HTML table. HTML tables are only for genuine tabular data.
"""

MATH_SPAN_RE = re.compile(
    r'<span\s+class=["\']math-tex["\']\s*>(.*?)</span>',
    re.IGNORECASE | re.DOTALL,
)


class _FragmentParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.paragraph_depth = 0
        self.invalid_paragraphs = False

    def handle_starttag(self, tag, attrs):
        if tag.lower() == "p":
            self.paragraph_depth += 1
            if self.paragraph_depth > 1:
                self.invalid_paragraphs = True

    def handle_endtag(self, tag):
        if tag.lower() == "p":
            self.paragraph_depth -= 1
            if self.paragraph_depth < 0:
                self.invalid_paragraphs = True


def wrap_bare_mathjax(value):
    protected = []

    def protect_span(match):
        protected.append(match.group(0))
        return f"__MATH_SPAN_{len(protected) - 1}__"

    value = MATH_SPAN_RE.sub(protect_span, value)
    value = re.sub(
        r"\\\[(.+?)\\\]",
        r'<span class="math-tex">\\[\1\\]</span>',
        value,
        flags=re.DOTALL,
    )
    value = re.sub(
        r"\\\((.+?)\\\)",
        r'<span class="math-tex">\\(\1\\)</span>',
        value,
        flags=re.DOTALL,
    )
    for index, span in enumerate(protected):
        value = value.replace(f"__MATH_SPAN_{index}__", span)
    return value


def split_display_math_paragraphs(value):
    def split_paragraph(match):
        body = match.group(1)
        displays = [
            span for span in MATH_SPAN_RE.finditer(body)
            if span.group(1).strip().startswith(r"\[")
        ]
        if not displays:
            return match.group(0)
        pieces = []
        cursor = 0
        for display in displays:
            prefix = body[cursor:display.start()].strip()
            if prefix:
                pieces.append(f"<p>{prefix}</p>")
            pieces.append(f"<p>{display.group(0)}</p>")
            cursor = display.end()
        suffix = body[cursor:].strip()
        if suffix:
            pieces.append(f"<p>{suffix}</p>")
        return "".join(pieces)

    return re.sub(r"<p>(.*?)</p>", split_paragraph, value, flags=re.IGNORECASE | re.DOTALL)

def repair_utf8_mojibake(value):
    value = str(value or "")
    suspicious = re.compile(r"[\u00c2\u00c3\u00ce\u00cf\u00e2]|[\u0080-\u009f]")
    for _ in range(2):
        if not suspicious.search(value):
            break
        raw = bytearray()
        try:
            for character in value:
                codepoint = ord(character)
                if codepoint <= 255:
                    raw.append(codepoint)
                else:
                    raw.extend(character.encode("cp1252"))
            repaired = bytes(raw).decode("utf-8")
        except (UnicodeEncodeError, UnicodeDecodeError):
            break
        if len(suspicious.findall(repaired)) >= len(suspicious.findall(value)):
            break
        value = repaired
    return value
def wrap_bare_tex_scripts(value):
    protected = []
    protected_re = re.compile(
        MATH_SPAN_RE.pattern + r"|\[image:\s*[^\]]+\]|<[^>]+>",
        re.IGNORECASE | re.DOTALL,
    )

    def protect(match):
        protected.append(match.group(0))
        return f"\x00PROTECTED{len(protected) - 1}\x00"

    value = protected_re.sub(protect, value)
    token_re = re.compile(
        r"(?<![A-Za-z0-9_])([A-Za-z](?:"
        r"_(?:\{[^{}\s]+\}|[A-Za-z0-9]+)|"
        r"\^(?:\{[^{}\s]+\}|[-+]?[A-Za-z0-9]+)"
        r")+)"
    )

    def wrap(match):
        token = re.sub(r"_([A-Za-z0-9]+)", r"_{\1}", match.group(1))
        token = re.sub(r"\^([-+]?[A-Za-z0-9]+)", r"^{\1}", token)
        return f'<span class="math-tex">\\({token}\\)</span>'

    value = token_re.sub(wrap, value)
    for index, item in enumerate(protected):
        value = value.replace(f"\x00PROTECTED{index}\x00", item)
    return value

def normalize_ckeditor_html(value, strip_number=False):
    value = repair_utf8_mojibake(value).strip()
    if strip_number:
        value = re.sub(
            r"^(\s*<p>\s*)Q[.\s]*\d+\b\s*",
            r"\1",
            value,
            count=1,
            flags=re.IGNORECASE,
        )
        value = re.sub(r"^\s*Q[.\s]*\d+\b\s*", "", value, count=1, flags=re.IGNORECASE)
    value = wrap_bare_mathjax(value)
    value = wrap_bare_tex_scripts(value)
    value = split_display_math_paragraphs(value)

    def add_missing_mathjax_delimiters(match):
        content = match.group(1).strip()
        delimiter_tokens = (r"\(", r"\)", r"\[", r"\]")
        if content and not any(token in content for token in delimiter_tokens):
            content = r"\(" + content + r"\)"
            return match.group(0).replace(match.group(1), content, 1)
        return match.group(0)

    value = MATH_SPAN_RE.sub(add_missing_mathjax_delimiters, value)
    return re.sub(r"[ \t]+", " ", value).strip()


def normalize_candidate(candidate):
    if not isinstance(candidate, dict):
        return candidate
    return {
        field: normalize_ckeditor_html(candidate.get(field, ""), strip_number=(field == "question"))
        for field in FIELDS if field in candidate
    }



def restore_image_markers(original, candidate, preserve_fields=None):
    if not isinstance(candidate, dict):
        return candidate
    cleaned = dict(candidate)
    preserve_fields = set(FIELDS if preserve_fields is None else preserve_fields)
    for field in FIELDS:
        value = str(cleaned.get(field, ""))
        value = re.sub(r"\s*\[image:\s*[^\]]+\]", "", value)
        markers = re.findall(r"\[image:\s*[^\]]+\]", str(original.get(field, "")))
        if field in preserve_fields and markers:
            marker_text = " ".join(markers)
            if value.rstrip().lower().endswith("</p>"):
                position = value.lower().rfind("</p>")
                value = value[:position].rstrip() + " " + marker_text + value[position:]
            else:
                value = (value + f"<p>{marker_text}</p>").strip()
        cleaned[field] = value
    return cleaned

def unpack_provider_result(result):
    if len(result) == 3:
        return result
    candidate, provider = result
    return candidate, provider, {}

def _env(name, default=None):
    value = os.getenv(name)
    if value:
        return value
    if os.name == "nt":
        try:
            import winreg
            for root, key in ((winreg.HKEY_CURRENT_USER, r"Environment"), (winreg.HKEY_LOCAL_MACHINE, r"SYSTEM\CurrentControlSet\Control\Session Manager\Environment")):
                try:
                    with winreg.OpenKey(root, key) as handle:
                        value, _ = winreg.QueryValueEx(handle, name)
                    if value:
                        return str(value)
                except OSError:
                    pass
        except ImportError:
            pass
    return default


def api_env_status():
    return {
        "openai_api_key": bool(_env("OPENAI_API_KEY")),
        "openai_vision_model": _env("OPENAI_VISION_MODEL", "gpt-5-mini"),
        "mathpix_app_id": bool(_env("MATHPIX_APP_ID")),
        "mathpix_app_key": bool(_env("MATHPIX_APP_KEY")),
    }


def _post_json(url, key, payload, timeout):
    request = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Authorization": f"Bearer {key}", "Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(request, timeout=timeout) as response:
        return json.loads(response.read().decode("utf-8"))


def _parse_json(text):
    text = (text or "").strip()
    if text.startswith("```"):
        text = re.sub(r"^```(?:json)?\s*|\s*```$", "", text, flags=re.IGNORECASE)
    return json.loads(text)


def _data_url(images):
    return ["data:image/png;base64," + base64.b64encode(image).decode("ascii") for image in images]


def _openai(
    fields, images, timeout, ocr_reference="", validation_feedback="",
    rejected_candidate=None, allow_option_recovery=False, image_source_fields=None,
    verification_crop_fields=None,
):
    key = _env("OPENAI_API_KEY")
    if not key:
        raise RuntimeError("OPENAI_API_KEY is not configured")
    supplied = PROMPT + "\nExtracted PDF text:\n" + json.dumps(fields, ensure_ascii=False)
    image_source_fields = sorted(set(image_source_fields or []))
    verification_crop_fields = sorted(set(verification_crop_fields or []))
    controlled_visual_fields = sorted(set(image_source_fields + verification_crop_fields))
    if controlled_visual_fields:
        supplied += (
            "\nFor these fields, the supplied image is an OCR source, not automatically delivery content: "
            + ", ".join(controlled_visual_fields)
            + ". Transcribe all visible prose, symbols, equations, chemistry, and tables into CKEditor HTML. "
            "Do not return their [image: ...] markers. In visual_regions, return tight normalized boxes only "
            "for genuine non-text visuals that must remain visible, such as diagrams, plots, graphs, maps, "
            "geometric figures, apparatus, chemical structures, or pictorial answer choices. Exclude printed text, "
            "equations, matrices, tables that can be HTML, option labels, borders, logos, and watermarks."
        )
    if verification_crop_fields:
        supplied += (
            "\nThe verification crop may contain the whole question and options. Never preserve the whole crop; "
            "visual_regions must isolate only the genuine visual itself."
        )

    if allow_option_recovery:
        supplied += (
            "\nThis row is a verified MCQ/MSQ, but OCR missed one or more option fields. "
            "Inspect the authoritative image and transcribe each visually present labeled option A-D "
            "into option1-option4. Do not solve or infer an option that is not visibly printed."
        )
    if ocr_reference:
        supplied += "\nMathpix OCR reference (use only to resolve visual symbols):\n" + ocr_reference
    if validation_feedback:
        supplied += "\nThe previous response failed validation. Correct this exact issue: " + validation_feedback
    if rejected_candidate:
        supplied += (
            "\nRejected JSON response (repair it without changing source meaning):\n"
            + json.dumps(rejected_candidate, ensure_ascii=False)
        )
    content = [{"type": "input_text", "text": supplied}]
    content.extend({"type": "input_image", "image_url": url, "detail": "high"} for url in _data_url(images))
    payload = {
        "model": _env("OPENAI_VISION_MODEL", "gpt-5-mini"),
        "input": [{"role": "user", "content": content}],
        "text": {"format": {"type": "json_object"}},
        "reasoning": {"effort": "minimal"},
        "max_output_tokens": 3000,
    }
    result = _post_json("https://api.openai.com/v1/responses", key, payload, timeout)
    output_text = result.get("output_text")
    if not output_text:
        for item in result.get("output", []):
            for part in item.get("content", []):
                if part.get("type") == "output_text":
                    output_text = part.get("text")
                    break
    return _parse_json(output_text), "openai_vision", result.get("usage", {})


def _mathpix(images, timeout):
    app_id = _env("MATHPIX_APP_ID")
    app_key = _env("MATHPIX_APP_KEY")
    if not app_id or not app_key:
        raise RuntimeError("MATHPIX_APP_ID and MATHPIX_APP_KEY are not configured")
    outputs = []
    for image in images:
        payload = {
            "src": "data:image/png;base64," + base64.b64encode(image).decode("ascii"),
            "formats": ["text", "latex_styled"],
            "data_options": {"include_latex": True, "include_asciimath": False},
        }
        request = urllib.request.Request(
            "https://api.mathpix.com/v3/text",
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json", "app_id": app_id, "app_key": app_key},
            method="POST",
        )
        with urllib.request.urlopen(request, timeout=timeout) as response:
            result = json.loads(response.read().decode("utf-8"))
        outputs.append(result.get("latex_styled") or result.get("text") or "")
    return "\n\n".join(outputs), {"requests": len(images)}

def _validate_math_core(core):
    if r"\tfrac{\quad}{}" in core:
        return False, "model invented an empty fraction"
    if re.search(r"\\d[xyzt]\b", core):
        return False, "model invented an invalid differential command"
    if core.count("{") != core.count("}") or core.count("[") != core.count("]"):
        return False, "unbalanced TeX delimiters"
    for environment in ("array", "matrix", "pmatrix", "bmatrix", "cases", "table", "tabular"):
        if core.count(f"\\begin{{{environment}}}") != core.count(f"\\end{{{environment}}}"):
            return False, f"unbalanced {environment} environment"
    return True, ""


def _validate_html_fragment(value):
    if not value:
        return True, ""
    if "$" in value:
        return False, "dollar-sign math delimiters are not supported"
    parser = _FragmentParser()
    try:
        parser.feed(value)
        parser.close()
    except Exception:
        return False, "malformed HTML fragment"
    if parser.invalid_paragraphs or parser.paragraph_depth:
        return False, "nested or unbalanced paragraph tags"
    if not re.search(r"<(?:p|table)\b", value, re.IGNORECASE):
        return False, "non-empty field is not a CKEditor HTML fragment"

    spans = MATH_SPAN_RE.findall(value)
    display_spans = sum(content.strip().startswith(r"\[") for content in spans)
    standalone_displays = len(re.findall(
        r'<p>\s*<span\s+class=["\']math-tex["\']\s*>\s*\\\[.*?\\\]\s*</span>\s*</p>',
        value,
        re.IGNORECASE | re.DOTALL,
    ))
    if display_spans != standalone_displays:
        return False, "display MathJax must be in its own paragraph"
    span_openings = len(re.findall(r'<span\s+class=["\']math-tex["\']\s*>', value, re.IGNORECASE))
    if span_openings != len(spans) or value.lower().count("</span>") != len(spans):
        return False, "unbalanced math-tex span"
    outside_math = MATH_SPAN_RE.sub("", value)
    outside_math = re.sub(r"\[image:\s*[^\]]+\]", "", outside_math, flags=re.IGNORECASE)
    if "\\" in outside_math:
        return False, "TeX command appears outside a math-tex span"
    if re.search(r"(?<!_)_(?!_)\s*(?:\{[^{}]*\}|[A-Za-z0-9])|\^\s*(?:\{[^{}]*\}|[A-Za-z0-9])", outside_math):
        return False, "TeX superscript or subscript appears outside a math-tex span"
    for content in spans:
        content = content.strip()
        if content.startswith(r"\(") and content.endswith(r"\)"):
            core = content[2:-2].strip()
        elif content.startswith(r"\[") and content.endswith(r"\]"):
            core = content[2:-2].strip()
        else:
            return False, "math-tex span has invalid MathJax delimiters"
        if not core:
            return False, "empty math-tex span"
        valid, reason = _validate_math_core(core)
        if not valid:
            return valid, reason
    return True, ""


def visible_field_content(value):
    value = re.sub(r"<[^>]+>", "", str(value or ""))
    value = re.sub(r"\[image:\s*[^\]]+\]", "", value, flags=re.IGNORECASE)
    return re.sub(r"\s+", "", value)


def has_flat_grouped_fraction(value):
    outside_math = MATH_SPAN_RE.sub("", str(value or ""))
    outside_math = re.sub(r"\[image:\s*[^\]]+\]", "", outside_math, flags=re.IGNORECASE)
    outside_math = re.sub(r"<[^>]+>", " ", outside_math)
    grouped_over_grouped = re.search(
        r"\([^()]{1,240}\)\s*[/⁄]\s*\([^()]{1,240}\)", outside_math
    )
    grouped_denominator = re.search(
        r"(?:\)|\]|[A-Za-z0-9₀-₉⁰-⁹])\s*[/⁄]\s*\([^()]*[+−-][^()]*\)",
        outside_math,
    )
    nested_slashes = len(re.findall(r"[/⁄]", outside_math)) >= 2 and bool(
        re.search(r"[()\[\]+−-]", outside_math)
    )
    return bool(grouped_over_grouped or grouped_denominator or nested_slashes)


def field_requires_vision(value):
    fields = {field: "" for field in FIELDS}
    fields["question"] = str(value or "")
    return bool(vision_verification_reasons(fields))

def _valid(
    original, candidate, allow_option_recovery=False, marker_change_fields=None,
    content_recovery_fields=None,
):
    if not isinstance(candidate, dict) or any(field not in candidate for field in FIELDS):
        return False, "missing required JSON fields"
    marker_change_fields = set(marker_change_fields or [])
    content_recovery_fields = set(content_recovery_fields or [])
    for field in FIELDS:
        if field in marker_change_fields:
            continue
        before = re.findall(r"\[image:\s*[^\]]+\]", str(original.get(field, "")))
        after = re.findall(r"\[image:\s*[^\]]+\]", str(candidate.get(field, "")))
        if before != after:
            return False, f"{field}: image markers changed"
    for field in FIELDS:
        original_present = bool(visible_field_content(original.get(field, "")))
        candidate_present = bool(visible_field_content(candidate.get(field, "")))
        if not original_present and candidate_present:
            recoverable_option = allow_option_recovery and field.startswith("option")
            recoverable_image_text = field in content_recovery_fields
            if not recoverable_option and not recoverable_image_text:
                return False, f"{field}: content was invented for an empty source field"
        if original_present and not candidate_present:
            return False, f"{field}: source content was removed"
        original_field = str(original.get(field, ""))
        candidate_field = str(candidate.get(field, ""))
        if has_flat_grouped_fraction(candidate_field):
            return False, f"{field}: grouped fraction must use MathJax \\frac"
        matrix_context = re.search(
            r"\b(?:matrix|determinant|eigenvector|eigenvalue)\b",
            original_field + " " + candidate_field,
            re.IGNORECASE,
        )
        if matrix_context and re.search(r"<table\b", candidate_field, re.IGNORECASE):
            return False, f"{field}: mathematical matrix must not use an HTML table"
        if has_stacked_math(original_field):
            structured = MATH_SPAN_RE.search(candidate_field) or "⁄" in candidate_field
            if not structured:
                return False, f"{field}: stacked source must use MathJax structure"
    original_text = "\n".join(original.values()).lower()
    candidate_text = "\n".join(str(candidate.get(field, "")) for field in FIELDS).lower()
    for tag in ("<table", "</table>"):
        original_count = original_text.count(tag)
        if original_count and original_count != candidate_text.count(tag):
            return False, "HTML table structure changed"
    layout_source = re.sub(r"\[image:\s*[^\]]+\]", "", original_text, flags=re.IGNORECASE)
    matrix_entries = re.search(r"\bmatrix\b", layout_source, re.IGNORECASE) and (
        "[" in layout_source or MATRIX_BRACKET_GLYPH_RE.search(layout_source)
    )
    structured_layout = matrix_entries or re.search(
        r"\b(?:determinant|piecewise)\b", layout_source, re.IGNORECASE
    )
    if structured_layout:
        if not re.search(r"\\begin\{(?:array|matrix|pmatrix|bmatrix|vmatrix|cases)\}", candidate_text):
            return False, "structured matrix or cases source must use a MathJax environment"
    if re.search(r"\b(?:differential equation|dy/dx|d2y)\b", original_text, re.IGNORECASE):
        if not MATH_SPAN_RE.search(candidate_text):
            return False, "structured differential source must use MathJax"
    visible_original = re.sub(r"\[image:\s*[^\]]+\]", "", original_text, flags=re.IGNORECASE)
    visible_original = re.sub(r"<[^>]+>", "", visible_original)
    visible_candidate = re.sub(r"\[image:\s*[^\]]+\]", "", candidate_text, flags=re.IGNORECASE)
    visible_candidate = re.sub(r"<[^>]+>", "", visible_candidate)
    if len(visible_original) > 40 and len(visible_candidate) < len(visible_original) * 0.55:
        return False, "too much source text was lost"
    visible_question = re.sub(r"<[^>]+>", " ", str(candidate.get("question", "")))
    if re.match(r"^\s*Q[.\s]*\d+\b", visible_question, re.IGNORECASE):
        return False, "leading question number was retained"
    for field in FIELDS:
        valid, reason = _validate_html_fragment(str(candidate.get(field, "")))
        if not valid:
            return False, f"{field}: {reason}"
    return True, ""


def _question_clip(page, question_no):
    body = fitz.Rect(45, 60, page.rect.width - 45, page.rect.height - 55)
    marker = re.compile(
        rf"(?:^|\n)\s*Q[.\s]*0*{re.escape(str(question_no))}\b(?!\s*[-\u2013\u2014])",
        re.IGNORECASE,
    )
    blocks = sorted(page.get_text("blocks"), key=lambda block: (block[1], block[0]))
    matches = [block for block in blocks if marker.search(block[4] or "")]
    if not matches:
        return body
    start_y = max(body.y0, matches[0][1] - 10)
    end_y = body.y1
    any_question = re.compile(
        r"(?:^|\n)\s*Q[.\s]*\d+\b(?!\s*[-\u2013\u2014])",
        re.IGNORECASE,
    )
    for block in blocks:
        if block[1] > matches[0][1] + 5 and any_question.search(block[4] or ""):
            end_y = min(end_y, block[1] - 8)
            break
    return fitz.Rect(body.x0, start_y, body.x1, max(start_y + 40, end_y))


def _render_pages(pdf_path, pages, question_no, scale=3.0):
    rendered = []
    with fitz.open(pdf_path) as doc:
        for page_no in pages[:2]:
            if 1 <= int(page_no) <= doc.page_count:
                page = doc[int(page_no) - 1]
                clip = _question_clip(page, question_no)
                pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), clip=clip, alpha=False)
                rendered.append(pix.tobytes("png"))
    return rendered

def vision_verification_reasons(fields):
    text = "\n".join(str(fields.get(field, "")) for field in FIELDS)
    text = re.sub(r"\[image:\s*[^\]]+\]", "", text, flags=re.IGNORECASE)
    reasons = []
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    fragmented = (
        len(lines) >= 6
        and sum(len(line) <= 3 for line in lines) / len(lines) >= 0.30
    )
    if BROKEN_GLYPH_RE.search(text):
        reasons.append("private-use or broken PDF math glyphs")
    if MATH_STYLED_RUN_RE.search(text):
        reasons.append("ambiguous styled math run may contain flattened scripts")
    if any(has_stacked_math(fields.get(field, "")) for field in FIELDS):
        reasons.append("stacked mathematical layout may contain a flattened fraction")
    if fragmented:
        reasons.append("heavily fragmented equation text")
    preserved = bool(PRESERVED_TEX_RE.search(text))
    if not preserved and STRUCTURED_NOTATION_RE.search(text):
        reasons.append("structured mathematical notation")
    if not preserved and COMPLEX_OPERATOR_RE.search(text):
        reasons.append("two-dimensional operator layout")
    return reasons

def summarize_usage(events):
    totals = {
        "input_tokens": 0,
        "output_tokens": 0,
        "total_tokens": 0,
        "mathpix_requests": 0,
    }
    for event in events:
        for attempt in event.get("attempts", []):
            usage = attempt.get("usage") or {}
            input_tokens = int(usage.get("input_tokens", usage.get("prompt_tokens", 0)) or 0)
            output_tokens = int(usage.get("output_tokens", usage.get("completion_tokens", 0)) or 0)
            totals["input_tokens"] += input_tokens
            totals["output_tokens"] += output_tokens
            totals["total_tokens"] += int(usage.get("total_tokens", input_tokens + output_tokens) or 0)
            if attempt.get("provider") == "mathpix":
                totals["mathpix_requests"] += int(usage.get("requests", 0) or 0)
    totals["estimated_openai_gpt5_mini_usd"] = round(
        totals["input_tokens"] * 0.25 / 1_000_000
        + totals["output_tokens"] * 2.00 / 1_000_000,
        8,
    )
    totals["estimated_mathpix_usd"] = round(totals["mathpix_requests"] * 0.002, 6)
    totals["estimated_total_usd"] = round(
        totals["estimated_openai_gpt5_mini_usd"] + totals["estimated_mathpix_usd"],
        8,
    )
    totals["pricing_urls"] = {
        "openai": "https://developers.openai.com/api/docs/models/gpt-5-mini",
        "mathpix": "https://website.mathpix.com/docs/convert/billing",
    }
    return totals

def _file_digest(path):
    digest = hashlib.sha256()
    with Path(path).open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _row_image_bytes(row, output_dir):
    images = []
    for field in ("question_images", "option1_images", "option2_images", "option3_images", "option4_images"):
        for reference in str(row.get(field, "")).split(";"):
            reference = reference.strip()
            if not reference:
                continue
            candidate = output_dir / "images" / Path(reference).name
            if candidate.exists():
                images.append(candidate.read_bytes())
    return images


IMAGE_FIELD_BY_CONTENT = {
    "question": "question_images",
    "option1": "option1_images",
    "option2": "option2_images",
    "option3": "option3_images",
    "option4": "option4_images",
}


def _row_image_paths(row, output_dir, field):
    paths = []
    for reference in str(row.get(IMAGE_FIELD_BY_CONTENT[field], "")).split(";"):
        reference = reference.strip()
        if not reference:
            continue
        candidate = Path(output_dir) / "images" / Path(reference).name
        if candidate.exists():
            paths.append((reference, candidate))
    return paths


def _normalized_regions(value):
    regions = []
    if not isinstance(value, list):
        return regions
    for item in value:
        if not isinstance(item, (list, tuple)) or len(item) != 4:
            continue
        try:
            x0, y0, x1, y1 = (max(0.0, min(1000.0, float(number))) for number in item)
        except (TypeError, ValueError):
            continue
        if x1 - x0 >= 12 and y1 - y0 >= 12:
            regions.append((x0, y0, x1, y1))
    return regions[:4]


def apply_visual_regions(row, candidate, visual_regions, output_dir, controlled_fields, verification_fields):
    controlled_fields = set(controlled_fields or [])
    verification_fields = set(verification_fields or [])
    visual_regions = visual_regions if isinstance(visual_regions, dict) else {}
    for field in controlled_fields:
        source_images = _row_image_paths(row, output_dir, field)
        candidate[field] = re.sub(r"\s*\[image:\s*[^\]]+\]", "", str(candidate.get(field, ""))).strip()
        if not source_images:
            row[IMAGE_FIELD_BY_CONTENT[field]] = ""
            continue
        reference, source_path = source_images[0]
        regions = _normalized_regions(visual_regions.get(field, []))
        with Image.open(source_path) as source:
            source = source.convert("RGB")
            accepted = []
            for region in regions:
                x0, y0, x1, y1 = region
                if field in verification_fields and (x1 - x0) * (y1 - y0) > 720000:
                    continue
                box = (
                    max(0, int(source.width * x0 / 1000)),
                    max(0, int(source.height * y0 / 1000)),
                    min(source.width, int(source.width * x1 / 1000)),
                    min(source.height, int(source.height * y1 / 1000)),
                )
                if box[2] - box[0] < 8 or box[3] - box[1] < 8:
                    continue
                accepted.append(source.crop(box))
        output_refs = []
        base_name = source_path.stem
        base_name = re.sub(r"__img\d+$", "", base_name)
        parent = str(Path(reference).parent).replace("\\", "/")
        for index, crop in enumerate(accepted, start=1):
            filename = f"{base_name}__img{index:02d}.png"
            destination = Path(output_dir) / "images" / filename
            crop.save(destination, format="PNG")
            relative = f"{parent}/{filename}" if parent not in {"", "."} else filename
            output_refs.append(relative)
        row[IMAGE_FIELD_BY_CONTENT[field]] = ";".join(output_refs)
        if output_refs:
            markers = " ".join(f"[image: {reference}]" for reference in output_refs)
            value = candidate[field]
            if value.lower().endswith("</p>"):
                candidate[field] = value + f"<p>{markers}</p>"
            else:
                candidate[field] = (value + f"<p>{markers}</p>").strip()
    return candidate

def _atomic_json(path, payload):
    temporary = path.with_suffix(".tmp")
    temporary.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    temporary.replace(path)


def enhance_math_output(pdf_path, output_dir, mode="auto", max_calls=50, timeout=90):
    if mode == "off":
        return {"calls": 0, "enhanced": 0, "events": []}
    output_dir = Path(output_dir)
    csv_path = output_dir / "questions.csv"
    json_path = output_dir / "questions.json"
    checkpoint_path = output_dir / "ai_math_checkpoint.json"
    with csv_path.open(encoding="utf-8-sig", newline="") as source:
        reader = csv.DictReader(source)
        fieldnames, rows = reader.fieldnames, list(reader)

    profile = {
        "version": 3,
        "source_sha256": _file_digest(pdf_path),
        "mode": mode,
        "model": _env("OPENAI_VISION_MODEL", "gpt-5-mini"),
        "max_calls": max_calls,
    }
    events, calls, enhanced, next_index = [], 0, 0, 0
    if checkpoint_path.exists():
        try:
            checkpoint = json.loads(checkpoint_path.read_text(encoding="utf-8"))
            if checkpoint.get("profile") == profile:
                rows = checkpoint.get("rows", rows)
                events = checkpoint.get("events", [])
                calls = int(checkpoint.get("calls", 0))
                enhanced = int(checkpoint.get("enhanced", 0))
                next_index = int(checkpoint.get("next_index", 0))
        except (OSError, ValueError, json.JSONDecodeError):
            next_index = 0
    if next_index < len(rows) and events:
        pending_question = str(rows[next_index].get("question_no"))
        last_event = events[-1]
        if str(last_event.get("question_no")) == pending_question and last_event.get("status") in {"vision_verification_failed", "limit_reached"}:
            events.pop()

    def save_checkpoint(index):
        _atomic_json(checkpoint_path, {
            "profile": profile, "next_index": index, "rows": rows,
            "events": events, "calls": calls, "enhanced": enhanced,
        })

    for index in range(next_index, len(rows)):
        row = rows[index]
        original = {field: row.get(field, "") for field in FIELDS}
        vision_reasons = vision_verification_reasons(original)
        try:
            metadata = json.loads(row.get("metadata_json") or "{}")
        except json.JSONDecodeError:
            metadata = {}
        scan_layout = metadata.get("layout") == "gate-scanned-page"
        image_text_fields = {
            field for field in FIELDS
            if _row_image_paths(row, output_dir, field)
            and not visible_field_content(original.get(field, ""))
        }
        verification_crop_fields = {"question"} if scan_layout and _row_image_paths(row, output_dir, "question") else set()
        controlled_visual_fields = image_text_fields | verification_crop_fields
        allow_option_recovery = (
            scan_layout
            and row.get("question_type") in {"MCQ", "MSQ"}
            and any(not visible_field_content(original.get(f"option{number}", "")) for number in range(1, 5))
        )
        if image_text_fields:
            vision_reasons.append("image-only source fields require transcription")
        if allow_option_recovery:
            vision_reasons.append("MCQ/MSQ source is missing one or more options")
        if scan_layout and "scanned source requires vision verification" not in vision_reasons:
            vision_reasons.append("scanned source requires vision verification")
        if mode == "auto" and not vision_reasons:
            save_checkpoint(index + 1)
            continue
        if calls >= max_calls:
            events.append({"question_no": row.get("question_no"), "status": "limit_reached"})
            save_checkpoint(len(rows))
            break

        pages = [int(value) for value in row.get("source_pages", "").split(";") if value.isdigit()]
        extracted_images = _row_image_bytes(row, output_dir)
        if controlled_visual_fields and extracted_images:
            images = extracted_images[:5]
        elif scan_layout and extracted_images:
            images = extracted_images[:2]
        else:
            images = (_render_pages(pdf_path, pages, row.get("question_no")) + extracted_images)[:5]
        attempts = []
        candidate = None
        candidate_visual_regions = {}
        preserve_marker_fields = set(FIELDS) - controlled_visual_fields

        def prepare_candidate(raw_candidate):
            regions = raw_candidate.get("visual_regions", {}) if isinstance(raw_candidate, dict) else {}
            normalized = normalize_candidate(raw_candidate)
            normalized = restore_image_markers(original, normalized, preserve_marker_fields)
            return normalized, regions

        validation_options = {
            "allow_option_recovery": allow_option_recovery,
            "marker_change_fields": controlled_visual_fields,
            "content_recovery_fields": image_text_fields,
        }
        try:
            calls += 1
            candidate, provider, usage = unpack_provider_result(_openai(
                original, images, timeout, allow_option_recovery=allow_option_recovery,
                image_source_fields=image_text_fields,
                verification_crop_fields=verification_crop_fields,
            ))
            candidate, candidate_visual_regions = prepare_candidate(candidate)
            valid, reason = _valid(original, candidate, **validation_options)
            attempts.append({"provider": provider, "valid": valid, "reason": reason, "usage": usage})
            if not valid:
                attempts[-1]["rejected_candidate"] = candidate
            if not valid:
                candidate = None
        except Exception as exc:
            attempts.append({"provider": "openai_vision", "valid": False, "reason": str(exc)[:300]})

        validation_feedback = attempts[-1].get("reason", "") if attempts else ""
        if candidate is None and images and calls + len(images) < max_calls:
            try:
                calls += len(images)
                mathpix_text, usage = _mathpix(images, timeout)
                attempts.append({"provider": "mathpix", "valid": bool(mathpix_text), "reason": "", "usage": usage})
                if mathpix_text and calls < max_calls:
                    calls += 1
                    candidate, provider, usage = unpack_provider_result(
                        _openai(
                            original, images, timeout, ocr_reference=mathpix_text,
                            validation_feedback=validation_feedback,
                            allow_option_recovery=allow_option_recovery,
                            image_source_fields=image_text_fields,
                            verification_crop_fields=verification_crop_fields,
                        )
                    )
                    candidate, candidate_visual_regions = prepare_candidate(candidate)
                    valid, reason = _valid(original, candidate, **validation_options)
                    attempts.append({
                        "provider": "openai_vision_mathpix_reference",
                        "valid": valid, "reason": reason, "usage": usage,
                    })
                    if not valid:
                        attempts[-1]["rejected_candidate"] = candidate
                    if not valid:
                        rejected_candidate = candidate
                        candidate = None
                        if calls < max_calls:
                            calls += 1
                            candidate, provider, usage = unpack_provider_result(
                                _openai(
                                    original, images, timeout,
                                    ocr_reference=mathpix_text,
                                    validation_feedback=reason,
                                    rejected_candidate=rejected_candidate,
                                    allow_option_recovery=allow_option_recovery,
                                    image_source_fields=image_text_fields,
                                    verification_crop_fields=verification_crop_fields,
                                )
                            )
                            candidate, candidate_visual_regions = prepare_candidate(candidate)
                            valid, reason = _valid(original, candidate, **validation_options)
                            attempts.append({
                                "provider": "openai_vision_final_repair",
                                "valid": valid, "reason": reason, "usage": usage,
                            })
                            if not valid:
                                attempts[-1]["rejected_candidate"] = candidate
                            if not valid:
                                candidate = None
            except Exception as exc:
                attempts.append({"provider": "mathpix", "valid": False, "reason": str(exc)[:300]})

        if candidate is not None:
            if controlled_visual_fields:
                candidate = apply_visual_regions(
                    row, candidate, candidate_visual_regions, output_dir,
                    controlled_visual_fields, verification_crop_fields,
                )
            for field in FIELDS:
                if (
                    mode == "always" or scan_layout or field in controlled_visual_fields
                    or field_requires_vision(original.get(field, ""))
                ):
                    row[field] = str(candidate.get(field, ""))
            enhanced += 1
            status = "enhanced"
        else:
            status = "vision_verification_failed" if vision_reasons else "unchanged"
        events.append({
            "question_no": row.get("question_no"), "status": status,
            "vision_reasons": vision_reasons, "attempts": attempts,
        })
        if status == "vision_verification_failed":
            save_checkpoint(index)
            break
        save_checkpoint(index + 1)

    with csv_path.open("w", encoding="utf-8-sig", newline="") as target:
        writer = csv.DictWriter(target, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)
    payload = json.loads(json_path.read_text(encoding="utf-8"))
    by_number = {str(row.get("question_no")): row for row in rows}
    for question in payload.get("questions", []):
        row = by_number.get(str(question.get("question_no")))
        if not row:
            continue
        question["question_text"] = row["question"]
        question["question_images"] = [item for item in row.get("question_images", "").split(";") if item]
        for option_index, label in enumerate("ABCD", start=1):
            if label in question.get("options", {}):
                question["options"][label]["text"] = row[f"option{option_index}"]
                question["options"][label]["images"] = [
                    item for item in row.get(f"option{option_index}_images", "").split(";") if item
                ]
    payload.setdefault("metadata", {})["ai_math"] = {"calls": calls, "enhanced": enhanced}
    json_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
    report = {"calls": calls, "enhanced": enhanced, "usage": summarize_usage(events), "events": events}
    (output_dir / "ai_math_report.json").write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    return report
