---
name: camthink-pdp
description: CamThink 商品详情 HTML 样式优化，包含响应式表格和资源链接。
---

# CamThink PDP HTML 样式

把输入规范化为完整 HTML 片段，仅输出 HTML。保留所有有效文字、链接、属性和语义；删除仅含空白或 `&nbsp;` 的空 `li`。允许新增：最外层 `div`、表格滚动容器、资源图标 `img` 与文字 `span`，以及资源链接或规格表专用 class；其他标签仅修改或新增 `style`。不添加标题、说明或正文，不执行脚本。

全部样式内联，不用 `<style>`、媒体查询、JS、伪类、`!important`、`outline:none` 或外部字体。资源图标只使用下文提供的站点内地址。字体由站点加载。用以下样式包裹内容，外部容器不足时不得突破其宽度：

`--bg:#fff;--ink:#333;--body:#666;--brand:#f24a00;--border:#dbdbdb;--font-sans:'IBM Plex Sans',-apple-system,'Segoe UI',Roboto,'PingFang SC','Microsoft YaHei',sans-serif;--font-mono:'IBM Plex Mono',Consolas,monospace;max-width:1290px;width:100%;box-sizing:border-box;margin:0 auto;padding:0 clamp(1rem,2vw,2rem) clamp(0px,4vw - 1rem,4rem);font-family:var(--font-sans);font-size:1rem;line-height:1.5;color:var(--body);background:var(--bg);-webkit-font-smoothing:antialiased`

## 元素样式

原样用于 `style`；下文特例优先。

| 元素 | 样式 |
|---|---|
| h1 | `margin:0 0 1.25rem;font-size:clamp(2rem,1.8vw + 1.58rem,3rem);font-weight:700;color:var(--ink);letter-spacing:-0.01em` |
| h2 | `margin:2rem 0 1rem;font-size:clamp(1.5rem,1.6vw + 1.13rem,2.375rem);font-weight:600;color:var(--ink);letter-spacing:-0.01em` |
| h3 | `margin:1.75rem 0 0.75rem;font-size:clamp(1.25rem,0.5vw + 1.14rem,1.5rem);font-weight:600;color:var(--ink)` |
| h4 | `margin:1.5rem 0 0.5rem;font-size:clamp(1.125rem,0.25vw + 1.07rem,1.25rem);font-weight:500;color:var(--ink)` |
| p | `font-size:16px;line-height:1.5`（不设置 margin） |
| strong | `font-weight:600;color:var(--ink)` |
| a | `color:var(--brand);text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:0.2em` |
| ul | `list-style:square outside;padding-left:1.375rem`（不设置 margin） |
| li | `margin-top:0.5rem;list-style:square outside;text-indent:0` |
| table | `display:table;width:100%;max-width:100%;table-layout:auto;border-collapse:collapse;border:none;margin:1rem 0 0;font-variant-numeric:tabular-nums` |
| tr | `border:none;background:transparent` |
| th | `text-align:left;vertical-align:top;overflow-wrap:normal;word-break:normal;padding:0.75rem 1rem;border:none;border-bottom:1px solid var(--border);background:transparent;font-family:var(--font-mono);font-size:0.875rem;font-weight:500;letter-spacing:0.06em;text-transform:uppercase;color:var(--body)` |
| td | `text-align:left;vertical-align:top;overflow-wrap:normal;word-break:normal;padding:0.75rem 1rem;border:none;border-bottom:1px solid var(--border);background:transparent` |
| hr | `border:0;border-top:1px solid var(--border);margin:1.25rem 0` |
| img | `max-width:100%;height:auto;display:block;border-radius:6px` |

## 表格

**字段/值规格表特例**：如果表格主体每行恰好两列，第一列是规格名称、第二列是对应值（例如 MCU / ESP32-S3），不是有独立列标题的数据对比表，保留表格原有语义和内容，给 `table` 增加 class `dresscode-spec-table`。保留原有 class；不要新增列标题、改写内容或拆分单元格。此表的 tbody 中 `tr`、`th`、`td` 不设置内联 `style`（移除已有内联样式），由 DressCode 站点样式负责：桌面两列，手机每行字段在上、值在下并用分隔线区分。该表不使用滚动容器、最小宽度、型号列 nowrap 或下面通用表格单元格样式。独立列标题的表格、跨行跨列表格和多列对比表不适用此特例，继续使用下面的通用规则。

使用原生表格布局，宽度占满父容器；移除行组和行上的块级或固定宽度。已有父容器的限制若导致表格过窄，调整该容器的 `width:100%;max-width:none;box-sizing:border-box`，不得覆盖外层 1290px 限制。

四列及以上：表格外包 `<div style="width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch">`；表格样式中的 `max-width:100%` 改为 `min-width:720px`。两三列内容过宽时同样包裹，改为 `min-width:560px`。桌面铺满，移动端横向滚动；不得使用 table 自身 `display:block`。文字按词换行，不缩小字号。

按表头识别 Model、SKU、Part Number 等型号列（不限定第一列），该列 th 和 td 追加 `white-space:nowrap`，型号完整单行；空间不足时使用滚动容器。tbody 每行首个 td 追加 `color:var(--ink);font-weight:500`，最后一行 td 改为 `border-bottom:none`。原 `align="right"` 的 th/td 使用 `text-align:right`。保留 `border:none` 在底边声明之前。

## 移动端通用要求

以 375px 窄屏和桌面宽屏检查布局：外层保持 `width:100%;box-sizing:border-box`、最大 1290px，边距用既定 clamp；不得给页面或内容固定像素宽度。图片保留比例并限制在容器内；文本、链接和非型号长字符串可换行，避免撑出屏幕。对已有 flex 子项需要防溢出时使用 `min-width:0`；保留链接点击空间和 16px 正文字号，不以缩小字号适配。仅表格滚动容器允许局部横向滚动，页面整体不得产生横向溢出。不能仅凭列数判断，按表格语义选择字段/值上下排列或多列横向滚动；不要隐藏任何数据。标题和内容保持原有顺序。

## 资源链接列表

当 ul 每个非空 li 仅包含一个链接（允许外层 strong），保留层级、地址和原文字，使用如下内联样式：

- ul：`list-style-type:none;margin:0;padding:0;width:100%;border-top:1px solid var(--border)`
- li：`list-style-type:none;margin:0;padding:0;border-bottom:1px solid var(--border);text-indent:0`
- a：在保留原有 class 的同时添加 `dresscode-resource-link`（由站点样式提供悬停和键盘焦点交互），样式 `display:flex;align-items:center;gap:0.875rem;padding:1rem 0;color:var(--ink);font-size:1rem;font-weight:500;line-height:1.5;text-decoration:none;overflow-wrap:anywhere`
- strong 如存在：`font-weight:500;color:inherit`。链接文字放入 `<span style="flex:1 1 auto;min-width:0">`，左右各加装饰图标。整行可点击。整个片段仅为资源列表时，外层 padding 改为 `clamp(2rem,5vw,4rem) clamp(1rem,2vw,2rem)`。

根据可确认的链接文字、扩展名或目标选择左图标：视频/YouTube/MP4 → `resource-video.svg`；图片/JPG/PNG/SVG → `resource-image.svg`；ZIP/下载包 → `resource-download.svg`；文档/PDF 或不确定 → `resource-file.svg`。右侧固定 `resource-arrow.svg`。

每个 a 内按顺序输出：左图标 `<img src="{{DRESSCODE_ICON_BASE_URL}}/resource-file.svg" alt="" width="20" height="20" style="width:20px;height:20px;max-width:none;flex:0 0 20px">`（按类型替换文件名）、包裹原有文字的 span、右图标 `<img src="{{DRESSCODE_ICON_BASE_URL}}/resource-arrow.svg" alt="" width="20" height="20" style="width:20px;height:20px;max-width:none;flex:0 0 20px">`。链接文字和地址保持原样。输出完整 img 标签；不得使用内联 svg、伪元素或虚构图标 URL。两个 img 均为装饰，空 alt 不可省略。

输出前检查：标签闭合和嵌套正确；空 li 已删除；原内容及链接未改；规格字段/值表在手机上下排列；其他宽表可横向滚动且型号不换行；资源列表无圆点且有两侧图标；375px 下内容不撑出屏幕。不输出解释。
