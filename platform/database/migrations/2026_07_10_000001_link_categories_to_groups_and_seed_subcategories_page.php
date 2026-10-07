<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  foreach (['packages', 'exams'] as $tableName) {
   Schema::table($tableName, function (Blueprint $table) use ($tableName) {
    if (! Schema::hasColumn($tableName, 'category_level_1')) $table->unsignedBigInteger('category_level_1')->nullable()->index();
    if (! Schema::hasColumn($tableName, 'category_level_2')) $table->unsignedBigInteger('category_level_2')->nullable()->index();
   });
  }

  // Category is a legacy table whose id type differs between installations.
  // Keep category_id indexed and enforce integrity in application logic.
  Schema::dropIfExists('category_groups');
  Schema::create('category_groups', function(Blueprint $t){$t->id();$t->unsignedBigInteger('category_id')->index();$t->foreignId('group_id')->constrained('groups')->cascadeOnDelete();$t->timestamps();$t->unique(['category_id','group_id']);});
  $now=now(); $links=collect(); $validCategoryIds=DB::table('category')->pluck('id')->map(fn($id)=>(int)$id);
  if(Schema::hasTable('packages')&&Schema::hasTable('package_groups')) $links=$links->merge(DB::table('packages')->join('package_groups','packages.id','=','package_groups.package_id')->select('packages.category_level_1','packages.category_level_2','package_groups.group_id')->get());
  if(Schema::hasTable('exams')&&Schema::hasTable('exam_groups')) $links=$links->merge(DB::table('exams')->join('exam_groups','exams.id','=','exam_groups.exam_id')->select('exams.category_level_1','exams.category_level_2','exam_groups.group_id')->get());
  $rows=$links->flatMap(fn($x)=>collect([$x->category_level_1,$x->category_level_2])->filter(fn($id)=>is_numeric($id)&&$validCategoryIds->contains((int)$id))->map(fn($c)=>['category_id'=>(int)$c,'group_id'=>(int)$x->group_id,'created_at'=>$now,'updated_at'=>$now]))->unique(fn($r)=>$r['category_id'].'-'.$r['group_id'])->values();
  $rows->chunk(500)->each(fn($c)=>DB::table('category_groups')->insertOrIgnore($c->all()));
if(Schema::hasTable('pages')) {
   DB::table('pages')->updateOrInsert(['action_name'=>'subcategories.index'],['page_name'=>'Subcategories','icon'=>'ri-git-branch-line','parent_id'=>null,'ordering'=>4,'created_at'=>$now,'updated_at'=>$now]);
   if(Schema::hasTable('page_rights')) {
    $sourceId=DB::table('pages')->where('action_name','category.index')->value('id');
    $targetId=DB::table('pages')->where('action_name','subcategories.index')->value('id');
    if($sourceId&&$targetId) foreach(DB::table('page_rights')->where('page_id',$sourceId)->get() as $right){$row=(array)$right;unset($row['id']);$row['page_id']=$targetId;$row['created_at']=$now;$row['updated_at']=$now;DB::table('page_rights')->updateOrInsert(['page_id'=>$targetId,'ugroup_id'=>$right->ugroup_id],$row);}
   }
  }
 }
 public function down(): void {if(Schema::hasTable('pages'))DB::table('pages')->where('action_name','subcategories.index')->delete();Schema::dropIfExists('category_groups');}
};