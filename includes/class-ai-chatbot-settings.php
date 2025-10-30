<?php
/**
 * Settings management for AI Chatbot
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
 * Settings management class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Settings {

	/**
	 * Settings groups
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $setting_groups = array();

	/**
	 * Default settings
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $defaults = array();

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_defaults();
		$this->init_setting_groups();
	}

	/**
	 * Initialize default settings
	 *
	 * @since 1.0.0
	 */
	private function init_defaults() {
		$this->defaults = array(
			// General settings
			'enabled'                => false,
			'enable_shortcodes_when_disabled' => false,
			'ai_provider'           => 'openai',
			'position'              => 'bottom-right',
			'theme_color'           => '#0073aa',
			'welcome_message'       => __( 'Hello! How can I help you today?', 'ai-website-chatbot' ),
			'placeholder_text'      => __( 'Type your message...', 'ai-website-chatbot' ),
			'send_button_text'      => __( 'Send', 'ai-website-chatbot' ),
			
			// Display settings
			'widget_title'          => __( 'AI Assistant', 'ai-website-chatbot' ),
			'show_on_mobile'        => true,
			'show_on_pages'         => array(),
			'hide_on_pages'         => array(),
			'show_to_logged_users'  => true,
			'show_to_guests'        => true,
			
			// AI Provider settings
			'openai_api_key'        => '',
			'openai_model'          => 'gpt-3.5-turbo',
			'openai_temperature'    => 0.7,
			'openai_max_tokens'     => 300,
			
			'claude_api_key'        => '',
			'claude_model'          => 'claude-3-haiku-20240307',
			'claude_max_tokens'     => 300,
			
			'gemini_api_key'        => '',
			'gemini_model'          => 'gemini-pro',
			'gemini_temperature'    => 0.7,
			
			// Training settings
			'auto_train'            => false,
			'allowed_post_types'    => array( 'post', 'page' ),
			'training_frequency'    => 'daily',
			'max_content_length'    => 2000,
			
			// Security & Privacy
			'collect_ip'            => false,
			'collect_user_agent'    => false,
			'data_retention_days'   => 30,
			'rate_limit_per_minute' => 10,
			'rate_limit_per_hour'   => 50,
			'max_message_length'    => 1000,
			'blocked_words'         => '',
			
			// Analytics
			'enable_analytics'      => true,
			'enable_rating'         => true,
			'track_conversations'   => true,

			'debug_mode' => false,
            'log_conversations' => true,
            'cache_responses' => false,
			
			// Advanced
			'custom_css'            => '',
			'custom_prompt'         => '',
			'fallback_message'      => __( "I'm sorry, I couldn't understand that. Could you please rephrase your question?", 'ai-website-chatbot' ),
			'error_message'         => __( "I'm experiencing some technical difficulties. Please try again later.", 'ai-website-chatbot' ),
		);
	}

	/**
	 * Initialize setting groups
	 *
	 * @since 1.0.0
	 */
	private function init_setting_groups() {
		$this->setting_groups = array(
			'general'  => __( 'General Settings', 'ai-website-chatbot' ),
			'display'  => __( 'Display Settings', 'ai-website-chatbot' ),
			'ai'       => __( 'AI Configuration', 'ai-website-chatbot' ),
			'training' => __( 'Content Training', 'ai-website-chatbot' ),
			'privacy'  => __( 'Privacy & Security', 'ai-website-chatbot' ),
			'advanced' => __( 'Advanced Settings', 'ai-website-chatbot' ),
		);
	}

	/**
	 * Get setting value with fallback to default
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $default Default value if not set.
	 * @return mixed Option value.
	 * @since 1.0.0
	 */
	public function get( $option_name, $default = null ) {
		$value = get_option( $option_name, $default );
		
		// If no default provided, use plugin defaults
		if ( $default === null && isset( $this->defaults[ $option_name ] ) ) {
			$value = get_option( $option_name, $this->defaults[ $option_name ] );
		}
		
		return apply_filters( 'ai_chatbot_get_setting', $value, $option_name );
	}

	/**
	 * Update setting value
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value Option value.
	 * @return bool True if updated, false otherwise.
	 * @since 1.0.0
	 */
	public function update( $option_name, $value ) {
		$value = apply_filters( 'ai_chatbot_update_setting', $value, $option_name );
		
		// Sanitize based on option type
		$value = $this->sanitize_setting( $option_name, $value );
		
		return update_option( $option_name, $value );
	}

	/**
	 * Delete setting
	 *
	 * @param string $option_name Option name.
	 * @return bool True if deleted, false otherwise.
	 * @since 1.0.0
	 */
	public function delete( $option_name ) {
		return delete_option( $option_name );
	}

	/**
	 * Get all settings
	 *
	 * @return array All plugin settings.
	 * @since 1.0.0
	 */
	public function get_all() {
		$settings = array();
		
		foreach ( $this->defaults as $option_name => $default_value ) {
			$settings[ $option_name ] = $this->get( $option_name );
		}
		
		return $settings;
	}

	/**
	 * Reset settings to defaults
	 *
	 * @param array $groups Setting groups to reset.
	 * @return bool True if reset successful.
	 * @since 1.0.0
	 */
	public function reset_to_defaults( $groups = array() ) {
		$settings_to_reset = $this->defaults;
		
		// If specific groups provided, filter settings
		if ( ! empty( $groups ) ) {
			$settings_to_reset = array_filter( 
				$settings_to_reset, 
				function( $key ) use ( $groups ) {
					return $this->get_setting_group( $key, $groups );
				}, 
				ARRAY_FILTER_USE_KEY 
			);
		}
		
		foreach ( $settings_to_reset as $option_name => $default_value ) {
			update_option( $option_name, $default_value );
		}
		
		return true;
	}

	/**
	 * Export settings
	 *
	 * @return string JSON encoded settings.
	 * @since 1.0.0
	 */
	public function export_settings() {
		$settings = $this->get_all();
		
		// Remove sensitive data
		$sensitive_keys = array(
			'ai_chatbot_openai_api_key',
			'ai_chatbot_claude_api_key',
			'ai_chatbot_gemini_api_key',
		);
		
		foreach ( $sensitive_keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$settings[ $key ] = '[REDACTED]';
			}
		}
		
		return wp_json_encode( $settings, JSON_PRETTY_PRINT );
	}

	/**
	 * Import settings from JSON
	 *
	 * @param string $json_data JSON encoded settings.
	 * @param bool   $overwrite_existing Whether to overwrite existing settings.
	 * @return bool|WP_Error True if successful, WP_Error if failed.
	 * @since 1.0.0
	 */
	public function import_settings( $json_data, $overwrite_existing = false ) {
		$settings = json_decode( $json_data, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON data provided.', 'ai-website-chatbot' ) );
		}
		
		$imported_count = 0;
		
		foreach ( $settings as $option_name => $value ) {
			// Skip sensitive data that was redacted
			if ( $value === '[REDACTED]' ) {
				continue;
			}
			
			// Only import known settings
			if ( ! isset( $this->defaults[ $option_name ] ) ) {
				continue;
			}
			
			// Skip if setting exists and overwrite is disabled
			if ( ! $overwrite_existing && get_option( $option_name ) !== false ) {
				continue;
			}
			
			if ( $this->update( $option_name, $value ) ) {
				$imported_count++;
			}
		}
		
		return $imported_count;
	}

	/**
	 * Validate settings before save
	 *
	 * @param array $settings Settings array to validate.
	 * @return array|WP_Error Validated settings or WP_Error if validation fails.
	 * @since 1.0.0
	 */
	public function validate_settings( $settings ) {
		$validated = array();
		$errors = array();
		
		foreach ( $settings as $option_name => $value ) {
			// Skip unknown settings
			if ( ! isset( $this->defaults[ $option_name ] ) ) {
				continue;
			}
			
			$sanitized_value = $this->sanitize_setting( $option_name, $value );
			
			if ( is_wp_error( $sanitized_value ) ) {
				$errors[ $option_name ] = $sanitized_value->get_error_message();
				continue;
			}
			
			$validated[ $option_name ] = $sanitized_value;
		}
		
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', __( 'Settings validation failed.', 'ai-website-chatbot' ), $errors );
		}
		
		return $validated;
	}

	/**
	 * Sanitize individual setting
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value Option value.
	 * @return mixed|WP_Error Sanitized value or WP_Error if sanitization fails.
	 * @since 1.0.0
	 */
	private function sanitize_setting( $option_name, $value ) {
		switch ( $option_name ) {
			// API Keys
            case 'api_key':
            case 'openai_api_key':
            case 'claude_api_key':
            case 'gemini_api_key':
                return sanitize_text_field($value);
                
            // Text areas with HTML
            case 'welcome_message':
            case 'offline_message':
            case 'system_prompt':
            case 'blocked_message':
			
                return sanitize_textarea_field($value);
                
            // Boolean/checkbox fields
            case 'enabled':
			case 'enable_shortcodes_when_disabled':
            case 'debug_mode':
            case 'log_conversations':
            case 'cache_responses':
            case 'show_on_mobile':
            case 'show_typing_indicator':
            case 'show_timestamp':
            case 'rate_limit_enabled':
            case 'gdpr_anonymize_data':
			case 'ai_chatbot_keep_data_on_uninstall':
                return !empty($value) ? 1 : 0;
                
            // Numeric fields
            case 'max_tokens':
            case 'max_message_length':
            case 'max_requests':
            case 'time_window':
            case 'retention_days':
                return max(1, intval($value));
                
            // Float fields
            case 'temperature':
                return max(0, min(2, floatval($value)));
                
            // Color fields
            case 'widget_color':
            case 'theme_color':
                return sanitize_hex_color($value) ?: '#0073aa';
                
            // URL fields
            case 'privacy_policy_url':
                return esc_url_raw($value);
                
            // Select/dropdown fields
            case 'ai_provider':
            case 'model':
            case 'widget_position':
            case 'widget_size':
            case 'animation_style':
            case 'content_sync_frequency':
                return sanitize_text_field($value);

            // Decimals
            case 'temperature':
                return floatval($value);

            // CSS/JS
            case 'custom_css':
            case 'custom_js':
                return wp_strip_all_tags($value);
                
            // Array fields
            case 'post_types':
            case 'show_on_pages':
            case 'hide_on_pages':
                return is_array($value) ? array_map('sanitize_text_field', $value) : array();
             
            default:
                return sanitize_text_field($value);
		}
	}

	/**
	 * Check if setting belongs to specific groups
	 *
	 * @param string $setting_name Setting name.
	 * @param array  $groups Groups to check.
	 * @return bool True if setting belongs to any of the groups.
	 * @since 1.0.0
	 */
	private function get_setting_group( $setting_name, $groups ) {
		$group_mappings = array(
			'general'  => array( 'enabled', 'enable_shortcodes_when_disabled', 'ai_provider', 'welcome_message', 'placeholder_text', 'send_button_text', 'widget_title' ),
			'display'  => array( 'position', 'theme_color', 'show_on_mobile', 'show_on_pages', 'hide_on_pages', 'show_to_logged_users', 'show_to_guests' ),
			'ai'       => array( 'openai_', 'claude_', 'gemini_' ),
			'training' => array( 'auto_train', 'allowed_post_types', 'training_frequency', 'max_content_length' ),
			'privacy'  => array( 'collect_ip', 'collect_user_agent', 'data_retention_days', 'rate_limit_', 'max_message_length', 'blocked_words' ),
			'advanced' => array( 'custom_css', 'custom_prompt', 'fallback_message', 'error_message' ),
		);
		
		foreach ( $groups as $group ) {
			if ( ! isset( $group_mappings[ $group ] ) ) {
				continue;
			}
			
			foreach ( $group_mappings[ $group ] as $pattern ) {
				if ( strpos( $setting_name, $pattern ) !== false ) {
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * Get setting groups
	 *
	 * @return array Setting groups.
	 * @since 1.0.0
	 */
	public function get_setting_groups() {
		return $this->setting_groups;
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings.
	 * @since 1.0.0
	 */
	public function get_defaults() {
		return $this->defaults;
	}
}