import {BadRequestException,ConflictException,ForbiddenException,Injectable,NotFoundException} from "@nestjs/common";
import {createHash} from "node:crypto";
import {Database} from "./database.js";
import {AccessService} from "./access.service.js";
import {LearnersService} from "./learners.service.js";
import type {Account} from "./identity.service.js";
import {verifyStudentAdmissionReceipt} from "./student-admission-proof.js";

function canonical(value:unknown):unknown {
  if(Array.isArray(value))return value.map(canonical);
  if(value && typeof value==="object")return Object.fromEntries(Object.entries(value).sort(([a],[b])=>a.localeCompare(b)).map(([k,v])=>[k,canonical(v)]));
  return value;
}
/** Internal native staff review. No guest authority, login activation or remote account creation. */
@Injectable()
export class StudentAdmissionsService {
 constructor(private readonly db:Database,private readonly access:AccessService,private readonly learners:LearnersService){}
 async bindingSnapshot(actor:Account,native:string,user:string,admission:string) {
  if(!/^[1-9][0-9]{0,14}$/.test(native) || !/^[1-9][0-9]{0,14}$/.test(user) ||
    !/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/.test(admission))throw new BadRequestException("Invalid admission identity.");
  return this.db.transaction(async sql=>{
   const mapping=(await sql.query<{organisation_id:string}>(`SELECT f.organisation_id FROM foundation_organisations f JOIN foundation_staff s ON s.native_organisation_id=f.native_id
    WHERE f.native_id=$1 AND s.native_user_id=$2 AND s.user_id=$3 AND f.active AND s.active FOR SHARE OF f,s`,[native,user,actor.id])).rows[0];
   if(!mapping)throw new NotFoundException("Admission staff identity is unavailable.");
   const org=mapping.organisation_id;await this.access.lock(sql,org);await this.access.require(actor,org,"learners.edit",sql);
   const origin=(await sql.query<{native_student_id:string;origin_version:string;origin_fingerprint:string;state:string}>(`SELECT a.native_student_id::text,a.origin_version::text,a.origin_fingerprint,a.state
    FROM foundation_student_admissions a JOIN foundation_native_signers k ON k.key_id=a.key_id
    WHERE a.native_organisation_id=$1 AND a.organisation_id=$2 AND a.admission_id=$3 AND k.active FOR SHARE OF a,k`,[native,org,admission])).rows[0];
   if(!origin)throw new NotFoundException("Reviewed admission is unavailable.");
   const profile=await this.learners.nativeProfileSnapshot(actor,org,admission,sql);
   const delivery=(await sql.query<{revision:string}>("SELECT revision::text FROM foundation_student_deliveries WHERE native_organisation_id=$1 AND organisation_id=$2 AND learner_id=$3 FOR SHARE",[native,org,admission])).rows[0];
   if(!delivery)throw new ConflictException("Admission has no current delivery revision.");
   return {nativeOrganisationId:native,nativeUserId:user,organisationId:org,admissionId:admission,
    nativeStudentId:origin.native_student_id,originVersion:Number(origin.origin_version),originFingerprint:origin.origin_fingerprint,
    state:origin.state,revision:Number(delivery.revision),...profile};
  });
 }

 async review(actor:Account,native:string,user:string,body:Record<string,unknown>) {
  if(!/^[1-9][0-9]{0,14}$/.test(native) || !/^[1-9][0-9]{0,14}$/.test(user))throw new BadRequestException("Invalid native staff identity.");
  if(Object.keys(body).some(k=>!["proof","fields","reviewConfirmed"].includes(k)) || body.reviewConfirmed!==true ||
    !body.proof || typeof body.proof!=="object" || Array.isArray(body.proof) ||
    !body.fields || typeof body.fields!=="object" || Array.isArray(body.fields))throw new BadRequestException("Review the admission fields before confirming.");
  const proof=body.proof as Record<string,unknown>,fields=body.fields as Record<string,unknown>;
  const allowed=["code","name","age","class_label","guardian_name","guardian_phone","custom_values","group_id","confirmDuplicate"];
  if(Object.keys(fields).some(k=>!allowed.includes(k)))throw new BadRequestException("Use enrolment fields without identity or authority overrides.");
  const bytes=JSON.stringify(canonical(fields));
  if(bytes.length>32000)throw new BadRequestException("Admission review is too large.");
  const reviewHash=createHash("sha256").update(bytes).digest("hex");
  return this.db.transaction(async sql=>{
   const mapping=(await sql.query<{organisation_id:string}>(`SELECT f.organisation_id FROM foundation_organisations f JOIN foundation_staff s ON s.native_organisation_id=f.native_id
    WHERE f.native_id=$1 AND s.native_user_id=$2 AND s.user_id=$3 AND f.active AND s.active FOR SHARE OF f,s`,[native,user,actor.id])).rows[0];
   if(!mapping)throw new NotFoundException("Enrolment staff identity is unavailable.");
   const org=mapping.organisation_id;await this.access.lock(sql,org);
   await this.access.require(actor,org,"learners.create",sql);await this.access.require(actor,org,"learners.view",sql);
   if(typeof proof.keyId!=="string" || !/^[a-f0-9]{64}$/.test(proof.keyId))throw new BadRequestException("Provide a native admission proof.");
   const key=(await sql.query<{public_key:string}>("SELECT public_key FROM foundation_native_signers WHERE key_id=$1 AND active FOR SHARE",[proof.keyId])).rows[0];
   if(!key)throw new ForbiddenException("Native admission signer is unavailable.");
   const receipt=verifyStudentAdmissionReceipt(proof,key.public_key);
   if(receipt.nativeOrganisationId!==native || receipt.nativeUserId!==user)throw new ForbiddenException("Admission proof does not match this organisation and reviewer.");
   const prior=(await sql.query<{learner_id:string;native_student_id:string;origin_version:string;origin_fingerprint:string;review_fingerprint:string}>(`SELECT learner_id,native_student_id::text,origin_version::text,origin_fingerprint,review_fingerprint
    FROM foundation_student_admissions WHERE native_organisation_id=$1 AND admission_id=$2 FOR UPDATE`,[native,receipt.admissionId])).rows[0];
   if(prior){
    if(prior.native_student_id!==receipt.nativeStudentId || Number(prior.origin_version)!==receipt.version ||
      prior.origin_fingerprint!==receipt.fingerprint || prior.review_fingerprint!==reviewHash)throw new ConflictException("Admission review changed; existing identity cannot be reassigned.");
    await this.learners.nativeProfileSnapshot(actor,org,prior.learner_id,sql);
    return {learnerId:prior.learner_id,admissionId:receipt.admissionId};
   }
   if((await sql.query("SELECT admission_id FROM foundation_student_admissions WHERE native_organisation_id=$1 AND native_student_id=$2",[native,receipt.nativeStudentId])).rows.length ||
      (await sql.query("SELECT learner_id FROM foundation_learners WHERE native_organisation_id=$1 AND native_student_id=$2",[native,receipt.nativeStudentId])).rows.length)
     throw new ConflictException("Native student identity already has a link requiring review.");
   const created=await this.learners.createReviewedAdmission(sql,actor,org,fields,receipt.admissionId);
   await sql.query(`INSERT INTO foundation_student_admissions(native_organisation_id,organisation_id,admission_id,learner_id,native_student_id,origin_version,origin_fingerprint,review_fingerprint,key_id,reviewed_by)
    VALUES($1,$2,$3,$3,$4,$5,$6,$7,$8,$9)`,[native,org,receipt.admissionId,receipt.nativeStudentId,receipt.version,receipt.fingerprint,reviewHash,proof.keyId,actor.id]);
   await this.access.audit(sql,actor,org,"foundation.admission_reviewed",{admissionId:receipt.admissionId,learnerId:created.id});
   return {learnerId:created.id,admissionId:receipt.admissionId};
  });
 }
}
