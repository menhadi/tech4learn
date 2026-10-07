<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationItem extends Model
{
    protected $fillable = ['organization_id','parent_id','location','section_label','label','link_type','reference_id','custom_url','icon','target','style','desktop_visible','mobile_visible','is_active','sort_order'];
    protected $casts = ['desktop_visible'=>'boolean','mobile_visible'=>'boolean','is_active'=>'boolean'];

    public function children() { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id'); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }

    public function resolvedUrl(): string
    {
        return match ($this->link_type) {
            'home' => url('/'), 'packages' => route('courses.index'), 'all_exams' => route('website.exams.index'),
            'page' => ($page = WebsitePage::where('organization_id',$this->organization_id)->find($this->reference_id)) ? route('page.show', \Illuminate\Support\Str::slug($page->short_title)) : '#',
            'group' => ($item = Group::where('organization_id',$this->organization_id)->find($this->reference_id)) ? route('website.exams.index', ['group' => $item->slug ?: \Illuminate\Support\Str::slug($item->group_name)]) : '#',
            'category' => ($item = Category::where('organization_id',$this->organization_id)->find($this->reference_id)) ? url('/exam-groups/all/'.$item->slug) : '#',
            'subcategory' => ($item = Category::with('parent')->where('organization_id',$this->organization_id)->find($this->reference_id)) ? url('/exam-groups/all/'.$item->parent?->slug.'/'.$item->slug) : '#',
            'package' => ($item = Package::where('organization_id',$this->organization_id)->find($this->reference_id)) ? route('courses.detail', $item->slug ?: $item->id) : '#',
            'exam' => ($item = Exam::where('organization_id',$this->organization_id)->find($this->reference_id)) ? route('exam.detail', $item->slug ?: $item->id) : '#',
            'quick_quiz' => auth('student')->check() && \Illuminate\Support\Facades\Route::has('student.quick-quizzes')
                ? route('student.quick-quizzes')
                : route('home', ['open_quick_quiz' => 1]),
            'mega_exams' => '#', default => $this->custom_url ?: '#',
        };
    }
}
