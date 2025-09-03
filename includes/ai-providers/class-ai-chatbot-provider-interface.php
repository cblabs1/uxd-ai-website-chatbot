<?php
/**
 * AI Provider Interface
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
 * Interface for AI providers
 *
 * @since 1.0.0
 */
interface AI_Chatbot_Provider_Interface {

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 * @since 1.0.0
	 */
	public function get_name();

	/**
	 * Get provider display name
	 *
	 * @return string Provider display name.
	 * @since 1.0.0
	 */
	public function get_display_name();

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if configured.
	 * @since 1.0.0
	 */
	public function is_configured();

	/**
	 * Test API connection
	 *
	 * @return bool|WP_Error True if connection successful, WP_Error if failed.
	 * @since 1.0.0
	 */
	public function test_connection();

	/**
	 * Generate response from AI
	 *
	 * @param string $message User message.
	 * @param string $context Additional context from website.
	 * @param array  $options Optional parameters.
	 * @return string|WP_Error AI response or WP_Error if failed.
	 * @since 1.0.0
	 */
	public function generate_response( $message, $context = '', $options = array() );

	/**
	 * Get available models
	 *
	 * @return array Available models.
	 * @since 1.0.0
	 */
	public function get_available_models();

	/**
	 * Get default model
	 *
	 * @return string Default model identifier.
	 * @since 1.0.0
	 */
	public function get_default_model();

	/**
	 * Get provider configuration fields
	 *
	 * @return array Configuration fields.
	 * @since 1.0.0
	 */
	public function get_config_fields();

	/**
	 * Validate configuration
	 *
	 * @param array $config Configuration values.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 * @since 1.0.0
	 */
	public function validate_config( $config );

	/**
	 * Get usage statistics
	 *
	 * @return array Usage statistics.
	 * @since 1.0.0
	 */
	public function get_usage_stats();

	/**
	 * Get rate limits
	 *
	 * @return array Rate limit information.
	 * @since 1.0.0
	 */
	public function get_rate_limits();
}