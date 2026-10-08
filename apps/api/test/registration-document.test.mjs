import test from 'node:test';
import assert from 'node:assert/strict';
import { registrationDocument } from '../dist/registration-document.js';

function image(width=640,height=480) {
  const metadata=Buffer.from([255,225,0,8,71,80,83,45,88,88]);
  const frame=Buffer.from([255,192,0,8,8,height>>8,height&255,width>>8,width&255,1]);
  const scan=Buffer.from([255,218,0,6,1,1,0,0,11,22,33,44,255,217]);
  return Buffer.concat([Buffer.from([255,216]),metadata,frame,scan]);
}
test('registration documents strip private metadata without mixing portrait purpose',()=>{
  const source=image();const clean=registrationDocument(source.toString('base64'));
  assert.equal(clean.includes(Buffer.from('GPS-XX')),false);
  assert.equal(source.includes(Buffer.from('GPS-XX')),true);
  assert.equal(clean.subarray(-14).equals(source.subarray(-14)),true);
  const hidden=Buffer.concat([source.subarray(0,-2),Buffer.from([255,225,0,4,88,88,255,217])]);
  assert.throws(()=>registrationDocument(hidden.toString('base64')));
  const progressive=image();progressive[13]=194;
  assert.throws(()=>registrationDocument(progressive.toString('base64')));
});
test('registration document rejects oversized dimensions and malformed JPEG structure',()=>{
  for(const bytes of [image(4097),image(159),image(640,0),Buffer.alloc(5*1024*1024+1),
    Buffer.from([255,216,255,225,255,255,...Array(20).fill(0),255,217])])
    assert.throws(()=>registrationDocument(bytes.toString('base64')));
  for(const value of [null,{},'not base64','data:image/jpeg;base64,'+image().toString('base64')])
    assert.throws(()=>registrationDocument(value));
  const corrupt=image();corrupt[23]=255;corrupt[24]=255;
  assert.throws(()=>registrationDocument(corrupt.toString('base64')));
});
