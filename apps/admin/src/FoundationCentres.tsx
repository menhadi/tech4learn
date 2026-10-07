import { useState } from 'react';
import { api } from './api';
import { DraftForm } from './DraftForm';
import { DirectoryTable, RecordStatus } from './DirectoryTable';
import { CentreLocation } from './CentreLocation';

export type AttendanceCentre = {id:string;name:string;address:string;centre_type:string;latitude:number|null;longitude:number|null;radius:number;location_approved:boolean;archived:boolean};

export function FoundationCentres({org,centres,permissions,scope,onRefresh}:{org:string;centres:AttendanceCentre[];permissions:string[];scope:string;onRefresh:()=>Promise<void>}) {
  const [selected,setSelected]=useState<AttendanceCentre|null>(null),[editing,setEditing]=useState(false),[busy,setBusy]=useState(false),[error,setError]=useState(''),[notice,setNotice]=useState('');
  const can=(permission:string)=>permissions.includes(`centres.${permission}`), writable=scope!=='groups';
  const base=`/organisations/${org}/centres`;
  async function act(run:()=>Promise<unknown>,message:string) {
    setBusy(true);setError('');setNotice('');
    try {await run();await onRefresh();setNotice(message);}catch(e){setError(e instanceof Error?e.message:'Centre action failed.');}finally{setBusy(false);}
  }
  return <section aria-label="Attendance centres">
    <h2>Centres</h2><p>Save the centre location, then approve its coordinates separately for attendance checks.</p>
    {error&&<p role="alert">{error}</p>}{notice&&<p role="status">{notice}</p>}
    {writable&&can('create')&&<button type="button" disabled={busy} onClick={()=>{setSelected(null);setEditing(true);}}>Add centre</button>}
    {!centres.length&&<p>No centres in your access scope yet.</p>}
    <DirectoryTable title="Centre directory" columns={['Centre','Type','Address','Location','Status','Actions']}>
      {centres.map(c=><tr key={c.id}><th scope="row">{c.name}</th><td>{c.centre_type.replaceAll('_',' ')}</td><td>{c.address||'Not set'}</td>
        <td>{c.location_approved?'Approved':c.latitude===null?'Not configured':'Needs approval'}<small className="cell-note">{c.latitude!==null&&`${c.latitude}, ${c.longitude} · ${c.radius} m`}</small></td>
        <td><RecordStatus archived={c.archived}/></td><td className="row-actions">{writable&&!c.archived&&<div className="actions">
          {can('edit')&&<button type="button" disabled={busy} onClick={()=>{setSelected(c);setEditing(true);}}>Edit</button>}
          {can('approve')&&!c.location_approved&&c.latitude!==null&&<button type="button" disabled={busy} onClick={()=>void act(()=>api(`${base}/${c.id}/approve`,'POST',{}),'Location approved.')}>Approve location</button>}
          {can('archive')&&<details><summary>Archive centre</summary><p>Active classes and sections must be archived first. History is retained.</p><button type="button" disabled={busy} onClick={()=>void act(()=>api(`${base}/${c.id}/archive`,'POST',{}),'Centre archived.')}>Confirm archive</button></details>}
        </div>}</td></tr>)}
    </DirectoryTable>
    {editing&&writable&&(selected?can('edit'):can('create'))&&<DraftForm title="Centre details" draftKey={`centre:${selected?.id||'new'}`} key={selected?.id||'new'} onSubmit={e=>{
      e.preventDefault();const body=Object.fromEntries(new FormData(e.currentTarget));
      void act(async()=>{await api(selected?`${base}/${selected.id}`:base,selected?'PATCH':'POST',{...body,latitude:body.latitude===''?null:Number(body.latitude),longitude:body.longitude===''?null:Number(body.longitude),radius:Number(body.radius)});setEditing(false);setSelected(null);},'Centre saved. Changed coordinates need approval.');
    }}><h3>{selected?'Edit centre':'Add centre'}</h3><fieldset disabled={busy}>
      <label>Name<input name="name" defaultValue={selected?.name} maxLength={120} required/></label>
      <label>Village / address<input name="address" defaultValue={selected?.address} maxLength={500}/></label>
      <label>Centre type<select name="centre_type" defaultValue={selected?.centre_type||'learning_centre'}>{['school','college','coaching','community_centre','learning_centre','training_centre','other'].map(type=><option key={type} value={type}>{type.replaceAll('_',' ')}</option>)}</select></label>
      <CentreLocation latitude={selected?.latitude??null} longitude={selected?.longitude??null}/>
      <label>Allowed distance (metres)<input name="radius" type="number" min={10} max={10000} step={1} defaultValue={selected?.radius??100} required/></label>
      <div className="actions"><button>Save centre</button><button type="button" className="secondary" onClick={()=>{setEditing(false);setSelected(null);}}>Close</button></div>
    </fieldset></DraftForm>}
  </section>;
}
