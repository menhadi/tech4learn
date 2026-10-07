<div class="row g-3">
<div class="col-md-4"><label class="form-label">Adapter name</label><input class="form-control" name="name" required value="{{ old('name',$profile->name ?? '') }}" placeholder="Example Question Bank"></div>
<div class="col-md-3"><label class="form-label">Adapter key</label><input class="form-control" name="key" required value="{{ old('key',$profile->key ?? '') }}" placeholder="example_bank"><div class="form-text">Lowercase letters, numbers, - and _.</div></div>
<div class="col-md-5"><label class="form-label">Supported URL pattern</label><input class="form-control" name="url_pattern" required value="{{ old('url_pattern',$profile->url_pattern ?? '') }}" placeholder="https://questions.example.com/*"><div class="form-text">Use * as a wildcard. One pattern per line is also supported.</div></div>
@foreach([
    'container'=>'Question container (optional)', 'question'=>'Question selector', 'options'=>'Options selector',
    'answer'=>'Answer selector', 'explanation'=>'Explanation selector', 'marks'=>'Marks selector',
    'negative_marks'=>'Negative marks selector', 'question_type'=>'Question type selector',
    'group'=>'Group selector', 'category'=>'Category selector', 'subcategory'=>'Subcategory selector',
    'package'=>'Package selector', 'exam'=>'Exam selector', 'subject'=>'Subject selector',
    'section'=>'Section selector', 'topic'=>'Topic selector', 'subtopic'=>'Subtopic selector',
    'difficulty_level'=>'Difficulty selector', 'language'=>'Language selector', 'images'=>'Additional images selector'
] as $key=>$label)
<div class="col-md-6"><label class="form-label">{{ $label }}</label><input class="form-control font-monospace" name="{{ $key }}_selector" value="{{ old($key.'_selector',$selectors[$key] ?? '') }}" @required($key==='question') placeholder="{{ $key==='question' ? '.question-text' : '.'.$key }}"></div>
@endforeach
<div class="col-12"><div class="form-check"><input type="hidden" name="enabled" value="0"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="adapter-enabled" @checked(old('enabled',$profile->enabled ?? true))><label class="form-check-label" for="adapter-enabled">Enabled for imports and audits</label></div></div>
</div>
@if($errors->any())<div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
