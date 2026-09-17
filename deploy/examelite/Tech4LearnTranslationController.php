<?php
namespace App\Http\Controllers;

use App\Services\Tech4LearnExamTranslations;
use Illuminate\Http\Request;

class Tech4LearnTranslationController extends Tech4LearnPlatformController
{
    public function centralReview(Request $request,string $exam,string $language) {
        $owner=(int)$this->configuration($request)['_platform']['organization_id'];
        abort_unless(!array_diff(array_keys($request->query()),['after','revision']),422);
        $after=$request->query('after','0');$revision=$request->query('revision');
        abort_unless(preg_match('/^[1-9][0-9]{0,14}$/D',$exam)&&preg_match('/^[1-9][0-9]{0,14}$/D',$language),422);
        abort_unless(is_string($after)&&preg_match('/^(0|[1-9][0-9]{0,14})$/D',$after)&&($revision===null||is_string($revision)),422);
        return $this->reply($owner,['data'=>app(Tech4LearnExamTranslations::class)->centralReview($owner,(int)$exam,(int)$language,(int)$after,$revision)]);
    }
    public function centralMedia(Request $request,string $exam,string $language,string $question,string $asset) {
        $owner=(int)$this->configuration($request)['_platform']['organization_id'];
        abort_unless(!array_diff(array_keys($request->query()),['revision']),422);
        $revision=$request->query('revision');
        abort_unless(is_string($revision)&&preg_match('/^[1-9][0-9]{0,14}$/D',$exam)&&preg_match('/^[1-9][0-9]{0,14}$/D',$language)&&preg_match('/^(0|[1-9][0-9]{0,14})$/D',$question),422);
        return $this->reply($owner,['data'=>app(Tech4LearnExamTranslations::class)->centralMedia($owner,(int)$exam,(int)$language,(int)$question,$asset,$revision)]);
    }
    public function media(Request $request,string $org,string $exam,string $language,string $question,string $asset) {
        $source=$this->configuration($request)['_platform']['organization_id'];
        abort_unless(array_diff(array_keys($request->query()),['actor_id','revision'])===[],422);
        $actor=$request->query('actor_id');$revision=$request->query('revision');
        abort_unless(is_string($actor)&&is_string($revision)&&preg_match('/^[1-9][0-9]{0,14}$/D',$exam)&&preg_match('/^[1-9][0-9]{0,14}$/D',$language)&&preg_match('/^(0|[1-9][0-9]{0,14})$/D',$question),422);
        return $this->reply($source,['data'=>app(Tech4LearnExamTranslations::class)->media($org,$source,$actor,(int)$exam,(int)$language,(int)$question,$asset,$revision)]);
    }
    public function review(Request $request,string $org,string $exam,string $language) {
        $source=$this->configuration($request)['_platform']['organization_id'];
        abort_unless(array_diff(array_keys($request->query()),['actor_id','after','revision'])===[],422);
        $actor=$request->query('actor_id');$after=$request->query('after','0');
        abort_unless(is_string($actor)&&preg_match('/^[1-9][0-9]{0,14}$/D',$exam)&&preg_match('/^[1-9][0-9]{0,14}$/D',$language),422);
        abort_unless(is_string($after)&&preg_match('/^(0|[1-9][0-9]{0,14})$/D',$after),422);
        $revision=$request->query('revision');abort_unless($revision===null||is_string($revision),422);
        return $this->reply($source,['data'=>app(Tech4LearnExamTranslations::class)->review($org,$source,$actor,(int)$exam,(int)$language,(int)$after,$revision)]);
    }
}
