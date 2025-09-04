<?php
/**
 * AI Chatbot Admin Settings Class
 * 
 * Handles all settings-related functionality for the AI Chatbot plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AI_Chatbot_Admin_Settings
 */
class AI_Chatbot_Admin_Settings {
    
    /**
     * Constructor - No parameters required
     */
    public function __construct() {
        // Initialize settings hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers for settings
        add_action('wp_ajax_ai_chatbot_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_ai_chatbot_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_ai_chatbot_test_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_ai_chatbot_sync_content', array($this, 'ajax_sync_content'));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ai-website-chatbot'));
        }
        
        // Get current settings
        $settings = $this->get_settings();
        
        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('ai_chatbot_admin_nonce', 'nonce')) {
            $this->handle_settings_update();
        }
        
        // Include settings template
        include AI_CHATBOT_PLUGIN_DIR . 'admin/partials/admin-settings-display.php';
    }
    
    /**
     * Get plugin settings with defaults
     */
    public function get_settings() {
        $defaults = $this->get_default_settings();
        $settings = get_option('ai_chatbot_settings', array());
        
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Get default settings
     */
    private function get_default_settings() {
        return array(
            // General Settings
            'enabled' => false,
            'widget_position' => 'bottom-right',
            'widget_color' => '#0073aa',
            'welcome_message' => __('Hello! How can I help you today?', 'ai-website-chatbot'),
            'offline_message' => __('Sorry, the chatbot is currently offline. Please try again later.', 'ai-website-chatbot'),
            
            // AI Provider Settings
            'ai_provider' => 'openai',
            'api_key' => '',
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 150,
            'temperature' => 0.7,
            'system_prompt' => __('You are a helpful assistant for this website. Provide accurate and helpful responses based on the website content.', 'ai-website-chatbot'),
            
            // Display Settings
            'show_on_pages' => array('all'),
            'hide_on_pages' => array(),
            'widget_size' => 'medium',
            'animation_style' => 'slide',
            'show_typing_indicator' => true,
            'show_timestamp' => true,
            
            // Rate Limiting
            'rate_limiting' => array(
                'enabled' => true,
                'max_requests' => 10,
                'time_window' => 3600, // 1 hour
                'blocked_message' => __('You have reached the maximum number of requests. Please try again later.', 'ai-website-chatbot')
            ),
            
            // Content Sync
            'content_sync' => array(
                'enabled' => false,
                'post_types' => array('post', 'page'),
                'sync_frequency' => 'daily',
                'auto_sync' => true,
                'include_excerpt' => true,
                'include_content' => false
            ),
            
            // GDPR Settings
            'gdpr' => array(
                'enabled' => false,
                'data_retention_days' => 30,
                'privacy_policy_url' => '',
                'cookie_consent' => false,
                'anonymize_data' => true
            ),
            
            // Advanced Settings
            'debug_mode' => false,
            'log_conversations' => true,
            'cache_responses' => false,
            'custom_css' => '',
            'custom_js' => ''
        );
    }
    
    /**
     * Handle settings update
     */

    public function handle_settings_update() {
        // Security: Check nonce - FIXED to match the form nonce
        if (!check_admin_referer('ai_chatbot_admin_nonce', 'nonce')) {
            add_settings_error(
                'ai_chatbot_settings',
                'nonce_failed',
                __('Security check failed. Please try again.', 'ai-website-chatbot'),
                'error'
            );
            return false;
        }

        // Permission check
        if (!current_user_can('manage_options')) {
            add_settings_error(
                'ai_chatbot_settings',
                'permission_denied',
                __('You do not have sufficient permissions to save settings.', 'ai-website-chatbot'),
                'error'
            );
            return false;
        }

        $saved_count = 0;
        $errors = array();
        $updated_settings = array();

        // ====================================================================
        // GENERAL SETTINGS
        // ====================================================================
        
        // Basic settings
        $general_settings = array(
            'ai_chatbot_enabled' => isset($_POST['ai_chatbot_enabled']) ? true : false,
            'ai_chatbot_widget_position' => sanitize_text_field($_POST['ai_chatbot_widget_position'] ?? 'bottom-right'),
            'ai_chatbot_theme_color' => sanitize_hex_color($_POST['ai_chatbot_theme_color'] ?? '#0073aa'),
            'ai_chatbot_welcome_message' => wp_kses_post($_POST['ai_chatbot_welcome_message'] ?? ''),
            'ai_chatbot_placeholder_text' => sanitize_text_field($_POST['ai_chatbot_placeholder_text'] ?? ''),
            'ai_chatbot_offline_message' => wp_kses_post($_POST['ai_chatbot_offline_message'] ?? ''),
        );

        // ====================================================================
        // AI PROVIDER SETTINGS
        // ====================================================================
        
        $provider_settings = array(
            'ai_chatbot_ai_provider' => sanitize_text_field($_POST['ai_chatbot_ai_provider'] ?? 'openai'),
            'ai_chatbot_openai_api_key' => sanitize_text_field($_POST['ai_chatbot_openai_api_key'] ?? ''),
            'ai_chatbot_openai_model' => sanitize_text_field($_POST['ai_chatbot_openai_model'] ?? 'gpt-3.5-turbo'),
            'ai_chatbot_openai_temperature' => max(0, min(2, floatval($_POST['ai_chatbot_openai_temperature'] ?? 0.7))),
            'ai_chatbot_openai_max_tokens' => max(1, min(4000, intval($_POST['ai_chatbot_openai_max_tokens'] ?? 150))),
            'ai_chatbot_claude_api_key' => sanitize_text_field($_POST['ai_chatbot_claude_api_key'] ?? ''),
            'ai_chatbot_claude_model' => sanitize_text_field($_POST['ai_chatbot_claude_model'] ?? 'claude-3-haiku-20240307'),
            'ai_chatbot_gemini_api_key' => sanitize_text_field($_POST['ai_chatbot_gemini_api_key'] ?? ''),
            'ai_chatbot_gemini_model' => sanitize_text_field($_POST['ai_chatbot_gemini_model'] ?? 'gemini-pro'),
            'ai_chatbot_system_prompt' => wp_kses_post($_POST['ai_chatbot_system_prompt'] ?? ''),
        );

        // ====================================================================
        // DISPLAY & BEHAVIOR SETTINGS
        // ====================================================================
        
        $display_settings = array(
            'ai_chatbot_widget_size' => sanitize_text_field($_POST['ai_chatbot_widget_size'] ?? 'medium'),
            'ai_chatbot_animation_style' => sanitize_text_field($_POST['ai_chatbot_animation_style'] ?? 'slide'),
            'ai_chatbot_show_typing_indicator' => isset($_POST['ai_chatbot_show_typing_indicator']) ? true : false,
            'ai_chatbot_show_timestamp' => isset($_POST['ai_chatbot_show_timestamp']) ? true : false,
            'ai_chatbot_show_powered_by' => isset($_POST['ai_chatbot_show_powered_by']) ? true : false,
            'ai_chatbot_enable_rating' => isset($_POST['ai_chatbot_enable_rating']) ? true : false,
        );

        // ====================================================================
        // RATE LIMITING SETTINGS
        // ====================================================================
        
        $rate_limit_settings = array(
            'ai_chatbot_rate_limit_enabled' => isset($_POST['ai_chatbot_rate_limit_enabled']) ? true : false,
            'ai_chatbot_rate_limit_per_minute' => max(1, intval($_POST['ai_chatbot_rate_limit_per_minute'] ?? 10)),
            'ai_chatbot_rate_limit_per_hour' => max(1, intval($_POST['ai_chatbot_rate_limit_per_hour'] ?? 50)),
            'ai_chatbot_blocked_message' => wp_kses_post($_POST['ai_chatbot_blocked_message'] ?? ''),
        );

        // ====================================================================
        // CONTENT SYNC SETTINGS
        // ====================================================================
        
        $content_sync_settings = array(
            'ai_chatbot_content_sync_enabled' => isset($_POST['ai_chatbot_content_sync_enabled']) ? true : false,
            'ai_chatbot_auto_train' => isset($_POST['ai_chatbot_auto_train']) ? true : false,
            'ai_chatbot_sync_frequency' => sanitize_text_field($_POST['ai_chatbot_sync_frequency'] ?? 'daily'),
            'ai_chatbot_allowed_post_types' => isset($_POST['ai_chatbot_allowed_post_types']) && is_array($_POST['ai_chatbot_allowed_post_types']) ? 
                array_map('sanitize_text_field', $_POST['ai_chatbot_allowed_post_types']) : array('post', 'page'),
            'ai_chatbot_excluded_pages' => isset($_POST['ai_chatbot_excluded_pages']) && is_array($_POST['ai_chatbot_excluded_pages']) ? 
                array_map('intval', $_POST['ai_chatbot_excluded_pages']) : array(),
        );

        // ====================================================================
        // GDPR & PRIVACY SETTINGS
        // ====================================================================
        
        $privacy_settings = array(
            'ai_chatbot_gdpr_enabled' => isset($_POST['ai_chatbot_gdpr_enabled']) ? true : false,
            'ai_chatbot_data_retention_days' => max(0, intval($_POST['ai_chatbot_data_retention_days'] ?? 30)),
            'ai_chatbot_privacy_policy_url' => esc_url_raw($_POST['ai_chatbot_privacy_policy_url'] ?? ''),
            'ai_chatbot_terms_url' => esc_url_raw($_POST['ai_chatbot_terms_url'] ?? ''),
            'ai_chatbot_collect_ip' => isset($_POST['ai_chatbot_collect_ip']) ? true : false,
            'ai_chatbot_collect_user_agent' => isset($_POST['ai_chatbot_collect_user_agent']) ? true : false,
            'ai_chatbot_anonymize_data' => isset($_POST['ai_chatbot_anonymize_data']) ? true : false,
            'ai_chatbot_cookie_consent' => isset($_POST['ai_chatbot_cookie_consent']) ? true : false,
        );

        // ====================================================================
        // ADVANCED SETTINGS
        // ====================================================================
        
        $advanced_settings = array(
            'ai_chatbot_debug_mode' => isset($_POST['ai_chatbot_debug_mode']) ? true : false,
            'ai_chatbot_log_conversations' => isset($_POST['ai_chatbot_log_conversations']) ? true : false,
            'ai_chatbot_cache_responses' => isset($_POST['ai_chatbot_cache_responses']) ? true : false,
            'ai_chatbot_max_message_length' => max(1, intval($_POST['ai_chatbot_max_message_length'] ?? 1000)),
            'ai_chatbot_custom_css' => wp_strip_all_tags($_POST['ai_chatbot_custom_css'] ?? ''),
            'ai_chatbot_custom_js' => wp_strip_all_tags($_POST['ai_chatbot_custom_js'] ?? ''),
        );

        // ====================================================================
        // COMBINE ALL SETTINGS
        // ====================================================================
        
        $all_settings = array_merge(
            $general_settings,
            $provider_settings, 
            $display_settings,
            $rate_limit_settings,
            $content_sync_settings,
            $privacy_settings,
            $advanced_settings
        );

        // ====================================================================
        // SAVE SETTINGS TO DATABASE
        // ====================================================================
        
        foreach ($all_settings as $setting_name => $setting_value) {
            $current_value = get_option($setting_name);
            
            if ($current_value !== $setting_value) {
                if (update_option($setting_name, $setting_value)) {
                    $saved_count++;
                    $updated_settings[$setting_name] = $setting_value;
                } else {
                    $errors[] = $setting_name;
                }
            } else {
                // Value is the same, count as successful
                $saved_count++;
            }
        }

        // ====================================================================
        // HANDLE RESULTS & SHOW MESSAGES
        // ====================================================================
        
        if ($saved_count > 0) {
            // Show success message
            add_settings_error(
                'ai_chatbot_settings',
                'settings_updated',
                sprintf(__('%d settings saved successfully!', 'ai-website-chatbot'), $saved_count),
                'updated'
            );
            
            // Test AI connection if provider settings were updated
            if (isset($updated_settings['ai_chatbot_ai_provider']) || 
                isset($updated_settings['ai_chatbot_openai_api_key']) ||
                isset($updated_settings['ai_chatbot_claude_api_key']) ||
                isset($updated_settings['ai_chatbot_gemini_api_key'])) {
                $this->test_ai_connection_after_save($updated_settings);
            }
            
            // Trigger content sync if auto-train is enabled
            if (isset($updated_settings['ai_chatbot_auto_train']) && $updated_settings['ai_chatbot_auto_train']) {
                $this->maybe_trigger_content_sync();
            }
            
            // Clear any caches
            wp_cache_flush();
            
            return true;
            
        } elseif (!empty($errors)) {
            add_settings_error(
                'ai_chatbot_settings',
                'settings_errors',
                sprintf(__('Failed to save %d settings. Please try again.', 'ai-website-chatbot'), count($errors)),
                'error'
            );
            return false;
            
        } else {
            add_settings_error(
                'ai_chatbot_settings',
                'no_changes',
                __('No changes detected in settings.', 'ai-website-chatbot'),
                'notice-info'
            );
            return true;
        }
    }

    /**
     * Helper method: Test AI connection after save
     */
    private function test_ai_connection_after_save($settings) {
        $provider = $settings['ai_chatbot_ai_provider'] ?? get_option('ai_chatbot_ai_provider', 'openai');
        
        try {
            // Load the appropriate provider class
            switch ($provider) {
                case 'openai':
                    if (class_exists('AI_Chatbot_OpenAI')) {
                        $provider_instance = new AI_Chatbot_OpenAI();
                        $test_result = $provider_instance->test_connection();
                        break;
                    }
                case 'claude':
                    if (class_exists('AI_Chatbot_Claude')) {
                        $provider_instance = new AI_Chatbot_Claude();
                        $test_result = $provider_instance->test_connection();
                        break;
                    }
                case 'gemini':
                    if (class_exists('AI_Chatbot_Gemini')) {
                        $provider_instance = new AI_Chatbot_Gemini();
                        $test_result = $provider_instance->test_connection();
                        break;
                    }
                default:
                    return; // Skip test if provider not found
            }
            
            if (isset($test_result) && !is_wp_error($test_result)) {
                add_settings_error(
                    'ai_chatbot_settings',
                    'connection_success',
                    __('AI connection test successful!', 'ai-website-chatbot'),
                    'notice-success'
                );
            } elseif (is_wp_error($test_result)) {
                add_settings_error(
                    'ai_chatbot_settings',
                    'connection_failed', 
                    sprintf(__('AI connection test failed: %s', 'ai-website-chatbot'), $test_result->get_error_message()),
                    'notice-warning'
                );
            }
        } catch (Exception $e) {
            add_settings_error(
                'ai_chatbot_settings',
                'connection_error',
                sprintf(__('Connection test error: %s', 'ai-website-chatbot'), $e->getMessage()),
                'notice-warning'
            );
        }
    }

    /**
     * Helper method: Trigger content sync if needed
     */
    private function maybe_trigger_content_sync() {
        if (get_option('ai_chatbot_auto_train', false)) {
            // Schedule content sync
            if (!wp_next_scheduled('ai_chatbot_content_sync')) {
                wp_schedule_single_event(time() + 60, 'ai_chatbot_content_sync');
            }
            
            add_settings_error(
                'ai_chatbot_settings',
                'content_sync_scheduled',
                __('Content synchronization has been scheduled.', 'ai-website-chatbot'),
                'notice-info'
            );
        }
    }

    /**
     * Helper method: Sanitize individual setting values
     */
    private function sanitize_setting_value($key, $value) {
        switch ($key) {
            // Text/Message fields
            case 'ai_chatbot_welcome_message':
            case 'ai_chatbot_offline_message':
            case 'ai_chatbot_system_prompt':
            case 'ai_chatbot_blocked_message':
                return wp_kses_post($value);
                
            // API Keys (sensitive data)
            case 'ai_chatbot_openai_api_key':
            case 'ai_chatbot_claude_api_key': 
            case 'ai_chatbot_gemini_api_key':
                return sanitize_text_field(trim($value));
                
            // Color values
            case 'ai_chatbot_theme_color':
                $color = sanitize_hex_color($value);
                return $color ? $color : '#0073aa';
                
            // Numeric settings with limits
            case 'ai_chatbot_max_tokens':
                return max(1, min(4000, intval($value)));
            case 'ai_chatbot_data_retention_days':
                return max(0, intval($value));
            case 'ai_chatbot_rate_limit_per_minute':
                return max(1, min(100, intval($value)));
            case 'ai_chatbot_rate_limit_per_hour':
                return max(1, min(1000, intval($value)));
            case 'ai_chatbot_max_message_length':
                return max(10, min(5000, intval($value)));
                
            // Float settings with limits
            case 'ai_chatbot_openai_temperature':
                return max(0, min(2, floatval($value)));
                
            // Boolean settings
            case 'ai_chatbot_enabled':
            case 'ai_chatbot_auto_train':
            case 'ai_chatbot_collect_ip':
            case 'ai_chatbot_collect_user_agent':
            case 'ai_chatbot_enable_rating':
            case 'ai_chatbot_show_powered_by':
            case 'ai_chatbot_show_typing_indicator':
            case 'ai_chatbot_show_timestamp':
            case 'ai_chatbot_gdpr_enabled':
            case 'ai_chatbot_anonymize_data':
            case 'ai_chatbot_cookie_consent':
            case 'ai_chatbot_debug_mode':
            case 'ai_chatbot_log_conversations':
            case 'ai_chatbot_cache_responses':
            case 'ai_chatbot_content_sync_enabled':
            case 'ai_chatbot_rate_limit_enabled':
                return !empty($value) && $value !== '0' && $value !== 'false';
                
            // Array settings
            case 'ai_chatbot_allowed_post_types':
                return is_array($value) ? array_map('sanitize_text_field', $value) : array();
            case 'ai_chatbot_excluded_pages':
                return is_array($value) ? array_map('intval', $value) : array();
                
            // URL settings
            case 'ai_chatbot_privacy_policy_url':
            case 'ai_chatbot_terms_url':
                return esc_url_raw($value);
                
            // Select/dropdown settings
            case 'ai_chatbot_ai_provider':
                $valid_providers = array('openai', 'claude', 'gemini');
                return in_array($value, $valid_providers) ? $value : 'openai';
                
            case 'ai_chatbot_widget_position':
                $valid_positions = array('bottom-right', 'bottom-left', 'top-right', 'top-left', 'center');
                return in_array($value, $valid_positions) ? $value : 'bottom-right';
                
            case 'ai_chatbot_widget_size':
                $valid_sizes = array('small', 'medium', 'large');
                return in_array($value, $valid_sizes) ? $value : 'medium';
                
            case 'ai_chatbot_sync_frequency':
                $valid_frequencies = array('hourly', 'daily', 'weekly');
                return in_array($value, $valid_frequencies) ? $value : 'daily';
                
            // Default sanitization
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Sanitize settings input
     */
    private function sanitize_settings($input) {
        $sanitized = array();
        
        // General Settings
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['widget_position'] = sanitize_text_field($input['widget_position'] ?? 'bottom-right');
        $sanitized['widget_color'] = sanitize_hex_color($input['widget_color'] ?? '#0073aa');
        $sanitized['welcome_message'] = sanitize_textarea_field($input['welcome_message'] ?? '');
        $sanitized['offline_message'] = sanitize_textarea_field($input['offline_message'] ?? '');
        
        // AI Provider Settings
        $sanitized['ai_provider'] = sanitize_text_field($input['ai_provider'] ?? 'openai');
        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $sanitized['model'] = sanitize_text_field($input['model'] ?? 'gpt-3.5-turbo');
        $sanitized['max_tokens'] = absint($input['max_tokens'] ?? 150);
        $sanitized['temperature'] = floatval($input['temperature'] ?? 0.7);
        $sanitized['system_prompt'] = sanitize_textarea_field($input['system_prompt'] ?? '');
        
        // Display Settings
        $sanitized['show_on_pages'] = is_array($input['show_on_pages']) ? array_map('sanitize_text_field', $input['show_on_pages']) : array('all');
        $sanitized['hide_on_pages'] = is_array($input['hide_on_pages']) ? array_map('sanitize_text_field', $input['hide_on_pages']) : array();
        $sanitized['widget_size'] = sanitize_text_field($input['widget_size'] ?? 'medium');
        $sanitized['animation_style'] = sanitize_text_field($input['animation_style'] ?? 'slide');
        $sanitized['show_typing_indicator'] = !empty($input['show_typing_indicator']);
        $sanitized['show_timestamp'] = !empty($input['show_timestamp']);
        
        // Rate Limiting
        if (isset($input['rate_limiting']) && is_array($input['rate_limiting'])) {
            $sanitized['rate_limiting'] = array(
                'enabled' => !empty($input['rate_limiting']['enabled']),
                'max_requests' => absint($input['rate_limiting']['max_requests'] ?? 10),
                'time_window' => absint($input['rate_limiting']['time_window'] ?? 3600),
                'blocked_message' => sanitize_textarea_field($input['rate_limiting']['blocked_message'] ?? '')
            );
        }
        
        // Content Sync
        if (isset($input['content_sync']) && is_array($input['content_sync'])) {
            $sanitized['content_sync'] = array(
                'enabled' => !empty($input['content_sync']['enabled']),
                'post_types' => is_array($input['content_sync']['post_types']) ? array_map('sanitize_text_field', $input['content_sync']['post_types']) : array('post', 'page'),
                'sync_frequency' => sanitize_text_field($input['content_sync']['sync_frequency'] ?? 'daily'),
                'auto_sync' => !empty($input['content_sync']['auto_sync']),
                'include_excerpt' => !empty($input['content_sync']['include_excerpt']),
                'include_content' => !empty($input['content_sync']['include_content'])
            );
        }
        
        // GDPR Settings
        if (isset($input['gdpr']) && is_array($input['gdpr'])) {
            $sanitized['gdpr'] = array(
                'enabled' => !empty($input['gdpr']['enabled']),
                'data_retention_days' => absint($input['gdpr']['data_retention_days'] ?? 30),
                'privacy_policy_url' => esc_url_raw($input['gdpr']['privacy_policy_url'] ?? ''),
                'cookie_consent' => !empty($input['gdpr']['cookie_consent']),
                'anonymize_data' => !empty($input['gdpr']['anonymize_data'])
            );
        }
        
        // Advanced Settings
        $sanitized['debug_mode'] = !empty($input['debug_mode']);
        $sanitized['log_conversations'] = !empty($input['log_conversations']);
        $sanitized['cache_responses'] = !empty($input['cache_responses']);
        $sanitized['custom_css'] = wp_strip_all_tags($input['custom_css'] ?? '');
        $sanitized['custom_js'] = wp_strip_all_tags($input['custom_js'] ?? '');
        
        return $sanitized;
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        // Verify nonce

        if (!check_ajax_referer('ai_chatbot_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'ai-website-chatbot'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
            return;
        }
        
        if (!isset($_POST['settings'])) {
            wp_send_json_error(__('No settings data received', 'ai-website-chatbot'));
            return;
        }

        // Parse the serialized form data
        parse_str($_POST['settings'], $form_data);
        
        $settings_to_save = array();
        
        // Process settings
        foreach ($form_data as $key => $value) {
            if (strpos($key, 'ai_chatbot_') === 0) {
                $settings_to_save[$key] = $this->sanitize_setting_value($key, $value);
            }
        }

        // Save settings
        $saved_count = 0;
        foreach ($settings_to_save as $setting_name => $setting_value) {
            if (update_option($setting_name, $setting_value)) {
                $saved_count++;
            }
        }

        if ($saved_count > 0) {
            wp_send_json_success(sprintf(__('%d settings saved successfully!', 'ai-website-chatbot'), $saved_count));
        } else {
            wp_send_json_error(__('No settings were updated', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Reset settings to defaults
     */
    public function ajax_reset_settings() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $defaults = $this->get_default_settings();
        $updated = update_option('ai_chatbot_settings', $defaults);
        
        if ($updated) {
            wp_send_json_success(__('Settings reset to defaults successfully!', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Failed to reset settings', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api_connection() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($provider) || empty($api_key)) {
            wp_send_json_error(__('Provider and API key are required', 'ai-website-chatbot'));
        }
        
        // Load the provider class
        $provider_file = AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/class-ai-chatbot-' . $provider . '.php';
        
        if (!file_exists($provider_file)) {
            wp_send_json_error(__('Provider not supported', 'ai-website-chatbot'));
        }
        
        require_once $provider_file;
        $provider_class = 'AI_Chatbot_' . ucfirst($provider);
        
        if (!class_exists($provider_class)) {
            wp_send_json_error(__('Provider class not found', 'ai-website-chatbot'));
        }
        
        try {
            $provider_instance = new $provider_class();
            $result = $provider_instance->test_connection($api_key);
            
            if ($result) {
                wp_send_json_success(__('Connection successful!', 'ai-website-chatbot'));
            } else {
                wp_send_json_error(__('Connection failed. Please check your API key.', 'ai-website-chatbot'));
            }
        } catch (Exception $e) {
            wp_send_json_error(sprintf(__('Connection error: %s', 'ai-website-chatbot'), $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Sync content
     */
    public function ajax_sync_content() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        // Load content sync class
        if (!class_exists('AI_Chatbot_Content_Sync')) {
            require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-content-sync.php';
        }
        
        $content_sync = new AI_Chatbot_Content_Sync();
        $result = $content_sync->sync_content();
        
        if ($result) {
            wp_send_json_success(__('Content synchronized successfully!', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Content synchronization failed', 'ai-website-chatbot'));
        }
    }
    
    /**
     * Get available AI providers
     */
    public function get_available_providers() {
        return array(
            'openai' => array(
                'name' => 'OpenAI',
                'description' => __('GPT-3.5 and GPT-4 models from OpenAI', 'ai-website-chatbot'),
                'models' => array(
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                    'gpt-4' => 'GPT-4',
                    'gpt-4-turbo' => 'GPT-4 Turbo'
                )
            ),
            'claude' => array(
                'name' => 'Anthropic Claude',
                'description' => __('Claude models from Anthropic', 'ai-website-chatbot'),
                'models' => array(
                    'claude-3-haiku' => 'Claude 3 Haiku',
                    'claude-3-sonnet' => 'Claude 3 Sonnet',
                    'claude-3-opus' => 'Claude 3 Opus'
                )
            ),
            'gemini' => array(
                'name' => 'Google Gemini',
                'description' => __('Gemini models from Google', 'ai-website-chatbot'),
                'models' => array(
                    'gemini-pro' => 'Gemini Pro',
                    'gemini-pro-vision' => 'Gemini Pro Vision'
                )
            ),
            'custom' => array(
                'name' => 'Custom API',
                'description' => __('Custom AI API endpoint', 'ai-website-chatbot'),
                'models' => array()
            )
        );
    }
    
    /**
     * Get available post types for content sync
     */
    public function get_available_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $available = array();
        
        foreach ($post_types as $post_type) {
            $available[$post_type->name] = $post_type->label;
        }
        
        return $available;
    }
    
    /**
     * Get widget positions
     */
    public function get_widget_positions() {
        return array(
            'bottom-right' => __('Bottom Right', 'ai-website-chatbot'),
            'bottom-left' => __('Bottom Left', 'ai-website-chatbot'),
            'top-right' => __('Top Right', 'ai-website-chatbot'),
            'top-left' => __('Top Left', 'ai-website-chatbot'),
            'center' => __('Center', 'ai-website-chatbot')
        );
    }
    
    /**
     * Get widget sizes
     */
    public function get_widget_sizes() {
        return array(
            'small' => __('Small', 'ai-website-chatbot'),
            'medium' => __('Medium', 'ai-website-chatbot'),
            'large' => __('Large', 'ai-website-chatbot')
        );
    }
    
    /**
     * Get animation styles
     */
    public function get_animation_styles() {
        return array(
            'slide' => __('Slide', 'ai-website-chatbot'),
            'fade' => __('Fade', 'ai-website-chatbot'),
            'bounce' => __('Bounce', 'ai-website-chatbot'),
            'none' => __('None', 'ai-website-chatbot')
        );
    }
}