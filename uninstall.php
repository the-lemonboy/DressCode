<?php
/**
 * This file runs when the plugin is uninstalled (deleted).
 * It does not run when the plugin is deactivated.
 *
 * Removes plugin options, the skills table and the uploaded
 * skill folders (current "dresscode" and legacy "camthink" names).
 *
 * @package DressCode/Uninstall
 */

// If plugin is not being uninstalled, exit (do nothing).
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options (API key and other GLM settings included),
// including keys from the legacy "camthink-ai-tool" naming.
$option_names = array(
	'dresscode_glm_api_key',
	'dresscode_glm_api_format',
	'dresscode_glm_endpoint',
	'dresscode_glm_model',
	'dresscode_glm_temperature',
	'dresscode_version',
	'dresscode_db_version',
	'camthink_glm_api_key',
	'camthink_glm_api_format',
	'camthink_glm_endpoint',
	'camthink_glm_model',
	'camthink_glm_temperature',
	'camthink_ai_tool_version',
	'camthink_ai_tool_db_version',
);
foreach ( $option_names as $name ) {
	delete_option( $name );
}

// Drop the skills tables (current + legacy).
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dresscode_skills" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}camthink_skills" ); // phpcs:ignore

// Remove uploaded skill folders (current + legacy).
$uploads = wp_upload_dir();
$dirs    = array(
	trailingslashit( $uploads['basedir'] ) . 'dresscode-skills',
	trailingslashit( $uploads['basedir'] ) . 'camthink-skills',
);

foreach ( $dirs as $dir ) {
	if ( is_dir( $dir ) && ! is_link( $dir ) ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore
			} else {
				@unlink( $item->getPathname() ); // phpcs:ignore
			}
		}
		@rmdir( $dir ); // phpcs:ignore
	}
}
