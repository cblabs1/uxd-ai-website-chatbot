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

        add_action('wp_ajax_ai_chatbot_audio_session_log', array($this, 'handle_audio_session_log'));
        add_action('wp_ajax_nopriv_ai_chatbot_audio_session_log', array($this, 'handle_audio_session_log'));

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

        // Add this line to the constructor
        add_action('wp_ajax_ai_chatbot_conversation_rating', array($this, 'handle_conversation_rating'));
        add_action('wp_ajax_nopriv_ai_chatbot_conversation_rating', array($this, 'handle_conversation_rating'));

        add_action('wp_ajax_ai_chatbot_get_conversation_rating_status', array($this, 'handle_get_conversation_rating_status'));
        add_action('wp_ajax_nopriv_ai_chatbot_get_conversation_rating_status', array($this, 'handle_get_conversation_rating_status'));

    }

    /**
     * Handle chat message AJAX request
     */
    public function handle_send_message() {

        error_log('AI Chatbot: handle_send_message called');
        $start_time = microtime(true);

        $settings = get_option('ai_chatbot_settings', array());
        $provider_name = $settings['ai_provider'] ?? 'openai';
        // Enhanced logging
        error_log('AI Chatbot: handle_send_message called');

        // Verify nonce
        if (!check_ajax_referer('ai_chatbot_nonce', 'nonce', false)) {
            error_log('AI Chatbot: Nonce verification failed');
            wp_send_json_error(array(
                'message' => __('Security check failed', 'ai-website-chatbot'),
                'code' => 'NONCE_FAILED'
            ));
            return;
        }

        // Get and validate input
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $audio_mode = isset($_POST['audio_mode']) && $_POST['audio_mode'] === 'true' ? 1 : 0;
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        $user_name = sanitize_text_field($_POST['user_name'] ?? '');
        $user_id = intval($_POST['user_id'] ?? 0);

        // Enhanced logging
        error_log('AI Chatbot: Message data - Email: ' . $user_email . ', Name: ' . $user_name . ', Message: ' . substr($message, 0, 50) . '...');

        if (empty($message)) {
            wp_send_json_error(array(
                'message' => __('Message cannot be empty', 'ai-website-chatbot'),
                'code' => 'EMPTY_MESSAGE'
            ));
            return;
        }

        // Enhanced email validation
        if (empty($user_email) || !is_email($user_email)) {
            error_log('AI Chatbot: Invalid email provided: ' . $user_email);
            wp_send_json_error(array(
                'message' => __('Valid email is required to send messages. Please refresh and try again.', 'ai-website-chatbot'),
                'code' => 'EMAIL_REQUIRED',
                'debug' => array(
                    'provided_email' => $user_email,
                    'is_valid' => is_email($user_email)
                )
            ));
            return;
        }

        // Get or validate user data
        $user_data = $this->validate_user_session($user_email, $user_name, $session_id, $user_id);
        if (!$user_data) {
            error_log('AI Chatbot: User validation failed');
            wp_send_json_error(array(
                'message' => __('User session invalid. Please refresh and try again.', 'ai-website-chatbot'),
                'code' => 'USER_SESSION_INVALID'
            ));
            return;
        }

        // Validate message length
        $max_length = get_option('ai_chatbot_max_message_length', 1000);
        if (strlen($message) > $max_length) {
            wp_send_json_error(array(
                'message' => sprintf(__('Message too long. Maximum %d characters allowed.', 'ai-website-chatbot'), $max_length),
                'code' => 'MESSAGE_TOO_LONG'
            ));
            return;
        }

        // Rate limiting check (now based on user email)
        if (!$this->check_rate_limit_by_email($user_email)) {
            wp_send_json_error(array(
                'message' => __('Too many messages. Please wait before sending another.', 'ai-website-chatbot'),
                'code' => 'RATE_LIMITED'
            ));
            return;
        }

        // Get AI provider settings
        $ai_provider = $provider_name;
        $model = $settings['model'] ?? 'gpt-3.5-turbo';

        try {
            // Initialize AI provider
            $provider = $this->get_ai_provider($ai_provider);
            
            if (!$provider) {
                throw new Exception('AI provider not available');
            }

            // Get conversation context for this user
            $context = $this->get_user_conversation_context($user_email, $session_id, $conversation_id);
            
            // Send message to AI provider
            $ai_response = $provider->generate_response($message, $context, array(
                'model' => $model,
                'user_data' => $user_data,
                'session_id' => $session_id
            ));

            if (!$ai_response || empty($ai_response['response'])) {
                throw new Exception('Empty response from AI provider');
            }

            $response_text = $ai_response['response'];
            $tokens_used = $ai_response['tokens_used'] ?? 0;
            $model_used = $ai_response['model'] ?? $model;

            // NEW: Save conversation with user email linkage
            $conversation_data = array(
                'session_id' => $session_id,
                'conversation_id' => $conversation_id,
                'user_email' => $user_email,  // NEW: Link to user
                'user_name' => $user_name,    // NEW: Store user name
                'user_id' => $user_data['id'], // NEW: Link to user ID
                'user_message' => $message,
                'ai_response' => $response_text,
                'tokens_used' => $tokens_used,
                'model' => $model_used,
                'audio_mode' => $audio_mode,
                'provider' => $ai_provider,
                'response_time' => microtime(true) - $start_time,
                'user_ip' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            );

            $saved = $this->save_conversation($conversation_data);
            
            // NEW: Update user statistics
            $this->update_user_stats($user_data['id']);
            
            $response_time = microtime(true) - $start_time;

            // Cache response if enabled
            $cache_enabled = $settings['cache_responses'] ?? false;
            if ($cache_enabled) {
                $this->cache_response($message, $response_text);
            }

            $final_response = $audio_mode ? $this->optimize_response_for_audio($response_text) : $response_text;

            // Send success response
            wp_send_json_success(array(
                'response' => $final_response,
                'conversation_id' => $conversation_id,
                'session_id' => $session_id,
                'audio_mode' => $audio_mode,
                'user_id' => $user_data['id'],
                'user_email' => $user_email,
                'user_name' => $user_name,
                'tokens_used' => $tokens_used,
                'response_time' => round($response_time, 3),
                'source' => 'ai_provider',
                'model' => $model_used,
                'cached' => ($cache_enabled ? true : false)
            ));

        } catch (Exception $e) {
            error_log('AI Chatbot Error: ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => __('Sorry, I encountered an error. Please try again.', 'ai-website-chatbot'),
                'code' => 'AI_ERROR',
                'debug' => WP_DEBUG ? $e->getMessage() : null
            ));
        }
    }

    /**
     * Handle audio session logging
     */
    public function handle_audio_session_log() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed', 'code' => 'NONCE_FAILED'));
            return;
        }

        $event = sanitize_text_field($_POST['event'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($event) || empty($session_id)) {
            wp_send_json_error(array('message' => 'Missing parameters', 'code' => 'MISSING_PARAMS'));
            return;
        }

        $logged = $this->log_audio_session($session_id, $event);
        wp_send_json_success(array('logged' => $logged, 'event' => $event));
    }

    /**
     * Log audio session event
     */
    private function log_audio_session($session_id, $event) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_audio_sessions';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'event' => $event,
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'user_ip' => $this->get_client_ip()
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );

        return $result !== false;
    }

    /**
     * Optimize response for audio
     */
    private function optimize_response_for_audio($text) {
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text); // Remove bold
        $text = preg_replace('/\*(.*?)\*/', '$1', $text);     // Remove italic
        $text = preg_replace('/`(.*?)`/', '$1', $text);       // Remove code
        $text = preg_replace('/#+ /', '', $text);             // Remove headers
        $text = preg_replace('/^\s*[-*•]\s+/m', '', $text);   // Remove bullets
        $text = preg_replace('/^\s*\d+\.\s+/m', '', $text);   // Remove numbers
        
        $replacements = array(
            'e.g.' => 'for example',
            'i.e.' => 'that is',
            'etc.' => 'and so on',
        );
        $text = str_replace(array_keys($replacements), array_values($replacements), $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    protected function cache_response($message, $response) {
        $cache_key = 'ai_chatbot_response_' . md5(strtolower(trim($message)));
        $cache_duration = 12 * HOUR_IN_SECONDS; // Cache for 12 hours
        
        set_transient($cache_key, $response, $cache_duration);
    }

    /**
     * NEW: Validate user session method
     */
    private function validate_user_session($email, $name, $session_id, $user_id) {
        // Enhanced logging
        error_log('AI Chatbot: validate_user_session called - Email: ' . $email . ', Session: ' . $session_id);
        
        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check WordPress session first
        if (isset($_SESSION['ai_chatbot_user']) && $_SESSION['ai_chatbot_user']['email'] === $email) {
            error_log('AI Chatbot: Found valid session data for: ' . $email);
            return $_SESSION['ai_chatbot_user'];
        }
        
        // Validate against database - FIXED THE INCOMPLETE QUERY
        global $wpdb;
        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        
        // First try to find user by email
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$users_table} WHERE email = %s AND status = 'active'",
            $email
        ), ARRAY_A);
        
        if ($user) {
            error_log('AI Chatbot: Found user in database: ' . $email);
            
            // Update session data
            $_SESSION['ai_chatbot_user'] = array(
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'session_id' => $session_id
            );
            
            // Update last_seen and session_id in database
            $wpdb->update(
                $users_table,
                array(
                    'last_seen' => current_time('mysql'),
                    'session_id' => $session_id,
                    'updated_at' => current_time('mysql')
                ),
                array('email' => $email),
                array('%s', '%s', '%s'),
                array('%s')
            );
            
            return $_SESSION['ai_chatbot_user'];
        }
        
        // If user not found, try to create/get user data
        error_log('AI Chatbot: User not found in database, attempting to create: ' . $email);
        $user_data = $this->get_or_create_user($email, $name, $session_id);
        
        if ($user_data) {
            error_log('AI Chatbot: Successfully created/retrieved user: ' . $email);
            
            // Store in session
            $_SESSION['ai_chatbot_user'] = array(
                'id' => $user_data['id'],
                'email' => $user_data['email'],
                'name' => $user_data['name'],
                'session_id' => $session_id
            );
            
            return $_SESSION['ai_chatbot_user'];
        }
        
        error_log('AI Chatbot: Failed to validate/create user session for: ' . $email);
        return false;
    }

    /**
     * UPDATE existing save_conversation method to include user data
     */
    private function save_conversation($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
    
        // CHECK FOR DUPLICATES first
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} 
            WHERE conversation_id = %s 
            AND user_message = %s 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $data['conversation_id'],
            $data['user_message']
        ));
        
        // If duplicate found within 1 minute, skip saving
        if ($existing) {
            error_log("AI Chatbot: Duplicate conversation detected, skipping save");
            return $existing;
        }
        
        // Create table if it doesn't exist
        $this->maybe_create_conversations_table();
        
        $result = $wpdb->insert(
            $table_name,
            $data,
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('AI Chatbot: Database insert failed: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }

    /**
     * UPDATE your existing maybe_create_conversations_table method
     */
    private function maybe_create_conversations_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                session_id varchar(255) NOT NULL,
                conversation_id varchar(255) NOT NULL,
                user_email varchar(255) NOT NULL,
                user_name varchar(255) DEFAULT NULL,
                user_id bigint(20) DEFAULT NULL,
                user_message text NOT NULL,
                ai_response text NOT NULL,
                tokens_used int(11) DEFAULT 0,
                model varchar(100) DEFAULT NULL,
                provider varchar(50) DEFAULT NULL,
                response_time float DEFAULT NULL,
                user_ip varchar(45) DEFAULT NULL,
                user_agent text DEFAULT NULL,
                message_rating int(1) DEFAULT NULL,
                message_rated_at datetime DEFAULT NULL,
                rating int(1) DEFAULT NULL,
                feedback text DEFAULT NULL,
                rated_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY session_id (session_id),
                KEY conversation_id (conversation_id),
                KEY user_email (user_email),
                KEY user_id (user_id),
                KEY created_at (created_at),
                FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}ai_chatbot_users(id) ON DELETE SET NULL
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }


    /**
     * Get user conversation context (updated to use email)
     */
    private function get_user_conversation_context($user_email, $session_id, $conversation_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return array();
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT user_message, ai_response, created_at 
            FROM {$table_name} 
            WHERE user_email = %s 
            ORDER BY created_at DESC 
            LIMIT %d",
            $user_email,
            $limit
        ));

        $context = array();
        foreach (array_reverse($results) as $row) {
            $context[] = array(
                'role' => 'user',
                'content' => $row->user_message
            );
            $context[] = array(
                'role' => 'assistant',
                'content' => $row->ai_response
            );
        }

        return $context;
    }

    /**
     * Update user statistics
     */
    private function update_user_stats($user_id) {
        global $wpdb;
        
        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$users_table} 
            SET total_messages = total_messages + 1,
                last_seen = %s,
                updated_at = %s
            WHERE id = %d",
            current_time('mysql'),
            current_time('mysql'),
            $user_id
        ));
    }

    /**
     * Check rate limit by email
     */
    private function check_rate_limit_by_email($user_email) {
        $rate_limit = get_option('ai_chatbot_rate_limit', 60); // messages per hour
        if ($rate_limit <= 0) {
            return true; // No rate limiting
        }

        $cache_key = 'ai_chatbot_rate_limit_email_' . md5($user_email);
        $requests = get_transient($cache_key) ?: 0;

        if ($requests >= $rate_limit) {
            return false;
        }

        set_transient($cache_key, $requests + 1, HOUR_IN_SECONDS);
        return true;
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

        $result = $this->save_message_rating($conversation_id, $rating);

        if ($result) {
            // Log the rating event
            $this->log_message_rating_event($conversation_id, $rating);
            
            wp_send_json_success(array(
                'message' => __('Thank you for your feedback!', 'ai-website-chatbot'),
                'rating' => $rating
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to save rating.', 'ai-website-chatbot')
            ));
        }
    }   

    /**
     * Save message rating to database (ADD THIS)
     */
    private function save_message_rating($conversation_id, $rating) {
        global $wpdb;
        
        // Try to update conversations table if it exists
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            $result = $wpdb->update(
                $table_name,
                array(
                    'message_rating' => $rating,
                    'message_rated_at' => current_time('mysql')
                ),
                array('conversation_id' => $conversation_id),
                array('%d', '%s'),
                array('%s')
            );
            
            if ($result !== false) {
                return true;
            }
        }
        
        // Fallback: save to options table
        $ratings = get_option('ai_chatbot_message_ratings', array());
        $ratings[$conversation_id] = array(
            'rating' => $rating,
            'timestamp' => current_time('mysql'),
            'ip' => $this->get_client_ip()
        );
        
        return update_option('ai_chatbot_message_ratings', $ratings);
    }

    /**
     * Log message rating event for analytics (ADD THIS)
     */
    private function log_message_rating_event($conversation_id, $rating) {
        // Log to WordPress debug log if enabled
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log("AI Chatbot Message Rating: ID={$conversation_id}, Rating={$rating}");
        }
        
        // Hook for external analytics
        do_action('ai_chatbot_message_rated', $conversation_id, $rating);
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
        $user_email = sanitize_email($_POST['user_email'] ?? ''); // NEW
        
        if (!empty($session_id)) {
            $this->clear_conversation_history($session_id, $user_email);
        }

        // Generate new session ID
        $new_session_id = $this->generate_session_id();

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
        $user_email = sanitize_email($_POST['user_email'] ?? ''); // NEW
        $limit = intval($_POST['limit'] ?? 50);

        if (empty($session_id)) {
            wp_send_json_error('Session ID required');
            return;
        }

        $messages = $this->get_conversation_history($session_id, $user_email, $limit);

        wp_send_json_success(array(
            'messages' => $messages,
            'count' => count($messages),
            'session_id' => $session_id
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
        $user_email = sanitize_email($_POST['user_email'] ?? ''); // NEW
        $format = sanitize_text_field($_POST['format'] ?? 'json');

        $data = $this->export_conversation_data($session_id, $user_email, $format);

        if ($data) {
            wp_send_json_success(array(
                'data' => $data,
                'filename' => 'chatbot-conversation-' . date('Y-m-d-H-i-s') . '.' . $format
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No data to export.', 'ai-website-chatbot')
            ));
        }
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
        
        // Set cookie (valid for 30 days)
        if (!headers_sent()) {
            setcookie('ai_chatbot_session', $session_id, time() + (30 * 24 * 60 * 60), '/', '', is_ssl(), true);
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
        $final_data = array(
            'session_id' => $message_data['session_id'],
            'conversation_id' => $message_data['conversation_id'],
            'user_message' => $message_data['user_message'],
            'ai_response' => $message_data['ai_response'] ?? '',
            'tokens_used' => $message_data['tokens_used'] ?? 0,
            'model' => $message_data['model'] ?? '',
            'provider' => $message_data['provider'] ?? '',
            'response_time' => $message_data['response_time'] ?? 0,
            'user_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'status' => $message_data['status'] ?? 'completed',
            'audio_mode' => $message_data['audio_mode'] ?? 0,
            'created_at' => current_time('mysql')
        );
        
        // Prioritize authenticated user data
        if (!empty($message_data['user_email'])) {
            $final_data['user_email'] = $message_data['user_email'];
            $final_data['user_name'] = $message_data['user_name'] ?? '';
            $final_data['user_id'] = $message_data['user_id'] ?? null;
        } else {
            // For anonymous users, use empty values (not "anonymous")
            $final_data['user_email'] = '';
            $final_data['user_name'] = '';
            $final_data['user_id'] = null;
        }
        
        return $this->save_conversation($final_data);

    }

    /**
     * Get conversation history (updated to use email)
     */
    private function get_conversation_history($session_id, $user_email = null, $limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return array();
        }

        // Updated query to include rating and feedback data
        $results = $wpdb->get_results($wpdb->prepare(
                "SELECT *, 
                rating as conversation_rating,
                feedback as conversation_feedback,
                rated_at as conversation_rated_at,
                message_rating,
                message_rated_at
                FROM {$table_name} 
                WHERE session_id = %s 
                ORDER BY created_at ASC 
                LIMIT %d",
                $session_id,
                $limit
        ));

        $messages = array();
        foreach ($results as $row) {
            // User message
            $user_message = array(
                'id' => $row->conversation_id,
                'sender' => 'user',
                'message' => $row->user_message,
                'timestamp' => strtotime($row->created_at),
                'user_email' => $row->user_email
            );
            
            // Bot message with rating data
            $bot_message = array(
                'id' => $row->conversation_id,
                'sender' => 'bot',
                'message' => $row->ai_response,
                'timestamp' => strtotime($row->created_at),
                'user_email' => $row->user_email,
                // Add rating data to bot messages
                'message_rating' => $row->message_rating,
                'message_rated_at' => $row->message_rated_at,
                'conversation_rating' => $row->conversation_rating,
                'conversation_feedback' => $row->conversation_feedback,
                'conversation_rated_at' => $row->conversation_rated_at
            );
            
            $messages[] = $user_message;
            $messages[] = $bot_message;
        }

        return $messages;
    }

    /**
     * Check if conversation has been rated (NEW FUNCTION - ADD THIS)
     */
    private function get_conversation_rating_status($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return null;
        }

        // Get the most recent conversation rating for this session
        $rating_data = $wpdb->get_row($wpdb->prepare(
            "SELECT rating, feedback, rated_at 
            FROM {$table_name} 
            WHERE session_id = %s 
            AND rating IS NOT NULL 
            ORDER BY rated_at DESC 
            LIMIT 1",
            $session_id
        ));

        return $rating_data;
    }

    /**
     * Handle get conversation rating status (NEW FUNCTION - ADD THIS)
     */
    public function handle_get_conversation_rating_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($session_id)) {
            wp_send_json_error('Session ID required');
            return;
        }

        $rating_status = $this->get_conversation_rating_status($session_id);

        wp_send_json_success(array(
            'has_rating' => !empty($rating_status),
            'rating' => $rating_status ? intval($rating_status->rating) : null,
            'feedback' => $rating_status ? $rating_status->feedback : '',
            'rated_at' => $rating_status ? $rating_status->rated_at : null
        ));
    }

    /**
     * Clear conversation history (updated to use email)
     */
    private function clear_conversation_history($session_id, $user_email = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            if ($user_email) {
                // Clear by user email (more reliable)
                $wpdb->delete(
                    $table_name,
                    array(
                        'session_id' => $session_id,
                        'user_email' => $user_email
                    ),
                    array('%s', '%s')
                );
            } else {
                // Fallback to session only
                $wpdb->delete(
                    $table_name,
                    array('session_id' => $session_id),
                    array('%s')
                );
            }
        }
    }

    /**
     * Export conversation data
     */
    private function export_conversation_data($session_id, $user_email = null, $format = 'json') {
        $messages = $this->get_conversation_history($session_id, $user_email);
        
        if (empty($messages)) {
            return false;
        }

        switch ($format) {
            case 'json':
                return $messages;
            case 'csv':
                // Convert to CSV format
                $csv_data = "Timestamp,Sender,Message,User Email\n";
                foreach ($messages as $msg) {
                    $csv_data .= date('Y-m-d H:i:s', $msg['timestamp']) . ',"' . $msg['sender'] . '","' . addslashes($msg['message']) . '","' . ($msg['user_email'] ?? '') . "\"\n";
                }
                return $csv_data;
            default:
                return $messages;
        }
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
        // Enhanced logging
        error_log('AI Chatbot: ajax_save_user_data called');
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            error_log('AI Chatbot: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        // Enhanced validation
        if (empty($name) || empty($email) || !is_email($email)) {
            error_log('AI Chatbot: Invalid user data - Name: ' . $name . ', Email: ' . $email);
            wp_send_json_error(array('message' => __('Please provide valid name and email.', 'ai-website-chatbot')));
            return;
        }

        // Enhanced logging
        error_log('AI Chatbot: Processing user data - Name: ' . $name . ', Email: ' . $email . ', Session: ' . $session_id);

        // Get or create user in database
        $user_data = $this->get_or_create_user($email, $name, $session_id);

        if ($user_data) {
            // Enhanced logging
            error_log('AI Chatbot: User data saved successfully - ID: ' . $user_data['id']);
            
            // Store in WordPress session for server-side access
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['ai_chatbot_user'] = array(
                'id' => $user_data['id'],
                'email' => $user_data['email'],
                'name' => $user_data['name'],
                'session_id' => $session_id
            );
            
            wp_send_json_success(array(
                'message' => __('User data saved successfully.', 'ai-website-chatbot'),
                'user_id' => $user_data['id'],
                'user_data' => array(
                    'name' => $user_data['name'],
                    'email' => $user_data['email'],
                    'id' => $user_data['id'],
                    'session_id' => $session_id,
                    'authenticated' => true
                )
            ));
        } else {
            error_log('AI Chatbot: Failed to save user data');
            wp_send_json_error(array(
                'message' => __('Failed to save user data.', 'ai-website-chatbot')
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
        // Use more entropy for better uniqueness
        $session_id = 'chat_' . wp_generate_uuid4() . '_' . time();
        
        // Ensure it's unique in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE session_id = %s",
                $session_id
            ));
            
            // If by rare chance it exists, generate another
            if ($exists > 0) {
                return $this->generate_session_id(); // Recursive call
            }
        }
        
        return $session_id;
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
     * Get or create user in database
     */
    private function get_or_create_user($email, $name, $session_id) {
        global $wpdb;
        
        $users_table = $wpdb->prefix . 'ai_chatbot_users';
        
        // Check if user exists
        $existing_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$users_table} WHERE email = %s",
            $email
        ), ARRAY_A);
        
        if ($existing_user) {
            // Update existing user
            $wpdb->update(
                $users_table,
                array(
                    'name' => $name,
                    'last_seen' => current_time('mysql'),
                    'session_id' => $session_id,
                    'updated_at' => current_time('mysql')
                ),
                array('email' => $email),
                array('%s', '%s', '%s', '%s'),
                array('%s')
            );
            
            // Return updated user data
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$users_table} WHERE email = %s",
                $email
            ), ARRAY_A);
        } else {
            // Create new user
            $result = $wpdb->insert(
                $users_table,
                array(
                    'name' => $name,
                    'email' => $email,
                    'session_id' => $session_id,
                    'ip_address' => $this->get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'first_seen' => current_time('mysql'),
                    'last_seen' => current_time('mysql'),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                return $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$users_table} WHERE id = %d",
                    $wpdb->insert_id
                ), ARRAY_A);
            }
        }
        
        return false;
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

    /**
     * Handle conversation rating submission
     */
    public function handle_conversation_rating() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-website-chatbot')));
            return;
        }

        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $rating = intval($_POST['rating'] ?? 0);
        $feedback = sanitize_textarea_field($_POST['feedback'] ?? '');

        // Validate rating
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error(array('message' => __('Invalid rating value.', 'ai-website-chatbot')));
            return;
        }

        if (empty($conversation_id)) {
            wp_send_json_error(array('message' => __('Invalid conversation ID.', 'ai-website-chatbot')));
            return;
        }

        // Save conversation rating
        $result = $this->save_conversation_rating($conversation_id, $rating, $feedback);

        if ($result) {
            // Log the rating event
            $this->log_conversation_rating_event($conversation_id, $rating, $feedback);
            
            wp_send_json_success(array(
                'message' => __('Thank you for your feedback!', 'ai-website-chatbot'),
                'rating' => $rating,
                'has_feedback' => !empty($feedback),
                'session_reset' => true,
                'new_session_id' => $new_session_id,
                'should_reload_chat' => true,
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to save rating.', 'ai-website-chatbot')
            ));
        }
    }

    /**
     * Reset session after rating submission (NEW FUNCTION - ADD THIS)
     */
    private function reset_session_after_rating($new_session_id) {
        // Clear the old session cookie
        if (isset($_COOKIE['ai_chatbot_session'])) {
            setcookie('ai_chatbot_session', '', time() - 3600, '/');
            unset($_COOKIE['ai_chatbot_session']);
        }
        
        // Set new session cookie
        setcookie('ai_chatbot_session', $new_session_id, time() + (86400 * 30), '/'); // 30 days
        
        // Also clear any session-based storage if you're using it
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['ai_chatbot_session']);
            unset($_SESSION['ai_chatbot_conversation_id']);
            unset($_SESSION['ai_chatbot_user_data']);
        }
        
        // Log the session reset
        error_log("AI Chatbot: Session reset after rating. New session: " . $new_session_id);
    }

    /**
     * Save conversation rating to database
     */

    private function save_conversation_rating($conversation_id, $rating, $feedback = '') {
        global $wpdb;
        
        // Try to update conversations table if it exists
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            $result = $wpdb->update(
                $table_name,
                array(
                    'rating' => $rating,
                    'feedback' => $feedback,
                    'rated_at' => current_time('mysql')
                ),
                array('conversation_id' => $conversation_id),
                array('%d', '%s', '%s'),
                array('%s')
            );
            
            if ($result !== false) {
                return true;
            }
        }
        
        // Fallback: save to options table
        $ratings = get_option('ai_chatbot_conversation_ratings', array());
        $ratings[] = array(
            'conversation_id' => $conversation_id,
            'rating' => $rating,
            'feedback' => $feedback,
            'timestamp' => current_time('mysql'),
            'ip' => $this->get_client_ip()
        );
        
        // Keep only last 1000 ratings
        if (count($ratings) > 1000) {
            $ratings = array_slice($ratings, -1000);
        }
        
        return update_option('ai_chatbot_conversation_ratings', $ratings);
    }

    /**
     * Log conversation rating event for analytics (NEW METHOD - ADD THIS)
     */
    private function log_conversation_rating_event($conversation_id, $rating, $feedback = '') {
        // Log to WordPress debug log if enabled
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log("AI Chatbot Conversation Rating: ID={$conversation_id}, Rating={$rating}, Feedback=" . substr($feedback, 0, 100));
        }
        
        // Hook for external analytics
        do_action('ai_chatbot_conversation_rated', $conversation_id, $rating, $feedback);
    }

    /**
     * Get rating statistics for admin dashboard
     */
    public function get_rating_statistics($days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_ratings,
                AVG(rating) as average_rating,
                COUNT(CASE WHEN rating = 5 THEN 1 END) as excellent_count,
                COUNT(CASE WHEN rating = 4 THEN 1 END) as good_count,
                COUNT(CASE WHEN rating = 3 THEN 1 END) as okay_count,
                COUNT(CASE WHEN rating = 2 THEN 1 END) as poor_count,
                COUNT(CASE WHEN rating = 1 THEN 1 END) as very_poor_count,
                COUNT(CASE WHEN feedback != '' AND feedback IS NOT NULL THEN 1 END) as feedback_count
            FROM {$table_name} 
            WHERE rating IS NOT NULL 
            AND rated_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        return $stats;
    }

    /**
     * Get recent feedback for admin review
     */
    public function get_recent_feedback($limit = 20) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $feedback = $wpdb->get_results($wpdb->prepare(
            "SELECT id, rating, feedback, rated_at, user_name, user_email
            FROM {$table_name} 
            WHERE feedback != '' AND feedback IS NOT NULL
            ORDER BY rated_at DESC 
            LIMIT %d",
            $limit
        ));
        
        return $feedback;
    }

} // End of AI_Chatbot_Ajax class