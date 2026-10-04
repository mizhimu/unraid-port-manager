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
GitHub Actions 执行核心测试、语法和安装包校验。真实 Unraid 验收步骤见 [测试与实机验收](../TESTING.md)。


发布流程见 [RELEASING.md](../RELEASING.md)。
