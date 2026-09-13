@if(request()->attributes->get('tech4learn_workspace_id') && session('tech4learn_workspace.kind')==='staff')
<nav class="card" aria-label="Exam workspace"><div class="card-body d-flex gap-3 flex-wrap">
<a href="https://tech4learn.com/">Tech4Learn</a>
<a href="/tech4learn/library">Shared library</a><a href="/subjects">Subjects</a><a href="/topics">Topics</a><a href="/stopics">Subtopics</a><a href="/sections">Sections</a><a href="/questions">Questions</a><a href="/exams">Exams</a><a href="/results">Results</a>
</div></nav>
@endif
