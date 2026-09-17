import React from "react";
import { createRoot } from "react-dom/client";
import { StudentExamAttempt } from "../../apps/admin/src/StudentExamAttempt";
import { ExamResults } from "../../apps/admin/src/ExamResults";
import { DraftScope } from "../../apps/admin/src/DraftForm";

const student = document.getElementById("student")!,
  staff = document.getElementById("staff")!;
const button = (root: Element, label: string) =>
  [...root.querySelectorAll("button")].find((b) => b.textContent === label)!;
async function until(predicate: () => unknown) {
  for (let i = 0; i < 400; i++) {
    await new Promise((resolve) => setTimeout(resolve, 50));
    if (predicate()) return;
  }
  throw Error("Timed out waiting for screen");
}
const request = async (path: string, body?: unknown) => {
  const reply = await fetch(path, {
    method: body === undefined ? "GET" : "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Tech4Learn-Request": "1",
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await reply.text(),
    data = text ? JSON.parse(text) : null;
  if (!reply.ok) throw Error(`${reply.status}: ${JSON.stringify(data)}`);
  return data;
};
async function run() {
  const fixture = await request("/pilot-fixture"),
    { org, learner, exam } = fixture;
  const root = `/api/v1/organisations/${org}`;
  await request(root + "/student-exam/exchange", {
    token: fixture.fragment.split(".")[1],
  });
  createRoot(student).render(
    <StudentExamAttempt base={`/organisations/${org}/student-exam`} />,
  );
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
  const answer = "Two pairs each have two items, giving four.";
  Object.getOwnPropertyDescriptor(
    HTMLTextAreaElement.prototype,
    "value",
  )!.set!.call(input, answer);
  input.dispatchEvent(new Event("input", { bubbles: true }));
  await until(() => !button(student, "Save answer").disabled);
  button(student, "Save answer").click();
  await until(() => !student.querySelector("fieldset")!.disabled);
  button(student, "Finish exam").click();
  await until(() => button(student, "Submit saved answers"));
  button(student, "Submit saved answers").click();
  await until(() => student.textContent!.includes("Exam submitted"));
  if (student.textContent!.includes("70"))
    throw Error("Result published before marking");
  createRoot(staff).render(
    <DraftScope user="synthetic-connected-marker" org={org}>
      <ExamResults org={org} />
    </DraftScope>,
  );
  await until(() => button(staff, "View results"));
  button(staff, "View results").click();
  await until(() => button(staff, "Review result"));
  button(staff, "Review result").click();
  await until(
    () =>
      staff.textContent!.includes(answer) &&
      staff.querySelector('input[name^="mark-"]'),
  );
  const mark = staff.querySelector<HTMLInputElement>('input[name^="mark-"]')!;
  mark.value = "7";
  mark.dispatchEvent(new Event("input", { bubbles: true }));
  await until(() => !button(staff, "Save all marks").disabled);
  button(staff, "Save all marks").click();
  await until(
    () =>
      button(staff, "Review result") &&
      !button(staff, "Choose another student").disabled,
  );
  button(student, "Refresh result").click();
  await until(() => !button(student, "Refresh result").disabled);
  if (student.textContent!.includes("70"))
    throw Error("Marking published hidden result");
  const current = await request(
    root + `/exam-content/taxonomy/exams/${exam.id}`,
  );
  await request(
    root + `/exam-content/exams/${exam.id}/actions/set-result-status`,
    {
      fields: { result_after_finish: true },
      revision: current.revision,
      request_id: crypto.randomUUID(),
    },
  );
  button(student, "Refresh result").click();
  await until(() => student.textContent!.includes("70"));
  button(staff, "Review result").click();
  await until(() =>
    staff.textContent!.includes("No answers are awaiting manual marking."),
  );
  const history = await request(root + `/exam-results/${learner}/attempts`);
  if (history.items.length !== 1 || history.items[0].score_percent !== 70)
    throw Error("Native history mismatch");
  document.getElementById("status")!.textContent =
    "PASS: real browser → Tech4Learn HTTP API → native ExamElite: answer, submit, mark, publish and result refresh";
}
run().catch((error) => {
  document.getElementById("status")!.textContent = "FAIL: " + error.message;
});
