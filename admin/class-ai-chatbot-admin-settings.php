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
        if (isset($_POST['submit']) && check_admin_referer('ai_chatbot_settings_nonce')) {
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
    private function handle_settings_update() {
        if (!isset($_POST['ai_chatbot_settings']) || !is_array($_POST['ai_chatbot_settings'])) {
            add_settings_error('ai_chatbot_settings', 'invalid_data', __('Invalid settings data.', 'ai-website-chatbot'));
            return;
        }
        
        $new_settings = $_POST['ai_chatbot_settings'];
        $sanitized_settings = $this->sanitize_settings($new_settings);
        
        // Save settings
        $updated = update_option('ai_chatbot_settings', $sanitized_settings);
        
        if ($updated) {
            add_settings_error('ai_chatbot_settings', 'settings_updated', __('Settings saved successfully!', 'ai-website-chatbot'), 'updated');
            
            // Trigger content sync if enabled and settings changed
            if ($sanitized_settings['content_sync']['enabled']) {
                $this->maybe_trigger_content_sync();
            }
        } else {
            add_settings_error('ai_chatbot_settings', 'settings_error', __('Failed to save settings. Please try again.', 'ai-website-chatbot'));
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
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        if (!isset($_POST['settings']) || !is_array($_POST['settings'])) {
            wp_send_json_error(__('Invalid settings data', 'ai-website-chatbot'));
        }
        
        $settings = $_POST['settings'];
        $sanitized_settings = $this->sanitize_settings($settings);
        
        $updated = update_option('ai_chatbot_settings', $sanitized_settings);
        
        if ($updated) {
            // Trigger content sync if needed
            if ($sanitized_settings['content_sync']['enabled']) {
                $this->maybe_trigger_content_sync();
            }
            
            wp_send_json_success(__('Settings saved successfully!', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Failed to save settings', 'ai-website-chatbot'));
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
     * Maybe trigger content sync after settings update
     */
    private function maybe_trigger_content_sync() {
        $settings = $this->get_settings();
        
        if ($settings['content_sync']['enabled'] && $settings['content_sync']['auto_sync']) {
            // Schedule content sync if not already scheduled
            if (!wp_next_scheduled('ai_chatbot_sync_content')) {
                wp_schedule_event(time(), $settings['content_sync']['sync_frequency'], 'ai_chatbot_sync_content');
            }
        } else {
            // Remove scheduled sync if disabled
            wp_clear_scheduled_hook('ai_chatbot_sync_content');
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