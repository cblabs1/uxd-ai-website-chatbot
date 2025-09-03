<?php
/**
 * Fired when the plugin is uninstalled
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Uninstall AI Website Chatbot plugin
 *
 * Removes all plugin data including:
 * - Database tables
 * - Plugin options
 * - Transients
 * - Upload directory and files
 * - User meta data
 */
class AI_Chatbot_Uninstaller {

	/**
	 * Run uninstall process
	 */
	public static function uninstall() {
		// Check if user has permission to uninstall
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Check if we should keep data on uninstall
		if ( get_option( 'ai_chatbot_keep_data_on_uninstall', false ) ) {
			return;
		}

		// Remove database tables
		self::drop_database_tables();

		// Remove all plugin options
		self::remove_plugin_options();

		// Remove user meta data
		self::remove_user_meta();

		// Remove transients
		self::remove_transients();

		// Remove upload directory
		self::remove_upload_directory();

		// Clear scheduled events
		self::clear_scheduled_events();
	}

	/**
	 * Drop all plugin database tables
	 */
	private static function drop_database_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'ai_chatbot_conversations',
			$wpdb->prefix . 'ai_chatbot_content',
			$wpdb->prefix . 'ai_chatbot_training_sessions',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS `%s`", $table ) );
		}
	}

	/**
	 * Remove all plugin options
	 */
	private static function remove_plugin_options() {
		global $wpdb;

		// Get all plugin options
		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'ai_chatbot_%'
			)
		);

		// Delete each option
		foreach ( $options as $option ) {
			delete_option( $option->option_name );
		}

		// Remove specific options that might not follow the pattern
		$specific_options = array(
			'ai_chatbot_version',
			'ai_chatbot_db_version',
			'ai_chatbot_activation_redirect',
			'ai_chatbot_keep_data_on_uninstall',
		);

		foreach ( $specific_options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Remove user meta data related to the plugin
	 */
	private static function remove_user_meta() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				'ai_chatbot_%'
			)
		);
	}

	/**
	 * Remove all plugin transients
	 */
	private static function remove_transients() {
		global $wpdb;

		// Delete transients
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

		// Delete site transients for multisite
		if ( is_multisite() ) {
			$site_transients = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'_site_transient_ai_chatbot_%',
					'_site_transient_timeout_ai_chatbot_%'
				)
			);

			foreach ( $site_transients as $transient ) {
				delete_site_option( str_replace( '_site_transient_', '', $transient->option_name ) );
			}
		}
	}

	/**
	 * Remove plugin upload directory and all files
	 */
	private static function remove_upload_directory() {
		$upload_dir = wp_upload_dir();
		$plugin_dir = $upload_dir['basedir'] . '/ai-chatbot';

		if ( is_dir( $plugin_dir ) ) {
			self::delete_directory_recursive( $plugin_dir );
		}
	}

	/**
	 * Recursively delete a directory and all its contents
	 *
	 * @param string $dir Directory path.
	 */
	private static function delete_directory_recursive( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::delete_directory_recursive( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}

	/**
	 * Clear all scheduled events
	 */
	private static function clear_scheduled_events() {
		$scheduled_hooks = array(
			'ai_chatbot_daily_cleanup',
			'ai_chatbot_weekly_sync',
			'ai_chatbot_hourly_rate_limit_reset',
		);

		foreach ( $scheduled_hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}

// Run the uninstall process
AI_Chatbot_Uninstaller::uninstall();