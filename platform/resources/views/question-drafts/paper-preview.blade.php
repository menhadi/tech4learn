<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - Full paper draft</title>
    <script>
        window.MathJax = {loader:{load:['[tex]/mhchem']},tex:{packages:{'[+]':['mhchem']},inlineMath:[['\\(','\\)'],['$','$']],displayMath:[['\\[','\\]'],['$$','$$']],processEscapes:true},options:{skipHtmlTags:['script','noscript','style','textarea','pre','code']}};
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js"></script>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #edf2f1; color: #172033; font-family: Arial, Helvetica, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 20px; background: #fff; border-bottom: 1px solid #d7e2df; }
        .toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .button { display: inline-flex; align-items: center; min-height: 38px; padding: 8px 14px; border: 1px solid #0f766e; border-radius: 7px; color: #0f766e; background: #fff; font-weight: 700; text-decoration: none; cursor: pointer; }
        .button.primary { color: #fff; background: #0f766e; }
        .paper { width: min(210mm, calc(100% - 32px)); min-height: 270mm; margin: 24px auto; padding: 15mm 16mm; background: #fff; box-shadow: 0 8px 30px rgba(15, 42, 39, .12); }
        .paper-header { display: flex; justify-content: space-between; gap: 20px; padding-bottom: 12px; border-bottom: 2px solid #0f766e; }
        .eyebrow { color: #0f766e; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 5px 0 0; font-size: 24px; }
        .meta { color: #64748b; font-size: 13px; line-height: 1.5; text-align: right; }
        .notice { margin: 16px 0 6px; padding: 10px 12px; border: 1px solid #f0c36b; border-radius: 6px; background: #fff8e6; color: #79520a; font-size: 13px; }
        .question-block { padding: 20px 0; border-bottom: 1px solid #cbd5e1; break-inside: avoid-page; }
        .question-block:last-child { border-bottom: 0; }
        .question-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 10px; }
        .question-heading h2 { margin: 0; font-size: 17px; color: #0f766e; }
        .status { color: #64748b; font-size: 12px; }
        .question { font-size: 16px; line-height: 1.6; overflow-wrap: anywhere; }
        .question img, .option img, .review-content img { display: block; max-width: 100%; height: auto; margin: 12px auto; object-fit: contain; }
        .options { margin-top: 14px; display: grid; gap: 8px; }
        .option { display: grid; grid-template-columns: 30px minmax(0, 1fr); gap: 9px; align-items: start; padding: 9px 11px; border: 1px solid #d8e1df; border-radius: 6px; font-size: 15px; line-height: 1.5; overflow-wrap: anywhere; }
        .option-label { display: inline-flex; justify-content: center; align-items: center; width: 25px; height: 25px; border-radius: 50%; background: #e6f3f1; color: #0f766e; font-weight: 700; }
        .review { margin-top: 12px; padding: 10px 12px; border-left: 3px solid #0f766e; background: #f4f8f7; font-size: 14px; line-height: 1.55; }
        .review h3 { margin: 0 0 6px; font-size: 14px; color: #0f766e; }
        .review ul { margin: 0; padding-left: 20px; }
        body.hide-review .review { display: none; }
        .empty { padding: 45px 0; color: #64748b; text-align: center; }
        @page { size: A4; margin: 12mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .paper { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .notice { display: none; }
            .question-block { break-inside: auto; }
            .question-heading, .option, img { break-inside: avoid; }
        }
        @media (max-width: 720px) {
            .paper { width: calc(100% - 16px); margin: 8px; padding: 22px 16px; }
            .paper-header { display: block; }
            .meta { margin-top: 8px; text-align: left; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <div><strong>Full paper rendered preview</strong><div style="font-size:12px;color:#64748b">Always composed from the latest saved drafts. Refresh after editing.</div></div>
    <div class="toolbar-actions">
        <a class="button" href="{{ $backUrl }}">Back to paper</a>
        @isset($paperEditUrl)<a class="button primary" href="{{ $paperEditUrl }}">Edit complete paper</a>@endisset
        @isset($paperPreviewUrl)<a class="button" href="{{ $paperPreviewUrl }}" target="_blank">View clean preview</a>@endisset
        @isset($paperPublishUrl)
            <form method="POST" action="{{ $paperPublishUrl }}" onsubmit="return confirm('Publish every changed manual draft to the live paper? Restorable versions will be created.');" style="margin:0">@csrf @method('PATCH')<button class="button primary" type="submit">Publish all saved drafts</button></form>
        @endisset
        <button class="button" type="button" onclick="document.body.classList.toggle('hide-review'); this.textContent = document.body.classList.contains('hide-review') ? 'Show answers & explanations' : 'Hide answers & explanations'">Hide answers & explanations</button>
        <button class="button primary" type="button" onclick="window.print()">Print / Save PDF</button>
    </div>
</div>
<main class="paper">
    <header class="paper-header">
        <div><div class="eyebrow">{{ $contextLabel }}</div><h1>{{ $title }}</h1></div>
        <div class="meta"><strong>{{ $questions->count() }} {{ Str::plural('question', $questions->count()) }}</strong><br>Generated {{ now()->format('d M Y, h:i A') }}<br>Latest saved draft content</div>
    </header>
    @if(session('success'))<div class="notice" style="border-color:#86c9a6;background:#ecfdf3;color:#166534">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="notice" style="border-color:#f1a5a5;background:#fff1f1;color:#991b1b">{{ session('error') }}</div>@endif
    <div class="notice">Preview only. Refresh this page after saving a question. Downloaded PDF files are snapshots and must be saved again after later edits.</div>
    @forelse($questions as $item)
        @php
            $payload = (array) ($item['payload'] ?? []);
            $optionRows = collect(range(1, 6))->map(fn ($index) => ['index' => $index, 'html' => (string) ($payload['option'.$index] ?? '')])
                ->filter(fn ($row) => trim(strip_tags($row['html'])) !== '' || stripos($row['html'], '<img') !== false)->values();
            $correctPositions = collect($payload['correct_option_indices'] ?? $payload['correct_answers'] ?? [])
                ->map(fn ($value) => (int) $value)->filter(fn ($value) => $value >= 1 && $value <= 6)->unique()->sort()->values();
            $legacyCorrect = strtoupper(trim((string) ($payload['correct_answer'] ?? '')));
            if ($correctPositions->isEmpty() && preg_match('/^[A-F]$/', $legacyCorrect)) $correctPositions = collect([ord($legacyCorrect) - 64]);
            $answerLines = collect();
            if ($correctPositions->isNotEmpty()) $answerLines->push('Correct option: '.$correctPositions->map(fn ($value) => chr(64 + $value))->implode(', '));
            foreach (['answer' => 'Answer', 'true_false' => 'True/False', 'fill_blank' => 'Fill blank', 'si_answer1' => 'Subjective answer'] as $field => $label) {
                $value = $payload[$field] ?? null;
                if (is_scalar($value) && trim((string) $value) !== '') $answerLines->push($label.': '.trim(strip_tags((string) $value)));
            }
            if ($answerLines->isEmpty() && $legacyCorrect !== '' && !preg_match('/^[A-F]$/', $legacyCorrect)) $answerLines->push('Answer: '.strip_tags($legacyCorrect));
            $hint = (string) ($payload['hint'] ?? '');
            $explanation = (string) ($payload['explanation'] ?? '');
        @endphp
        <section class="question-block">
            <div class="question-heading">
                <h2>{{ $item['label'] }}</h2>
                <div style="display:flex;align-items:center;gap:8px">
                    <div class="status">{{ $item['type'] }} - {{ ucfirst(str_replace('_', ' ', $item['status'])) }}</div>
                    @if(!empty($item['edit_url']))<a class="button" style="min-height:32px;padding:5px 10px;font-size:12px" href="{{ $item['edit_url'] }}">Edit / crop</a>@endif
                </div>
            </div>
            <article class="question">{!! $payload['question'] ?? '<span style="color:#94a3b8">No question text</span>' !!}</article>
            @if($optionRows->isNotEmpty())
                <div class="options">@foreach($optionRows as $row)<div class="option"><span class="option-label">{{ chr(64 + $row['index']) }}</span><div>{!! $row['html'] !!}</div></div>@endforeach</div>
            @elseif(str_contains(strtolower((string) $item['type']), 'true'))
                <div class="options"><div class="option"><span class="option-label">A</span><div>True</div></div><div class="option"><span class="option-label">B</span><div>False</div></div></div>
            @endif
            @if($answerLines->isNotEmpty())<div class="review"><h3>Answer key</h3><ul>@foreach($answerLines as $line)<li>{{ $line }}</li>@endforeach</ul></div>@endif
            @if(trim(strip_tags($hint)) !== '' || stripos($hint, '<img') !== false)<div class="review"><h3>Hint</h3><div class="review-content">{!! $hint !!}</div></div>@endif
            @if(trim(strip_tags($explanation)) !== '' || stripos($explanation, '<img') !== false)<div class="review"><h3>Explanation</h3><div class="review-content">{!! $explanation !!}</div></div>@endif
        </section>
    @empty
        <div class="empty">No questions are available in this paper preview yet.</div>
    @endforelse
</main>
</body>
</html>