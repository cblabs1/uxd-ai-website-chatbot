<?php
/**
 * AJAX Handler Class for AI Website Chatbot
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class AI_Chatbot_Ajax {
    
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name    The name of this plugin.
     * @param    string    $version        The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Initialize AJAX hooks
        $this->init_ajax_hooks();
    }

    /**
     * Initialize AJAX hooks
     *
     * @since 1.0.0
     */
    private function init_ajax_hooks() {
        // Main chat message handler - both logged in and non-logged in users
        add_action('wp_ajax_ai_chatbot_message', array($this, 'handle_chat_message'));
        add_action('wp_ajax_nopriv_ai_chatbot_message', array($this, 'handle_chat_message'));
        
        // Also register send_message for backward compatibility
        add_action('wp_ajax_ai_chatbot_send_message', array($this, 'handle_chat_message'));
        add_action('wp_ajax_nopriv_ai_chatbot_send_message', array($this, 'handle_chat_message'));
        
        // Additional AJAX handlers
        add_action('wp_ajax_ai_chatbot_rating', array($this, 'handle_rating'));
        add_action('wp_ajax_nopriv_ai_chatbot_rating', array($this, 'handle_rating'));
        
        add_action('wp_ajax_ai_chatbot_clear_conversation', array($this, 'handle_clear_conversation'));
        add_action('wp_ajax_nopriv_ai_chatbot_clear_conversation', array($this, 'handle_clear_conversation'));
        
        add_action('wp_ajax_ai_chatbot_get_suggestions', array($this, 'handle_get_suggestions'));
        add_action('wp_ajax_nopriv_ai_chatbot_get_suggestions', array($this, 'handle_get_suggestions'));
        
        add_action('wp_ajax_ai_chatbot_status_check', array($this, 'handle_status_check'));
        add_action('wp_ajax_nopriv_ai_chatbot_status_check', array($this, 'handle_status_check'));
        
        add_action('wp_ajax_ai_chatbot_export_data', array($this, 'handle_export_data'));
        add_action('wp_ajax_nopriv_ai_chatbot_export_data', array($this, 'handle_export_data'));
    }

    /**
     * Handle chat message AJAX request
     *
     * @since 1.0.0
     */
    public function handle_chat_message() {
        // Log the request for debugging
        error_log('AI Chatbot: Received chat message request');
        
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        // Check rate limiting
        $user_identifier = $this->get_user_identifier();
        if (!$this->check_rate_limit($user_identifier)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait a moment.', 'ai-website-chatbot')));
            return;
        }

        // Get and sanitize input
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');

        // Validate input
        if (empty($message)) {
            wp_send_json_error(array('message' => __('Message cannot be empty.', 'ai-website-chatbot')));
            return;
        }

        if (empty($session_id)) {
            $session_id = 'session_' . uniqid();
        }

        if (empty($conversation_id)) {
            $conversation_id = 'conv_' . time() . '_' . wp_generate_password(10, false);
        }

        // Save user message to database first
        $user_message_saved = $this->save_message($session_id, $conversation_id, $message, null, $page_url);
        
        if (!$user_message_saved) {
            error_log('AI Chatbot: Failed to save user message to database');
        }

        // Get AI provider and settings
        $settings = get_option('ai_chatbot_settings', array());
        $provider_name = $settings['ai_provider'] ?? 'openai';
        
        error_log('AI Chatbot: Provider from main settings - ' . $provider_name);

        // Load the AI provider
        $provider = $this->get_ai_provider($provider_name);
        
        if (!$provider || !$provider->is_configured()) {
            wp_send_json_error(array('message' => __('AI service is not configured.', 'ai-website-chatbot')));
            return;
        }

        error_log('AI Chatbot: Generating AI response');

        // Get conversation history for context
        $history = $this->get_conversation_history($session_id);
        
        // Get website content for context
        $context = $this->get_website_context($page_url);

        // Generate AI response
        $ai_response = $provider->generate_response($message, $context, array(
            'history' => $history,
            'max_tokens' => intval($settings['max_tokens'] ?? 300),
            'temperature' => floatval($settings['temperature'] ?? 0.7)
        ));

        if (is_wp_error($ai_response)) {
            wp_send_json_error(array('message' => $ai_response->get_error_message()));
            return;
        }

        // Update the conversation with AI response
        $this->update_conversation_response($session_id, $conversation_id, $ai_response);

        // Return successful response
        wp_send_json_success(array(
            'response' => $ai_response,
            'session_id' => $session_id,
            'conversation_id' => $conversation_id,
            'message' => __('Response generated successfully.', 'ai-website-chatbot')
        ));
    }

    /**
     * Update conversation with AI response
     *
     * @param string $session_id
     * @param string $conversation_id
     * @param string $ai_response
     * @return int|false
     * @since 1.0.0
     */
    private function update_conversation_response($session_id, $conversation_id, $ai_response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        return $wpdb->update(
            $table_name,
            array(
                'ai_response' => $ai_response,
                'updated_at' => current_time('mysql')
            ),
            array(
                'session_id' => $session_id,
                'conversation_id' => $conversation_id,
                'ai_response' => null  // Update the most recent record without ai_response
            ),
            array('%s', '%s'),
            array('%s', '%s', '%s')
        );
    }

    /**
     * Get website context for AI
     *
     * @param string $page_url Current page URL
     * @return string Context string
     * @since 1.0.0
     */
    private function get_website_context($page_url = '') {
        $context = '';
        
        // Add site information
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        
        if (!empty($site_name)) {
            $context .= "Website: " . $site_name . "\n";
        }
        
        if (!empty($site_description)) {
            $context .= "Description: " . $site_description . "\n";
        }
        
        // Add current page context if URL provided
        if (!empty($page_url)) {
            $post_id = url_to_postid($page_url);
            if ($post_id) {
                $post = get_post($post_id);
                if ($post) {
                    $context .= "Current page: " . $post->post_title . "\n";
                    $context .= "Page content: " . wp_trim_words(strip_tags($post->post_content), 50) . "\n";
                }
            }
        }
        
        // Add general context from settings
        $custom_context = get_option('ai_chatbot_website_context', '');
        if (!empty($custom_context)) {
            $context .= $custom_context . "\n";
        }
        
        return $context;
    }

    /**
     * Get AI provider instance
     *
     * @return AI_Chatbot_Provider_Interface|WP_Error
     * @since 1.0.0
     */
    private function get_ai_provider() {
		$main_settings = get_option('ai_chatbot_settings', array());

		if (!empty($main_settings['ai_provider'])) {
			$provider_name = $main_settings['ai_provider'];
			error_log('AI Chatbot: Provider from main settings - ' . $provider_name);
		} else {
			// Only fallback to individual option if main settings don't exist
			$provider_name = get_option('ai_chatbot_ai_provider', 'openai');
			error_log('AI Chatbot: Provider from individual option (fallback) - ' . $provider_name);
		}
        
        // Ensure provider classes are loaded
        $providers_path = AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/';
        
        // Load provider interface if not loaded
        if (!interface_exists('AI_Chatbot_Provider_Interface')) {
            require_once $providers_path . 'class-ai-chatbot-provider-interface.php';
        }
        
        switch ($provider_name) {
            case 'openai':
                if (!class_exists('AI_Chatbot_OpenAI')) {
                    require_once $providers_path . 'class-ai-chatbot-openai.php';
                }
                if (class_exists('AI_Chatbot_OpenAI')) {
                    return new AI_Chatbot_OpenAI();
                }
                break;
                
            case 'claude':
                if (!class_exists('AI_Chatbot_Claude')) {
                    require_once $providers_path . 'class-ai-chatbot-claude.php';
                }
                if (class_exists('AI_Chatbot_Claude')) {
                    return new AI_Chatbot_Claude();
                }
                break;
                
            case 'gemini':
                if (!class_exists('AI_Chatbot_Gemini')) {
                    require_once $providers_path . 'class-ai-chatbot-gemini.php';
                }
                if (class_exists('AI_Chatbot_Gemini')) {
                    return new AI_Chatbot_Gemini();
                }
                break;
        }
        
        return new WP_Error('invalid_provider', __('AI provider not available or not properly configured.', 'ai-website-chatbot'));
    }

    /**
     * Get user identifier for rate limiting
     *
     * @return string
     * @since 1.0.0
     */
    private function get_user_identifier() {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        } else {
            return 'ip_' . $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * Check rate limit
     *
     * @param string $identifier User identifier
     * @return bool
     * @since 1.0.0
     */
    private function check_rate_limit($identifier) {
        $settings = get_option('ai_chatbot_settings', array());
        $rate_limit_enabled = $settings['rate_limit_enabled'] ?? false;
        
        if (!$rate_limit_enabled) {
            return true;
        }
        
        $max_requests = intval($settings['rate_limit_per_minute'] ?? 10);
        $time_window = 60; // 1 minute
        
        // Use IP address for rate limiting
        $identifier = $this->get_client_ip();
        $transient_key = 'ai_chatbot_rate_limit_' . md5($identifier);
        $current_count = get_transient($transient_key);
        
        if ($current_count === false) {
            set_transient($transient_key, 1, $time_window);
            return true;
        }
        
        if ($current_count >= $max_requests) {
            return false;
        }
        
        set_transient($transient_key, $current_count + 1, $time_window);
        return true;
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     * @since 1.0.0
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Save message to database
     *
     * @param string $session_id
     * @param string $conversation_id
     * @param string $user_message
     * @param string|null $ai_response
     * @param string $page_url
     * @return int|false
     * @since 1.0.0
     */
    private function save_message($session_id, $conversation_id, $user_message, $ai_response = null, $page_url = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('AI Chatbot Error: Database table does not exist');
            $this->create_tables();
        }
        
        return $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'conversation_id' => $conversation_id,
                'user_message' => $user_message,
                'ai_response' => $ai_response,
                'page_url' => $page_url,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Create database tables
     *
     * @since 1.0.0
     */
    private function create_tables() {
        global $wpdb;
    
		$charset_collate = $wpdb->get_charset_collate();
		
		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		
		// CORRECTED: Include all required columns from the start
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            conversation_id varchar(255) NOT NULL,
            user_message longtext NOT NULL,
            ai_response longtext DEFAULT NULL,
            user_name varchar(255) DEFAULT '',
            user_email varchar(255) DEFAULT '',
            user_ip varchar(100) DEFAULT '',
            user_agent varchar(500) DEFAULT '',
            page_url varchar(255) DEFAULT '',
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
            KEY idx_session_id (session_id),
            KEY idx_conversation_id (conversation_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
    }

    /**
     * Get conversation history
     *
     * @param string $session_id
     * @param string $conversation_id
     * @return string
     * @since 1.0.0
     */
    private function get_conversation_history($session_id, $limit = 5) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT user_message, ai_response, created_at 
            FROM $table_name 
            WHERE session_id = %s 
            AND user_message IS NOT NULL 
            AND ai_response IS NOT NULL 
            ORDER BY created_at DESC 
            LIMIT %d",
            $session_id,
            $limit
        ), ARRAY_A);
        
        // Reverse to get chronological order (oldest first)
        return array_reverse($results ?: array());
    }

    /**
     * Get conversation starter suggestions
     *
     * @return array Suggestions
     * @since 1.0.0
     */
    private function get_conversation_starters() {
        $default_suggestions = array(
            __('How can I help you today?', 'ai-website-chatbot'),
            __('What would you like to know?', 'ai-website-chatbot'),
            __('Ask me anything about our services.', 'ai-website-chatbot'),
            __('I\'m here to assist you.', 'ai-website-chatbot')
        );
        
        $custom_suggestions = get_option('ai_chatbot_conversation_starters', array());
        
        return !empty($custom_suggestions) ? $custom_suggestions : $default_suggestions;
    }

    /**
     * Check AI service status
     *
     * @return bool True if online
     * @since 1.0.0
     */
    private function check_ai_service_status() {
        $settings = get_option('ai_chatbot_settings', array());
        $provider_name = $settings['ai_provider'] ?? 'openai';
        
        $provider = $this->get_ai_provider($provider_name);
        
        if (!$provider || !$provider->is_configured()) {
            return false;
        }
        
        // Simple connection test
        $test_result = $provider->test_connection();
        
        return !is_wp_error($test_result);
    }

    /**
     * Clear conversation history
     *
     * @param string $session_id
     * @return bool
     * @since 1.0.0
     */
    private function clear_conversation_history($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        return $wpdb->delete(
            $table_name,
            array('session_id' => $session_id),
            array('%s')
        );
    }

    /**
     * Save rating
     *
     * @param string $conversation_id
     * @param int $rating
     * @return int|false
     * @since 1.0.0
     */
    private function save_rating($conversation_id, $rating) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_ratings';
        
        // Create table if not exists
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            conversation_id varchar(255) NOT NULL,
            rating int(11) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) " . $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        return $wpdb->insert(
            $table_name,
            array(
                'conversation_id' => $conversation_id,
                'rating' => $rating,
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%d', '%s')
        );
    }

    /**
     * Export conversation data
     *
     * @param string $session_id
     * @return array
     * @since 1.0.0
     */
    private function export_conversation_data($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s ORDER BY timestamp ASC",
            $session_id
        ), ARRAY_A);
        
        return $results;
    }

    /**
     * Handle rating submission
     *
     * @since 1.0.0
     */
    public function handle_rating() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $rating = intval($_POST['rating'] ?? 0);

        if (empty($conversation_id) || !in_array($rating, [-1, 1])) {
            wp_send_json_error(array('message' => __('Invalid rating data.', 'ai-website-chatbot')));
            return;
        }

        $this->save_rating($conversation_id, $rating);

        wp_send_json_success(array(
            'message' => __('Thank you for your feedback!', 'ai-website-chatbot')
        ));
    }

    /**
     * Handle clear conversation
     *
     * @since 1.0.0
     */
    public function handle_clear_conversation() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (!empty($session_id)) {
            $this->clear_conversation_history($session_id);
        }

        wp_send_json_success(array(
            'new_session_id' => 'session_' . uniqid(),
            'message' => __('Conversation cleared.', 'ai-website-chatbot')
        ));
    }

    /**
     * Handle get suggestions
     *
     * @since 1.0.0
     */
    public function handle_get_suggestions() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        $suggestions = $this->get_conversation_starters();

        wp_send_json_success(array(
            'suggestions' => $suggestions
        ));
    }

    /**
     * Handle status check
     *
     * @since 1.0.0
     */
    public function handle_status_check() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        $is_online = $this->check_ai_service_status();

        wp_send_json_success(array(
            'online' => $is_online,
            'status' => $is_online ? 'online' : 'offline'
        ));
    }

    /**
     * Handle export data
     *
     * @since 1.0.0
     */
    public function handle_export_data() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('No conversation to export.', 'ai-website-chatbot')));
            return;
        }

        $conversation_data = $this->export_conversation_data($session_id);

        wp_send_json_success(array(
            'data' => $conversation_data,
            'filename' => 'chatbot-conversation-' . date('Y-m-d-H-i-s') . '.json'
        ));
    }
}