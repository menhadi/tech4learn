import { useEffect, useState } from "react";
import { Attendance } from "./Attendance";
import { DraftScope } from "./DraftForm";
import { api } from "./api";
import type { AcademicGroup } from "./AcademicStructure";

export type FoundationContext = {
  userId: string;
  organisation: { id: string; name: string };
  permissions: string[];
};

export function FoundationAttendance() {
  const [context,setContext]=useState<FoundationContext|null>(null);
  const [groups,setGroups]=useState<AcademicGroup[]>([]);
  const [error,setError]=useState("");
  const [revision,setRevision]=useState(0);
  useEffect(()=>{
    const reload=()=>{setContext(null);setRevision(v=>v+1);};
    window.addEventListener('t4l:foundation-access-changed',reload);
    return()=>window.removeEventListener('t4l:foundation-access-changed',reload);
  },[]);
  useEffect(()=>{
    let current=true;
    setContext(null);setError("");setGroups([]);
    void (async()=>{
      const response=await fetch('/attendance/context',{credentials:'same-origin',headers:{Accept:'application/json'},signal:AbortSignal.timeout(15000)});
      const data=await response.json().catch(()=>null);
      if(!response.ok)throw new Error(data?.message || "Attendance is unavailable. Please sign in again or contact your administrator.");
      const resolved=data as FoundationContext;
      if(!resolved?.userId||!resolved.organisation?.id||!resolved.permissions?.includes('attendance.view'))throw new Error("You do not have attendance access.");
      const sections=resolved.permissions.includes('groups.view')?await api<AcademicGroup[]>(`/organisations/${resolved.organisation.id}/groups`):[];
      if(current){setGroups(sections.filter(g=>!g.archived));setContext(resolved);}
    })().catch(e=>{if(current)setError(e instanceof Error?e.message:"Attendance could not load.");});
    return()=>{current=false;};
  },[revision]);
  return <section className="panel org-workspace" aria-label="Attendance workspace">
    <header><p className="eyebrow">{context?.organisation.name || 'Tech4Learn'}</p><h1>Attendance</h1></header>
    {error?<div role="alert"><p>{error}</p><button type="button" onClick={()=>setRevision(v=>v+1)}>Retry</button> <a href="/login">Sign in again</a></div>:!context?<p role="status">Loading attendance…</p>:
      <DraftScope user={context.userId} org={context.organisation.id}>
        {context.permissions.includes('attendance.capture')&&!context.permissions.includes('groups.view')&&<p role="status">Section access is required to start a capture. Daily attendance and permitted reviews remain available below.</p>}
        <Attendance org={context.organisation.id} groups={groups} permissions={context.permissions} mode="all"/>
      </DraftScope>}
  </section>;
}
