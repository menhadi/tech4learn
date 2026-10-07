// Owned headless browser against the fixed synthetic loopback tenant only.
// Requires the guarded native fixture and --tenant-browser API pilot; no production data.
if(process.env.NODE_ENV==='production')throw new Error('Local synthetic browser only');
import {createRequire} from 'node:module';
import {readFileSync} from 'node:fs';
const require=createRequire(new URL('../.local/tech4learn-foundation/package.json',import.meta.url));
const {chromium}=require('playwright');
const credentials=JSON.parse(readFileSync(new URL('../.local/tech4learn-foundation/local-pilot-credentials.json',import.meta.url),'utf8'));
const browser=await chromium.launch({headless:true,args:['--host-resolver-rules=MAP two.localhost 127.0.0.1','--no-proxy-server']});
try {
 const page=await browser.newPage({viewport:{width:1366,height:900}});
 const runtimeErrors=[];page.on('pageerror',()=>runtimeErrors.push(true));
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
 if(runtimeErrors.length)throw new Error('Browser runtime error');
 await page.screenshot({path:new URL('../.local/embedded-setup-review.png',import.meta.url).pathname.replace(/^\/([A-Z]:)/,'$1'),fullPage:true});
} catch {console.log('BLOCKED: synthetic local sign-in or embedded workspace review failed.');process.exitCode=1;}
finally {await browser.close();}
