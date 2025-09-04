<?php
/**
 * Admin functionality for AI Chatbot
 *
 * @package AI_Website_Chatbot
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Ajax {

	/**
	 * Plugin name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $plugin_name;

	/**
	 * Plugin version
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $version;

	/**
	 * Settings instance
	 *
	 * @var AI_Chatbot_Settings
	 * @since 1.0.0
	 */
	private $settings;

	/**
	 * Constructor
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 * @since 1.0.0
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->settings = new AI_Chatbot_Settings();
	}

	/**
	 * Enqueue admin styles
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @since 1.0.0
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( ! $this->is_plugin_page( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name . '-admin',
			AI_CHATBOT_ASSETS_URL . 'css/admin/admin-main.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue additional styles based on page
		if ( strpos( $hook_suffix, 'ai-chatbot-analytics' ) !== false ) {
			wp_enqueue_style(
				$this->plugin_name . '-charts',
				AI_CHATBOT_ASSETS_URL . 'css/admin/charts.css',
				array(),
				$this->version
			);
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @since 1.0.0
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( ! $this->is_plugin_page( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name . '-admin',
			AI_CHATBOT_ASSETS_URL . 'js/admin/admin-main.js',
			array( 'jquery', 'wp-color-picker' ),
			$this->version,
			true
		);

		// Enqueue color picker CSS
		wp_enqueue_style( 'wp-color-picker' );

		// Localize script
		wp_localize_script(
			$this->plugin_name . '-admin',
			'aiChatbotAdmin',
			$this->get_admin_localization()
		);

		// Enqueue Chart.js for analytics
		if ( strpos( $hook_suffix, 'ai-chatbot-analytics' ) !== false ) {
			wp_enqueue_script(
				'chart-js',
				'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
				array(),
				'3.9.1',
				true
			);
		}
	}

	/**
	 * Add plugin admin menu
	 *
	 * @since 1.0.0
	 */
	public function add_plugin_admin_menu() {
		// Main menu page
		add_menu_page(
			__( 'AI Chatbot', 'ai-website-chatbot' ),
			__( 'AI Chatbot', 'ai-website-chatbot' ),
			'manage_options',
			'ai-chatbot-settings',
			array( $this, 'display_plugin_admin_page' ),
			'dashicons-format-chat',
			30
		);

		// Settings submenu (rename main menu item)
		add_submenu_page(
			'ai-chatbot-settings',
			__( 'Settings', 'ai-website-chatbot' ),
			__( 'Settings', 'ai-website-chatbot' ),
			'manage_options',
			'ai-chatbot-settings'
		);

		// Analytics submenu
		add_submenu_page(
			'ai-chatbot-settings',
			__( 'Analytics', 'ai-website-chatbot' ),
			__( 'Analytics', 'ai-website-chatbot' ),
			'manage_options',
			'ai-chatbot-analytics',
			array( $this, 'display_analytics_page' )
		);

		// Conversations submenu
		add_submenu_page(
			'ai-chatbot-settings',
			__( 'Conversations', 'ai-website-chatbot' ),
			__( 'Conversations', 'ai-website-chatbot' ),
			'manage_options',
			'ai-chatbot-conversations',
			array( $this, 'display_conversations_page' )
		);

		// Training submenu
		add_submenu_page(
			'ai-chatbot-settings',
			__( 'Training', 'ai-website-chatbot' ),
			__( 'Training', 'ai-website-chatbot' ),
			'manage_options',
			'ai-chatbot-training',
			array( $this, 'display_training_page' )
		);

		// Privacy submenu
		add_submenu_page(
			'ai-chatbot-settings',
			__( 'Privacy & Security', 'ai-website-chatbot' ),
			__( 'Privacy & Security', 'ai-website-chatbot' ),
			'manage_options',
			'ai-chatbot-privacy',
			array( $this, 'display_privacy_page' )
		);
	}

	/**
	 * Register plugin settings
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// Register setting groups
		$setting_groups = $this->settings->get_setting_groups();
		
		foreach ( $setting_groups as $group_id => $group_name ) {
			register_setting(
				'ai_chatbot_' . $group_id . '_settings',
				'ai_chatbot_' . $group_id . '_settings',
				array(
					'type' => 'array',
					'sanitize_callback' => array( $this, 'sanitize_settings' ),
				)
			);
		}

		// Add settings sections and fields
		$this->add_settings_sections();
	}

	/**
	 * Add settings sections and fields
	 *
	 * @since 1.0.0
	 */
	private function add_settings_sections() {
		// General Settings Section
		add_settings_section(
			'ai_chatbot_general',
			__( 'General Settings', 'ai-website-chatbot' ),
			array( $this, 'general_section_callback' ),
			'ai-chatbot-settings'
		);

		$this->add_settings_fields( 'general', 'ai-chatbot-settings' );

		// AI Provider Section
		add_settings_section(
			'ai_chatbot_ai_provider',
			__( 'AI Configuration', 'ai-website-chatbot' ),
			array( $this, 'ai_provider_section_callback' ),
			'ai-chatbot-settings'
		);

		$this->add_settings_fields( 'ai_provider', 'ai-chatbot-settings' );

		// Display Section
		add_settings_section(
			'ai_chatbot_display',
			__( 'Display Settings', 'ai-website-chatbot' ),
			array( $this, 'display_section_callback' ),
			'ai-chatbot-settings'
		);

		$this->add_settings_fields( 'display', 'ai-chatbot-settings' );
	}

	/**
	 * Add settings fields for a section
	 *
	 * @param string $section Section name.
	 * @param string $page Page slug.
	 * @since 1.0.0
	 */
	private function add_settings_fields( $section, $page ) {
		$fields = $this->get_section_fields( $section );

		foreach ( $fields as $field_id => $field ) {
			add_settings_field(
				$field_id,
				$field['label'],
				array( $this, 'render_field' ),
				$page,
				'ai_chatbot_' . $section,
				array(
					'field_id' => $field_id,
					'field' => $field,
				)
			);
		}
	}

	/**
	 * Get fields for a section
	 *
	 * @param string $section Section name.
	 * @return array Section fields.
	 * @since 1.0.0
	 */
	private function get_section_fields( $section ) {
		switch ( $section ) {
			case 'general':
				return array(
					'ai_chatbot_enabled' => array(
						'label' => __( 'Enable Chatbot', 'ai-website-chatbot' ),
						'type' => 'checkbox',
						'description' => __( 'Enable the AI chatbot on your website.', 'ai-website-chatbot' ),
					),
					'ai_chatbot_welcome_message' => array(
						'label' => __( 'Welcome Message', 'ai-website-chatbot' ),
						'type' => 'textarea',
						'description' => __( 'Initial message shown to users.', 'ai-website-chatbot' ),
					),
					'ai_chatbot_widget_title' => array(
						'label' => __( 'Widget Title', 'ai-website-chatbot' ),
						'type' => 'text',
						'description' => __( 'Title displayed in the chatbot header.', 'ai-website-chatbot' ),
					),
				);

			case 'ai_provider':
				return array(
					'ai_chatbot_ai_provider' => array(
						'label' => __( 'AI Provider', 'ai-website-chatbot' ),
						'type' => 'select',
						'options' => array(
							'openai' => 'OpenAI (ChatGPT)',
							'claude' => 'Anthropic Claude',
							'gemini' => 'Google Gemini',
						),
						'description' => __( 'Select your AI service provider.', 'ai-website-chatbot' ),
					),
					'ai_chatbot_openai_api_key' => array(
						'label' => __( 'OpenAI API Key', 'ai-website-chatbot' ),
						'type' => 'password',
						'description' => __( 'Your OpenAI API key (required for OpenAI).', 'ai-website-chatbot' ),
						'conditional' => array( 'ai_chatbot_ai_provider', 'openai' ),
					),
					'ai_chatbot_claude_api_key' => array(
						'label' => __( 'Claude API Key', 'ai-website-chatbot' ),
						'type' => 'password',
						'description' => __( 'Your Anthropic Claude API key (required for Claude).', 'ai-website-chatbot' ),
						'conditional' => array( 'ai_chatbot_ai_provider', 'claude' ),
					),
					'ai_chatbot_gemini_api_key' => array(
						'label' => __( 'Gemini API Key', 'ai-website-chatbot' ),
						'type' => 'password',
						'description' => __( 'Your Google Gemini API key (required for Gemini).', 'ai-website-chatbot' ),
						'conditional' => array( 'ai_chatbot_ai_provider', 'gemini' ),
					),
				);

			case 'display':
				return array(
					'ai_chatbot_position' => array(
						'label' => __( 'Position', 'ai-website-chatbot' ),
						'type' => 'select',
						'options' => array(
							'bottom-right' => __( 'Bottom Right', 'ai-website-chatbot' ),
							'bottom-left' => __( 'Bottom Left', 'ai-website-chatbot' ),
							'top-right' => __( 'Top Right', 'ai-website-chatbot' ),
							'top-left' => __( 'Top Left', 'ai-website-chatbot' ),
						),
						'description' => __( 'Where to position the chatbot widget.', 'ai-website-chatbot' ),
					),
					'ai_chatbot_theme_color' => array(
						'label' => __( 'Theme Color', 'ai-website-chatbot' ),
						'type' => 'color',
						'description' => __( 'Primary color for the chatbot interface.', 'ai-website-chatbot' ),
					),
					'ai_chatbot_show_on_mobile' => array(
						'label' => __( 'Show on Mobile', 'ai-website-chatbot' ),
						'type' => 'checkbox',
						'description' => __( 'Display chatbot on mobile devices.', 'ai-website-chatbot' ),
					),
				);

			default:
				return array();
		}
	}

	/**
	 * Render a settings field
	 *
	 * @param array $args Field arguments.
	 * @since 1.0.0
	 */
	public function render_field( $args ) {
		$field_id = $args['field_id'];
		$field = $args['field'];
		$value = $this->settings->get( $field_id );

		$conditional_class = '';
		if ( isset( $field['conditional'] ) ) {
			$conditional_class = 'conditional-field';
		}

		echo '<div class="' . esc_attr( $conditional_class ) . '"';
		if ( isset( $field['conditional'] ) ) {
			echo ' data-condition="' . esc_attr( $field['conditional'][0] ) . '"';
			echo ' data-value="' . esc_attr( $field['conditional'][1] ) . '"';
		}
		echo '>';

		switch ( $field['type'] ) {
			case 'text':
				printf(
					'<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
					esc_attr( $field_id ),
					esc_attr( $field_id ),
					esc_attr( $value )
				);
				break;

			case 'password':
				$masked_value = ! empty( $value ) ? str_repeat( '*', 20 ) : '';
				printf(
					'<input type="password" id="%s" name="%s" value="%s" class="regular-text" />',
					esc_attr( $field_id ),
					esc_attr( $field_id ),
					esc_attr( $masked_value )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="4" class="large-text">%s</textarea>',
					esc_attr( $field_id ),
					esc_attr( $field_id ),
					esc_textarea( $value )
				);
				break;

			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $field_id ), esc_attr( $field_id ) );
				foreach ( $field['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $option_value ),
						selected( $value, $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<input type="checkbox" id="%s" name="%s" value="1"%s /> <label for="%s">%s</label>',
					esc_attr( $field_id ),
					esc_attr( $field_id ),
					checked( $value, true, false ),
					esc_attr( $field_id ),
					esc_html( $field['description'] ?? '' )
				);
				break;

			case 'color':
				printf(
					'<input type="text" id="%s" name="%s" value="%s" class="color-picker" />',
					esc_attr( $field_id ),
					esc_attr( $field_id ),
					esc_attr( $value )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" class="small-text" />',
					esc_attr( $field_id ),
					esc_attr( $field_id ),
					esc_attr( $value ),
					esc_attr( $field['min'] ?? '' ),
					esc_attr( $field['max'] ?? '' ),
					esc_attr( $field['step'] ?? '1' )
				);
				break;
		}

		if ( isset( $field['description'] ) && $field['type'] !== 'checkbox' ) {
			printf( '<p class="description">%s</p>', wp_kses_post( $field['description'] ) );
		}

		echo '</div>';
	}

	/**
	 * Display main admin page
	 *
	 * @since 1.0.0
	 */
	public function display_plugin_admin_page() {
		// Handle form submission
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'ai_chatbot_settings', 'ai_chatbot_nonce' ) ) {
			$this->handle_settings_update();
		}

		// Get current settings
		$current_settings = $this->settings->get_all();
		$ai_provider = $this->settings->get( 'ai_chatbot_ai_provider', 'openai' );

		include AI_CHATBOT_ADMIN_PATH . 'partials/admin-display.php';
	}

	/**
	 * Display analytics page
	 *
	 * @since 1.0.0
	 */
	public function display_analytics_page() {
		$database = new AI_Chatbot_Database();
		
		// Get analytics data
		$stats = array(
			'today' => $database->get_conversation_stats( 'day' ),
			'week' => $database->get_conversation_stats( 'week' ),
			'month' => $database->get_conversation_stats( 'month' ),
		);

		$recent_conversations = $database->get_conversations_by_session( '', 10 );
		
		include AI_CHATBOT_ADMIN_PATH . 'partials/admin-analytics-display.php';
	}

	/**
	 * Display conversations page
	 *
	 * @since 1.0.0
	 */
	public function display_conversations_page() {
		global $wpdb;

		// Handle bulk actions
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'delete_selected' && isset( $_POST['conversation_ids'] ) ) {
			if ( check_admin_referer( 'bulk_delete_conversations', 'bulk_delete_nonce' ) ) {
				$this->handle_bulk_delete_conversations();
			}
		}

		// Get conversations with pagination
		$per_page = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $current_page - 1 ) * $per_page;

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		$total_conversations = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		
		$conversations = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );

		$total_pages = ceil( $total_conversations / $per_page );

		include AI_CHATBOT_ADMIN_PATH . 'partials/admin-conversations-display.php';
	}

	/**
	 * Display training page
	 *
	 * @since 1.0.0
	 */
	public function display_training_page() {
		$database = new AI_Chatbot_Database();
		
		// Get training statistics
		$training_stats = array(
			'total_content' => $database->get_content_count(),
			'trained_content' => $database->get_content_count( 'completed' ),
			'pending_content' => $database->get_content_count( 'pending' ),
		);

		include AI_CHATBOT_ADMIN_PATH . 'partials/admin-training-display.php';
	}

	/**
	 * Display privacy page
	 *
	 * @since 1.0.0
	 */
	public function display_privacy_page() {
		$privacy = new AI_Chatbot_Privacy();
		$security = new AI_Chatbot_Security();

		$privacy_summary = $privacy->get_privacy_summary();
		$security_stats = $security->get_security_stats();

		include AI_CHATBOT_ADMIN_PATH . 'partials/admin-privacy-display.php';
	}

	/**
	 * Handle settings update
	 *
	 * @since 1.0.0
	 */
	private function handle_settings_update() {
		$updated_settings = array();

		// Get all posted settings
		foreach ( $_POST as $key => $value ) {
			if ( strpos( $key, 'ai_chatbot_' ) === 0 ) {
				$updated_settings[ $key ] = $value;
			}
		}

		// Validate settings
		$validated_settings = $this->settings->validate_settings( $updated_settings );

		if ( is_wp_error( $validated_settings ) ) {
			add_settings_error(
				'ai_chatbot_settings',
				'validation_failed',
				$validated_settings->get_error_message()
			);
			return;
		}

		// Update settings
		$updated_count = 0;
		foreach ( $validated_settings as $setting_name => $setting_value ) {
			if ( $this->settings->update( $setting_name, $setting_value ) ) {
				$updated_count++;
			}
		}

		if ( $updated_count > 0 ) {
			add_settings_error(
				'ai_chatbot_settings',
				'settings_updated',
				__( 'Settings saved successfully!', 'ai-website-chatbot' ),
				'updated'
			);
		}

		// Test AI connection if API key was updated
		if ( isset( $validated_settings['ai_chatbot_ai_provider'] ) ) {
			$this->test_ai_connection_after_save( $validated_settings );
		}
	}

	/**
	 * Test AI connection after settings save
	 *
	 * @param array $settings Updated settings.
	 * @since 1.0.0
	 */
	private function test_ai_connection_after_save( $settings ) {
		$provider_name = $settings['ai_chatbot_ai_provider'] ?? 'openai';
		
		try {
			$provider = $this->get_ai_provider( $provider_name );
			
			if ( ! is_wp_error( $provider ) ) {
				$test_result = $provider->test_connection();
				
				if ( is_wp_error( $test_result ) ) {
					add_settings_error(
						'ai_chatbot_settings',
						'connection_failed',
						sprintf( 
							/* translators: %s: error message */
							__( 'AI connection test failed: %s', 'ai-website-chatbot' ), 
							$test_result->get_error_message() 
						)
					);
				} else {
					add_settings_error(
						'ai_chatbot_settings',
						'connection_success',
						__( 'AI connection test successful!', 'ai-website-chatbot' ),
						'updated'
					);
				}
			}
		} catch ( Exception $e ) {
			add_settings_error(
				'ai_chatbot_settings',
				'connection_error',
				sprintf( 
					/* translators: %s: error message */
					__( 'Connection test error: %s', 'ai-website-chatbot' ), 
					$e->getMessage() 
				)
			);
		}
	}

	/**
	 * Get AI provider instance
	 *
	 * @param string $provider_name Provider name.
	 * @return AI_Chatbot_Provider_Interface|WP_Error Provider instance or error.
	 * @since 1.0.0
	 */
	private function get_ai_provider( $provider_name ) {
		switch ( $provider_name ) {
			case 'openai':
				return new AI_Chatbot_OpenAI();
			case 'claude':
				if ( class_exists( 'AI_Chatbot_Claude' ) ) {
					return new AI_Chatbot_Claude();
				}
				break;
			case 'gemini':
				if ( class_exists( 'AI_Chatbot_Gemini' ) ) {
					return new AI_Chatbot_Gemini();
				}
				break;
		}
		
		return new WP_Error( 'invalid_provider', __( 'Invalid AI provider.', 'ai-website-chatbot' ) );
	}

	/**
	 * Handle bulk delete conversations
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_delete_conversations() {
		global $wpdb;

		$conversation_ids = array_map( 'intval', $_POST['conversation_ids'] );
		$placeholders = implode( ',', array_fill( 0, count( $conversation_ids ), '%d' ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ai_chatbot_conversations WHERE id IN ($placeholders)",
				$conversation_ids
			)
		);

		if ( $deleted ) {
			add_settings_error(
				'ai_chatbot_conversations',
				'conversations_deleted',
				sprintf(
					/* translators: %d: number of deleted conversations */
					_n( '%d conversation deleted.', '%d conversations deleted.', $deleted, 'ai-website-chatbot' ),
					$deleted
				),
				'updated'
			);
		}
	}

	/**
	 * AJAX: Sync content for training
	 *
	 * @since 1.0.0
	 */
	public function sync_content() {
		check_ajax_referer( 'ai_chatbot_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-website-chatbot' ) ) );
		}

		try {
			$content_sync = new AI_Chatbot_Content_Sync();
			$result = $content_sync->sync_website_content();

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of synced items */
					__( 'Successfully synced %d content items.', 'ai-website-chatbot' ),
					$result['synced']
				),
				'stats' => $result,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Test AI connection
	 *
	 * @since 1.0.0
	 */
	public function test_ai_connection() {
		check_ajax_referer( 'ai_chatbot_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-website-chatbot' ) ) );
		}

		$provider_name = $_POST['provider'] ?? get_option( 'ai_chatbot_ai_provider', 'openai' );
		
		try {
			$provider = $this->get_ai_provider( $provider_name );
			
			if ( is_wp_error( $provider ) ) {
				wp_send_json_error( array( 'message' => $provider->get_error_message() ) );
			}

			$test_result = $provider->test_connection();
			
			if ( is_wp_error( $test_result ) ) {
				wp_send_json_error( array( 'message' => $test_result->get_error_message() ) );
			}

			wp_send_json_success( array(
				'message' => __( 'Connection test successful!', 'ai-website-chatbot' ),
				'provider' => $provider_name,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Add action links to plugin list
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 * @since 1.0.0
	 */
	public function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=ai-chatbot-settings' ),
			__( 'Settings', 'ai-website-chatbot' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Add admin notices
	 *
	 * @since 1.0.0
	 */
	public function add_admin_notices() {
		// Show activation notice
		if ( get_option( 'ai_chatbot_activation_redirect' ) ) {
			delete_option( 'ai_chatbot_activation_redirect' );
			
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'AI Website Chatbot has been activated!', 'ai-website-chatbot' ),
				esc_url( admin_url( 'admin.php?page=ai-chatbot-settings' ) ),
				esc_html__( 'Configure Settings', 'ai-website-chatbot' )
			);
		}

		// Show configuration warnings
		if ( get_option( 'ai_chatbot_enabled' ) && ! $this->is_properly_configured() ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'AI Chatbot is enabled but not properly configured.', 'ai-website-chatbot' ),
				esc_url( admin_url( 'admin.php?page=ai-chatbot-settings' ) ),
				esc_html__( 'Complete Setup', 'ai-website-chatbot' )
			);
		}
	}

	/**
	 * Check if plugin is properly configured
	 *
	 * @return bool True if configured properly.
	 * @since 1.0.0
	 */
	private function is_properly_configured() {
		$ai_provider = get_option( 'ai_chatbot_ai_provider', 'openai' );

		switch ( $ai_provider ) {
			case 'openai':
				return ! empty( get_option( 'ai_chatbot_openai_api_key' ) );
			case 'claude':
				return ! empty( get_option( 'ai_chatbot_claude_api_key' ) );
			case 'gemini':
				return ! empty( get_option( 'ai_chatbot_gemini_api_key' ) );
		}

		return false;
	}

	/**
	 * Check if current page is a plugin admin page
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return bool True if plugin page.
	 * @since 1.0.0
	 */
	private function is_plugin_page( $hook_suffix ) {
		return strpos( $hook_suffix, 'ai-chatbot' ) !== false;
	}

	/**
	 * Get admin localization data
	 *
	 * @return array Localization data.
	 * @since 1.0.0
	 */
	private function get_admin_localization() {
		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ai_chatbot_admin_nonce' ),
			'strings' => array(
				'testing' => __( 'Testing connection...', 'ai-website-chatbot' ),
				'success' => __( 'Success!', 'ai-website-chatbot' ),
				'error' => __( 'Error:', 'ai-website-chatbot' ),
				'syncing' => __( 'Syncing content...', 'ai-website-chatbot' ),
				'confirmDelete' => __( 'Are you sure you want to delete the selected conversations?', 'ai-website-chatbot' ),
				'selectItems' => __( 'Please select items to delete.', 'ai-website-chatbot' ),
				'saving' => __( 'Saving...', 'ai-website-chatbot' ),
			),
		);
	}

	/**
	 * Section callback for general settings
	 *
	 * @since 1.0.0
	 */
	public function general_section_callback() {
		echo '<p>' . esc_html__( 'Configure the basic settings for your AI chatbot.', 'ai-website-chatbot' ) . '</p>';
	}

	/**
	 * Section callback for AI provider settings
	 *
	 * @since 1.0.0
	 */
	public function ai_provider_section_callback() {
		echo '<p>' . esc_html__( 'Configure your AI service provider and API credentials.', 'ai-website-chatbot' ) . '</p>';
	}

	/**
	 * Section callback for display settings
	 *
	 * @since 1.0.0
	 */
	public function display_section_callback() {
		echo '<p>' . esc_html__( 'Customize how the chatbot appears on your website.', 'ai-website-chatbot' ) . '</p>';
	}

	/**
	 * Sanitize settings input
	 *
	 * @param array $input Settings input.
	 * @return array Sanitized settings.
	 * @since 1.0.0
	 */
	public function sanitize_settings( $input ) {
		return $this->settings->validate_settings( $input );
	}
}