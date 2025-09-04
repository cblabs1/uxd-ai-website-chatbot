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
		$this->api_key = get_option( 'ai_chatbot_gemini_api_key', '' );
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
		return ! empty( $this->api_key ) && strlen( $this->api_key ) >= 20;
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
	 * @param string $context Additional context from website.
	 * @param array  $options Optional parameters.
	 * @return string|WP_Error AI response or WP_Error if failed.
	 * @since 1.0.0
	 */
	public function generate_response( $message, $context = '', $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Gemini API key is not configured.', 'ai-website-chatbot' ) );
		}

		// Build full prompt with context
		$full_prompt = $this->build_full_prompt( $message, $context, $options );

		// Get model
		$model = $options['model'] ?? get_option( 'ai_chatbot_gemini_model', 'gemini-pro' );

		// Prepare request data
		$data = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $full_prompt )
					)
				)
			),
			'generationConfig' => array(
				'temperature' => $options['temperature'] ?? get_option( 'ai_chatbot_gemini_temperature', 0.7 ),
				'maxOutputTokens' => $options['max_tokens'] ?? get_option( 'ai_chatbot_gemini_max_tokens', 300 ),
			),
		);

		$response = $this->make_request( "models/{$model}:generateContent", $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Gemini.', 'ai-website-chatbot' ) );
		}

		// Log usage
		$this->log_usage( $response );

		return trim( $response['candidates'][0]['content']['parts'][0]['text'] );
	}

	/**
	 * Get available models
	 *
	 * @return array Available models.
	 * @since 1.0.0
	 */
	public function get_available_models() {
		return array(
			'gemini-pro' => array(
				'name' => 'Gemini Pro',
				'description' => __( 'Best model for text-based tasks', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'cost_per_1k' => 0.5,
			),
			'gemini-pro-vision' => array(
				'name' => 'Gemini Pro Vision',
				'description' => __( 'Multimodal model with vision capabilities', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'cost_per_1k' => 0.5,
			),
			'gemini-2.0-flash' => array(
				'name' => 'Gemini 2.0 Flash',
				'description' => __( 'Fast and efficient for simple tasks', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'cost_per_1k' => 0.1,
			),
			'gemini-1.5-flash-8b' => array(
				'name' => 'Gemini 1.5 Flash-8B',
				'description' => __( 'Fast and efficient for simple tasks', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'cost_per_1k' => 0.1,
			),
			'gemini-1.5-flash' => array(
				'name' => 'Gemini 1.5 Flash',
				'description' => __( 'Fast and efficient for simple tasks', 'ai-website-chatbot' ),
				'max_tokens' => 8192,
				'cost_per_1k' => 0.1,
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
		return 'gemini-pro';
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
				'default' => 'gemini-pro',
			),
			'ai_chatbot_gemini_temperature' => array(
				'label' => __( 'Temperature', 'ai-website-chatbot' ),
				'type' => 'number',
				'min' => 0,
				'max' => 2,
				'step' => 0.1,
				'description' => __( 'Controls randomness in responses', 'ai-website-chatbot' ),
				'default' => 0.7,
			),
			'ai_chatbot_gemini_max_tokens' => array(
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
		if ( empty( $config['ai_chatbot_gemini_api_key'] ) ) {
			$errors[] = __( 'API key is required.', 'ai-website-chatbot' );
		} elseif ( ! preg_match( '/^[a-zA-Z0-9_-]{35,45}$/', $config['ai_chatbot_gemini_api_key'] ) ) {
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
		$stats = get_option( 'ai_chatbot_gemini_usage_stats', array(
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
				'free_tier' => 60,
				'paid_tier' => 1000,
			),
			'requests_per_day' => array(
				'free_tier' => 1500,
				'paid_tier' => 50000,
			),
		);
	}

	/**
	 * Make API request to Gemini
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data Request data.
	 * @param string $method HTTP method.
	 * @return array|WP_Error Response data or WP_Error if failed.
	 * @since 1.0.0
	 */
	private function make_request( $endpoint, $data = array(), $method = 'POST' ) {
		$url = $this->api_base . $endpoint . '?key=' . $this->api_key;

		$args = array(
			'method' => $method,
			'headers' => array(
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
			return new WP_Error( 'invalid_json', __( 'Invalid JSON response from Gemini.', 'ai-website-chatbot' ) );
		}

		return $decoded_body;
	}

	/**
	 * Build full prompt with context and history
	 *
	 * @param string $message User message.
	 * @param string $context Website context.
	 * @param array  $options Options including history.
	 * @return string Full prompt.
	 * @since 1.0.0
	 */
	private function build_full_prompt( $message, $context = '', $options = array() ) {
		$custom_prompt = get_option( 'ai_chatbot_custom_prompt', '' );
		
		if ( ! empty( $custom_prompt ) ) {
			$system_message = $custom_prompt;
		} else {
			$system_message = __( 'You are a helpful AI assistant for this website. Answer questions accurately and helpfully based on the provided context. If you don\'t know something based on the context, say so politely. Keep responses concise and friendly.', 'ai-website-chatbot' );
		}

		$full_prompt = $system_message;

		// Add website information
		$site_name = get_bloginfo( 'name' );
		$site_description = get_bloginfo( 'description' );
		
		if ( ! empty( $site_name ) ) {
			$full_prompt .= "\n\n" . sprintf( __( 'Website: %s', 'ai-website-chatbot' ), $site_name );
		}
		
		if ( ! empty( $site_description ) ) {
			$full_prompt .= "\n" . sprintf( __( 'Description: %s', 'ai-website-chatbot' ), $site_description );
		}

		// Add context if provided
		if ( ! empty( $context ) ) {
			$full_prompt .= "\n\n" . __( 'Website Context:', 'ai-website-chatbot' ) . "\n" . $context;
		}

		// Add conversation history if provided
		if ( ! empty( $options['history'] ) ) {
			$full_prompt .= "\n\n" . __( 'Recent conversation:', 'ai-website-chatbot' );
			foreach ( $options['history'] as $item ) {
				$full_prompt .= "\nUser: " . $item['user_message'];
				$full_prompt .= "\nAssistant: " . $item['bot_response'];
			}
		}

		// Add current user message
		$full_prompt .= "\n\n" . __( 'Current user question:', 'ai-website-chatbot' ) . "\n" . $message;

		return $full_prompt;
	}

	/**
	 * Log API usage statistics
	 *
	 * @param array $response API response.
	 * @since 1.0.0
	 */
	private function log_usage( $response ) {
		// Gemini doesn't provide detailed usage stats in the same way as other providers
		// We'll estimate based on the response
		$stats = get_option( 'ai_chatbot_gemini_usage_stats', array(
			'total_requests' => 0,
			'total_tokens' => 0,
			'total_cost' => 0,
			'last_request' => null,
		) );

		$stats['total_requests']++;
		$stats['last_request'] = current_time( 'mysql' );

		// Estimate tokens (rough approximation)
		if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$response_text = $response['candidates'][0]['content']['parts'][0]['text'];
			$estimated_tokens = strlen( $response_text ) / 4; // Rough estimate
			$stats['total_tokens'] += $estimated_tokens;

			// Estimate cost
			$model = get_option( 'ai_chatbot_gemini_model', 'gemini-pro' );
			$cost_per_1k = $this->get_model_cost( $model );
			$stats['total_cost'] += ( $estimated_tokens / 1000 ) * $cost_per_1k;
		}

		update_option( 'ai_chatbot_gemini_usage_stats', $stats );

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
		return $models[ $model ]['cost_per_1k'] ?? 0.5;
	}

	/**
	 * Log detailed usage for analytics
	 *
	 * @param array $response API response.
	 * @since 1.0.0
	 */
	private function log_detailed_usage( $response ) {
		$usage_log = get_option( 'ai_chatbot_gemini_usage_log', array() );
		
		// Estimate tokens from response
		$estimated_tokens = 0;
		if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$response_text = $response['candidates'][0]['content']['parts'][0]['text'];
			$estimated_tokens = strlen( $response_text ) / 4;
		}

		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'model' => get_option( 'ai_chatbot_gemini_model', 'gemini-pro' ),
			'estimated_tokens' => $estimated_tokens,
		);

		$usage_log[] = $log_entry;

		// Keep only last 100 entries
		if ( count( $usage_log ) > 100 ) {
			$usage_log = array_slice( $usage_log, -100 );
		}

		update_option( 'ai_chatbot_gemini_usage_log', $usage_log );
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

		// Gemini has reasonable limits
		if ( strlen( $message ) > 8000 ) {
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

		update_option( 'ai_chatbot_gemini_usage_stats', $default_stats );
		delete_option( 'ai_chatbot_gemini_usage_log' );

		return true;
	}
}