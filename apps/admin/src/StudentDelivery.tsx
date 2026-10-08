import {useEffect,useState} from 'react';

type Status={learnerId:string;revision:number;state:'pending'|'delivered'|'review_required'};
export function StudentDelivery({learnerId,canEdit}:{learnerId:string;canEdit:boolean}) {
  const [status,setStatus]=useState<Status|null>(null),[error,setError]=useState(''),[busy,setBusy]=useState(false),[revision,setRevision]=useState(0);
  const base=`/enrolment/students/${encodeURIComponent(learnerId)}`;
  async function request(path:string,method='GET') {
    const response=await fetch(base+path,{method,credentials:'same-origin',signal:AbortSignal.timeout(20000),
      headers:{Accept:'application/json',...(method==='POST'?{'X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''}:{})}});
    const data=await response.json().catch(()=>null);
    if(!response.ok)throw new Error(response.status===404?'Delivery has not been requested for this student, or your access changed.':data?.message || 'Student delivery could not complete. Retry after checking access.');
    return data;
  }
  useEffect(()=>{
    let current=true;setStatus(null);setError('');
    void request('/delivery-status').then(data=>{
      if(data?.learnerId!==learnerId || !Number.isSafeInteger(data?.revision) || !['pending','delivered','review_required'].includes(data?.state))throw new Error('Student delivery status is unavailable.');
      if(current)setStatus(data);
    }).catch(e=>{if(current)setError(e instanceof Error?e.message:'Delivery status unavailable.');});
    return()=>{current=false;};
  },[learnerId,revision]);
  async function deliver() {
    setBusy(true);setError('');
    try {await request('/deliver','POST');setRevision(v=>v+1);}
    catch(e){setError(e instanceof Error?e.message:'Student delivery failed.');}
    finally {setBusy(false);}
  }
  return <section className="record" aria-label="Shared student profile">
    <h4>Shared student profile</h4>
    {error&&<p role="alert" className="error">{error}</p>}
    {status?<p>{status.state==='delivered'?'Current student details delivered to the exam profile.':status.state==='review_required'?'This identity link requires administrator review.':'Student details are waiting for delivery to the exam profile.'}</p>:!error&&<p role="status">Loading delivery status…</p>}
    <p>Delivery preserves this student identity. Exam group assignment and student login are managed separately.</p>
    {status&&status.state!=='review_required'&&canEdit&&<button type="button" disabled={busy} onClick={()=>void deliver()}>{busy?'Delivering…':status.state==='delivered'?'Refresh profile and group assignment':'Deliver student profile'}</button>}
    <button type="button" disabled={busy} onClick={()=>setRevision(v=>v+1)}>Refresh delivery status</button>
  </section>;
}
