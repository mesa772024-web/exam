// node render.js  -> IUSBC_Invitation.pdf + IUSBC_Invitation.png (via Chromium)
const path = require('path');
const { chromium } = require('playwright');

(async () => {
  const here = __dirname;
  const out = path.join(here, '..');
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ deviceScaleFactor: 4, viewport: { width: 794, height: 560 } });
  const page = await ctx.newPage();
  await page.goto('file://' + path.join(here, 'invitation.html'));
  await page.waitForSelector('body[data-ready="1"]');
  await page.screenshot({ path: path.join(out, 'IUSBC_Invitation.png'), clip: { x: 0, y: 0, width: 793.7, height: 559.4 } });
  await page.pdf({ path: path.join(out, 'IUSBC_Invitation.pdf'), width: '210mm', height: '148mm',
                   printBackground: true, preferCSSPageSize: true });
  await browser.close();
  console.log('pdf + png written');
})();
