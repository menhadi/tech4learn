// Owned headless browser against the fixed synthetic loopback tenant only.
// Requires the guarded native fixture and --tenant-browser API pilot; no production data.
if(process.env.NODE_ENV==='production')throw new Error('Local synthetic browser only');
import {createRequire} from 'node:module';
import {readFileSync} from 'node:fs';
const require=createRequire(new URL('../.local/tech4learn-foundation/package.json',import.meta.url));
const {chromium}=require('playwright');
const credentials=JSON.parse(readFileSync(new URL('../.local/tech4learn-foundation/local-pilot-credentials.json',import.meta.url),'utf8'));
const captureMode=process.argv.includes('--capture');
const reviewMode=process.argv.includes('--review');
const examMode=process.argv.includes('--exams');
const browser=await chromium.launch({headless:true,args:['--host-resolver-rules=MAP two.localhost 127.0.0.1','--no-proxy-server',...(captureMode?['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream']:[])]});
let activePage;
try {
 const page=await browser.newPage({viewport:{width:1366,height:900},...(captureMode?{permissions:['camera','geolocation'],geolocation:{latitude:0,longitude:0,accuracy:5}}:{})});
 activePage=page;
 page.setDefaultTimeout(15000);
 if(examMode)await page.route('**/select2.min.js',route=>route.abort());
 const runtimeErrors=[];page.on('pageerror',error=>runtimeErrors.push({name:error.name,kind:/jQuery|\$ is not defined/.test(error.message)?'jquery':/select2/.test(error.message)?'select2':/randomUUID/.test(error.message)?'crypto':'other'}));
 await page.goto('http://two.localhost:8001/login',{waitUntil:'domcontentloaded',timeout:15000});
 await page.locator('input[name="login"]').fill('synthetic@example.invalid');
 await page.locator('input[name="password"]').fill(credentials.password);
 await page.locator('button[type="submit"]').click();
 await page.waitForTimeout(1500);
 console.log(JSON.stringify({loginIssue:await page.locator('body').evaluate(n=>{const t=n.innerText;return {invalid:/invalid|incorrect/i.test(t),connection:/unavailable|connection/i.test(t),linked:/linked attendance/i.test(t)};})}));
 await page.goto('http://two.localhost:8001/attendance',{waitUntil:'networkidle',timeout:15000});
 console.log(JSON.stringify({pathname:new URL(page.url()).pathname,headings:await page.locator('h1,h2,h3').allTextContents(),menus:await page.getByRole('navigation').allTextContents(),alerts:await page.getByRole('alert').count()}));
 await page.getByRole('button',{name:'Centres',exact:true}).click();
 await page.getByRole('button',{name:'Classes and sections',exact:true}).click();
 await page.getByText('Sections and groups',{exact:true}).first().waitFor({timeout:10000});
 console.log('PASS: actual browser academic setup view loaded.');
 if(!await page.evaluate(()=>isSecureContext&&typeof crypto.randomUUID==='function'))throw new Error('Secure local context required');
 await page.getByRole('button',{name:'Attendance learners',exact:true}).click();
 await page.getByText('Synthetic learner',{exact:true}).first().waitFor({timeout:25000});
 console.log(JSON.stringify({learnerHeadings:await page.locator('h2,h3').allTextContents(),alerts:await page.getByRole('alert').count()}));
 await page.getByRole('button',{name:'Add learner',exact:true}).click();
 const form=page.locator('form').filter({has:page.getByRole('button',{name:'Save enrolment',exact:true})});
 await form.locator('input[name="name"]').fill('Synthetic browser enrolment '+Date.now());
 const section=await form.locator('select[name="group_id"] option').evaluateAll(options=>options.find(o=>o.value&&o.textContent.includes('Synthetic capture section'))?.value);
 if(!section)throw new Error('Synthetic capture section missing');
 await form.locator('select[name="group_id"]').selectOption(section);
 await page.getByRole('button',{name:'Save enrolment',exact:true}).click();
 await page.getByText('Student enrolment saved. Photos can be added or retaken separately.',{exact:true}).waitFor({timeout:25000});
 console.log('PASS: actual browser learner form saved an enrolment through the native gateway.');
 if(captureMode) {
  await page.getByRole('button',{name:'Daily attendance',exact:true}).click();
  await page.getByRole('button',{name:'Refresh',exact:true}).click();
  await page.locator('select[name="group_id"]').first().selectOption(section);
  await page.getByRole('button',{name:'Open camera',exact:true}).click();
  await page.getByLabel('Live attendance camera preview').waitFor({timeout:20000});
  await page.waitForFunction(()=>document.querySelector('video[aria-label="Live attendance camera preview"]')?.readyState>=2,{},{timeout:15000});
  await page.getByRole('button',{name:'Take photo and get location',exact:true}).click();
  await page.getByRole('button',{name:'Submit for review',exact:true}).waitFor({timeout:20000});
  await page.getByRole('button',{name:'Submit for review',exact:true}).click();
  await page.getByText('Submitted for review. No learner marks have been confirmed yet.',{exact:true}).waitFor({timeout:25000});
  console.log('PASS: virtual-camera browser capture with synthetic location submitted for teacher review.');
 }
 if(reviewMode) {
  await page.getByRole('button',{name:'Daily attendance',exact:true}).click();
  await page.locator('tr').filter({hasText:'Synthetic capture section'}).filter({hasText:'pending'}).getByRole('button',{name:'Open attendance',exact:true}).first().click();
  const marks=page.locator('select[name^="mark:"]');
  await marks.first().waitFor({timeout:15000});
  const history=page.locator('summary').filter({hasText:'Review and correction history'});
  const initialHistory=Number((await history.innerText()).match(/\((\d+)\)/)?.[1]);
  if(!Number.isInteger(initialHistory))throw new Error('History count missing');
  if(!await marks.count())throw new Error('Synthetic roster missing');
  for(const mark of await marks.all())await mark.selectOption('present');
  await page.getByLabel('Review / correction reason',{exact:true}).fill('Synthetic browser teacher review');
  const warning=page.getByLabel('I have reviewed the location warnings for every photo.',{exact:true});
  if(await warning.count())await warning.check();
  await page.getByRole('button',{name:'Confirm attendance',exact:true}).click();
  await page.getByText('Attendance confirmed. The review is recorded in its history.',{exact:true}).waitFor({timeout:25000});
  await marks.first().selectOption('excused');
  await page.getByLabel('Review / correction reason',{exact:true}).fill('Synthetic browser correction');
  if(await warning.count())await warning.check();
  await page.getByRole('button',{name:'Save correction',exact:true}).click();
  await page.getByText(`Review and correction history (${initialHistory+2})`,{exact:true}).waitFor({timeout:25000});
  await history.click();
  await page.locator('.record-row p').filter({hasText:'Synthetic browser teacher review'}).waitFor();
  await page.locator('.record-row p').filter({hasText:'Synthetic browser correction'}).waitFor();
  if(await marks.first().inputValue()!=='excused')throw new Error('Correction not retained');
  console.log('PASS: browser teacher confirmation and correction retained both history entries.');
 }
 if(examMode) {
  for(const path of ['/exams','/exams/create','/questions','/questions/create']) {
   const response=await page.goto('http://two.localhost:8001'+path,{waitUntil:'networkidle',timeout:25000});
   if(response?.status()!==200||new URL(page.url()).pathname!==path)throw new Error('Native exam page unavailable');
   if(path==='/exams/create')await page.locator('input[name="name"]').waitFor();
   if(path==='/questions/create')await page.locator('textarea[name="question"]').waitFor({state:'attached'});
  }
  console.log('PASS: signed-in native exam and question directories and authoring forms loaded.');
 }
 if(runtimeErrors.length){console.log(JSON.stringify({runtimeErrors}));throw new Error('Browser runtime error');}
 await page.screenshot({path:new URL('../.local/embedded-setup-review.png',import.meta.url).pathname.replace(/^\/([A-Z]:)/,'$1'),fullPage:true});
} catch {await activePage?.screenshot({path:new URL('../.local/embedded-setup-failure.png',import.meta.url).pathname.replace(/^\/([A-Z]:)/,'$1'),fullPage:true}).catch(()=>{});console.log('BLOCKED: synthetic local sign-in or embedded workspace review failed.');process.exitCode=1;}
finally {await browser.close();}
