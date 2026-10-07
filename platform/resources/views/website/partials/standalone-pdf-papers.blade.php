@if(isset($standalonePapers) && $standalonePapers->isNotEmpty())
<section class="container py-4" aria-label="Official papers">
    <h2 class="h4">Official papers</h2>
    <p class="text-muted">Download the original papers. Online attempts are available for papers with published questions.</p>
    <div class="row g-3">
        @foreach($standalonePapers as $paper)
        <div class="col-md-6 col-xl-4">
            <article class="card h-100"><div class="card-body">
                <h3 class="h6"><a href="{{ route('exam.detail', $paper->slug ?: $paper->id) }}">{{ $paper->name }}</a></h3>
                @if($paper->category)<a class="small" href="{{ route('website.exams.index', ['group' => 'all', 'category' => $paper->category->slug]) }}">{{ $paper->category->title }}</a>@endif
                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-primary downloadPdfBtn" data-pdf-intent-url="{{ route('exam.print.intent', ['id' => $paper->slug ?: $paper->id]) }}" data-exam-id="{{ $paper->slug ?: $paper->id }}" data-exam-name="{{ $paper->name }}" data-pdf-languages='[]'>Download PDF</button>
                    @if($paper->canAttemptOnline())<a class="btn btn-primary" href="{{ route('student.instructions', ['id' => $paper->id]) }}">Attempt</a>@else<span class="badge bg-light text-dark align-self-center">PDF only</span>@endif
                </div>
            </div></article>
        </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $standalonePapers->links('pagination::bootstrap-5') }}</div>
</section>
@endif
