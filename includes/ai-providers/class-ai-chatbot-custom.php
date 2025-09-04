<?php
/**
 * Custom AI Provider for AI Chatbot
 *
 * @package AI_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom AI Provider Class
 *
 * This class handles interactions with custom AI providers or APIs
 * that are not officially supported by the plugin.
 */
class AI_Chatbot_Custom implements AI_Chatbot_Provider_Interface {

    /**
     * API base URL
     *
     * @var string
     */
    private $api_base;

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->api_base = get_option( 'ai_chatbot_custom_api_base', '' );
        $this->api_key = get_option( 'ai_chatbot_custom_api_key', '' );
    }

    /**
     * Get provider name
     *
     * @return string Provider name.
     * @since 1.0.0
     */
    public function get_name() {
        return 'custom';
    }

    /**
     * Get provider display name
     *
     * @return string Provider display name.
     * @since 1.0.0
     */
    public function get_display_name() {
        return get_option( 'ai_chatbot_custom_display_name', 'Custom AI Provider' );
    }

    /**
     * Check if provider is configured
     *
     * @return bool True if configured.
     * @since 1.0.0
     */
    public function is_configured() {
        return ! empty( $this->api_base ) && ! empty( $this->api_key );
    }

    /**
     * Test API connection
     *
     * @return bool|WP_Error True if connection successful, WP_Error if failed.
     * @since 1.0.0
     */
    public function test_connection() {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'not_configured', __( 'Custom API is not configured.', 'ai-website-chatbot' ) );
        }

        $test_endpoint = get_option( 'ai_chatbot_custom_test_endpoint', '/test' );
        $response = $this->make_request( $test_endpoint, array(), 'GET' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Check for success response based on custom criteria
        $success_field = get_option( 'ai_chatbot_custom_success_field', 'status' );
        $success_value = get_option( 'ai_chatbot_custom_success_value', 'ok' );

        if ( isset( $response[ $success_field ] ) && $response[ $success_field ] === $success_value ) {
            return true;
        }

        return new WP_Error( 'test_failed', __( 'Failed to connect to custom AI provider.', 'ai-website-chatbot' ) );
    }

    /**
     * Generate response from AI
     *
     * @param string $message User message.
     * @param string $context Additional context from website.
     * @param array $options Optional parameters.
     * @return string|WP_Error AI response or WP_Error if failed.
     * @since 1.0.0
     */
    public function generate_response( $message, $context = '', $options = array() ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'not_configured', __( 'Custom AI provider is not configured.', 'ai-website-chatbot' ) );
        }

        // Build request data based on custom format
        $data = $this->build_request_data( $message, $context, $options );
        $endpoint = get_option( 'ai_chatbot_custom_chat_endpoint', '/chat' );

        $response = $this->make_request( $endpoint, $data );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Extract response content based on custom field mapping
        $response_field = get_option( 'ai_chatbot_custom_response_field', 'response' );
        $response_content = $this->extract_response_content( $response, $response_field );

        if ( empty( $response_content ) ) {
            return new WP_Error( 'invalid_response', __( 'Invalid response from custom AI provider.', 'ai-website-chatbot' ) );
        }

        // Log usage
        $this->log_usage( $response );

        return trim( $response_content );
    }

    /**
     * Get available models
     *
     * @return array Available models.
     * @since 1.0.0
     */
    public function get_available_models() {
        $models_option = get_option( 'ai_chatbot_custom_models', array() );
        
        if ( empty( $models_option ) ) {
            return array(
                'default' => array(
                    'name' => 'Default Model',
                    'description' => __( 'Default custom AI model', 'ai-website-chatbot' ),
                    'max_tokens' => 2048,
                    'cost_per_1k' => 0.001,
                ),
            );
        }

        return $models_option;
    }

    /**
     * Get default model
     *
     * @return string Default model identifier.
     * @since 1.0.0
     */
    public function get_default_model() {
        return get_option( 'ai_chatbot_custom_default_model', 'default' );
    }

    /**
     * Get provider configuration fields
     *
     * @return array Configuration fields.
     * @since 1.0.0
     */
    public function get_config_fields() {
        return array(
            'ai_chatbot_custom_display_name' => array(
                'label' => __( 'Provider Name', 'ai-website-chatbot' ),
                'type' => 'text',
                'description' => __( 'Display name for your custom AI provider', 'ai-website-chatbot' ),
                'default' => 'Custom AI Provider',
            ),
            'ai_chatbot_custom_api_base' => array(
                'label' => __( 'API Base URL', 'ai-website-chatbot' ),
                'type' => 'url',
                'description' => __( 'Base URL for your custom API (e.g., https://api.example.com)', 'ai-website-chatbot' ),
                'required' => true,
            ),
            'ai_chatbot_custom_api_key' => array(
                'label' => __( 'API Key', 'ai-website-chatbot' ),
                'type' => 'password',
                'description' => __( 'Your custom API authentication key', 'ai-website-chatbot' ),
                'required' => true,
            ),
            'ai_chatbot_custom_chat_endpoint' => array(
                'label' => __( 'Chat Endpoint', 'ai-website-chatbot' ),
                'type' => 'text',
                'description' => __( 'API endpoint for chat completions (e.g., /v1/chat)', 'ai-website-chatbot' ),
                'default' => '/chat',
            ),
            'ai_chatbot_custom_test_endpoint' => array(
                'label' => __( 'Test Endpoint', 'ai-website-chatbot' ),
                'type' => 'text',
                'description' => __( 'API endpoint for testing connection (e.g., /status)', 'ai-website-chatbot' ),
                'default' => '/test',
            ),
            'ai_chatbot_custom_auth_method' => array(
                'label' => __( 'Authentication Method', 'ai-website-chatbot' ),
                'type' => 'select',
                'options' => array(
                    'bearer' => 'Bearer Token',
                    'api_key' => 'API Key Header',
                    'basic' => 'Basic Auth',
                    'custom' => 'Custom Header',
                ),
                'description' => __( 'How to authenticate with your API', 'ai-website-chatbot' ),
                'default' => 'bearer',
            ),
            'ai_chatbot_custom_auth_header' => array(
                'label' => __( 'Custom Auth Header', 'ai-website-chatbot' ),
                'type' => 'text',
                'description' => __( 'Custom header name for authentication (if using custom method)', 'ai-website-chatbot' ),
                'default' => 'X-API-Key',
            ),
            'ai_chatbot_custom_response_field' => array(
                'label' => __( 'Response Field', 'ai-website-chatbot' ),
                'type' => 'text',
                'description' => __( 'JSON field path for response content (e.g., data.response or choices.0.message.content)', 'ai-website-chatbot' ),
                'default' => 'response',
            ),
            'ai_chatbot_custom_request_format' => array(
                'label' => __( 'Request Format', 'ai-website-chatbot' ),
                'type' => 'select',
                'options' => array(
                    'openai' => 'OpenAI Compatible',
                    'simple' => 'Simple Message',
                    'custom' => 'Custom JSON',
                ),
                'description' => __( 'Format for sending requests to your API', 'ai-website-chatbot' ),
                'default' => 'simple',
            ),
            'ai_chatbot_custom_max_tokens' => array(
                'label' => __( 'Max Tokens', 'ai-website-chatbot' ),
                'type' => 'number',
                'min' => 1,
                'max' => 8192,
                'description' => __( 'Maximum length of the response', 'ai-website-chatbot' ),
                'default' => 300,
            ),
            'ai_chatbot_custom_temperature' => array(
                'label' => __( 'Temperature', 'ai-website-chatbot' ),
                'type' => 'number',
                'min' => 0,
                'max' => 2,
                'step' => 0.1,
                'description' => __( 'Controls randomness (0 = focused, 2 = creative)', 'ai-website-chatbot' ),
                'default' => 0.7,
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

        // Validate API Base URL
        if ( empty( $config['ai_chatbot_custom_api_base'] ) ) {
            $errors[] = __( 'API Base URL is required.', 'ai-website-chatbot' );
        } elseif ( ! filter_var( $config['ai_chatbot_custom_api_base'], FILTER_VALIDATE_URL ) ) {
            $errors[] = __( 'Invalid API Base URL format.', 'ai-website-chatbot' );
        }

        // Validate API Key
        if ( empty( $config['ai_chatbot_custom_api_key'] ) ) {
            $errors[] = __( 'API key is required.', 'ai-website-chatbot' );
        }

        // Validate endpoints
        if ( ! empty( $config['ai_chatbot_custom_chat_endpoint'] ) && ! preg_match( '/^\/[a-zA-Z0-9\/_-]*$/', $config['ai_chatbot_custom_chat_endpoint'] ) ) {
            $errors[] = __( 'Invalid chat endpoint format.', 'ai-website-chatbot' );
        }

        // Validate temperature
        if ( isset( $config['ai_chatbot_custom_temperature'] ) ) {
            $temp = floatval( $config['ai_chatbot_custom_temperature'] );
            if ( $temp < 0 || $temp > 2 ) {
                $errors[] = __( 'Temperature must be between 0 and 2.', 'ai-website-chatbot' );
            }
        }

        // Validate max tokens
        if ( isset( $config['ai_chatbot_custom_max_tokens'] ) ) {
            $tokens = intval( $config['ai_chatbot_custom_max_tokens'] );
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
        $stats = get_option( 'ai_chatbot_custom_usage_stats', array(
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
        return get_option( 'ai_chatbot_custom_rate_limits', array(
            'requests_per_minute' => 100,
            'tokens_per_minute' => 10000,
            'requests_per_day' => 1000,
        ) );
    }

    /**
     * Validate message content
     *
     * @param string $message Message to validate.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     * @since 1.0.0
     */
    public function validate_message( $message ) {
        if ( empty( trim( $message ) ) ) {
            return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'ai-website-chatbot' ) );
        }

        $max_length = get_option( 'ai_chatbot_custom_max_message_length', 2000 );
        if ( strlen( $message ) > $max_length ) {
            return new WP_Error( 'message_too_long', sprintf( __( 'Message is too long. Maximum length is %d characters.', 'ai-website-chatbot' ), $max_length ) );
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

        update_option( 'ai_chatbot_custom_usage_stats', $default_stats );
        delete_option( 'ai_chatbot_custom_usage_log' );

        return true;
    }

    /**
     * Check if model supports streaming
     *
     * @param string $model_id Model identifier.
     * @return bool True if streaming is supported.
     * @since 1.0.0
     */
    public function supports_streaming( $model_id = null ) {
        return get_option( 'ai_chatbot_custom_streaming_support', false );
    }

    /**
     * Make API request to custom provider
     *
     * @param string $endpoint API endpoint.
     * @param array $data Request data.
     * @param string $method HTTP method.
     * @return array|WP_Error Response data or WP_Error if failed.
     * @since 1.0.0
     */
    private function make_request( $endpoint, $data = array(), $method = 'POST' ) {
        $url = rtrim( $this->api_base, '/' ) . '/' . ltrim( $endpoint, '/' );

        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress-AI-Chatbot/' . AI_CHATBOT_VERSION,
        );

        // Add authentication based on configured method
        $headers = array_merge( $headers, $this->get_auth_headers() );

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => get_option( 'ai_chatbot_custom_timeout', 30 ),
            'sslverify' => get_option( 'ai_chatbot_custom_ssl_verify', true ),
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

        if ( $status_code < 200 || $status_code >= 300 ) {
            $error_message = isset( $decoded_body['error'] )
                ? ( is_string( $decoded_body['error'] ) ? $decoded_body['error'] : $decoded_body['error']['message'] ?? 'Unknown error' )
                : sprintf( __( 'HTTP %d error', 'ai-website-chatbot' ), $status_code );

            return new WP_Error( 'api_error', $error_message, array( 'status_code' => $status_code ) );
        }

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'invalid_json', __( 'Invalid JSON response from custom provider.', 'ai-website-chatbot' ) );
        }

        return $decoded_body;
    }

    /**
     * Get authentication headers
     *
     * @return array Authentication headers.
     * @since 1.0.0
     */
    private function get_auth_headers() {
        $auth_method = get_option( 'ai_chatbot_custom_auth_method', 'bearer' );
        $headers = array();

        switch ( $auth_method ) {
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $this->api_key;
                break;

            case 'api_key':
                $headers['X-API-Key'] = $this->api_key;
                break;

            case 'basic':
                $headers['Authorization'] = 'Basic ' . base64_encode( $this->api_key );
                break;

            case 'custom':
                $custom_header = get_option( 'ai_chatbot_custom_auth_header', 'X-API-Key' );
                $headers[ $custom_header ] = $this->api_key;
                break;
        }

        return $headers;
    }

    /**
     * Build request data based on configured format
     *
     * @param string $message User message.
     * @param string $context Additional context.
     * @param array $options Optional parameters.
     * @return array Request data.
     * @since 1.0.0
     */
    private function build_request_data( $message, $context = '', $options = array() ) {
        $request_format = get_option( 'ai_chatbot_custom_request_format', 'simple' );

        switch ( $request_format ) {
            case 'openai':
                return $this->build_openai_format( $message, $context, $options );

            case 'custom':
                return $this->build_custom_format( $message, $context, $options );

            case 'simple':
            default:
                return $this->build_simple_format( $message, $context, $options );
        }
    }

    /**
     * Build OpenAI-compatible request format
     *
     * @param string $message User message.
     * @param string $context Additional context.
     * @param array $options Optional parameters.
     * @return array Request data.
     * @since 1.0.0
     */
    private function build_openai_format( $message, $context = '', $options = array() ) {
        $system_message = $this->build_system_message( $context );

        $data = array(
            'model' => $options['model'] ?? $this->get_default_model(),
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
            'max_tokens' => $options['max_tokens'] ?? get_option( 'ai_chatbot_custom_max_tokens', 300 ),
            'temperature' => $options['temperature'] ?? get_option( 'ai_chatbot_custom_temperature', 0.7 ),
        );

        // Add conversation history if provided
        if ( ! empty( $options['history'] ) ) {
            $history_messages = $this->format_conversation_history( $options['history'] );
            array_splice( $data['messages'], -1, 0, $history_messages );
        }

        return $data;
    }

    /**
     * Build simple request format
     *
     * @param string $message User message.
     * @param string $context Additional context.
     * @param array $options Optional parameters.
     * @return array Request data.
     * @since 1.0.0
     */
    private function build_simple_format( $message, $context = '', $options = array() ) {
        return array(
            'message' => $message,
            'context' => $context,
            'max_tokens' => $options['max_tokens'] ?? get_option( 'ai_chatbot_custom_max_tokens', 300 ),
            'temperature' => $options['temperature'] ?? get_option( 'ai_chatbot_custom_temperature', 0.7 ),
        );
    }

    /**
     * Build custom request format
     *
     * @param string $message User message.
     * @param string $context Additional context.
     * @param array $options Optional parameters.
     * @return array Request data.
     * @since 1.0.0
     */
    private function build_custom_format( $message, $context = '', $options = array() ) {
        $custom_format = get_option( 'ai_chatbot_custom_request_template', '{}' );
        $template = json_decode( $custom_format, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return $this->build_simple_format( $message, $context, $options );
        }

        // Replace placeholders
        $template = $this->replace_placeholders( $template, array(
            'message' => $message,
            'context' => $context,
            'max_tokens' => $options['max_tokens'] ?? get_option( 'ai_chatbot_custom_max_tokens', 300 ),
            'temperature' => $options['temperature'] ?? get_option( 'ai_chatbot_custom_temperature', 0.7 ),
        ) );

        return $template;
    }

    /**
     * Replace placeholders in template
     *
     * @param mixed $template Template data.
     * @param array $replacements Replacement values.
     * @return mixed Template with replacements.
     * @since 1.0.0
     */
    private function replace_placeholders( $template, $replacements ) {
        if ( is_string( $template ) ) {
            foreach ( $replacements as $key => $value ) {
                $template = str_replace( "{{$key}}", $value, $template );
            }
        } elseif ( is_array( $template ) ) {
            foreach ( $template as $key => $value ) {
                $template[ $key ] = $this->replace_placeholders( $value, $replacements );
            }
        }

        return $template;
    }

    /**
     * Extract response content based on field path
     *
     * @param array $response API response.
     * @param string $field_path Field path (e.g., 'data.response' or 'choices.0.message.content').
     * @return string Response content.
     * @since 1.0.0
     */
    private function extract_response_content( $response, $field_path ) {
        $parts = explode( '.', $field_path );
        $current = $response;

        foreach ( $parts as $part ) {
            if ( is_numeric( $part ) ) {
                $part = intval( $part );
            }

            if ( is_array( $current ) && isset( $current[ $part ] ) ) {
                $current = $current[ $part ];
            } else {
                return '';
            }
        }

        return is_string( $current ) ? $current : '';
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
        $stats = get_option( 'ai_chatbot_custom_usage_stats', array(
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'last_request' => null,
        ) );

        $stats['total_requests']++;
        $stats['last_request'] = current_time( 'mysql' );

        // Try to extract token usage if available
        $token_fields = array( 'usage.total_tokens', 'tokens', 'total_tokens' );
        foreach ( $token_fields as $field ) {
            $tokens = $this->extract_response_content( $response, $field );
            if ( ! empty( $tokens ) && is_numeric( $tokens ) ) {
                $stats['total_tokens'] += intval( $tokens );
                break;
            }
        }

        // Estimate cost
        $cost_per_request = get_option( 'ai_chatbot_custom_cost_per_request', 0.001 );
        $stats['total_cost'] += $cost_per_request;

        update_option( 'ai_chatbot_custom_usage_stats', $stats );
    }
}
