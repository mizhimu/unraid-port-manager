const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
process.chdir(path.resolve(__dirname, '..'));
(async () => {
  const browser=await chromium.launch({headless:true,...(process.env.PM_BROWSER_CHANNEL?{channel:process.env.PM_BROWSER_CHANNEL}:{})});
  try {
    const page=await browser.newPage({viewport:{width:1180,height:1000}});
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    const groups=Array.from({length:50},(_,i)=>({port:8000+i,name:`示例服务 ${String(i+1).padStart(2,'0')}`,source:'Docker',purpose:'端口列表滚动示例',protocol:'tcp',owner:`demo-${i}`,running:true,status:'occupied',risk:'none',addresses:['0.0.0.0'],mapping:`${8000+i} → 80`,notes:[]}));
    await page.route('http://port-manager.test/**',async route=>{
      const url=new URL(route.request().url());
      if(url.pathname.endsWith('api.php')) await route.fulfill({contentType:'application/json',body:JSON.stringify({complete:true,groups,rows:[],summary:{occupied:50,configured:0,risks:0,containers:50},timestamp:'2026-10-05T05:00:00Z'})});
      else if(url.pathname.endsWith('settings.php')) await route.fulfill({contentType:'application/json',body:JSON.stringify({enabled:true,reserved:''})});
      else if(url.pathname.startsWith('/plugins/port-manager/assets/')) {
        const file=path.resolve('src/port-manager/assets',path.basename(url.pathname));
        await route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file,'utf8')});
      } else await route.fulfill({contentType:'text/html',body:'<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;background:#f4f4f4;color:#252525}</style></head><body><input id="pm-csrf-token" type="hidden" value="fixture">'+fs.readFileSync('src/port-manager/include/view.html','utf8')+'</body></html>'});
    });
    await page.goto('http://port-manager.test/Tools/PortManager');
    await page.waitForFunction(()=>document.querySelectorAll('#pm-rows tr').length===50);
    const table=page.locator('.pm-table');
    await table.scrollIntoViewIfNeeded();
    const topBefore=await page.locator('thead th').first().evaluate(el=>el.getBoundingClientRect().top);
    for(const offset of [300,800,100000]) {
      await table.evaluate((el,y)=>el.scrollTop=y,offset);
      await page.evaluate(()=>new Promise(requestAnimationFrame));
      const rects=await table.evaluate(el=>({scroll:el.scrollTop,top:el.getBoundingClientRect().top,head:el.querySelector('th').getBoundingClientRect().top,body:el.querySelector('tbody tr').getBoundingClientRect().top}));
      assert.ok(rects.scroll>0);assert.ok(Math.abs(rects.head-topBefore)<2,JSON.stringify(rects));assert.ok(rects.body<rects.head);
    }
    fs.mkdirSync('docs/images',{recursive:true});fs.mkdirSync('dist',{recursive:true});
    await table.evaluate(el=>el.scrollTop=350);await page.evaluate(()=>new Promise(requestAnimationFrame));
    await table.screenshot({path:'docs/images/port-table-sticky.png'});
    await table.screenshot({path:'dist/table-preview.png'});
    const headers=await page.locator('thead th').allTextContents();assert.equal(headers[0],'宿主机端口');
    await page.setViewportSize({width:600,height:1000});
    await table.scrollIntoViewIfNeeded();await table.evaluate(el=>{el.scrollTop=500;el.scrollLeft=100;});
    await page.evaluate(()=>new Promise(requestAnimationFrame));
    const alignment=await table.evaluate(el=>({head:el.querySelector('thead th:nth-child(2)').getBoundingClientRect().left,body:el.querySelector('tbody td:nth-child(2)').getBoundingClientRect().left}));
    assert.ok(Math.abs(alignment.head-alignment.body)<1);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    // Mirror the inherited background used by Unraid's dark theme.
    await page.evaluate(()=>{document.body.style.background='#202020';document.body.style.color='#eee';document.getElementById('port-manager').style.setProperty('--pm-drawer-bg',getComputedStyle(document.body).backgroundColor);});
    assert.equal(await page.locator('thead th').first().evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(32, 32, 32)');
    await table.screenshot({path:'dist/table-dark-preview.png'});
    assert.deepEqual(errors,[]);
    console.log('PASS table headers remain visible at middle/bottom scroll positions, opaque theme background, horizontal column alignment and narrow layout');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
