<?php
/**
 * Updated AJAX Handler Class for AI Website Chatbot
 * Complete implementation with proper session management
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class AI_Chatbot_Ajax {
    
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Initialize AJAX hooks
        $this->init_ajax_hooks();
    }

    /**
     * Initialize AJAX hooks
     */
    private function init_ajax_hooks() {
        // Main chat message handler - both logged in and non-logged in users
        add_action('wp_ajax_ai_chatbot_send_message', array($this, 'handle_send_message'));
        add_action('wp_ajax_nopriv_ai_chatbot_send_message', array($this, 'handle_send_message'));

        add_action('wp_ajax_ai_chatbot_save_user_data', array($this, 'ajax_save_user_data'));
        add_action('wp_ajax_nopriv_ai_chatbot_save_user_data', array($this, 'ajax_save_user_data'));
        
        // Additional AJAX handlers
        add_action('wp_ajax_ai_chatbot_rating', array($this, 'handle_rating'));
        add_action('wp_ajax_nopriv_ai_chatbot_rating', array($this, 'handle_rating'));
        
        add_action('wp_ajax_ai_chatbot_clear_conversation', array($this, 'handle_clear_conversation'));
        add_action('wp_ajax_nopriv_ai_chatbot_clear_conversation', array($this, 'handle_clear_conversation'));
        
        add_action('wp_ajax_ai_chatbot_get_history', array($this, 'handle_get_history'));
        add_action('wp_ajax_nopriv_ai_chatbot_get_history', array($this, 'handle_get_history'));
        
        add_action('wp_ajax_ai_chatbot_export_data', array($this, 'handle_export_data'));
        add_action('wp_ajax_nopriv_ai_chatbot_export_data', array($this, 'handle_export_data'));
    }

    /**
     * Handle chat message AJAX request
     */
    public function handle_send_message() {
        $start_time = microtime(true);

        // Verify nonce
        if (!check_ajax_referer('ai_chatbot_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'ai-website-chatbot'),
                'code' => 'NONCE_FAILED'
            ));
            return;
        }

        // Get and validate input
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error(array(
                'message' => __('Message cannot be empty', 'ai-website-chatbot'),
                'code' => 'EMPTY_MESSAGE'
            ));
            return;
        }

        // Get user ID from session
        $user_id = $this->get_current_user_id();
        
        // IMPORTANT: Block messages if user is not identified
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('Please provide your email to start chatting', 'ai-website-chatbot'),
                'code' => 'USER_IDENTIFICATION_REQUIRED',
                'require_user_data' => true,
                'show_form' => true
            ));
            return;
        }

        // Continue with rest of your existing code...
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        // Generate IDs if not provided
        if (empty($conversation_id)) {
            $conversation_id = 'conv_' . time() . '_' . wp_generate_password(12, false);
        }
        if (empty($session_id)) {
            $session_id = $this->generate_session_id();
        }

        // Rate limiting check
        if (!$this->check_rate_limits()) {
            wp_send_json_error(array(
                'message' => __('Too many requests. Please wait a moment.', 'ai-website-chatbot'),
                'code' => 'RATE_LIMITED'
            ));
            return;
        }

        // Get user data
        $user_data = $this->get_user_data($user_id);
        if (!$user_data) {
            wp_send_json_error(array(
                'message' => __('User data not found. Please refresh and try again.', 'ai-website-chatbot'),
                'code' => 'USER_DATA_NOT_FOUND'
            ));
            return;
        }

        // Get AI provider and settings
        $settings = get_option('ai_chatbot_settings', array());
        $provider_name = $settings['ai_provider'] ?? 'openai';
        
        // Load the AI provider
        $provider = $this->get_ai_provider($provider_name);
        
        if (!$provider || !$provider->is_configured()) {
            wp_send_json_error(array(
                'message' => __('AI service is not configured properly. Please contact the administrator.', 'ai-website-chatbot'),
                'code' => 'PROVIDER_NOT_CONFIGURED'
            ));
            return;
        }

        // Increment user message count
        $this->increment_user_message_count($user_id);

        // Save user message to database - NOW WITH VALID USER_ID
        $user_message_data = array(
            'session_id' => $session_id,
            'conversation_id' => $conversation_id,
            'user_id' => $user_id, // This is now guaranteed to be valid
            'user_message' => $message,
            'ai_response' => null,
            'user_name' => $user_data['name'] ?? '',
            'user_email' => $user_data['email'] ?? '',
            'user_ip' => $this->get_client_ip(),
            'page_url' => $page_url,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'provider' => $provider_name,
            'status' => 'processing'
        );

        $message_id = $this->save_message($user_message_data);
        
        if (!$message_id) {
            wp_send_json_error(array(
                'message' => __('Failed to save message. Please try again.', 'ai-website-chatbot'),
                'code' => 'SAVE_FAILED'
            ));
            return;
        }


        $message_id = $this->save_message($user_message_data);
        
        if (!$message_id) {
            error_log('AI Chatbot: Failed to save user message to database');
        }

        // Get website content for context
        $context = $this->get_website_context($page_url);
        
        // Add user context if available
        if ($user_data) {
            $user_context = "\n\nUser Information:\n";
            $user_context .= "- Name: " . ($user_data['name'] ?: 'Not provided') . "\n";
            $user_context .= "- Email: " . $user_data['email'] . "\n";
            $user_context .= "- Previous conversations: " . $user_data['total_conversations'] . "\n";
            $user_context .= "- Total messages: " . $user_data['total_messages'] . "\n";
            
            if ($user_data['total_conversations'] > 0) {
                $user_context .= "\nThis is a returning user. You can reference their previous interactions for context.";
            } else {
                $user_context .= "\nThis is a new user's first conversation.";
            }
            
            $context .= $user_context;
        }

        // Generate AI response
        $ai_response = $provider->generate_response($message, $context, array(
            'session_id' => $session_id,
            'conversation_id' => $conversation_id,
            'user_id' => $user_id,
            'max_tokens' => intval($settings['max_tokens'] ?? 300),
            'temperature' => floatval($settings['temperature'] ?? 0.7)
        ));

        $response_time = microtime(true) - $start_time;

        if (is_wp_error($ai_response)) {
            error_log('AI Chatbot: AI Response Error - ' . $ai_response->get_error_message());
            
            // Update message status in database
            if ($message_id) {
                $this->update_message_status($message_id, 'failed', $ai_response->get_error_message());
            }
            
            wp_send_json_error(array(
                'message' => $ai_response->get_error_message(),
                'code' => $ai_response->get_error_code(),
                'session_id' => $session_id,
                'conversation_id' => $conversation_id
            ));
            return;
        }

        // Extract response data
        $response_text = $ai_response['response'] ?? '';
        $tokens_used = $ai_response['tokens_used'] ?? 0;
        $source = $ai_response['source'] ?? 'api';
        $model = $ai_response['model'] ?? 'unknown';

        // Update message with AI response
        if ($message_id) {
            $this->update_message_with_response($message_id, $response_text, $tokens_used, $response_time, $model);
        }

        // Cache response if enabled
        if ($settings['cache_responses'] ?? false) {
            $this->cache_response($message, $response_text);
        }

        // Send success response
        wp_send_json_success(array(
            'response' => $response_text,
            'conversation_id' => $conversation_id,
            'session_id' => $session_id,
            'user_id' => $user_id,
            'tokens_used' => $tokens_used,
            'response_time' => round($response_time, 3),
            'source' => $source,
            'model' => $model,
            'user_name' => $user_data['name'] ?? null
        ));
    }

    /**
     * Handle rating submission
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

        $result = $this->save_rating($conversation_id, $rating);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Thank you for your feedback!', 'ai-website-chatbot')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to save rating.', 'ai-website-chatbot')
            ));
        }
    }

    /**
     * Handle clear conversation
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

        // Generate new session ID
        $new_session_id = $this->get_session_id();

        wp_send_json_success(array(
            'new_session_id' => $new_session_id,
            'message' => __('Conversation cleared.', 'ai-website-chatbot')
        ));
    }

    /**
     * Handle get conversation history
     */
    public function handle_get_history() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_success(array('messages' => array()));
            return;
        }

        $history = $this->get_conversation_history($session_id, 10);

        wp_send_json_success(array(
            'messages' => $history
        ));
    }

    /**
     * Handle export conversation data
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

    // ==========================================
    // SESSION MANAGEMENT METHODS
    // ==========================================

    /**
     * Get or generate session ID
     */
    private function get_session_id() {
        // Check if session ID exists in cookie
        $session_id = isset($_COOKIE['ai_chatbot_session']) ? sanitize_text_field($_COOKIE['ai_chatbot_session']) : '';
        
        // Validate existing session ID
        if (!empty($session_id) && strlen($session_id) >= 20) {
            return $session_id;
        }
        
        // Generate new session ID using security class
        $security = new AI_Chatbot_Security();
        $session_id = $security->generate_session_id();
        
        // Set cookie (valid for 7 days)
        if (!headers_sent()) {
            setcookie('ai_chatbot_session', $session_id, time() + (7 * 24 * 60 * 60), '/', '', is_ssl(), true);
        }
        
        return $session_id;
    }

    /**
     * Generate conversation ID
     */
    private function generate_conversation_id() {
        return 'conv_' . time() . '_' . wp_generate_password(10, false, false);
    }

    /**
     * Get user identifier for rate limiting and analytics
     */
    private function get_user_identifier() {
        // Prefer user ID if logged in
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        // Fallback to session ID
        $session_id = $this->get_session_id();
        return 'session_' . $session_id;
    }

    // ==========================================
    // AI PROVIDER METHODS
    // ==========================================

    /**
     * Get AI provider instance
     */
    private function get_ai_provider($provider_name) {
        if (!$provider_name) {
            $settings = get_option('ai_chatbot_settings', array());
            $provider_name = $settings['ai_provider'] ?? 'openai';
        }
        try{

            switch ($provider_name) {
                case 'openai':
                    if (class_exists('AI_Chatbot_OpenAI')) {
                        return new AI_Chatbot_OpenAI();
                    }
                    break;
                case 'claude':
                    if (class_exists('AI_Chatbot_Claude')) {
                        return new AI_Chatbot_Claude();
                    }
                    break;
                case 'gemini':
                    if (class_exists('AI_Chatbot_Gemini')) {
                        return new AI_Chatbot_Gemini();
                    }
                    break;
                case 'custom':
                    if (class_exists('AI_Chatbot_Custom')) {
                        return new AI_Chatbot_Custom();
                    }
                    break;
            }
            

        }catch (Exception $e){
            error_log('AI Chatbot: Failed to create provider instance: ' . $e->getMessage());
            return null;
        }
        switch ($provider_name) {
            case 'openai':
                if (class_exists('AI_Chatbot_OpenAI')) {
                    return new AI_Chatbot_OpenAI();
                }
                break;
            case 'claude':
                if (class_exists('AI_Chatbot_Claude')) {
                    return new AI_Chatbot_Claude();
                }
                break;
            case 'gemini':
                if (class_exists('AI_Chatbot_Gemini')) {
                    return new AI_Chatbot_Gemini();
                }
                break;
            case 'custom':
                if (class_exists('AI_Chatbot_Custom')) {
                    return new AI_Chatbot_Custom();
                }
                break;
        }
        
        return null;
    }

    /**
     * Get website context for AI
     */
    private function get_website_context($page_url) {
        $context = '';
        
        // Get page/post content if URL is provided
        if (!empty($page_url)) {
            $post_id = url_to_postid($page_url);
            if ($post_id) {
                $post = get_post($post_id);
                if ($post) {
                    $context = $post->post_title . "\n\n" . wp_strip_all_tags($post->post_content);
                    $context = wp_trim_words($context, 500);
                }
            }
        }
        
        // Add site info
        $site_context = get_bloginfo('name') . ' - ' . get_bloginfo('description');
        $context = $site_context . "\n\n" . $context;
        
        return $context;
    }

    // ==========================================
    // DATABASE METHODS
    // ==========================================

    /**
     * Save message to database
     */
    private function save_message($message_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'session_id' => $message_data['session_id'],
                'conversation_id' => $message_data['conversation_id'],
                'user_id' => $message_data['user_id'],
                'user_message' => $message_data['user_message'],
                'ai_response' => $message_data['ai_response'],
                'user_name' => $message_data['user_name'],
                'user_email' => $message_data['user_email'],
                'user_ip' => $message_data['user_ip'],
                'page_url' => $message_data['page_url'],
                'user_agent' => $message_data['user_agent'],
                'provider' => $message_data['provider'],
                'status' => $message_data['status'],
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('Database error: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Update message with AI response
     */
    private function update_message_with_response($message_id, $response, $tokens_used = 0, $response_time = 0, $model = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $update_data = array(
            'ai_response' => $response,
            'status' => 'completed',
            'updated_at' => current_time('mysql')
        );
        
        // Add tokens used if provided
        if (!empty($tokens_used) && is_numeric($tokens_used)) {
            $update_data['tokens_used'] = intval($tokens_used);
        }
        
        // Add response time if provided
        if (!empty($response_time) && is_numeric($response_time)) {
            $update_data['response_time'] = floatval($response_time);
        }
        
        // Add model if provided
        if (!empty($model)) {
            $update_data['model'] = sanitize_text_field($model);
        }
        
        // Build format array dynamically
        $format_array = array();
        foreach ($update_data as $value) {
            if (is_int($value)) {
                $format_array[] = '%d';
            } elseif (is_float($value)) {
                $format_array[] = '%f';
            } else {
                $format_array[] = '%s';
            }
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $message_id),
            $format_array,
            array('%d')
        );
        
        if ($result === false) {
            error_log('AI Chatbot: Failed to update message. Database error: ' . $wpdb->last_error);
        }
        
        return $result;
    }

    /**
     * Update message status
     */
    private function update_message_status($message_id, $status, $error_message = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if (!empty($error_message)) {
            $update_data['error_message'] = $error_message;
        }
        
        return $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $message_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Get conversation history
     */
    private function get_conversation_history($session_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT user_message, ai_response, created_at 
             FROM $table_name 
             WHERE session_id = %s 
             AND ai_response IS NOT NULL 
             AND ai_response != ''
             AND status = 'completed'
             ORDER BY created_at DESC 
             LIMIT %d",
            $session_id,
            $limit
        ), ARRAY_A);
        
        // Format for frontend
        $formatted_history = array();
        foreach (array_reverse($results) as $row) {
            $formatted_history[] = array(
                'id' => uniqid(),
                'sender' => 'user',
                'message' => $row['user_message'],
                'timestamp' => strtotime($row['created_at']) * 1000
            );
            $formatted_history[] = array(
                'id' => uniqid(),
                'sender' => 'bot',
                'message' => $row['ai_response'],
                'timestamp' => strtotime($row['created_at']) * 1000
            );
        }
        
        return $formatted_history;
    }

    /**
     * Clear conversation history
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
     * Export conversation data
     */
    private function export_conversation_data($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s ORDER BY created_at ASC",
            $session_id
        ), ARRAY_A);
        
        return array(
            'session_id' => $session_id,
            'export_date' => current_time('mysql'),
            'total_conversations' => count($conversations),
            'conversations' => $conversations
        );
    }

    /**
     * Save rating
     */
    private function save_rating($conversation_id, $rating) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        return $wpdb->update(
            $table_name,
            array('rating' => $rating),
            array('conversation_id' => $conversation_id),
            array('%d'),
            array('%s')
        );
    }

    // ==========================================
    // SECURITY & VALIDATION METHODS
    // ==========================================

    /**
     * Check rate limits for user requests
     *
     * @return bool True if within limits, false if rate limited
     */
    private function check_rate_limits() {
        $settings = get_option('ai_chatbot_settings', array());
        
        // Check if rate limiting is enabled
        if (!isset($settings['rate_limiting']['enabled']) || !$settings['rate_limiting']['enabled']) {
            return true; // Rate limiting disabled, allow request
        }

        $user_ip = $this->get_client_ip();
        $current_time = time();
        
        // Get rate limiting settings
        $max_requests = intval($settings['rate_limiting']['max_requests'] ?? 10);
        $time_window = intval($settings['rate_limiting']['time_window'] ?? 60); // seconds
        
        // Use transients to track requests per IP
        $transient_key = 'ai_chatbot_rate_limit_' . md5($user_ip);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            // First request in this time window
            set_transient($transient_key, 1, $time_window);
            return true;
        }
        
        if ($requests >= $max_requests) {
            error_log('AI Chatbot: Rate limit exceeded for IP: ' . $user_ip);
            return false;
        }
        
        // Increment request count
        set_transient($transient_key, $requests + 1, $time_window);
        return true;
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip_list = explode(',', $_SERVER[$key]);
                $ip = trim($ip_list[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if message is spam
     */
    private function is_message_spam($message) {
        $security = new AI_Chatbot_Security();
        return $security->is_spam_message($message);
    }

    // ==========================================
    // ANALYTICS METHODS
    // ==========================================

    /**
     * Track conversation analytics
     */
    private function track_conversation_analytics($session_id, $conversation_id, $data) {
        if (class_exists('AI_Chatbot_Analytics')) {
            $analytics = new AI_Chatbot_Analytics();
            $analytics->record_event(array(
                'event_type' => 'conversation_completed',
                'session_id' => $session_id,
                'conversation_id' => $conversation_id,
                'event_data' => $data
            ));
        }
    }

    // ==========================================
    // UTILITY & HELPER METHODS
    // ==========================================

    /**
     * Check AI service status
     */
    private function check_ai_service_status() {
        $settings = get_option('ai_chatbot_settings', array());
        $provider_name = $settings['ai_provider'] ?? 'openai';
        
        $provider = $this->get_ai_provider($provider_name);
        
        if (!$provider || !$provider->is_configured()) {
            return false;
        }
        
        // Test connection with a simple ping
        $test_result = $provider->test_connection();
        
        return !is_wp_error($test_result);
    }

    /**
     * Get conversation starters/suggestions
     */
    private function get_conversation_starters() {
        $default_suggestions = array(
            __('What services do you offer?', 'ai-website-chatbot'),
            __('How can I contact you?', 'ai-website-chatbot'),
            __('Tell me about your company', 'ai-website-chatbot'),
            __('What are your business hours?', 'ai-website-chatbot')
        );
        
        // Get custom suggestions from settings
        $custom_suggestions = get_option('ai_chatbot_conversation_starters', array());
        
        if (!empty($custom_suggestions) && is_array($custom_suggestions)) {
            return array_merge($custom_suggestions, $default_suggestions);
        }
        
        return $default_suggestions;
    }

    /**
     * Sanitize and validate message content
     */
    private function sanitize_message($message) {
        // Remove dangerous HTML tags
        $message = wp_kses($message, array(
            'br' => array(),
            'p' => array(),
            'strong' => array(),
            'em' => array()
        ));
        
        // Normalize whitespace
        $message = preg_replace('/\s+/', ' ', $message);
        
        // Trim and sanitize
        $message = sanitize_textarea_field(trim($message));
        
        return $message;
    }

    /**
     * Log conversation for debugging
     */
    private function log_conversation($session_id, $message, $response, $context = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'session_id' => $session_id,
            'message' => substr($message, 0, 100) . '...',
            'response_length' => strlen($response),
            'context' => $context
        );
        
        error_log('AI Chatbot Conversation: ' . wp_json_encode($log_data));
    }

    /**
     * Get user preferences
     */
    private function get_user_preferences($user_identifier) {
        $preferences = get_transient('ai_chatbot_user_prefs_' . md5($user_identifier));
        
        if (!$preferences) {
            $preferences = array(
                'language' => get_locale(),
                'theme' => 'default',
                'notifications' => true
            );
        }
        
        return $preferences;
    }

    /**
     * Save user preferences
     */
    private function save_user_preferences($user_identifier, $preferences) {
        $cache_key = 'ai_chatbot_user_prefs_' . md5($user_identifier);
        set_transient($cache_key, $preferences, DAY_IN_SECONDS);
    }

    /**
     * Check if chatbot is enabled
     */
    private function is_chatbot_enabled() {
        $settings = get_option('ai_chatbot_settings', array());
        return isset($settings['enabled']) ? (bool) $settings['enabled'] : true;
    }

    /**
     * Check maintenance mode
     */
    private function is_maintenance_mode() {
        return get_option('ai_chatbot_maintenance_mode', false);
    }

    /**
     * Get chatbot working hours
     */
    private function is_within_working_hours() {
        $settings = get_option('ai_chatbot_settings', array());
        
        if (!isset($settings['enable_working_hours']) || !$settings['enable_working_hours']) {
            return true; // Always available if working hours not enabled
        }
        
        $timezone = get_option('timezone_string') ?: 'UTC';
        $current_time = new DateTime('now', new DateTimeZone($timezone));
        $current_hour = (int) $current_time->format('H');
        $current_day = (int) $current_time->format('N'); // 1 = Monday, 7 = Sunday
        
        $start_hour = (int) ($settings['working_hours_start'] ?? 9);
        $end_hour = (int) ($settings['working_hours_end'] ?? 17);
        $working_days = $settings['working_days'] ?? array(1, 2, 3, 4, 5); // Mon-Fri
        
        // Check if current day is a working day
        if (!in_array($current_day, $working_days)) {
            return false;
        }
        
        // Check if current hour is within working hours
        return ($current_hour >= $start_hour && $current_hour < $end_hour);
    }

    /**
     * Handle file upload (if enabled)
     */
    private function handle_file_upload() {
        if (!isset($_FILES['file']) || !$this->is_file_upload_enabled()) {
            return false;
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        $validation = $this->validate_uploaded_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Process file
        $upload_result = $this->process_uploaded_file($file);
        
        return $upload_result;
    }

    /**
     * Check if file upload is enabled
     */
    private function is_file_upload_enabled() {
        $settings = get_option('ai_chatbot_settings', array());
        return isset($settings['enable_file_upload']) ? (bool) $settings['enable_file_upload'] : false;
    }

    /**
     * Validate uploaded file
     */
    private function validate_uploaded_file($file) {
        $max_size = 2 * 1024 * 1024; // 2MB
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx');
        
        // Check file size
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('File is too large. Maximum size is 2MB.', 'ai-website-chatbot'));
        }
        
        // Check file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            return new WP_Error('invalid_file_type', __('File type not allowed.', 'ai-website-chatbot'));
        }
        
        return true;
    }

    /**
     * Process uploaded file
     */
    private function process_uploaded_file($file) {
        // Handle the upload using WordPress functions
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $upload_overrides = array('test_form' => false);
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            return new WP_Error('upload_failed', $uploaded_file['error']);
        }
        
        return $uploaded_file;
    }

    /**
     * Generate response summary for analytics
     */
    private function generate_response_summary($response) {
        return array(
            'word_count' => str_word_count(strip_tags($response)),
            'character_count' => strlen($response),
            'has_links' => preg_match('/<a\s+.*?>.*?<\/a>/i', $response) ? true : false,
            'has_formatting' => preg_match('/<(strong|em|b|i)\s*>.*?<\/\1>/i', $response) ? true : false
        );
    }

    /**
     * Check user permissions for specific actions
     */
    private function check_user_permissions($action) {
        switch ($action) {
            case 'send_message':
                return true; // All users can send messages
                
            case 'export_data':
                // Only logged-in users or session owners can export
                return is_user_logged_in() || !empty($_POST['session_id']);
                
            case 'clear_conversation':
                return true; // All users can clear their own conversations
                
            case 'rate_message':
                return true; // All users can rate messages
                
            default:
                return false;
        }
    }

    /**
     * Get localized strings for JavaScript
     */
    private function get_localized_strings() {
        return array(
            'sending' => __('Sending...', 'ai-website-chatbot'),
            'error' => __('Something went wrong. Please try again.', 'ai-website-chatbot'),
            'network_error' => __('Network error. Please check your connection.', 'ai-website-chatbot'),
            'thinking' => __('AI is thinking...', 'ai-website-chatbot'),
            'typing' => __('AI is typing...', 'ai-website-chatbot'),
            'message_too_long' => __('Message is too long.', 'ai-website-chatbot'),
            'empty_message' => __('Please enter a message.', 'ai-website-chatbot'),
            'thank_you' => __('Thank you for your feedback!', 'ai-website-chatbot'),
            'confirm_clear' => __('Are you sure you want to clear the conversation?', 'ai-website-chatbot'),
            'conversation_cleared' => __('Conversation cleared.', 'ai-website-chatbot'),
            'copy_success' => __('Copied to clipboard!', 'ai-website-chatbot'),
            'copy_failed' => __('Failed to copy to clipboard.', 'ai-website-chatbot'),
            'offline_message' => __('Chatbot is currently offline. Please try again later.', 'ai-website-chatbot'),
            'maintenance_message' => __('Chatbot is under maintenance. Please try again later.', 'ai-website-chatbot')
        );
    }

    /**
     * Handle emergency shutdown
     */
    private function handle_emergency_shutdown() {
        $emergency_mode = get_option('ai_chatbot_emergency_mode', false);
        
        if ($emergency_mode) {
            wp_send_json_error(array(
                'message' => __('Chatbot service is temporarily unavailable. Please try again later.', 'ai-website-chatbot'),
                'code' => 'EMERGENCY_SHUTDOWN'
            ));
            return;
        }
    }

    /**
     * Clean old conversations (cleanup job)
     */
    public function cleanup_old_conversations() {
        global $wpdb;
        
        $retention_days = get_option('ai_chatbot_data_retention_days', 30);
        
        if ($retention_days <= 0) {
            return; // Don't delete if retention is disabled
        }
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ));
        
        if ($deleted !== false) {
            error_log("AI Chatbot: Cleaned up {$deleted} old conversations");
        }
    }

    /**
     * Get system status for debugging
     */
    private function get_system_status() {
        return array(
            'plugin_version' => AI_CHATBOT_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'database_version' => $this->get_database_version(),
            'ssl_enabled' => is_ssl(),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
        );
    }

    /**
     * Get database version
     */
    private function get_database_version() {
        global $wpdb;
        return $wpdb->get_var("SELECT VERSION()");
    }

    /**
     * Handle webhook requests (for future integrations)
     */
    public function handle_webhook() {
        // Verify webhook signature
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
        $payload = file_get_contents('php://input');
        
        if (!$this->verify_webhook_signature($signature, $payload)) {
            http_response_code(401);
            exit('Unauthorized');
        }
        
        // Process webhook data
        $data = json_decode($payload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            exit('Invalid JSON');
        }
        
        // Handle different webhook types
        $this->process_webhook_data($data);
        
        http_response_code(200);
        exit('OK');
    }

    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($signature, $payload) {
        $webhook_secret = get_option('ai_chatbot_webhook_secret', '');
        
        if (empty($webhook_secret)) {
            return false;
        }
        
        $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
        
        return hash_equals($signature, $expected_signature);
    }

    /**
     * Process webhook data
     */
    private function process_webhook_data($data) {
        $event_type = $data['event'] ?? '';
        
        switch ($event_type) {
            case 'model_update':
                $this->handle_model_update($data);
                break;
                
            case 'usage_alert':
                $this->handle_usage_alert($data);
                break;
                
            case 'system_notification':
                $this->handle_system_notification($data);
                break;
        }
    }

    /**
     * Handle model update webhook
     */
    private function handle_model_update($data) {
        // Update model information in database
        $model_info = $data['model'] ?? array();
        update_option('ai_chatbot_latest_model_info', $model_info);
        
        // Log the update
        error_log('AI Chatbot: Model updated - ' . wp_json_encode($model_info));
    }

    /**
     * Handle usage alert webhook
     */
    private function handle_usage_alert($data) {
        $usage_data = $data['usage'] ?? array();
        
        // Send notification to admin if usage is high
        if (isset($usage_data['percentage']) && $usage_data['percentage'] > 80) {
            $this->send_usage_notification($usage_data);
        }
    }

    /**
     * Handle system notification webhook
     */
    private function handle_system_notification($data) {
        $notification = $data['notification'] ?? '';
        $level = $data['level'] ?? 'info';
        
        // Store notification for admin
        $notifications = get_option('ai_chatbot_notifications', array());
        $notifications[] = array(
            'message' => $notification,
            'level' => $level,
            'timestamp' => current_time('mysql')
        );
        
        // Keep only last 50 notifications
        $notifications = array_slice($notifications, -50);
        update_option('ai_chatbot_notifications', $notifications);
    }

    /**
     * Send usage notification to admin
     */
    private function send_usage_notification($usage_data) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('[%s] AI Chatbot Usage Alert', 'ai-website-chatbot'), $site_name);
        $message = sprintf(
            __('Your AI Chatbot usage has reached %d%% of your monthly limit. Please monitor your usage to avoid service interruption.', 'ai-website-chatbot'),
            $usage_data['percentage']
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    /**
     * Handle user data collection from pre-chat form
     */
    public function ajax_save_user_data() {
        try {
            // Verify nonce
            if (!check_ajax_referer('ai_chatbot_nonce', 'nonce', false)) {
                wp_send_json_error(array(
                    'message' => __('Security check failed', 'ai-website-chatbot'),
                    'code' => 'NONCE_FAILED'
                ));
                return;
            }

            // Get form data
            $user_email = sanitize_email($_POST['user_email'] ?? '');
            $user_name = sanitize_text_field($_POST['user_name'] ?? '');
            $page_url = esc_url_raw($_POST['page_url'] ?? '');
            $user_agent = sanitize_text_field($_POST['user_agent'] ?? '');

            // Validate required fields
            if (empty($user_email) || !is_email($user_email)) {
                wp_send_json_error(array(
                    'message' => __('Please enter a valid email address', 'ai-website-chatbot'),
                    'code' => 'INVALID_EMAIL'
                ));
                return;
            }

            // Validate optional name field
            if (!empty($user_name) && (strlen($user_name) < 2 || strlen($user_name) > 50)) {
                wp_send_json_error(array(
                    'message' => __('Name should be between 2-50 characters', 'ai-website-chatbot'),
                    'code' => 'INVALID_NAME'
                ));
                return;
            }

            // Check if user collection is enabled
            $settings = get_option('ai_chatbot_settings', array());
            $user_collection_enabled = isset($settings['user_collection']['enabled']) ? 
                                    (bool) $settings['user_collection']['enabled'] : true;

            if (!$user_collection_enabled) {
                wp_send_json_error(array(
                    'message' => __('User collection is currently disabled', 'ai-website-chatbot'),
                    'code' => 'FEATURE_DISABLED'
                ));
                return;
            }

            // Save or update user data
            $user_data = $this->save_user_data($user_email, $user_name, $page_url, $user_agent);

            if (is_wp_error($user_data)) {
                wp_send_json_error(array(
                    'message' => $user_data->get_error_message(),
                    'code' => $user_data->get_error_code()
                ));
                return;
            }

            // Generate session ID for this interaction
            $session_id = $this->generate_session_id();
            
            // Store user ID in session for this conversation
            if (!session_id()) {
                session_start();
            }
            $_SESSION['ai_chatbot_user_id'] = $user_data['user_id'];
            $_SESSION['ai_chatbot_session_id'] = $session_id;

            // Return success response
            wp_send_json_success(array(
                'user_id' => $user_data['user_id'],
                'session_id' => $session_id,
                'message' => __('User information saved successfully', 'ai-website-chatbot')
            ));

        } catch (Exception $e) {
            error_log('AI Chatbot: User data save error - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An error occurred while saving your information', 'ai-website-chatbot'),
                'code' => 'SERVER_ERROR'
            ));
        }
    }

    /**
     * Save user data to database
     *
     * @param string $email User email
     * @param string $name User name
     * @param string $page_url Current page URL
     * @param string $user_agent User agent string
     * @return array|WP_Error User data or error
     */
    private function save_user_data($email, $name, $page_url, $user_agent) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        $current_time = current_time('mysql');
        $user_ip = $this->get_client_ip();

        // Check if user already exists
        $existing_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$users_table} WHERE email = %s",
            $email
        ));

        if ($existing_user) {
            // Update existing user
            $update_data = array(
                'last_seen' => $current_time,
                'session_count' => $existing_user->session_count + 1,
                'updated_at' => $current_time
            );

            // Update name if provided and different
            if (!empty($name) && $name !== $existing_user->name) {
                $update_data['name'] = $name;
            }

            $updated = $wpdb->update(
                $users_table,
                $update_data,
                array('id' => $existing_user->id),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );

            if ($updated === false) {
                return new WP_Error('update_failed', __('Failed to update user information', 'ai-website-chatbot'));
            }

            return array(
                'user_id' => $existing_user->id,
                'is_returning' => true,
                'session_count' => $existing_user->session_count + 1
            );
        } else {
            // Create new user
            $insert_data = array(
                'email' => $email,
                'name' => $name,
                'first_seen' => $current_time,
                'last_seen' => $current_time,
                'session_count' => 1,
                'total_messages' => 0,
                'total_conversations' => 0,
                'status' => 'active',
                'created_at' => $current_time,
                'updated_at' => $current_time
            );

            $inserted = $wpdb->insert(
                $users_table,
                $insert_data,
                array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s')
            );

            if ($inserted === false) {
                return new WP_Error('insert_failed', __('Failed to save user information', 'ai-website-chatbot'));
            }

            $user_id = $wpdb->insert_id;

            return array(
                'user_id' => $user_id,
                'is_returning' => false,
                'session_count' => 1
            );
        }
    }

    /**
     * Get user data by ID
     *
     * @param int $user_id User ID
     * @return array|null User data or null if not found
     */
    public function get_user_data($user_id) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$users_table} WHERE id = %d AND status = 'active'",
            $user_id
        ), ARRAY_A);

        return $user;
    }

    /**
     * Get user data by email
     *
     * @param string $email User email
     * @return array|null User data or null if not found
     */
    public function get_user_by_email($email) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$users_table} WHERE email = %s AND status = 'active'",
            $email
        ), ARRAY_A);

        return $user;
    }

    /**
     * Update user message count
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function increment_user_message_count($user_id) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$users_table} 
            SET total_messages = total_messages + 1, 
                last_seen = %s,
                updated_at = %s 
            WHERE id = %d",
            current_time('mysql'),
            current_time('mysql'),
            $user_id
        ));

        return $updated !== false;
    }

    /**
     * Update user conversation count
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function increment_user_conversation_count($user_id) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$users_table} 
            SET total_conversations = total_conversations + 1,
                updated_at = %s 
            WHERE id = %d",
            current_time('mysql'),
            $user_id
        ));

        return $updated !== false;
    }

    /**
     * Generate session ID
     *
     * @return string Session ID
     */
    private function generate_session_id() {
        return 'sess_' . time() . '_' . wp_generate_password(12, false);
    }

    /**
     * Get current user ID from session
     *
     * @return int|null User ID or null if not found
     */
    private function get_current_user_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['ai_chatbot_user_id']) ? 
            (int) $_SESSION['ai_chatbot_user_id'] : null;
    }

    /**
     * Get user conversation history for context
     *
     * @param int $user_id User ID
     * @param int $limit Number of recent conversations to fetch
     * @return array Conversation history
     */
    public function get_user_conversation_history($user_id, $limit = 5) {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT conversation_id, user_message, ai_response, created_at 
            FROM {$conversations_table} 
            WHERE user_id = %d AND status = 'completed' 
            ORDER BY created_at DESC 
            LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);

        return $history;
    }

    /**
     * Get user statistics
     *
     * @param int $user_id User ID
     * @return array User statistics
     */
    public function get_user_statistics($user_id) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        $conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Get basic user stats
        $user_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT total_messages, total_conversations, session_count, first_seen, last_seen 
            FROM {$users_table} 
            WHERE id = %d",
            $user_id
        ), ARRAY_A);

        if (!$user_stats) {
            return null;
        }

        // Get conversation stats
        $conversation_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_interactions,
                COUNT(DISTINCT conversation_id) as unique_conversations,
                AVG(response_time) as avg_response_time,
                MAX(created_at) as last_interaction
            FROM {$conversations_table} 
            WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        return array_merge($user_stats, $conversation_stats ?: array());
    }

    /**
     * Update user preferences
     *
     * @param int $user_id User ID
     * @param array $preferences User preferences
     * @return bool Success status
     */
    public function update_user_preferences($user_id, $preferences) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        
        $updated = $wpdb->update(
            $users_table,
            array(
                'preferences' => wp_json_encode($preferences),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $user_id),
            array('%s', '%s'),
            array('%d')
        );

        return $updated !== false;
    }

    /**
     * Anonymize user data (for GDPR compliance)
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function anonymize_user_data($user_id) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        $conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Anonymize user table
        $anonymized_email = 'anonymous_' . $user_id . '@deleted.com';
        $anonymized_name = 'Deleted User';
        
        $user_updated = $wpdb->update(
            $users_table,
            array(
                'email' => $anonymized_email,
                'name' => $anonymized_name,
                'status' => 'anonymized',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $user_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        // Anonymize conversations
        $conversations_updated = $wpdb->update(
            $conversations_table,
            array(
                'user_name' => $anonymized_name,
                'user_email' => $anonymized_email,
                'user_ip' => '0.0.0.0',
                'user_agent' => 'Anonymized'
            ),
            array('user_id' => $user_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        return $user_updated !== false && $conversations_updated !== false;
    }

    /**
     * Delete user data completely (for GDPR compliance)
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function delete_user_data($user_id) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        $conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Delete conversations
        $conversations_deleted = $wpdb->delete(
            $conversations_table,
            array('user_id' => $user_id),
            array('%d')
        );

        // Delete user
        $user_deleted = $wpdb->delete(
            $users_table,
            array('id' => $user_id),
            array('%d')
        );

        return $user_deleted !== false;
    }

    /**
     * Export user data (for GDPR compliance)
     *
     * @param int $user_id User ID
     * @return array|null User data export or null if user not found
     */
    public function export_user_data($user_id) {
        global $wpdb;

        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        $conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Get user data
        $user_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$users_table} WHERE id = %d",
            $user_id
        ), ARRAY_A);

        if (!$user_data) {
            return null;
        }

        // Get conversation data
        $conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT conversation_id, user_message, ai_response, created_at, page_url 
            FROM {$conversations_table} 
            WHERE user_id = %d 
            ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);

        return array(
            'user_information' => $user_data,
            'conversations' => $conversations,
            'export_date' => current_time('mysql'),
            'total_conversations' => count($conversations)
        );
    }

} // End of AI_Chatbot_Ajax class