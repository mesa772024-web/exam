// node render.js  -> renders every job in jobs.json (pdf, optional png) via Chromium
const path = require('path');
const fs = require('fs');
const { chromium } = require('playwright');

(async () => {
  const here = __dirname;
  const jobs = JSON.parse(fs.readFileSync(path.join(here, 'jobs.json'), 'utf8'));
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ deviceScaleFactor: 4, viewport: { width: 794, height: 560 } });
  for (const j of jobs) {
    const page = await ctx.newPage();
    await page.goto('file://' + path.join(here, j.html));
    await page.waitForSelector('body[data-ready="1"]');
    if (j.png) await page.screenshot({ path: j.png, clip: { x: 0, y: 0, width: 793.7, height: 559.4 } });
    await page.pdf({ path: j.pdf, width: '210mm', height: '148mm', printBackground: true, preferCSSPageSize: true });
    await page.close();
  }
  await browser.close();
  console.log('rendered', jobs.length, 'file(s)');
})();
