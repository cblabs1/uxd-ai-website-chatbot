<?php
/**
 * Fired during plugin deactivation
 *
 * @package AI_Website_Chatbot
 * @subpackage Includes
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since 1.0.0
 */
class AI_Chatbot_Deactivator {

	/**
	 * Plugin deactivation handler
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Clear scheduled events
		self::clear_scheduled_events();

		// Clear transients
		self::clear_transients();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Clear any cached data
		self::clear_cache();
	}

	/**
	 * Clear all scheduled events
	 *
	 * @since 1.0.0
	 */
	private static function clear_scheduled_events() {
		$scheduled_hooks = array(
			'ai_chatbot_daily_cleanup',
			'ai_chatbot_weekly_sync',
			'ai_chatbot_hourly_rate_limit_reset',
		);

		foreach ( $scheduled_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}

		// Clear all instances of recurring events
		wp_clear_scheduled_hook( 'ai_chatbot_daily_cleanup' );
		wp_clear_scheduled_hook( 'ai_chatbot_weekly_sync' );
		wp_clear_scheduled_hook( 'ai_chatbot_hourly_rate_limit_reset' );
	}

	/**
	 * Clear plugin transients
	 *
	 * @since 1.0.0
	 */
	private static function clear_transients() {
		global $wpdb;

		// Delete all plugin-related transients
		$transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_ai_chatbot_%',
				'_transient_timeout_ai_chatbot_%'
			)
		);

		foreach ( $transients as $transient ) {
			delete_option( $transient->option_name );
		}
	}

	/**
	 * Clear any cached data
	 *
	 * @since 1.0.0
	 */
	private static function clear_cache() {
		// Clear WordPress object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Clear any plugin-specific cache files
		$upload_dir = wp_upload_dir();
		$cache_dir = $upload_dir['basedir'] . '/ai-chatbot/cache';
		
		if ( is_dir( $cache_dir ) ) {
			self::delete_directory_contents( $cache_dir );
		}
	}

	/**
	 * Recursively delete directory contents
	 *
	 * @param string $dir Directory path.
	 * @since 1.0.0
	 */
	private static function delete_directory_contents( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::delete_directory_contents( $path );
				rmdir( $path );
			} else {
				unlink( $path );
			}
		}
	}
}