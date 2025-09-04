<?php
/**
 * Fired during plugin activation
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
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 1.0.0
 */
class AI_Chatbot_Activator {

	/**
	 * Plugin activation handler
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Check WordPress version
		self::check_wordpress_version();

		// Check PHP version
		self::check_php_version();

		// Create database tables
		self::create_database_tables();

		// Set default options
		self::set_default_options();

		// Create upload directory
		self::create_upload_directory();

		// Schedule cleanup events
		self::schedule_cleanup_events();

		// Set activation flag
		add_option( 'ai_chatbot_activation_redirect', true );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Check WordPress version compatibility
	 *
	 * @since 1.0.0
	 * @throws Exception If WordPress version is incompatible.
	 */
	private static function check_wordpress_version() {
		$required_wp_version = '5.0';
		
		if ( version_compare( get_bloginfo( 'version' ), $required_wp_version, '<' ) ) {
			deactivate_plugins( AI_CHATBOT_PLUGIN_BASENAME );
			wp_die(
				sprintf(
					/* translators: 1: Plugin name, 2: Required WordPress version */
					esc_html__( '%1$s requires WordPress %2$s or higher. Please upgrade WordPress and try again.', 'ai-website-chatbot' ),
					'<strong>AI Website Chatbot</strong>',
					$required_wp_version
				),
				esc_html__( 'Plugin Activation Error', 'ai-website-chatbot' ),
				array(
					'back_link' => true,
				)
			);
		}
	}

	/**
	 * Check PHP version compatibility
	 *
	 * @since 1.0.0
	 * @throws Exception If PHP version is incompatible.
	 */
	private static function check_php_version() {
		$required_php_version = '7.4';
		
		if ( version_compare( PHP_VERSION, $required_php_version, '<' ) ) {
			deactivate_plugins( AI_CHATBOT_PLUGIN_BASENAME );
			wp_die(
				sprintf(
					/* translators: 1: Plugin name, 2: Required PHP version, 3: Current PHP version */
					esc_html__( '%1$s requires PHP %2$s or higher. Your server is currently running PHP %3$s. Please upgrade PHP and try again.', 'ai-website-chatbot' ),
					'<strong>AI Website Chatbot</strong>',
					$required_php_version,
					PHP_VERSION
				),
				esc_html__( 'Plugin Activation Error', 'ai-website-chatbot' ),
				array(
					'back_link' => true,
				)
			);
		}
	}

	/**
	 * Create database tables
	 *
	 * @since 1.0.0
	 */
	private static function create_database_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Conversations table
		$conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
		$sql_conversations = "CREATE TABLE $conversations_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(255) NOT NULL,
			user_message longtext NOT NULL,
			bot_response longtext NOT NULL,
			ai_response longtext DEFAULT NULL,
			user_name varchar(255) DEFAULT '',
			user_ip varchar(100) DEFAULT '',
			page_url varchar(2048) DEFAULT '',
			user_agent varchar(500) DEFAULT '',
			status varchar(20) DEFAULT 'completed',
			intent varchar(255) DEFAULT NULL,
			rating tinyint(1) DEFAULT NULL,
			response_time decimal(8,3) DEFAULT NULL,
			tokens_used int(10) unsigned DEFAULT NULL,
			provider varchar(50) DEFAULT NULL,
			model varchar(100) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY created_at (created_at),
			KEY rating (rating),
			KEY status (status),
			KEY intent (intent),
			KEY provider (provider)
		) $charset_collate;";

		// Website content index table
		$content_table = $wpdb->prefix . 'ai_chatbot_content';
		$sql_content = "CREATE TABLE $content_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned,
			content_type varchar(50) NOT NULL,
			title text,
			content longtext,
			url varchar(2048) DEFAULT '',
			content_hash varchar(64) DEFAULT '',
			embedding_status varchar(20) DEFAULT 'pending',
			last_trained datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY content_type (content_type),
			KEY embedding_status (embedding_status),
			KEY content_hash (content_hash),
			KEY last_trained (last_trained)
		) $charset_collate;";

		// Training sessions table
		$training_table = $wpdb->prefix . 'ai_chatbot_training_sessions';
		$sql_training = "CREATE TABLE $training_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_name varchar(255) NOT NULL,
			status varchar(20) DEFAULT 'pending',
			total_items int(10) unsigned DEFAULT 0,
			processed_items int(10) unsigned DEFAULT 0,
			error_message text,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_conversations );
		dbDelta( $sql_content );
		dbDelta( $sql_training );

		// Store database version for future upgrades
		add_option( 'ai_chatbot_db_version', '1.0.0' );
	}

	/**
	 * Set default plugin options
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		$default_options = array(
			'ai_chatbot_enabled'                => false, // Disabled by default for security
			'ai_chatbot_ai_provider'           => 'openai',
			'ai_chatbot_position'              => 'bottom-right',
			'ai_chatbot_theme_color'           => '#0073aa',
			'ai_chatbot_welcome_message'       => __( 'Hello! How can I help you today?', 'ai-website-chatbot' ),
			'ai_chatbot_placeholder_text'      => __( 'Type your message...', 'ai-website-chatbot' ),
			'ai_chatbot_auto_train'            => false,
			'ai_chatbot_data_retention_days'   => 30,
			'ai_chatbot_collect_ip'            => false,
			'ai_chatbot_collect_user_agent'    => false,
			'ai_chatbot_rate_limit_per_minute' => 10,
			'ai_chatbot_rate_limit_per_hour'   => 50,
			'ai_chatbot_max_message_length'    => 1000,
			'ai_chatbot_enable_rating'         => true,
			'ai_chatbot_show_powered_by'       => true,
			'ai_chatbot_privacy_policy_url'    => '',
			'ai_chatbot_terms_url'             => '',
			'ai_chatbot_allowed_post_types'    => array( 'post', 'page' ),
			'ai_chatbot_excluded_pages'        => array(),
			'ai_chatbot_openai_api_key'        => '',
			'ai_chatbot_openai_model'          => 'gpt-3.5-turbo',
			'ai_chatbot_openai_temperature'    => 0.7,
			'ai_chatbot_openai_max_tokens'     => 300,
			'ai_chatbot_claude_api_key'        => '',
			'ai_chatbot_claude_model'          => 'claude-3-haiku-20240307',
			'ai_chatbot_gemini_api_key'        => '',
			'ai_chatbot_gemini_model'          => 'gemini-pro',
		);

		foreach ( $default_options as $option_name => $default_value ) {
			add_option( $option_name, $default_value );
		}

		// Store plugin version
		add_option( 'ai_chatbot_version', AI_CHATBOT_VERSION );
	}

	/**
	 * Create upload directory for plugin files
	 *
	 * @since 1.0.0
	 */
	private static function create_upload_directory() {
		$upload_dir = wp_upload_dir();
		$chatbot_dir = $upload_dir['basedir'] . '/ai-chatbot';

		if ( ! file_exists( $chatbot_dir ) ) {
			wp_mkdir_p( $chatbot_dir );
		}

		// Create .htaccess file for security
		$htaccess_file = $chatbot_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# AI Chatbot Upload Directory Protection\n";
			$htaccess_content .= "Options -Indexes\n";
			$htaccess_content .= "<Files *.php>\n";
			$htaccess_content .= "Order Allow,Deny\n";
			$htaccess_content .= "Deny from all\n";
			$htaccess_content .= "</Files>\n";
			
			file_put_contents( $htaccess_file, $htaccess_content );
		}

		// Create index.php file for additional security
		$index_file = $chatbot_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, '<?php // Silence is golden.' );
		}
	}

	/**
	 * Database schema update to includes/class-ai-chatbot-activator.php
	 * Fix for the missing database columns
	 */
	public static function update_database_schema() {
		global $wpdb;

		$current_db_version = get_option('ai_chatbot_db_version', '1.0.0');
		
		if (version_compare($current_db_version, '1.1.0', '<')) {
			$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
			
			// Check if table exists first
			if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
				// Table doesn't exist, create it with correct schema
				self::create_database_tables();
				return;
			}

			// Add missing columns one by one
			$columns_to_add = array(
				'ai_response' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN ai_response longtext DEFAULT NULL',
					'check' => 'ai_response'
				),
				'user_name' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN user_name varchar(255) DEFAULT ""',
					'check' => 'user_name'
				),
				'status' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN status varchar(20) DEFAULT "completed"',
					'check' => 'status'
				),
				'intent' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN intent varchar(255) DEFAULT NULL',
					'check' => 'intent'
				),
				'response_time' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN response_time decimal(8,3) DEFAULT NULL',
					'check' => 'response_time'
				),
				'tokens_used' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN tokens_used int(10) unsigned DEFAULT NULL',
					'check' => 'tokens_used'
				),
				'provider' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN provider varchar(50) DEFAULT NULL',
					'check' => 'provider'
				),
				'model' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN model varchar(100) DEFAULT NULL',
					'check' => 'model'
				)
			);

			foreach ($columns_to_add as $column => $config) {
				// Check if column exists
				$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '{$config['check']}'");
				if (empty($column_exists)) {
					$result = $wpdb->query($config['sql']);
					if ($result === false) {
						error_log("AI Chatbot: Failed to add column $column to $table_name");
					}
				}
			}

			// Update database version
			update_option('ai_chatbot_db_version', '1.1.0');
		}
	}

	/**
	 * Schedule cleanup events
	 *
	 * @since 1.0.0
	 */
	private static function schedule_cleanup_events() {
		// Schedule daily cleanup of old conversations
		if ( ! wp_next_scheduled( 'ai_chatbot_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'ai_chatbot_daily_cleanup' );
		}

		// Schedule weekly training content sync
		if ( ! wp_next_scheduled( 'ai_chatbot_weekly_sync' ) ) {
			wp_schedule_event( time(), 'weekly', 'ai_chatbot_weekly_sync' );
		}
	}
}