<?php
namespace App\Http\Controllers;
use App\Models\Category;
use App\Models\Exam;
use App\Models\FlashcardSet;
use App\Models\Group;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
class CategoryController extends Controller
{
 private function tenantId():int{return (int)\App\Support\Tenant::id();}
 private function ensureTenantOwns(Category $category):void{if((int)$category->organization_id!==$this->tenantId())abort(404);}
 public function index(Request $request){return $this->listing($request,false);}
 public function subcategories(Request $request){$this->ensureSubcategoriesEnabled();return $this->listing($request,true);}
 private function listing(Request $request,bool $subcategories){
  $query=Category::with(['parent','groups'])->where('organization_id',$this->tenantId())->when($subcategories,fn($q)=>$q->whereNotNull('parent_id'),fn($q)=>$q->whereNull('parent_id'));
  if($request->filled('search')){$search=$request->search;$query->where(fn($q)=>$q->where('title','like','%'.$search.'%')->orWhere('slug','like','%'.$search.'%'));}
  $perPage=(int)$request->input('per_page',50);$perPage=in_array($perPage,[50,100,500],true)?$perPage:50;
  $categories=$query->displayOrdered()->paginate($perPage)->withQueryString();
  $parentCategories=Category::where('organization_id',$this->tenantId())->whereNull('parent_id')->displayOrdered()->get();
  $groups=Group::where('organization_id',$this->tenantId())->displayOrdered()->get();
  return view('category.index',compact('categories','parentCategories','groups','perPage','subcategories'));
 }
 public function store(Request $request){return $this->storeRecord($request,false);}
 public function storeSubcategory(Request $request){$this->ensureSubcategoriesEnabled();return $this->storeRecord($request,true);}
 private function storeRecord(Request $request,bool $subcategory){
  try{$validated=$this->validated($request,null,$subcategory);$validated['organization_id']=$this->tenantId();$validated['parent_id']=$subcategory?(int)$validated['parent_id']:null;$validated['slug']=$this->uniqueSlug($validated['title']);$category=Category::create($validated);if(!$subcategory)$this->syncCategoryGroups($category,$request);return redirect()->route($subcategory?'subcategories.index':'category.index')->with('success',($subcategory?'Subcategory':'Category').' created successfully.');}
  catch(ValidationException $e){return back()->withErrors($e->validator)->withInput();}catch(\Throwable $e){return back()->with('error','Failed to create '.($subcategory?'subcategory.':'category.'));}
 }
 public function update(Request $request,Category $category){
  $this->ensureTenantOwns($category);$subcategory=$request->routeIs('subcategories.*')||$category->parent_id!==null;if($subcategory)$this->ensureSubcategoriesEnabled();
  try{$validated=$this->validated($request,$category,$subcategory);$validated['parent_id']=$subcategory?(int)$validated['parent_id']:null;$validated['slug']=$this->uniqueSlug($validated['title'],$category->id);$category->update($validated);if(!$subcategory)$this->syncCategoryGroups($category,$request);return redirect()->route($subcategory?'subcategories.index':'category.index')->with('success',($subcategory?'Subcategory':'Category').' updated successfully.');}
  catch(ValidationException $e){return back()->withErrors($e->validator)->withInput();}catch(\Throwable $e){return back()->with('error','Failed to update '.($subcategory?'subcategory.':'category.'));}
 }
 public function destroy(Category $category){
  $this->ensureTenantOwns($category);$subcategory=$category->parent_id!==null;if($subcategory)$this->ensureSubcategoriesEnabled();$used=Category::where('parent_id',$category->id)->exists()||Exam::where('category_level_1',$category->id)->orWhere('category_level_2',$category->id)->exists()||Package::where('category_level_1',$category->id)->orWhere('category_level_2',$category->id)->exists()||FlashcardSet::where('category_level_1',$category->id)->orWhere('category_level_2',$category->id)->exists();
  if($used)return redirect()->route($subcategory?'subcategories.index':'category.index')->with('error','This '.($subcategory?'subcategory':'category').' is in use and cannot be deleted.');
  $category->groups()->detach();$category->delete();return redirect()->route($subcategory?'subcategories.index':'category.index')->with('success',($subcategory?'Subcategory':'Category').' deleted successfully.');
 }
 private function ensureSubcategoriesEnabled():void{if(!subcategories_enabled())abort(404);}
 private function validated(Request $request,?Category $category,bool $subcategory):array{
  $tenant=$this->tenantId();$rules=['title'=>['required','string','max:255',Rule::unique('category','title')->where(fn($q)=>$q->where('organization_id',$tenant))->ignore($category?->id)],'parent_id'=>$subcategory?['required',Rule::exists('category','id')->where(fn($q)=>$q->where('organization_id',$tenant)->whereNull('parent_id'))]:['nullable'],'description'=>['nullable','string'],'status'=>['required','boolean'],'show_in_header'=>['nullable','boolean'],'header_display_order'=>['nullable','integer','min:0'],'display_order'=>['nullable','integer','min:0'],'meta_title'=>['nullable','string','max:191'],'meta_description'=>['nullable','string'],'meta_keywords'=>['nullable','string'],'canonical_url'=>['nullable','string','max:255'],'og_title'=>['nullable','string','max:191'],'og_description'=>['nullable','string'],'og_image'=>['nullable','string','max:255'],'robots_meta'=>['nullable','string','max:50'],'seo_schema'=>['nullable','string'],'group_ids'=>[$subcategory?'nullable':'nullable','array'],'group_ids.*'=>[Rule::exists('groups','id')->where(fn($q)=>$q->where('organization_id',$tenant))]];
  $data=$request->validate($rules);$data['show_in_header']=$subcategory?false:$request->boolean('show_in_header');$data['header_display_order']=$subcategory?null:$request->input('header_display_order');unset($data['group_ids']);return $data;
 }
 private function syncCategoryGroups(Category $category,Request $request):void{$request->validate(['group_ids'=>['nullable','array'],'group_ids.*'=>[Rule::exists('groups','id')->where(fn($q)=>$q->where('organization_id',$this->tenantId()))],'group_orders'=>['nullable','array'],'group_orders.*'=>['nullable','integer','min:0']]);$orders=(array)$request->input('group_orders',[]);$sync=[];foreach(array_map('intval',$request->input('group_ids',[])) as $groupId){$value=$orders[$groupId]??null;$sync[$groupId]=['display_order'=>filled($value)?(int)$value:null];}$category->groups()->sync($sync);}
 private function uniqueSlug(string $title,?int $ignore=null):string{$base=Str::slug($title)?:'category';$slug=$base;$n=2;while(Category::where('organization_id',$this->tenantId())->where('slug',$slug)->when($ignore,fn($q)=>$q->where('id','!=',$ignore))->exists())$slug=$base.'-'.$n++;return $slug;}
}