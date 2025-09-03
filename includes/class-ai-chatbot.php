<?php
/**
 * Main plugin class
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
 * Main AI_Chatbot class
 *
 * @since 1.0.0
 */
final class AI_Chatbot {

	/**
	 * The single instance of the class
	 *
	 * @var AI_Chatbot
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Plugin loader instance
	 *
	 * @var AI_Chatbot_Loader
	 * @since 1.0.0
	 */
	public $loader;

	/**
	 * Settings instance
	 *
	 * @var AI_Chatbot_Settings
	 * @since 1.0.0
	 */
	public $settings;

	/**
	 * Database instance
	 *
	 * @var AI_Chatbot_Database
	 * @since 1.0.0
	 */
	public $database;

	/**
	 * Frontend instance
	 *
	 * @var AI_Chatbot_Frontend
	 * @since 1.0.0
	 */
	public $frontend;

	/**
	 * Admin instance
	 *
	 * @var AI_Chatbot_Admin
	 * @since 1.0.0
	 */
	public $admin;

	/**
	 * Main AI_Chatbot Instance
	 *
	 * Ensures only one instance of AI_Chatbot is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @return AI_Chatbot - Main instance
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * AI_Chatbot Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define additional constants
	 *
	 * @since 1.0.0
	 */
	private function define_constants() {
		$this->define( 'AI_CHATBOT_ABSPATH', dirname( AI_CHATBOT_PLUGIN_FILE ) . '/' );
		$this->define( 'AI_CHATBOT_INCLUDES_PATH', AI_CHATBOT_PLUGIN_DIR . 'includes/' );
		$this->define( 'AI_CHATBOT_ADMIN_PATH', AI_CHATBOT_PLUGIN_DIR . 'admin/' );
		$this->define( 'AI_CHATBOT_PUBLIC_PATH', AI_CHATBOT_PLUGIN_DIR . 'public/' );
		$this->define( 'AI_CHATBOT_ASSETS_URL', AI_CHATBOT_PLUGIN_URL . 'assets/' );
	}

	/**
	 * Define constant if not already set
	 *
	 * @param string $name Constant name.
	 * @param string $value Constant value.
	 * @since 1.0.0
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Include required core files
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		// Core includes
		include_once AI_CHATBOT_INCLUDES_PATH . 'class-ai-chatbot-loader.php';
		include_once AI_CHATBOT_INCLUDES_PATH . 'class-ai-chatbot-i18n.php';
		include_once AI_CHATBOT_INCLUDES_PATH . 'class-ai-chatbot-database.php';
		include_once AI_CHATBOT_INCLUDES_PATH . 'class-ai-chatbot-settings.php';
		include_once AI_CHATBOT_INCLUDES_PATH . 'class-ai-chatbot-privacy.php';
		include_once AI_CHATBOT_INCLUDES_PATH . 'class-ai-chatbot-security.php';

		// AI Provider classes
		include_once AI_CHATBOT_INCLUDES_PATH . 'ai-providers/class-ai-chatbot-provider-interface.php';
		include_once AI_CHATBOT_INCLUDES_PATH . 'ai-providers/class-ai-chatbot-openai.php';
		include_once AI_CHATBOT_INCLUDES_PATH . 'ai-providers/class-ai-chatbot-claude.php';
		include_once AI_CHATBOT_INCLUDES_PATH . 'ai-providers/class-ai-chatbot-gemini.php';

		// Admin includes
		if ( is_admin() ) {
			include_once AI_CHATBOT_ADMIN_PATH . 'class-ai-chatbot-admin.php';
		}

		// Public includes
		if ( ! is_admin() || wp_doing_ajax() ) {
			include_once AI_CHATBOT_PUBLIC_PATH . 'class-ai-chatbot-frontend.php';
			include_once AI_CHATBOT_PUBLIC_PATH . 'class-ai-chatbot-ajax.php';
		}
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		$this->loader = new AI_Chatbot_Loader();
		
		// Initialize internationalization
		$plugin_i18n = new AI_Chatbot_I18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

		// Initialize database
		$this->database = new AI_Chatbot_Database();
		
		// Initialize settings
		$this->settings = new AI_Chatbot_Settings();

		// Initialize admin functionality
		if ( is_admin() ) {
			$this->admin = new AI_Chatbot_Admin( $this->get_plugin_name(), $this->get_version() );
			$this->define_admin_hooks();
		}

		// Initialize public functionality
		if ( ! is_admin() || wp_doing_ajax() ) {
			$this->frontend = new AI_Chatbot_Frontend( $this->get_plugin_name(), $this->get_version() );
			$this->define_public_hooks();
		}

		// Initialize privacy functionality
		$privacy = new AI_Chatbot_Privacy();
		$this->define_privacy_hooks( $privacy );

		// Run the loader
		$this->loader->run();
	}

	/**
	 * Define admin hooks
	 *
	 * @since 1.0.0
	 */
	private function define_admin_hooks() {
		// Enqueue scripts and styles
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );

		// Admin menu
		$this->loader->add_action( 'admin_menu', $this->admin, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );

		// Plugin action links
		$this->loader->add_filter( 'plugin_action_links_' . AI_CHATBOT_PLUGIN_BASENAME, $this->admin, 'add_action_links' );

		// AJAX actions for admin
		$this->loader->add_action( 'wp_ajax_ai_chatbot_sync_content', $this->admin, 'sync_content' );
		$this->loader->add_action( 'wp_ajax_ai_chatbot_test_connection', $this->admin, 'test_ai_connection' );
	}

	/**
	 * Define public hooks
	 *
	 * @since 1.0.0
	 */
	private function define_public_hooks() {
		// Enqueue scripts and styles
		$this->loader->add_action( 'wp_enqueue_scripts', $this->frontend, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->frontend, 'enqueue_scripts' );

		// Render chatbot
		$this->loader->add_action( 'wp_footer', $this->frontend, 'render_chatbot' );

		// AJAX handler
		$ajax_handler = new AI_Chatbot_Ajax();
		$this->loader->add_action( 'wp_ajax_ai_chatbot_send_message', $ajax_handler, 'handle_message' );
		$this->loader->add_action( 'wp_ajax_nopriv_ai_chatbot_send_message', $ajax_handler, 'handle_message' );
	}

	/**
	 * Define privacy hooks
	 *
	 * @param AI_Chatbot_Privacy $privacy Privacy instance.
	 * @since 1.0.0
	 */
	private function define_privacy_hooks( $privacy ) {
		$this->loader->add_action( 'wp_privacy_personal_data_exporters', $privacy, 'register_exporter' );
		$this->loader->add_action( 'wp_privacy_personal_data_erasers', $privacy, 'register_eraser' );
	}

	/**
	 * Get plugin name
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_plugin_name() {
		return 'ai-website-chatbot';
	}

	/**
	 * Get plugin version
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_version() {
		return AI_CHATBOT_VERSION;
	}

	/**
	 * Prevent cloning
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'ai-website-chatbot' ), '1.0.0' );
	}

	/**
	 * Prevent unserializing
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances is forbidden.', 'ai-website-chatbot' ), '1.0.0' );
	}
}