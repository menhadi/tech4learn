import React from "react";
import { createRoot } from "react-dom/client";
import { GroupedMenu, organisationMenu } from "../../apps/admin/src/GroupedMenu";
import { ExamWorkspace } from "../../apps/admin/src/ExamWorkspace";
import { ExamLearnerPicker } from "../../apps/admin/src/ExamLearnerPicker";
import { ExamStudentLinks } from "../../apps/admin/src/ExamStudentLinks";
import { DraftScope } from "../../apps/admin/src/DraftForm";
import "../../apps/admin/src/styles.css";
import "../../apps/admin/src/admin-theme.css";

const element = document.getElementById("root")!;
const root = createRoot(element);
const org = "11111111-1111-4111-8111-111111111111";
const section = "22222222-2222-4222-8222-222222222222";
const student = "33333333-3333-4333-8333-333333333333";
const groups = [{id:section,name:"A",centre_id:"44444444-4444-4444-8444-444444444444",centre_name:"Synthetic centre",class_id:"55555555-5555-4555-8555-555555555555",class_name:"Class 5",academic_year_id:"66666666-6666-4666-8666-666666666666",year_name:"2026",archived:false}];
let ruleFailure = true, searchFailure = true, writes = 0;
const learnerCalls: URL[] = [];
window.fetch = async (input, options = {}) => {
  const url = new URL(String(input), location.origin);
  if (options.method && options.method !== "GET") {writes++;throw Error("Unexpected write in read-only fixture");}
  let result: unknown;
  if (url.pathname.endsWith("/exam-workspace")) {
    if(ruleFailure) {ruleFailure=false;throw Error("Synthetic access read failure");}
    result={revision:1,restrictions:["questions","subjects","taking","results"]};
  } else if(url.pathname.endsWith("/learners")) {
    learnerCalls.push(url);
    const selected=url.searchParams.get("group_id") === section;
    result={items:[{id:student,name:"Synthetic student A",code:"A"},...selected?[]:[{id:"77777777-7777-4777-8777-777777777777",name:"Synthetic student B",code:"B"}]],total:selected?1:2,filtered:selected?1:2};
  } else if(url.pathname.endsWith("/exam-student-access")) {
    result=[{id:"old",exam_name:"Expired paper",expires_at:"2000-01-01T00:00:00Z",consumed_at:null,revoked_at:null}];
  } else if(url.pathname.endsWith("/choices/exams")) {
    if(searchFailure) {searchFailure=false;throw Error("Synthetic catalogue failure");}
    result={items:url.searchParams.get("search")?[{id:9,label:"Synthetic paper"}]:[],next:null};
  } else throw Error("Unexpected read: "+url.pathname);
  return new Response(JSON.stringify(result),{headers:{"Content-Type":"application/json"}});
};
const button=(label:string)=>[...element.querySelectorAll<HTMLButtonElement>("button")].find(item=>item.textContent===label);
const assert=(value:unknown,message:string)=>{if(!value)throw Error(message);};
async function until(check:()=>unknown) {for(let n=0;n<200;n++){if(check())return;await new Promise(resolve=>setTimeout(resolve,40));}throw Error("Timed out waiting for UI");}
function inputValue(input:HTMLInputElement,value:string) {
  Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,"value")!.set!.call(input,value);
  input.dispatchEvent(new Event("input",{bubbles:true}));
}
async function run() {
  const menu=organisationMenu("Centre",["Daily overview","Learners","Exam workspace"],["FLN workspace"]);
  root.render(<GroupedMenu searchable groups={menu} active="Learners" label="Organisation sections" onSelect={()=>{}} />);
  await until(()=>element.querySelector('input[type="search"]'));
  inputValue(element.querySelector<HTMLInputElement>('input[type="search"]')!,"exam");
  await until(()=>!button("Students / enrolment") && button("Exam workspace"));
  assert(!button("Daily register"),"Search introduced an inaccessible page");
  inputValue(element.querySelector<HTMLInputElement>('input[type="search"]')!,"missing-page");
  await until(()=>element.textContent!.includes("No accessible pages match"));
  button("Clear")!.click();
  await until(()=>button("Students / enrolment"));
  assert(!button("Students / enrolment")!.closest<HTMLElement>(".menu-children")!.hidden,"Clear search hid current page");

  root.render(<ExamWorkspace org={org} />);
  await until(()=>button("Reload exam access"));button("Reload exam access")!.click();
  await until(()=>element.querySelector('.exam-tools [aria-current="page"]'));
  assert(element.querySelector('.exam-tools [aria-current="page"]')!.textContent==="Create and manage exams","Restricted question bank left an unavailable default tool");
  assert(!button("Question bank")&&!button("Student exam access"),"Restricted tools remain selectable");

  root.render(<ExamLearnerPicker org={org} groups={groups} onSelect={()=>{}} />);
  await until(()=>element.textContent!.includes("Synthetic student B"));
  const groupSelect=element.querySelector<HTMLSelectElement>('select[name="exam_student_section"]')!;
  groupSelect.value=section;groupSelect.dispatchEvent(new Event("change",{bubbles:true}));
  await until(()=>learnerCalls.at(-1)?.searchParams.get("group_id")===section && element.textContent!.includes("Synthetic student A")&&!element.textContent!.includes("Synthetic student B"));
  assert(learnerCalls.at(-1)!.searchParams.get("offset")==="0","Section filter did not reset pagination");

  root.render(<DraftScope user="synthetic-staff" org={org}><ExamStudentLinks org={org} groups={groups} /></DraftScope>);
  await until(()=>element.querySelector("h3")?.textContent === "Student exam access");
  await until(()=>button("Select student")&&!button("Select student")!.disabled);button("Select student")!.click();
  await until(()=>button("Retry exam search"));button("Retry exam search")!.click();
  await until(()=>element.textContent!.includes("No organisation exams match"));
  inputValue(element.querySelector<HTMLInputElement>('input[type="search"]')!,"paper");
  await until(()=>element.querySelector('option[value="9"]'));
  const examSelect=[...element.querySelectorAll("select")].find(select=>select.querySelector('option[value="9"]'))!;
  examSelect.value="9";examSelect.dispatchEvent(new Event("change",{bubbles:true}));
  await until(()=>button("Create link and replace previous access")&&!button("Create link and replace previous access")!.disabled);
  assert(button("Revoke access")!.disabled,"Expired access can still be revoked from UI");
  searchFailure=true;inputValue(element.querySelector<HTMLInputElement>('input[type="search"]')!,"denied");
  await until(()=>button("Retry exam search"));
  assert(button("Create link and replace previous access")!.disabled,"Failed lookup left link creation enabled");
  element.querySelector("form")!.dispatchEvent(new Event("submit",{bubbles:true,cancelable:true}));
  await until(()=>element.textContent!.includes("Load organisation exams successfully"));
  assert(writes===0,"Failed catalogue allowed a write");
  document.getElementById("status")!.textContent="PASS: accessible menu search/reset, exam access retry/default, scoped section filtering and failed-catalogue write blocking";
}
run().catch(error=>{document.getElementById("status")!.textContent="FAIL: "+error.message;});
