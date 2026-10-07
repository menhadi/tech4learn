import { useEffect, useState } from "react";
import { Learners } from "./Learners";
import { Attendance } from "./Attendance";
import { DraftScope } from "./DraftForm";
import { api } from "./api";
import { FoundationCentres, type AttendanceCentre } from "./FoundationCentres";
import { GroupedMenu } from "./GroupedMenu";
import { AcademicStructure, type AcademicGroup } from "./AcademicStructure";

export type FoundationContext = {
  userId: string;
  organisation: { id: string; name: string };
  permissions: string[];
  scope: { type: string; ids: string[] };
};

export function FoundationAttendance() {
  const [context,setContext]=useState<FoundationContext|null>(null);
  const [groups,setGroups]=useState<AcademicGroup[]>([]);
  const [page,setPage]=useState("attendance");
  const [selectedGroup,setSelectedGroup]=useState("");
  const [centres,setCentres]=useState<AttendanceCentre[]>([]);
  const [error,setError]=useState("");
  const [revision,setRevision]=useState(0);
  useEffect(()=>{
    const reload=()=>{setContext(null);setPage("attendance");setRevision(v=>v+1);};
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
      const locations=resolved.permissions.includes("centres.view")?await api<AttendanceCentre[]>(`/organisations/${resolved.organisation.id}/centres`):[];
      if(current){setCentres(locations);setGroups(sections);setContext(resolved);}
    })().catch(e=>{if(current)setError(e instanceof Error?e.message:"Attendance could not load.");});
    return()=>{current=false;};
  },[revision]);
  return <section className="panel org-workspace" aria-label="Attendance workspace">
    <header><p className="eyebrow">{context?.organisation.name || 'Tech4Learn'}</p><h1>Attendance</h1></header>
    {error?<div role="alert"><p>{error}</p><button type="button" onClick={()=>setRevision(v=>v+1)}>Retry</button> <a href="/login">Sign in again</a></div>:!context?<p role="status">Loading attendance…</p>:
      <DraftScope user={context.userId} org={context.organisation.id}>
        {context.permissions.includes('attendance.capture')&&!context.permissions.includes('groups.view')&&<p role="status">Section access is required to start a capture. Daily attendance and permitted reviews remain available below.</p>}
        <GroupedMenu label="Attendance navigation" active={page} onSelect={setPage} groups={[{id:"attendance",label:"Attendance",icon:"attendance",items:[{id:"attendance",label:"Daily attendance"},...(context.permissions.includes('learners.view')?[{id:"learners",label:"Attendance learners"}]:[]),...(context.permissions.includes('centres.view')?[{id:"centres",label:"Centres"}]:[]),...(context.permissions.includes('groups.view')&&context.permissions.includes('centres.view')?[{id:"structure",label:"Classes and sections"}]:[])]}]}/>
        {page==='learners'&&context.permissions.includes('learners.view') ? <>
          <p>These are attendance enrolments. Links to native exam students require explicit review; they are not matched by name or email.</p>
          <Learners key={selectedGroup} org={context.organisation.id} permissions={context.permissions} groups={groups} initialGroup={selectedGroup}/>
        </> : page==='centres'&&context.permissions.includes('centres.view') ? <FoundationCentres org={context.organisation.id} centres={centres} permissions={context.permissions} scope={context.scope.type} onRefresh={async()=>setCentres(await api<AttendanceCentre[]>(`/organisations/${context.organisation.id}/centres`))}/> : page==='structure'&&context.permissions.includes('groups.view')&&context.permissions.includes('centres.view') ? <>
          <p>Use an approved attendance centre to organise years, classes and sections. Create and approve centres in the Centres view. Native student linking is still managed separately.</p>
          <AcademicStructure org={context.organisation.id} centres={centres} groups={groups} permissions={context.permissions} scope={context.scope.type} onRefresh={async()=>{const rows=await api<AcademicGroup[]>(`/organisations/${context.organisation.id}/groups`);setGroups(rows);}} onStudents={id=>{setSelectedGroup(id);setPage('learners');}} onAttendance={id=>{setSelectedGroup(id);setPage('attendance');}}/>
        </> : <Attendance org={context.organisation.id} groups={groups.filter(g=>!g.archived)} permissions={context.permissions} mode="all" initialGroup={selectedGroup}/>}
      </DraftScope>}
  </section>;
}
