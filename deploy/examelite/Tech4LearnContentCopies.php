<?php
namespace App\Services;

use App\Models\{Exam,Question,Subject,Topic,Stopic,Group,Language,Passage,QuestionSection,QuestionTag,ExamSection,ExamSubjectDuration,Category,ExamQualitySource};
use App\Support\Tech4LearnWorkspacePolicy;
use Illuminate\Support\Facades\{DB,Storage};

/** Copies content, not the exam engine. All writes are to the destination tenant. */
final class Tech4LearnContentCopies
{
    private const MODELS = ['exam'=>Exam::class,'question'=>Question::class,'subject'=>Subject::class,
        'topic'=>Topic::class,'subtopic'=>Stopic::class,'group'=>Group::class,'language'=>Language::class,
        'passage'=>Passage::class,'section'=>QuestionSection::class,'tag'=>QuestionTag::class,'category'=>Category::class];
    private const FOREIGN = ['subject_id'=>'subject','topic_id'=>'topic','stopic_id'=>'subtopic',
        'group_id'=>'group','language_id'=>'language','passage_id'=>'passage','question_section_id'=>'section',
        'test_subject_id'=>'subject','test_topic_id'=>'topic','test_stopic_id'=>'subtopic',
        'category_level_1'=>'category','category_level_2'=>'category'];
    private object $workspace;
    private array $visiting=[];
    private array $createdFiles=[];
    private bool $pull=false;

    public function copy(string $workspace, int $destination, string $kind, int $id, bool $pull=false): int
    {
        if (!in_array($kind,['exam','question','subject','topic','subtopic','section'],true) || $id<1) {
            throw new \InvalidArgumentException('Invalid shared content.');
        }
        $this->createdFiles=[];
        try {return DB::transaction(function()use($workspace,$destination,$kind,$id,$pull){
            $row=DB::table('tech4learn_workspaces')->where('id',$workspace)->lockForUpdate()->first();
            if (!$row || !$row->organization_id || (int)($pull?$row->source_organization_id:$row->organization_id)!==$destination || (int)$row->organization_id===(int)$row->source_organization_id || ($pull && $kind!=='question')) {
                throw new \DomainException('Workspace does not own the destination.');
            }
            $feature=['exam'=>'exams','question'=>'questions','subject'=>'subjects','topic'=>'subjects','subtopic'=>'subjects','section'=>'subjects'][$kind];
            if (!$pull && !Tech4LearnWorkspacePolicy::mayUse($feature,json_decode($row->restrictions,true,512,JSON_THROW_ON_ERROR))) {
                throw new \DomainException('Feature restricted.');
            }
            $this->pull=$pull;
            if($pull) { $source=$row->organization_id; $row->organization_id=$row->source_organization_id; $row->source_organization_id=$source; }
            $this->workspace=$row;
            $this->visiting=[];
            return $this->record($kind,$id);
        });} catch(\Throwable $e) {
            foreach($this->createdFiles as [$disk,$path]) {try {Storage::disk($disk)->delete($path);}catch(\Throwable $ignored){}}
            throw $e;
        }
    }

    private function record(string $kind, ?int $id): ?int
    {
        if (!$id) return null;
        $model=self::MODELS[$kind];
        $source=$model::findOrFail($id);
        if($kind==='exam' && ($source->created_by_student_id || $source->is_student_practice)) throw new \DomainException('Personal student practice is outside the shared library.');
        $sourceOrg=in_array($kind,['topic','subtopic'],true)?$source->subject?->organization_id:$source->organization_id;
        if ((int)$sourceOrg!==(int)$this->workspace->source_organization_id) throw new \DomainException('Content is outside the shared library.');
        $key=['workspace_id'=>$this->workspace->id,'kind'=>($this->pull?'pull:':'').$kind,'source_id'=>$id];
        $mapped=DB::table('tech4learn_workspace_copies')->where($key)->value('target_id');
        if ($mapped) {
            $owned=$model::findOrFail($mapped);
            $ownedOrg=in_array($kind,['topic','subtopic'],true)?$owned->subject?->organization_id:$owned->organization_id;
            if ((int)$ownedOrg!==(int)$this->workspace->organization_id) throw new \DomainException('Invalid owned copy.');
            // Reopening or retrying never overwrites edits made in the organisation's version.
            return (int)$mapped;
        }
        $visit=$kind.':'.$id;
        if(isset($this->visiting[$visit])) throw new \DomainException('Shared content contains a circular relationship. Correct it in ExamElite first.');
        $this->visiting[$visit]=true;
        // Native languages have tenant/code uniqueness. Reuse the destination's language
        // identity without changing its content or preferences during a central pull.
        if($this->pull && $kind==='language') {
            $language=Language::where('organization_id',$this->workspace->organization_id)->where('code',$source->code)->first();
            if($language) {
                DB::table('tech4learn_workspace_copies')->insert($key+['target_id'=>$language->id]);
                unset($this->visiting[$visit]);
                return (int)$language->id;
            }
        }
        $target=$source->replicate();
        $target->unsetRelations();
        if (array_key_exists('organization_id',$source->getAttributes())) $target->organization_id=$this->workspace->organization_id;
        foreach(self::FOREIGN as $field=>$relation) {
            if(array_key_exists($field,$source->getAttributes())) $target->$field=$this->record($relation,$source->$field);
        }
        if ($kind==='category') $target->parent_id=$this->record('category',$source->parent_id);
        if ($kind==='question') {
            $target->question_code=null;
            // Keep provenance in the private copy map, not in native cross-tenant editable links.
            if(array_key_exists('original_question_id',$source->getAttributes())) $target->original_question_id=null;
        }
        if ($kind==='exam') {
            $target->slug='t4l-'.str_replace('-','',$this->workspace->id).'-'.$id;
            $target->created_by_student_id=null;$target->is_student_practice=false;
        }
        if ($kind==='language') $target->source_language_id=$this->pull?null:$id;
        if (in_array($kind,['group','tag','category'],true) && array_key_exists('slug',$source->getAttributes())) {
            $target->slug='t4l-'.($this->pull?'pull-':'').str_replace('-','',$this->workspace->id).'-'.$kind.'-'.$id;
        }
        $target->save();
        DB::table('tech4learn_workspace_copies')->insert($key+['target_id'=>$target->id]);
        if (method_exists($source,'groups')) {
            $target->groups()->sync($source->groups->map(fn($g)=>$this->record('group',$g->id))->all());
        }
        if(in_array($kind,['question','passage'],true)) {
            foreach($source->langs as $translation) {
                $copy=$translation->replicate();$copy->unsetRelations();
                $parent=$kind.'_id';$copy->$parent=$target->id;
                if(array_key_exists('translated_by',$translation->getAttributes())) $copy->translated_by=null;
                $copy->language_id=$this->record('language',$translation->language_id);$copy->save();
            }
        }
        if($kind==='question') {
            $target->tags()->sync($source->tags->map(fn($tag)=>$this->record('tag',$tag->id))->all());
            foreach($source->taxonomies as $taxonomy) {
                $copy=$taxonomy->replicate();$copy->unsetRelations();
                $copy->question_id=$target->id;$copy->organization_id=$this->workspace->organization_id;
                foreach(self::FOREIGN as $field=>$relation) if(array_key_exists($field,$taxonomy->getAttributes())) $copy->$field=$this->record($relation,$taxonomy->$field);
                $copy->save();
            }
        }
        if($kind==='exam') $this->examRelations($source,$target);
        unset($this->visiting[$visit]);
        return (int)$target->id;
    }

    private function examRelations(Exam $source, Exam $target): void
    {
        $sections=[];
        foreach($source->sections as $section) {
            $copy=$section->replicate();$copy->unsetRelations();$copy->exam_id=$target->id;
            $copy->question_section_id=$this->record('section',$section->question_section_id);$copy->save();
            $sections[$section->id]=$copy->id;
        }
        foreach($source->questions as $question) {
            $section=$question->pivot->exam_section_id;
            if($section&&!isset($sections[$section])) throw new \DomainException('Exam has an invalid section.');
            $target->questions()->attach($this->record('question',$question->id),['exam_section_id'=>$section?$sections[$section]:null]);
        }
        foreach(ExamSubjectDuration::where('exam_id',$source->id)->get() as $timer) {
            $copy=$timer->replicate();$copy->unsetRelations();$copy->exam_id=$target->id;
            $copy->subject_id=$this->record('subject',$timer->subject_id);$copy->save();
        }
        foreach($source->languages as $language) {
            // Preserve available translations; jobs and approval identities are organisation-specific.
            $state=$language->pivot->translation_status;
            $target->languages()->attach($this->record('language',$language->id),[
                'translation_status'=>in_array($state,['completed','translated','ready'],true)?$state:'pending',
                'translated_at'=>$language->pivot->translated_at,
                'auto_translate'=>false,'auto_pdf'=>false,
            ]);
        }
        foreach($source->languageTranslations as $translation) {
            $copy=$translation->replicate();$copy->unsetRelations();$copy->exam_id=$target->id;
            $copy->language_id=$this->record('language',$translation->language_id);$copy->translated_by=null;$copy->save();
        }
        foreach(ExamQualitySource::where('organization_id',$this->workspace->source_organization_id)->where('exam_id',$source->id)->where('is_active',true)->get() as $asset) {
            $copy=$asset->replicate();$copy->unsetRelations();
            $copy->organization_id=$this->workspace->organization_id;$copy->exam_id=$target->id;
            $copy->provider=null;$copy->provider_file_id=null;$copy->provider_uploaded_at=null;
            if($asset->file_path) {
                $disk=$asset->storage_disk?:'local';
                if(!in_array($disk,['local','public'],true)) throw new \DomainException('Shared source uses an unsupported storage disk.');
                $path=$asset->file_path;
                if(str_starts_with($path,'/') || str_contains($path,'\\') || in_array('..',explode('/',$path),true)) throw new \DomainException('Invalid shared source path.');
                $extension=strtolower(pathinfo($path,PATHINFO_EXTENSION));
                if(!preg_match('/^[a-z0-9]{1,10}$/D',$extension)) throw new \DomainException('Invalid shared source file.');
                $owned='tech4learn-workspaces/'.$this->workspace->id.'/'.$target->id.'/'.bin2hex(random_bytes(16)).'.'.$extension;
                $this->createdFiles[]=[$disk,$owned];
                if(!Storage::disk($disk)->copy($path,$owned)) throw new \DomainException('Could not copy the shared source file. Retry after checking ExamElite storage.');
                $copy->storage_disk=$disk;$copy->file_path=$owned;
            }
            $copy->save();
        }
        // Attempts, results, purchases and processing jobs belong to the source tenant and are never copied.
    }
}
