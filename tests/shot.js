// Uso: node shot.js URL saida.png [cookie]
const { chromium } = require('playwright');
(async () => {
  const [url, out, cookie] = process.argv.slice(2);
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  if (cookie) await ctx.addCookies([{ name: 'narutorpg', value: cookie, url: 'http://127.0.0.1:8080/' }]);
  const page = await ctx.newPage();
  const errs = [];
  page.on('pageerror', e => errs.push('JS: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
  page.on('response', r => { if (r.status() >= 400) errs.push(r.status() + ' ' + r.url()); });
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.screenshot({ path: out, fullPage: true });
  console.log(errs.join('\n'));
  await browser.close();
})();
