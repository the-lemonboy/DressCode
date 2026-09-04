<?php
/**
 * Plugin Name: DressCode
 * Version: 0.1.0
 * Description: One-click AI styling for the WordPress classic editor — optimize post/page/product HTML according to admin-managed skills (style guides), via GLM or any OpenAI-compatible / Anthropic Messages endpoint. API keys stay in your own database.
 * Author: lemon
 * Requires at least: 5.6
 * Requires PHP:   7.4
 * Tested up to: 7.0
 *
 * Text Domain: dresscode
 * Domain Path: /lang/
 *
 * @package DressCode
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-wordpress-plugin-template.php';
require_once 'includes/class-wordpress-plugin-template-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-wordpress-plugin-template-admin-api.php';
require_once 'includes/lib/class-wordpress-plugin-template-post-type.php';
require_once 'includes/lib/class-wordpress-plugin-template-taxonomy.php';

// Load DressCode modules.
require_once 'includes/class-dresscode-glm-client.php';
require_once 'includes/class-dresscode-skills.php';
require_once 'includes/class-dresscode-editor.php';

/**
 * Returns the main instance of WordPress_Plugin_Template to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object WordPress_Plugin_Template
 */
function wordpress_plugin_template() {
	$instance = WordPress_Plugin_Template::instance( __FILE__, '0.1.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = WordPress_Plugin_Template_Settings::instance( $instance );
	}

	if ( is_null( $instance->skills ) ) {
		$instance->skills = new DressCode_Skills();
	}

	if ( is_null( $instance->editor ) ) {
		$instance->editor = new DressCode_Editor();
	}

	// Expose skills globally for the editor module to read at runtime.
	$GLOBALS['dresscode_skills'] = $instance->skills;

	return $instance;
}

wordpress_plugin_template();
