/* Native form fixture; run with Playwright available in NODE_PATH. */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
process.chdir(path.resolve(__dirname, '..'));
(async () => {
  const browser = await chromium.launch({headless:true, ...(process.env.PM_BROWSER_CHANNEL ? {channel:process.env.PM_BROWSER_CHANNEL} : {})});
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.setContent(`<form><input name="contWebUI" value="https://[IP]:[PORT:443]/admin"><select name="contNetwork"><option>bridge</option><option>host</option></select><div id="configLocation"></div></form><script>
      window.drivers={bridge:'bridge',host:'host'};
      window.addPort=(id,host,target,mode='tcp')=>{
        const row=document.createElement('div');row.id='ConfigNum'+id;
        for(const [name,value] of Object.entries({confType:'Port',confValue:host,confTarget:target,confMode:mode,confName:'配置 '+id})) {
          const input=document.createElement('input');input.name=name+'[]';input.value=value;input.type='hidden';row.append(input);
        }
        document.getElementById('configLocation').append(row);
      };
      window.changes=0;document.querySelector('[name="contWebUI"]').addEventListener('change',()=>changes++);
      window.submits=0;document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();submits++;});
    </script>`);
    await page.addStyleTag({path:path.resolve('src/port-manager/assets/container.css')});
    await page.addScriptTag({path:path.resolve('src/port-manager/assets/container.js')});
    const buttons=page.locator('.pm-webui button');
    const input=page.locator('[name="contWebUI"]');
    assert.match(await page.locator('.pm-webui').textContent(),/请先在下方添加/);
    assert.equal(await buttons.count(),0);
    await page.evaluate(()=>{addPort(1,'8018','80');addPort(2,'9443','443');addPort(3,'9000','90','udp');addPort(4,'10000-10002','100-102');addPort(5,'0','80');addPort(6,'8080','');});
    await buttons.nth(3).waitFor();
    assert.equal(await buttons.count(),4);
    assert.match(await buttons.first().textContent(),/主机 8018 → 容器 80 · TCP/);
    assert.equal(await input.inputValue(),'https://[IP]:[PORT:443]/admin');
    await buttons.first().click();
    assert.equal(await input.inputValue(),'http://[IP]:[PORT:8018]');
    await buttons.nth(1).click();
    assert.equal(await input.inputValue(),'http://[IP]:[PORT:9443]');
    assert.match(await page.locator('.pm-webui').textContent(),/纯 UDP/);
    await page.selectOption('.pm-webui select','10002');
    await buttons.nth(3).click();
    assert.equal(await input.inputValue(),'http://[IP]:[PORT:10002]');
    await page.evaluate(()=>{const field=document.querySelector('#ConfigNum1 [name="confValue[]"]');field.value='8020';field.dispatchEvent(new Event('change',{bubbles:true}));});
    assert.match(await buttons.first().textContent(),/主机 8020/);
    assert.equal(await input.inputValue(),'http://[IP]:[PORT:10002]');
    await buttons.first().click();
    assert.equal(await input.inputValue(),'http://[IP]:[PORT:8020]');
    await page.evaluate(()=>document.getElementById('ConfigNum1').remove());
    await page.waitForFunction(()=>document.querySelectorAll('.pm-webui button').length===3);
    assert.equal(await input.inputValue(),'http://[IP]:[PORT:8020]');
    await page.selectOption('[name="contNetwork"]','host');
    assert.equal(await buttons.count(),0);
    assert.match(await page.locator('.pm-webui').textContent(),/手动填写/);
    await page.selectOption('[name="contNetwork"]','bridge');
    assert.equal(await buttons.count(),3);
    await page.setViewportSize({width:375,height:800});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    require('node:fs').mkdirSync(path.resolve('dist'),{recursive:true});
    await page.screenshot({path:path.resolve('dist/webui-preview.png')});
    require('node:fs').mkdirSync(path.resolve('docs/images'),{recursive:true});
    await page.setViewportSize({width:700,height:800});
    await page.locator('.pm-webui').screenshot({path:path.resolve('docs/images/container-webui.png')});
    await page.evaluate(()=>document.getElementById('configLocation').replaceChildren());
    await page.waitForFunction(()=>document.querySelector('.pm-webui').textContent.includes('请先在下方添加'));
    assert.equal(await buttons.count(),0);
    assert.equal(await page.evaluate(()=>changes),4);
    assert.equal(await page.evaluate(()=>submits),0);
    assert.deepEqual(errors,[]);
    console.log('PASS Web UI guidance, explicit choice, all mappings, range, UDP, invalid fields, edit/delete, network, events and narrow layout');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
