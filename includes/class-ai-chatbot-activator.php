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

		// migration for pro upgrade
		self::migrate_to_pro_schema();

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

		// FIXED: Conversations table with correct schema
		$conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
        $sql_conversations = "CREATE TABLE $conversations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            conversation_id varchar(255) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
            user_message longtext NOT NULL,
            ai_response longtext DEFAULT NULL,
            user_name varchar(255) DEFAULT '',
            user_email varchar(255) DEFAULT '',
            user_ip varchar(100) DEFAULT '',
            user_agent varchar(500) DEFAULT '',
            page_url varchar(255) DEFAULT '',
            source varchar(100) DEFAULT 'chatbot',
			error_message varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT 'completed',
            intent varchar(255) DEFAULT NULL,
            rating tinyint(1) DEFAULT NULL,
            response_time decimal(8,3) DEFAULT NULL,
            tokens_used int(10) unsigned DEFAULT NULL,
            provider varchar(50) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
			message_rating int(1) DEFAULT NULL,
            message_rated_at datetime DEFAULT NULL,
			feedback text,
			rated_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_session_id (session_id),
            KEY idx_conversation_id (conversation_id),
            KEY idx_created_at (created_at),
            KEY idx_rating (rating),
            KEY idx_status (status),
            KEY idx_intent (intent),
            KEY idx_provider (provider),
            KEY idx_source (source)
        ) $charset_collate;";

		// Website content index table
		$content_table = $wpdb->prefix . 'ai_chatbot_content';
		$sql_content = "CREATE TABLE $content_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned DEFAULT NULL,
			content_type varchar(50) NOT NULL,
			title text,
			content longtext,
			url varchar(2048) DEFAULT '',
			content_hash varchar(64) DEFAULT '',
			embedding_status varchar(20) DEFAULT 'pending',
			content_embedding longtext,
			last_trained datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_post_id (post_id),
			KEY idx_content_type (content_type),
			KEY idx_embedding_status (embedding_status),
			KEY idx_content_hash (content_hash),
			KEY idx_last_trained (last_trained)
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
			KEY idx_status (status),
			KEY idx_created_at (created_at)
		) $charset_collate;";

		// Training data table
		$training_data_table = $wpdb->prefix . 'ai_chatbot_training_data';
		$sql_training_data = "CREATE TABLE $training_data_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			question longtext NOT NULL,
			answer longtext NOT NULL,
			intent varchar(255) DEFAULT '',
			tags text DEFAULT '',
			status varchar(20) DEFAULT 'active',
			source varchar(20) DEFAULT 'manual',
			source_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_status (status),
			KEY idx_intent (intent),
			KEY idx_source_id (source_id),
			KEY idx_user_id (user_id),
			KEY idx_created_at (created_at)
		) $charset_collate;";

		$users_table = $wpdb->prefix . 'ai_chatbot_users';
		$sql_users = "CREATE TABLE $users_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			email varchar(255) NOT NULL,
			session_id varchar(255) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			total_conversations int(11) DEFAULT 0,
			total_messages int(11) DEFAULT 0,
			first_seen datetime DEFAULT NULL,
			last_seen datetime DEFAULT NULL,
			status varchar(20) DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_email (email),
			KEY idx_email (email),
			KEY idx_status (status),
			KEY idx_session_id (session_id),
			KEY idx_total_messages (total_messages)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql_conversations);
		dbDelta($sql_content);
		dbDelta($sql_training);
		dbDelta($sql_training_data);
		dbDelta($sql_users);

		
		// Store database version for future upgrades
		update_option('ai_chatbot_db_version', '1.3.0');
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
		
		// Update to version 1.2.0 (includes conversation_id fix)
		if (version_compare($current_db_version, '1.2.0', '<')) {
			$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
			
			// Check if table exists first
			if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
				// Table doesn't exist, create it with correct schema
				self::create_database_tables();
				return;
			}

			// Add missing columns one by one
			$columns_to_add = array(
				'conversation_id' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN conversation_id varchar(255) NOT NULL AFTER session_id',
					'check' => 'conversation_id'
				),
				'message' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN message text NOT NULL',
					'check' => 'message'
				),
				'sender' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN sender varchar(20) NOT NULL DEFAULT "user"',
					'check' => 'sender'
				),
				'timestamp' => array(
					'sql' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN timestamp datetime DEFAULT CURRENT_TIMESTAMP',
					'check' => 'timestamp'
				),
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
			);

			foreach ($columns_to_add as $column => $config) {
				// Check if column exists
				$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '{$config['check']}'");
				if (empty($column_exists)) {
					$result = $wpdb->query($config['sql']);
					if ($result === false) {
						error_log("AI Chatbot: Failed to add column $column to $table_name");
					} else {
						error_log("AI Chatbot: Successfully added column $column to $table_name");
					}
				}
			}

			

			// Add indexes for better performance
			$indexes_to_add = array(
				'idx_conversation_id' => 'ALTER TABLE ' . $table_name . ' ADD INDEX idx_conversation_id (conversation_id)',
				'idx_timestamp' => 'ALTER TABLE ' . $table_name . ' ADD INDEX idx_timestamp (timestamp)',
				'idx_sender' => 'ALTER TABLE ' . $table_name . ' ADD INDEX idx_sender (sender)',
				'idx_user_id' => 'ALTER TABLE ' . $table_name . ' ADD INDEX idx_user_id (user_id)'
			);

			foreach ($indexes_to_add as $index_name => $sql) {
				// Check if index exists
				$index_exists = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = '$index_name'");
				if (empty($index_exists)) {
					$wpdb->query($sql);
				}
			}

			// Update database version
			update_option('ai_chatbot_db_version', '1.2.0');
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

	/**
	 * Database schema migration for Pro features
	 * Call this during plugin activation/update
	 */
	public static function migrate_to_pro_schema() {
		global $wpdb;
		
		$current_db_version = get_option('ai_chatbot_db_version', '1.0.0');
		$target_version = '2.0.0'; // Pro version with embeddings
		
		// Only run if we need to upgrade
		if (version_compare($current_db_version, $target_version, '>=')) {
			return;
		}
		
		// Run migrations step by step
		self::migrate_conversations_table();
		self::migrate_content_table();
		self::migrate_training_data_table();
		self::create_pro_tables();
		self::add_pro_options();
		
		// Update database version
		update_option('ai_chatbot_db_version', $target_version);
		
		// Log successful migration
		error_log('AI Chatbot Pro: Database migrated to version ' . $target_version);
	}

	/**
	 * Migrate conversations table for Pro features
	 */
	private static function migrate_conversations_table() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		
		// Check if table exists
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			// Table doesn't exist, create it with full Pro schema
			self::create_conversations_table_pro();
			return;
		}
		
		// Add embedding columns if they don't exist
		$columns_to_add = array(
			'message_embedding' => "ADD COLUMN `message_embedding` LONGTEXT DEFAULT NULL",
			'response_embedding' => "ADD COLUMN `response_embedding` LONGTEXT DEFAULT NULL", 
			'embedding_status' => "ADD COLUMN `embedding_status` VARCHAR(20) DEFAULT 'pending'",
			'intent' => "ADD COLUMN `intent` VARCHAR(255) DEFAULT NULL",
			'confidence_score' => "ADD COLUMN `confidence_score` DECIMAL(5,4) DEFAULT NULL",
			'satisfaction_rating' => "ADD COLUMN `satisfaction_rating` TINYINT(1) DEFAULT NULL",
			'response_time_ms' => "ADD COLUMN `response_time_ms` INT(10) UNSIGNED DEFAULT NULL",
			'tokens_used' => "ADD COLUMN `tokens_used` INT(10) UNSIGNED DEFAULT NULL",
			'cost' => "ADD COLUMN `cost` DECIMAL(10,6) DEFAULT NULL",
			'provider' => "ADD COLUMN `provider` VARCHAR(50) DEFAULT NULL",
			'model' => "ADD COLUMN `model` VARCHAR(100) DEFAULT NULL"
		);
		
		foreach ($columns_to_add as $column => $sql) {
			if (!self::column_exists($table_name, $column)) {
				$wpdb->query("ALTER TABLE $table_name $sql");
			}
		}
		
		// Add indexes if they don't exist
		$indexes_to_add = array(
			'idx_intent' => "ADD INDEX `idx_intent` (`intent`)",
			'idx_embedding_status' => "ADD INDEX `idx_embedding_status` (`embedding_status`)",
			'idx_provider' => "ADD INDEX `idx_provider` (`provider`)"
		);
		
		foreach ($indexes_to_add as $index => $sql) {
			if (!self::index_exists($table_name, $index)) {
				$wpdb->query("ALTER TABLE $table_name $sql");
			}
		}
	}

	/**
	 * Migrate content table for Pro features
	 */
	private static function migrate_content_table() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_chatbot_content';
		
		// Check if table exists
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			// Table doesn't exist, create it with full Pro schema
			self::create_content_table_pro();
			return;
		}
		
		// Add embedding columns if they don't exist
		$columns_to_add = array(
			'embedding_vector' => "ADD COLUMN `embedding_vector` LONGTEXT DEFAULT NULL",
			'embedding_status' => "ADD COLUMN `embedding_status` VARCHAR(20) DEFAULT 'pending'",
			'embedding_model' => "ADD COLUMN `embedding_model` VARCHAR(100) DEFAULT 'text-embedding-ada-002'",
			'embedding_generated_at' => "ADD COLUMN `embedding_generated_at` DATETIME DEFAULT NULL",
			'embedding_tokens' => "ADD COLUMN `embedding_tokens` INT(10) UNSIGNED DEFAULT NULL",
			'embedding_cost' => "ADD COLUMN `embedding_cost` DECIMAL(8,6) DEFAULT NULL",
			'search_count' => "ADD COLUMN `search_count` INT(10) UNSIGNED DEFAULT 0",
			'avg_similarity' => "ADD COLUMN `avg_similarity` DECIMAL(5,4) DEFAULT NULL"
		);
		
		foreach ($columns_to_add as $column => $sql) {
			if (!self::column_exists($table_name, $column)) {
				$wpdb->query("ALTER TABLE $table_name $sql");
			}
		}
		
		// Add indexes
		$indexes_to_add = array(
			'idx_embedding_status' => "ADD INDEX `idx_embedding_status` (`embedding_status`)",
			'idx_embedding_model' => "ADD INDEX `idx_embedding_model` (`embedding_model`)",
			'idx_search_count' => "ADD INDEX `idx_search_count` (`search_count`)"
		);
		
		foreach ($indexes_to_add as $index => $sql) {
			if (!self::index_exists($table_name, $index)) {
				$wpdb->query("ALTER TABLE $table_name $sql");
			}
		}
	}

	/**
	 * Migrate training data table for Pro features  
	 */
	private static function migrate_training_data_table() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_chatbot_training_data';
		
		// Check if table exists
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			// Table doesn't exist, create it with full Pro schema
			self::create_training_data_table_pro();
			return;
		}
		
		// Add embedding columns if they don't exist
		$columns_to_add = array(
			'question_embedding' => "ADD COLUMN `question_embedding` LONGTEXT DEFAULT NULL",
			'answer_embedding' => "ADD COLUMN `answer_embedding` LONGTEXT DEFAULT NULL",
			'embedding_status' => "ADD COLUMN `embedding_status` VARCHAR(20) DEFAULT 'pending'",
			'embedding_model' => "ADD COLUMN `embedding_model` VARCHAR(100) DEFAULT 'text-embedding-ada-002'",
			'embedding_generated_at' => "ADD COLUMN `embedding_generated_at` DATETIME DEFAULT NULL",
			'usage_count' => "ADD COLUMN `usage_count` INT(10) UNSIGNED DEFAULT 0",
			'success_rate' => "ADD COLUMN `success_rate` DECIMAL(5,4) DEFAULT NULL",
			'last_used' => "ADD COLUMN `last_used` DATETIME DEFAULT NULL"
		);
		
		foreach ($columns_to_add as $column => $sql) {
			if (!self::column_exists($table_name, $column)) {
				$wpdb->query("ALTER TABLE $table_name $sql");
			}
		}
		
		// Add indexes
		$indexes_to_add = array(
			'idx_embedding_status' => "ADD INDEX `idx_embedding_status` (`embedding_status`)",
			'idx_last_used' => "ADD INDEX `idx_last_used` (`last_used`)",
			'idx_usage_count' => "ADD INDEX `idx_usage_count` (`usage_count`)"
		);
		
		foreach ($indexes_to_add as $index => $sql) {
			if (!self::index_exists($table_name, $index)) {
				$wpdb->query("ALTER TABLE $table_name $sql");
			}
		}
	}

	/**
	 * Create new Pro-specific tables
	 */
	private static function create_pro_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		// 1. Embedding Jobs Table
		$embedding_jobs_table = $wpdb->prefix . 'ai_chatbot_embedding_jobs';
		if ($wpdb->get_var("SHOW TABLES LIKE '$embedding_jobs_table'") != $embedding_jobs_table) {
			$sql_embedding_jobs = "CREATE TABLE $embedding_jobs_table (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_type varchar(50) NOT NULL,
				status varchar(20) DEFAULT 'pending',
				total_items int(10) unsigned DEFAULT 0,
				processed_items int(10) unsigned DEFAULT 0,
				failed_items int(10) unsigned DEFAULT 0,
				batch_size int(10) unsigned DEFAULT 10,
				provider varchar(50) DEFAULT 'openai',
				model varchar(100) DEFAULT 'text-embedding-ada-002',
				error_message text DEFAULT NULL,
				started_at datetime DEFAULT NULL,
				completed_at datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_status (status),
				KEY idx_job_type (job_type),
				KEY idx_created_at (created_at)
			) $charset_collate;";
			
			dbDelta($sql_embedding_jobs);
		}
		
		// 2. Embedding Cache Table
		$embedding_cache_table = $wpdb->prefix . 'ai_chatbot_embedding_cache';
		if ($wpdb->get_var("SHOW TABLES LIKE '$embedding_cache_table'") != $embedding_cache_table) {
			$sql_embedding_cache = "CREATE TABLE $embedding_cache_table (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				text_hash varchar(64) NOT NULL,
				text_preview varchar(255) DEFAULT NULL,
				embedding_vector longtext NOT NULL,
				provider varchar(50) DEFAULT 'openai',
				model varchar(100) DEFAULT 'text-embedding-ada-002',
				usage_count int(10) unsigned DEFAULT 1,
				last_used datetime DEFAULT CURRENT_TIMESTAMP,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY unique_text_hash (text_hash, provider, model),
				KEY idx_last_used (last_used),
				KEY idx_usage_count (usage_count)
			) $charset_collate;";
			
			dbDelta($sql_embedding_cache);
		}
		
		// 3. Semantic Analytics Table
		$semantic_analytics_table = $wpdb->prefix . 'ai_chatbot_semantic_analytics';
		if ($wpdb->get_var("SHOW TABLES LIKE '$semantic_analytics_table'") != $semantic_analytics_table) {
			$sql_semantic_analytics = "CREATE TABLE $semantic_analytics_table (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				query text NOT NULL,
				query_embedding_hash varchar(64) DEFAULT NULL,
				results_found int(10) unsigned DEFAULT 0,
				avg_similarity decimal(5,4) DEFAULT NULL,
				top_similarity decimal(5,4) DEFAULT NULL,
				search_type varchar(20) DEFAULT 'content',
				response_quality enum('excellent','good','fair','poor') DEFAULT NULL,
				user_feedback tinyint(1) DEFAULT NULL,
				processing_time_ms int(10) unsigned DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_search_type (search_type),
				KEY idx_created_at (created_at),
				KEY idx_response_quality (response_quality),
				KEY idx_query_hash (query_embedding_hash)
			) $charset_collate;";
			
			dbDelta($sql_semantic_analytics);
		}
		
		// 4. Conversation Insights Table
		$conversation_insights_table = $wpdb->prefix . 'ai_chatbot_conversation_insights';
		if ($wpdb->get_var("SHOW TABLES LIKE '$conversation_insights_table'") != $conversation_insights_table) {
			$sql_conversation_insights = "CREATE TABLE $conversation_insights_table (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				conversation_id bigint(20) unsigned NOT NULL,
				primary_intent varchar(100) DEFAULT NULL,
				intent_confidence decimal(5,4) DEFAULT NULL,
				satisfaction_score decimal(3,2) DEFAULT NULL,
				journey_stage varchar(50) DEFAULT NULL,
				resolution_status enum('resolved','partial','unresolved','escalated') DEFAULT NULL,
				engagement_level enum('high','medium','low') DEFAULT NULL,
				lead_score tinyint(3) unsigned DEFAULT NULL,
				insights_data longtext DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_conversation_id (conversation_id),
				KEY idx_primary_intent (primary_intent),
				KEY idx_journey_stage (journey_stage),
				KEY idx_resolution_status (resolution_status),
				KEY idx_lead_score (lead_score),
				KEY idx_created_at (created_at)
			) $charset_collate;";
			
			dbDelta($sql_conversation_insights);
		}
		
		// 5. Context Cache Table
		$context_cache_table = $wpdb->prefix . 'ai_chatbot_context_cache';
		if ($wpdb->get_var("SHOW TABLES LIKE '$context_cache_table'") != $context_cache_table) {
			$sql_context_cache = "CREATE TABLE $context_cache_table (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				context_key varchar(255) NOT NULL,
				context_type varchar(50) NOT NULL,
				context_data longtext NOT NULL,
				expires_at datetime NOT NULL,
				usage_count int(10) unsigned DEFAULT 1,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY unique_context_key (context_key),
				KEY idx_context_type (context_type),
				KEY idx_expires_at (expires_at),
				KEY idx_usage_count (usage_count)
			) $charset_collate;";
			
			dbDelta($sql_context_cache);
		}
	}

	/**
	 * Add Pro-specific options
	 */
	private static function add_pro_options() {
		$pro_options = array(
			// Embedding Configuration
			'ai_chatbot_embedding_provider' => 'openai',
			'ai_chatbot_embedding_model' => 'text-embedding-ada-002',
			'ai_chatbot_embedding_similarity_threshold' => '0.75',
			'ai_chatbot_embedding_batch_size' => '10',
			'ai_chatbot_embedding_cache_duration' => '86400', // 24 hours
			'ai_chatbot_embedding_max_tokens' => '8192',
			
			// Semantic Search Configuration
			'ai_chatbot_semantic_search_enabled' => '1',
			'ai_chatbot_semantic_training_enabled' => '1',
			'ai_chatbot_semantic_intent_enabled' => '1',
			'ai_chatbot_semantic_context_enabled' => '1',
			
			// Intelligence Engine Configuration
			'ai_chatbot_intent_recognition_enabled' => '1',
			'ai_chatbot_intent_sensitivity' => 'medium',
			'ai_chatbot_context_builder_enabled' => '1',
			'ai_chatbot_response_reasoning_enabled' => '1',
			'ai_chatbot_conversation_memory_enabled' => '1',
			
			// Analytics Configuration
			'ai_chatbot_advanced_analytics_enabled' => '1',
			'ai_chatbot_conversation_insights_enabled' => '1',
			'ai_chatbot_lead_scoring_enabled' => '1',
			'ai_chatbot_journey_tracking_enabled' => '1',
			
			// Business Context Configuration
			'ai_chatbot_business_hours' => '',
			'ai_chatbot_contact_phone' => '',
			'ai_chatbot_contact_email' => '',
			'ai_chatbot_location_info' => '',
			'ai_chatbot_industry_keywords' => '',
			'ai_chatbot_current_promotions' => '',
			
			// Pro Feature Toggles
			'ai_chatbot_pro_enabled_features' => json_encode(array(
				'intelligence_engine',
				'context_builder', 
				'intent_recognition',
				'response_reasoning',
				'advanced_analytics',
				'conversation_insights'
			)),
			
			// Performance Settings
			'ai_chatbot_cache_embeddings' => '1',
			'ai_chatbot_async_embedding_generation' => '1',
			'ai_chatbot_embedding_cleanup_days' => '30',
			
			// Pro Version Info
			'ai_chatbot_pro_version' => '1.0.0',
			'ai_chatbot_pro_activated_at' => current_time('mysql'),
			'ai_chatbot_features_cache_duration' => '3600' // 1 hour
		);
		
		foreach ($pro_options as $option_name => $default_value) {
			add_option($option_name, $default_value);
		}
	}

	/**
	 * Check if column exists in table
	 */
	private static function column_exists($table_name, $column_name) {
		global $wpdb;
		
		$column = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s 
			AND TABLE_NAME = %s 
			AND COLUMN_NAME = %s",
			DB_NAME,
			$table_name,
			$column_name
		));
		
		return !empty($column);
	}

	/**
	 * Check if index exists in table
	 */
	private static function index_exists($table_name, $index_name) {
		global $wpdb;
		
		$index = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM INFORMATION_SCHEMA.STATISTICS 
			WHERE TABLE_SCHEMA = %s 
			AND TABLE_NAME = %s 
			AND INDEX_NAME = %s",
			DB_NAME,
			$table_name,
			$index_name
		));
		
		return !empty($index);
	}

	/**
	 * Create conversations table with full Pro schema
	 */
	private static function create_conversations_table_pro() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(255) NOT NULL,
			conversation_id varchar(255) DEFAULT NULL,
			message longtext NOT NULL,
			response longtext NOT NULL,
			message_embedding longtext DEFAULT NULL,
			response_embedding longtext DEFAULT NULL,
			embedding_status varchar(20) DEFAULT 'pending',
			intent varchar(255) DEFAULT NULL,
			confidence_score decimal(5,4) DEFAULT NULL,
			satisfaction_rating tinyint(1) DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			response_time_ms int(10) unsigned DEFAULT NULL,
			tokens_used int(10) unsigned DEFAULT NULL,
			cost decimal(10,6) DEFAULT NULL,
			provider varchar(50) DEFAULT NULL,
			model varchar(100) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_session_id (session_id),
			KEY idx_conversation_id (conversation_id),
			KEY idx_user_id (user_id),
			KEY idx_intent (intent),
			KEY idx_embedding_status (embedding_status),
			KEY idx_provider (provider),
			KEY idx_created_at (created_at)
		) $charset_collate;";
		
		dbDelta($sql);
	}

	/**
	 * Create content table with full Pro schema
	 */
	private static function create_content_table_pro() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		$table_name = $wpdb->prefix . 'ai_chatbot_content';
		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned DEFAULT NULL,
			content_type varchar(50) NOT NULL,
			title text,
			content longtext,
			url varchar(2048) DEFAULT '',
			content_hash varchar(64) DEFAULT '',
			content_embedding longtext DEFAULT NULL,
			embedding_status varchar(20) DEFAULT 'pending',
			embedding_model varchar(100) DEFAULT 'text-embedding-ada-002',
			embedding_generated_at datetime DEFAULT NULL,
			embedding_tokens int(10) unsigned DEFAULT NULL,
			embedding_cost decimal(8,6) DEFAULT NULL,
			last_trained datetime DEFAULT NULL,
			search_count int(10) unsigned DEFAULT 0,
			avg_similarity decimal(5,4) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_post_id (post_id),
			KEY idx_content_type (content_type),
			KEY idx_embedding_status (embedding_status),
			KEY idx_content_hash (content_hash),
			KEY idx_last_trained (last_trained),
			KEY idx_search_count (search_count),
			KEY idx_updated_at (updated_at)
		) $charset_collate;";
		
		dbDelta($sql);
	}

	/**
	 * Create training data table with full Pro schema
	 */
	private static function create_training_data_table_pro() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		$table_name = $wpdb->prefix . 'ai_chatbot_training_data';
		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			question longtext NOT NULL,
			answer longtext NOT NULL,
			intent varchar(255) DEFAULT '',
			tags text DEFAULT '',
			status varchar(20) DEFAULT 'active',
			source varchar(20) DEFAULT 'manual',
			source_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			question_embedding longtext DEFAULT NULL,
			answer_embedding longtext DEFAULT NULL,
			embedding_status varchar(20) DEFAULT 'pending',
			embedding_model varchar(100) DEFAULT 'text-embedding-ada-002',
			embedding_generated_at datetime DEFAULT NULL,
			usage_count int(10) unsigned DEFAULT 0,
			success_rate decimal(5,4) DEFAULT NULL,
			last_used datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_status (status),
			KEY idx_intent (intent),
			KEY idx_source_id (source_id),
			KEY idx_user_id (user_id),
			KEY idx_embedding_status (embedding_status),
			KEY idx_last_used (last_used),
			KEY idx_usage_count (usage_count),
			KEY idx_created_at (created_at)
		) $charset_collate;";
		
		dbDelta($sql);
	}

	/**
	 * Clean up old embedding data (optional)
	 */
	public static function cleanup_embedding_data() {
		global $wpdb;
		
		$cleanup_days = get_option('ai_chatbot_embedding_cleanup_days', 30);
		
		// Clean old cache entries
		$cache_table = $wpdb->prefix . 'ai_chatbot_embedding_cache';
		$wpdb->query($wpdb->prepare(
			"DELETE FROM $cache_table 
			WHERE last_used < DATE_SUB(NOW(), INTERVAL %d DAY)
			AND usage_count < 5",
			$cleanup_days
		));
		
		// Clean old analytics data
		$analytics_table = $wpdb->prefix . 'ai_chatbot_semantic_analytics';
		$wpdb->query($wpdb->prepare(
			"DELETE FROM $analytics_table 
			WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$cleanup_days * 2 // Keep analytics longer
		));
	}
}