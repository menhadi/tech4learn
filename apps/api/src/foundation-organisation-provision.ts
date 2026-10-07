import { randomUUID } from "node:crypto";
import { AccessService } from "./access.service.js";
import type { Database } from "./database.js";
import { field,uuid } from "./security.js";

// Trusted operator command: native organisation ownership must be independently reviewed.
export async function provisionAttendanceOrganisation(db: Database, canonicalAdminId: string, nativeId: string, displayName: string) {
  const admin=uuid(canonicalAdminId), name=field(displayName,"Organisation name");
  if(!/^[1-9][0-9]{0,14}$/.test(nativeId))throw new Error("Provide a reviewed native organisation ID.");
  return db.transaction(async sql=>{
    await sql.query("SELECT pg_advisory_xact_lock(74041020)");
    if(!(await sql.query("SELECT version FROM schema_versions WHERE version=20")).rows.length)throw new Error("Apply checked migration 20 explicitly first.");
    const root=await sql.query<{is_superadmin:boolean}>("SELECT is_superadmin FROM users WHERE id=$1 FOR SHARE",[admin]);
    if(!root.rows[0]?.is_superadmin)throw new Error("The operator identity must already be a stored superadmin.");
    const realm=await sql.query<{kind:string}>("SELECT kind FROM foundation_realms WHERE native_id=$1",[nativeId]);
    if(realm.rows[0]?.kind==="platform")throw new Error("The global platform cannot become an attendance tenant.");
    const previous=await sql.query<{organisation_id:string;origin:string;active:boolean;name:string}>("SELECT f.organisation_id,f.origin,f.active,o.name FROM foundation_organisations f JOIN organisations o ON o.id=f.organisation_id WHERE f.native_id=$1 FOR UPDATE OF f,o",[nativeId]);
    if(previous.rows.length){
      const link=previous.rows[0];
      if(link.origin!=="native_companion" || !link.active || link.name!==name)throw new Error("Existing mapping conflicts or is revoked; no adoption or repair attempted.");
      return {created:false,organisationId:link.organisation_id};
    }
    const id=randomUUID(), slug="native-org-"+nativeId;
    if(!(await sql.query("INSERT INTO organisations(id,name,slug) VALUES($1,$2,$3) ON CONFLICT(slug) DO NOTHING RETURNING id",[id,name,slug])).rows.length)throw new Error("Reserved companion address already exists; old organisations are never adopted.");
    await sql.query("INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,$2)",[id,JSON.stringify({learners:true,attendance:true,fln:false,exams:false})]);
    await new AccessService(db).initialise(sql,id);
    await sql.query("INSERT INTO foundation_organisations(native_id,organisation_id,origin) VALUES($1,$2,'native_companion')",[nativeId,id]);
    await sql.query("INSERT INTO audit_events(id,actor_id,organisation_id,action,details) VALUES($1,$2,$3,'foundation.organisation_provisioned',$4)",[randomUUID(),admin,id,JSON.stringify({nativeOrganisationId:nativeId,source:"server_cli"})]);
    return {created:true,organisationId:id};
  });
}
