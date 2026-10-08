import {test} from 'node:test';
import assert from 'node:assert/strict';
import {generateKeyPairSync,sign,randomUUID} from 'node:crypto';
import {nativeStudentPublicKey,verifyStudentDeliveryReceipt} from '../dist/student-delivery-proof.js';
import {verifyStudentAdmissionReceipt} from '../dist/student-admission-proof.js';

test('admission proof rejects cross-purpose signatures, expiry, tampering and client authority',()=>{
 const {publicKey,privateKey}=generateKeyPairSync('rsa',{modulusLength:2048});
 const pem=publicKey.export({type:'spki',format:'pem'}).toString(),keyId=nativeStudentPublicKey(pem).keyId;
 const value={issuer:'tech4learn-native-admission-v1',nativeOrganisationId:'2',nativeUserId:'5',nativeStudentId:'99',
  admissionId:randomUUID(),version:1,fingerprint:'a'.repeat(64),issuedAt:1000};
 const envelope=v=>{const bytes=Buffer.from(JSON.stringify(v));return {keyId,receipt:bytes.toString('base64url'),signature:sign('sha256',bytes,privateKey).toString('base64url')};};
 assert.deepEqual(verifyStudentAdmissionReceipt(envelope(value),pem,1000),value);
 for(const change of [{issuedAt:699},{issuedAt:1061},{version:0},{fingerprint:'bad'},{nativeStudentId:'0'},
  {issuer:'tech4learn-native-student-v1'},{organisationId:randomUUID()},{consent:true}])
  assert.throws(()=>verifyStudentAdmissionReceipt(envelope({...value,...change}),pem,1000));
 const tampered=envelope(value);tampered.receipt=envelope({...value,nativeStudentId:'100'}).receipt;
 assert.throws(()=>verifyStudentAdmissionReceipt(tampered,pem,1000));
 assert.throws(()=>verifyStudentAdmissionReceipt({...envelope(value),nativeStudentId:'100'},pem,1000));
 assert.throws(()=>verifyStudentAdmissionReceipt({...envelope(value),keyId:'0'.repeat(64)},pem,1000));
 assert.throws(()=>verifyStudentDeliveryReceipt(envelope(value),pem,1000));
});
