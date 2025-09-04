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
	 * @param string|array $context Additional context from website or conversation history.
	 * @param array  $options Optional parameters.
	 * @return string|WP_Error AI response or WP_Error if failed.
	 * @since 1.0.0
	 */
	public function generate_response( $message, $context = '', $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Gemini API key is not configured.', 'ai-website-chatbot' ) );
		}

		// Handle conversation history if provided as array
		$conversation_contents = array();
		
		if ( is_array( $context ) && !empty( $context ) ) {
			// Build conversation from history array
			foreach ( $context as $item ) {
				if ( isset( $item['sender'], $item['message'] ) ) {
					$conversation_contents[] = array(
						'role' => $item['sender'] === 'user' ? 'user' : 'model',
						'parts' => array( array( 'text' => $item['message'] ) )
					);
				}
			}
		} else {
			// Build simple prompt with context string
			$full_prompt = $this->build_full_prompt( $message, $context, $options );
		}

		// Add current user message
		$conversation_contents[] = array(
			'role' => 'user',
			'parts' => array( array( 'text' => isset($full_prompt) ? $full_prompt : $message ) )
		);

		// Get model
		$model = $options['model'] ?? get_option( 'ai_chatbot_gemini_model', 'gemini-2.0-flash' );

		// Prepare request data
		$data = array(
			'contents' => $conversation_contents,
			'generationConfig' => array(
				'temperature' => floatval( $options['temperature'] ?? get_option( 'ai_chatbot_gemini_temperature', 0.7 ) ),
				'maxOutputTokens' => intval( $options['max_tokens'] ?? get_option( 'ai_chatbot_gemini_max_tokens', 1000 ) ),
				'topP' => floatval( get_option( 'ai_chatbot_gemini_top_p', 0.8 ) ),
				'topK' => intval( get_option( 'ai_chatbot_gemini_top_k', 40 ) )
			),
		);

		// Add system instruction if available
		$system_prompt = get_option( 'ai_chatbot_system_prompt', '' );
		if ( !empty( $system_prompt ) ) {
			$data['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system_prompt ) )
			);
		}

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
}