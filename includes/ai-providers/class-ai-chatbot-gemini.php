<?php
/**
 * Google Gemini Provider Implementation
 *
 * @package AI_Website_Chatbot
 * @subpackage AI_Providers
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gemini provider class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Gemini implements AI_Chatbot_Provider_Interface {

	/**
	 * API base URL
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $api_base = 'https://generativelanguage.googleapis.com/v1beta/';

	/**
	 * API key
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $api_key;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$main_settings = get_option('ai_chatbot_settings', array());
    
		if (!empty($main_settings['api_key']) && $main_settings['ai_provider'] === 'gemini') {
			// New structure: settings stored in main array
			$this->api_key = $main_settings['api_key'];
		} else {
			// Fallback to old structure: individual options
			$this->api_key = get_option('ai_chatbot_gemini_api_key', '');
		}

	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 * @since 1.0.0
	 */
	public function get_name() {
		return 'gemini';
	}

	/**
	 * Get provider display name
	 *
	 * @return string Provider display name.
	 * @since 1.0.0
	 */
	public function get_display_name() {
		return 'Google Gemini';
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if configured.
	 * @since 1.0.0
	 */
	public function is_configured() {
		$api_key = $this->api_key;
    
		// Debug logging
		error_log('Gemini Provider - API Key check: ' . (empty($api_key) ? 'EMPTY' : 'Present (' . strlen($api_key) . ' chars)'));
		
		return !empty($api_key) && strlen($api_key) >= 20;
	}

	/**
	 * Test API connection
	 *
	 * @return bool|WP_Error True if connection successful, WP_Error if failed.
	 * @since 1.0.0
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Gemini API key is not configured.', 'ai-website-chatbot' ) );
		}

		// Test with a simple message
		$test_response = $this->generate_response( 'Hello', '', array( 'max_tokens' => 10 ) );

		if ( is_wp_error( $test_response ) ) {
			return $test_response;
		}

		return true;
	}

	/**
	 * Generate response from AI
	 *
	 * @param string $message User message.
	 * @param string|array $context Additional context from website or conversation history.
	 * @param array  $options Optional parameters.
	 * @return string|WP_Error AI response or WP_Error if failed.
	 * @since 1.0.0
	 */
	public function generate_response( $message, $context = '', $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Gemini API key is not configured.', 'ai-website-chatbot' ) );
		}

		// Get or generate session ID
		$session_id = $options['session_id'] ?? $this->get_or_generate_session_id();
		
		// Get or generate conversation ID
		$conversation_id = $options['conversation_id'] ?? $this->generate_conversation_id($session_id);

		// Check training data first (exact match)
		$training_response = $this->check_training_data($message);
		if (!is_wp_error($training_response) && !empty($training_response)) {
			error_log('Gemini Provider: Found exact training match for: ' . $message);
			
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
			error_log('Gemini Provider: Found similar training match for: ' . $message . ' (similarity: ' . $partial_match['similarity'] . ')');
			
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
			error_log('Gemini Provider: Using cached response for: ' . $message);
			
			return array(
				'response' => $cached['response'],
				'tokens_used' => 0,
				'model' => 'cached',
				'source' => 'cache'
			);
		}

		// Proceed with API call
		error_log('Gemini Provider: Making API call for: ' . $message);

		// Validate message
		$validation = $this->validate_message( $message );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// FIXED: Use the proper getter methods that check main settings
		$model = $options['model'] ?? $this->get_model();           // ✅ NOW USES get_model()
		$max_tokens = $options['max_tokens'] ?? $this->get_max_tokens(); // ✅ NOW USES get_max_tokens()
		$temperature = $options['temperature'] ?? $this->get_temperature(); // ✅ NOW USES get_temperature()

		// Add debug logging to see what model is actually being used
		error_log('Gemini Provider: Using model: ' . $model);
		error_log('Gemini Provider: Max tokens: ' . $max_tokens);
		error_log('Gemini Provider: Temperature: ' . $temperature);

		// Build system instruction with enhanced context
		$system_instruction = $this->build_system_message( $context );

		// Build conversation history with validation
		$conversation_history = $this->get_chat_conversation_history($conversation_id, 3);
		$contents = array();

		// Add conversation history - WITH VALIDATION
		foreach (array_reverse($conversation_history) as $history_item) {
			// Validate that both user_message and ai_response have content
			$user_msg = trim($history_item['user_message'] ?? '');
			$ai_msg = trim($history_item['ai_response'] ?? '');
			
			// Only add to contents if both messages have actual content
			if (!empty($user_msg) && !empty($ai_msg)) {
				$contents[] = array(
					'role' => 'user',
					'parts' => array(array('text' => $user_msg))
				);
				$contents[] = array(
					'role' => 'model',
					'parts' => array(array('text' => $ai_msg))
				);
			}
		}

		// Add current message - WITH VALIDATION
		$current_message = trim($message);
		if (!empty($current_message)) {
			$contents[] = array(
				'role' => 'user',
				'parts' => array(array('text' => $current_message))
			);
		}

		// Ensure we have at least one content item
		if (empty($contents)) {
			$contents[] = array(
				'role' => 'user',
				'parts' => array(array('text' => $message)) // Use original message as fallback
			);
		}

		// Build system instruction with validation
		$system_instruction = trim($this->build_system_message($context));
		$system_data = array();

		if (!empty($system_instruction)) {
			$system_data = array(
				'parts' => array(array('text' => $system_instruction))
			);
		}

		// Prepare request data
		$data = array(
			'contents' => $contents,
			'generationConfig' => array(
				'maxOutputTokens' => (int) $max_tokens,
				'temperature' => (float) $temperature,
			),
		);

		// Only add systemInstruction if we have content
		if (!empty($system_data)) {
			$data['systemInstruction'] = $system_data;
		}

		// Create the correct API URL
		$api_url = $this->api_base . 'models/' . $model . ':generateContent?key=' . $this->api_key;
		
		error_log('Gemini API URL: ' . $api_url);
		error_log('Gemini API Request Data: ' . wp_json_encode($data, JSON_PRETTY_PRINT));

		// Make API request
		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode( $data ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			error_log('Gemini API Error: ' . $response->get_error_message());
			return new WP_Error( 'api_error', __( 'Failed to connect to Gemini API.', 'ai-website-chatbot' ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		error_log('Gemini API Response Code: ' . $response_code);
		error_log('Gemini API Response Body: ' . $response_body);

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = $error_data['error']['message'] ?? __( 'Unknown API error.', 'ai-website-chatbot' );
			error_log('Gemini API Error ' . $response_code . ': ' . $error_message);
			
			return new WP_Error( 'api_error', sprintf( 
				__( 'Gemini API error (%d): %s', 'ai-website-chatbot' ), 
				$response_code, 
				$error_message 
			) );
		}

		$response_data = json_decode( $response_body, true );

		if ( ! isset( $response_data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			// Check for content filtering
			if ( isset( $response_data['candidates'][0]['finishReason'] ) && 
				$response_data['candidates'][0]['finishReason'] === 'SAFETY' ) {
				return new WP_Error( 'content_filtered', __( 'Response was filtered for safety. Please try rephrasing your question.', 'ai-website-chatbot' ) );
			}
			
			return new WP_Error( 'invalid_response', __( 'Invalid response from Gemini API.', 'ai-website-chatbot' ) );
		}

		$response_text = trim( $response_data['candidates'][0]['content']['parts'][0]['text'] );
		$tokens_used = $response_data['usageMetadata']['totalTokenCount'] ?? 0;

		// Cache the response
		$cache_key = 'ai_chatbot_gemini_response_' . md5(strtolower(trim($message)));
		set_transient($cache_key, $response_text, 3600); // Cache for 1 hour

		// Log the conversation
		$this->log_conversation($conversation_id, $message, $response_text, $tokens_used, 'api');

		return array(
			'response' => $response_text,
			'tokens_used' => $tokens_used,
			'model' => $model,
			'source' => 'api'
		);
	}

	/**
	 * Helper method for session ID management in providers
	 */
	private function get_or_generate_session_id() {
		// Check if session ID exists in cookie
		$session_id = isset($_COOKIE['ai_chatbot_session']) ? sanitize_text_field($_COOKIE['ai_chatbot_session']) : '';
		
		// Validate existing session ID
		if (!empty($session_id) && strlen($session_id) >= 20) {
			return $session_id;
		}
		
		// Generate new session ID
		$security = new AI_Chatbot_Security();
		$session_id = $security->generate_session_id();
		
		// Set cookie (valid for 7 days)
		if (!headers_sent()) {
			setcookie('ai_chatbot_session', $session_id, time() + (7 * 24 * 60 * 60), '/');
		}
		
		return $session_id;
	}

	/**
	 * Helper method to generate conversation ID
	 */
	private function generate_conversation_id($session_id) {
		return $session_id . '_conv_' . time() . '_' . wp_generate_password(8, false, false);
	}

	private function check_training_data($message) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_chatbot_training_data';
		
		// First check exact match (case-insensitive)
		$result = $wpdb->get_var($wpdb->prepare(
			"SELECT answer FROM $table_name WHERE LOWER(TRIM(question)) = LOWER(TRIM(%s)) AND status = 'active' LIMIT 1",
			$message
		));
		
		if ($result) {
			error_log('Found exact training match for: ' . $message);
			return $result;
		}
		
		return new WP_Error('no_training_match', 'No exact training match found');
	}

	/**
	 * Find similar training data using fuzzy matching
	 */
	private function find_similar_training($message, $similarity_threshold = 0.7) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_chatbot_training_data';
		
		$training_data = $wpdb->get_results(
			"SELECT question, answer FROM $table_name WHERE status = 'active'",
			ARRAY_A
		);
		
		$best_match = null;
		$best_similarity = 0;
		
		foreach ($training_data as $training_item) {
			$similarity = $this->calculate_similarity($message, $training_item['question']);
			
			if ($similarity > $best_similarity && $similarity >= $similarity_threshold) {
				$best_similarity = $similarity;
				$best_match = array(
					'response' => $training_item['answer'],
					'similarity' => $similarity,
					'original_question' => $training_item['question']
				);
			}
		}
		
		return $best_match ? $best_match : new WP_Error('no_similar_match', 'No similar training match found');
	}

	/**
	 * Calculate similarity between two strings
	 */
	private function calculate_similarity($str1, $str2) {
		// Simple Levenshtein distance based similarity
		$distance = levenshtein(strtolower($str1), strtolower($str2));
		$max_length = max(strlen($str1), strlen($str2));
		
		if ($max_length == 0) return 1.0;
		
		return 1 - ($distance / $max_length);
	}

	/**
	 * Adapt training response for similar questions
	 */
	private function adapt_training_response($response, $original_message) {
		// Simple adaptation - could be enhanced with AI
		return $response;
	}

	/**
	 * Get conversation history
	 */
	private function get_chat_conversation_history($conversation_id, $limit = 3) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		
		return $wpdb->get_results($wpdb->prepare(
			"SELECT user_message, ai_response, created_at 
			FROM $table_name 
			WHERE conversation_id = %s 
			ORDER BY created_at DESC 
			LIMIT %d",
			$conversation_id,
			$limit
		), ARRAY_A);
	}

	/**
	 * Check cached response
	 */
	private function check_cached_response($query, $similarity_threshold = 0.8) {
		$cache_key = 'ai_chatbot_' . $this->get_name() . '_response_' . md5(strtolower(trim($query)));
		$cached = get_transient($cache_key);
		
		if ($cached) {
			return array(
				'cached' => true,
				'response' => $cached,
				'similarity' => 1.0
			);
		}
		
		return array('cached' => false);
	}

	/**
	 * Log conversation to database
	 */
	private function log_conversation($conversation_id, $user_message, $ai_response, $tokens_used, $source) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		
		$wpdb->insert(
			$table_name,
			array(
				'conversation_id' => $conversation_id,
				'user_message' => $user_message,
				'ai_response' => $ai_response,
				'tokens_used' => $tokens_used,
				'source' => $source,
				'provider' => $this->get_name(),
				'created_at' => current_time('mysql')
			),
			array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
		);
	}

	/**
	 * Build enhanced system message with context
	 */
	private function build_system_message( $context = '' ) {
		$system_prompt = get_option( 'ai_chatbot_system_prompt', $this->get_default_system_prompt() );
		
		if ( ! empty( $context ) ) {
			if ( is_array( $context ) ) {
				$context = implode( "\n\n", $context );
			}
			$system_prompt .= "\n\nWebsite Context:\n" . $context;
		}
		
		return $system_prompt;
	}

	/**
	 * Get default system prompt
	 */
	private function get_default_system_prompt() {
		return "You are a helpful AI assistant for this website. Provide accurate, helpful, and concise responses based on the website content and context provided. Be friendly and professional.";
	}

	/**
	 * Validate user message
	 */
	private function validate_message( $message ) {
		if ( empty( trim( $message ) ) ) {
			return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'ai-website-chatbot' ) );
		}
		
		if ( strlen( $message ) > 4000 ) {
			return new WP_Error( 'message_too_long', __( 'Message is too long. Please keep it under 4000 characters.', 'ai-website-chatbot' ) );
		}
		
		return true;
	}


	/**
	 * Get available models with latest Gemini models
	 *
	 * @return array Available models.
	 * @since 1.0.0
	 */
	public function get_available_models() {
		return array(
			'gemini-2.0-flash' => array(
				'name' => 'Gemini 2.0 Flash',
				'description' => __( 'Latest and fastest Gemini model with improved reasoning and multimodal capabilities', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'context_length' => 1000000,
				'cost_per_1k' => 0.075,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'gemini-1.5-pro' => array(
				'name' => 'Gemini 1.5 Pro',
				'description' => __( 'High-performance model for complex reasoning and large context', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'context_length' => 2000000,
				'cost_per_1k' => 1.25,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'gemini-1.5-flash' => array(
				'name' => 'Gemini 1.5 Flash',
				'description' => __( 'Fast and efficient model for most use cases', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'context_length' => 1000000,
				'cost_per_1k' => 0.075,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'gemini-1.5-flash-8b' => array(
				'name' => 'Gemini 1.5 Flash 8B',
				'description' => __( 'Lightweight model optimized for speed and efficiency', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'context_length' => 1000000,
				'cost_per_1k' => 0.0375,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'gemini-pro' => array(
				'name' => 'Gemini Pro',
				'description' => __( 'Original Gemini Pro model for text tasks', 'ai-website-chatbot' ),
				'max_tokens' => 2048,
				'context_length' => 30720,
				'cost_per_1k' => 0.5,
				'supports_vision' => false,
				'supports_function_calling' => true,
			),
			'gemini-pro-vision' => array(
				'name' => 'Gemini Pro Vision',
				'description' => __( 'Gemini Pro with vision capabilities', 'ai-website-chatbot' ),
				'max_tokens' => 2048,
				'context_length' => 12288,
				'cost_per_1k' => 0.5,
				'supports_vision' => true,
				'supports_function_calling' => false,
			)
		);
	}

	/**
	 * Get default model
	 *
	 * @return string Default model identifier.
	 * @since 1.0.0
	 */
	public function get_default_model() {
		return 'gemini-2.0-flash';
	}

	/**
	 * Get provider configuration fields
	 *
	 * @return array Configuration fields.
	 * @since 1.0.0
	 */
	public function get_config_fields() {
		return array(
			'ai_chatbot_gemini_api_key' => array(
				'label' => __( 'API Key', 'ai-website-chatbot' ),
				'type' => 'password',
				'description' => __( 'Your Google Gemini API key', 'ai-website-chatbot' ),
				'required' => true,
			),
			'ai_chatbot_gemini_model' => array(
				'label' => __( 'Model', 'ai-website-chatbot' ),
				'type' => 'select',
				'options' => wp_list_pluck( $this->get_available_models(), 'name' ),
				'description' => __( 'Choose the Gemini model to use', 'ai-website-chatbot' ),
				'default' => 'gemini-2.0-flash',
			),
			'ai_chatbot_gemini_temperature' => array(
				'label' => __( 'Temperature', 'ai-website-chatbot' ),
				'type' => 'number',
				'min' => 0,
				'max' => 2,
				'step' => 0.1,
				'description' => __( 'Controls randomness in responses (0.0 = deterministic, 2.0 = very random)', 'ai-website-chatbot' ),
				'default' => 0.7,
			),
			'ai_chatbot_gemini_max_tokens' => array(
				'label' => __( 'Max Output Tokens', 'ai-website-chatbot' ),
				'type' => 'number',
				'min' => 1,
				'max' => 8192,
				'description' => __( 'Maximum length of the response', 'ai-website-chatbot' ),
				'default' => 1000,
			),
			'ai_chatbot_gemini_top_p' => array(
				'label' => __( 'Top P', 'ai-website-chatbot' ),
				'type' => 'number',
				'min' => 0,
				'max' => 1,
				'step' => 0.01,
				'description' => __( 'Nucleus sampling parameter', 'ai-website-chatbot' ),
				'default' => 0.8,
			),
			'ai_chatbot_gemini_top_k' => array(
				'label' => __( 'Top K', 'ai-website-chatbot' ),
				'type' => 'number',
				'min' => 1,
				'max' => 100,
				'description' => __( 'Top-k sampling parameter', 'ai-website-chatbot' ),
				'default' => 40,
			),
		);
	}

	/**
	 * Validate configuration
	 *
	 * @param array $config Configuration values.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 * @since 1.0.0
	 */
	public function validate_config( $config ) {
		$errors = array();

		// Validate API key
		if ( empty( $config['ai_chatbot_gemini_api_key'] ) ) {
			$errors[] = __( 'API key is required.', 'ai-website-chatbot' );
		} elseif ( strlen( $config['ai_chatbot_gemini_api_key'] ) < 35 ) {
			$errors[] = __( 'Invalid API key format.', 'ai-website-chatbot' );
		}

		// Validate model
		$available_models = array_keys( $this->get_available_models() );
		if ( ! empty( $config['ai_chatbot_gemini_model'] ) && ! in_array( $config['ai_chatbot_gemini_model'], $available_models, true ) ) {
			$errors[] = __( 'Invalid model selected.', 'ai-website-chatbot' );
		}

		// Validate temperature
		if ( isset( $config['ai_chatbot_gemini_temperature'] ) ) {
			$temp = floatval( $config['ai_chatbot_gemini_temperature'] );
			if ( $temp < 0 || $temp > 2 ) {
				$errors[] = __( 'Temperature must be between 0 and 2.', 'ai-website-chatbot' );
			}
		}

		// Validate max tokens
		if ( isset( $config['ai_chatbot_gemini_max_tokens'] ) ) {
			$tokens = intval( $config['ai_chatbot_gemini_max_tokens'] );
			if ( $tokens < 1 || $tokens > 8192 ) {
				$errors[] = __( 'Max tokens must be between 1 and 8192.', 'ai-website-chatbot' );
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( ' ', $errors ) );
		}

		return true;
	}

	/**
	 * Get usage statistics
	 *
	 * @return array Usage statistics.
	 * @since 1.0.0
	 */
	public function get_usage_stats() {
		return get_option( 'ai_chatbot_gemini_usage_stats', array(
			'total_requests' => 0,
			'total_tokens' => 0,
			'total_cost' => 0,
			'last_request' => null,
		) );
	}

	/**
	 * Get rate limits
	 *
	 * @return array Rate limit information.
	 * @since 1.0.0
	 */
	public function get_rate_limits() {
		return array(
			'requests_per_minute' => 60,
			'requests_per_day' => 1500,
			'tokens_per_minute' => 32000,
		);
	}

	/**
	 * Make HTTP request to Gemini API
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data Request data.
	 * @return array|WP_Error Response data or WP_Error.
	 * @since 1.0.0
	 */
	private function make_request( $endpoint, $data ) {
		$url = $this->api_base . $endpoint;
		
		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-goog-api-key' => $this->api_key,
			),
			'body' => wp_json_encode( $data ),
			'timeout' => 30,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'request_failed', __( 'Failed to connect to Gemini API.', 'ai-website-chatbot' ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			$error_data = json_decode( $response_body, true );
			$error_message = $error_data['error']['message'] ?? __( 'Unknown API error.', 'ai-website-chatbot' );
			
			return new WP_Error( 'api_error', sprintf( 
				__( 'Gemini API error (%d): %s', 'ai-website-chatbot' ), 
				$response_code, 
				$error_message 
			) );
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON response from Gemini API.', 'ai-website-chatbot' ) );
		}

		return $data;
	}

	/**
	 * Build full prompt with context
	 *
	 * @param string $message User message.
	 * @param string $context Website context.
	 * @param array  $options Additional options.
	 * @return string Full prompt.
	 * @since 1.0.0
	 */
	private function build_full_prompt( $message, $context, $options ) {
		$full_prompt = '';

		// Add system prompt if available
		$system_prompt = get_option( 'ai_chatbot_system_prompt', '' );
		if ( ! empty( $system_prompt ) ) {
			$full_prompt .= $system_prompt . "\n\n";
		}

		// Add website context
		if ( ! empty( $context ) && is_string( $context ) ) {
			$full_prompt .= __( 'Website information:', 'ai-website-chatbot' ) . "\n" . $context . "\n\n";
		}

		// Add conversation history if provided
		if ( ! empty( $options['history'] ) ) {
			$full_prompt .= __( 'Recent conversation:', 'ai-website-chatbot' ) . "\n";
			foreach ( $options['history'] as $item ) {
				if ( isset( $item['user_message'], $item['bot_response'] ) ) {
					$full_prompt .= "User: " . $item['user_message'] . "\n";
					$full_prompt .= "Assistant: " . $item['bot_response'] . "\n";
				}
			}
			$full_prompt .= "\n";
		}

		// Add current user message
		$full_prompt .= __( 'User question:', 'ai-website-chatbot' ) . "\n" . $message;

		return $full_prompt;
	}

	/**
	 * Log API usage statistics
	 *
	 * @param array $response API response.
	 * @since 1.0.0
	 */
	private function log_usage( $response ) {
		$stats = get_option( 'ai_chatbot_gemini_usage_stats', array(
			'total_requests' => 0,
			'total_tokens' => 0,
			'total_cost' => 0,
			'last_request' => null,
		) );

		$stats['total_requests']++;
		$stats['last_request'] = current_time( 'mysql' );

		// Estimate tokens and cost
		if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$response_text = $response['candidates'][0]['content']['parts'][0]['text'];
			$estimated_tokens = strlen( $response_text ) / 4; // Rough estimate
			$stats['total_tokens'] += $estimated_tokens;

			// Calculate cost
			$model = get_option( 'ai_chatbot_gemini_model', 'gemini-2.0-flash' );
			$cost_per_1k = $this->get_model_cost( $model );
			$stats['total_cost'] += ( $estimated_tokens / 1000 ) * $cost_per_1k;
		}

		update_option( 'ai_chatbot_gemini_usage_stats', $stats );
	}

	/**
	 * Get cost per 1K tokens for a model
	 *
	 * @param string $model Model name.
	 * @return float Cost per 1K tokens.
	 * @since 1.0.0
	 */
	private function get_model_cost( $model ) {
		$models = $this->get_available_models();
		return $models[ $model ]['cost_per_1k'] ?? 0.075;
	}

	/**
	 * Get model from correct settings location
	 */
	private function get_model() {
		$main_settings = get_option('ai_chatbot_settings', array());
		
		if (!empty($main_settings['model']) && $main_settings['ai_provider'] === 'gemini') {
			return $main_settings['model'];
		}
		
		return get_option('ai_chatbot_gemini_model', 'gemini-2.0-flash');
	}

	/**
	 * Get temperature from correct settings location
	 */
	private function get_temperature() {
		$main_settings = get_option('ai_chatbot_settings', array());
		
		if (isset($main_settings['temperature']) && $main_settings['ai_provider'] === 'gemini') {
			return floatval($main_settings['temperature']);
		}
		
		return floatval(get_option('ai_chatbot_gemini_temperature', 0.7));
	}

	/**
	 * Get max tokens from correct settings location
	 */
	private function get_max_tokens() {
		$main_settings = get_option('ai_chatbot_settings', array());
		
		if (isset($main_settings['max_tokens']) && $main_settings['ai_provider'] === 'gemini') {
			return intval($main_settings['max_tokens']);
		}
		
		return intval(get_option('ai_chatbot_gemini_max_tokens', 1000));
	}

	/**
	 * Get top_p setting
	 */
	private function get_top_p() {
		return floatval(get_option('ai_chatbot_gemini_top_p', 0.8));
	}

	/**
	 * Get top_k setting
	 */
	private function get_top_k() {
		return intval(get_option('ai_chatbot_gemini_top_k', 40));
	}
}