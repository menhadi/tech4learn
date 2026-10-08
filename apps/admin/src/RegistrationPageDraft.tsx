import {useEffect,useRef,useState} from "react";
import {DraftForm,useDraftKey} from "./DraftForm";
import {removeDraft} from "./form-drafts";
import {api} from "./api";
import type {AcademicGroup} from "./AcademicStructure";

type Fields={name:string;age:number|null;guardian_name:string;guardian_phone:string};
type Provider={id:string;label:string;configured:boolean};
export function RegistrationPageDraft({org,groups,onApply}:{org:string;groups:AcademicGroup[];onApply:(fields:Fields,group:string)=>void}) {
  const [group,setGroup]=useState(""),[providers,setProviders]=useState<Provider[]>([]),[provider,setProvider]=useState("");
  const [image,setImage]=useState(""),[draft,setDraft]=useState<Fields|null>(null),[warnings,setWarnings]=useState<string[]>([]);
  const [busy,setBusy]=useState(false),[error,setError]=useState(""),[attested,setAttested]=useState(false);
  const [providersLoading,setProvidersLoading]=useState(false),[providersLoaded,setProvidersLoaded]=useState(false);
  const generation=useRef(0);
  const draftKey=useDraftKey(`registration-page:${group}`);
  useEffect(()=>{generation.current++;let active=true;setProviders([]);setProvider("");setDraft(null);setImage("");setAttested(false);setError("");setProvidersLoaded(false);setProvidersLoading(!!group);
    if(group)void api<Provider[]>(`/organisations/${org}/registration-documents/providers?group_id=${encodeURIComponent(group)}`)
      .then(rows=>{if(active){setProviders(rows);setProvidersLoaded(true);}}).catch(()=>{if(active)setError("Registration-page processing is unavailable for this section.");}).finally(()=>{if(active)setProvidersLoading(false);});
    return()=>{active=false;generation.current++;};
  },[org,group]);
  async function file(file?:File) {
    const current=++generation.current;setImage("");setDraft(null);setAttested(false);setError("");
    if(!file)return;
    if(!["image/jpeg","image/png","image/webp"].includes(file.type)||file.size>10*1024*1024){setError("Choose a JPEG, PNG or WebP page up to 10 MB.");return;}
    setBusy(true);
    try {
      const bitmap=await createImageBitmap(file);
      try {
        const ratio=Math.min(1,2048/Math.max(bitmap.width,bitmap.height));
        const canvas=document.createElement("canvas");canvas.width=Math.round(bitmap.width*ratio);canvas.height=Math.round(bitmap.height*ratio);
        const context=canvas.getContext("2d");if(!context||canvas.width<160||canvas.height<160)throw new Error();
        context.fillStyle="white";context.fillRect(0,0,canvas.width,canvas.height);context.drawImage(bitmap,0,0,canvas.width,canvas.height);
        const encoded=canvas.toDataURL("image/jpeg",.88).split(",")[1];if(!encoded||encoded.length>6990508)throw new Error();
        if(current===generation.current)setImage(encoded);
      } finally {bitmap.close();}
    } catch {if(current===generation.current)setError("The page could not be read. Retake or upload a clearer image.");}
    finally {setBusy(false);}
  }
  async function extract() {
    const current=generation.current;setBusy(true);setError("");setDraft(null);
    try {
      const result=await api<{fields:Fields;warnings:string[];requiresReview:boolean}>(`/organisations/${org}/registration-documents/draft`,"POST",{group_id:group,provider,document:image,attested});
      if(current!==generation.current)return;
      if(result.requiresReview!==true)throw new Error();
      setDraft(result.fields);setWarnings(result.warnings);setImage("");setAttested(false);
    } catch {if(current===generation.current)setError("Page processing could not complete. No student was saved. Check provider setup, access and the daily limit.");}
    finally {setBusy(false);}
  }
  return <section className="record"><h4>Read a registration page</h4>
    <p>Use one student's text page. This document is separate from their portrait and face references. Review every extracted field; this step never saves a student.</p>
    <fieldset disabled={busy} data-no-draft="true">
      <label>Registration section<select value={group} onChange={e=>setGroup(e.target.value)}><option value="">Choose section</option>{groups.filter(g=>!g.archived).map(g=><option key={g.id} value={g.id}>{g.name}</option>)}</select></label>
      {providersLoading&&<p role="status">Checking page processing providers…</p>}
      {providersLoaded&&!providers.some(p=>p.configured)&&<p role="status">Page reading is unavailable until a vision provider is configured by the platform administrator. Continue filling the enrolment form manually; portrait capture and upload remain available.</p>}
      <label>Page processing provider<select value={provider} disabled={providersLoading||!providers.some(p=>p.configured)} onChange={e=>{setProvider(e.target.value);setAttested(false);}}><option value="">Choose configured provider</option>{providers.filter(p=>p.configured).map(p=><option key={p.id} value={p.id}>{p.label}</option>)}</select></label>
      <label>Photograph or upload the registration page<input type="file" accept="image/jpeg,image/png,image/webp" capture="environment" onChange={e=>{void file(e.target.files?.[0]);e.target.value="";}} disabled={!group||!provider}/></label>
      {image&&<p>Page ready for processing. It has not been uploaded.</p>}
      <label className="check"><input type="checkbox" checked={attested} disabled={!provider||!image} onChange={e=>setAttested(e.target.checked)}/>I confirm this page belongs to this organisation and may be sent, including its written contact details, to the selected provider.</label>
      <button type="button" disabled={!group||!provider||!image||!attested} onClick={()=>void extract()}>Read page into draft</button>
    </fieldset>
    {busy&&<p role="status">Processing page…</p>}{error&&<p className="error" role="alert">{error}</p>}
    {draft&&<DraftForm key={`${group}:${JSON.stringify(draft)}`} title="Review registration text" draftKey={`registration-page:${group}`} onSubmit={e=>{
      e.preventDefault();const f=new FormData(e.currentTarget);onApply({name:String(f.get("name")||""),age:f.get("age")===""?null:Number(f.get("age")),guardian_name:String(f.get("guardian_name")||""),guardian_phone:String(f.get("guardian_phone")||"")},group);if(draftKey)removeDraft(draftKey);setDraft(null);
    }}>
      {warnings.map((w,i)=><p key={i}>{w}</p>)}
      <p>Correct the text below, then copy it into the enrolment form. Student IDs stay under the normal registration rules. Required custom fields still need completion.</p>
      <label>Name<input name="name" defaultValue={draft.name} maxLength={120} required/></label>
      <label>Age<input name="age" type="number" min={0} max={120} step={1} defaultValue={draft.age??""}/></label>
      <label>Guardian name<input name="guardian_name" maxLength={120} defaultValue={draft.guardian_name}/></label>
      <label>Guardian phone<input name="guardian_phone" type="tel" maxLength={40} defaultValue={draft.guardian_phone}/></label>
      <label className="check"><input name="reviewed" type="checkbox" required/>I have reviewed and corrected the extracted text.</label>
      <button>Copy reviewed fields to enrolment</button>
    </DraftForm>}
  </section>;
}
