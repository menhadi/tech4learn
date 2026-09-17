<?php
namespace App\Http\Controllers;

/** Private invocation only: context comes from the checked central authoring service.
 * No routes target this class and no native user receives global administrator rights.
 * Native LanguageController retains validation, persistence and duplicate rules.
 */
final class Tech4LearnCentralLanguageController extends LanguageController
{
    public function __construct(private readonly int $centralOwner) {
        abort_unless($centralOwner>0,403);
    }
    protected function isPlatformAdmin():bool {return true;}
    protected function platformOrganizationId():?int {return $this->centralOwner;}
    public function destroy($id) {
        return \Illuminate\Support\Facades\DB::transaction(function()use($id){
            $language=\App\Models\Language::where('organization_id',$this->centralOwner)->lockForUpdate()->findOrFail($id);
            abort_if(strtolower((string)$language->code)==='en',409,'The source English language cannot be deleted.');
            // References may belong to another organisation (enabled copies),
            // so check existence globally but never return those records.
            foreach([
                'languages'=>'source_language_id','questions'=>'language_id',
                'question_langs'=>'language_id','passage_langs'=>'language_id',
                'exam_languages'=>'language_id','exam_language_translations'=>'language_id',
                'exam_results'=>'language_id','exam_pdf_builds'=>'language_id',
                'official_exam_source_rules'=>'language_id',
            ] as $table=>$column){
                if(\Illuminate\Support\Facades\Schema::hasColumn($table,$column))
                    abort_if(\Illuminate\Support\Facades\DB::table($table)->where($column,$language->id)->exists(),409,'This language is still in use.');
            }
            return parent::destroy($id);
        });
    }
}
