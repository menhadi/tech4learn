import re
from dataclasses import dataclass
from pathlib import Path

import fitz


@dataclass(frozen=True)
class DetectionResult:
    extractor: str
    confidence: float
    scores: dict
    reasons: list
    uncertain: bool


SIGNATURES = {
    "upsc_bilingual": [],
    "answer_key_pdf": [
        (r"\bAnswer\s+Key\s+for\b|\bAnswer\s+Key\b", 4, "answer-key title"),
        (r"\bQ\.\s*No\.\s*\n\s*Session\s*\n\s*Q\.\s*Type\b", 8, "answer-key columns"),
        (r"\bKey/Range\b", 7, "key/range column"),
    ],
    "gate_pdf": [
        (r"\bGATE\s+20\d{2}\b|Graduate\s+Aptitude\s+Test\s+in\s+Engineering", 6, "GATE title"),
        (r"\bAerospace\s+Engineering\b|AEROSPACE\s+ENGINEERING\s*[-\u2013]\s*AE", 6, "Aerospace Engineering paper"),
    ],
    "cuetug": [
        (r"\bItem\s*No\.?\s*\d+", 4, "Item No"),
        (r"\bQuestion\s*ID\s*[:.]", 3, "Question ID"),
        (r"\bQuestion\s*Type\s*[:.]", 4, "Question Type"),
        (r"(?:^|\n)\s*[A-D]\s*:", 3, "A-D labels"),
    ],
    "ugcnet_bilingual": [
        (r"\bSl\.?\s*No\.?\s*\d+", 5, "Sl. No"),
        (r"\bQBID\s*[:.]\s*\d+", 7, "QBID"),
        (r"(?:^|\n)\s*\([1-4]\)", 2, "numbered options"),
    ],
    "jee_nta_image": [
        (r"\bQuestion\s*Number\s*[:.]?\s*\d+", 6, "Question Number"),
        (r"\bQuestion\s*ID\s*[:.]", 3, "Question ID"),
        (r"\bOptions\s*:", 4, "Options"),
    ],
    "text_math_pdf": [
        (r"(?:^|\n)\s*Q\.?\s*\d+\b", 5, "Q-number text"),
        (r"(?:^|\n)\s*\(?[A-D]\)\s+", 3, "A-D text options"),
    ],
}


def detect_pdf(pdf_path, sample_pages=16, minimum_score=5, minimum_margin=2):
    path = Path(pdf_path)
    with fitz.open(path) as doc:
        page_count = doc.page_count
        if not page_count:
            if re.search(r"Aerospace Engineering.*20\d{2}|AE20\d{2}", path.name, re.IGNORECASE):
                return DetectionResult("gate_pdf", 1.0, {"gate_pdf": 10}, ["zero-page GATE source requires repair"], True)
            return DetectionResult("text_math_pdf", 0.0, {}, ["empty PDF"], True)
        indexes = list(range(min(sample_pages, page_count)))
        if page_count > sample_pages:
            indexes.extend(range(max(sample_pages, page_count - 3), page_count))
        text = "\n".join(doc[index].get_text("text") for index in sorted(set(indexes)))
        image_count = sum(len(doc[index].get_images(full=True)) for index in sorted(set(indexes)))

    scores = {}
    matched = {}
    for extractor, signatures in SIGNATURES.items():
        score = 0
        reasons = []
        for pattern, weight, label in signatures:
            if re.search(pattern, text, re.IGNORECASE | re.MULTILINE):
                score += weight
                reasons.append(label)
        scores[extractor] = score
        matched[extractor] = reasons

    if re.search(r"Aerospace Engineering.*20\d{2}|AE20\d{2}", path.name, re.IGNORECASE):
        scores["gate_pdf"] += 10
        matched["gate_pdf"].append("GATE Aerospace source filename")
    if (
        page_count == 48
        and re.search(
            r"^General\s+Studies\s+Paper\s*[- ]*(?:I|II)$",
            path.stem,
            re.IGNORECASE,
        )
    ):
        scores["upsc_bilingual"] += 20
        matched["upsc_bilingual"].extend([
            "UPSC General Studies paper filename",
            "48-page paired bilingual scan",
        ])
    ranked = sorted(scores.items(), key=lambda item: item[1], reverse=True)
    best, best_score = ranked[0]
    second_score = ranked[1][1]
    if best_score == 0 and image_count:
        best = "jee_nta_image"
        matched[best].append("image-heavy PDF without selectable question text")
    confidence = 0.0 if best_score == 0 else min(1.0, best_score / max(10, best_score + second_score))
    uncertain = best_score < minimum_score or best_score - second_score < minimum_margin
    return DetectionResult(best, round(confidence, 3), scores, matched[best], uncertain)
