<?php
/**
 * Google Gemini Provider Implementation
 * Uses AI_Chatbot_Provider_Base for common functionality
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
 * Gemini provider class - NOW EXTENDS BASE CLASS
 *
 * @since 1.0.0
 */
class AI_Chatbot_Gemini extends AI_Chatbot_Provider_Base {

	/**
	 * Initialize provider-specific settings
	 */
	public function __construct() {
		$this->api_base = 'https://generativelanguage.googleapis.com/v1beta/';
		$this->provider_name = 'gemini';
		
		// Call parent constructor to load API key
		parent::__construct();
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
	 * Generate response from AI
	 * Uses base class for common functionality, only implements Gemini-specific logic
	 *
	 * @param string $message User message.
	 * @param string|array $context Additional context from website or conversation history.
	 * @param array  $options Optional parameters.
	 * @return array|WP_Error AI response array or WP_Error if failed.
	 * @since 1.0.0
	 */
	public function generate_response( $message, $context = '', $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Gemini API key is not configured.', 'ai-website-chatbot' ) );
		}

		// Get or generate session and conversation IDs (from base class)
		$session_id = $options['session_id'] ?? $this->get_or_generate_session_id();
		$conversation_id = $options['conversation_id'] ?? $this->generate_conversation_id($session_id);

		// Process training and cache checks (using base class methods correctly)
		// Check training data first (exact match)
		$training_response = $this->check_training_data($message);
		if (!is_wp_error($training_response) && !empty($training_response)) {
			error_log('Gemini Provider: Found exact training match for: ' . $message);
			
			// Log the training response (from base class)
			$this->log_conversation($conversation_id, $message, $training_response, 0, 'training');
			
			return array(
				'response' => $training_response,
				'tokens_used' => 0,
				'model' => 'training',
				'source' => 'training',
				'session_id' => $session_id,
				'conversation_id' => $conversation_id,
				'response_time' => 0
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
				'source' => 'training',
				'session_id' => $session_id,
				'conversation_id' => $conversation_id,
				'response_time' => 0
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
				'source' => 'cache',
				'session_id' => $session_id,
				'conversation_id' => $conversation_id,
				'response_time' => 0
			);
		}

		// Proceed with API call
		error_log('Gemini Provider: Making API call for: ' . $message);

		// Validate message (from base class)
		$validation = $this->validate_message( $message );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Get settings (from base class)
		$model = $options['model'] ?? $this->get_model();
		$max_tokens = $options['max_tokens'] ?? $this->get_max_tokens();
		$temperature = $options['temperature'] ?? $this->get_temperature();

		// Add debug logging
		error_log('Gemini Provider: Using model: ' . $model);
		error_log('Gemini Provider: Max tokens: ' . $max_tokens);
		error_log('Gemini Provider: Temperature: ' . $temperature);

		// Build conversation history (from base class)
		$conversation_history = $this->get_chat_conversation_history($conversation_id, 3);
		$contents = array();

		// Add conversation history in Gemini format
		foreach (array_reverse($conversation_history) as $history_item) {
			$user_msg = trim($history_item['user_message'] ?? '');
			$ai_msg = trim($history_item['ai_response'] ?? '');
			
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

		// Add current message
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
				'parts' => array(array('text' => $message))
			);
		}

		// Build system instruction (from base class)
		$system_instruction = $this->build_system_message($context);

		// Prepare request data for Gemini
		$data = array(
			'contents' => $contents,
			'generationConfig' => array(
				'maxOutputTokens' => (int) $max_tokens,
				'temperature' => (float) $temperature,
			),
		);

		// Add system instruction if available
		if (!empty($system_instruction)) {
			$data['systemInstruction'] = array(
				'parts' => array(array('text' => $system_instruction))
			);
		}

		error_log('Gemini API Request Data: ' . wp_json_encode($data, JSON_PRETTY_PRINT));

		// Make API request (Gemini-specific implementation)
		$endpoint = 'models/' . $model . ':generateContent?key=' . $this->api_key;
		$response = $this->make_api_request( $endpoint, $data );

		if ( is_wp_error( $response ) ) {
			error_log('Gemini API Error: ' . $response->get_error_message());
			return $response;
		}

		// Extract response text (Gemini-specific)
		$response_text = '';
		if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
			$response_text = $response['candidates'][0]['content']['parts'][0]['text'];
		}

		if (empty($response_text)) {
			return new WP_Error('empty_response', __('Empty response from Gemini API.', 'ai-website-chatbot'));
		}

		// Calculate tokens and response time
		$tokens_used = $this->extract_tokens_from_response($response);
		$response_time = isset($options['start_time']) ? (microtime(true) - $options['start_time']) * 1000 : 0;

		// Log the API response (from base class)
		$this->log_conversation($conversation_id, $message, $response_text, $response_time, 'api');

		// Log usage statistics (from base class)
		$this->log_usage( $response );

		// Cache the response (from base class)
		$this->cache_response($message, $response_text);

		return array(
			'response' => trim($response_text),
			'tokens_used' => $tokens_used,
			'model' => $model,
			'source' => 'api',
			'session_id' => $session_id,
			'conversation_id' => $conversation_id,
			'response_time' => $response_time
		);
	}

	// ==========================================
	// GEMINI-SPECIFIC IMPLEMENTATIONS OF ABSTRACT METHODS
	// ==========================================

	/**
	 * Make API request to Gemini
	 * Implementation of abstract method
	 *
	 * @param string $endpoint API endpoint with query parameters
	 * @param array  $data Request data
	 * @return array|WP_Error API response or error
	 * @since 1.0.0
	 */
	protected function make_api_request( $endpoint, $data ) {
		$url = $this->api_base . $endpoint;

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'User-Agent' => 'AI-Website-Chatbot/' . (defined('AI_CHATBOT_VERSION') ? AI_CHATBOT_VERSION : '1.0.0'),
			),
			'body' => wp_json_encode( $data ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_request_failed', __( 'Failed to connect to Gemini API.', 'ai-website-chatbot' ) );
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
	 * Build system message with context
	 * Implementation of abstract method
	 *
	 * @param string $context Website context
	 * @return string System message
	 * @since 1.0.0
	 */
	protected function build_system_message( $context ) {
		$system_parts = array();

		// Add system prompt if available
		$system_prompt = get_option( 'ai_chatbot_system_prompt', '' );
		if ( ! empty( $system_prompt ) ) {
			$system_parts[] = $system_prompt;
		}

		// Add website context
		if ( ! empty( $context ) && is_string( $context ) ) {
			$system_parts[] = __( 'Website information:', 'ai-website-chatbot' ) . "\n" . $context;
		}

		return implode( "\n\n", $system_parts );
	}

	/**
	 * Get cost per 1K tokens for a model
	 * Implementation of abstract method
	 *
	 * @param string $model Model name
	 * @return float Cost per 1K tokens
	 * @since 1.0.0
	 */
	protected function get_model_cost( $model ) {
		$costs = array(
			'gemini-1.5-flash' => 0.000075,
			'gemini-1.5-flash-8b' => 0.0000375,
			'gemini-1.5-pro' => 0.00125,
			'gemini-2.0-flash-exp' => 0.000075
		);

		return $costs[ $model ] ?? 0.000075;
	}

	// ==========================================
	// INTERFACE IMPLEMENTATIONS (Provider-specific)
	// ==========================================

	/**
	 * Get available models
	 *
	 * @return array Available models
	 * @since 1.0.0
	 */
	public function get_available_models() {
		return array(
			'gemini-1.5-flash' => 'Gemini 1.5 Flash',
			'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B', 
			'gemini-1.5-pro' => 'Gemini 1.5 Pro',
			'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental)'
		);
	}

	/**
	 * Get default model
	 *
	 * @return string Default model identifier
	 * @since 1.0.0
	 */
	public function get_default_model() {
		return 'gemini-1.5-flash';
	}

	/**
	 * Get provider configuration fields
	 *
	 * @return array Configuration fields
	 * @since 1.0.0
	 */
	public function get_config_fields() {
		return array(
			'api_key' => array(
				'label' => __('Gemini API Key', 'ai-website-chatbot'),
				'type' => 'password',
				'description' => __('Your Google Gemini API key. Get it from https://aistudio.google.com/app/apikey', 'ai-website-chatbot'),
				'required' => true
			),
			'model' => array(
				'label' => __('Model', 'ai-website-chatbot'),
				'type' => 'select',
				'options' => $this->get_available_models(),
				'default' => $this->get_default_model(),
				'description' => __('Choose the Gemini model to use for responses.', 'ai-website-chatbot')
			),
			'max_tokens' => array(
				'label' => __('Max Tokens', 'ai-website-chatbot'),
				'type' => 'number',
				'default' => 300,
				'min' => 50,
				'max' => 4000,
				'description' => __('Maximum number of tokens in the response.', 'ai-website-chatbot')
			),
			'temperature' => array(
				'label' => __('Temperature', 'ai-website-chatbot'),
				'type' => 'range',
				'default' => 0.7,
				'min' => 0,
				'max' => 1,
				'step' => 0.1,
				'description' => __('Controls randomness: 0 is focused, 1 is creative.', 'ai-website-chatbot')
			)
		);
	}

	/**
	 * Get rate limits for Gemini
	 * Override base class with Gemini-specific limits
	 *
	 * @return array Rate limit information
	 * @since 1.0.0
	 */
	public function get_rate_limits() {
		return array(
			'requests_per_minute' => 15,
			'tokens_per_minute' => 32000,
			'requests_per_day' => 1500,
			'current_usage' => 0
		);
	}
}