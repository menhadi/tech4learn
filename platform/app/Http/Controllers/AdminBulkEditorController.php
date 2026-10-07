<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Diff;
use App\Models\Group;
use App\Models\Language;
use App\Models\Package;
use App\Models\Question;
use App\Models\Qtype;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use App\Support\SaasAccess;
use App\Services\CurriculumTaxonomyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class AdminBulkEditorController extends Controller
{
    public function index(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $query = $this->ownedQuery($resource, $config['model']);
        $filters = $this->filters($request, $resource);
        $this->applyFilters($query, $resource, $filters);
        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($config, $search) {
                foreach ($config['search'] as $index => $column) {
                    $index === 0 ? $builder->where($column, 'like', "%{$search}%") : $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }
        return view('admin.bulk-editor', [
            'resource' => $resource,
            'config' => $config,
            'records' => $query->orderBy($config['order'][0], $config['order'][1])->paginate(50)->withQueryString(),
            'options' => $this->options($resource),
            'search' => $search,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($filters),
        ]);
    }

    public function update(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $validated = $request->validate([
            'selected' => ['required', 'array', 'min:1', 'max:500'], 'selected.*' => ['integer'],
            'rows' => ['nullable', 'array'], 'bulk' => ['nullable', 'array'],
            'name_prefix' => ['nullable', 'string', 'max:100'], 'name_suffix' => ['nullable', 'string', 'max:100'],
        ]);
        $selected = array_values(array_unique(array_map('intval', $validated['selected'])));
        $records = $this->ownedQuery($resource, $config['model'])->whereKey($selected)->get()->keyBy('id');
        abort_if($records->count() !== count($selected), 403, 'One or more selected records are unavailable.');
        $allowed = array_keys($config['fields']);
        $bulk = Arr::only($validated['bulk'] ?? [], $allowed);
        $rows = $validated['rows'] ?? [];

        foreach ($records as $record) {
            $changes = [];
            foreach ($bulk as $field => $value) if ($value !== null && $value !== '') $changes[$field] = $value;
            foreach (Arr::only($rows[$record->id] ?? [], $allowed) as $field => $value) if ($value !== null && $value !== '') $changes[$field] = $value;
            $prefix = (string) ($validated['name_prefix'] ?? '');
            $suffix = (string) ($validated['name_suffix'] ?? '');
            if ($prefix !== '' || $suffix !== '') {
                $nameField = $config['name_field'];
                $current = $record->getAttribute($nameField);
                if (is_array($current)) {
                    $locale = app()->getLocale();
                    $current[$locale] = $prefix.($current[$locale] ?? reset($current) ?: '').$suffix;
                    $changes[$nameField] = $current;
                } else $changes[$nameField] = $prefix.(string) $current.$suffix;
            }
            if ($changes !== []) {
                if ($resource === 'questions' && array_key_exists('correct_option_indices', $changes)) {
                    $changes['correct_option_indices'] = $this->parseOptionIndices($changes['correct_option_indices']);
                }
                validator($changes, $this->rules($resource))->validate();
                $this->validateRelationships($changes);
                $this->validateTaxonomyChange($resource, $record, $changes);
                $record->fill($changes)->save();
                if ($resource === 'questions') {
                    app(CurriculumTaxonomyService::class)->syncQuestion($record->fresh(['topic', 'stopic']), $record->groups()->pluck('groups.id')->all());
                }
            }
        }
        return back()->with('success', $records->count().' '.strtolower($config['title']).' updated successfully.');
    }

    private function config(string $resource): array
    {
        $seo = [];
        $configs = [
            'subjects' => ['title'=>'Subjects','model'=>Subject::class,'name_field'=>'subject_name','search'=>['subject_name'],'order'=>['subject_name','asc'],'filters'=>['group'],'fields'=>[
                'subject_name'=>['label'=>'Subject name','type'=>'text'],'ordering'=>['label'=>'Display order','type'=>'number'],
            ]],
            'topics' => ['title'=>'Topics','model'=>Topic::class,'name_field'=>'name','search'=>['name'],'order'=>['name','asc'],'filters'=>['group','subject'],'fields'=>[
                'name'=>['label'=>'Topic name','type'=>'text'],'group_id'=>['label'=>'Group','type'=>'select','options'=>'groups'],'subject_id'=>['label'=>'Subject','type'=>'select','options'=>'subjects'],'display_order'=>['label'=>'Display order','type'=>'number'],
            ]],
            'subtopics' => ['title'=>'Subtopics','model'=>Stopic::class,'name_field'=>'name','search'=>['name'],'order'=>['name','asc'],'filters'=>['group','subject','topic'],'fields'=>[
                'name'=>['label'=>'Subtopic name','type'=>'text'],'group_id'=>['label'=>'Group','type'=>'select','options'=>'groups'],'subject_id'=>['label'=>'Subject','type'=>'select','options'=>'subjects'],'topic_id'=>['label'=>'Topic','type'=>'select','options'=>'topics'],'display_order'=>['label'=>'Display order','type'=>'number'],
            ]],
            'questions' => ['title'=>'Questions','model'=>Question::class,'name_field'=>'question_code','search'=>['question_code','question'],'order'=>['id','desc'],'filters'=>['group','category','subcategory','package','subject','topic','subtopic'],'fields'=>[
                'question_code'=>['label'=>'Question code','type'=>'text'],'source_url'=>['label'=>'Source URL','type'=>'url'],'source_reference'=>['label'=>'Source reference','type'=>'text'],'ai_generated'=>['label'=>'AI generated','type'=>'select','options'=>'yes_no'],'original_question_id'=>['label'=>'Original question ID','type'=>'number'],'question_section_id'=>['label'=>'Question section ID','type'=>'number'],'passage_id'=>['label'=>'Passage ID','type'=>'number'],
                'qtype_id'=>['label'=>'Type','type'=>'select','options'=>'qtypes'],'subject_id'=>['label'=>'Subject','type'=>'select','options'=>'subjects'],'topic_id'=>['label'=>'Topic','type'=>'select','options'=>'topics'],'stopic_id'=>['label'=>'Subtopic','type'=>'select','options'=>'subtopics'],'diff_id'=>['label'=>'Difficulty','type'=>'select','options'=>'difficulties'],'language_id'=>['label'=>'Language','type'=>'select','options'=>'languages'],
                'question'=>['label'=>'Question','type'=>'textarea'],'option1'=>['label'=>'Option 1','type'=>'textarea'],'option2'=>['label'=>'Option 2','type'=>'textarea'],'option3'=>['label'=>'Option 3','type'=>'textarea'],'option4'=>['label'=>'Option 4','type'=>'textarea'],'option5'=>['label'=>'Option 5','type'=>'textarea'],'option6'=>['label'=>'Option 6','type'=>'textarea'],
                'correct_option_indices'=>['label'=>'Correct option positions (e.g. 2 or 1,3)','type'=>'option_indices'],
                'si_answer1'=>['label'=>'Single/NAT answer','type'=>'text'],'answer'=>['label'=>'Legacy answer','type'=>'text'],'true_false'=>['label'=>'True/false answer','type'=>'text'],'fill_blank'=>['label'=>'Fill blank answer','type'=>'text'],'fill_blank_config'=>['label'=>'Fill blank configuration','type'=>'textarea'],'nat_config'=>['label'=>'NAT configuration','type'=>'textarea'],'marks'=>['label'=>'Marks','type'=>'number','step'=>'0.01'],'negative_marks'=>['label'=>'Negative marks','type'=>'number','step'=>'0.01'],'scoring_policy'=>['label'=>'Scoring policy','type'=>'select','options'=>'scoring_policies'],'hint'=>['label'=>'Hint','type'=>'textarea'],'explanation'=>['label'=>'Explanation','type'=>'textarea'],'status'=>['label'=>'Status','type'=>'select','options'=>'question_statuses'],
            ]],
            'groups' => ['title'=>'Groups','model'=>Group::class,'name_field'=>'group_name','search'=>['group_name'],'order'=>['display_order','asc'],'filters'=>[],'fields'=>array_merge([
                'group_name'=>['label'=>'Group name','type'=>'text'],'slug'=>['label'=>'Slug','type'=>'text'],'description'=>['label'=>'Description','type'=>'textarea'],'display_order'=>['label'=>'Display order','type'=>'number'],
            ],$seo)],
            'categories' => ['title'=>'Categories','model'=>Category::class,'name_field'=>'title','search'=>['title'],'order'=>['display_order','asc'],'filters'=>['group'],'fields'=>array_merge([
                'title'=>['label'=>'Category title','type'=>'text'],'description'=>['label'=>'Description','type'=>'textarea'],'slug'=>['label'=>'Slug','type'=>'text'],'display_order'=>['label'=>'Display order','type'=>'number'],'status'=>['label'=>'Status','type'=>'select','options'=>'boolean_statuses'],'show_in_header'=>['label'=>'Show in header','type'=>'select','options'=>'yes_no'],'header_display_order'=>['label'=>'Header order','type'=>'number'],
            ],$seo)],
            'subcategories' => ['title'=>'Subcategories','model'=>Category::class,'name_field'=>'title','search'=>['title'],'order'=>['display_order','asc'],'filters'=>['group','category'],'fields'=>array_merge([
                'title'=>['label'=>'Subcategory title','type'=>'text'],'description'=>['label'=>'Description','type'=>'textarea'],'slug'=>['label'=>'Slug','type'=>'text'],'parent_id'=>['label'=>'Parent category','type'=>'select','options'=>'categories'],'display_order'=>['label'=>'Display order','type'=>'number'],'status'=>['label'=>'Status','type'=>'select','options'=>'boolean_statuses'],'show_in_header'=>['label'=>'Show in header','type'=>'select','options'=>'yes_no'],'header_display_order'=>['label'=>'Header order','type'=>'number'],
            ],$seo)],
            'packages' => ['title'=>'Packages','model'=>Package::class,'name_field'=>'name','search'=>['name','description'],'order'=>['id','desc'],'filters'=>['group','category','subcategory'],'fields'=>array_merge([
                'name'=>['label'=>'Package name','type'=>'text'],'slug'=>['label'=>'Slug','type'=>'text'],'slug2'=>['label'=>'Secondary slug','type'=>'text'],'description'=>['label'=>'Description','type'=>'textarea'],'amount'=>['label'=>'Price','type'=>'number','step'=>'0.01'],'discounted_amount'=>['label'=>'Discounted price','type'=>'number','step'=>'0.01'],'package_type'=>['label'=>'Package type','type'=>'text'],'expiry_days'=>['label'=>'Validity days','type'=>'number'],'display_order'=>['label'=>'Display order','type'=>'number'],'category_level_1'=>['label'=>'Category','type'=>'select','options'=>'categories'],'category_level_2'=>['label'=>'Subcategory','type'=>'select','options'=>'subcategories'],'status'=>['label'=>'Status','type'=>'select','options'=>'boolean_statuses'],'auto_enroll_on_registration'=>['label'=>'Auto enroll','type'=>'select','options'=>'yes_no'],'show_pdf_download'=>['label'=>'Paper PDF','type'=>'select','options'=>'yes_no'],'show_solution_pdf_download'=>['label'=>'Solution PDF','type'=>'select','options'=>'yes_no'],'flashcards_enabled'=>['label'=>'Flashcards','type'=>'select','options'=>'yes_no'],'guest_flashcards_enabled'=>['label'=>'Guest flashcards','type'=>'select','options'=>'yes_no'],'ai_flashcard_generation_enabled'=>['label'=>'AI flashcards','type'=>'select','options'=>'yes_no'],
                'photo'=>['label'=>'Package image','type'=>'text'],'pdf_title_text'=>['label'=>'PDF title','type'=>'text'],'pdf_header_text'=>['label'=>'PDF header','type'=>'text'],'pdf_footer_text'=>['label'=>'PDF footer','type'=>'text'],'pdf_watermark_text'=>['label'=>'PDF watermark','type'=>'text'],'solution_pdf_title_text'=>['label'=>'Solution PDF title','type'=>'text'],'solution_pdf_header_text'=>['label'=>'Solution PDF header','type'=>'text'],'solution_pdf_footer_text'=>['label'=>'Solution PDF footer','type'=>'text'],'solution_pdf_watermark_text'=>['label'=>'Solution PDF watermark','type'=>'text'],
            ],$seo)],
        ];
        abort_unless(isset($configs[$resource]), 404);
        return $configs[$resource];
    }
    private function filters(Request $request, string $resource): array
    {
        $available = $this->config($resource)['filters'] ?? [];
        $filters = [];
        foreach ($available as $filter) {
            $filters[$filter] = $request->integer($filter.'_id') ?: null;
        }
        return $filters;
    }

    private function applyFilters(Builder $query, string $resource, array $filters): void
    {
        $group = $filters['group'] ?? null;
        $category = $filters['category'] ?? null;
        $subcategory = $filters['subcategory'] ?? null;
        $package = $filters['package'] ?? null;
        $subject = $filters['subject'] ?? null;
        $topic = $filters['topic'] ?? null;
        $subtopic = $filters['subtopic'] ?? null;

        match ($resource) {
            'subjects' => $query->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group))),
            'topics' => $query->when($group, fn ($q) => $q->where('group_id', $group))->when($subject, fn ($q) => $q->where('subject_id', $subject)),
            'subtopics' => $query->when($group, fn ($q) => $q->where('group_id', $group))->when($subject, fn ($q) => $q->where('subject_id', $subject))->when($topic, fn ($q) => $q->where('topic_id', $topic)),
            'questions' => $query->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))->when($category, fn ($q) => $q->whereHas('exams.packages', fn ($p) => $p->where('category_level_1', $category)))->when($subcategory, fn ($q) => $q->whereHas('exams.packages', fn ($p) => $p->where('category_level_2', $subcategory)))->when($subject, fn ($q) => $q->where('subject_id', $subject))->when($topic, fn ($q) => $q->where('topic_id', $topic))->when($subtopic, fn ($q) => $q->where('stopic_id', $subtopic))->when($package, fn ($q) => $q->whereHas('exams.packages', fn ($p) => $p->whereKey($package))),
            'categories' => $query->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group))),
            'subcategories' => $query->when($group, fn ($q) => $q->whereHas('parent.groups', fn ($g) => $g->whereKey($group)))->when($category, fn ($q) => $q->where('parent_id', $category)),
            'packages' => $query->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))->when($category, fn ($q) => $q->where('category_level_1', $category))->when($subcategory, fn ($q) => $q->where('category_level_2', $subcategory)),
            default => null,
        };
    }

    private function filterOptions(array $filters): array
    {
        $org = SaasAccess::organization()?->id;
        $group = $filters['group'] ?? null;
        $category = $filters['category'] ?? null;
        $subcategory = $filters['subcategory'] ?? null;
        $subject = $filters['subject'] ?? null;
        $topic = $filters['topic'] ?? null;

        $categories = Category::where('organization_id', $org)->whereNull('parent_id')
            ->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))
            ->orderBy('title')->pluck('title', 'id')->all();
        $subcategories = Category::where('organization_id', $org)->whereNotNull('parent_id')
            ->when($group, fn ($q) => $q->whereHas('parent.groups', fn ($g) => $g->whereKey($group)))
            ->when($category, fn ($q) => $q->where('parent_id', $category))
            ->orderBy('title')->pluck('title', 'id')->all();
        $packages = Package::where('organization_id', $org)
            ->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))
            ->when($category, fn ($q) => $q->where('category_level_1', $category))
            ->when($subcategory, fn ($q) => $q->where('category_level_2', $subcategory))
            ->orderBy('name')->get()->mapWithKeys(fn ($package) => [$package->id => is_array($package->name) ? ($package->name[app()->getLocale()] ?? reset($package->name)) : $package->name])->all();
        $subjects = Subject::whereHas('groups', fn (Builder $q) => $q->where('groups.organization_id', $org)->when($group, fn ($g) => $g->whereKey($group)))
            ->orderBy('subject_name')->pluck('subject_name', 'id')->all();
        $topics = Topic::whereHas('subject.groups', fn (Builder $q) => $q->where('groups.organization_id', $org)->when($group, fn ($g) => $g->whereKey($group)))
            ->when($subject, fn ($q) => $q->where('subject_id', $subject))->orderBy('name')->pluck('name', 'id')->all();
        $subtopics = Stopic::whereHas('subject.groups', fn (Builder $q) => $q->where('groups.organization_id', $org)->when($group, fn ($g) => $g->whereKey($group)))
            ->when($subject, fn ($q) => $q->where('subject_id', $subject))->when($topic, fn ($q) => $q->where('topic_id', $topic))
            ->orderBy('name')->pluck('name', 'id')->all();

        return [
            'group' => $this->optionSet('groups'),
            'category' => $categories,
            'subcategory' => $subcategories,
            'package' => $packages,
            'subject' => $subjects,
            'topic' => $topics,
            'subtopic' => $subtopics,
        ];
    }
    private function ownedQuery(string $resource, string $model): Builder
    {
        $org = SaasAccess::organization()?->id; abort_unless($org, 403); $query = $model::query();
        return match ($resource) {
            'subjects' => $query->where('organization_id', $org),
            'topics','subtopics' => $query->whereHas('group', fn(Builder $q) => $q->where('organization_id', $org)),
            'categories' => $query->where('organization_id',$org)->whereNull('parent_id'),
            'subcategories' => $query->where('organization_id',$org)->whereNotNull('parent_id'),
            default => $query->where('organization_id',$org),
        };
    }

    private function options(string $resource): array
    {
        $sets=[]; foreach ($this->config($resource)['fields'] as $field) if(isset($field['options'])) $sets[$field['options']]=$this->optionSet($field['options']); return $sets;
    }

    private function optionSet(string $name): array
    {
        $org=SaasAccess::organization()?->id;
        return match($name) {
            'groups'=>Group::where('organization_id',$org)->orderBy('group_name')->pluck('group_name','id')->all(),
            'packages'=>Package::where('organization_id',$org)->orderBy('name')->get()->mapWithKeys(fn($p)=>[$p->id => is_array($p->name) ? ($p->name[app()->getLocale()] ?? reset($p->name)) : $p->name])->all(),
            'subjects'=>Subject::where('organization_id', $org)->orderBy('subject_name')->pluck('subject_name','id')->all(),
            'topics'=>Topic::whereHas('subject.groups',fn(Builder $q)=>$q->where('groups.organization_id',$org))->orderBy('name')->pluck('name','id')->all(),
            'subtopics'=>Stopic::whereHas('subject.groups',fn(Builder $q)=>$q->where('groups.organization_id',$org))->orderBy('name')->pluck('name','id')->all(),
            'categories'=>Category::where('organization_id',$org)->whereNull('parent_id')->orderBy('title')->pluck('title','id')->all(),
            'subcategories'=>Category::where('organization_id',$org)->whereNotNull('parent_id')->orderBy('title')->pluck('title','id')->all(),
            'qtypes'=>Qtype::orderBy('question_type')->pluck('question_type','id')->all(),
            'difficulties'=>Diff::orderBy('diff_level')->pluck('diff_level','id')->all(),
            'languages'=>Language::forOrganization($org)->orderBy('name')->pluck('name','id')->all(),
            'scoring_policies'=>['NORMAL'=>'Normal evaluation','MTA'=>'Marks to all (MTA)'],'boolean_statuses'=>[1=>'Active',0=>'Inactive'],'question_statuses'=>['Active'=>'Active','Inactive'=>'Inactive'],'yes_no'=>[1=>'Yes',0=>'No'],default=>[],
        };
    }

    private function rules(string $resource): array
    {
        $rules = [];
        foreach ($this->config($resource)['fields'] as $field => $definition) {
            $base = ['sometimes', 'nullable'];
            $rules[$field] = match ($definition['type']) {
                'number' => array_merge($base, ['numeric']),
                'url' => array_merge($base, ['url', 'max:2048']),
                'textarea' => array_merge($base, ['string']),
                'select' => array_merge($base, ['string']),
                'option_indices' => array_merge($base, ['array']),
                default => array_merge($base, ['string', 'max:10000']),
            };
        }
        return $rules;
    }
    private function parseOptionIndices($value): array
    {
        return collect(preg_split('/[,;|\s]+/', strtoupper(trim((string) $value)), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($index) => preg_match('/^[A-F]$/', $index) ? ord($index) - 64 : (int) $index)
            ->filter(fn ($index) => $index >= 1 && $index <= 6)
            ->unique()->sort()->values()->all();
    }

    private function validateTaxonomyChange(string $resource, $record, array $changes): void
    {
        $org = (int) SaasAccess::organization()?->id;
        if ($resource === 'questions') {
            app(CurriculumTaxonomyService::class)->validateSelection(
                $org,
                $record->groups()->pluck('groups.id')->all(),
                isset($changes['subject_id']) ? (int) $changes['subject_id'] : $record->subject_id,
                isset($changes['topic_id']) ? (int) $changes['topic_id'] : $record->topic_id,
                isset($changes['stopic_id']) ? (int) $changes['stopic_id'] : $record->stopic_id,
            );
            return;
        }
        if (! in_array($resource, ['topics', 'subtopics'], true)) {
            return;
        }

        $groupId = (int) ($changes['group_id'] ?? $record->group_id);
        $subjectId = (int) ($changes['subject_id'] ?? $record->subject_id);
        $subject = Subject::where('organization_id', $org)->find($subjectId);
        abort_unless($subject && $subject->groups()->whereKey($groupId)->exists(), 422, 'The subject is not available in the selected group.');

        if ($resource === 'subtopics') {
            $topicId = (int) ($changes['topic_id'] ?? $record->topic_id);
            abort_unless(Topic::whereKey($topicId)->where('subject_id', $subjectId)->where('group_id', $groupId)->exists(), 422, 'The topic is outside the selected group/subject context.');
        }
    }
    private function validateRelationships(array $changes): void
    {
        $map=['subject_id'=>'subjects','topic_id'=>'topics','stopic_id'=>'subtopics','parent_id'=>'categories','category_level_1'=>'categories','category_level_2'=>'subcategories','qtype_id'=>'qtypes','diff_id'=>'difficulties','language_id'=>'languages'];
        foreach($map as $field=>$set) if(isset($changes[$field])&&!array_key_exists((int)$changes[$field],$this->optionSet($set))) abort(422,"The selected {$field} is unavailable.");
    }
}
