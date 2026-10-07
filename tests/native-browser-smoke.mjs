// Synthetic loopback-only native browser renderer smoke check; no application/database sign-in.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {spawn} from 'node:child_process';
import {mkdirSync,mkdtempSync,writeFileSync,readFileSync,existsSync} from 'node:fs';
import {resolve,join} from 'node:path';
const root=resolve(process.argv[2]||'');
if(process.argv.length!==3 || !existsSync(join(root,'scripts/exam-quality-browser.mjs')))throw new Error('Provide a prepared native dependency root');
mkdirSync('.local',{recursive:true});const dir=mkdtempSync(resolve('.local/native-browser-smoke-'));
const server=createServer((req,res)=>{
 if(req.url==='/missing.png'){res.writeHead(404);res.end();return;}
 res.setHeader('Content-Type','text/html; charset=utf-8');
 res.end(req.url==='/good'?'<!doctype html><html><body><div data-qa-question-body>Synthetic question: two plus two?</div><div data-qa-option>Four</div><div data-qa-option>Five</div></body></html>':'<!doctype html><html><body><div data-qa-question-body>Synthetic broken image question</div><img src="/missing.png"></body></html>');
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
try{
 const base='http://127.0.0.1:'+server.address().port;
 const manifest={items:[{question_id:1,url:base+'/good',screenshot:join(dir,'good.png')},{question_id:2,url:base+'/broken',screenshot:join(dir,'broken.png')}],output:join(dir,'results.json')};
 const input=join(dir,'manifest.json');writeFileSync(input,JSON.stringify(manifest));
 await new Promise((ok,fail)=>{
  const child=spawn(process.execPath,[join(root,'scripts/exam-quality-browser.mjs'),input],{cwd:root,stdio:['ignore','ignore','pipe'],timeout:30000});
  child.on('error',fail);child.stderr.on('data',()=>{});
  child.on('exit',code=>code===0?ok():fail(new Error('Native browser renderer failed; verify its locked package and browser runtime')));
 });
 const output=JSON.parse(readFileSync(manifest.output,'utf8'));
 assert.deepEqual(output.items[0].findings,[]);
 assert.equal(output.items[1].findings.some(f=>f.type==='broken_rendered_image'),true);
 assert.equal(output.items.some(i=>i.findings.some(f=>f.type==='browser_exception')),false);
 for(const name of ['good.png','broken.png'])assert.equal(readFileSync(join(dir,name)).subarray(1,4).toString(),'PNG');
 console.log('PASS: native synthetic question rendering, screenshot creation and broken-image detection.');
 console.log('Synthetic local evidence: '+dir);
}finally{await new Promise(resolve=>server.close(resolve));}
