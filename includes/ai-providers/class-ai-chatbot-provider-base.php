<?php
/**
 * Abstract AI Provider Base Class
 * Contains all common functionality shared between providers
 *
 * @package AI_Website_Chatbot
 * @subpackage AI_Providers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for AI providers
 * Implements common functionality to eliminate code duplication
 */
abstract class AI_Chatbot_Provider_Base implements AI_Chatbot_Provider_Interface {

    /**
     * API key
     *
     * @var string
     */
    protected $api_key;

    /**
     * API base URL
     *
     * @var string
     */
    protected $api_base;

    /**
     * Provider name
     *
     * @var string
     */
    protected $provider_name;

    /**
     * Constructor - loads API key
     */
    public function __construct() {
        $this->load_api_key();
    }

    // ==========================================
    // ABSTRACT METHODS - Must be implemented by child classes
    // ==========================================

    /**
     * Make API request - provider-specific implementation
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|WP_Error API response or error
     */
    abstract protected function make_api_request($endpoint, $data);

    /**
     * Build system message - provider-specific format
     *
     * @param string $context Website context
     * @return string System message
     */
    abstract protected function build_system_message($context);

    /**
     * Get cost per 1K tokens for a model
     *
     * @param string $model Model name
     * @return float Cost per 1K tokens
     */
    abstract protected function get_model_cost($model);

    // ==========================================
    // COMMON SESSION & ID MANAGEMENT
    // ==========================================

    /**
     * Get or generate session ID
     *
     * @return string Session ID
     */
    protected function get_or_generate_session_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['ai_chatbot_session_id'])) {
            $_SESSION['ai_chatbot_session_id'] = wp_generate_uuid4();
        }
        
        return $_SESSION['ai_chatbot_session_id'];
    }

    /**
     * Generate conversation ID
     *
     * @param string $session_id Session ID
     * @return string Conversation ID
     */
    protected function generate_conversation_id($session_id) {
        return wp_generate_uuid4();
    }

    // ==========================================
    // COMMON SETTINGS RETRIEVAL
    // ==========================================

    /**
     * Load API key from settings
     */
    protected function load_api_key() {
        $main_settings = get_option('ai_chatbot_settings', array());
        $provider_name = $this->get_name();
    
        if (!empty($main_settings['api_key']) && $main_settings['ai_provider'] === $provider_name) {
            // New structure: settings stored in main array
            $this->api_key = $main_settings['api_key'];
        } else {
            // Fallback to old structure: individual options
            $this->api_key = get_option('ai_chatbot_' . $provider_name . '_api_key', '');
        }
    }

    /**
     * Get model setting
     *
     * @return string Model name
     */
    protected function get_model() {
        $main_settings = get_option('ai_chatbot_settings', array());
        $fallback_option = 'ai_chatbot_' . $this->get_name() . '_model';
        return $main_settings['model'] ?? get_option($fallback_option, $this->get_default_model());
    }

    /**
     * Get max tokens setting
     *
     * @return int Max tokens
     */
    protected function get_max_tokens() {
        $main_settings = get_option('ai_chatbot_settings', array());
        $fallback_option = 'ai_chatbot_' . $this->get_name() . '_max_tokens';
        return intval($main_settings['max_tokens'] ?? get_option($fallback_option, 300));
    }

    /**
     * Get temperature setting
     *
     * @return float Temperature
     */
    protected function get_temperature() {
        $main_settings = get_option('ai_chatbot_settings', array());
        $fallback_option = 'ai_chatbot_' . $this->get_name() . '_temperature';
        return floatval($main_settings['temperature'] ?? get_option($fallback_option, 0.7));
    }

    // ==========================================
    // COMMON VALIDATION
    // ==========================================

    /**
     * Validate message
     *
     * @param string $message Message to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    protected function validate_message($message) {
        if (empty(trim($message))) {
            return new WP_Error('empty_message', __('Message cannot be empty.', 'ai-website-chatbot'));
        }

        $max_length = get_option('ai_chatbot_max_message_length', 1000);
        if (strlen($message) > $max_length) {
            return new WP_Error('message_too_long', sprintf(
                __('Message is too long. Maximum length is %d characters.', 'ai-website-chatbot'), 
                $max_length 
            ));
        }

        return true;
    }

    // ==========================================
    // COMMON TRAINING DATA OPERATIONS
    // ==========================================

    /**
     * Check training data for exact match
     *
     * @param string $message User message
     * @return string|WP_Error Training response or error if not found
     */
    protected function check_training_data($message) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return new WP_Error('no_training_table', 'Training table does not exist');
        }
        
        $training_data = $wpdb->get_row($wpdb->prepare(
            "SELECT response FROM {$table_name} 
             WHERE LOWER(TRIM(question)) = LOWER(TRIM(%s)) 
             AND status = 'active' 
             LIMIT 1",
            $message
        ));
        
        if ($training_data && !empty($training_data->response)) {
            return $training_data->response;
        }
        
        return new WP_Error('no_match', 'No training match found');
    }

    /**
     * Find similar training data
     *
     * @param string $message User message
     * @return array|WP_Error Similar training data or error
     */
    protected function find_similar_training($message) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return new WP_Error('no_training_table', 'Training table does not exist');
        }
        
        $similar_data = $wpdb->get_results($wpdb->prepare(
            "SELECT question, response,
                    MATCH(question) AGAINST(%s IN NATURAL LANGUAGE MODE) as relevance
             FROM {$table_name} 
             WHERE MATCH(question) AGAINST(%s IN NATURAL LANGUAGE MODE)
             AND status = 'active'
             ORDER BY relevance DESC 
             LIMIT 1",
            $message, $message
        ));
        
        if (!empty($similar_data) && $similar_data[0]->relevance > 0.5) {
            return array(
                'response' => $similar_data[0]->response,
                'similarity' => $similar_data[0]->relevance,
                'original_question' => $similar_data[0]->question
            );
        }
        
        return new WP_Error('no_similar_match', 'No similar training match found');
    }

    /**
     * Adapt training response to current message
     *
     * @param string $training_response Training response
     * @param string $current_message Current message
     * @return string Adapted response
     */
    protected function adapt_training_response($training_response, $current_message) {
        // Simple adaptation - can be made more sophisticated
        return $training_response;
    }

    // ==========================================
    // COMMON CACHING OPERATIONS
    // ==========================================

    /**
     * Check cached response
     *
     * @param string $message User message
     * @return array Cache result
     */
    protected function check_cached_response($message) {
        $cache_key = 'ai_chatbot_response_' . md5(strtolower(trim($message)));
        $cached_response = get_transient($cache_key);
        
        if ($cached_response !== false) {
            return array(
                'cached' => true,
                'response' => $cached_response
            );
        }
        
        return array('cached' => false);
    }

    /**
     * Cache response
     *
     * @param string $message User message
     * @param string $response AI response
     */
    protected function cache_response($message, $response) {
        $cache_key = 'ai_chatbot_response_' . md5(strtolower(trim($message)));
        $cache_duration = 12 * HOUR_IN_SECONDS; // Cache for 12 hours
        
        set_transient($cache_key, $response, $cache_duration);
    }

    // ==========================================
    // COMMON DATABASE OPERATIONS
    // ==========================================

    /**
     * Get conversation history
     *
     * @param string $conversation_id Conversation ID
     * @param int $limit Number of messages to retrieve
     * @return array Conversation history
     */
    protected function get_chat_conversation_history($conversation_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT user_message, ai_response 
             FROM {$table_name} 
             WHERE conversation_id = %s 
             AND status = 'completed'
             AND ai_response IS NOT NULL
             AND ai_response != ''
             ORDER BY created_at DESC 
             LIMIT %d",
            $conversation_id, $limit
        ), ARRAY_A);
        
        return $history ?: array();
    }

    /**
     * Log conversation
     *
     * @param string $conversation_id Conversation ID
     * @param string $message User message
     * @param string $response AI response
     * @param float $response_time Response time
     * @param string $source Response source
     */
    protected function log_conversation($conversation_id, $message, $response, $response_time, $source) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $wpdb->insert(
            $table_name,
            array(
                'conversation_id' => $conversation_id,
                'user_message' => $message,
                'ai_response' => $response,
                'response_time' => $response_time,
                'source' => $source,
                'provider' => $this->get_name(),
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
        );
    }

    // ==========================================
    // COMMON USAGE TRACKING
    // ==========================================

    /**
     * Log API usage statistics
     *
     * @param array $response API response
     */
    protected function log_usage($response) {
        $provider_name = $this->get_name();
        $stats_option = 'ai_chatbot_' . $provider_name . '_usage_stats';
        
        $stats = get_option($stats_option, array(
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'last_request' => null,
        ));

        $stats['total_requests']++;
        $stats['last_request'] = current_time('mysql');

        // Extract tokens based on provider
        $tokens_used = $this->extract_tokens_from_response($response);
        if ($tokens_used > 0) {
            $stats['total_tokens'] += $tokens_used;

            // Calculate cost
            $model = $this->extract_model_from_response($response);
            $cost_per_1k = $this->get_model_cost($model);
            $stats['total_cost'] += ($tokens_used / 1000) * $cost_per_1k;
        }

        update_option($stats_option, $stats);
    }

    /**
     * Extract tokens from API response - provider-specific
     *
     * @param array $response API response
     * @return int Tokens used
     */
    protected function extract_tokens_from_response($response) {
        $provider_name = $this->get_name();
        
        switch ($provider_name) {
            case 'openai':
                return $response['usage']['total_tokens'] ?? 0;
            case 'claude':
                return $response['usage']['output_tokens'] ?? 0;
            case 'gemini':
                // Gemini doesn't provide token count, estimate
                if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $response['candidates'][0]['content']['parts'][0]['text'];
                    return ceil(strlen($text) / 4); // Rough estimate
                }
                return 0;
            default:
                return 0;
        }
    }

    /**
     * Extract model from API response
     *
     * @param array $response API response
     * @return string Model name
     */
    protected function extract_model_from_response($response) {
        return $response['model'] ?? $this->get_default_model();
    }

    // ==========================================
    // COMMON INTERFACE IMPLEMENTATIONS
    // ==========================================

    /**
     * Test API connection - common implementation
     *
     * @return bool|WP_Error True if connection successful, WP_Error if failed
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', sprintf(
                __('%s API key is not configured.', 'ai-website-chatbot'),
                $this->get_display_name()
            ));
        }

        // Test with a simple message
        $test_response = $this->generate_response('Hello', '', array('max_tokens' => 10));

        if (is_wp_error($test_response)) {
            return $test_response;
        }

        return true;
    }

    /**
     * Check if provider is configured - common implementation
     *
     * @return bool True if configured
     */
    public function is_configured() {
        $api_key = $this->api_key;
        error_log($this->get_display_name() . ' Provider - API Key check: ' . (empty($api_key) ? 'EMPTY' : 'Present (' . strlen($api_key) . ' chars)'));
        return !empty($api_key) && strlen($api_key) >= 20;
    }

    /**
     * Get usage statistics - common implementation
     *
     * @return array Usage statistics
     */
    public function get_usage_stats() {
        $stats_option = 'ai_chatbot_' . $this->get_name() . '_usage_stats';
        return get_option($stats_option, array(
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'last_request' => null,
        ));
    }

    /**
     * Validate configuration - common implementation
     *
     * @param array $config Configuration values
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_config($config) {
        if (empty($config['api_key'])) {
            return new WP_Error('missing_api_key', sprintf(
                __('%s API key is required.', 'ai-website-chatbot'),
                $this->get_display_name()
            ));
        }

        if (strlen($config['api_key']) < 20) {
            return new WP_Error('invalid_api_key', sprintf(
                __('Invalid %s API key format.', 'ai-website-chatbot'),
                $this->get_display_name()
            ));
        }

        return true;
    }

    /**
     * Get rate limits - default implementation
     *
     * @return array Rate limit information
     */
    public function get_rate_limits() {
        return array(
            'requests_per_minute' => 60,
            'tokens_per_minute' => 10000,
            'requests_per_day' => 1000,
            'current_usage' => 0
        );
    }

    // ==========================================
    // COMMON RESPONSE PROCESSING
    // ==========================================

    /**
     * Process training and cache checks before API call
     *
     * @param string $message User message
     * @param string $conversation_id Conversation ID
     * @return array|null Returns response array if found, null if should proceed to API
     */
    protected function process_pre_api_checks($message, $conversation_id) {
        // Check training data first (exact match)
        $training_response = $this->check_training_data($message);
        if (!is_wp_error($training_response) && !empty($training_response)) {
            error_log($this->get_display_name() . ' Provider: Found exact training match for: ' . $message);
            
            // Log the training response
            $this->log_conversation($conversation_id, $message, $training_response, 0, 'training');
            
            return array(
                'response' => $training_response,
                'tokens_used' => 0,
                'model' => 'training',
                'source' => 'training'
            );
        }

        // Check for partial training matches (similarity-based)
        $partial_match = $this->find_similar_training($message);
        if (!is_wp_error($partial_match) && !empty($partial_match['response'])) {
            error_log($this->get_display_name() . ' Provider: Found similar training match for: ' . $message . ' (similarity: ' . $partial_match['similarity'] . ')');
            
            // Use similar response with slight modification
            $modified_response = $this->adapt_training_response($partial_match['response'], $message);
            
            $this->log_conversation($conversation_id, $message, $modified_response, 0, 'training_similar');
            
            return array(
                'response' => $modified_response,
                'tokens_used' => 0,
                'model' => 'training_similar',
                'source' => 'training'
            );
        }

        // Check cache for API responses
        $cached = $this->check_cached_response($message);
        if ($cached['cached']) {
            error_log($this->get_display_name() . ' Provider: Using cached response for: ' . $message);
            
            return array(
                'response' => $cached['response'],
                'tokens_used' => 0,
                'model' => 'cached',
                'source' => 'cache'
            );
        }

        // No matches found, proceed with API call
        return null;
    }
}