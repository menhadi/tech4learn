import {useEffect,useState} from 'react';
export type Admission={admissionId:string;name:string;state:'awaiting_review'|'bound'};
export async function admissionRequest<T>(path:string,method='GET',body?:unknown):Promise<T>{
 const response=await fetch('/enrolment/admissions'+path,{method,credentials:'same-origin',signal:AbortSignal.timeout(45000),
  headers:{Accept:'application/json',...(body!==undefined?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||''}:{})},
  body:body===undefined?undefined:JSON.stringify(body)});
 const data=await response.json().catch(()=>null);
 if(!response.ok){if([401,403].includes(response.status))window.dispatchEvent(new Event('t4l:foundation-access-changed'));
  throw new Error(data?.message||'Admission review could not complete. Keep your draft and retry after checking access.');}
 return data as T;
}
export function StudentAdmissions({onReview,onLinked,revision}:{onReview:(row:Admission)=>void;onLinked:(row:Admission)=>Promise<void>;revision:number}){
 const [rows,setRows]=useState<Admission[]>([]),[error,setError]=useState(''),[busy,setBusy]=useState(false),[refresh,setRefresh]=useState(0),[hasMore,setHasMore]=useState(false);
 useEffect(()=>{let active=true;setError('');
  void admissionRequest<{items:Admission[];hasMore:boolean}>('').then(value=>{
   if(!Array.isArray(value.items)||value.items.some(row=>!/^([a-f0-9]{8}-)(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/.test(row.admissionId)||!['awaiting_review','bound'].includes(row.state)||typeof row.name!=='string'))throw new Error('Admission list is unavailable.');
   if(active){setRows(value.items);setHasMore(value.hasMore===true);}
  }).catch(e=>{if(active)setError(e instanceof Error?e.message:'Admissions unavailable.');});
  return()=>{active=false;};
 },[revision,refresh]);
 async function retry(row:Admission){setBusy(true);setError('');try{
  await admissionRequest('/'+row.admissionId+'/bind','POST',{});await onLinked(row);setRefresh(v=>v+1);
 }catch(e){setError(e instanceof Error?e.message:'Could not finish linking.');}finally{setBusy(false);}}
 return <details className="record"><summary>Review public student signups</summary>
  <p>Review each signup in the enrolment form and choose its section. Linking preserves its original exam account and does not activate login.</p>
  {error&&<p role="alert" className="error">{error}</p>}
  {!rows.length&&!error&&<p>No pending signup in the current review list.</p>}
  <ul>{rows.map(row=><li key={row.admissionId}>{row.name} {' '}
   <button type="button" disabled={busy} onClick={()=>row.state==='bound'?void retry(row):onReview(row)}>{row.state==='bound'?'Finish linking':'Review enrolment'}</button>
  </li>)}</ul>
  {hasMore&&<p>Showing the first 50 pending signups. Complete reviews and refresh to see the next signups.</p>}
  <button type="button" disabled={busy} onClick={()=>setRefresh(v=>v+1)}>Refresh signups</button>
 </details>;
}
