<?php
/**
 * Build lang/dresscode-en_US.po + .mo from the generated POT and the
 * translation map below. Usage: php tools/build-translations.php
 *
 * Source strings are authored in Chinese (zh_CN is the source locale); this
 * provides the English translation for English admin languages.
 */

$root  = dirname( __DIR__ );
$pot   = file_get_contents( $root . '/lang/dresscode.pot' );

// msgid (Chinese source) => English msgstr.
$map = array(
	'0~2，越小越稳定，建议 0.2~0.5。' => '0–2. Lower is more deterministic; 0.2–0.5 is recommended.',
	'AI 优化' => 'AI Optimize',
	'AI 优化中…' => 'Optimizing…',
	'AI 生成超时（内容可能过长），请重试；若仍超时，可选中一部分内容分段优化。' => 'The AI request timed out (the content may be too long). Please retry; if it keeps timing out, select part of the content and optimize it in sections.',
	'API 格式' => 'API Format',
	'Anthropic Messages (/v1/messages)' => 'Anthropic Messages (/v1/messages)',
	'DressCode' => 'DressCode',
	'DressCode 设置' => 'DressCode Settings',
	'DressCode Skills' => 'DressCode Skills',
	'DressCode 通用规范' => 'DressCode Standard',
	'GLM API Key' => 'GLM API Key',
	'GLM 接口返回内容为空或格式异常。' => 'The GLM API returned empty or malformed content.',
	'GLM 接口返回错误（%1$s）：%2$s' => 'GLM API error (%1$s): %2$s',
	'GLM 接口配置' => 'GLM API Configuration',
	'ID' => 'ID',
	'OpenAI 兼容 (chat/completions)' => 'OpenAI-compatible (chat/completions)',
	'SKILL.md（System Prompt）' => 'SKILL.md (System Prompt)',
	'Settings' => 'Settings',
	'Skill' => 'Skill',
	'Skills' => 'Skills',
	'Temperature' => 'Temperature',
	'zip 内须含 SKILL.md（可在根目录，或单个子文件夹下）。上传后将以此 zip 内容创建/替换 Skill 文件夹，下面的 SKILL.md 文本框会被忽略。' => 'The zip must contain a SKILL.md (at the archive root, or inside a single subfolder). Uploading creates/replaces the skill folder with the zip contents; the SKILL.md text box below is then ignored.',
	'上传 zip 导入' => 'Import via zip upload',
	'上传的 zip 文件无效。' => 'The uploaded zip file is invalid.',
	'优化失败' => 'Optimization failed',
	'优化完成' => 'Optimization complete',
	'你是 HTML 样式优化助手。请严格按照系统消息中的规范，优化下面这段 HTML 的样式与结构：保留语义化标签与原有内容文字，补充必要的内联样式或 CSS 变量，使其符合品牌视觉标准。只输出优化后的完整 HTML 代码片段，不要任何解释说明，不要使用 markdown 代码围栏（```）。需要优化的 HTML 如下：' => 'You are an HTML styling assistant. Strictly following the rules in the system message, optimize the style and structure of the HTML below: keep the semantic tags and the original text, and add inline styles or CSS variables as needed to match the brand visual standard. Output only the complete optimized HTML fragment — no explanations, no markdown code fences. The HTML to optimize is as follows:',
	'你是 HTML 样式优化助手。请优化用户提供的 HTML，使其语义清晰、样式规范、响应式，只输出优化后的完整 HTML 片段，不要解释、不要 markdown 围栏。' => 'You are an HTML styling assistant. Optimize the user-provided HTML so that it is semantic, consistently styled and responsive. Output only the complete optimized HTML fragment — no explanations, no markdown fences.',
	'保存 Skill' => 'Save Skill',
	'删除' => 'Delete',
	'压缩包内未找到 SKILL.md（应在根目录或单个子文件夹下）。' => 'No SKILL.md found in the archive (it must be at the root or inside a single subfolder).',
	'参考文件' => 'Reference files',
	'名称' => 'Name',
	'名称不能为空。' => 'The name cannot be empty.',
	'在 open.bigmodel.cn 获取的 API Key，以密码框掩码显示；如需更换直接覆盖。' => 'API key from open.bigmodel.cn, shown masked in a password field; overwrite it to replace.',
	'如 glm-4.6、glm-4.5、glm-4-plus 等，按账号可用模型填写。' => 'e.g. glm-4.6, glm-4.5, glm-4-plus — use a model available to your account.',
	'完整端点 URL。OpenAI 格式如 https://open.bigmodel.cn/api/paas/v4/chat/completions；Anthropic 格式如 https://your-relay.com/v1/messages。' => 'Full endpoint URL. OpenAI format e.g. https://open.bigmodel.cn/api/paas/v4/chat/completions; Anthropic format e.g. https://your-relay.com/v1/messages.',
	'按服务商选择：OpenAI 兼容（chat/completions）或 Anthropic Messages（/v1/messages）。' => 'Choose per your provider: OpenAI-compatible (chat/completions) or Anthropic Messages (/v1/messages).',
	'接口地址' => 'Endpoint URL',
	'描述' => 'Description',
	'操作' => 'Actions',
	'文件夹' => 'Folder',
	'文件夹名（slug）创建后不可更改；上传 zip 会替换其内容。' => 'The folder slug cannot be changed after creation; uploading a zip replaces its contents.',
	'新增 / 导入 Skill' => 'Add / Import Skill',
	'无效请求。' => 'Invalid request.',
	'无权限。' => 'Permission denied.',
	'无法初始化文件系统。' => 'Unable to initialize the filesystem.',
	'暂无 Skill，请新增或导入。' => 'No skills yet — add or import one.',
	'未上传 zip 时，以此文本作为 SKILL.md 内容保存。可选附带 references/*.md（在 zip 内）。' => 'When no zip is uploaded, this text is saved as SKILL.md. Optionally include references/*.md inside the zip.',
	'未能创建 Skill 文件夹。' => 'Could not create the skill folder.',
	'未配置 GLM API Key，请先在 DressCode 设置中填写。' => 'No GLM API key configured. Set one under DressCode → Settings first.',
	'未配置 GLM API Key，请到 DressCode → 设置中填写。' => 'No GLM API key configured — please fill it in under DressCode → Settings.',
	'模型' => 'Model',
	'每个 Skill 是一个独立文件夹（含 SKILL.md，可选 references/*.md）。可手写或上传 zip 导入。AI 按选中的 Skill 优化编辑器里的 HTML。' => 'Each skill is a standalone folder (with a SKILL.md and optional references/*.md). Write it by hand or import a zip. The AI optimizes the editor HTML according to the selected skill.',
	'编辑' => 'Edit',
	'编辑 Skill： %s' => 'Edit skill: %s',
	'编辑器 AI 按钮未选择时使用此 Skill' => 'Use this skill when nothing is selected in the editor AI dropdown',
	'编辑器内容为空，无内容可优化。' => 'The editor is empty — nothing to optimize.',
	'设为默认' => 'Set as default',
	'设置' => 'Settings',
	'请上传 zip，或填写 SKILL.md 内容。' => 'Upload a zip, or fill in the SKILL.md content.',
	'返回' => 'Back',
	'这些文件会自动拼接到 System Prompt 末尾。仅 zip 导入可带入。' => 'These files are appended to the system prompt automatically. They can only be brought in via zip import.',
	'配置智谱 GLM（BigModel）API。API Key 优先在此填写。' => 'Configure the Zhipu GLM (BigModel) API. Set the API key here.',
	'默认' => 'Default',
	'默认通用规范：rem 字体系统、1.5 行高、圆角、响应式基准、CSS 变量。可编辑或重新上传覆盖。' => 'Default generic spec: rem font scale, 1.5 line height, border radii, responsive baselines, CSS variables. Edit or re-upload to override.',
);

// Parse msgids from the POT.
preg_match_all( '/^msgid "((?:[^"\\\\]|\\\\.)*)"$/m', $pot, $m );
$ids = array();
foreach ( $m[1] as $raw ) {
	$id = stripcslashes( $raw ); // same as PO unescape: \" \\ \n \t
	if ( '' !== $id ) {
		$ids[] = $id;
	}
}

$missing = array_diff( $ids, array_keys( $map ) );
if ( $missing ) {
	echo "MISSING TRANSLATIONS:\n";
	foreach ( $missing as $s ) {
		echo '  - ' . $s . "\n";
	}
	exit( 1 );
}

/**
 * Minimal gettext .mo writer (little-endian).
 *
 * @param array $entries Array of [msgid, msgstr] pairs.
 * @param string $file   Output path.
 */
function write_mo( $entries, $file ) {
	$count      = count( $entries );
	$tab_size   = 8 * $count;
	$header_size = 7 * 4;
	$orig_tab   = $header_size;
	$trans_tab  = $orig_tab + $tab_size;
	$orig_data  = '';
	$trans_data = '';
	$orig_idx   = array();
	$trans_idx  = array();

	foreach ( $entries as $pair ) {
		list( $id, $str ) = $pair;
		$orig_idx[] = array( strlen( $id ), strlen( $orig_data ) );
		$orig_data .= $id . "\x00";
		$trans_idx[] = array( strlen( $str ), strlen( $trans_data ) );
		$trans_data .= $str . "\x00";
	}

	$orig_data_off  = $trans_tab + $tab_size;
	$trans_data_off = $orig_data_off + strlen( $orig_data );

	$out  = pack( 'V*', 0x950412de, 0, $count, $orig_tab, $trans_tab, 0, 0 );
	foreach ( $orig_idx as $e ) {
		$out .= pack( 'V*', $e[0], $e[1] + $orig_data_off );
	}
	foreach ( $trans_idx as $e ) {
		$out .= pack( 'V*', $e[0], $e[1] + $trans_data_off );
	}
	$out .= $orig_data . $trans_data;

	file_put_contents( $file, $out );
}

$meta  = 'Project-Id-Version: DressCode 0.1.0' . "\n";
$meta .= 'MIME-Version: 1.0' . "\n";
$meta .= 'Content-Type: text/plain; charset=UTF-8' . "\n";
$meta .= 'Content-Transfer-Encoding: 8bit' . "\n";
$meta .= 'Language: en_US' . "\n";
$meta .= 'X-Domain: dresscode' . "\n";

$po_entries = array( array( '', $meta ) );
foreach ( $ids as $id ) {
	$po_entries[] = array( $id, $map[ $id ] );
}

$po  = 'msgid ""' . "\n" . 'msgstr ""' . "\n";
foreach ( explode( "\n", $meta ) as $line ) {
	$po .= '"' . $line . '\n"' . "\n";
}
$po .= "\n";
foreach ( $po_entries as $i => $pair ) {
	if ( 0 === $i ) {
		continue;
	}
	list( $id, $str ) = $pair;
	$esc = function ( $s ) {
		return str_replace( array( '\\', '"', "\n", "\r", "\t" ), array( '\\\\', '\\"', '\\n', '', '\\t' ), $s );
	};
	$po .= 'msgid "' . $esc( $id ) . '"' . "\n";
	$po .= 'msgstr "' . $esc( $str ) . '"' . "\n\n";
}
file_put_contents( $root . '/lang/dresscode-en_US.po', $po );
write_mo( $po_entries, $root . '/lang/dresscode-en_US.mo' );

echo count( $ids ) . " strings: lang/dresscode-en_US.po + .mo written\n";
