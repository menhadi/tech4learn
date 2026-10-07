import fs from 'node:fs';
import { chromium } from 'playwright';

const [inputPath, outputPath] = process.argv.slice(2);
if (!inputPath || !outputPath) throw new Error('Expected input and output paths.');
const input = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
const settings = input.settings || {};
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ userAgent: 'ExamElite Official Exam Monitor/1.0' });
page.setDefaultTimeout(Math.min(60000, Number(settings.timeout_ms || 30000)));

const resultHtml = [];
try {
  await page.goto(input.url, { waitUntil: settings.wait_until || 'domcontentloaded' });
  const yearSelector = String(settings.year_selector || '');
  const examSelector = String(settings.exam_selector || '');
  const submitSelector = String(settings.submit_selector || '');
  const resultSelector = String(settings.results_selector || 'body');
  const max = Math.max(1, Math.min(100, Number(settings.max_combinations || 30)));
  const values = async selector => selector ? page.locator(selector).locator('option:not([disabled])').evaluateAll(options => options.map(o => o.value).filter(v => v && v !== '0' && v.toLowerCase() !== 'all')) : [''];
  const years = await values(yearSelector);
  let combinations = 0;
  for (const year of years) {
    if (yearSelector) {
      await page.selectOption(yearSelector, year);
      await page.waitForTimeout(Number(settings.dependent_wait_ms || 500));
    }
    const exams = await values(examSelector);
    for (const exam of exams) {
      if (examSelector) await page.selectOption(examSelector, exam);
      if (submitSelector) {
        await page.click(submitSelector);
        await page.waitForLoadState(settings.result_wait_until || 'domcontentloaded').catch(() => {});
      }
      await page.waitForSelector(resultSelector);
      resultHtml.push(await page.locator(resultSelector).evaluate(element => element.outerHTML));
      combinations++;
      if (combinations >= max) break;
    }
    if (combinations >= max) break;
  }
  if (!resultHtml.length) resultHtml.push(await page.content());
  fs.writeFileSync(outputPath, resultHtml.join('\n'));
} finally {
  await browser.close();
}
