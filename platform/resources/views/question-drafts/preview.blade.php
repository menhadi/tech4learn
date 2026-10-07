<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $questionLabel }} - Rendered draft</title>
    <script>
        window.MathJax = {loader:{load:['[tex]/mhchem']},tex:{packages:{'[+]':['mhchem']},inlineMath:[['\\(','\\)'],['$','$']],displayMath:[['\\[','\\]'],['$$','$$']],processEscapes:true},options:{skipHtmlTags:['script','noscript','style','textarea','pre','code']}};
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js"></script>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #edf2f1; color: #172033; font-family: Arial, Helvetica, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 20px; background: #fff; border-bottom: 1px solid #d7e2df; }
        .toolbar-actions { display: flex; gap: 8px; }
        .button { display: inline-flex; align-items: center; min-height: 38px; padding: 8px 14px; border: 1px solid #0f766e; border-radius: 7px; color: #0f766e; background: #fff; font-weight: 700; text-decoration: none; cursor: pointer; }
        .button.primary { color: #fff; background: #0f766e; }
        .paper { width: min(210mm, calc(100% - 32px)); min-height: 270mm; margin: 24px auto; padding: 18mm 17mm; background: #fff; box-shadow: 0 8px 30px rgba(15, 42, 39, .12); }
        .paper-header { display: flex; justify-content: space-between; gap: 20px; padding-bottom: 12px; border-bottom: 2px solid #0f766e; }
        .eyebrow { color: #0f766e; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 5px 0 0; font-size: 22px; }
        .meta { color: #64748b; font-size: 13px; line-height: 1.5; text-align: right; }
        .notice { margin: 18px 0; padding: 10px 12px; border: 1px solid #f0c36b; border-radius: 6px; background: #fff8e6; color: #79520a; font-size: 13px; }
        .question { font-size: 17px; line-height: 1.65; overflow-wrap: anywhere; }
        .question img, .option img, .section-content img { display: block; max-width: 100%; height: auto; margin: 14px auto; object-fit: contain; }
        .options { margin-top: 18px; display: grid; gap: 10px; }
        .option { display: grid; grid-template-columns: 34px minmax(0, 1fr); gap: 10px; align-items: start; padding: 11px 13px; border: 1px solid #d8e1df; border-radius: 7px; font-size: 16px; line-height: 1.55; overflow-wrap: anywhere; }
        .option-label { display: inline-flex; justify-content: center; align-items: center; width: 28px; height: 28px; border-radius: 50%; background: #e7f3f1; color: #0f766e; font-weight: 700; }
        .section { margin-top: 24px; padding-top: 15px; border-top: 1px solid #d8e1df; }
        .section h2 { margin: 0 0 10px; color: #0f766e; font-size: 15px; text-transform: uppercase; letter-spacing: .04em; }
        .section-content { font-size: 15px; line-height: 1.6; overflow-wrap: anywhere; }
        .answer-list { margin: 0; padding-left: 20px; }
        mjx-container { max-width: 100%; overflow-x: auto; overflow-y: hidden; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { border: 1px solid #879b96; padding: 7px; text-align: left; }
        @page { size: A4; margin: 14mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .paper { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .notice { break-inside: avoid; }
            .option, .section { break-inside: avoid; }
        }
        @media (max-width: 640px) {
            .paper { width: 100%; min-height: 0; margin: 0; padding: 22px 16px; box-shadow: none; }
            .paper-header { display: block; }
            .meta { margin-top: 8px; text-align: left; }
        }
    </style>
</head>
<body>
@php
    $optionRows = collect(range(1, 6))->map(fn ($index) => [
        'index' => $index,
        'html' => (string) ($payload['option'.$index] ?? ''),
    ])->filter(fn ($row) => trim(strip_tags($row['html'])) !== '' || stripos($row['html'], '<img') !== false)->values();
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
    $blankAnswers = collect(data_get($payload, 'fill_blank_config.blanks', []))->map(function ($blank, $index) {
        $answers = collect((array) ($blank['answers'] ?? []))->filter()->implode(' / ');
        return $answers !== '' ? 'Blank '.($index + 1).': '.$answers : null;
    })->filter();
    $answerLines = $answerLines->concat($blankAnswers);
    $nat = is_array($payload['nat_config'] ?? null) ? $payload['nat_config'] : [];
    if ($nat !== []) {
        $mode = $nat['mode'] ?? 'exact';
        $natText = $mode === 'range' ? (($nat['min'] ?? '?').' to '.($nat['max'] ?? '?'))
            : (($nat['value'] ?? '?').($mode === 'tolerance' ? ' +/- '.($nat['tolerance'] ?? '?') : ''));
        $answerLines->push('Numerical answer: '.$natText);
    }
@endphp
<div class="toolbar">
    <div><strong>Rendered draft preview</strong><div style="font-size:12px;color:#64748b">This is the composed page, not separate editor fields.</div></div>
    <div class="toolbar-actions"><a class="button" href="{{ $backUrl }}">Back to draft</a><button class="button primary" type="button" onclick="window.print()">Print / Save PDF</button></div>
</div>
<main class="paper">
    <header class="paper-header">
        <div><div class="eyebrow">{{ $contextLabel }}</div><h1>{{ $title }}</h1></div>
        <div class="meta"><strong>{{ $questionLabel }}</strong><br>{{ $questionType }}<br>Status: {{ ucfirst(str_replace('_', ' ', $status)) }}</div>
    </header>
    <div class="notice">Preview only. Review the complete rendered question before publishing; this page does not change live content.</div>
    <article class="question">{!! $payload['question'] ?? '<span style="color:#94a3b8">No question text</span>' !!}</article>
    @if($optionRows->isNotEmpty())
        <section class="options">
            @foreach($optionRows as $row)<div class="option"><span class="option-label">{{ chr(64 + $row['index']) }}</span><div>{!! $row['html'] !!}</div></div>@endforeach
        </section>
    @elseif(str_contains(strtolower($questionType), 'true'))
        <section class="options"><div class="option"><span class="option-label">A</span><div>True</div></div><div class="option"><span class="option-label">B</span><div>False</div></div></section>
    @endif
    @if($answerLines->isNotEmpty())
        <section class="section"><h2>Answer key</h2><div class="section-content"><ul class="answer-list">@foreach($answerLines as $line)<li>{{ $line }}</li>@endforeach</ul></div></section>
    @endif
    @if(trim(strip_tags((string) ($payload['hint'] ?? ''))) !== '' || stripos((string) ($payload['hint'] ?? ''), '<img') !== false)
        <section class="section"><h2>Hint</h2><div class="section-content">{!! $payload['hint'] !!}</div></section>
    @endif
    @if(trim(strip_tags((string) ($payload['explanation'] ?? ''))) !== '' || stripos((string) ($payload['explanation'] ?? ''), '<img') !== false)
        <section class="section"><h2>Explanation</h2><div class="section-content">{!! $payload['explanation'] !!}</div></section>
    @endif
</main>
</body>
</html>