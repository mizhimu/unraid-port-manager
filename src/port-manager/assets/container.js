/* Native Unraid container editor: recommendations never submit or alter container targets. */
(() => {
  'use strict';
  function initWebUI() {
    const input = document.querySelector('[name="contWebUI"]');
    const configs = document.getElementById('configLocation');
    if (!input || !configs) return;
    const panel = document.createElement('div');
    panel.className = 'pm-webui';
    input.insertAdjacentElement('afterend', panel);
    const read = (row, name) => row.querySelector(`[name="${name}[]"]`)?.value.trim() || '';
    function fill(port) {
      input.value = `http://[IP]:[PORT:${port}]`;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      panel.querySelector('[role="status"]').textContent = `已填入主机端口 ${port}`;
    }
    function render() {
      panel.replaceChildren();
      const hint = document.createElement('p');
      panel.append(hint);
      const network = document.querySelector('[name="contNetwork"]')?.value;
      if (window.drivers?.[network] !== 'bridge') {
        hint.textContent = '此网络模式不使用主机端口映射，请手动填写 Web UI 地址。';
        return;
      }
      const mappings = [];
      configs.querySelectorAll('input[name="confType[]"]').forEach(type => {
        if (type.value !== 'Port') return;
        const row = type.closest('[id^="ConfigNum"]');
        if (!row) return;
        const host = read(row, 'confValue'), target = read(row, 'confTarget');
        const match = host.match(/^(\d+)(?:-(\d+))?$/);
        const targetMatch = target.match(/^(\d+)(?:-(\d+))?$/);
        if (!match || !targetMatch) return;
        const start = Number(match[1]), end = Number(match[2] || match[1]);
        const targetStart = Number(targetMatch[1]), targetEnd = Number(targetMatch[2] || targetMatch[1]);
        if (start < 1 || end > 65535 || start > end || targetStart < 1 || targetEnd > 65535 || targetStart > targetEnd) return;
        mappings.push({host, target, start, end, name: read(row, 'confName'), mode: read(row, 'confMode').toUpperCase()});
      });
      if (!mappings.length) {
        hint.textContent = '请先在下方添加端口映射，填写容器端口和主机端口，添加后可一键填入。';
        return;
      }
      hint.textContent = '一键填入 · 请选择 Web UI 使用的端口，点击将替换当前 Web UI 地址。';
      const choices = document.createElement('div');
      choices.className = 'pm-webui-choices';
      panel.append(choices);
      mappings.forEach(mapping => {
        const row = document.createElement('div');
        row.className = 'pm-webui-choice';
        const label = `${mapping.name ? mapping.name + ' · ' : ''}主机 ${mapping.host} → 容器 ${mapping.target}${mapping.mode ? ' · ' + mapping.mode : ''}`;
        let select;
        if (mapping.start !== mapping.end) {
          const caption = document.createElement('span');
          caption.textContent = label;
          select = document.createElement('select');
          select.setAttribute('aria-label', label + '：选择主机端口');
          for (let port = mapping.start; port <= mapping.end; port++) {
            const option = document.createElement('option');
            option.value = port;
            option.textContent = port;
            select.append(option);
          }
          row.append(caption, select);
        }
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = select ? '填入所选端口' : label;
        button.addEventListener('click', () => fill(select ? select.value : mapping.start));
        row.append(button);
        if (mapping.mode === 'UDP') {
          const note = document.createElement('small');
          note.textContent = '纯 UDP 通常不用于网页访问';
          row.append(note);
        }
        choices.append(row);
      });
      const status = document.createElement('p');
      status.setAttribute('role', 'status');
      panel.append(status);
    }
    new MutationObserver(render).observe(configs, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
    ['input', 'change'].forEach(eventName => document.addEventListener(eventName, event => {
      if (configs.contains(event.target) || event.target.name === 'contNetwork') render();
    }));
    render();
  }
  function init() {
    initWebUI();
    const dialog = document.getElementById('dialogAddConfig');
    if (!dialog || !window.jQuery) return;
    let panel, snapshot, controller, generation = 0, offset = 0, enabled = true;
    const field = name => dialog.querySelector(`[name="${name}"]`);
    const isPort = () => field('Type')?.value === 'Port';
    const networkSupported = () => {
      const network = document.querySelector('[name="contNetwork"]')?.value;
      return window.drivers?.[network] === 'bridge';
    };
    function formPorts() {
      const ports = new Set();
      document.querySelectorAll('#configLocation input[name="confType[]"]').forEach(type => {
        if (type.value !== 'Port') return;
        const value = type.closest('[id^="ConfigNum"]')?.querySelector('[name="confValue[]"]')?.value || '';
        const match = value.match(/^(\d+)(?:-(\d+))?$/);
        if (!match) return;
        const start = Number(match[1]), end = Number(match[2] || match[1]);
        for (let port = start; port <= Math.min(end, 65535); port++) ports.add(port);
      });
      return ports;
    }
    function invalidate() {
      generation++;
      controller?.abort();
      snapshot = null;
      offset = 0;
    }
    function message(text) {
      panel.querySelector('.pm-container-status').textContent = text;
    }
    function render() {
      if (!panel?.isConnected) return;
      panel.hidden = !isPort() || !enabled;
      const candidates = panel.querySelector('.pm-container-candidates');
      candidates.replaceChildren();
      panel.querySelector('[data-action="more"]').disabled = true;
      if (!isPort() || !enabled) return;
      if (!networkSupported()) {
        message('此网络模式不使用宿主机端口映射，空闲端口推荐不适用。');
        return;
      }
      if (!snapshot) { panel.querySelector('time').textContent = '尚未扫描'; return; }
      const used = formPorts();
      const available = snapshot.available.filter(port => !used.has(port));
      if (offset >= available.length) offset = 0;
      available.slice(offset, offset + 6).forEach(port => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = port;
        button.title = '填入主机端口';
        button.addEventListener('click', () => {
          field('Value').value = port;
          field('Value').dispatchEvent(new Event('input', { bubbles: true }));
          field('Value').dispatchEvent(new Event('change', { bubbles: true }));
        });
        candidates.append(button);
      });
      panel.querySelector('[data-action="more"]').disabled = available.length <= 6;
      const value = field('Value').value.trim();
      const port = Number(value);
      if (!value) message(available.length ? '点击候选填入主机端口' : '该范围没有符合规则的候选');
      else if (!/^\d+$/.test(value) || port < 1 || port > 65535) message('请输入 1～65535 的整数端口');
      else if (snapshot.blocked[port] || snapshot.reserved.some(range => port >= range.start && port <= range.end)) {
        const reasons = [...(snapshot.blocked[port] || []), ...snapshot.reserved.filter(range => port >= range.start && port <= range.end).map(range => range.reason)];
        message([...new Set(reasons)].join('；'));
      }
      else if (used.has(port)) message('当前表单已有这个端口；编辑原有映射时请核对对应条目。');
      else if (port < 5000) message('低于推荐范围（5000～65535）');
      else message('扫描时 TCP＋UDP 均空闲');
      panel.querySelector('time').textContent = new Date(snapshot.timestamp).toLocaleTimeString();
    }
    async function refresh() {
      invalidate();
      render();
      if (!isPort() || !networkSupported()) return;
      const request = generation;
      const currentPanel = panel;
      controller = new AbortController();
      message('正在检查系统及全部容器端口…');
      try {
        const response = await fetch('/plugins/port-manager/include/api.php?action=recommend', {
          cache: 'no-store', signal: controller.signal, credentials: 'same-origin'
        });
        const data = await response.json();
        if (request !== generation || currentPanel !== panel || !isPort()) return;
        enabled = data.enabled !== false;
        if (!enabled) { render(); return; }
        if (!response.ok || !data.complete || !Array.isArray(data.available) || !data.blocked || !Array.isArray(data.reserved)) {
          throw new Error(data.error || '扫描不完整，暂不能推荐');
        }
        if (request !== generation || currentPanel !== panel || !isPort()) return;
        snapshot = data;
        render();
      } catch (error) {
        if (request !== generation || error.name === 'AbortError') return;
        message(error instanceof SyntaxError ? '无法读取扫描结果，请检查登录状态并刷新' : error.message);
      }
    }
    function setup() {
      if (!field('Value') || !field('Type')) return;
      if (!panel?.isConnected) {
        invalidate();
        panel = document.createElement('div');
        panel.className = 'pm-container';
        panel.innerHTML = '<div class="pm-container-heading">推荐空闲端口 <span>5000～65535 · TCP＋UDP</span></div>' +
          '<div class="pm-container-candidates"></div><div class="pm-container-actions">' +
          '<button type="button" data-action="more">换一批</button><button type="button" data-action="refresh">刷新</button></div>' +
          '<p class="pm-container-status" role="status"></p><small>扫描时可用 · <time>尚未扫描</time> · 不锁定端口<br>' +
          '额外预留可在<a href="/Tools/PortManager#pm-container-settings" target="_blank" rel="noopener">推荐设置</a>中填写。</small>';
        field('Value').insertAdjacentElement('afterend', panel);
        panel.addEventListener('click', event => {
          const action = event.target.dataset.action;
          if (action === 'refresh') refresh();
          if (action === 'more' && snapshot) { offset += 6; render(); }
        });
        render();
      }
    }
    // Unraid recreates the popup contents on every open. Observe only native field changes.
    new MutationObserver(records => {
      if (records.some(record => !record.target.closest?.('.pm-container'))) setup();
    }).observe(dialog, { childList: true, subtree: true });
    window.jQuery(dialog).on('dialogopen.pmContainer', () => { setup(); refresh(); });
    window.jQuery(dialog).on('dialogclose.pmContainer', invalidate);
    document.addEventListener('change', event => {
      if (dialog.contains(event.target) && event.target.name === 'Type') { setup(); refresh(); }
      else if (event.target.name === 'contNetwork') { setup(); refresh(); }
      else if (dialog.contains(event.target) && event.target.name === 'Value') render();
    });
    document.addEventListener('input', event => {
      if (dialog.contains(event.target) && event.target.name === 'Value') render();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
