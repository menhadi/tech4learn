import React from "react";
import { createRoot } from "react-dom/client";
import { StudentExamAttempt } from "../../apps/admin/src/StudentExamAttempt";
import { ExamResults } from "../../apps/admin/src/ExamResults";
import { DraftScope } from "../../apps/admin/src/DraftForm";
import data from "../../.local/pilot-native-transcript.json";

const student = document.getElementById("student")!,
  staff = document.getElementById("staff")!;
let answered = false,
  submitted = false,
  marked = false,
  published = false;
let answerRequests: string[] = [],
  markRequests: string[] = [];
const answer = data.resumed.questions[0].answer;
const pending = data.review.questions[0];
window.fetch = async (input, options = {}) => {
  const url = String(input),
    body = options.body ? JSON.parse(String(options.body)) : null;
  if (!url.includes(`/organisations/${data.org}/`))
    throw Error("Unexpected organisation route");
  let result;
  if (url.includes("/student-exam/attempt/")) {
    const action = url.split("/").at(-1);
    if (action === "prepare") result = data.prepared;
    else if (action === "start")
      result = answered ? data.resumed : data.started;
    else if (action === "answer") {
      if (
        body.attempt_id !== data.started.attempt_id ||
        body.question_id !== data.question ||
        body.fields.option_selected !== answer
      )
        throw Error("Wrong native answer request");
      answerRequests.push(JSON.stringify(body));
      answered = true;
      if (answerRequests.length === 1)
        throw Error("Synthetic lost answer response");
      result = data.ack;
    } else if (action === "submit") {
      if (!answered) throw Error("Submitted before saving");
      submitted = true;
      result = data.finished;
    } else if (action === "result")
      result = published ? data.published : data.finished;
    else if (action === "history")
      result = published ? data.history : { exam_id: data.exam, items: [] };
    else throw Error("Unexpected student action");
  } else if (url.includes("/learners?"))
    result = {
      items: [
        { id: data.learner, name: "Synthetic pilot candidate", code: "PILOT" },
      ],
      total: 1,
      filtered: 1,
    };
  else if (options.method === "POST") {
    if (
      !submitted ||
      body.marks[pending.stat_id] !== 7 ||
      body.revision !== data.review.revision
    )
      throw Error("Wrong native marking request");
    markRequests.push(JSON.stringify(body));
    marked = true;
    if (markRequests.length === 1)
      throw Error("Synthetic lost marking response");
    result = data.graded;
  } else if (url.endsWith("/" + data.started.attempt_id))
    result = marked ? data.marked_review : data.review;
  else
    result = marked
      ? data.marked_list
      : {
          ...data.marked_list,
          items: data.marked_list.items.map((row) => ({
            ...row,
            ...data.review.summary,
            pending_count: 1,
          })),
        };
  return Response.json(result);
};
createRoot(student).render(
  <StudentExamAttempt base={`/organisations/${data.org}/student-exam`} />,
);
const button = (root: Element, text: string) =>
  [...root.querySelectorAll("button")].find((el) => el.textContent === text)!;
async function until(test: () => unknown) {
  for (let i = 0; i < 300; i++) {
    await new Promise((resolve) => setTimeout(resolve, 50));
    if (test()) return;
  }
  throw Error("Timed out");
}
async function run() {
  await until(() => button(student, "Check exam setup"));
  button(student, "Check exam setup").click();
  await until(() => button(student, "Start or resume exam"));
  button(student, "Start or resume exam").click();
  await until(
    () =>
      student.querySelector("textarea") &&
      !student.querySelector("fieldset")!.disabled,
  );
  const input = student.querySelector("textarea")!;
  Object.getOwnPropertyDescriptor(
    HTMLTextAreaElement.prototype,
    "value",
  )!.set!.call(input, answer);
  input.dispatchEvent(new Event("input", { bubbles: true }));
  await until(() => !button(student, "Save answer").disabled);
  button(student, "Save answer").click();
  await until(() => button(student, "Retry last request"));
  button(student, "Retry last request").click();
  await until(() => !student.querySelector("fieldset")!.disabled);
  if (answerRequests.length !== 2 || answerRequests[0] !== answerRequests[1])
    throw Error("Answer retry changed");
  button(student, "Finish exam").click();
  await until(() => button(student, "Submit saved answers"));
  button(student, "Submit saved answers").click();
  await until(() => student.textContent!.includes("Exam submitted"));
  if (student.textContent!.includes("70"))
    throw Error("Unpublished score exposed");
  createRoot(staff).render(
    <DraftScope user="synthetic-pilot-marker" org={data.org}>
      <ExamResults org={data.org} />
    </DraftScope>,
  );
  await until(() => button(staff, "View results"));
  button(staff, "View results").click();
  await until(() => button(staff, "Review result"));
  button(staff, "Review result").click();
  await until(() => staff.querySelector(`[name="mark-${pending.stat_id}"]`));
  await until(() => staff.textContent!.includes(String(answer)));
  const mark = staff.querySelector<HTMLInputElement>(
    `[name="mark-${pending.stat_id}"]`,
  )!;
  mark.value = "7";
  mark.dispatchEvent(new Event("input", { bubbles: true }));
  await until(() => !button(staff, "Save all marks").disabled);
  button(staff, "Save all marks").click();
  await until(() => button(staff, "Retry saving marks"));
  button(staff, "Retry saving marks").click();
  await until(
    () =>
      button(staff, "Review result") &&
      !button(staff, "Choose another student").disabled,
  );
  if (markRequests.length !== 2 || markRequests[0] !== markRequests[1])
    throw Error("Marking retry changed");
  button(student, "Refresh result").click();
  await until(() => !button(student, "Refresh result").disabled);
  if (student.textContent!.includes("70"))
    throw Error("Marking published results implicitly");
  // Publication was verified through the real native authoring action in the transcript producer.
  published = true;
  button(student, "Refresh result").click();
  await until(() => student.textContent!.includes("70"));
  button(staff, "Review result").click();
  await until(() =>
    staff.textContent!.includes("No answers are awaiting manual marking."),
  );
  document.getElementById("status")!.textContent =
    "PASS: native payloads render through student answer/retry/submission, staff marking/retry and published result refresh";
}
run().catch((error) => {
  document.getElementById("status")!.textContent = "FAIL: " + error.message;
});
