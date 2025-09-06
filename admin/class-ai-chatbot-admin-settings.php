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
    
        // Get from main settings first, then fallback to individual options
        $saved_settings = get_option('ai_chatbot_settings', array());

        $main_settings = wp_parse_args($saved_settings, $defaults);

        
        if (!empty($main_settings)) {
            // Use main settings structure
            $settings = wp_parse_args($main_settings, $defaults);
        } else {
            // Fallback: try to build from individual options
            $individual_settings = array();
            foreach ($defaults as $key => $default_value) {
                $option_name = 'ai_chatbot_' . $key;
                $individual_settings[$key] = get_option($option_name, $default_value);
            }
            $settings = $individual_settings;
        }

        if (isset($main_settings['rate_limiting'])) {
            $settings['rate_limiting'] = wp_parse_args($main_settings['rate_limiting'], $defaults['rate_limiting']);
        }
        
        if (isset($main_settings['gdpr'])) {
            $settings['gdpr'] = wp_parse_args($main_settings['gdpr'], $defaults['gdpr']);
        }
        
        // Ensure ai_provider is set
        if (empty($settings['ai_provider'])) {
            $settings['ai_provider'] = get_option('ai_chatbot_ai_provider', 'openai');
        }
        
        return $settings;
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
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ai-website-chatbot'));
        }
        
        // Verify nonce - FIXED: Use correct nonce name from form
        if (!check_admin_referer('ai_chatbot_admin_nonce', 'nonce')) {
            wp_die(__('Security check failed', 'ai-website-chatbot'));
        }
        
        // Get submitted settings from the ai_chatbot_settings array
        $submitted_settings = $_POST['ai_chatbot_settings'] ?? array();
        
        // CRITICAL FIX: Get defaults and handle missing checkboxes
        $defaults = $this->get_default_settings();
        
        // Extract checkbox defaults (boolean values) and set unchecked ones to false
        $processed_settings = $this->process_checkbox_settings($submitted_settings, $defaults);
        
        // Save the complete settings as one option
        $save_result = update_option('ai_chatbot_settings', $processed_settings);
        
        if ($save_result) {
            add_settings_error(
                'ai_chatbot_settings',
                'settings_updated',
                __('Settings saved successfully!', 'ai-website-chatbot'),
                'updated'
            );
            
            // Test API connection if enabled and API key provided
            if (!empty($processed_settings['enabled']) && !empty($processed_settings['api_key'])) {
                $this->maybe_test_api_connection($processed_settings);
            }
            
            // Trigger content sync if needed
            $this->maybe_trigger_content_sync();
            
        } else {
            // Check if settings are the same (update_option returns false if no change)
            $current_settings = get_option('ai_chatbot_settings', array());
            if ($current_settings === $processed_settings) {
                add_settings_error(
                    'ai_chatbot_settings',
                    'settings_unchanged',
                    __('Settings are up to date!', 'ai-website-chatbot'),
                    'updated'
                );
            } else {
                add_settings_error(
                    'ai_chatbot_settings',
                    'settings_failed',
                    __('Failed to save settings. Please try again.', 'ai-website-chatbot'),
                    'error'
                );
            }
        }
    }

    /**
     * Process checkbox settings to handle unchecked values
     * 
     * @param array $submitted_settings Settings from form submission
     * @param array $defaults Default settings structure
     * @return array Processed settings with proper checkbox values
     */
    private function process_checkbox_settings($submitted_settings, $defaults) {
        // Start with current settings to preserve non-form values
        $current_settings = get_option('ai_chatbot_settings', array());
        
        // Recursively process settings
        $processed = $this->merge_settings_recursive($submitted_settings, $defaults, $current_settings);
        
        return $processed;
    }

    /**
     * Recursively merge settings handling checkboxes properly
     * 
     * @param array $submitted Submitted form data
     * @param array $defaults Default values structure
     * @param array $current Current saved settings
     * @return array Merged settings
     */
    private function merge_settings_recursive($submitted, $defaults, $current) {
        $result = $current; // Start with current settings
        
        foreach ($defaults as $key => $default_value) {
            if (is_array($default_value)) {
                // Handle nested arrays (like gdpr, rate_limiting, content_sync)
                if (!isset($result[$key])) {
                    $result[$key] = array();
                }
                
                $submitted_nested = $submitted[$key] ?? array();
                $result[$key] = $this->merge_settings_recursive($submitted_nested, $default_value, $result[$key]);
                
            } elseif (is_bool($default_value)) {
                // Handle checkbox fields - set to false if not submitted, true if submitted
                $result[$key] = isset($submitted[$key]) && $submitted[$key];
                
            } else {
                // Handle regular fields - use submitted value if provided, otherwise keep current
                if (isset($submitted[$key])) {
                    $result[$key] = $this->sanitize_setting_value($key, $submitted[$key]);
                }
            }
        }
        
        // Add any submitted settings that aren't in defaults (for flexibility)
        foreach ($submitted as $key => $value) {
            if (!isset($defaults[$key]) && !is_array($value)) {
                $result[$key] = $this->sanitize_setting_value($key, $value);
            }
        }
        
        return $result;
    }


    /**
     * Helper method: Test AI connection after save
     */
    private function maybe_test_api_connection($settings) {
        $provider = '';
        $api_key = '';
        
        // Check if it's in the new structure
        if (isset($settings['ai_chatbot_settings'])) {
            $provider = $settings['ai_chatbot_settings']['ai_provider'] ?? '';
            $api_key = $settings['ai_chatbot_settings']['api_key'] ?? '';
        } else {
            // Check individual settings
            $provider = $settings['ai_chatbot_ai_provider'] ?? '';
            $api_key = $settings['ai_chatbot_api_key'] ?? '';
        }
        
        if (empty($provider) || empty($api_key)) {
            error_log('AI Chatbot: Skipping connection test - missing provider or API key');
            return;
        }
        
        error_log('AI Chatbot: Testing connection after save for provider: ' . $provider);
        
        $result = $this->test_provider_connection($provider, $api_key);
        
        if (is_wp_error($result)) {
            error_log('AI Chatbot: Connection test failed: ' . $result->get_error_message());
            add_settings_error(
                'ai_chatbot_settings',
                'connection_failed',
                sprintf(__('Settings saved but API connection test failed: %s', 'ai-website-chatbot'), $result->get_error_message()),
                'notice-warning'
            );
        } else {
            error_log('AI Chatbot: Connection test successful');
            add_settings_error(
                'ai_chatbot_settings',
                'connection_success',
                __('Settings saved and API connection test successful!', 'ai-website-chatbot'),
                'notice-success'
            );
        }
    }

    /**
     * Helper method: Trigger content sync if needed
     */
    private function maybe_trigger_content_sync() {
        $settings = get_option('ai_chatbot_settings', array());
        if (!empty($settings['content_sync']['auto_sync'])) {
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
        // Handle arrays
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        
        switch ($key) {
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
            case 'debug_mode':
            case 'log_conversations':
            case 'cache_responses':
            case 'show_on_mobile':
            case 'show_typing_indicator':
            case 'show_timestamp':
            case 'rate_limit_enabled':
            case 'gdpr_anonymize_data':
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
        // Debug logging
        error_log('AI Chatbot: ajax_save_settings called');
        error_log('AI Chatbot: POST data keys: ' . implode(', ', array_keys($_POST)));
        
        // Verify nonce
        if (!check_ajax_referer('ai_chatbot_admin_nonce', 'nonce', false)) {
            error_log('AI Chatbot: Nonce verification failed');
            wp_send_json_error(__('Security check failed', 'ai-website-chatbot'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            error_log('AI Chatbot: User lacks permissions');
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
            return;
        }
        
        if (!isset($_POST['settings'])) {
            error_log('AI Chatbot: No settings parameter found');
            wp_send_json_error(__('No settings data received', 'ai-website-chatbot'));
            return;
        }

        // Parse the serialized form data
        parse_str($_POST['settings'], $form_data);
        
        error_log('AI Chatbot: Parsed form data: ' . print_r($form_data, true));
        
        // CORRECT: Save the entire ai_chatbot_settings array as one option
        if (isset($form_data['ai_chatbot_settings']) && is_array($form_data['ai_chatbot_settings'])) {
            
            $new_settings = $form_data['ai_chatbot_settings'];
            
            // Get current settings to merge with new ones
            
            $current_settings = get_option('ai_chatbot_settings', array());
            
            // Merge current settings with new settings
            $checkbox_fields = array(
                'enabled',
                'debug_mode', 
                'log_conversations',
                'show_on_mobile',
                'show_typing_indicator'
            );

            $checkbox_nested_fields = array(
                'gdpr' => array('enabled', 'cookie_consent', 'anonymize_data'),
                'rate_limiting' => array('enabled')
            );

            // Set missing checkboxes to false
            foreach ($checkbox_fields as $field) {
                if (!isset($new_settings[$field])) {
                    $new_settings[$field] = false;
                }
            }

            // Handle nested checkbox fields
            foreach ($checkbox_nested_fields as $parent => $fields) {
                if (!isset($new_settings[$parent])) {
                    $new_settings[$parent] = array();
                }
                foreach ($fields as $field) {
                    if (!isset($new_settings[$parent][$field])) {
                        $new_settings[$parent][$field] = false;
                    }
                }
            }
            $updated_settings = array_merge($current_settings, $new_settings);
            
            error_log('AI Chatbot: Current settings: ' . print_r($current_settings, true));
            error_log('AI Chatbot: New settings: ' . print_r($new_settings, true));
            error_log('AI Chatbot: Merged settings: ' . print_r($updated_settings, true));
            
            // Save the complete settings array as one option
            $current_check = get_option('ai_chatbot_settings', array());
            if ($current_check === $updated_settings) {
                error_log('AI Chatbot: Settings unchanged, but counting as success');
                wp_send_json_success(__('Settings are up to date!', 'ai-website-chatbot'));
                return; // Add this return statement
            }
            
            $save_result = update_option('ai_chatbot_settings', $updated_settings);
            
            if ($save_result) {
                error_log('AI Chatbot: Settings saved successfully');
                wp_send_json_success(sprintf(__('%d settings saved successfully!', 'ai-website-chatbot'), count($new_settings)));
            } else {
                // Check if settings are the same (update_option returns false if no change)
                $current_check = get_option('ai_chatbot_settings', array());
                if ($current_check === $updated_settings) {
                    error_log('AI Chatbot: Settings unchanged, but counting as success');
                    wp_send_json_success(__('Settings are up to date!', 'ai-website-chatbot'));
                } else {
                    error_log('AI Chatbot: Failed to save settings');
                    wp_send_json_error(__('Failed to save settings', 'ai-website-chatbot'));
                }
            }
            
        } else {
            error_log('AI Chatbot: No ai_chatbot_settings array found in form data');
            wp_send_json_error(__('Invalid settings data structure', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Reset settings to defaults
     */
    public function ajax_reset_settings() {
        error_log('AI Chatbot: ajax_reset_settings called');
        
        if (!check_ajax_referer('ai_chatbot_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'ai-website-chatbot'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
            return;
        }
        
        $defaults = $this->get_default_settings();
        $reset_count = 0;
        
        foreach ($defaults as $setting_name => $default_value) {
            if (update_option($setting_name, $default_value)) {
                $reset_count++;
            }
        }
        
        if ($reset_count > 0) {
            wp_send_json_success(sprintf(__('%d settings reset to defaults successfully!', 'ai-website-chatbot'), $reset_count));
        } else {
            wp_send_json_error(__('No settings were reset', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api_connection() {
        error_log('AI Chatbot: ajax_test_api_connection called');
        
        if (!check_ajax_referer('ai_chatbot_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'ai-website-chatbot'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
            return;
        }
        
        // FIXED: Get provider and API key from the correct location
        $main_settings = get_option('ai_chatbot_settings', array());
        
        // Check if we have values in POST data (from the test button)
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        // If not in POST, get from main settings
        if (empty($provider) && !empty($main_settings['ai_provider'])) {
            $provider = $main_settings['ai_provider'];
        }
        
        if (empty($api_key) && !empty($main_settings['api_key'])) {
            $api_key = $main_settings['api_key'];
        }
        
        // Fallback to individual options if main settings don't exist
        if (empty($provider)) {
            $provider = get_option('ai_chatbot_ai_provider', 'openai');
        }
        
        if (empty($api_key)) {
            switch ($provider) {
                case 'openai':
                    $api_key = get_option('ai_chatbot_openai_api_key', '');
                    break;
                case 'claude':
                    $api_key = get_option('ai_chatbot_claude_api_key', '');
                    break;
                case 'gemini':
                    $api_key = get_option('ai_chatbot_gemini_api_key', '');
                    break;
            }
        }
        
        error_log('AI Chatbot: Testing connection for provider: ' . $provider);
        error_log('AI Chatbot: API key present: ' . (empty($api_key) ? 'NO' : 'YES (' . strlen($api_key) . ' chars)'));
        
        if (empty($provider) || empty($api_key)) {
            wp_send_json_error(__('Provider and API key are required', 'ai-website-chatbot'));
            return;
        }
        
        // Test connection based on provider
        $result = $this->test_provider_connection($provider, $api_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('API connection successful!', 'ai-website-chatbot'));
        }
    }

    /**
     * Test provider connection
     */
    private function test_provider_connection($provider, $api_key) {
        error_log('AI Chatbot: test_provider_connection called with provider: ' . $provider);
        
        // Load provider classes
        $providers_path = AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/';
        
        // Load provider interface if not loaded
        if (!interface_exists('AI_Chatbot_Provider_Interface')) {
            require_once $providers_path . 'class-ai-chatbot-provider-interface.php';
        }
        
        switch ($provider) {
            case 'openai':
                if (!class_exists('AI_Chatbot_OpenAI')) {
                    require_once $providers_path . 'class-ai-chatbot-openai.php';
                }
                if (class_exists('AI_Chatbot_OpenAI')) {
                    // Temporarily override the API key for testing
                    $original_key = get_option('ai_chatbot_openai_api_key', '');
                    update_option('ai_chatbot_openai_api_key', $api_key);
                    
                    $provider_instance = new AI_Chatbot_OpenAI();
                    $result = $provider_instance->test_connection();
                    
                    // Restore original key
                    update_option('ai_chatbot_openai_api_key', $original_key);
                    
                    return $result;
                }
                break;
                
            case 'claude':
                if (!class_exists('AI_Chatbot_Claude')) {
                    require_once $providers_path . 'class-ai-chatbot-claude.php';
                }
                if (class_exists('AI_Chatbot_Claude')) {
                    // Temporarily override the API key for testing
                    $original_key = get_option('ai_chatbot_claude_api_key', '');
                    update_option('ai_chatbot_claude_api_key', $api_key);
                    
                    $provider_instance = new AI_Chatbot_Claude();
                    $result = $provider_instance->test_connection();
                    
                    // Restore original key
                    update_option('ai_chatbot_claude_api_key', $original_key);
                    
                    return $result;
                }
                break;
                
            case 'gemini':
                if (!class_exists('AI_Chatbot_Gemini')) {
                    require_once $providers_path . 'class-ai-chatbot-gemini.php';
                }
                if (class_exists('AI_Chatbot_Gemini')) {
                    // FIXED: For testing, temporarily set both the main settings and individual option
                    $main_settings = get_option('ai_chatbot_settings', array());
                    $original_main_settings = $main_settings;
                    $original_individual_key = get_option('ai_chatbot_gemini_api_key', '');
                    
                    // Set the test values
                    $main_settings['ai_provider'] = 'gemini';
                    $main_settings['api_key'] = $api_key;
                    update_option('ai_chatbot_settings', $main_settings);
                    update_option('ai_chatbot_gemini_api_key', $api_key);
                    
                    $provider_instance = new AI_Chatbot_Gemini();
                    $result = $provider_instance->test_connection();
                    
                    // Restore original values
                    update_option('ai_chatbot_settings', $original_main_settings);
                    update_option('ai_chatbot_gemini_api_key', $original_individual_key);
                    
                    return $result;
                }
                break;
        }
        
        return new WP_Error('provider_not_found', __('AI provider class not found.', 'ai-website-chatbot'));
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
                    'gemini-pro-vision' => 'Gemini Pro Vision',
                    'gemini-2.0-flash' => 'Gemini 2.0 Flash',
                    'gemini-1.5-flash' => 'Gemini 1.5 Flash'
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