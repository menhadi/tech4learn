import {useEffect,useState} from 'react';
import {DraftForm} from './DraftForm';
import {submittingDraft} from './form-drafts';
import type {AcademicGroup} from './AcademicStructure';

type Options={sectionId:string;mapping:{nativeGroupId:string;version:number;active:boolean}|null;groups:{id:string;name:string}[]};
export function SectionExamGroup({groups,canEdit}:{groups:AcademicGroup[];canEdit:boolean}) {
  const [section,setSection]=useState(''),[options,setOptions]=useState<Options|null>(null),[error,setError]=useState(''),[notice,setNotice]=useState(''),[busy,setBusy]=useState(false),[revision,setRevision]=useState(0);
  const path=`/enrolment/sections/${encodeURIComponent(section)}/exam-group`;
  useEffect(()=>{
    let current=true;setOptions(null);setError('');
    if(section)void fetch(path,{credentials:'same-origin',headers:{Accept:'application/json'},signal:AbortSignal.timeout(15000)})
      .then(async r=>{const data=await r.json().catch(()=>null);if(!r.ok)throw new Error(data?.message || 'Section mapping is unavailable.');
        if(data?.sectionId!==section || !Array.isArray(data?.groups))throw new Error('Invalid mapping options.');
        if(current)setOptions(data);}).catch(e=>{if(current)setError(e instanceof Error?e.message:'Mapping unavailable.');});
    return()=>{current=false;};
  },[section,revision]);
  async function save(nativeGroupId:string,draftKey:string|null) {
    if(!options)return;setBusy(true);setError('');setNotice('');
    try {
      const response=await fetch(path,{method:'POST',credentials:'same-origin',signal:AbortSignal.timeout(15000),
        headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''},
        body:JSON.stringify({nativeGroupId,version:options.mapping?.version || 0})});
      const data=await response.json().catch(()=>null);if(!response.ok)throw new Error(data?.message || 'Mapping could not be saved. Reload its current version before retrying.');
      if(draftKey)window.dispatchEvent(new CustomEvent('t4l:write-saved',{detail:{draftKey}}));
      setNotice('Section mapping saved. Students are assigned when their profiles are explicitly delivered.');setRevision(v=>v+1);
    }catch(e){setError(e instanceof Error?e.message:'Mapping failed.');}finally{setBusy(false);}
  }
  return <section className="record" aria-label="Section exam group mapping">
    <h3>Section exam group</h3><p>Choose which exam group receives students enrolled in this section. Existing manual memberships are preserved.</p>
    <label>Enrolment section<select value={section} disabled={busy} onChange={e=>{setSection(e.target.value);setNotice('');}}><option value="">Select section</option>{groups.filter(g=>!g.archived).map(g=><option key={g.id} value={g.id}>{g.display_name || g.name}</option>)}</select></label>
    {error&&<p role="alert" className="error">{error}</p>}{notice&&<p role="status">{notice}</p>}
    {section&&!options&&!error&&<p role="status">Loading exam groups…</p>}
    {options&&<DraftForm key={`${section}:${options.mapping?.version || 0}`} title="Map section" draftKey={`section-exam-group:${section}`} onSubmit={e=>{e.preventDefault();void save(String(new FormData(e.currentTarget).get('nativeGroupId') || ''),submittingDraft);}}>
      <fieldset disabled={busy || !canEdit || options.mapping?.active===false}>
        <label>Exam group<select name="nativeGroupId" required defaultValue={options.mapping?.nativeGroupId || ''}><option value="">Select exam group</option>{options.groups.map(g=><option key={g.id} value={g.id}>{g.name || `Exam group ${g.id}`}</option>)}</select></label>
        {canEdit&&<button type="submit" disabled={!options.groups.length}>{busy?'Saving…':'Save section mapping'}</button>}
      </fieldset>
      {options.mapping?.active===false&&<p role="alert">This mapping was revoked and needs administrator review.</p>}
      {!options.groups.length&&<p>Create an exam group in Academic Structure before mapping this section.</p>}
    </DraftForm>}
    {section&&<button type="button" disabled={busy} onClick={()=>setRevision(v=>v+1)}>Reload mapping</button>}
  </section>;
}
