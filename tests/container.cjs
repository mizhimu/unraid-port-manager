/* Run with Playwright available in NODE_PATH. Native field IDs mirror Unraid 7.3.2. */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
(async () => {
  const browser = await chromium.launch({headless: true, ...(process.env.PM_BROWSER_CHANNEL ? {channel:process.env.PM_BROWSER_CHANNEL} : {})});
  try {
    const page = await browser.newPage({ viewport: {width: 800, height: 760} });
    let failure = false, disabled = false, delay = 0, requests = 0;
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const response = {
      complete: true, available: [5002,5003,5004,5005,5006,5007,5008,5009,5010,5011,5012],
      blocked: {'5000':['常用端口避让：registry'], '5013':['容器预留：stopped / UDP']},
      reserved: [{start: 5100,end:5102,reason:'内核预留'}], timestamp: '2026-10-04T12:00:00Z'
    };
    await page.route('http://port-manager.test/**', async route => {
      if (route.request().url().includes('api.php')) {
        requests++;
        const failed = failure;
        if (delay) await new Promise(resolve => setTimeout(resolve, delay));
        await route.fulfill({status: failed ? 503 : 200,contentType:'application/json',body:JSON.stringify(failed ? {error:'采集不完整'} : disabled ? {enabled:false} : response)});
      } else await route.fulfill({contentType:'text/html',body:'<!doctype html><html><body></body></html>'});
    });
    await page.goto('http://port-manager.test/AddContainer');
    await page.setContent(`<select name="contNetwork"><option value="bridge">Bridge</option><option value="host">Host</option><option value="br0">br0</option></select>
      <div id="configLocation"><div id="ConfigNum1"><input name="confType[]" value="Port" type="hidden"><input name="confValue[]" value="5003"></div></div><div id="dialogAddConfig" style="display:inline-block"></div>
      <script>window.drivers={bridge:'bridge',host:'host',br0:'macvlan'};
      window.jQuery=element=>({on:(name,handler)=>element.addEventListener(name.split('.')[0],handler)});
      window.openPopup=type=>{const d=document.getElementById('dialogAddConfig');d.innerHTML='配置类型 <select name="Type"><option>Path</option><option>Port</option><option>Variable</option></select><div id="Value"><label>主机端口 <input name="Value"></label></div><label>容器端口 <input name="Target" value="80"></label>';d.querySelector('select').value=type;d.dispatchEvent(new Event('dialogopen'));};</script>`);
    await page.addStyleTag({path:path.resolve('src/port-manager/assets/container.css')});
    await page.addScriptTag({path:path.resolve('src/port-manager/assets/container.js')});
    const buttons = page.locator('.pm-container-candidates button');
    const status = page.locator('.pm-container-status');
    await page.evaluate(() => openPopup('Path'));
    assert.equal(await page.locator('.pm-container').isVisible(), false);
    assert.equal(requests,0);
    await page.selectOption('#dialogAddConfig [name="Type"]','Port');
    await buttons.first().waitFor();
    assert.deepEqual(await buttons.allTextContents(), ['5002','5004','5005','5006','5007','5008']);
    await buttons.first().click();
    assert.equal(await page.inputValue('#dialogAddConfig [name="Value"]'),'5002');
    assert.equal(await page.inputValue('#dialogAddConfig [name="Target"]'),'80');
    await page.click('[data-action="more"]');
    assert.deepEqual(await buttons.allTextContents(), ['5009','5010','5011','5012']);
    await page.fill('#dialogAddConfig [name="Value"]','5013');
    assert.match(await status.textContent(),/stopped.*UDP/);
    await page.fill('#dialogAddConfig [name="Value"]','5101');
    assert.match(await status.textContent(),/内核预留/);
    failure = true;
    await page.click('[data-action="refresh"]');
    await page.waitForFunction(() => document.querySelector('.pm-container-status').textContent === '采集不完整');
    assert.equal(await buttons.count(),0);
    failure = false;
    await page.selectOption('[name="contNetwork"]','host');
    assert.match(await status.textContent(),/不适用/);
    assert.equal(await buttons.count(),0);
    await page.selectOption('[name="contNetwork"]','br0');
    assert.match(await status.textContent(),/不适用/);
    await page.selectOption('[name="contNetwork"]','bridge');
    await buttons.first().waitFor();
    delay = 150;
    await page.click('[data-action="refresh"]');
    await page.selectOption('#dialogAddConfig [name="Type"]','Variable');
    await page.waitForTimeout(250);
    assert.equal(await page.locator('.pm-container').isVisible(),false);
    assert.equal(await buttons.count(),0);
    delay = 0;
    await page.evaluate(() => {document.getElementById('dialogAddConfig').dispatchEvent(new Event('dialogclose'));openPopup('Port');});
    await buttons.first().waitFor();
    assert.equal(await page.locator('.pm-container').count(),1);
    assert.deepEqual(await buttons.allTextContents(), ['5002','5004','5005','5006','5007','5008']);
    require('node:fs').mkdirSync(path.resolve('dist'), {recursive:true});
    const boxes=await buttons.evaluateAll(elements=>elements.map(element=>{const r=element.getBoundingClientRect();return {top:r.top,width:r.width,height:r.height};}));
    assert.equal(new Set(boxes.map(box=>box.top)).size,1);
    assert.ok(boxes.every(box=>box.width<50 && box.height<=24));
    assert.equal(await page.locator('.pm-container a').getAttribute('href'),'/Tools/PortManager#pm-container-settings');
    disabled = true;
    await page.click('[data-action="refresh"]');
    await page.waitForFunction(() => document.querySelector('.pm-container').hidden);
    await page.fill('#dialogAddConfig [name="Value"]','5002');
    assert.equal(await page.locator('.pm-container').isVisible(),false);
    disabled = false;
    await page.evaluate(() => openPopup('Port'));
    await buttons.first().waitFor();
    await page.screenshot({path:path.resolve('dist/container-preview.png')});
    require('node:fs').mkdirSync(path.resolve('docs/images'),{recursive:true});
    await page.locator('.pm-container').screenshot({path:path.resolve('docs/images/container-port-recommendation.png')});
    assert.deepEqual(errors,[]);
    console.log('PASS popup lifecycle, ascending/form exclusions, fill target preservation, batch, manual reasons, failure, network modes, stale response cancellation and reopen');
  } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
