<?php
/**
 * OpenAI Provider Implementation
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
 * OpenAI provider class
 *
 * @since 1.0.0
 */
class AI_Chatbot_OpenAI implements AI_Chatbot_Provider_Interface {

	/**
	 * API base URL
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $api_base = 'https://api.openai.com/v1/';

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
    
		if (!empty($main_settings['api_key']) && $main_settings['ai_provider'] === 'openai') {
			// New structure: settings stored in main array
			$this->api_key = $main_settings['api_key'];
		} else {
			// Fallback to old structure: individual options
			$this->api_key = get_option('ai_chatbot_openai_api_key', '');
		}
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 * @since 1.0.0
	 */
	public function get_name() {
		return 'openai';
	}

	/**
	 * Get provider display name
	 *
	 * @return string Provider display name.
	 * @since 1.0.0
	 */
	public function get_display_name() {
		return 'OpenAI (ChatGPT)';
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if configured.
	 * @since 1.0.0
	 */
	public function is_configured() {
		$api_key = $this->api_key;
		error_log('OpenAI Provider - API Key check: ' . (empty($api_key) ? 'EMPTY' : 'Present (' . strlen($api_key) . ' chars)'));
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
			return new WP_Error( 'not_configured', __( 'OpenAI API key is not configured.', 'ai-website-chatbot' ) );
		}

		$response = $this->make_request( 'models', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return true;
		}

		return new WP_Error( 'test_failed', __( 'Failed to retrieve models from OpenAI.', 'ai-website-chatbot' ) );
	}

	/**
	 * Generate response from AI
	 *
	 * @param string $message User message.
	 * @param string $context Additional context from website.
	 * @param array  $options Optional parameters.
	 * @return string|WP_Error AI response or WP_Error if failed.
	 * @since 1.0.0
	 */
	public function generate_response( $message, $context = '', $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'OpenAI API key is not configured.', 'ai-website-chatbot' ) );
		}

		// Get or generate session ID
		$session_id = $options['session_id'] ?? $this->get_or_generate_session_id();
		
		// Get or generate conversation ID
		$conversation_id = $options['conversation_id'] ?? $this->generate_conversation_id($session_id);

		
		// Check training data first (exact match)
		$training_response = $this->check_training_data($message);
		if (!is_wp_error($training_response) && !empty($training_response)) {
			error_log('OpenAI Provider: Found exact training match for: ' . $message);
			
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
			error_log('OpenAI Provider: Found similar training match for: ' . $message . ' (similarity: ' . $partial_match['similarity'] . ')');
			
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
			error_log('OpenAI Provider: Using cached response for: ' . $message);
			
			return array(
				'response' => $cached['response'],
				'tokens_used' => 0,
				'model' => 'cached',
				'source' => 'cache'
			);
		}

		// Proceed with API call
		error_log('OpenAI Provider: Making API call for: ' . $message);

		// Validate message
		$validation = $this->validate_message( $message );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Build messages array
		$messages = array();

		// Add system message with enhanced context
		$system_prompt = $this->build_system_message( $context );
		if ( ! empty( $system_prompt ) ) {
			$messages[] = array(
				'role' => 'system',
				'content' => $system_prompt,
			);
		}

		// Add conversation history for context
		$conversation_history = $this->get_chat_conversation_history($conversation_id, 3);
		foreach (array_reverse($conversation_history) as $history_item) {
			$messages[] = array('role' => 'user', 'content' => $history_item['user_message']);
			$messages[] = array('role' => 'assistant', 'content' => $history_item['ai_response']);
		}

		// Add current user message
		$messages[] = array(
			'role' => 'user',
			'content' => $message,
		);

		// Get model and settings
		$model = $options['model'] ?? get_option( 'ai_chatbot_openai_model', 'gpt-4o-mini' );
		$max_tokens = $options['max_tokens'] ?? get_option( 'ai_chatbot_openai_max_tokens', 300 );
		$temperature = $options['temperature'] ?? get_option( 'ai_chatbot_openai_temperature', 0.7 );

		// Prepare request data
		$data = array(
			'model' => $model,
			'messages' => $messages,
			'max_tokens' => (int) $max_tokens,
			'temperature' => (float) $temperature,
			'stream' => false,
		);

		// Make API request
		$response = wp_remote_post( $this->api_base . 'chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode( $data ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			error_log('OpenAI API Error: ' . $response->get_error_message());
			return new WP_Error( 'api_error', __( 'Failed to connect to OpenAI API.', 'ai-website-chatbot' ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = $error_data['error']['message'] ?? __( 'Unknown API error.', 'ai-website-chatbot' );
			error_log('OpenAI API Error ' . $response_code . ': ' . $error_message);
			
			return new WP_Error( 'api_error', sprintf( 
				__( 'OpenAI API error (%d): %s', 'ai-website-chatbot' ), 
				$response_code, 
				$error_message 
			) );
		}

		$data = json_decode( $response_body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from OpenAI API.', 'ai-website-chatbot' ) );
		}

		$response_text = trim( $data['choices'][0]['message']['content'] );
		$tokens_used = $data['usage']['total_tokens'] ?? 0;

		// Cache the response
		$cache_key = 'ai_chatbot_openai_response_' . md5(strtolower(trim($message)));
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

	/**
	 * Check for training data match
	 */
	private function check_training_data_match($user_message) {
		global $wpdb;
		
		$training_table = $wpdb->prefix . 'ai_chatbot_training_data';
		
		// Check if table exists
		if ($wpdb->get_var("SHOW TABLES LIKE '$training_table'") != $training_table) {
			return false;
		}
		
		// Look for exact or similar matches
		$query_lower = strtolower(trim($user_message));
		
		// Try exact match first
		$exact_match = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $training_table 
			WHERE status = 'active' AND LOWER(question) = %s 
			LIMIT 1",
			$query_lower
		));
		
		if ($exact_match) {
			return $exact_match;
		}
		
		// Try partial match (contains keywords)
		$keywords = explode(' ', $query_lower);
		$keywords = array_filter($keywords, function($word) {
			return strlen($word) > 3; // Only words longer than 3 chars
		});
		
		if (!empty($keywords)) {
			$like_conditions = array();
			$prepare_values = array();
			
			foreach ($keywords as $keyword) {
				$like_conditions[] = 'LOWER(question) LIKE %s';
				$prepare_values[] = '%' . $wpdb->esc_like($keyword) . '%';
			}
			
			$where_clause = implode(' OR ', $like_conditions);
			
			$partial_match = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM $training_table 
				WHERE status = 'active' AND ($where_clause)
				ORDER BY CHAR_LENGTH(question) ASC 
				LIMIT 1",
				...$prepare_values
			));
			
			return $partial_match;
		}
		
		return false;
	}

	/**
	 * Check training data for exact match
	 */
	private function check_training_data($message) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ai_chatbot_training_data';
		
		// First check exact match (case-insensitive)
		$result = $wpdb->get_var($wpdb->prepare(
			"SELECT response FROM $table_name WHERE LOWER(TRIM(question)) = LOWER(TRIM(%s)) AND status = 'active' LIMIT 1",
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
			"SELECT question, response FROM $table_name WHERE status = 'active'",
			ARRAY_A
		);
		
		$best_match = null;
		$best_similarity = 0;
		
		foreach ($training_data as $training_item) {
			$similarity = $this->calculate_similarity($message, $training_item['question']);
			
			if ($similarity > $best_similarity && $similarity >= $similarity_threshold) {
				$best_similarity = $similarity;
				$best_match = array(
					'response' => $training_item['response'],
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
	 * Get available models
	 *
	 * @return array Available models.
	 * @since 1.0.0
	 */
	public function get_available_models() {
		return array(
			// GPT-4 models (Latest)
			'gpt-4o' => array(
				'name' => 'GPT-4o',
				'description' => __( 'Latest multimodal flagship model, cheaper and faster than GPT-4 Turbo', 'ai-website-chatbot' ),
				'max_tokens' => 4096,
				'context_length' => 128000,
				'cost_per_1k_input' => 2.50,
				'cost_per_1k_output' => 10.00,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'gpt-4o-mini' => array(
				'name' => 'GPT-4o Mini',
				'description' => __( 'Affordable and intelligent small model for fast, lightweight tasks', 'ai-website-chatbot' ),
				'max_tokens' => 16384,
				'context_length' => 128000,
				'cost_per_1k_input' => 0.15,
				'cost_per_1k_output' => 0.60,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'gpt-4-turbo' => array(
				'name' => 'GPT-4 Turbo',
				'description' => __( 'Latest GPT-4 Turbo model with vision capabilities', 'ai-website-chatbot' ),
				'max_tokens' => 4096,
				'context_length' => 128000,
				'cost_per_1k_input' => 10.00,
				'cost_per_1k_output' => 30.00,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'gpt-4' => array(
				'name' => 'GPT-4',
				'description' => __( 'Original GPT-4 model for complex tasks', 'ai-website-chatbot' ),
				'max_tokens' => 4096,
				'context_length' => 8192,
				'cost_per_1k_input' => 30.00,
				'cost_per_1k_output' => 60.00,
				'supports_vision' => false,
				'supports_function_calling' => true,
			),
			// GPT-3.5 models
			'gpt-3.5-turbo' => array(
				'name' => 'GPT-3.5 Turbo',
				'description' => __( 'Fast, inexpensive model for simple tasks', 'ai-website-chatbot' ),
				'max_tokens' => 4096,
				'context_length' => 16384,
				'cost_per_1k_input' => 0.50,
				'cost_per_1k_output' => 1.50,
				'supports_vision' => false,
				'supports_function_calling' => true,
			),
			// Reasoning models (o1 series - GPT-5 level)
			'o1-preview' => array(
				'name' => 'o1-preview (GPT-5 level)',
				'description' => __( 'Advanced reasoning model designed to solve hard problems across domains', 'ai-website-chatbot' ),
				'max_tokens' => 32768,
				'context_length' => 128000,
				'cost_per_1k_input' => 15.00,
				'cost_per_1k_output' => 60.00,
				'supports_vision' => false,
				'supports_function_calling' => false,
			),
			'o1-mini' => array(
				'name' => 'o1-mini (GPT-5 level)',
				'description' => __( 'Faster and cheaper reasoning model for coding, math, and science', 'ai-website-chatbot' ),
				'max_tokens' => 65536,
				'context_length' => 128000,
				'cost_per_1k_input' => 3.00,
				'cost_per_1k_output' => 12.00,
				'supports_vision' => false,
				'supports_function_calling' => false,
			),
		);
	}

	/**
	 * Get default model
	 *
	 * @return string Default model identifier.
	 * @since 1.0.0
	 */
	public function get_default_model() {
		return 'gpt-4o';
	}

	/**
	 * Get provider configuration fields
	 *
	 * @return array Configuration fields.
	 * @since 1.0.0
	 */
	public function get_config_fields() {
		return array(
			'ai_chatbot_openai_api_key' => array(
				'label' => __( 'API Key', 'ai-website-chatbot' ),
				'type' => 'password',
				'description' => __( 'Your OpenAI API key (starts with sk-)', 'ai-website-chatbot' ),
				'required' => true,
			),
			'ai_chatbot_openai_model' => array(
				'label' => __( 'Model', 'ai-website-chatbot' ),
				'type' => 'select',
				'options' => wp_list_pluck( $this->get_available_models(), 'name' ),
				'description' => __( 'Choose the OpenAI model to use', 'ai-website-chatbot' ),
				'default' => 'gpt-3.5-turbo',
			),
			'ai_chatbot_openai_temperature' => array(
				'label' => __( 'Temperature', 'ai-website-chatbot' ),
				'type' => 'number',
				'min' => 0,
				'max' => 2,
				'step' => 0.1,
				'description' => __( 'Controls randomness (0 = focused, 2 = creative)', 'ai-website-chatbot' ),
				'default' => 0.7,
			),
			'ai_chatbot_openai_max_tokens' => array(
				'label' => __( 'Max Tokens', 'ai-website-chatbot' ),
				'type' => 'number',
				'min' => 1,
				'max' => 4096,
				'description' => __( 'Maximum length of the response', 'ai-website-chatbot' ),
				'default' => 300,
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
		if ( empty( $config['ai_chatbot_openai_api_key'] ) ) {
			$errors[] = __( 'API key is required.', 'ai-website-chatbot' );
		} elseif ( ! preg_match( '/^sk-[a-zA-Z0-9]{48}$/', $config['ai_chatbot_openai_api_key'] ) ) {
			$errors[] = __( 'Invalid API key format.', 'ai-website-chatbot' );
		}

		// Validate model
		$available_models = array_keys( $this->get_available_models() );
		if ( ! empty( $config['ai_chatbot_openai_model'] ) && ! in_array( $config['ai_chatbot_openai_model'], $available_models, true ) ) {
			$errors[] = __( 'Invalid model selected.', 'ai-website-chatbot' );
		}

		// Validate temperature
		if ( isset( $config['ai_chatbot_openai_temperature'] ) ) {
			$temp = floatval( $config['ai_chatbot_openai_temperature'] );
			if ( $temp < 0 || $temp > 2 ) {
				$errors[] = __( 'Temperature must be between 0 and 2.', 'ai-website-chatbot' );
			}
		}

		// Validate max tokens
		if ( isset( $config['ai_chatbot_openai_max_tokens'] ) ) {
			$tokens = intval( $config['ai_chatbot_openai_max_tokens'] );
			if ( $tokens < 1 || $tokens > 4096 ) {
				$errors[] = __( 'Max tokens must be between 1 and 4096.', 'ai-website-chatbot' );
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
		$stats = get_option( 'ai_chatbot_openai_usage_stats', array(
			'total_requests' => 0,
			'total_tokens' => 0,
			'total_cost' => 0,
			'last_request' => null,
		) );

		return $stats;
	}

	/**
	 * Get rate limits
	 *
	 * @return array Rate limit information.
	 * @since 1.0.0
	 */
	public function get_rate_limits() {
		return array(
			'requests_per_minute' => array(
				'gpt-3.5-turbo' => 3500,
				'gpt-4' => 200,
			),
			'tokens_per_minute' => array(
				'gpt-3.5-turbo' => 90000,
				'gpt-4' => 40000,
			),
			'requests_per_day' => array(
				'gpt-3.5-turbo' => 10000,
				'gpt-4' => 10000,
			),
		);
	}

	/**
	 * Make API request to OpenAI
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data Request data.
	 * @param string $method HTTP method.
	 * @return array|WP_Error Response data or WP_Error if failed.
	 * @since 1.0.0
	 */
	private function make_request( $endpoint, $data = array(), $method = 'POST' ) {
		$url = $this->api_base . $endpoint;

		$args = array(
			'method' => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type' => 'application/json',
				'User-Agent' => 'WordPress-AI-Chatbot/' . AI_CHATBOT_VERSION,
			),
			'timeout' => 30,
			'sslverify' => true,
		);

		if ( $method === 'POST' && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'request_failed', $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded_body = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			$error_message = isset( $decoded_body['error']['message'] ) 
				? $decoded_body['error']['message'] 
				: sprintf( __( 'HTTP %d error', 'ai-website-chatbot' ), $status_code );
				
			return new WP_Error( 'api_error', $error_message, array( 'status_code' => $status_code ) );
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON response from OpenAI.', 'ai-website-chatbot' ) );
		}

		return $decoded_body;
	}

	/**
	 * Format conversation history for API
	 *
	 * @param array $history Conversation history.
	 * @return array Formatted messages.
	 * @since 1.0.0
	 */
	private function format_conversation_history( $history ) {
		$messages = array();
		
		foreach ( $history as $item ) {
			$messages[] = array(
				'role' => 'user',
				'content' => $item['user_message'],
			);
			$messages[] = array(
				'role' => 'assistant',
				'content' => $item['bot_response'],
			);
		}

		return $messages;
	}

	/**
	 * Log API usage statistics
	 *
	 * @param array $response API response.
	 * @since 1.0.0
	 */
	private function log_usage( $response ) {
		if ( ! isset( $response['usage'] ) ) {
			return;
		}

		$usage = $response['usage'];
		$stats = get_option( 'ai_chatbot_openai_usage_stats', array(
			'total_requests' => 0,
			'total_tokens' => 0,
			'total_cost' => 0,
			'last_request' => null,
		) );

		$stats['total_requests']++;
		$stats['total_tokens'] += $usage['total_tokens'];
		$stats['last_request'] = current_time( 'mysql' );

		// Estimate cost based on model
		$model = $response['model'] ?? 'gpt-3.5-turbo';
		$cost_per_1k = $this->get_model_cost( $model );
		$stats['total_cost'] += ( $usage['total_tokens'] / 1000 ) * $cost_per_1k;

		update_option( 'ai_chatbot_openai_usage_stats', $stats );

		// Log detailed usage for analytics
		$this->log_detailed_usage( $response );
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
		return $models[ $model ]['cost_per_1k'] ?? 0.002;
	}

	/**
	 * Log detailed usage for analytics
	 *
	 * @param array $response API response.
	 * @since 1.0.0
	 */
	private function log_detailed_usage( $response ) {
		$usage_log = get_option( 'ai_chatbot_openai_usage_log', array() );
		
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'model' => $response['model'] ?? '',
			'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
			'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
			'total_tokens' => $response['usage']['total_tokens'] ?? 0,
		);

		$usage_log[] = $log_entry;

		// Keep only last 100 entries
		if ( count( $usage_log ) > 100 ) {
			$usage_log = array_slice( $usage_log, -100 );
		}

		update_option( 'ai_chatbot_openai_usage_log', $usage_log );
	}

	/**
	 * Get model information
	 *
	 * @param string $model_id Model identifier.
	 * @return array|null Model information.
	 * @since 1.0.0
	 */
	public function get_model_info( $model_id ) {
		$models = $this->get_available_models();
		return $models[ $model_id ] ?? null;
	}

	/**
	 * Check if model supports streaming
	 *
	 * @param string $model_id Model identifier.
	 * @return bool True if streaming is supported.
	 * @since 1.0.0
	 */
	public function supports_streaming( $model_id = null ) {
		// All OpenAI chat models support streaming
		$model_id = $model_id ?? get_option( 'ai_chatbot_openai_model', 'gpt-3.5-turbo' );
		$models = $this->get_available_models();
		return isset( $models[ $model_id ] );
	}

	/**
	 * Generate streaming response
	 *
	 * @param string $message User message.
	 * @param string $context Additional context.
	 * @param array  $options Optional parameters.
	 * @return Generator|WP_Error Streaming response or error.
	 * @since 1.0.0
	 */
	public function generate_streaming_response( $message, $context = '', $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'OpenAI API key is not configured.', 'ai-website-chatbot' ) );
		}

		// Prepare system message
		$system_message = $this->build_system_message( $context );

		// Prepare request data
		$data = array(
			'model' => $options['model'] ?? get_option( 'ai_chatbot_openai_model', 'gpt-3.5-turbo' ),
			'messages' => array(
				array(
					'role' => 'system',
					'content' => $system_message,
				),
				array(
					'role' => 'user',
					'content' => $message,
				),
			),
			'max_tokens' => $options['max_tokens'] ?? get_option( 'ai_chatbot_openai_max_tokens', 300 ),
			'temperature' => $options['temperature'] ?? get_option( 'ai_chatbot_openai_temperature', 0.7 ),
			'stream' => true,
		);

		$url = $this->api_base . 'chat/completions';

		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type' => 'application/json',
				'Accept' => 'text/event-stream',
				'Cache-Control' => 'no-cache',
			),
			'body' => wp_json_encode( $data ),
			'timeout' => 30,
			'stream' => true,
		);

		// Note: WordPress doesn't natively support streaming responses
		// This would require custom implementation or a different approach
		return new WP_Error( 'streaming_not_supported', __( 'Streaming responses are not yet supported in this implementation.', 'ai-website-chatbot' ) );
	}

	/**
	 * Get provider status
	 *
	 * @return array Provider status information.
	 * @since 1.0.0
	 */
	public function get_status() {
		$status = array(
			'configured' => $this->is_configured(),
			'connection' => false,
			'last_error' => null,
			'usage_stats' => $this->get_usage_stats(),
		);

		if ( $status['configured'] ) {
			$connection_test = $this->test_connection();
			if ( is_wp_error( $connection_test ) ) {
				$status['last_error'] = $connection_test->get_error_message();
			} else {
				$status['connection'] = true;
			}
		}

		return $status;
	}

	/**
	 * Reset usage statistics
	 *
	 * @return bool True if reset successful.
	 * @since 1.0.0
	 */
	public function reset_usage_stats() {
		$default_stats = array(
			'total_requests' => 0,
			'total_tokens' => 0,
			'total_cost' => 0,
			'last_request' => null,
		);

		update_option( 'ai_chatbot_openai_usage_stats', $default_stats );
		delete_option( 'ai_chatbot_openai_usage_log' );

		return true;
	}

	private function get_model() {
		$main_settings = get_option('ai_chatbot_settings', array());
		
		if (!empty($main_settings['model']) && $main_settings['ai_provider'] === 'openai') {
			return $main_settings['model'];
		}
		
		return get_option('ai_chatbot_openai_model', 'gpt-3.5-turbo');
	}

	private function get_temperature() {
		$main_settings = get_option('ai_chatbot_settings', array());
		
		if (isset($main_settings['temperature']) && $main_settings['ai_provider'] === 'openai') {
			return floatval($main_settings['temperature']);
		}
		
		return floatval(get_option('ai_chatbot_openai_temperature', 0.7));
	}

	private function get_max_tokens() {
		$main_settings = get_option('ai_chatbot_settings', array());
		
		if (isset($main_settings['max_tokens']) && $main_settings['ai_provider'] === 'openai') {
			return intval($main_settings['max_tokens']);
		}
		
		return intval(get_option('ai_chatbot_openai_max_tokens', 300));
	}
}