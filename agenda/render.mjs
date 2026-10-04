// Render out/agenda.html to a print PDF and a PNG preview with Chromium.
import { chromium } from 'playwright';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 794, height: 1123 }, deviceScaleFactor: 2.5 });
await page.goto('file://' + path.join(dir, 'out/agenda.html'));
await page.evaluate(() => document.fonts.ready);
await page.pdf({ path: path.join(dir, 'out/Agenda_Draft.pdf'), width: '210mm', height: '297mm', printBackground: true });
await page.locator('.page').screenshot({ path: path.join(dir, 'out/Agenda_Draft.png') });
await browser.close();
console.log('rendered PDF + PNG');
