<?php
namespace App\Services;

use App\Models\{Organization,SaasPlan};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Internal persistence only: a future private boundary must authorise the
 * central actor, install native request context and own an idempotency receipt.
 * No route exposes this helper. ExamElite's controller owns plan validation.
 */
final class Tech4LearnPlanEditor
{
    public const FEATURES=['public_website','paid_packages','guest_exams','ai_generator','ai_translation','ai_regeneration','ai_content_generation','ai_subjective_analysis','ai_student_analysis','ai_seo','ai_platform_api','ai_settings','email_messaging','sms_messaging','reports','question_sharing','custom_theme','student_self_registration','flashcards','ai_flashcard_generation','exam_quality_audit','exam_quality_source','exam_quality_visual','exam_quality_ai'];
    public const LIMITS=['organizations','admins','students','exams','packages','questions','quality_audits_monthly','quality_questions_per_audit','quality_source_per_audit','quality_ai_per_audit','quality_visual_per_audit','quality_repairs_monthly'];

    public function create(int $central,array $fields,Request $request):array {
        $allowed=array_merge(['name','price','billing_cycle','status'],array_map(fn($key)=>'feature_'.$key,self::FEATURES),array_map(fn($key)=>'limit_'.$key,self::LIMITS));
        abort_unless(array_diff(array_keys($fields),$allowed)===[],422);
        // All native capabilities begin available unless explicitly restricted.
        // Creating a plan never changes the global default or assigns customers.
        $values=array_replace(['price'=>0,'billing_cycle'=>'monthly','status'=>true],array_fill_keys(array_map(fn($key)=>'feature_'.$key,self::FEATURES),true),$fields,['is_default'=>false]);
        return DB::transaction(function()use($central,$values,$request){
            Organization::where('status','active')->lockForUpdate()->findOrFail($central);
            $lastId=(int)SaasPlan::max('id');
            $request->replace($values);
            $request->session()->forget(['success','error','errors']);
            app(\App\Http\Controllers\SaasController::class)->storePlan($request);
            abort_unless($request->session()->has('success')&&!$request->session()->has('errors')&&!$request->session()->has('error'),422,'Native plan creation was not confirmed.');
            // Fail closed if a concurrent native writer makes discovery ambiguous.
            $created=SaasPlan::where('id','>',$lastId)->where('name',$values['name'])->lockForUpdate()->limit(2)->get();
            abort_unless($created->count()===1,409,'Plan creation changed concurrently.');
            $plan=$created->first();
            abort_unless(!$plan->is_default,500);
            return ['plan_id'=>(int)$plan->id,'revision'=>Tech4LearnPlanAssignment::revision($plan),'name'=>$plan->name];
        });
    }
}
