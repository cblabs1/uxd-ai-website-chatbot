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

		// Prepare system message
		$system_message = $this->build_system_message( $context );

		// Prepare request data
		$data = array(
			'model' => $options['model'] ?? $this->get_model(),
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
			'max_tokens' => $options['max_tokens'] ?? $this->get_max_tokens(),
			'temperature' => $options['temperature'] ?? $this->get_temperature(),
			'stream' => false,
		);

		// Add conversation history if provided
		if ( ! empty( $options['history'] ) ) {
			$history_messages = $this->format_conversation_history( $options['history'] );
			// Insert history before the current user message
			array_splice( $data['messages'], -1, 0, $history_messages );
		}

		$response = $this->make_request( 'chat/completions', $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from OpenAI.', 'ai-website-chatbot' ) );
		}

		// Log usage
		$this->log_usage( $response );

		return trim( $response['choices'][0]['message']['content'] );
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
	 * Build system message with context
	 *
	 * @param string $context Website context.
	 * @return string System message.
	 * @since 1.0.0
	 */
	private function build_system_message( $context = '' ) {
		$custom_prompt = get_option( 'ai_chatbot_custom_prompt', '' );
		
		if ( ! empty( $custom_prompt ) ) {
			$system_message = $custom_prompt;
		} else {
			$system_message = __( 'You are a helpful AI assistant for this website. Answer questions accurately and helpfully based on the provided context. If you don\'t know something, say so politely. Keep responses concise and friendly.', 'ai-website-chatbot' );
		}

		if ( ! empty( $context ) ) {
			$system_message .= "\n\n" . __( 'Website Context:', 'ai-website-chatbot' ) . "\n" . $context;
		}

		// Add website information
		$site_name = get_bloginfo( 'name' );
		$site_description = get_bloginfo( 'description' );
		
		if ( ! empty( $site_name ) ) {
			$system_message .= "\n\n" . sprintf( __( 'Website: %s', 'ai-website-chatbot' ), $site_name );
		}
		
		if ( ! empty( $site_description ) ) {
			$system_message .= "\n" . sprintf( __( 'Description: %s', 'ai-website-chatbot' ), $site_description );
		}

		return $system_message;
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
	 * Validate message content
	 *
	 * @param string $message Message to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 * @since 1.0.0
	 */
	public function validate_message( $message ) {
		// Check message length (OpenAI has token limits, not character limits)
		if ( empty( trim( $message ) ) ) {
			return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'ai-website-chatbot' ) );
		}

		// Estimate token count (rough approximation: 1 token ≈ 4 characters)
		$estimated_tokens = strlen( $message ) / 4;
		$max_tokens = 4000; // Leave room for system message and response

		if ( $estimated_tokens > $max_tokens ) {
			return new WP_Error( 'message_too_long', __( 'Message is too long. Please shorten your message.', 'ai-website-chatbot' ) );
		}

		return true;
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