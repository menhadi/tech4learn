<?php

namespace App\Http\Controllers;

use App\Models\{Category,Exam,Group,NavigationItem,NavigationSetting,Package,WebsitePage};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class NavigationController extends Controller
{
    private function tenantId(): int { return (int) \App\Support\Tenant::id(); }

    public function index()
    {
        $tenant = $this->tenantId();
        $loadedItems = NavigationItem::where('organization_id',$tenant)->with('children')->orderBy('location')->orderBy('sort_order')->orderBy('id')->get();
        $items = collect(['header','secondary','footer'])->flatMap(function ($location) use ($loadedItems) {
            return $loadedItems->where('location',$location)->whereNull('parent_id')->flatMap(fn ($root) => collect([$root])->merge($loadedItems->where('parent_id',$root->id)));
        })->values();
        $destinations = collect([
            ['value'=>'mega_exams:','label'=>'Dynamic Explore Exams mega menu'], ['value'=>'packages:','label'=>'Explore Packages'],
            ['value'=>'all_exams:','label'=>'All Exams'], ['value'=>'quick_quiz:','label'=>'Quick Quiz'], ['value'=>'home:','label'=>'Homepage'], ['value'=>'custom:','label'=>'Custom URL'],
        ]);
        WebsitePage::where('organization_id',$tenant)->get()->each(fn($x)=>$destinations->push(['value'=>'page:'.$x->id,'label'=>'Page - '.$x->title]));
        Group::where('organization_id',$tenant)->displayOrdered()->get()->each(fn($x)=>$destinations->push(['value'=>'group:'.$x->id,'label'=>'Group - '.$x->group_name]));
        Category::where('organization_id',$tenant)->displayOrdered()->get()->each(fn($x)=>$destinations->push(['value'=>($x->parent_id?'subcategory:':'category:').$x->id,'label'=>($x->parent_id?'Subcategory - ':'Category - ').$x->title]));
        Package::where('organization_id',$tenant)->displayOrdered()->get()->each(fn($x)=>$destinations->push(['value'=>'package:'.$x->id,'label'=>'Package - '.$x->name]));
        Exam::where('organization_id',$tenant)->orderBy('name')->get()->each(fn($x)=>$destinations->push(['value'=>'exam:'.$x->id,'label'=>'Exam - '.$x->name]));
        $settings = NavigationSetting::firstOrNew(['organization_id' => $tenant]);
        $footerSections = $items->where('location','footer')->pluck('section_label')->filter()->unique()->values();
        $icons = [''=>'No icon','ri-home-4-line'=>'Home','ri-flashlight-line'=>'Quick Quiz','ri-compass-3-line'=>'Explore','ri-file-list-3-line'=>'Exams','ri-stack-line'=>'Packages','ri-book-open-line'=>'Book / Study','ri-graduation-cap-line'=>'Education','ri-user-line'=>'Student','ri-information-line'=>'Information','ri-customer-service-2-line'=>'Support','ri-link'=>'Link','ri-star-line'=>'Featured'];
        return view('navigation.index', compact('items','destinations','settings','icons','footerSections'));
    }

    public function initializeDefaults()
    {
        $tenant = $this->tenantId();
        $defaults = [
            ['header','Explore Exams','mega_exams',null,null,'ri-compass-3-line',1],
            ['header','Explore Packages','packages',null,null,'ri-stack-line',2],
            ['header','All Exams','all_exams',null,null,'ri-file-list-3-line',3],
            ['header','Quizzes','quick_quiz',null,null,'ri-flashlight-line',4],
            ['footer','Home','home','Company',null,'ri-home-4-line',1],
            ['footer','All Exams','all_exams','Explore',null,'ri-file-list-3-line',1],
            ['footer','Exam Packages','packages','Explore',null,'ri-stack-line',2],
            ['footer','Student Login','custom','Students','/student/signin','ri-user-line',1],
            ['footer','Student Dashboard','custom','Students','/student/dashboard','ri-graduation-cap-line',2],
            ['footer','Sitemap','custom','Company','/sitemap.xml','ri-link',2],
        ];
        foreach ($defaults as [$location,$label,$type,$section,$url,$icon,$order]) {
            NavigationItem::firstOrCreate(
                ['organization_id'=>$tenant,'location'=>$location,'link_type'=>$type,'custom_url'=>$url],
                ['label'=>$label,'section_label'=>$section,'icon'=>$icon,'sort_order'=>$order,'desktop_visible'=>true,'mobile_visible'=>true,'is_active'=>true]
            );
        }
        $student = NavigationItem::firstOrCreate(
            ['organization_id'=>$tenant,'location'=>'header','link_type'=>'custom','custom_url'=>'/student/signin','parent_id'=>null],
            ['label'=>'Student Area','icon'=>'ri-user-line','sort_order'=>5,'desktop_visible'=>true,'mobile_visible'=>true,'is_active'=>true]
        );
        NavigationItem::firstOrCreate(
            ['organization_id'=>$tenant,'location'=>'header','link_type'=>'custom','custom_url'=>'/student/dashboard'],
            ['parent_id'=>$student->id,'label'=>'Student Dashboard','icon'=>'ri-graduation-cap-line','sort_order'=>1,'desktop_visible'=>true,'mobile_visible'=>true,'is_active'=>true]
        );
        $this->seedSecondaryDefaults($tenant);
        $this->clear();
        return back()->with('success','Missing demo Header, Secondary, Footer, submenu, and Mega Menu items were added. Your existing items were kept.');
    }
    public function store(Request $request) { $this->saveItem($request, new NavigationItem(['organization_id'=>$this->tenantId()])); return back()->with('success','Navigation item added.'); }
    public function update(Request $request, NavigationItem $navigation) { $this->own($navigation); $this->saveItem($request,$navigation); return back()->with('success','Navigation item updated.'); }
    public function destroy(NavigationItem $navigation) { $this->own($navigation); $navigation->children()->where('organization_id',$this->tenantId())->update(['parent_id'=>null]); $navigation->delete(); $this->clear(); return back()->with('success','Navigation item removed.'); }
    public function reorder(Request $request)
    {
        $data = $request->validate(['location'=>['required',Rule::in(['header','secondary','footer'])],'items'=>'required|array','items.*.id'=>'required|integer','items.*.parent_id'=>'nullable|integer']);
        $tenant = $this->tenantId();
        $ids = collect($data['items'])->pluck('id')->map(fn($id)=>(int)$id);
        abort_unless(NavigationItem::where('organization_id',$tenant)->where('location',$data['location'])->whereIn('id',$ids)->count()===$ids->unique()->count(),422);
        $parents = collect($data['items'])->mapWithKeys(fn($row)=>[(int)$row['id'] => empty($row['parent_id']) ? null : (int)$row['parent_id']]);
        foreach ($data['items'] as $order=>$row) {
            $id=(int)$row['id']; $parent=$data['location']==='header' ? ($parents[$id] ?? null) : null;
            abort_if($parent===$id || ($parent && ! $ids->contains($parent)) || ($parent && ($parents[$parent] ?? null)),422);
            NavigationItem::where('organization_id',$tenant)->whereKey($id)->update(['parent_id'=>$parent,'sort_order'=>$order+1]);
        }
        $this->clear(); return response()->json(['success'=>true]);
    }

    public function toggle(Request $request)
    {
        $data = $request->validate(['location' => ['required', Rule::in(['header','secondary','footer'])]]);
        $settings = NavigationSetting::firstOrCreate(['organization_id' => $this->tenantId()]);
        $field = $data['location'].'_enabled';
        $settings->{$field} = ! $settings->{$field};
        $settings->save();
        $this->clear();
        return back()->with('success', ucfirst($data['location']).' custom menu '.($settings->{$field} ? 'activated.' : 'deactivated; the default menu is now live.'));
    }

    public function updateFooterHeadings(Request $request)
    {
        $data = $request->validate([
            'footer_brand_label' => 'nullable|string|max:100',
            'sections' => 'nullable|array', 'sections.*.old' => 'required|string|max:100', 'sections.*.new' => 'required|string|max:100',
        ]);
        NavigationSetting::updateOrCreate(
            ['organization_id'=>$this->tenantId()],
            ['footer_brand_label'=>blank($data['footer_brand_label'] ?? null) ? null : $data['footer_brand_label']]
        );
        foreach ($data['sections'] ?? [] as $section) {
            NavigationItem::where('organization_id',$this->tenantId())->where('location','footer')->where('section_label',$section['old'])->update(['section_label'=>$section['new']]);
        }
        $this->clear();
        return back()->with('success','Footer headings updated.');
    }
    private function saveItem(Request $request, NavigationItem $item): void
    {
        $data=$request->validate(['location'=>['required',Rule::in(['header','secondary','footer'])],'label'=>'nullable|string|max:100','section_label'=>'nullable|string|max:100','destination'=>'required|string','custom_url'=>'nullable|string|max:500','parent_id'=>'nullable|integer','icon'=>'nullable|string|max:100','target'=>['required',Rule::in(['_self','_blank'])],'style'=>['required',Rule::in(['link','button'])],'sort_order'=>'nullable|integer|min:0']);
        [$type,$id]=array_pad(explode(':',$data['destination'],2),2,null);
        abort_unless(in_array($type,['home','packages','all_exams','quick_quiz','page','group','category','subcategory','package','exam','mega_exams','custom'],true),422);
        if ($type==='custom' && ! preg_match('~^(https?://|/|mailto:|tel:)~i',(string)($data['custom_url']??''))) throw \Illuminate\Validation\ValidationException::withMessages(['custom_url'=>'Use an internal /path or an http(s), mailto, or tel URL.']);
        if ($data['location'] !== 'header') $data['parent_id'] = null;
        if (!empty($data['parent_id'])) {
            if ($item->exists && (int) $data['parent_id'] === (int) $item->id) throw \Illuminate\Validation\ValidationException::withMessages(['parent_id'=>'An item cannot be its own parent.']);
            abort_unless(NavigationItem::where('organization_id',$this->tenantId())->where('location','header')->whereKey($data['parent_id'])->exists(),422);
        }
        if (blank($data['label'])) $data['label'] = $this->destinationLabel($type, $id, $data['custom_url'] ?? null);
        $item->fill($data); $item->link_type=$type; $item->reference_id=$id?:null; $item->desktop_visible=$request->boolean('desktop_visible'); $item->mobile_visible=$request->boolean('mobile_visible'); $item->is_active=$request->boolean('is_active'); $item->organization_id=$this->tenantId(); $item->save(); $this->clear();
    }
    private function destinationLabel(string $type, $id, ?string $url): string
    {
        return match ($type) {
            'mega_exams' => 'Explore Exams', 'packages' => 'Explore Packages', 'all_exams' => 'All Exams', 'quick_quiz' => 'Quizzes', 'home' => 'Home',
            'page' => WebsitePage::where('organization_id',$this->tenantId())->find($id)?->title,
            'group' => Group::where('organization_id',$this->tenantId())->find($id)?->group_name,
            'category','subcategory' => Category::where('organization_id',$this->tenantId())->find($id)?->title,
            'package' => Package::where('organization_id',$this->tenantId())->find($id)?->name,
            'exam' => Exam::where('organization_id',$this->tenantId())->find($id)?->name,
            default => $url ?: 'Menu Link',
        } ?: 'Menu Link';
    }
    private function seedSecondaryDefaults(int $tenant): void
    {
        NavigationSetting::updateOrCreate(
            ['organization_id' => $tenant],
            ['secondary_enabled' => true]
        );

        $candidates = Category::where('organization_id', $tenant)
            ->active()->parents()->displayOrdered()->limit(5)->get()
            ->map(fn (Category $category) => [
                'type' => 'category',
                'id' => $category->id,
                'label' => $this->plainLabel($category->title),
            ]);

        if ($candidates->count() < 5) {
            $packages = Package::where('organization_id', $tenant)
                ->where('status', true)->displayOrdered()
                ->limit(5 - $candidates->count())->get()
                ->map(fn (Package $package) => [
                    'type' => 'package',
                    'id' => $package->id,
                    'label' => $this->plainLabel($package->name),
                ]);
            $candidates = $candidates->concat($packages);
        }

        foreach ($candidates->values() as $order => $candidate) {
            NavigationItem::firstOrCreate(
                [
                    'organization_id' => $tenant,
                    'location' => 'secondary',
                    'link_type' => $candidate['type'],
                    'reference_id' => $candidate['id'],
                ],
                [
                    'label' => $candidate['label'],
                    'target' => '_self',
                    'style' => 'link',
                    'desktop_visible' => true,
                    'mobile_visible' => true,
                    'is_active' => true,
                    'sort_order' => $order + 1,
                ]
            );
        }
    }
    private function plainLabel($value): string
    {
        if (is_array($value)) return (string) ($value['en'] ?? reset($value) ?: 'Menu Link');
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return (string) ($decoded['en'] ?? reset($decoded) ?: 'Menu Link');
            }
        }
        return (string) ($value ?: 'Menu Link');
    }
    private function own(NavigationItem $item): void { abort_unless((int)$item->organization_id===$this->tenantId(),404); }
    private function clear(): void { Cache::forget('website.navigation.'.$this->tenantId()); }
}

