<?php
/**
 * Anthropic Claude Provider Implementation
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
 * Claude provider class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Claude implements AI_Chatbot_Provider_Interface {

	/**
	 * API base URL
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $api_base = 'https://api.anthropic.com/v1/';

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
    
		if (!empty($main_settings['api_key']) && $main_settings['ai_provider'] === 'claude') {
			// New structure: settings stored in main array
			$this->api_key = $main_settings['api_key'];
		} else {
			// Fallback to old structure: individual options
			$this->api_key = get_option('ai_chatbot_claude_api_key', '');
		}
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 * @since 1.0.0
	 */
	public function get_name() {
		return 'claude';
	}

	/**
	 * Get provider display name
	 *
	 * @return string Provider display name.
	 * @since 1.0.0
	 */
	public function get_display_name() {
		return 'Anthropic Claude';
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if configured.
	 * @since 1.0.0
	 */
	public function is_configured() {
		$api_key = $this->api_key;
		error_log('Claude Provider - API Key check: ' . (empty($api_key) ? 'EMPTY' : 'Present (' . strlen($api_key) . ' chars)'));
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
			return new WP_Error( 'not_configured', __( 'Claude API key is not configured.', 'ai-website-chatbot' ) );
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
	 * @param string $context Additional context from website.
	 * @param array  $options Optional parameters.
	 * @return string|WP_Error AI response or WP_Error if failed.
	 * @since 1.0.0
	 */
	public function generate_response( $message, $context = '', $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Claude API key is not configured.', 'ai-website-chatbot' ) );
		}

		// Validate message
		$validation = $this->validate_message( $message );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Build system prompt
		$system_prompt = $this->build_system_message( $context );

		// Prepare request data
		$data = array(
			'model' => $options['model'] ?? $this->get_model(),
			'max_tokens' => $options['max_tokens'] ?? $this->get_max_tokens(),
			'system' => $system_prompt,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => $message,
				),
			),
		);

		// Add conversation history if provided
		if ( ! empty( $options['history'] ) ) {
			$history_messages = $this->format_conversation_history( $options['history'] );
			// Prepend history to current message
			$data['messages'] = array_merge( $history_messages, $data['messages'] );
		}

		$response = $this->make_request( 'messages', $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['content'][0]['text'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Claude.', 'ai-website-chatbot' ) );
		}

		// Log usage
		$this->log_usage( $response );

		return trim( $response['content'][0]['text'] );
	}

	/**
	 * Get available models
	 *
	 * @return array Available models.
	 * @since 1.0.0
	 */
	public function get_available_models() {
		return array(
			// Claude 3.5 models (Latest)
			'claude-3-5-sonnet-20241022' => array(
				'name' => 'Claude 3.5 Sonnet (New)',
				'description' => __( 'Latest Claude 3.5 Sonnet with improved coding and reasoning capabilities', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'context_length' => 200000,
				'cost_per_1k_input' => 3.00,
				'cost_per_1k_output' => 15.00,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'claude-3-5-sonnet-20240620' => array(
				'name' => 'Claude 3.5 Sonnet',
				'description' => __( 'Most intelligent model, combining top-tier performance with improved speed', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'context_length' => 200000,
				'cost_per_1k_input' => 3.00,
				'cost_per_1k_output' => 15.00,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'claude-3-5-haiku-20241022' => array(
				'name' => 'Claude 3.5 Haiku',
				'description' => __( 'Fastest model with improved instruction following and coding capabilities', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'context_length' => 200000,
				'cost_per_1k_input' => 0.80,
				'cost_per_1k_output' => 4.00,
				'supports_vision' => false,
				'supports_function_calling' => true,
			),
			// Claude 3 models
			'claude-3-opus-20240229' => array(
				'name' => 'Claude 3 Opus',
				'description' => __( 'Most powerful model for highly complex tasks', 'ai-website-chatbot' ),
				'max_tokens' => 4096,
				'context_length' => 200000,
				'cost_per_1k_input' => 15.00,
				'cost_per_1k_output' => 75.00,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'claude-3-sonnet-20240229' => array(
				'name' => 'Claude 3 Sonnet',
				'description' => __( 'Balanced performance and speed for enterprise workloads', 'ai-website-chatbot' ),
				'max_tokens' => 4096,
				'context_length' => 200000,
				'cost_per_1k_input' => 3.00,
				'cost_per_1k_output' => 15.00,
				'supports_vision' => true,
				'supports_function_calling' => true,
			),
			'claude-3-haiku-20240307' => array(
				'name' => 'Claude 3 Haiku',
				'description' => __( 'Fastest and most compact model for near-instant responsiveness', 'ai-website-chatbot' ),
				'max_tokens' => 4096,
				'context_length' => 200000,
				'cost_per_1k_input' => 0.25,
				'cost_per_1k_output' => 1.25,
				'supports_vision' => true,
				'supports_function_calling' => true,
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
		return 'claude-3-haiku-20240307';
	}

	/**
	 * Get provider configuration fields
	 *
	 * @return array Configuration fields.
	 * @since 1.0.0
	 */
	public function get_config_fields() {
		return array(
			'ai_chatbot_claude_api_key' => array(
				'label' => __( 'API Key', 'ai-website-chatbot' ),
				'type' => 'password',
				'description' => __( 'Your Anthropic Claude API key (starts with sk-ant-)', 'ai-website-chatbot' ),
				'required' => true,
			),
			'ai_chatbot_claude_model' => array(
				'label' => __( 'Model', 'ai-website-chatbot' ),
				'type' => 'select',
				'options' => wp_list_pluck( $this->get_available_models(), 'name' ),
				'description' => __( 'Choose the Claude model to use', 'ai-website-chatbot' ),
				'default' => 'claude-3-haiku-20240307',
			),
			'ai_chatbot_claude_max_tokens' => array(
				'label' => __( 'Max Tokens', 'ai-website-chatbot' ),
				'type' => 'number',
				'min' => 1,
				'max' => 8192,
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
		if ( empty( $config['ai_chatbot_claude_api_key'] ) ) {
			$errors[] = __( 'API key is required.', 'ai-website-chatbot' );
		} elseif ( ! preg_match( '/^sk-ant-[a-zA-Z0-9_-]+$/', $config['ai_chatbot_claude_api_key'] ) ) {
			$errors[] = __( 'Invalid API key format.', 'ai-website-chatbot' );
		}

		// Validate model
		$available_models = array_keys( $this->get_available_models() );
		if ( ! empty( $config['ai_chatbot_claude_model'] ) && ! in_array( $config['ai_chatbot_claude_model'], $available_models, true ) ) {
			$errors[] = __( 'Invalid model selected.', 'ai-website-chatbot' );
		}

		// Validate max tokens
		if ( isset( $config['ai_chatbot_claude_max_tokens'] ) ) {
			$tokens = intval( $config['ai_chatbot_claude_max_tokens'] );
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
		$stats = get_option( 'ai_chatbot_claude_usage_stats', array(
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
				'claude-3-haiku' => 1000,
				'claude-3-sonnet' => 1000,
				'claude-3-opus' => 1000,
				'claude-3-5-sonnet' => 1000,
			),
			'tokens_per_minute' => array(
				'claude-3-haiku' => 100000,
				'claude-3-sonnet' => 80000,
				'claude-3-opus' => 40000,
				'claude-3-5-sonnet' => 80000,
			),
			'requests_per_day' => array(
				'claude-3-haiku' => 1000000,
				'claude-3-sonnet' => 1000000,
				'claude-3-opus' => 1000000,
				'claude-3-5-sonnet' => 1000000,
			),
		);
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
	 * Validate message content
	 *
	 * @param string $message Message to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 * @since 1.0.0
	 */
	public function validate_message( $message ) {
		// Check message length
		if ( empty( trim( $message ) ) ) {
			return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'ai-website-chatbot' ) );
		}

		// Claude has a generous context window, but let's be reasonable
		if ( strlen( $message ) > 100000 ) {
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

		update_option( 'ai_chatbot_claude_usage_stats', $default_stats );
		delete_option( 'ai_chatbot_claude_usage_log' );

		return true;
	}

	/**
	 * Make API request to Claude
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
				'x-api-key' => $this->api_key,
				'Content-Type' => 'application/json',
				'anthropic-version' => '2023-06-01',
				'User-Agent' => 'WordPress-AI-Chatbot/' . ( defined( 'AI_CHATBOT_VERSION' ) ? AI_CHATBOT_VERSION : '1.0.0' ),
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
			return new WP_Error( 'invalid_json', __( 'Invalid JSON response from Claude.', 'ai-website-chatbot' ) );
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
			$system_message = __( 'You are a helpful AI assistant for this website. Answer questions accurately and helpfully based on the provided context. If you don\'t know something based on the context, say so politely. Keep responses concise and friendly. Be helpful, harmless, and honest.', 'ai-website-chatbot' );
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
		$stats = get_option( 'ai_chatbot_claude_usage_stats', array(
			'total_requests' => 0,
			'total_tokens' => 0,
			'total_cost' => 0,
			'last_request' => null,
		) );

		$stats['total_requests']++;
		$stats['total_tokens'] += $usage['output_tokens'] + $usage['input_tokens'];
		$stats['last_request'] = current_time( 'mysql' );

		// Estimate cost based on model
		$model = $response['model'] ?? 'claude-3-haiku-20240307';
		$cost_per_1k = $this->get_model_cost( $model );
		$total_tokens = $usage['output_tokens'] + $usage['input_tokens'];
		$stats['total_cost'] += ( $total_tokens / 1000 ) * $cost_per_1k;

		update_option( 'ai_chatbot_claude_usage_stats', $stats );

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
		return $models[ $model ]['cost_per_1k'] ?? 0.25;
	}

	/**
	 * Log detailed usage for analytics
	 *
	 * @param array $response API response.
	 * @since 1.0.0
	 */
	private function log_detailed_usage( $response ) {
		$usage_log = get_option( 'ai_chatbot_claude_usage_log', array() );
		
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'model' => $response['model'] ?? '',
			'input_tokens' => $response['usage']['input_tokens'] ?? 0,
			'output_tokens' => $response['usage']['output_tokens'] ?? 0,
			'total_tokens' => ( $response['usage']['input_tokens'] ?? 0 ) + ( $response['usage']['output_tokens'] ?? 0 ),
		);

		$usage_log[] = $log_entry;

		// Keep only last 100 entries
		if ( count( $usage_log ) > 100 ) {
			$usage_log = array_slice( $usage_log, -100 );
		}

		update_option( 'ai_chatbot_claude_usage_log', $usage_log );
	}

	private function get_model() {
		$main_settings = get_option('ai_chatbot_settings', array());
		
		if (!empty($main_settings['model']) && $main_settings['ai_provider'] === 'claude') {
			return $main_settings['model'];
		}
		
		return get_option('ai_chatbot_claude_model', 'claude-3-haiku-20240307');
	}

	private function get_max_tokens() {
		$main_settings = get_option('ai_chatbot_settings', array());
		
		if (isset($main_settings['max_tokens']) && $main_settings['ai_provider'] === 'claude') {
			return intval($main_settings['max_tokens']);
		}
		
		return intval(get_option('ai_chatbot_claude_max_tokens', 300));
	}
}