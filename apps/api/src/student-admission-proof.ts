import {BadRequestException} from "@nestjs/common";
import {verify} from "node:crypto";
import {nativeStudentPublicKey} from "./student-delivery-proof.js";

export type StudentAdmissionReceipt={issuer:string;nativeOrganisationId:string;nativeUserId:string;nativeStudentId:string;
  admissionId:string;version:number;fingerprint:string;issuedAt:number};
/** Cryptographic origin only. Callers must separately check current actor, tenant, scope and pinned-key activity. */
export function verifyStudentAdmissionReceipt(body:Record<string,unknown>,pem:string,now=Math.floor(Date.now()/1000)):StudentAdmissionReceipt {
  const invalid=()=>new BadRequestException("Native admission proof is invalid or expired.");
  try {
    if(Object.keys(body).some(k=>!["keyId","receipt","signature"].includes(k)) ||
      typeof body.keyId!=="string" || !/^[a-f0-9]{64}$/.test(body.keyId) ||
      typeof body.receipt!=="string" || !/^[A-Za-z0-9_-]{1,3000}$/.test(body.receipt) ||
      typeof body.signature!=="string" || !/^[A-Za-z0-9_-]{1,1024}$/.test(body.signature))throw invalid();
    const pinned=nativeStudentPublicKey(pem);
    if(pinned.keyId!==body.keyId)throw invalid();
    const bytes=Buffer.from(body.receipt,"base64url"),signature=Buffer.from(body.signature,"base64url");
    if(bytes.toString("base64url")!==body.receipt || signature.toString("base64url")!==body.signature ||
      !verify("sha256",bytes,pinned.key,signature))throw invalid();
    const value=JSON.parse(bytes.toString("utf8"));
    const keys=["issuer","nativeOrganisationId","nativeUserId","nativeStudentId","admissionId","version","fingerprint","issuedAt"];
    if(!value || typeof value!=="object" || Array.isArray(value) || Object.keys(value).some(k=>!keys.includes(k)) ||
      value.issuer!=="tech4learn-native-admission-v1" ||
      ["nativeOrganisationId","nativeUserId","nativeStudentId"].some(k=>typeof value[k]!=="string" || !/^[1-9][0-9]{0,14}$/.test(value[k])) ||
      typeof value.admissionId!=="string" || !/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/.test(value.admissionId) ||
      !Number.isSafeInteger(value.version) || value.version<1 || value.version>=1000000000000000 ||
      typeof value.fingerprint!=="string" || !/^[a-f0-9]{64}$/.test(value.fingerprint) ||
      !Number.isSafeInteger(value.issuedAt) || value.issuedAt<now-300 || value.issuedAt>now+60)throw invalid();
    return value;
  } catch {throw invalid();}
}
