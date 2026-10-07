<?php
namespace App\Http\Controllers;
use App\Models\{ExamResult, ExamStats};
use App\Support\{SaasAccess, Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Storage};
class SubjectiveUploadController extends Controller
{
    public function upload(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_subjective_analysis');
        $request->validate([
            'answer_file'=>'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,txt|max:10240',
            'question_id'=>'required|integer', 'exam_result_id'=>'required|integer',
        ]);
        $tenantId=Tenant::hostId($request->getHost());
        $studentId=Auth::guard('student')->id();
        $stored=null;
        try {
            DB::transaction(function()use($request,$tenantId,$studentId,&$stored){
                // Serialize with exam finalization before accepting new evidence.
                $result=ExamResult::whereKey($request->exam_result_id)
                    ->where('organization_id',$tenantId)->where('student_id',$studentId)
                    ->lockForUpdate()->first();
                abort_unless($result,403,'Invalid exam result for this student.');
                abort_if($result->end_time,409,'This exam has already been submitted.');
                $stat=ExamStats::where('exam_result_id',$result->id)
                    ->where('organization_id',$tenantId)->where('student_id',$studentId)
                    ->where('question_id',$request->question_id)->lockForUpdate()->first();
                abort_unless($stat,404,'Question answer record was not found for this exam.');
                $stored=$request->file('answer_file')->store("student_answers_private/$tenantId/$result->id",'local');
                abort_unless($stored,500,'Answer upload failed.');
                $stat->uploaded_answer_path=$stored;
                $stat->save();
            });
        } catch(\Throwable $error) {
            if($stored)Storage::disk('local')->delete($stored);
            throw $error;
        }
        return response()->json(['success'=>true,'message'=>'Answer uploaded successfully!']);
    }
}
