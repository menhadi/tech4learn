<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use App\Support\Tech4LearnWorkspacePolicy;

final class Tech4LearnLaunchTickets
{
    public function consume(object $workspace,string $token):object
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new \DomainException('Invalid launch link.',422);
        return DB::transaction(function()use($workspace,$token){
            // Serialize launch/restriction changes against the same workspace record.
            $current=DB::table('tech4learn_workspaces')->where('id',$workspace->id)->lockForUpdate()->first();
            if(!$current||(int)$current->organization_id!==(int)$workspace->organization_id) throw new \DomainException('Invalid workspace.',403);
            $ticket=DB::table('tech4learn_workspace_tickets')->where('hash',hash('sha256',$token))->lockForUpdate()->first();
            if(!$ticket||$ticket->workspace_id!==$workspace->id||$ticket->used_at||strtotime($ticket->expires_at.' UTC')<=time()) throw new \DomainException('The link expired or was already used. Open a new link from Tech4Learn.',410);
            $feature=['subjects'=>'subjects','questions'=>'questions','exams'=>'exams','results'=>'results','student/dashboard'=>'taking'][$ticket->entry]??null;
            if(!$feature||!Tech4LearnWorkspacePolicy::mayUse($feature,json_decode($current->restrictions,true))) throw new \DomainException('This feature is restricted.',403);
            if(!DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace->id)->where('kind',$ticket->kind)->where('external_id',$ticket->external_id)->exists()) throw new \DomainException('Invalid exam identity.',403);
            if(($ticket->kind==='student')!==($feature==='taking')||!in_array($ticket->kind,['student','staff'],true))throw new \DomainException('Invalid exam identity.',403);
            DB::table('tech4learn_workspace_tickets')->where('hash',$ticket->hash)->update(['used_at'=>gmdate('Y-m-d H:i:s')]);
            return $ticket;
        });
    }
}
