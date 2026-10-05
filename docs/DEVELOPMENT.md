# 开发与验证

以下命令在仓库根目录运行。

```sh
php tests/scanner.php
php tests/recommendation.php
php tests/settings.php
php -l src/port-manager/include/Scanner.php
php -l src/port-manager/include/api.php
php -l src/port-manager/include/Recommendation.php
php -l src/port-manager/include/RecommendationSettings.php
php -l src/port-manager/include/settings.php
node --check src/port-manager/assets/app.js
node --check src/port-manager/assets/dashboard.js
node --check src/port-manager/assets/charts.js
node --check src/port-manager/assets/container.js
python3 scripts/build.py
python3 tests/package.py
```

`scripts/build.py` 统一维护版本和作者，生成根目录的 `port-manager.plg` 及 `dist/` 安装产物。`dist/` 不纳入 Git。
GitHub Actions 执行核心测试、语法、安装包校验及五个 Playwright 浏览器行为测试，并保存预览截图。真实 Unraid 验收步骤见 [测试与实机验收](../TESTING.md)。


发布流程见 [RELEASING.md](../RELEASING.md)。

Web UI 联动浏览器验证：安装环境提供 Playwright 后运行 `node tests/webui.cjs`，可设置 `PM_BROWSER_CHANNEL=chrome` 使用本机 Chrome。原有弹窗回归运行 `node tests/container.cjs`。两者使用表单夹具，不能替代 Unraid 实机验收。

浏览器测试依赖锁定在 `tests/package-lock.json`。统一入口（在仓库根目录运行）：

```sh
npm ci --prefix tests
./tests/node_modules/.bin/playwright install --with-deps chromium
npm test --prefix tests
```

本机使用已安装 Chrome 时，最后一条可改为 `PM_BROWSER_CHANNEL=chrome npm test --prefix tests`。

额外参数面板单项测试：`node tests/extra-params.cjs`。表头滚动单项测试：`node tests/table.cjs`。完整 `npm test --prefix tests` 包含全部五项测试。
