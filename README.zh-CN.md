# DressCode

**在 WordPress 经典编辑器中一键 AI 优化 HTML 样式——按 Skill(样式规范)驱动,基于 GLM 大模型。**

[English](./README.md) | 简体中文

[![WP](https://img.shields.io/badge/WordPress-5.6%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)](./LICENSE)

## 功能特性

- **编辑器 AI 按钮**:经典编辑器工具栏(Visual/Text 双模式)显示「AI 优化」按钮与 Skill 下拉框,适用于文章、页面、产品
- **Skill 体系**:每个 Skill 是一个含 `SKILL.md` 的文件夹(可带 `references/*.md`),支持后台管理、编辑、zip 导入、设为默认
- **双 API 方言**:兼容 OpenAI `chat/completions` 与 Anthropic `/v1/messages` 两种端点(含各类中转)
- **选中感知**:选中内容只优化选中部分,未选中则优化全文
- **WooCommerce Custom Tabs**:每个本地 Tab 内容框下都有独立 AI 按钮,动态新增的 Tab 自动获得按钮
- **服务端压缩**:结果先压缩(缩进/注释/内联 CSS)再插入编辑器
- **提示词预清理**:发送前自动解包逐字符 `<span>` 粘贴垃圾,token 缩减数倍
- **默认 Skill 自愈**:默认 Skill 文件丢失时自动按内置规范重建
- **国际化**:中文源语言,自带英文翻译,后台语言自动切换

## 环境要求

- WordPress 5.6+,已安装 **Classic Editor** 插件(或任何使用经典 `#content` 编辑器的环境)
- PHP 7.4+
- GLM API Key——来自 [open.bigmodel.cn](https://open.bigmodel.cn)、[z.ai](https://z.ai) 或任何承载 GLM 模型的 OpenAI/Anthropic 兼容中转
- WooCommerce + *WB Custom Product Tabs for WooCommerce*(可选,用于产品 Tab 按钮)

## 安装

```bash
# 放入 wp-content/plugins/
git clone https://github.com/the-lemonboy/DressCode.git
```

或下载 zip 后通过 **插件 → 安装插件 → 上传插件** 安装并激活。

## 配置

进入 **DressCode → Settings**:

| 字段 | 说明 |
|---|---|
| GLM API Key | 你的 API Key(密码框掩码显示) |
| API Format | `OpenAI-compatible (chat/completions)` 或 `Anthropic Messages (/v1/messages)` |
| Endpoint URL | 完整端点 URL,如 `https://open.bigmodel.cn/api/paas/v4/chat/completions` 或 `https://your-relay.com/v1/messages` |
| Model | 如 `glm-4.6`、`glm-4.5-air`、`glm-5.3` |
| Temperature | 0–2(建议 0.2–0.5) |

> **模型选择提示**:推理模型(如 `glm-5.3`)处理整页内容可能需要数分钟并超出 300 秒请求预算;`glm-4.6` 通常一分钟内完成。长页面建议选择更快的模型,或分段选中优化。

## Skill 管理

进入 **DressCode → Skills**。每个 Skill 是 `wp-content/uploads/dresscode-skills/` 下的一个文件夹:

```
dresscode-skills/
└── my-style/
    ├── SKILL.md            # 系统提示词(必需)
    └── references/
        └── tokens.md       # 自动拼接到提示词末尾
```

- 可在后台表单直接编写 `SKILL.md`,或 **zip 导入**(内含 `SKILL.md`,根目录或单个子文件夹均可,可带 `references/*.md`)
- 可将某个 Skill **设为默认**——编辑器下拉框未明确选择时使用
- 内置的 *DressCode 通用规范* 是无品牌的通用样式指南(rem 字号体系、1.5 行高、圆角、响应式基准、CSS 变量),可随意编辑或替换为你自己的品牌规范

## 使用

1. 打开任意文章/页面/产品的经典编辑器
2. 在「添加媒体」一栏的 Skill 下拉框选择规范
3. (可选)选中一段内容
4. 点击 **AI Optimize**——四角星图标旋转表示处理中(长内容约 1~2 分钟)
5. 优化后压缩过的 HTML 自动写回编辑器,满意后保存

WooCommerce 产品的每个本地 Custom Tab 内容框下方都有独立的小 **AI Optimize** 按钮。

## 国际化

源语言为中文(`zh_CN`),`lang/` 内置英文(`en_US`)翻译。修改字符串后重新生成:

```bash
php tools/extract-strings.php     # 重建 lang/dresscode.pot
php tools/build-translations.php  # 按脚本内翻译表重建 en_US .po/.mo
```

## 常见问题

**点击按钮没反应?**
留意右下角 toast 提示——常见原因是 Key 失效(401)或推理模型处理长内容超时。可重试、换更快的模型,或选中内容分段优化。

**支持中转/代理吗?**
支持。任何提供 OpenAI `chat/completions` 或 Anthropic `/v1/messages` 端点并承载 GLM 模型的服务均可,填完整 URL 并选择对应 API 格式即可。

**Skill 存在哪里?**
`wp-content/uploads/dresscode-skills/`(跟随 `upload_path` 配置)。目录自带 `.htaccess` 防列目录、防执行 PHP。

## 许可

基于 [WordPress Plugin Template](https://github.com/hlashbrooke/WordPress-Plugin-Template)(Hugh Lashbrooke)构建,采用 [GPL-2.0-or-later](./LICENSE) 许可。
