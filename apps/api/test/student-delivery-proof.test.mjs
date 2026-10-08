import {test} from 'node:test';
import assert from 'node:assert/strict';
import {generateKeyPairSync,sign,randomUUID} from 'node:crypto';
import {nativeStudentPublicKey,verifyStudentDeliveryReceipt} from '../dist/student-delivery-proof.js';

test('native delivery proofs bind IDs and revisions and reject tampering, expiry and authority extras',()=>{
  const {publicKey,privateKey}=generateKeyPairSync('rsa',{modulusLength:2048});
  const pem=publicKey.export({type:'spki',format:'pem'}).toString();
  const keyId=nativeStudentPublicKey(pem).keyId;
  const value={issuer:'tech4learn-native-student-v1',nativeOrganisationId:'2',nativeUserId:'5',nativeStudentId:'99',learnerId:randomUUID(),revision:1,issuedAt:1000};
  const envelope=v=>{const bytes=Buffer.from(JSON.stringify(v));return {keyId,receipt:bytes.toString('base64url'),signature:sign('sha256',bytes,privateKey).toString('base64url')};};
  assert.deepEqual(verifyStudentDeliveryReceipt(envelope(value),pem,1000),value);
  for(const changed of [{...value,issuedAt:699},{...value,issuedAt:1061},{...value,revision:0},{...value,nativeStudentId:'0'},{...value,organisationId:randomUUID()}])
    assert.throws(()=>verifyStudentDeliveryReceipt(envelope(changed),pem,1000));
  const tampered=envelope(value);tampered.receipt=envelope({...value,nativeStudentId:'100'}).receipt;
  assert.throws(()=>verifyStudentDeliveryReceipt(tampered,pem,1000));
  assert.throws(()=>verifyStudentDeliveryReceipt({...envelope(value),keyId:'0'.repeat(64)},pem,1000));
  assert.throws(()=>nativeStudentPublicKey(privateKey.export({type:'pkcs8',format:'pem'}).toString()));
  const weak=generateKeyPairSync('rsa',{modulusLength:1024});
  assert.throws(()=>nativeStudentPublicKey(weak.publicKey.export({type:'spki',format:'pem'}).toString()));
});
