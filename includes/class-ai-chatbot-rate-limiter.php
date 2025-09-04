<?php
/**
 * AI Chatbot Rate Limiter Class
 * 
 * File: includes/class-ai-chatbot-rate-limiter.php
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Rate Limiter Class
 */
class AI_Chatbot_Rate_Limiter {

    /**
     * Rate limit cache key prefix
     */
    const CACHE_PREFIX = 'ai_chatbot_rate_limit_';

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize any hooks if needed
    }

    /**
     * Check if request is within rate limit
     *
     * @param string $identifier User identifier (IP or user ID)
     * @param int $max_requests Maximum requests allowed
     * @param int $time_window Time window in seconds
     * @return bool True if within limits, false if exceeded
     */
    public function is_allowed($identifier, $max_requests = 10, $time_window = 60) {
        // Get settings
        $settings = get_option('ai_chatbot_settings', array());
        
        // Check if rate limiting is enabled
        if (isset($settings['enable_rate_limiting']) && $settings['enable_rate_limiting'] !== 'yes') {
            return true; // Rate limiting disabled
        }

        // Use settings values if available
        if (isset($settings['rate_limit_requests'])) {
            $max_requests = intval($settings['rate_limit_requests']);
        }
        if (isset($settings['rate_limit_window'])) {
            $time_window = intval($settings['rate_limit_window']);
        }

        $cache_key = $this->get_cache_key($identifier);
        $requests = $this->get_requests($cache_key);
        $current_time = time();

        // Clean old requests
        $requests = $this->clean_old_requests($requests, $current_time, $time_window);

        // Check if limit is exceeded
        if (count($requests) >= $max_requests) {
            $this->log_rate_limit_exceeded($identifier, $max_requests, $time_window);
            return false;
        }

        return true;
    }

    /**
     * Record a request
     *
     * @param string $identifier User identifier
     * @param int $time_window Time window in seconds
     */
    public function record_request($identifier, $time_window = 60) {
        $cache_key = $this->get_cache_key($identifier);
        $requests = $this->get_requests($cache_key);
        $current_time = time();

        // Clean old requests
        $requests = $this->clean_old_requests($requests, $current_time, $time_window);

        // Add current request
        $requests[] = $current_time;

        // Save updated requests
        $this->save_requests($cache_key, $requests, $time_window);
    }

    /**
     * Get remaining requests for an identifier
     *
     * @param string $identifier User identifier
     * @param int $max_requests Maximum requests allowed
     * @param int $time_window Time window in seconds
     * @return int Number of remaining requests
     */
    public function get_remaining_requests($identifier, $max_requests = 10, $time_window = 60) {
        $cache_key = $this->get_cache_key($identifier);
        $requests = $this->get_requests($cache_key);
        $current_time = time();

        // Clean old requests
        $requests = $this->clean_old_requests($requests, $current_time, $time_window);

        return max(0, $max_requests - count($requests));
    }

    /**
     * Get time until rate limit resets
     *
     * @param string $identifier User identifier
     * @param int $time_window Time window in seconds
     * @return int Seconds until reset (0 if no requests or already reset)
     */
    public function get_reset_time($identifier, $time_window = 60) {
        $cache_key = $this->get_cache_key($identifier);
        $requests = $this->get_requests($cache_key);
        
        if (empty($requests)) {
            return 0;
        }

        $oldest_request = min($requests);
        $reset_time = $oldest_request + $time_window;
        $current_time = time();

        return max(0, $reset_time - $current_time);
    }

    /**
     * Clear rate limit for an identifier
     *
     * @param string $identifier User identifier
     */
    public function clear_rate_limit($identifier) {
        $cache_key = $this->get_cache_key($identifier);
        delete_transient($cache_key);
    }

    /**
     * Get rate limit statistics
     *
     * @param string $identifier User identifier
     * @param int $max_requests Maximum requests allowed
     * @param int $time_window Time window in seconds
     * @return array Statistics array
     */
    public function get_statistics($identifier, $max_requests = 10, $time_window = 60) {
        $cache_key = $this->get_cache_key($identifier);
        $requests = $this->get_requests($cache_key);
        $current_time = time();

        // Clean old requests
        $requests = $this->clean_old_requests($requests, $current_time, $time_window);

        $used_requests = count($requests);
        $remaining_requests = max(0, $max_requests - $used_requests);
        $reset_time = $this->get_reset_time($identifier, $time_window);

        return array(
            'max_requests' => $max_requests,
            'used_requests' => $used_requests,
            'remaining_requests' => $remaining_requests,
            'time_window' => $time_window,
            'reset_time' => $reset_time,
            'is_limited' => $remaining_requests <= 0,
            'percentage_used' => $max_requests > 0 ? round(($used_requests / $max_requests) * 100, 2) : 0
        );
    }

    /**
     * Get user identifier for rate limiting
     *
     * @return string User identifier
     */
    public function get_user_identifier() {
        // For logged-in users, use user ID
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        // For non-logged-in users, use IP address
        $ip = $this->get_client_ip();
        return 'ip_' . md5($ip);
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    // Validate IP and exclude private/reserved ranges for public IPs
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        // Fallback to REMOTE_ADDR even if it's private (for local development)
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }

    /**
     * Get cache key for identifier
     *
     * @param string $identifier User identifier
     * @return string Cache key
     */
    private function get_cache_key($identifier) {
        return self::CACHE_PREFIX . md5($identifier);
    }

    /**
     * Get requests from cache
     *
     * @param string $cache_key Cache key
     * @return array Array of request timestamps
     */
    private function get_requests($cache_key) {
        $requests = get_transient($cache_key);
        return is_array($requests) ? $requests : array();
    }

    /**
     * Save requests to cache
     *
     * @param string $cache_key Cache key
     * @param array $requests Array of request timestamps
     * @param int $expiration Cache expiration in seconds
     */
    private function save_requests($cache_key, $requests, $expiration) {
        set_transient($cache_key, $requests, $expiration);
    }

    /**
     * Clean old requests from array
     *
     * @param array $requests Array of request timestamps
     * @param int $current_time Current timestamp
     * @param int $time_window Time window in seconds
     * @return array Cleaned array of requests
     */
    private function clean_old_requests($requests, $current_time, $time_window) {
        return array_filter($requests, function($timestamp) use ($current_time, $time_window) {
            return ($current_time - $timestamp) < $time_window;
        });
    }

    /**
     * Log rate limit exceeded event
     *
     * @param string $identifier User identifier
     * @param int $max_requests Maximum requests allowed
     * @param int $time_window Time window in seconds
     */
    private function log_rate_limit_exceeded($identifier, $max_requests, $time_window) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AI Chatbot Rate Limiter] Rate limit exceeded for %s: %d requests in %d seconds',
                $identifier,
                $max_requests,
                $time_window
            ));
        }

        // You can extend this to save to database for analytics
        $this->maybe_record_analytics($identifier, 'rate_limit_exceeded', array(
            'max_requests' => $max_requests,
            'time_window' => $time_window
        ));
    }

    /**
     * Maybe record analytics event
     *
     * @param string $identifier User identifier
     * @param string $event Event type
     * @param array $data Additional event data
     */
    private function maybe_record_analytics($identifier, $event, $data = array()) {
        $settings = get_option('ai_chatbot_settings', array());
        
        if (isset($settings['enable_analytics']) && $settings['enable_analytics'] === 'yes') {
            // Here you can integrate with your analytics system
            // For example, save to database or send to external service
            
            $analytics_data = array(
                'timestamp' => time(),
                'identifier' => $identifier,
                'event' => $event,
                'data' => $data,
                'ip' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
            );
            
            // You can implement your analytics storage here
            do_action('ai_chatbot_analytics_event', $analytics_data);
        }
    }

    /**
     * Get default rate limit settings
     *
     * @return array Default settings
     */
    public static function get_default_settings() {
        return array(
            'enable_rate_limiting' => 'yes',
            'rate_limit_requests' => 10,
            'rate_limit_window' => 60, // 1 minute
            'rate_limit_block_duration' => 300 // 5 minutes block after limit exceeded
        );
    }

    /**
     * Clean up expired rate limit data (can be called via cron)
     */
    public static function cleanup_expired_data() {
        global $wpdb;
        
        // Get all transients with our prefix
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_name LIKE %s",
                '_transient_timeout_' . self::CACHE_PREFIX . '%',
                '%'
            )
        );

        $cleaned = 0;
        foreach ($transients as $transient) {
            $key = str_replace('_transient_timeout_', '', $transient->option_name);
            $timeout = get_option($transient->option_name);
            
            if ($timeout && $timeout < time()) {
                delete_transient($key);
                $cleaned++;
            }
        }

        if ($cleaned > 0 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AI Chatbot Rate Limiter] Cleaned up ' . $cleaned . ' expired rate limit entries');
        }

        return $cleaned;
    }
}