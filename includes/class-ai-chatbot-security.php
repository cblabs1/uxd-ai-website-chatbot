<?php
/**
 * Security and rate limiting for AI Chatbot
 *
 * @package AI_Website_Chatbot
 * @subpackage Includes
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security and rate limiting class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Security {

	/**
	 * Rate limit cache key prefix
	 *
	 * @var string
	 * @since 1.0.0
	 */
	const RATE_LIMIT_PREFIX = 'ai_chatbot_rate_limit_';

	/**
	 * Blocked IPs cache key
	 *
	 * @var string
	 * @since 1.0.0
	 */
	const BLOCKED_IPS_KEY = 'ai_chatbot_blocked_ips';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_security_headers' ) );
		add_action( 'ai_chatbot_hourly_cleanup', array( $this, 'cleanup_rate_limits' ) );
	}

	/**
	 * Initialize security headers
	 *
	 * @since 1.0.0
	 */
	public function init_security_headers() {
		// Add security headers for chatbot endpoints
		if ( wp_doing_ajax() && isset( $_POST['action'] ) && strpos( $_POST['action'], 'ai_chatbot' ) === 0 ) {
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: DENY' );
			header( 'X-XSS-Protection: 1; mode=block' );
		}
	}

	/**
	 * Verify nonce for AJAX requests
	 *
	 * @param string $nonce Nonce to verify.
	 * @param string $action Action name.
	 * @return bool True if nonce is valid.
	 * @since 1.0.0
	 */
	public function verify_nonce( $nonce, $action = 'ai_chatbot_nonce' ) {
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Check user capabilities
	 *
	 * @param string $capability Required capability.
	 * @return bool True if user has capability.
	 * @since 1.0.0
	 */
	public function check_capability( $capability = 'read' ) {
		return current_user_can( $capability );
	}

	/**
	 * Rate limiting check
	 *
	 * @param string $identifier Unique identifier (IP, user ID, etc.).
	 * @param int    $limit Maximum requests allowed.
	 * @param int    $window Time window in seconds.
	 * @return bool True if within rate limit.
	 * @since 1.0.0
	 */
	public function check_rate_limit( $identifier, $limit = null, $window = 60 ) {
		if ( $limit === null ) {
			$limit = get_option( 'ai_chatbot_rate_limit_per_minute', 10 );
		}

		$cache_key = self::RATE_LIMIT_PREFIX . md5( $identifier . $window );
		$current_count = get_transient( $cache_key );

		if ( $current_count === false ) {
			// First request in this window
			set_transient( $cache_key, 1, $window );
			return true;
		}

		if ( $current_count >= $limit ) {
			// Rate limit exceeded
			$this->log_rate_limit_violation( $identifier, $current_count, $limit );
			return false;
		}

		// Increment counter
		set_transient( $cache_key, $current_count + 1, $window );
		return true;
	}

	/**
	 * Check if IP is blocked
	 *
	 * @param string $ip IP address.
	 * @return bool True if IP is blocked.
	 * @since 1.0.0
	 */
	public function is_ip_blocked( $ip ) {
		$blocked_ips = get_option( self::BLOCKED_IPS_KEY, array() );
		
		// Check exact match
		if ( in_array( $ip, $blocked_ips, true ) ) {
			return true;
		}

		// Check CIDR ranges
		foreach ( $blocked_ips as $blocked_ip ) {
			if ( $this->ip_in_range( $ip, $blocked_ip ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Block IP address
	 *
	 * @param string $ip IP address to block.
	 * @param int    $duration Duration in seconds (0 = permanent).
	 * @return bool True if IP was blocked successfully.
	 * @since 1.0.0
	 */
	public function block_ip( $ip, $duration = 3600 ) {
		$blocked_ips = get_option( self::BLOCKED_IPS_KEY, array() );
		
		if ( ! in_array( $ip, $blocked_ips, true ) ) {
			$blocked_ips[] = $ip;
			update_option( self::BLOCKED_IPS_KEY, $blocked_ips );
		}

		// Set expiration if temporary block
		if ( $duration > 0 ) {
			set_transient( 'ai_chatbot_ip_block_' . md5( $ip ), true, $duration );
		}

		$this->log_ip_block( $ip, $duration );
		
		return true;
	}

	/**
	 * Unblock IP address
	 *
	 * @param string $ip IP address to unblock.
	 * @return bool True if IP was unblocked successfully.
	 * @since 1.0.0
	 */
	public function unblock_ip( $ip ) {
		$blocked_ips = get_option( self::BLOCKED_IPS_KEY, array() );
		$key = array_search( $ip, $blocked_ips, true );
		
		if ( $key !== false ) {
			unset( $blocked_ips[ $key ] );
			update_option( self::BLOCKED_IPS_KEY, array_values( $blocked_ips ) );
		}

		// Remove temporary block transient
		delete_transient( 'ai_chatbot_ip_block_' . md5( $ip ) );
		
		return true;
	}

	/**
	 * Validate and sanitize user message
	 *
	 * @param string $message User message.
	 * @return string|WP_Error Sanitized message or WP_Error if validation fails.
	 * @since 1.0.0
	 */
	public function sanitize_user_message( $message ) {
		// Check message length
		$max_length = get_option( 'ai_chatbot_max_message_length', 1000 );
		if ( strlen( $message ) > $max_length ) {
			return new WP_Error( 
				'message_too_long', 
				sprintf( 
					/* translators: %d: maximum message length */
					__( 'Message is too long. Maximum length is %d characters.', 'ai-website-chatbot' ), 
					$max_length 
				) 
			);
		}

		// Check for blocked words
		if ( $this->contains_blocked_words( $message ) ) {
			return new WP_Error( 'blocked_content', __( 'Message contains inappropriate content.', 'ai-website-chatbot' ) );
		}

		// Basic sanitization
		$message = trim( $message );
		$message = wp_strip_all_tags( $message );
		
		// Remove excessive whitespace
		$message = preg_replace( '/\s+/', ' ', $message );
		
		// Check for spam patterns
		if ( $this->is_spam_message( $message ) ) {
			return new WP_Error( 'spam_detected', __( 'Message appears to be spam.', 'ai-website-chatbot' ) );
		}

		return $message;
	}

	/**
	 * Check if message contains blocked words
	 *
	 * @param string $message Message to check.
	 * @return bool True if contains blocked words.
	 * @since 1.0.0
	 */
	private function contains_blocked_words( $message ) {
		$blocked_words = get_option( 'ai_chatbot_blocked_words', '' );
		
		if ( empty( $blocked_words ) ) {
			return false;
		}

		$words = array_filter( array_map( 'trim', explode( "\n", strtolower( $blocked_words ) ) ) );
		$message_lower = strtolower( $message );

		foreach ( $words as $word ) {
			if ( strpos( $message_lower, $word ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if message appears to be spam
	 *
	 * @param string $message Message to check.
	 * @return bool True if appears to be spam.
	 * @since 1.0.0
	 */
	public function is_spam_message( $message ) {
		// Check for excessive repetition
		if ( preg_match( '/(.)\1{10,}/', $message ) ) {
			return true;
		}

		// Check for excessive caps
		$caps_percentage = 0;
		if ( strlen( $message ) > 10 ) {
			$caps_count = strlen( preg_replace( '/[^A-Z]/', '', $message ) );
			$caps_percentage = ( $caps_count / strlen( $message ) ) * 100;
		}
		
		if ( $caps_percentage > 70 ) {
			return true;
		}

		// Check for common spam patterns
		$spam_patterns = array(
			'/https?:\/\/[^\s]+/',  // URLs
			'/\b\d{3}-\d{3}-\d{4}\b/', // Phone numbers
			'/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // Email addresses
		);

		foreach ( $spam_patterns as $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address.
	 * @since 1.0.0
	 */
	public function get_client_ip() {
		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
		
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = $_SERVER[ $key ];
				
				// Handle comma-separated IPs (from proxies)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				
				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	}

	/**
	 * Check if IP is in range (supports CIDR)
	 *
	 * @param string $ip IP to check.
	 * @param string $range IP range (supports CIDR notation).
	 * @return bool True if IP is in range.
	 * @since 1.0.0
	 */
	private function ip_in_range( $ip, $range ) {
		if ( strpos( $range, '/' ) === false ) {
			return $ip === $range;
		}

		list( $range_ip, $netmask ) = explode( '/', $range, 2 );
		
		$range_decimal = ip2long( $range_ip );
		$ip_decimal = ip2long( $ip );
		$wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
		$netmask_decimal = ~ $wildcard_decimal;
		
		return ( $ip_decimal & $netmask_decimal ) === ( $range_decimal & $netmask_decimal );
	}

	/**
	 * Generate secure session ID
	 *
	 * @return string Secure session ID.
	 * @since 1.0.0
	 */
	public function generate_session_id() {
		return wp_generate_password( 32, false, false );
	}

	/**
	 * Encrypt sensitive data
	 *
	 * @param string $data Data to encrypt.
	 * @param string $key Encryption key.
	 * @return string|false Encrypted data or false on failure.
	 * @since 1.0.0
	 */
	public function encrypt_data( $data, $key = null ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return false;
		}

		if ( $key === null ) {
			$key = $this->get_encryption_key();
		}

		$iv = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );
		
		if ( $encrypted === false ) {
			return false;
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt sensitive data
	 *
	 * @param string $encrypted_data Encrypted data.
	 * @param string $key Encryption key.
	 * @return string|false Decrypted data or false on failure.
	 * @since 1.0.0
	 */
	public function decrypt_data( $encrypted_data, $key = null ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return false;
		}

		if ( $key === null ) {
			$key = $this->get_encryption_key();
		}

		$data = base64_decode( $encrypted_data );
		$iv = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );
		
		return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
	}

	/**
	 * Get or generate encryption key
	 *
	 * @return string Encryption key.
	 * @since 1.0.0
	 */
	private function get_encryption_key() {
		$key = get_option( 'ai_chatbot_encryption_key' );
		
		if ( ! $key ) {
			$key = wp_generate_password( 64, true, true );
			update_option( 'ai_chatbot_encryption_key', $key );
		}
		
		return $key;
	}

	/**
	 * Log rate limit violation
	 *
	 * @param string $identifier Identifier that exceeded rate limit.
	 * @param int    $current_count Current request count.
	 * @param int    $limit Rate limit.
	 * @since 1.0.0
	 */
	private function log_rate_limit_violation( $identifier, $current_count, $limit ) {
		$log_entry = array(
			'timestamp'     => current_time( 'mysql' ),
			'identifier'    => $identifier,
			'current_count' => $current_count,
			'limit'         => $limit,
			'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
			'referer'       => $_SERVER['HTTP_REFERER'] ?? '',
		);

		$violations = get_option( 'ai_chatbot_rate_limit_violations', array() );
		$violations[] = $log_entry;

		// Keep only last 100 violations
		if ( count( $violations ) > 100 ) {
			$violations = array_slice( $violations, -100 );
		}

		update_option( 'ai_chatbot_rate_limit_violations', $violations );

		// Auto-block if too many violations
		$recent_violations = array_filter( $violations, function( $violation ) use ( $identifier ) {
			return $violation['identifier'] === $identifier && 
				   strtotime( $violation['timestamp'] ) > ( time() - 3600 ); // Last hour
		});

		if ( count( $recent_violations ) >= 5 ) {
			$this->block_ip( $identifier, 3600 ); // Block for 1 hour
		}
	}

	/**
	 * Log IP block event
	 *
	 * @param string $ip IP address.
	 * @param int    $duration Block duration.
	 * @since 1.0.0
	 */
	private function log_ip_block( $ip, $duration ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'ip'        => $ip,
			'duration'  => $duration,
			'reason'    => 'Rate limit violation',
		);

		$blocks = get_option( 'ai_chatbot_ip_blocks_log', array() );
		$blocks[] = $log_entry;

		// Keep only last 50 blocks
		if ( count( $blocks ) > 50 ) {
			$blocks = array_slice( $blocks, -50 );
		}

		update_option( 'ai_chatbot_ip_blocks_log', $blocks );
	}

	/**
	 * Clean up expired rate limits and blocks
	 *
	 * @since 1.0.0
	 */
	public function cleanup_rate_limits() {
		global $wpdb;

		// Clean up expired transients
		$expired_transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s
				AND option_value < %d",
				'_transient_timeout_' . self::RATE_LIMIT_PREFIX . '%',
				'_transient_' . self::RATE_LIMIT_PREFIX . '%',
				time()
			)
		);

		foreach ( $expired_transients as $transient ) {
			$key = str_replace( '_transient_timeout_', '', $transient->option_name );
			delete_transient( $key );
		}

		// Clean up old violations log
		$violations = get_option( 'ai_chatbot_rate_limit_violations', array() );
		$recent_violations = array_filter( $violations, function( $violation ) {
			return strtotime( $violation['timestamp'] ) > ( time() - 86400 ); // Last 24 hours
		});

		if ( count( $recent_violations ) !== count( $violations ) ) {
			update_option( 'ai_chatbot_rate_limit_violations', array_values( $recent_violations ) );
		}

		// Clean up temporary IP blocks
		$blocked_ips = get_option( self::BLOCKED_IPS_KEY, array() );
		$permanent_blocks = array();

		foreach ( $blocked_ips as $ip ) {
			$temp_block_key = 'ai_chatbot_ip_block_' . md5( $ip );
			if ( get_transient( $temp_block_key ) === false ) {
				// Check if this was a temporary block that expired
				$blocks_log = get_option( 'ai_chatbot_ip_blocks_log', array() );
				$was_temporary = false;

				foreach ( $blocks_log as $log_entry ) {
					if ( $log_entry['ip'] === $ip && $log_entry['duration'] > 0 ) {
						$block_end = strtotime( $log_entry['timestamp'] ) + $log_entry['duration'];
						if ( time() > $block_end ) {
							$was_temporary = true;
							break;
						}
					}
				}

				if ( ! $was_temporary ) {
					$permanent_blocks[] = $ip;
				}
			} else {
				$permanent_blocks[] = $ip;
			}
		}

		if ( count( $permanent_blocks ) !== count( $blocked_ips ) ) {
			update_option( self::BLOCKED_IPS_KEY, $permanent_blocks );
		}
	}

	/**
	 * Get security statistics
	 *
	 * @return array Security statistics.
	 * @since 1.0.0
	 */
	public function get_security_stats() {
		$violations = get_option( 'ai_chatbot_rate_limit_violations', array() );
		$blocked_ips = get_option( self::BLOCKED_IPS_KEY, array() );
		$blocks_log = get_option( 'ai_chatbot_ip_blocks_log', array() );

		// Count recent violations (last 24 hours)
		$recent_violations = array_filter( $violations, function( $violation ) {
			return strtotime( $violation['timestamp'] ) > ( time() - 86400 );
		});

		// Count recent blocks (last 24 hours)
		$recent_blocks = array_filter( $blocks_log, function( $block ) {
			return strtotime( $block['timestamp'] ) > ( time() - 86400 );
		});

		return array(
			'total_blocked_ips'    => count( $blocked_ips ),
			'recent_violations'    => count( $recent_violations ),
			'recent_blocks'        => count( $recent_blocks ),
			'total_violations'     => count( $violations ),
			'security_events'      => array_merge(
				array_slice( $violations, -10 ),
				array_slice( $blocks_log, -10 )
			),
		);
	}

	/**
	 * Validate API key format
	 *
	 * @param string $api_key API key to validate.
	 * @param string $provider AI provider (openai, claude, gemini).
	 * @return bool True if format is valid.
	 * @since 1.0.0
	 */
	public function validate_api_key_format( $api_key, $provider ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		switch ( $provider ) {
			case 'openai':
				// OpenAI keys start with 'sk-' and are typically 51 characters
				return preg_match( '/^sk-[a-zA-Z0-9]{48}$/', $api_key ) === 1;

			case 'claude':
				// Claude keys start with 'sk-ant-' and are longer
				return preg_match( '/^sk-ant-[a-zA-Z0-9_-]+$/', $api_key ) === 1;

			case 'gemini':
				// Gemini keys are typically 39 characters of alphanumeric + dashes
				return preg_match( '/^[a-zA-Z0-9_-]{35,45}$/', $api_key ) === 1;

			default:
				return strlen( $api_key ) >= 20; // Generic validation
		}
	}

	/**
	 * Mask API key for display
	 *
	 * @param string $api_key API key to mask.
	 * @return string Masked API key.
	 * @since 1.0.0
	 */
	public function mask_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return '';
		}

		if ( strlen( $api_key ) <= 8 ) {
			return str_repeat( '*', strlen( $api_key ) );
		}

		$start = substr( $api_key, 0, 4 );
		$end = substr( $api_key, -4 );
		$middle = str_repeat( '*', strlen( $api_key ) - 8 );

		return $start . $middle . $end;
	}
}