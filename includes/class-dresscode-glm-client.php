<?php
/**
 * GLM (Zhipu BigModel) API client.
 *
 * OpenAI-compatible chat completions endpoint.
 *
 * @package DressCode Tool/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DressCode_GLM_Client
 *
 * Wraps a single call to the GLM chat completions API to optimize HTML.
 */
class DressCode_GLM_Client {

	/**
	 * API key.
	 *
	 * @var string
	 */
	protected $api_key = '';

	/**
	 * API endpoint.
	 *
	 * @var string
	 */
	protected $endpoint = '';

	/**
	 * Model name.
	 *
	 * @var string
	 */
	protected $model = '';

	/**
	 * Sampling temperature.
	 *
	 * @var float
	 */
	protected $temperature = 0.3;

	/**
	 * API dialect: 'openai' (chat/completions) or 'anthropic' (/v1/messages).
	 *
	 * @var string
	 */
	protected $format = 'openai';

	/**
	 * Constructor.
	 *
	 * @param string $api_key     API key.
	 * @param string $endpoint    Chat completions / messages endpoint.
	 * @param string $model       Model name.
	 * @param float  $temperature Temperature.
	 * @param string $format      API format: 'openai' or 'anthropic'.
	 */
	public function __construct( $api_key = '', $endpoint = '', $model = '', $temperature = 0.3, $format = 'openai' ) {
		$this->api_key     = $api_key;
		$this->endpoint    = $endpoint ? $endpoint : 'https://open.bigmodel.cn/api/paas/v4/chat/completions';
		$this->model       = $model ? $model : 'glm-4.6';
		$this->temperature = is_numeric( $temperature ) ? (float) $temperature : 0.3;
		$this->format      = ( 'anthropic' === $format ) ? 'anthropic' : 'openai';
	}

	/**
	 * Optimize a piece of HTML using the given skill system prompt.
	 *
	 * @param string $html          HTML to optimize.
	 * @param string $system_prompt Skill system prompt / style rules.
	 * @return array { 'success' => bool, 'data' => string, 'error' => string }
	 */
	public function optimize( $html, $system_prompt ) {

		if ( empty( $this->api_key ) ) {
			return array(
				'success' => false,
				'data'    => '',
				'error'   => __( '未配置 GLM API Key，请先在 DressCode 设置中填写。', 'dresscode' ),
			);
		}

		$user_prompt = $this->build_user_prompt();

		if ( 'anthropic' === $this->format ) {
			// Anthropic Messages API: system is a top-level param, max_tokens
			// is required, temperature must be within 0..1. Reasoning models
			// burn part of the budget on thinking blocks, so keep generous
			// headroom (a messy 25K-char page needed ~14.5K output tokens).
			$body = array(
				'model'       => $this->model,
				'max_tokens'  => 32768,
				'temperature' => max( 0.0, min( 1.0, $this->temperature ) ),
				'stream'      => false,
				'system'      => $system_prompt,
				'messages'    => array(
					array(
						'role'    => 'user',
						'content' => $user_prompt . "\n\n" . $html,
					),
				),
			);
			$headers = array(
				'x-api-key'         => $this->api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			);
		} else {
			// OpenAI-compatible chat completions.
			$body = array(
				'model'       => $this->model,
				'temperature' => $this->temperature,
				'stream'      => false,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => $system_prompt,
					),
					array(
						'role'    => 'user',
						'content' => $user_prompt . "\n\n" . $html,
					),
				),
			);
			$headers = array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			);
		}

		// Reasoning models (glm-4.5+/glm-5.x) may think for a while before
		// emitting the optimized HTML; keep generous headroom.
		$timeout = 300;

		// Some stacks cap the effective cURL timeout at
		// default_socket_timeout (60s here) regardless of the wp_remote_post
		// timeout argument; force it on the handle as the last word before
		// curl_exec, and remove the hook right after our request.
		$force_timeout = function ( $handle ) use ( $timeout ) {
			if ( function_exists( 'curl_setopt' ) ) {
				curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout );
			}
		};
		add_action( 'requests-curl.before_send', $force_timeout, 10, 1 );

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		remove_action( 'requests-curl.before_send', $force_timeout, 10 );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'data'    => '',
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );

		if ( 200 !== (int) $code ) {
			$msg = $this->extract_error_message( $raw );
			return array(
				'success' => false,
				'data'    => '',
				'error'   => sprintf(
					/* translators: 1: HTTP status code, 2: error message */
					__( 'GLM 接口返回错误（%1$s）：%2$s', 'dresscode' ),
					$code,
					$msg ? $msg : $raw
				),
			);
		}

		$decoded = json_decode( $raw, true );

		if ( 'anthropic' === $this->format ) {
			// {"content":[{"type":"text","text":"..."}], ...}
			$content = '';
			if ( is_array( $decoded ) && ! empty( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
				foreach ( $decoded['content'] as $block ) {
					if ( is_array( $block ) && isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
						$content .= $block['text'];
					}
				}
			}
		} else {
			// {"choices":[{"message":{"content":"..."}}]}
			$content = ( is_array( $decoded ) && isset( $decoded['choices'][0]['message']['content'] ) )
				? (string) $decoded['choices'][0]['message']['content']
				: '';
		}

		if ( '' === trim( $content ) ) {
			return array(
				'success' => false,
				'data'    => '',
				'error'   => __( 'GLM 接口返回内容为空或格式异常。', 'dresscode' ),
			);
		}

		$content = $this->strip_fences( $content );

		return array(
			'success' => true,
			'data'    => $content,
			'error'   => '',
		);
	}

	/**
	 * Build the user-side instruction prepended to the HTML.
	 *
	 * @return string
	 */
	protected function build_user_prompt() {
		return __( '你是 HTML 样式优化助手。请严格按照系统消息中的规范，优化下面这段 HTML 的样式与结构：保留语义化标签与原有内容文字，补充必要的内联样式或 CSS 变量，使其符合品牌视觉标准。只输出优化后的完整 HTML 代码片段，不要任何解释说明，不要使用 markdown 代码围栏（```）。需要优化的 HTML 如下：', 'dresscode' );
	}

	/**
	 * Strip markdown code fences and surrounding whitespace from the response.
	 *
	 * @param string $content Raw model output.
	 * @return string
	 */
	protected function strip_fences( $content ) {
		$content = trim( $content );

		// Remove leading ```html / ``` and trailing ```.
		if ( preg_match( '/^```(?:html)?\s*\n(.+?)\n```$/is', $content, $m ) ) {
			$content = trim( $m[1] );
		} else {
			// Looser fallback: strip any leading/trailing fence line.
			$content = preg_replace( '/^\s*```(?:html)?\s*\n?/i', '', $content );
			$content = preg_replace( '/\n?```\s*$/', '', $content );
			$content = trim( $content );
		}

		return $content;
	}

	/**
	 * Try to extract a human-readable error message from an error response body.
	 *
	 * @param string $raw Response body.
	 * @return string
	 */
	protected function extract_error_message( $raw ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['error']['message'] ) ) {
				return $decoded['error']['message'];
			}
			if ( isset( $decoded['msg'] ) ) {
				return $decoded['msg'];
			}
			if ( isset( $decoded['message'] ) ) {
				return $decoded['message'];
			}
		}
		return '';
	}
}
