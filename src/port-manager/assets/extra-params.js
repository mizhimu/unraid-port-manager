/* Only edits the native Extra Parameters field; never applies container changes. */
(() => {
  'use strict';
  const groups = {
    cpu: ['--cpus'],
    memory: ['--memory', '-m'],
    restart: ['--restart'],
    stop: ['--stop-timeout']
  };
  // Docker options whose following token is a value, including native short aliases.
  const valueFlags = new Set(`--add-host --annotation --attach -a --blkio-weight --blkio-weight-device --cap-add --cap-drop --cgroup-parent --cgroupns --cidfile --cpu-period --cpu-quota --cpu-rt-period --cpu-rt-runtime --cpu-shares -c --cpus --cpuset-cpus --cpuset-mems --detach-keys --device --device-cgroup-rule --device-read-bps --device-read-iops --device-write-bps --device-write-iops --dns --dns-option --dns-search --domainname --entrypoint --env -e --env-file --expose --gpus --group-add --health-cmd --health-interval --health-retries --health-start-interval --health-start-period --health-timeout --hostname -h --ip --ip6 --ipc --isolation --label -l --label-file --link --link-local-ip --log-driver --log-opt --mac-address --memory -m --memory-reservation --memory-swap --memory-swappiness --mount --name --network --net --network-alias --oom-score-adj --pid --pids-limit --platform --publish -p --pull --restart --runtime --security-opt --shm-size --stop-signal --stop-timeout --storage-opt --sysctl --tmpfs --ulimit --user -u --userns --uts --volume -v --volume-driver --volumes-from --workdir -w`.split(' '));
  function options(list) {
    const result = [];
    for (let i = 0; i < list.length; i++) {
      const token = list[i], key = /^-m[^=]/.test(token.value) ? '-m' : token.value.split('=')[0];
      const entry = { key, items: [token] };
      if (valueFlags.has(key) && !token.value.includes('=') && !(key === '-m' && token.value !== '-m')) {
        if (!list[i + 1]) throw new Error(`${key} 缺少参数值，请先手动修正。`);
        entry.items.push(list[++i]);
      }
      result.push(entry);
    }
    return result;
  }
  // Preserve raw tokens. Refuse shell expressions rather than reinterpret them.
  function tokens(text) {
    const result = [];
    let value = '', raw = '', mode = '', active = false;
    const flush = () => { if (active) result.push({ value, raw }); value = raw = ''; active = false; };
    for (let i = 0; i < text.length; i++) {
      const char = text[i];
      if (!mode && /[\r\n]/.test(char)) throw new Error('额外参数包含换行，请先整理为单行参数。');
      if (!mode && /\s/.test(char)) { flush(); continue; }
      active = true;
      raw += char;
      if (mode === "'") {
        if (char === "'") mode = ''; else value += char;
      } else if (char === '\\') {
        if (i + 1 === text.length) throw new Error('额外参数末尾的反斜杠不完整，请先手动修正。');
        const next = text[++i]; raw += next;
        if (mode === '"' && !['$', '`', '"', '\\', '\n'].includes(next)) value += '\\';
        value += next;
      } else if (char === mode && mode) mode = '';
      else if (!mode && (char === "'" || char === '"')) mode = char;
      else {
        if (char === '$' || char === '`' || (!mode && /[;&|<>()#]/.test(char))) {
          throw new Error('当前额外参数包含 Shell 表达式，请手动编辑；快捷配置不会改写这类内容。');
        }
        value += char;
      }
    }
    if (mode) throw new Error('额外参数引号未闭合，请先手动修正。');
    flush();
    return result;
  }
  function init() {
    const input = document.querySelector('[name="contExtraParams"]');
    if (!input) return;
    const panel = document.createElement('details');
    panel.className = 'pm-extra';
    panel.innerHTML = `<summary>快捷配置：CPU、内存、重启策略与停止等待</summary>
      <p>只处理你选择的项目。预览后点击填入，最后仍需点击 Unraid 原生“应用”。</p>
      <fieldset data-group="cpu"><legend>CPU 使用上限</legend>
        <label>操作 <select data-action><option value="keep">保持现有参数</option><option value="set">设置上限</option><option value="remove">移除 CPU 上限参数</option></select></label>
        <div data-fields hidden><label>核心算力 <input data-key="cpus" type="number" min="0.001" step="any" placeholder="例如 2 或 0.5"></label></div>
        <p>2 表示最多使用相当于两个核心的算力，不绑定到指定核心。小数表示部分核心算力；不设置时受 Docker 和宿主机配置约束。</p>
      </fieldset>
      <fieldset data-group="memory"><legend>内存上限</legend>
        <label>操作 <select data-action><option value="keep">保持现有参数</option><option value="set">设置上限</option><option value="remove">移除内存上限参数</option></select></label>
        <div data-fields hidden><label>容量 <input data-key="memory" type="number" min="0.001" step="any" placeholder="例如 2"></label><label>单位 <select data-key="unit"><option value="g">GiB</option><option value="m">MiB</option></select></label></div>
        <p>限制内存使用，不会提前占用。Docker 最小值为 6 MiB；达到上限且无法释放时，程序可能被系统终止。此项不设置交换空间，已有 memory-swap / memory-reservation 参数保持原样。</p>
      </fieldset>
      <h4>启动与停止行为</h4>
      <p>Docker 重启策略与 Unraid“自动启动”是两套机制。以下两项分别设置，不改变其他项。</p>
      <fieldset data-group="restart"><legend>重启策略</legend>
        <label>操作 <select data-action><option value="keep">保持现有参数</option><option value="set">设置重启策略</option><option value="remove">移除重启策略参数</option></select></label>
        <div data-fields hidden>
          <label>策略 <select data-key="restart"><option value="no">不自动重启</option><option value="on-failure">异常退出时重启</option><option value="always">总是重启</option><option value="unless-stopped">除非手动停止</option></select></label>
          <p data-restart-help></p>
          <label data-retries hidden>最多重试次数 <input data-key="restartRetries" type="number" min="1" step="1" placeholder="留空表示不限次数"></label>
        </div>
        <p>移除覆盖参数后，Docker 默认不自动重启。</p>
      </fieldset>
      <fieldset data-group="stop"><legend>停止等待时间</legend>
        <label>操作 <select data-action><option value="keep">保持现有参数</option><option value="set">设置等待时间</option><option value="remove">移除停止等待时间参数</option></select></label>
        <div data-fields hidden><label>等待时间（秒）<input data-key="stop" type="number" min="0" step="1" placeholder="例如 30"></label></div>
        <p>收到停止信号后等待多久，再强制终止。0 表示立即强制终止，不改变停止信号。移除后使用镜像或 Docker 默认值。</p>
      </fieldset>
      <p>本次变更</p><pre data-changes></pre><p>填入后的完整额外参数</p><pre data-preview></pre>
      <button type="button" data-fill disabled>填入额外参数</button><p role="status" aria-live="polite"></p>`;
    input.insertAdjacentElement('afterend', panel);
    const status = panel.querySelector('[role="status"]');
    let next = null;
    const get = (group, key) => group.querySelector(`[data-key="${key}"]`);
    function number(group, key, min, integer = false, optional = false) {
      const value = get(group, key).value.trim();
      if (!value && optional) return '';
      if (!/^\d+(?:\.\d+)?$/.test(value) || Number(value) < min || !Number.isFinite(Number(value)) || (integer && !Number.isSafeInteger(Number(value)))) {
        throw new Error(`请检查“${group.querySelector('legend').textContent}”中的数值：${integer ? '需要整数，' : ''}最小为 ${min}。`);
      }
      return Number(value).toString();
    }
    function replacement(group, action) {
      const name = group.dataset.group;
      if (action === 'remove') return [];
      if (name === 'cpu') return [`--cpus=${number(group, 'cpus', 0.001)}`];
      if (name === 'memory') {
        const size = number(group, 'memory', 0.001), unit = get(group, 'unit').value;
        if (Number(size) * (unit === 'g' ? 1024 : 1) < 6) throw new Error('内存上限至少为 6 MiB。');
        return [`--memory=${size}${unit}`];
      }
      if (name === 'restart') {
        const restart = get(group, 'restart').value;
        const retries = restart === 'on-failure' ? number(group, 'restartRetries', 1, true, true) : '';
        return [`--restart=${restart}${retries ? ':' + retries : ''}`];
      }
      return [`--stop-timeout=${number(group, 'stop', 0, true)}`];
    }

    const restartHelp = {
      no: '退出后不自动重启。',
      'on-failure': '退出码非 0 时重启；Docker 服务重启本身不会触发此策略。',
      always: '退出后自动重启。手动停止后暂不重启，但 Docker 服务重启后会再次启动。',
      'unless-stopped': '退出后自动重启；手动停止后，Docker 服务重启也保持停止。'
    };
    function preview() {
      next = null;
      status.textContent = '';
      panel.querySelector('[data-fill]').disabled = true;
      panel.querySelector('[data-preview]').textContent = '';
      panel.querySelector('[data-changes]').textContent = '';
      const selected = [];
      panel.querySelectorAll('fieldset').forEach(group => {
        const action = group.querySelector('[data-action]').value;
        group.querySelector('[data-fields]').hidden = !['set', 'custom'].includes(action);
        if (action !== 'keep') selected.push({ group, action });
      });
      const lifecycle = panel.querySelector('[data-group="restart"]');
      const restart = get(lifecycle, 'restart').value;
      lifecycle.querySelector('[data-restart-help]').textContent = restartHelp[restart];
      lifecycle.querySelector('[data-retries]').hidden = restart !== 'on-failure';
      if (!selected.length) { status.textContent = '请选择要配置或移除的项目。'; return; }
      try {
        let current = options(tokens(input.value));
        const changes = [];
        for (const { group, action } of selected) {
          const keys = groups[group.dataset.group];
          const added = replacement(group, action), old = [], kept = [];
          for (const entry of current) {
            if (keys.includes(entry.key)) old.push(...entry.items.map(t => t.raw));
            else kept.push(entry);
          }
          if (group.dataset.group === 'cpu' && added.length && current.some(e => ['--cpu-period', '--cpu-quota'].includes(e.key))) throw new Error('已有 cpu-period / cpu-quota，请先手动移除，避免与 CPU 上限冲突。');
          if (added.some(v => v.startsWith('--restart=') && v !== '--restart=no') && current.some(e => e.key === '--rm' && e.items[0].value !== '--rm=false')) throw new Error('自动重启与 --rm 冲突，请先手动移除 --rm。');
          current = [...kept, ...added.map(raw => ({ key: raw.split('=')[0], items: [{ raw }] }))];
          changes.push(`${group.querySelector('legend').textContent}\n当前：${old.join(' ') || '未设置'}\n改为：${added.join(' ') || '移除覆盖参数'}`);
        }
        next = current.flatMap(e => e.items.map(t => t.raw)).join(' ');
        panel.querySelector('[data-changes]').textContent = changes.join('\n\n');
        panel.querySelector('[data-preview]').textContent = next || '（空）';
        panel.querySelector('[data-fill]').disabled = false;
      } catch (error) { status.textContent = error.message; }
    }
    panel.addEventListener('input', preview);
    panel.addEventListener('change', preview);
    panel.addEventListener('toggle', () => { if (panel.open) preview(); });
    input.addEventListener('input', preview);
    input.addEventListener('change', preview);
    panel.querySelector('[data-fill]').addEventListener('click', () => {
      preview();
      if (next === null) return;
      input.value = next;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      status.textContent = '已填入额外参数，请核对后通过 Unraid 原生“应用”保存。';
    });
    preview();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
