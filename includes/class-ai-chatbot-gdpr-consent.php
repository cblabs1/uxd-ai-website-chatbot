<?php
/**
 * AI Chatbot - GDPR Consent Manager
 * 
 * Handles GDPR cookie consent functionality
 * 
 * @package AI_Website_Chatbot
 * @since 11.6.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chatbot_GDPR_Consent {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue consent scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_consent_assets'), 11);
        
        // Add consent configuration to frontend
        add_filter('ai_chatbot_frontend_config', array($this, 'add_consent_config'));
        
        // AJAX handler to check consent status
        add_action('wp_ajax_ai_chatbot_check_consent', array($this, 'ajax_check_consent'));
        add_action('wp_ajax_nopriv_ai_chatbot_check_consent', array($this, 'ajax_check_consent'));
        
        // Add revoke consent shortcode
        add_shortcode('ai_chatbot_revoke_consent', array($this, 'revoke_consent_shortcode'));
    }
    
    /**
     * Enqueue consent assets
     */
    public function enqueue_consent_assets() {
        $settings = get_option('ai_chatbot_settings', array());
        
        // Only load if chatbot is enabled and GDPR is enabled
        if (empty($settings['enabled']) || empty($settings['gdpr']['enabled'])) {
            return;
        }
        
        // Only load if cookie consent is required
        if (empty($settings['gdpr']['cookie_consent'])) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'ai-chatbot-gdpr-consent',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-gdpr-consent.css',
            array(),
            AI_CHATBOT_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'ai-chatbot-gdpr-consent',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-gdpr-consent.js',
            array('jquery'),
            AI_CHATBOT_VERSION,
            true
        );
    }
    
    /**
     * Add consent configuration to frontend config
     */
    public function add_consent_config($config) {
        $settings = get_option('ai_chatbot_settings', array());
        
        $config['gdpr_enabled'] = !empty($settings['gdpr']['enabled']);
        $config['cookie_consent_required'] = !empty($settings['gdpr']['cookie_consent']);
        $config['privacy_policy_url'] = !empty($settings['gdpr']['privacy_policy_url']) 
            ? $settings['gdpr']['privacy_policy_url'] 
            : get_privacy_policy_url();
        
        // Customizable consent messages
        $config['consent_title'] = get_option('ai_chatbot_consent_title', __('Cookie Consent', 'ai-website-chatbot'));
        $config['consent_message'] = get_option('ai_chatbot_consent_message', 
            __('We use cookies and collect conversation data to improve our AI chatbot service. Your conversations may be processed by third-party AI providers.', 'ai-website-chatbot')
        );
        $config['consent_accept_text'] = get_option('ai_chatbot_consent_accept', __('Accept', 'ai-website-chatbot'));
        $config['consent_decline_text'] = get_option('ai_chatbot_consent_decline', __('Decline', 'ai-website-chatbot'));
        $config['consent_accepted_message'] = get_option('ai_chatbot_consent_accepted_msg', 
            __('Thank you! You can now use the chatbot.', 'ai-website-chatbot')
        );
        $config['consent_declined_message'] = get_option('ai_chatbot_consent_declined_msg', 
            __('You have declined. The chatbot will not be available.', 'ai-website-chatbot')
        );
        
        return $config;
    }
    
    /**
     * AJAX: Check consent status
     */
    public function ajax_check_consent() {
        // Get cookie value
        $consent = isset($_COOKIE['ai_chatbot_consent']) ? sanitize_text_field($_COOKIE['ai_chatbot_consent']) : null;
        
        wp_send_json_success(array(
            'has_consent' => $consent === 'accepted',
            'consent_value' => $consent,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Shortcode to display revoke consent button
     */
    public function revoke_consent_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => __('Revoke AI Chatbot Consent', 'ai-website-chatbot'),
            'class' => ''
        ), $atts);
        
        return sprintf(
            '<a href="#" class="ai-chatbot-revoke-consent %s">%s</a>',
            esc_attr($atts['class']),
            esc_html($atts['text'])
        );
    }
    
    /**
     * Check if user has consented (server-side check)
     */
    public static function has_user_consented() {
        $settings = get_option('ai_chatbot_settings', array());
        
        // If GDPR not enabled or consent not required, return true
        if (empty($settings['gdpr']['enabled']) || empty($settings['gdpr']['cookie_consent'])) {
            return true;
        }
        
        // Check cookie
        return isset($_COOKIE['ai_chatbot_consent']) && $_COOKIE['ai_chatbot_consent'] === 'accepted';
    }
    
    /**
     * Get consent status details
     */
    public static function get_consent_status() {
        $consent = isset($_COOKIE['ai_chatbot_consent']) ? sanitize_text_field($_COOKIE['ai_chatbot_consent']) : null;
        
        return array(
            'has_consent' => $consent === 'accepted',
            'consent_value' => $consent,
            'consent_required' => self::is_consent_required(),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Check if consent is required
     */
    public static function is_consent_required() {
        $settings = get_option('ai_chatbot_settings', array());
        return !empty($settings['gdpr']['enabled']) && !empty($settings['gdpr']['cookie_consent']);
    }
    
    /**
     * Log consent action (for audit trail)
     */
    public static function log_consent_action($action, $user_data = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_consent_log';
        
        // Create table if doesn't exist
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            consent_value varchar(20),
            user_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert log entry
        $wpdb->insert(
            $table_name,
            array(
                'action' => sanitize_text_field($action),
                'ip_address' => self::get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 500) : '',
                'consent_value' => isset($user_data['consent_value']) ? sanitize_text_field($user_data['consent_value']) : null,
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Get consent statistics (for admin)
     */
    public static function get_consent_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_consent_log';
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array(
            'total_requests' => 0,
            'accepted' => 0,
            'declined' => 0,
            'revoked' => 0,
            'acceptance_rate' => 0
        );
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return $stats;
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT action, COUNT(*) as count 
            FROM $table_name 
            WHERE created_at >= %s 
            GROUP BY action",
            $date_from
        ));
        
        foreach ($results as $row) {
            $stats['total_requests']++;
            
            switch ($row->action) {
                case 'accepted':
                    $stats['accepted'] = intval($row->count);
                    break;
                case 'declined':
                    $stats['declined'] = intval($row->count);
                    break;
                case 'revoked':
                    $stats['revoked'] = intval($row->count);
                    break;
            }
        }
        
        // Calculate acceptance rate
        $total_decisions = $stats['accepted'] + $stats['declined'];
        if ($total_decisions > 0) {
            $stats['acceptance_rate'] = round(($stats['accepted'] / $total_decisions) * 100, 2);
        }
        
        return $stats;
    }
}

// Initialize
AI_Chatbot_GDPR_Consent::get_instance();
