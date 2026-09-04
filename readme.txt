=== DressCode ===
Contributors: lemon
Tags: ai, glm, editor, classic-editor, html
Requires at least: 5.6
Tested up to: 7.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

在 WordPress 经典编辑器中一键用 AI（GLM）按样式规范优化文章 HTML。

== Description ==

DressCode Tool 在经典编辑器工具栏添加一个「AI 优化」按钮，把当前编辑的文章 HTML 发送给 GLM 大模型，按照你维护的 Skill（样式规范）一键完成排版与样式优化，结果自动写回编辑器。

主要功能：

* **编辑器 AI 按钮**：在文章编辑页一键优化全文，自动压缩返回的 HTML（去注释、压空白、压缩内联 CSS）。
* **Skill 管理**：每个 Skill 是一个独立文件夹（SKILL.md + 可选 references/*.md），支持后台手写编辑或上传 zip 导入；可设置默认 Skill。
* **接口配置**：支持智谱 GLM 官方接口，以及任意 OpenAI 兼容（chat/completions）或 Anthropic Messages（/v1/messages）格式的中转接口；模型、Temperature 均可在后台配置。

API Key 等配置仅保存在你站点自己的数据库中（wp_options 表），只在服务器端调用模型接口时使用，不会输出到任何前台页面，也不会发送给任何第三方（模型接口除外）。

= 权限说明 =

* 「AI 优化」按钮及 AJAX 接口：需要 `edit_posts` 权限。
* Skill 管理与接口设置：需要 `manage_options`（管理员）权限。

== Installation ==

1. 通过后台「插件 → 安装插件」上传 zip，或上传解压后的文件夹到 `/wp-content/plugins/` 目录
1. 在「插件」菜单中启用插件
1. 进入「DressCode → 设置」填写 GLM API Key（open.bigmodel.cn 获取）
1. （可选）在「DressCode → Skills」中导入或编辑你的样式规范 Skill
1. 打开任意文章的经典编辑器，点击工具栏「AI 优化」按钮即可使用

== Frequently Asked Questions ==

= API Key 保存在哪里？安全吗？ =

保存在你站点数据库的 wp_options 表中，通过 WordPress Settings API 写入。Key 只在服务器端调用模型接口时使用，页面源码和前端不会出现 Key。

= 不用经典编辑器（纯 Gutenberg）能用吗？ =

按钮只在检测到经典编辑器（#content 容器）时生效，纯 Gutenberg 环境下脚本会自动不执行，不会报错。

= 支持哪些接口？ =

智谱 GLM 官方接口（默认），以及任意 OpenAI 兼容 chat/completions 或 Anthropic Messages 格式的自定义端点，在设置页选择格式并填写完整端点 URL 即可。

== Screenshots ==

1. 经典编辑器工具栏中的「AI 优化」按钮
2. DressCode → Skills 管理页
3. DressCode → 设置（GLM 接口配置）

== Changelog ==

= 0.1.0 =
* 2026-09-02
* 首个正式版本
* New - 经典编辑器「AI 优化」按钮，一键按 Skill 优化文章 HTML
* New - Skill 后台管理：SKILL.md 编辑、zip 导入、默认 Skill 设置
* New - GLM 接口配置：API Key / 格式（OpenAI 兼容、Anthropic Messages）/ 端点 / 模型 / Temperature
* New - 返回 HTML 自动压缩（去注释、压空白、压缩内联 CSS）

== Upgrade Notice ==

= 0.1.0 =
首个正式版本。
