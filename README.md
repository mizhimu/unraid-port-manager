# Port Status · Unraid 端口状态

用于 Unraid 的端口查询与推荐插件。查看 NAS 上哪些端口正在使用、哪些被容器预留，并在创建或编辑容器时选择空闲主机端口。

- 查看 TCP / UDP 监听、系统服务和容器端口映射。
- 识别停止容器的端口预留，提示可能的启动冲突。
- 在原生容器端口弹窗中推荐空闲主机端口，点击即可填入。
- 支持自定义预留端口，避免占用计划留给其他服务的端口。

## 安装

1. 打开 Unraid WebGUI 的 **Plugins → Install Plugin**。
2. 粘贴以下安装地址，点击 **Install**：

   ```text
   https://raw.githubusercontent.com/mizhimu/unraid-port-manager/main/port-manager.plg
   ```

3. 安装完成后刷新页面，在首页查看端口概览，或打开 **Tools → 端口状态**。

**系统要求：**安装最低版本为 Unraid 6.12；当前实测环境为 Unraid 7.3.2，其他版本尚未完成实机验证。依赖系统提供的 PHP 7.4+、`ss`、`docker` 和 `timeout`，无须额外安装运行库。

## 功能展示

### 端口查询

按端口、容器或服务搜索，查看占用状态、绑定地址和映射关系；也可以指定范围查找可用端口，点击“清除”清空范围和推荐结果。

![端口查询页面](docs/images/port-status-page.png)

### 首页概览

在首页查看端口分布，展开卡片即可查询端口或复制空闲候选。

<img src="docs/images/dashboard-expanded.png" alt="首页端口概览" width="492">

### 创建容器时推荐端口

点击“添加另一个路径、端口、变量、标签或设备”，选择“端口”，主机端口下方会出现推荐候选。点击候选填入主机端口，再通过 Unraid 原有按钮保存；编辑容器时也可使用。

<img src="docs/images/container-port-recommendation.png" alt="主机端口旁的空闲端口候选" width="320">

在 **Tools → 端口状态 → 创建容器端口推荐设置** 中，可以关闭此功能，或填写需要避让的端口。清单支持单个端口、范围及备注，例如 `5500 # host 容器`。

<img src="docs/images/container-settings.png" alt="推荐开关与自定义预留端口" width="440">

*完整页与推荐组件图片使用当前代码和示例数据生成；首页图片来自此前的 Unraid 实测。*

## 使用说明

容器弹窗从 **5000～65535** 中按升序推荐，要求 TCP 和 UDP 都空闲，并避开已采集到的系统占用、系统预留、运行及停止容器的宿主机端口，以及常用服务端口。

- 停止的 host 网络容器无法自动推断全部启动端口，请在预留清单中补充。
- 容器弹窗推荐适用于 bridge 网络的宿主机端口映射。
- 结果代表扫描时刻；有其他服务发生变化时，请刷新。采集不完整时暂停推荐。
- 插件只保存自身设置，不修改容器、网络、防火墙或系统服务配置。

详细采集来源、虚拟机显示端口预留及配置格式见 [端口推荐规则](docs/PORT-RECOMMENDATIONS.md)。

## 更新与卸载

- **更新：**在 **Plugins** 页面点击 **Check for Updates**，有新版时点击 **Update**。
- **卸载：**在 **Plugins** 页面找到 Port Status，点击 **Remove**。卸载会删除插件及其自定义预留设置，保留容器和系统配置。

安装包及发行记录见 [Releases](https://github.com/mizhimu/unraid-port-manager/releases)。

## 问题反馈

通过 [GitHub Issues](https://github.com/mizhimu/unraid-port-manager/issues) 提交问题或建议。报告问题时请附上 Unraid 版本、复现步骤和相关错误信息，隐去私人信息。

维护者：[mizhimu](https://github.com/mizhimu)。

## 许可

本项目源码公开，采用 **PolyForm Noncommercial 1.0.0 非商业许可**，并非 [OSI 定义](https://opensource.org/osd)的开源许可。使用、修改及分发须遵守 [LICENSE](LICENSE)。

图表代码改编自 Lieflat Charts，采用同一许可；来源和署名见 [第三方声明](THIRD_PARTY_NOTICES.md)。

## 开发文档

[开发与验证](docs/DEVELOPMENT.md) · [实机验收](TESTING.md) · [发布流程](RELEASING.md) · [版本记录](CHANGELOG.md)
