<?php
/**
 * Classic Editor integration: AI button + AJAX optimize endpoint.
 *
 * @package DressCode Tool/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DressCode_Editor
 */
class DressCode_Editor {

	/**
	 * Settings: GLM api key.
	 *
	 * @var string
	 */
	protected $api_key = '';

	/**
	 * Settings: GLM endpoint.
	 *
	 * @var string
	 */
	protected $endpoint = '';

	/**
	 * Settings: GLM model.
	 *
	 * @var string
	 */
	protected $model = '';

	/**
	 * Settings: GLM temperature.
	 *
	 * @var float
	 */
	protected $temperature = 0.3;

	/**
	 * Settings: API dialect ('openai' or 'anthropic').
	 *
	 * @var string
	 */
	protected $format = 'openai';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_key     = get_option( 'dresscode_glm_api_key', '' );
		$this->endpoint    = get_option( 'dresscode_glm_endpoint', 'https://open.bigmodel.cn/api/paas/v4/chat/completions' );
		$this->model       = get_option( 'dresscode_glm_model', 'glm-4.6' );
		$this->temperature = (float) get_option( 'dresscode_glm_temperature', 0.3 );
		$format            = get_option( 'dresscode_glm_api_format', 'openai' );
		$this->format      = in_array( $format, array( 'openai', 'anthropic', 'glm_coding' ), true ) ? $format : 'openai';
		if ( 'glm_coding' === $this->format ) {
			$this->endpoint = 'https://open.bigmodel.cn/api/coding/paas/v4/chat/completions';
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20, 1 );
		add_action( 'wp_ajax_dresscode_optimize', array( $this, 'handle_optimize' ) );
	}

	/**
	 * Enqueue admin JS/CSS on classic post edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// Only meaningful when the classic editor is in use.
		if ( ! $this->is_classic_editor_active() ) {
			return;
		}

		$ver = filemtime( dirname( __DIR__ ) . '/assets/js/admin.js' );
		// plugin_dir_url() needs a FILE path: passing the plugin directory makes
		// the URL fall back to /wp-content/plugins/ (missing the plugin folder),
		// which 404s the script and the AI button never renders.
		$url = plugin_dir_url( dirname( __DIR__ ) . '/wordpress-plugin-template.php' ) . 'assets/';

		// The main plugin class enqueues its own admin.min.js globally; on post
		// edit screens we replace it with the non-minified admin.js that holds
		// the editor integration, to avoid loading two copies.
		wp_dequeue_script( 'dresscode-admin' );
		wp_deregister_script( 'dresscode-admin' );
		wp_enqueue_script( 'dresscode-editor', $url . 'js/admin.js', array( 'jquery' ), $ver, true );

		$skills = array();
		if ( isset( $GLOBALS['dresscode_skills'] ) && $GLOBALS['dresscode_skills'] instanceof DressCode_Skills ) {
			foreach ( $GLOBALS['dresscode_skills']->get_all_skills() as $s ) {
				$skills[] = array(
					'id'        => (int) $s->id,
					'name'      => $s->name,
					'default'   => (int) $s->is_default ? 1 : 0,
				);
			}
		}

		$default_skill_id = 0;
		foreach ( $skills as $s ) {
			if ( $s['default'] ) {
				$default_skill_id = $s['id'];
				break;
			}
		}
		if ( ! $default_skill_id && $skills ) {
			$default_skill_id = $skills[0]['id'];
		}

		wp_localize_script(
			'dresscode-editor',
			'DressCodeAI',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'dresscode_optimize' ),
				'action'          => 'dresscode_optimize',
				'skills'          => $skills,
				'defaultSkillId'  => $default_skill_id,
				'hasApiKey'       => ! empty( $this->api_key ) ? 1 : 0,
				'i18n'            => array(
					'buttonLabel'    => __( 'AI 优化', 'dresscode' ),
					'loading'        => __( 'AI 优化中…', 'dresscode' ),
					'done'           => __( '优化完成', 'dresscode' ),
					'failed'         => __( '优化失败（原因未知，请查看 DressCode → 设置中的最近失败记录）。', 'dresscode' ),
					'errorTitle'     => __( 'AI 优化失败', 'dresscode' ),
					'noKey'          => __( '未配置 GLM API Key，请到 DressCode → 设置中填写。', 'dresscode' ),
					'empty'          => __( '编辑器内容为空，无内容可优化。', 'dresscode' ),
					'stillWorking'   => __( '仍在优化中，内容较长时可能需要 1~3 分钟，请稍候…', 'dresscode' ),
					'nonceExpired'   => __( '安全校验已过期（页面打开时间过长），请刷新页面后重试。', 'dresscode' ),
					'networkError'   => __( '网络错误或请求被中断（HTTP 0）。请检查服务器能否访问设置中的接口地址。', 'dresscode' ),
					'gatewayTimeout' => __( '网关/服务器超时（HTTP %s）。内容可能过长或模型响应超时，可选中部分内容分段优化。', 'dresscode' ),
					'httpError'      => __( '请求失败（HTTP %s）', 'dresscode' ),
					'skillLabel'     => __( 'Skill', 'dresscode' ),
					'settingsUrl'    => admin_url( 'admin.php?page=dresscode_settings' ),
				),
			)
		);
	}

	/**
	 * Whether the classic editor is available for the current screen.
	 *
	 * @return bool
	 */
	protected function is_classic_editor_active() {
		// Classic Editor plugin explicitly enabled.
		if ( function_exists( 'classic_editor_init' ) ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'post' === $screen->base ) {
			// Load on every post-type edit screen (post, page, product, ...).
			// The JS itself no-ops when the classic #content container is absent
			// (e.g. pure Gutenberg), so this is safe.
			return true;
		}

		return false;
	}

	/**
	 * AJAX handler: optimize editor HTML via GLM.
	 *
	 * @return void
	 */
	public function handle_optimize() {
		check_ajax_referer( 'dresscode_optimize', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			$this->fail( __( '无权限。', 'dresscode' ), 403 );
		}

		$content   = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$skill_id  = isset( $_POST['skill_id'] ) ? (int) $_POST['skill_id'] : 0;

		$context = array(
			'user'     => get_current_user_id(),
			'skill'    => $skill_id,
			'length'   => strlen( $content ),
			'model'    => $this->model,
			'endpoint' => wp_parse_url( $this->endpoint, PHP_URL_HOST ),
		);

		if ( '' === trim( $content ) ) {
			$this->fail( __( '编辑器内容为空，无内容可优化。', 'dresscode' ), null, $context );
		}

		if ( empty( $this->api_key ) ) {
			$this->fail( __( '未配置 GLM API Key，请到 DressCode → 设置中填写。', 'dresscode' ), null, $context );
		}

		// Resolve the skill system prompt (reads SKILL.md + references from disk).
		$system_prompt = '';
		if ( isset( $GLOBALS['dresscode_skills'] ) && $GLOBALS['dresscode_skills'] instanceof DressCode_Skills ) {
			$system_prompt = $GLOBALS['dresscode_skills']->get_skill_prompt( $skill_id );
		}

		if ( '' === trim( $system_prompt ) ) {
			// Last-resort fallback prompt.
			$system_prompt = __( '你是 HTML 样式优化助手。请优化用户提供的 HTML，使其语义清晰、样式规范、响应式，只输出优化后的完整 HTML 片段，不要解释、不要 markdown 围栏。', 'dresscode' );
		}

		$client  = new DressCode_GLM_Client( $this->api_key, $this->endpoint, $this->model, $this->temperature, $this->format );
		$result  = $client->optimize( $this->pre_clean( $content ), $system_prompt );

		if ( empty( $result['success'] ) ) {
			$error = $result['error'];
			if ( false !== stripos( (string) $error, 'timed out' ) ) {
				$error = __( 'AI 生成超时（内容可能过长），请重试；若仍超时，可选中一部分内容分段优化。', 'dresscode' );
			}
			if ( isset( $result['diagnostics'] ) && is_array( $result['diagnostics'] ) ) {
				$context['response'] = $result['diagnostics'];
			}
			$this->fail( $error, null, $context + array( 'raw_error' => (string) $result['error'] ) );
		}

		// A success clears the recorded last failure.
		delete_option( 'dresscode_last_error' );

		wp_send_json_success( array( 'html' => $this->minify_html( $result['data'] ) ) );
	}

	/**
	 * Report an optimize failure to the browser and leave a trace behind:
	 * store the last error (shown on the settings page) and write it to the
	 * PHP error log. AJAX responses are easy to miss on screen and impossible
	 * to debug after the fact, so failures must be persisted as well.
	 *
	 * @param string   $message User-facing error message (also sent as JSON).
	 * @param int|null $status  Optional HTTP status for the JSON response.
	 * @param array    $context Diagnostic context (user, skill, length, ...).
	 * @return void
	 */
	protected function fail( $message, $status = null, $context = array() ) {
		$message = (string) $message;

		update_option(
			'dresscode_last_error',
			array(
				'time'    => time(),
				'message' => $message,
				'context' => $context,
			),
			false
		);

		$pairs = array();
		foreach ( $context as $key => $value ) {
			$pairs[] = $key . '=' . ( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
		}
		error_log( '[DressCode] AI 优化失败: ' . $message . ( $pairs ? ' (' . implode( ', ', $pairs ) . ')' : '' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		wp_send_json_error( $message, $status );
	}

	/**
	 * Slim the HTML before sending it to the AI. Editor-paste artifacts like
	 * <span data-font-family="default">x</span> around individual characters
	 * bloat the prompt several-fold and make generation far slower; unwrap
	 * them so the model sees the same content with a fraction of the tokens.
	 *
	 * @param string $html Raw editor content.
	 * @return string
	 */
	protected function pre_clean( $html ) {
		$pattern = '#<span\s+data-font-family=["\'][^"\']*["\']\s*>(.*?)</span>#is';

		$html = preg_replace( $pattern, '$1', $html );

		// Handle nested wrappers of the same kind.
		$prev = null;
		while ( $prev !== $html ) {
			$prev = $html;
			$html = preg_replace( $pattern, '$1', $html );
		}

		return $html;
	}

	/**
	 * Compact the AI-optimized HTML before it is inserted into the editor:
	 * drop pretty-print indentation/line breaks between tags, strip HTML
	 * comments and minify inline <style> blocks. Whitespace that could be
	 * meaningful is preserved: <pre>/<textarea>/<script> contents and a
	 * single same-line space between tags (separates inline elements).
	 *
	 * @param string $html Optimized HTML from the model.
	 * @return string
	 */
	protected function minify_html( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return $html;
		}

		// Drop HTML comments first (placeholders below must survive).
		$html = preg_replace( '#<!--.*?-->#s', '', $html );

		// Stash whitespace-sensitive / self-contained blocks as comment
		// placeholders so the inter-tag collapsing cannot touch them.
		$placeholders = array();
		$html         = preg_replace_callback(
			'#<(pre|textarea|script|style)\b[^>]*>.*?</\1>#is',
			function ( $m ) use ( &$placeholders ) {
				$block = $m[0];
				if ( 'style' === strtolower( $m[1] ) ) {
					$block = $this->minify_css( $block );
				}
				$key                = '<!--CTAI' . count( $placeholders ) . '-->';
				$placeholders[ $key ] = $block;
				return $key;
			},
			$html
		);

		// Collapse pure-whitespace spans between tags. Line breaks/tabs are
		// pretty-print indentation and are removed; same-line spaces may
		// separate inline elements and are kept as a single space.
		$html = preg_replace_callback(
			'#>(\s+)<#',
			function ( $m ) {
				$ws = $m[1];
				if ( false !== strpos( $ws, "\n" ) || false !== strpos( $ws, "\r" ) || false !== strpos( $ws, "\t" ) ) {
					return '><';
				}
				return '> <';
			},
			$html
		);

		$html = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $html );

		return trim( $html );
	}

	/**
	 * Light CSS minification for a <style> block: collapse whitespace and
	 * remove it around structural symbols. Spaces inside values (e.g.
	 * "to top", "1px solid red") are preserved.
	 *
	 * @param string $css <style> block including its tags.
	 * @return string
	 */
	protected function minify_css( $css ) {
		// Trim the inner CSS (between the tags) before collapsing.
		$css = preg_replace_callback(
			'#(<style\b[^>]*>)\s*(.*?)\s*(</style>)#is',
			function ( $m ) {
				return $m[1] . $m[2] . $m[3];
			},
			$css
		);
		$css = preg_replace( '#\s+#', ' ', $css );
		$css = preg_replace( '#\s*([{};:,])\s*#', '$1', $css );
		return trim( $css );
	}
}
