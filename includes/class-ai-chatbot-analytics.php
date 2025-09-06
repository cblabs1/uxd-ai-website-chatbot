<?php
/**
 * AI Chatbot Analytics Class
 *
 * @package AI_Website_Chatbot
 * @subpackage Includes
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Analytics Class
 */
class AI_Chatbot_Analytics {

    /**
     * Database table name for analytics
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_chatbot_analytics';
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into analytics events
        add_action('ai_chatbot_analytics_event', array($this, 'record_event'));
        
        // Hook into conversation events
        add_action('ai_chatbot_message_sent', array($this, 'record_message_event'), 10, 3);
        add_action('ai_chatbot_response_received', array($this, 'record_response_event'), 10, 3);
        
        // Hook into plugin activation to create tables
        register_activation_hook(AI_CHATBOT_PLUGIN_FILE, array($this, 'create_analytics_table'));
        
        // Schedule cleanup tasks
        add_action('ai_chatbot_analytics_cleanup', array($this, 'cleanup_old_data'));
        
        // Setup cron job if not exists
        if (!wp_next_scheduled('ai_chatbot_analytics_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ai_chatbot_analytics_cleanup');
        }
    }

    /**
     * Create analytics table
     */
    public function create_analytics_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_identifier varchar(100),
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45),
            user_agent text,
            referer text,
            session_id varchar(100),
            conversation_id varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_identifier (user_identifier),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Record an analytics event
     *
     * @param array $event_data Event data
     */
    public function record_event($event_data) {
        // Check if analytics is enabled
        if (!$this->is_analytics_enabled()) {
            return;
        }

        global $wpdb;

        $default_data = array(
            'event_type' => 'unknown',
            'event_data' => array(),
            'user_identifier' => $this->get_user_identifier(),
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
            'session_id' => $this->get_session_id(),
            'conversation_id' => null,
            'created_at' => current_time('mysql')
        );

        $event_data = wp_parse_args($event_data, $default_data);

        // Serialize event data if it's an array
        if (is_array($event_data['event_data'])) {
            $event_data['event_data'] = maybe_serialize($event_data['event_data']);
        }

        $wpdb->insert(
            $this->table_name,
            $event_data,
            array(
                '%s', // event_type
                '%s', // event_data
                '%s', // user_identifier
                '%d', // user_id
                '%s', // ip_address
                '%s', // user_agent
                '%s', // referer
                '%s', // session_id
                '%s', // conversation_id
                '%s'  // created_at
            )
        );
    }

    /**
     * Record message sent event
     *
     * @param string $message User message
     * @param string $conversation_id Conversation ID
     * @param array $context Additional context
     */
    public function record_message_event($message, $conversation_id, $context = array()) {
        $event_data = array(
            'event_type' => 'message_sent',
            'event_data' => array(
                'message' => substr($message, 0, 500), // Limit message length
                'message_length' => strlen($message),
                'context' => $context
            ),
            'conversation_id' => $conversation_id
        );

        $this->record_event($event_data);
    }

    /**
     * Record AI response received event
     *
     * @param string $response AI response
     * @param string $conversation_id Conversation ID
     * @param array $context Additional context (provider, model, etc.)
     */
    public function record_response_event($response, $conversation_id, $context = array()) {
        $event_data = array(
            'event_type' => 'response_received',
            'event_data' => array(
                'response' => substr($response, 0, 500), // Limit response length
                'response_length' => strlen($response),
                'provider' => isset($context['provider']) ? $context['provider'] : null,
                'model' => isset($context['model']) ? $context['model'] : null,
                'tokens_used' => isset($context['tokens_used']) ? $context['tokens_used'] : null,
                'response_time' => isset($context['response_time']) ? $context['response_time'] : null,
                'context' => $context
            ),
            'conversation_id' => $conversation_id
        );

        $this->record_event($event_data);
    }

    /**
     * Get analytics dashboard data
     *
     * @param array $args Query arguments
     * @return array Analytics data
     */
    public function get_dashboard_data($args = array()) {
        if (!$this->is_analytics_enabled()) {
            return array();
        }

        $default_args = array(
            'period' => '7d', // 1d, 7d, 30d, 90d
            'timezone' => get_option('timezone_string', 'UTC')
        );

        $args = wp_parse_args($args, $default_args);

        return array(
            'overview' => $this->get_overview_stats($args),
            'messages_over_time' => $this->get_messages_over_time($args),
            'popular_queries' => $this->get_popular_queries($args),
            'user_engagement' => $this->get_user_engagement_stats($args),
            'ai_performance' => $this->get_ai_performance_stats($args),
            'traffic_sources' => $this->get_traffic_sources($args)
        );
    }

    /**
     * Get overview statistics
     *
     * @param array $args Query arguments
     * @return array Overview stats
     */
    private function get_overview_stats($args) {
        global $wpdb;

        $date_filter = $this->get_date_filter($args['period']);

        $total_conversations = $wpdb->get_var(
            "SELECT COUNT(DISTINCT conversation_id) 
             FROM {$this->table_name} 
             WHERE conversation_id IS NOT NULL {$date_filter}"
        );

        $total_messages = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->table_name} 
             WHERE event_type = 'message_sent' {$date_filter}"
        );

        $total_responses = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$this->table_name} 
             WHERE event_type = 'response_received' {$date_filter}"
        );

        $unique_users = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_identifier) 
             FROM {$this->table_name} 
             WHERE user_identifier IS NOT NULL {$date_filter}"
        );

        $avg_messages_per_conversation = $total_conversations > 0 ? 
            round($total_messages / $total_conversations, 2) : 0;

        return array(
            'total_conversations' => intval($total_conversations),
            'total_messages' => intval($total_messages),
            'total_responses' => intval($total_responses),
            'unique_users' => intval($unique_users),
            'avg_messages_per_conversation' => $avg_messages_per_conversation,
            'success_rate' => $total_messages > 0 ? 
                round(($total_responses / $total_messages) * 100, 2) : 0
        );
    }

    /**
     * Get messages over time data
     *
     * @param array $args Query arguments
     * @return array Time series data
     */
    private function get_messages_over_time($args) {
        global $wpdb;

        $date_filter = $this->get_date_filter($args['period']);
        $date_format = $this->get_date_format($args['period']);

        $results = $wpdb->get_results(
            "SELECT 
                DATE_FORMAT(created_at, '{$date_format}') as period,
                COUNT(CASE WHEN event_type = 'message_sent' THEN 1 END) as messages,
                COUNT(CASE WHEN event_type = 'response_received' THEN 1 END) as responses
             FROM {$this->table_name} 
             WHERE 1=1 {$date_filter}
             GROUP BY period 
             ORDER BY period ASC"
        );

        return $results ?: array();
    }

    /**
     * Get popular queries
     *
     * @param array $args Query arguments
     * @return array Popular queries data
     */
    private function get_popular_queries($args) {
        global $wpdb;

        $date_filter = $this->get_date_filter($args['period']);

        // This is a simplified version - in production you might want more sophisticated text analysis
        $results = $wpdb->get_results(
            "SELECT 
                LEFT(event_data, 100) as query_preview,
                COUNT(*) as count
             FROM {$this->table_name} 
             WHERE event_type = 'message_sent' {$date_filter}
             GROUP BY LEFT(event_data, 100)
             ORDER BY count DESC 
             LIMIT 10"
        );

        return $results ?: array();
    }

    /**
     * Get user engagement statistics
     *
     * @param array $args Query arguments
     * @return array User engagement stats
     */
    private function get_user_engagement_stats($args) {
        global $wpdb;

        $date_filter = $this->get_date_filter($args['period']);

        $engagement_data = $wpdb->get_results(
            "SELECT 
                user_identifier,
                COUNT(CASE WHEN event_type = 'message_sent' THEN 1 END) as message_count,
                COUNT(DISTINCT conversation_id) as conversation_count,
                MIN(created_at) as first_interaction,
                MAX(created_at) as last_interaction
             FROM {$this->table_name} 
             WHERE user_identifier IS NOT NULL {$date_filter}
             GROUP BY user_identifier"
        );

        $total_users = count($engagement_data);
        $returning_users = 0;
        $avg_messages_per_user = 0;
        $total_messages = 0;

        foreach ($engagement_data as $user_data) {
            if ($user_data->conversation_count > 1) {
                $returning_users++;
            }
            $total_messages += $user_data->message_count;
        }

        $avg_messages_per_user = $total_users > 0 ? round($total_messages / $total_users, 2) : 0;
        $return_rate = $total_users > 0 ? round(($returning_users / $total_users) * 100, 2) : 0;

        return array(
            'total_users' => $total_users,
            'returning_users' => $returning_users,
            'return_rate' => $return_rate,
            'avg_messages_per_user' => $avg_messages_per_user
        );
    }

    /**
     * Get AI performance statistics
     *
     * @param array $args Query arguments
     * @return array AI performance stats
     */
    private function get_ai_performance_stats($args) {
        global $wpdb;

        $date_filter = $this->get_date_filter($args['period']);

        $performance_data = $wpdb->get_results(
            "SELECT 
                event_data
             FROM {$this->table_name} 
             WHERE event_type = 'response_received' {$date_filter}"
        );

        $total_responses = count($performance_data);
        $total_response_time = 0;
        $total_tokens = 0;
        $providers = array();

        foreach ($performance_data as $response) {
            $data = maybe_unserialize($response->event_data);
            
            if (isset($data['response_time'])) {
                $total_response_time += floatval($data['response_time']);
            }
            
            if (isset($data['tokens_used'])) {
                $total_tokens += intval($data['tokens_used']);
            }
            
            if (isset($data['provider'])) {
                $provider = $data['provider'];
                $providers[$provider] = isset($providers[$provider]) ? $providers[$provider] + 1 : 1;
            }
        }

        $avg_response_time = $total_responses > 0 ? round($total_response_time / $total_responses, 3) : 0;
        $avg_tokens_per_response = $total_responses > 0 ? round($total_tokens / $total_responses, 2) : 0;

        return array(
            'total_responses' => $total_responses,
            'avg_response_time' => $avg_response_time,
            'avg_tokens_per_response' => $avg_tokens_per_response,
            'total_tokens_used' => $total_tokens,
            'providers_usage' => $providers
        );
    }

    /**
     * Get traffic sources
     *
     * @param array $args Query arguments
     * @return array Traffic sources data
     */
    private function get_traffic_sources($args) {
        global $wpdb;

        $date_filter = $this->get_date_filter($args['period']);

        $results = $wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN referer = '' OR referer IS NULL THEN 'Direct'
                    WHEN referer LIKE '%google.%' THEN 'Google'
                    WHEN referer LIKE '%facebook.%' THEN 'Facebook'
                    WHEN referer LIKE '%twitter.%' THEN 'Twitter'
                    ELSE 'Other'
                END as source,
                COUNT(DISTINCT user_identifier) as users
             FROM {$this->table_name} 
             WHERE 1=1 {$date_filter}
             GROUP BY source 
             ORDER BY users DESC"
        );

        return $results ?: array();
    }

    /**
     * Get date filter SQL
     *
     * @param string $period Period (1d, 7d, 30d, 90d)
     * @return string SQL WHERE clause
     */
    private function get_date_filter($period) {
        switch ($period) {
            case '1d':
                return " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case '7d':
                return " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30d':
                return " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90d':
                return " AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            default:
                return " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }
    }

    /**
     * Get date format for grouping
     *
     * @param string $period Period (1d, 7d, 30d, 90d)
     * @return string MySQL date format
     */
    private function get_date_format($period) {
        switch ($period) {
            case '1d':
                return '%Y-%m-%d %H:00'; // Group by hour
            case '7d':
                return '%Y-%m-%d'; // Group by day
            case '30d':
            case '90d':
                return '%Y-%m-%d'; // Group by day
            default:
                return '%Y-%m-%d';
        }
    }

    /**
     * Check if analytics is enabled
     *
     * @return bool
     */
    private function is_analytics_enabled() {
        $settings = get_option('ai_chatbot_settings', array());
        return isset($settings['enable_analytics']) && $settings['enable_analytics'] === 'yes';
    }

    /**
     * Get user identifier
     *
     * @return string
     */
    private function get_user_identifier() {
        // Prefer user ID if logged in
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        // Use IP + User Agent hash for anonymous users
        $ip = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        return 'anon_' . substr(md5($ip . $user_agent), 0, 16);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }

    /**
     * Get session ID
     *
     * @return string
     */
    private function get_session_id() {
        // Check if session ID exists in cookie
        $session_id = isset($_COOKIE['ai_chatbot_session']) ? sanitize_text_field($_COOKIE['ai_chatbot_session']) : '';
        
        // Validate existing session ID
        if (!empty($session_id) && strlen($session_id) >= 20) {
            return $session_id;
        }
        
        // Generate new session ID using security class
        $security = new AI_Chatbot_Security();
        $session_id = $security->generate_session_id();
        
        // Set cookie (valid for 7 days)
        setcookie('ai_chatbot_session', $session_id, time() + (7 * 24 * 60 * 60), '/');
        
        return $session_id;
    }

    /**
     * Clean up old analytics data
     */
    public function cleanup_old_data() {
        if (!$this->is_analytics_enabled()) {
            return;
        }

        global $wpdb;

        $settings = get_option('ai_chatbot_settings', array());
        $retention_days = isset($settings['data_retention_days']) ? intval($settings['data_retention_days']) : 30;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );

        if ($deleted && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AI Chatbot Analytics] Cleaned up ' . $deleted . ' old analytics records');
        }

        return $deleted;
    }

    /**
     * Export analytics data
     *
     * @param array $args Export arguments
     * @return string CSV data
     */
    public function export_data($args = array()) {
        if (!$this->is_analytics_enabled()) {
            return '';
        }

        global $wpdb;

        $default_args = array(
            'period' => '30d',
            'format' => 'csv'
        );

        $args = wp_parse_args($args, $default_args);
        $date_filter = $this->get_date_filter($args['period']);

        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE 1=1 {$date_filter} ORDER BY created_at DESC",
            ARRAY_A
        );

        if (empty($results)) {
            return '';
        }

        // Generate CSV
        $csv = '';
        $headers = array_keys($results[0]);
        $csv .= implode(',', $headers) . "\n";

        foreach ($results as $row) {
            $csv .= implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }

        return $csv;
    }
}