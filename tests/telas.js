// Tira screenshots de várias telas do jogo. Uso: node telas.js BASE COOKIE PASTA_SAIDA lista.txt
// lista.txt: uma linha por tela "nome|url|largura(opcional)"
const { chromium } = require('playwright'); const fs = require('fs');
(async () => {
  const [base, cookie, saida, lista] = process.argv.slice(2);
  const browser = await chromium.launch();
  for (const linha of fs.readFileSync(lista, 'utf8').split('\n').filter(l => l.trim())) {
    const [nome, url, larg] = linha.split('|');
    const ctx = await browser.newContext({ viewport: { width: parseInt(larg || '1280'), height: 900 } });
    if (cookie !== '-') await ctx.addCookies([{ name: 'narutorpg', value: cookie, url: base + '/' }]);
    const page = await ctx.newPage(); const erros = [];
    page.on('pageerror', e => erros.push('JS: ' + e.message));
    page.on('response', r => { if (r.status() >= 400) erros.push(r.status() + ' ' + r.url().replace(base, '')); });
    try { await page.goto(base + '/' + url, { waitUntil: 'networkidle', timeout: 30000 }); } catch (e) { erros.push('timeout'); }
    await page.screenshot({ path: `${saida}/${nome}.png`, fullPage: true });
    console.log(nome.padEnd(22), erros.length ? erros.join(' | ') : 'ok');
    await ctx.close();
  }
  await browser.close();
})();
