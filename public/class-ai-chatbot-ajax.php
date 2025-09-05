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
            error_log('AI Chatbot Error: Nonce verification failed');
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'ai-website-chatbot')
            ));
            return;
        }

        // Rate limiting check
        $user_identifier = $this->get_user_identifier();
        if (!$this->check_rate_limit($user_identifier)) {
            wp_send_json_error(array(
                'message' => __('Too many requests. Please wait a moment before sending another message.', 'ai-website-chatbot')
            ));
            return;
        }

        // Get and sanitize input
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');

        // Validate message
        if (empty($message)) {
            wp_send_json_error(array(
                'message' => __('Please enter a message.', 'ai-website-chatbot')
            ));
            return;
        }

        // Check message length
        $max_length = get_option('ai_chatbot_max_message_length', 1000);
        if (strlen($message) > $max_length) {
            wp_send_json_error(array(
                'message' => sprintf(__('Message too long. Maximum %d characters allowed.', 'ai-website-chatbot'), $max_length)
            ));
            return;
        }

        // Generate session ID if not provided
        if (empty($session_id)) {
            $session_id = 'session_' . uniqid();
        }

        // Generate conversation ID if not provided
        if (empty($conversation_id)) {
            $conversation_id = 'conv_' . uniqid();
        }

        try {
            // Get AI provider
            $provider = $this->get_ai_provider();
            
            if (is_wp_error($provider)) {
                error_log('AI Chatbot Error: Provider initialization failed - ' . $provider->get_error_message());
                wp_send_json_error(array(
                    'message' => __('AI service is currently unavailable. Please check your API configuration in the settings.', 'ai-website-chatbot'),
                    'error_details' => $provider->get_error_message()
                ));
                return;
            }

            // Check if provider is configured
            if (!$provider->is_configured()) {
                error_log('AI Chatbot Error: Provider not configured');
                wp_send_json_error(array(
                    'message' => __('AI service is not properly configured. Please check your API key in the settings.', 'ai-website-chatbot')
                ));
                return;
            }

            // Save user message to database
            $this->save_message($session_id, $conversation_id, $message, 'user', $page_url);

            // Get conversation history for context
            $conversation_history = $this->get_conversation_history($session_id, $conversation_id);

            // Generate AI response
            error_log('AI Chatbot: Generating AI response');
            $ai_response = $provider->generate_response($message, $conversation_history);

            if (is_wp_error($ai_response)) {
                error_log('AI Chatbot Error: Failed to generate response - ' . $ai_response->get_error_message());
                wp_send_json_error(array(
                    'message' => __('Failed to get AI response. Please check your API key and try again.', 'ai-website-chatbot'),
                    'error_details' => $ai_response->get_error_message()
                ));
                return;
            }

            // Save AI response to database
            $this->save_message($session_id, $conversation_id, $ai_response, 'bot', $page_url);

            // Return success response
            wp_send_json_success(array(
                'response' => $ai_response,
                'session_id' => $session_id,
                'conversation_id' => $conversation_id,
                'timestamp' => current_time('timestamp')
            ));

        } catch (Exception $e) {
            error_log('AI Chatbot Error: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An error occurred while processing your message. Please try again.', 'ai-website-chatbot'),
                'error_details' => $e->getMessage()
            ));
        }
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
        $rate_limit_enabled = get_option('ai_chatbot_enable_rate_limiting', 'yes');
        
        if ($rate_limit_enabled !== 'yes') {
            return true;
        }
        
        $max_requests = intval(get_option('ai_chatbot_rate_limit_max_requests', 10));
        $time_window = intval(get_option('ai_chatbot_rate_limit_time_window', 60));
        
        $transient_key = 'ai_chatbot_rate_' . md5($identifier);
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
     * Save message to database
     *
     * @param string $session_id
     * @param string $conversation_id
     * @param string $message
     * @param string $sender
     * @param string $page_url
     * @return int|false
     * @since 1.0.0
     */
    private function save_message($session_id, $conversation_id, $message, $sender, $page_url = '') {
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
                'message' => $message,
                'sender' => $sender,
                'page_url' => $page_url,
                'timestamp' => current_time('mysql')
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
			id bigint(20) NOT NULL AUTO_INCREMENT,
			session_id varchar(255) NOT NULL,
			conversation_id varchar(255) NOT NULL,
			message text NOT NULL,
			sender varchar(20) NOT NULL DEFAULT 'user',
			page_url varchar(255) DEFAULT '',
			timestamp datetime DEFAULT CURRENT_TIMESTAMP,
			user_message longtext DEFAULT NULL,
			bot_response longtext DEFAULT NULL,
			rating tinyint(1) DEFAULT NULL,
			status varchar(20) DEFAULT 'completed',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_session_id (session_id),
			KEY idx_conversation_id (conversation_id),
			KEY idx_timestamp (timestamp),
			KEY idx_sender (sender)
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
    private function get_conversation_history($session_id, $conversation_id = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return '';
        }
        
        $where = $wpdb->prepare("WHERE session_id = %s", $session_id);
        
        if (!empty($conversation_id)) {
            $where .= $wpdb->prepare(" AND conversation_id = %s", $conversation_id);
        }
        
        $results = $wpdb->get_results(
            "SELECT message, sender FROM $table_name 
             $where 
             ORDER BY timestamp DESC 
             LIMIT 10"
        );
        
        if (empty($results)) {
            return '';
        }
        
        $history = array();
        foreach (array_reverse($results) as $row) {
            $history[] = ucfirst($row->sender) . ': ' . $row->message;
        }
        
        return implode("\n", $history);
    }

    /**
     * Get conversation starters
     *
     * @return array
     * @since 1.0.0
     */
    private function get_conversation_starters() {
        $default_starters = array(
            __('How can I help you?', 'ai-website-chatbot'),
            __('What information are you looking for?', 'ai-website-chatbot'),
            __('Do you have any questions?', 'ai-website-chatbot'),
            __('How can I assist you today?', 'ai-website-chatbot')
        );
        
        $custom_starters = get_option('ai_chatbot_conversation_starters', '');
        
        if (!empty($custom_starters)) {
            $starters = explode("\n", $custom_starters);
            return array_map('trim', $starters);
        }
        
        return $default_starters;
    }

    /**
     * Check AI service status
     *
     * @return bool
     * @since 1.0.0
     */
    private function check_ai_service_status() {
        $provider = $this->get_ai_provider();
        
        if (is_wp_error($provider)) {
            return false;
        }
        
        if (!$provider->is_configured()) {
            return false;
        }
        
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