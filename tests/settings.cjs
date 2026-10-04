const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
  const browser = await chromium.launch({headless:true, ...(process.env.PM_BROWSER_CHANNEL ? {channel:process.env.PM_BROWSER_CHANNEL} : {})});
  try {
    const page = await browser.newPage({viewport:{width:1180,height:900}});
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    let settings={enabled:true,reserved:'5500 # host 容器'},lastBody;
    await page.route('http://port-manager.test/**',async route=>{
      const request=route.request(),url=new URL(request.url());
      if(url.pathname.endsWith('settings.php')) {
        if(request.method()==='POST') {
          lastBody=new URLSearchParams(request.postData());
          if(lastBody.get('reserved')==='invalid') {await route.fulfill({status:400,contentType:'application/json',body:JSON.stringify({error:'第 1 行：预留端口格式无效'})});return;}
          settings={enabled:lastBody.get('enabled')==='1',reserved:lastBody.get('reserved')};
        }
        await route.fulfill({contentType:'application/json',body:JSON.stringify(settings)});
      }else if(url.pathname.endsWith('api.php')) await route.fulfill({contentType:'application/json',body:JSON.stringify({complete:true,groups:[],rows:[],summary:{occupied:0,configured:0,risks:0,containers:0},timestamp:'2026-10-04T12:00:00Z'})});
      else if(url.pathname.startsWith('/plugins/port-manager/assets/')) {
        const file=path.resolve('src/port-manager/assets',path.basename(url.pathname));
        await route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file,'utf8')});
      }else await route.fulfill({contentType:'text/html',body:'<!doctype html><html><head><meta charset="utf-8"></head><body><input id="pm-csrf-token" type="hidden" value="fixture-csrf">'+fs.readFileSync('src/port-manager/include/view.html','utf8')+'</body></html>'});
    });
    await page.goto('http://port-manager.test/Tools/PortManager#pm-container-settings');
    await page.waitForFunction(()=>!document.getElementById('pm-settings-fields').disabled, null, {timeout:5000}).catch(async error=>{console.error(errors, await page.locator('#pm-settings-message').textContent());throw error;});
    assert.equal(await page.isChecked('#pm-recommend-enabled'),true);
    assert.equal(await page.inputValue('#pm-reserved-ports'),'5500 # host 容器');
    await page.uncheck('#pm-recommend-enabled');
    await page.fill('#pm-reserved-ports','5500 # host 容器\n5600-5610,6200 # 预留');
    await page.click('#pm-settings-form button');
    await page.waitForFunction(()=>document.getElementById('pm-settings-message').textContent.startsWith('已保存'));
    assert.equal(lastBody.get('csrf_token'),'fixture-csrf');assert.equal(settings.enabled,false);
    await page.reload();
    await page.waitForFunction(()=>!document.getElementById('pm-settings-fields').disabled);
    assert.equal(await page.isChecked('#pm-recommend-enabled'),false);
    assert.match(await page.inputValue('#pm-reserved-ports'),/5600-5610/);
    const cards=await page.locator('.pm-workspace>section').evaluateAll(elements=>elements.map(element=>({top:element.getBoundingClientRect().top,right:element.getBoundingClientRect().right})));
    assert.equal(cards.length,3);assert.equal(new Set(cards.map(card=>card.top)).size,1);
    assert.ok(cards.every(card=>card.right<=1180));
    fs.mkdirSync('docs/images',{recursive:true});
    await page.mouse.move(0,0);
    await page.locator('.pm-workspace').screenshot({path:'docs/images/container-settings.png'});
    await page.setViewportSize({width:600,height:900});
    const narrow=await page.locator('.pm-workspace>section').evaluateAll(elements=>elements.map(element=>element.getBoundingClientRect().top));
    assert.ok(narrow[0]<narrow[1] && narrow[1]<narrow[2]);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
    await page.setViewportSize({width:1180,height:900});
    const previous={...settings};await page.fill('#pm-reserved-ports','invalid');
    await page.click('#pm-settings-form button');
    await page.waitForFunction(()=>document.getElementById('pm-settings-message').textContent.startsWith('第 1 行'));
    assert.deepEqual(settings,previous);assert.equal(await page.inputValue('#pm-reserved-ports'),'invalid');
    assert.equal(await page.locator('#pm-settings-fields').isEnabled(),true);
    await page.fill('#pm-reserved-ports','5500 # host 容器\n5600-5610,6200 # 预留');
    fs.mkdirSync('dist',{recursive:true});
    await page.locator('#pm-container-settings').screenshot({path:'dist/settings-preview.png'});
    assert.deepEqual(errors,[]);
    console.log('PASS full-page settings load, CSRF form body, save, reload persistence, server validation and edit preservation');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
