import { chromium } from 'playwright';

const [url, output] = process.argv.slice(2);
if (!url || !output) throw new Error('Usage: render-exam-pdf.mjs <url> <output>');

const executablePath = process.env.PDF_CHROMIUM_PATH || undefined;
const browser = await chromium.launch({
  headless: true,
  executablePath,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

try {
  const page = await browser.newPage();
  const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 180000 });
  if (!response?.ok()) throw new Error(`Print page returned HTTP ${response?.status() ?? 'unknown'}`);
  await page.waitForFunction(() => document.documentElement.dataset.mathjaxReady === '1', null, { timeout: 120000 });
  await page.evaluate(async () => {
    await document.fonts?.ready;
    await Promise.all(Array.from(document.images).map((image) => image.complete
      ? Promise.resolve()
      : new Promise((resolve) => {
          image.addEventListener('load', resolve, { once: true });
          image.addEventListener('error', resolve, { once: true });
        })));
  });
  await page.emulateMedia({ media: 'print' });
  await page.pdf({
    path: output,
    format: 'A4',
    printBackground: true,
    preferCSSPageSize: true,
    margin: { top: '12mm', right: '10mm', bottom: '12mm', left: '10mm' },
  });
} finally {
  await browser.close();
}