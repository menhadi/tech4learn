import {BadRequestException,ForbiddenException,ConflictException} from "@nestjs/common";
import {createHash,createPublicKey,verify,randomUUID} from "node:crypto";
import type {Database} from "./database.js";
import {uuid} from "./security.js";

export function nativeStudentPublicKey(pem:string) {
  try {
    if(pem.length>8192 || !pem.trim().startsWith('-----BEGIN PUBLIC KEY-----') || pem.includes('PRIVATE KEY'))throw new Error();
    const key=createPublicKey(pem);
    if(key.asymmetricKeyType!=='rsa' || (key.asymmetricKeyDetails?.modulusLength??0)<2048)throw new Error();
    const publicKey=key.export({type:'spki',format:'pem'}).toString();
    const keyId=createHash('sha256').update(key.export({type:'spki',format:'der'})).digest('hex');
    return {keyId,publicKey,key};
  } catch {throw new BadRequestException('Provide the reviewed native installation RSA public key (2048 bits or more).');}
}

export async function registerNativeStudentKey(db:Database,actorId:string,pem:string) {
  const actor=uuid(actorId),key=nativeStudentPublicKey(pem);
  return db.transaction(async sql=>{
    const stored=(await sql.query<{is_superadmin:boolean}>('SELECT is_superadmin FROM users WHERE id=$1 FOR SHARE',[actor])).rows[0];
    if(!stored?.is_superadmin)throw new ForbiddenException('Only the stored platform administrator can register a native signing key.');
    const inserted=(await sql.query('INSERT INTO foundation_native_signers(key_id,public_key,created_by) VALUES($1,$2,$3) ON CONFLICT DO NOTHING RETURNING key_id',[key.keyId,key.publicKey,actor])).rows.length;
    if(!inserted){
      const existing=(await sql.query<{public_key:string;active:boolean}>('SELECT public_key,active FROM foundation_native_signers WHERE key_id=$1 FOR SHARE',[key.keyId])).rows[0];
      if(!existing?.active || existing.public_key!==key.publicKey)throw new ConflictException('The native signing key is revoked or changed; retry cannot repair it.');
    } else await sql.query("INSERT INTO audit_events(id,actor_id,action,details) VALUES($1,$2,'foundation.native_key_registered',$3)",[randomUUID(),actor,JSON.stringify({keyId:key.keyId})]);
    return {keyId:key.keyId,created:!!inserted};
  });
}

export type StudentDeliveryReceipt={issuer:string;nativeOrganisationId:string;nativeUserId:string;nativeStudentId:string;learnerId:string;revision:number;issuedAt:number};
export function verifyStudentDeliveryReceipt(body:Record<string,unknown>,pem:string,now=Math.floor(Date.now()/1000)):StudentDeliveryReceipt {
  const invalid=()=>new BadRequestException('Native student delivery receipt is invalid or expired.');
  try {
    if(typeof body.keyId!=='string' || !/^[a-f0-9]{64}$/.test(body.keyId) ||
       typeof body.receipt!=='string' || !/^[A-Za-z0-9_-]{1,3000}$/.test(body.receipt) ||
       typeof body.signature!=='string' || !/^[A-Za-z0-9_-]{1,1024}$/.test(body.signature))throw invalid();
    const pinned=nativeStudentPublicKey(pem);
    if(pinned.keyId!==body.keyId)throw invalid();
    const bytes=Buffer.from(body.receipt,'base64url'),signature=Buffer.from(body.signature,'base64url');
    if(bytes.toString('base64url')!==body.receipt || signature.toString('base64url')!==body.signature ||
      !verify('sha256',bytes,pinned.key,signature))throw invalid();
    const value=JSON.parse(bytes.toString('utf8'));
    const keys=['issuer','nativeOrganisationId','nativeUserId','nativeStudentId','learnerId','revision','issuedAt'];
    if(!value || typeof value!=='object' || Array.isArray(value) || Object.keys(value).some(k=>!keys.includes(k)) ||
      value.issuer!=='tech4learn-native-student-v1' ||
      ['nativeOrganisationId','nativeUserId','nativeStudentId'].some(k=>typeof value[k]!=='string'||!/^[1-9][0-9]{0,14}$/.test(value[k])) ||
      typeof value.learnerId!=='string' || !/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/.test(value.learnerId) ||
      !Number.isSafeInteger(value.revision) || value.revision<1 || value.revision>=1000000000000000 ||
      !Number.isSafeInteger(value.issuedAt) || value.issuedAt<now-300 || value.issuedAt>now+60)throw invalid();
    return value;
  } catch {throw invalid();}
}

