import { randomUUID } from "node:crypto";
import type { Database } from "./database.js";
import { emailValue,field,hashPassword,passwordValue,uuid } from "./security.js";

// Trusted operator only: independently review the native tenant user before provisioning.
export async function provisionAttendanceAdministrator(db: Database, rootId: string, nativeId: string, nativeUserId: string, emailInput: string, nameInput: string, passwordInput: string) {
  const root=uuid(rootId),email=emailValue(emailInput),name=field(nameInput,"Name");
  const passwordHash=await hashPassword(passwordValue(passwordInput));
  for(const id of [nativeId,nativeUserId])if(!/^[1-9][0-9]{0,14}$/.test(id))throw new Error("Provide reviewed native numeric IDs.");
  return db.transaction(async sql=>{
    await sql.query("SELECT pg_advisory_xact_lock(74041020)");
    const actor=await sql.query<{is_superadmin:boolean}>("SELECT is_superadmin FROM users WHERE id=$1 FOR SHARE",[root]);
    if(!actor.rows[0]?.is_superadmin)throw new Error("A stored canonical superadmin is required.");
    const companion=await sql.query<{organisation_id:string;origin:string;active:boolean}>("SELECT organisation_id,origin,active FROM foundation_organisations WHERE native_id=$1 FOR SHARE",[nativeId]);
    const link=companion.rows[0];
    if(!link?.active || link.origin!=="native_companion")throw new Error("An active fresh attendance companion is required.");
    const prior=await sql.query<{user_id:string;active:boolean;email:string;name:string;is_superadmin:boolean}>("SELECT f.user_id,f.active,u.email,u.name,u.is_superadmin FROM foundation_staff f JOIN users u ON u.id=f.user_id WHERE f.native_organisation_id=$1 AND f.native_user_id=$2 FOR UPDATE OF f,u",[nativeId,nativeUserId]);
    if(prior.rows.length) {
      const user=prior.rows[0];
      const membership=await sql.query("SELECT m.user_id FROM memberships m JOIN access_roles r ON r.organisation_id=m.organisation_id AND r.id=m.role_id WHERE m.organisation_id=$1 AND m.user_id=$2 AND m.status='active' AND m.scope_type='organisation' AND cardinality(m.scope_ids)=0 AND r.protected",[link.organisation_id,user.user_id]);
      if(!user.active || user.is_superadmin || user.email!==email || user.name!==name || !membership.rows.length)throw new Error("Existing staff identity conflicts or is revoked; no repair attempted.");
      return {created:false,userId:user.user_id};
    }
    if((await sql.query("SELECT id FROM users WHERE email=$1",[email])).rows.length)throw new Error("Email already exists; existing accounts are never adopted.");
    const roles=await sql.query<{id:string}>("SELECT id FROM access_roles WHERE organisation_id=$1 AND protected",[link.organisation_id]);
    if(roles.rows.length!==1)throw new Error("Review the fresh companion administrator role.");
    const id=randomUUID();
    await sql.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,$2,$3,$4,false)",[id,email,name,passwordHash]);
    await sql.query("INSERT INTO memberships(user_id,organisation_id,role,role_id) VALUES($1,$2,'organisation_admin',$3)",[id,link.organisation_id,roles.rows[0].id]);
    await sql.query("INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id) VALUES($1,$2,$3)",[nativeId,nativeUserId,id]);
    await sql.query("INSERT INTO audit_events(id,actor_id,organisation_id,action,details) VALUES($1,$2,$3,'foundation.staff_provisioned',$4)",[randomUUID(),root,link.organisation_id,JSON.stringify({nativeOrganisationId:nativeId,nativeUserId,userId:id,source:"server_cli"})]);
    return {created:true,userId:id};
  });
}
