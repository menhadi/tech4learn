import fs from 'node:fs';
import { chromium } from 'playwright';

const manifest = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 1 });
const results = [];

for (const item of manifest.items) {
  const page = await context.newPage();
  const findings = [];
  try {
    const response = await page.goto(item.url, { waitUntil: 'networkidle', timeout: 60000 });
    if (!response || !response.ok()) {
      findings.push({ type: 'preview_http_error', severity: 'error', title: 'Question preview did not load', details: `HTTP ${response?.status() ?? 'no response'}` });
    } else {
      await page.evaluate(async () => {
        if (document.fonts?.ready) await document.fonts.ready;
        if (window.MathJax?.startup?.promise) await window.MathJax.startup.promise;
        await Promise.all([...document.images].map(img => img.complete ? Promise.resolve() : new Promise(resolve => { img.onload = img.onerror = resolve; })));
      });
      const state = await page.evaluate(() => ({
        textLength: document.querySelector('[data-qa-question-body]')?.innerText.trim().length || 0,
        brokenImages: [...document.images].filter(img => !img.complete || img.naturalWidth === 0).map(img => img.currentSrc || img.src),
        horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 4,
        mathExpected: document.body.innerText.includes('\\(') || document.body.innerText.includes('\\[') || document.body.innerText.includes('$$'),
        renderedMath: document.querySelectorAll('mjx-container').length,
        optionCount: document.querySelectorAll('[data-qa-option]').length,
      }));
      if (!state.textLength) findings.push({ type: 'question_not_visible', severity: 'error', title: 'Question text is not visible', details: 'The rendered browser preview has no visible question body.' });
      if (state.brokenImages.length) findings.push({ type: 'broken_rendered_image', severity: 'error', title: 'Image failed to render', details: `${state.brokenImages.length} image(s) failed to load.`, evidence: { images: state.brokenImages } });
      if (state.horizontalOverflow) findings.push({ type: 'horizontal_overflow', severity: 'warning', title: 'Question overflows the screen', details: 'The question creates horizontal scrolling at the desktop test viewport.' });
      if (state.mathExpected && !state.renderedMath) findings.push({ type: 'math_not_rendered', severity: 'error', title: 'Math formula did not render', details: 'Math delimiters were present but MathJax produced no rendered formula.' });
      await page.screenshot({ path: item.screenshot, fullPage: true });
    }
  } catch (error) {
    findings.push({ type: 'browser_exception', severity: 'error', title: 'Browser check failed', details: String(error.message || error) });
  }
  results.push({ question_id: item.question_id, viewport: '1366x900', findings });
  await page.close();
}

await browser.close();
fs.writeFileSync(manifest.output, JSON.stringify({ items: results }));
