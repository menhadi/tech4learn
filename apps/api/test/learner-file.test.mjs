import { test } from "node:test";
import assert from "node:assert/strict";
import { csvRows, readLearnerFile } from "../../admin/src/learner-import.ts";
test("CSV import preserves quoted commas, escaped quotes, newlines and rejects malformed headers", async () => {
  assert.deepEqual(
    csvRows('code,name\r\nX1,"Synthetic, \"\"quoted\"\"\nname"\r\n'),
    [
      ["code", "name"],
      ["X1", 'Synthetic, "quoted"\nname'],
    ],
  );
  assert.throws(() => csvRows('code,name\nX,"unclosed'), /Unclosed/);
  await assert.rejects(
    readLearnerFile(new File(["code,code\nX,Y"], "test.csv")),
    /unique/,
  );
  await assert.rejects(
    readLearnerFile(new File(["code,name"], "test.xls")),
    /Choose CSV/,
  );
  const rows = await readLearnerFile(
    new File(["\uFEFFcode,name\r\n0001,Synthetic test\r\n"], "test.csv"),
  );
  assert.equal(rows[0].code, "0001");
  await assert.rejects(
    readLearnerFile(
      new File(["code,name\n" + "A,B\n".repeat(101)], "test.csv"),
    ),
    /100/,
  );
});
