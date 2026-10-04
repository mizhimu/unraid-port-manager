# Port Status · Unraid 端口状态

Unraid 宿主机端口查询与推荐插件，包含首页概览、完整查询页，以及原生容器端口弹窗推荐。

当前版本：**2026.10.04**（北京时间日期版本）。

- 查询 TCP / UDP 监听、进程、绑定地址和 Docker 端口映射。
- 区分正在占用的端口与停止容器的端口预留，提示绑定重叠及启动冲突风险。
- 按端口、容器、服务搜索，支持来源、协议和状态筛选。
- 按范围推荐 TCP、UDP 或双方均可用的端口，分批展示并支持复制。
- 创建 / 编辑容器时，在主机端口旁显示单行 6 个空闲候选，点击直接填入。
- 完整页将快速查询、可用端口和推荐设置放在同一排；支持推荐开关和带备注的预留清单。
- 首页每 120 秒刷新；页面隐藏时暂停自动扫描。

插件不修改容器、网络、防火墙或系统服务配置，不运行后台服务。原生容器弹窗中点击推荐只填写主机端口，由用户通过 Unraid 原有按钮保存。

## 创建容器时推荐端口

在 Unraid 的添加容器页面，点击“添加另一个路径、端口、变量、标签或设备”，选择“端口”，主机端口输入框下面显示单行 6 个紧凑推荐候选。支持点击填入、换一批、刷新；编辑端口弹窗也提供同样的辅助信息。

![容器端口推荐：单行六个候选，点击填入、换一批、刷新及设置入口](docs/images/container-port-recommendation.png)

*原生字段结构与当前插件代码的浏览器示意，使用示例数据，不是实时 NAS 页面截图。*

### 同一排的查询与推荐设置

快速查询、可用端口和容器推荐设置在宽屏上并排显示，窄屏自动纵向排列。在右侧开启 / 关闭弹窗推荐，填写需要避让的主机端口并保存。关闭开关后，容器弹窗不提供推荐，也不进行推荐扫描。

![三栏布局：快速查询、可用端口、推荐开关和预留清单](docs/images/container-settings.png)

*使用当前页面源码和示例配置生成的示意图；清单中的端口及备注仅作格式演示。*

### 推荐规则

此处使用严格规则，与首页和完整查询页的按协议查询分开：

- 范围 5000～65535，升序，要求 TCP 和 UDP、所有绑定地址同时空闲。
- 排除系统监听及其他当前套接字、所有运行/停止容器的宿主机发布配置，以及当前表单已填写的端口。
- 排除内核 `ip_local_reserved_ports` 和动态分配范围、`ident.cfg` 的 HTTP/HTTPS/SSH/Telnet 配置端口，以及 Web Terminal 7681。
- 读取 `/etc/libvirt/qemu/*.xml` 的虚拟机显示端口配置，包括停止 VM；自动分配时排除 `qemu.conf` 的显示/WebSocket 范围。未显式配置时按 libvirt 默认 5900～65535 / 5700～65535 避让，可能使候选集中在较低端口。依据：[libvirt 配置](https://github.com/libvirt/libvirt/blob/master/src/qemu/qemu.conf.in)。
- 内置常用容器服务避让清单见 `Recommendation::common()`，这是有限的推荐避让清单，并非所有 IANA 登记端口；不禁止用户手动选择。
- 仅为 bridge 网络提供宿主机映射推荐；host、macvlan/ipvlan 等模式显示不适用。
- 任一必需采集失败时不推荐。每次打开端口弹窗或切换到端口时重新扫描；“换一批”使用本次快照，“刷新”重新采集。扫描不会锁定端口。

无法从停止的 host 网络容器自动推断全部启动监听端口，也无法通用解析所有第三方服务配置。额外计划占用通过 `/boot/config/plugins/port-manager/reserved-ports.conf` 明确预留：

```text
# 每行端口、范围或逗号分隔列表，TCP / UDP 一并排除
5500
5600-5610,6200 # 计划部署的服务
```

在 **Tools → 端口状态 → 创建容器端口推荐设置** 中可以开关推荐、填写清单并保存；弹窗的“推荐设置”链接可直接跳到这里。清单支持备注与范围，格式错误时拒绝保存并保留原配置。开关仅影响容器弹窗，保存后下次打开弹窗生效。配置保存在启动盘，重启和插件升级后保留。也可直接编辑上述文件；文件不存在时默认启用，手动填错会停止推荐并提示。

集成使用 WebGUI 的 `Buttons` 扩展入口及原生弹窗事件，不修改 Unraid 核心文件。当前页面结构依据 Unraid 7.3.2 确认，其他版本需实机验收。

## 界面预览

### 完整查询页

查看端口占用、停止容器预留、服务用途与绑定详情，按来源、协议和状态筛选。

![完整查询页：端口统计、服务图表、搜索筛选和端口列表](docs/images/port-status-page.png)

*查询明细功能示意（早期布局）；最新三栏布局见上图。数值为示例数据。*

### 首页卡片

折叠时显示占用、预留摘要及 TCP / UDP 分布：

![首页卡片折叠预览：占用与预留摘要、TCP 和 UDP 热力图](docs/images/dashboard-folded.png)

展开后可直接查询端口，或按范围推荐并复制空闲候选：

<img src="docs/images/dashboard-expanded.png" alt="首页卡片展开：联合及单协议空闲推荐、候选复制和端口分布" width="492">

*首页卡片图片来自此前的 Unraid 实测；数值为截图时刻的状态。*

## 安装

要求 Unraid 6.12+ / 7.x、PHP 7.4+，以及系统提供的 `ss`、`docker`、`timeout`。实际版本兼容性需在目标机器验收。

在 Unraid **Plugins → Install Plugin** 中输入：

```text
https://raw.githubusercontent.com/mizhimu/unraid-port-manager/main/port-manager.plg
```

或从 [Releases](https://github.com/mizhimu/unraid-port-manager/releases) 下载 `port-manager.plg`，复制到 NAS 并运行：

```sh
plugin install /boot/config/plugins/port-manager.plg
```

安装后刷新 WebGUI，打开 **Tools → 端口状态**，或查看首页卡片。
安装包自包含，无须下载额外运行依赖。插件列表的 **Support Thread / 支持论坛** 链接指向本项目。

## 更新与卸载

安装 1.0.4 后，可在 **Plugins → Check for Updates** 检查新版，出现 **Update / 更新** 后点击即可安装。插件通过标准 `pluginURL` 读取本仓库 `main` 分支的 PLG；不会自行后台安装。也可从 Releases 下载新版本手动安装。

1.0.3 及更早版本没有更新地址，需要先手动安装当前版本，之后即可使用一键更新。

版本号使用日期；完整发行说明保留所有历史版本，后续更新只新增记录。
如果安装器拒绝同版本或旧开发版本迁移，可使用：

```sh
plugin install /tmp/port-manager.plg forced
```

在 Plugins 页面卸载。卸载只删除本插件安装目录和启动盘上的插件数据，不修改容器及网络配置。

## 扫描范围与限制

- 占用：系统实际监听或运行容器发布的宿主机端口，包括 NAT 发布。
- 预留：停止容器配置的宿主机映射。macvlan/ipvlan 未实际发布的端口配置不计入宿主机预留。
- host 网络容器通过实际进程 PID 关联监听，不把 EXPOSE 当作宿主机端口。
- 不包含 VM 和独立 IP 容器内部端口，也不读取尚未创建容器的 XML 模板。
- 推荐排除 1–1023、系统动态端口范围，以及同协议任何绑定地址上的占用和预留。采集不完整时禁止推荐。
- 风险提示不等同于实际启动失败。共享监听及 IPv4/IPv6 双栈行为需结合部署情况确认。
- 数据顺序采样，结果只代表扫描时刻；部署前请刷新。大型 host 容器集合可能采集较慢。

API 依赖 Unraid WebGUI 的访问认证。请勿将源代码直接部署到公开 Web 服务器。

## 开发

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
GitHub Actions 执行核心测试、语法和安装包校验。真实 Unraid 验收步骤见 [TESTING.md](TESTING.md)。

## 支持与许可

问题和功能建议请提交 [GitHub Issues](https://github.com/mizhimu/unraid-port-manager/issues)，附上 Unraid 版本、复现步骤和错误信息。请去除 IP、容器名称等不希望公开的信息。

图表改编自 Lieflat Charts，采用 PolyForm Noncommercial 1.0.0。项目按同一非商业许可发布，详见 [LICENSE](LICENSE) 和 [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md)。

维护者发布步骤见 [RELEASING.md](RELEASING.md)。

作者：mizhimu。版本记录见 [CHANGELOG.md](CHANGELOG.md)。
