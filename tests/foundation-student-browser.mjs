// Synthetic local native student session only; no production or original data.
if(process.env.NODE_ENV==='production')throw new Error('Local synthetic browser only');
import {createRequire} from 'node:module';
import {readFileSync} from 'node:fs';
const base=new URL('../.local/tech4learn-foundation/',import.meta.url);
const require=createRequire(new URL('package.json',base));
const {chromium}=require('playwright');
const fixture=JSON.parse(readFileSync(new URL('exam-browser-fixture.json',base),'utf8'));
const credentials=JSON.parse(readFileSync(new URL('local-pilot-credentials.json',base),'utf8'));
if(!Number.isSafeInteger(fixture.exam)||!/^synthetic-exam-[a-f0-9]+@example\.invalid$/.test(fixture.login))throw new Error('Synthetic fixture required');
const browser=await chromium.launch({headless:true,args:['--host-resolver-rules=MAP two.localhost 127.0.0.1','--no-proxy-server']});
let page,stage='sign-in';
try {
 page=await browser.newPage({viewport:{width:1366,height:900}});page.setDefaultTimeout(15000);
 const runtimeErrors=[];page.on('pageerror',error=>runtimeErrors.push({name:error.name,undefinedIdentifier:error.message.match(/^([A-Za-z_$][\w$]*) is not defined/)?.[1]||null,kind:/MathJax/.test(error.message)?'mathjax':/select2/.test(error.message)?'select2':/null/.test(error.message)?'missing-element':'other'}));
 await page.goto('http://two.localhost:8001/student/signin',{waitUntil:'networkidle'});
 await page.locator('input[name="login"]').fill(fixture.login);
 await page.locator('input[name="password"]').fill(credentials.password);
 await page.locator('button[type="submit"]').click();
 await page.waitForURL(url=>!url.pathname.includes('signin'),{timeout:20000});
 stage='start';
 const response=await page.goto('http://two.localhost:8001/exam/start/'+fixture.exam,{waitUntil:'networkidle'});
 if(response?.status()!==200)throw new Error('Exam start unavailable');
 await page.getByText(fixture.subjective?'Synthetic browser: explain your answer.':'Synthetic browser: what is two plus two?',{exact:true}).first().waitFor();
 console.log('PASS: native student sign-in and exam question rendered.');
 stage='answer';
 if(fixture.subjective) {
  const chooser=page.waitForEvent('filechooser');
  await page.locator('.upload-single-btn').click();
  const extracted=page.waitForResponse(response=>response.url().endsWith('/student/answer-extraction')&&response.request().method()==='POST');
  const uploaded=page.waitForResponse(response=>response.url().endsWith('/subjective-upload')&&response.request().method()==='POST');
  const docx=process.argv.includes('--docx');
  const pdf=process.argv.includes('--pdf');
  await (await chooser).setFiles(pdf?{name:'answer.pdf',mimeType:'application/pdf',buffer:readFileSync(new URL('synthetic-answer.pdf',base))}:docx?{name:'answer.docx',mimeType:'application/vnd.openxmlformats-officedocument.wordprocessingml.document',buffer:readFileSync(new URL('synthetic-answer.docx',base))}:{name:'answer.txt',mimeType:'text/plain',buffer:Buffer.from('Synthetic uploaded explanation')});
  if(!(await extracted).ok()||!(await uploaded).ok())throw new Error('Written answer upload failed');
  if(await page.locator('textarea.answer-input').inputValue()!=='Synthetic uploaded explanation')throw new Error('Extracted answer not inserted');
  console.log('PASS: uploaded '+(pdf?'PDF':docx?'DOCX':'TXT')+' extracted into the answer and evidence saved privately.');
 } else await page.locator('.answer-input[type="radio"][value="1"]').check();
 const saved=page.waitForResponse(response=>response.url().endsWith('/student/save-answer')&&response.request().method()==='POST');
 await page.locator('#nextButton').click();
 if(!(await saved).ok())throw new Error('Answer save failed');
 stage='submit';
 await page.locator('[data-bs-target="#finalizeExamModal"]').click();
 await page.locator('#finishExamButton').click();
 await page.waitForURL(url=>url.pathname.includes('feedback'),{timeout:20000});
 stage='result';
 await page.getByRole('link',{name:'View Result',exact:true}).click();
 await page.waitForURL(url=>/^\/student\/results\/\d+$/.test(url.pathname),{timeout:20000});
 await page.locator('.score-display').filter({hasText:fixture.subjective?'0.00':'2.00'}).first().waitFor();
 if(runtimeErrors.length){console.log(JSON.stringify({runtimeErrors}));throw new Error('Browser runtime error');}
 console.log(fixture.subjective?'PASS: native student submitted an uploaded written answer and opened its result; marking remains pending.':'PASS: native student answered, submitted and viewed the two-mark result using browser controls.');
 if(fixture.subjective&&process.argv.includes('--grade')) {
  stage='teacher-marking';
  const resultId=new URL(page.url()).pathname.split('/').pop();
  const teacher=await browser.newPage();teacher.setDefaultTimeout(15000);
  await teacher.goto('http://two.localhost:8001/login',{waitUntil:'networkidle'});
  await teacher.locator('input[name="login"]').fill('synthetic@example.invalid');
  await teacher.locator('input[name="password"]').fill(credentials.password);
  await teacher.locator('button[type="submit"]').click();
  await teacher.waitForURL(url=>url.pathname!=='/login');
  const evaluation=await teacher.goto('http://two.localhost:8001/results/'+resultId+'/evaluate',{waitUntil:'networkidle'});
  if(!evaluation?.ok())throw new Error('Teacher evaluation unavailable');
  await teacher.getByText('Synthetic uploaded explanation',{exact:true}).waitFor();
  await teacher.locator('.marks-input').fill('2');
  await teacher.getByRole('button',{name:'Save & Publish Result'}).click();
  await teacher.waitForURL(url=>url.pathname==='/results');
  await page.reload({waitUntil:'networkidle'});
  await page.locator('.score-display').filter({hasText:'2.00'}).first().waitFor();
  console.log('PASS: teacher reviewed and published marks; the student saw the updated two-mark result.');
 }
 await page.screenshot({path:new URL('../.local/student-exam-review.png',import.meta.url).pathname.replace(/^\/([A-Z]:)/,'$1'),fullPage:true});
} catch {
 await page?.screenshot({path:new URL('../.local/student-exam-failure.png',import.meta.url).pathname.replace(/^\/([A-Z]:)/,'$1'),fullPage:true}).catch(()=>{});
 console.log('BLOCKED: synthetic student browser stage '+stage+'.');process.exitCode=1;
} finally {await browser.close();}
