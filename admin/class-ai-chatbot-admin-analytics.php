<?php
/**
 * AI Chatbot Admin Analytics Class
 * 
 * Handles analytics and reporting functionality
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AI_Chatbot_Admin_Analytics
 */
class AI_Chatbot_Admin_Analytics {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_ai_chatbot_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_ai_chatbot_export_analytics', array($this, 'ajax_export_analytics'));
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ai-website-chatbot'));
        }
        
        // Get analytics data
        $analytics_data = $this->get_analytics_data();
        
        // Include analytics template
        include AI_CHATBOT_PLUGIN_DIR . 'admin/partials/admin-analytics-display.php';
    }
    
    /**
     * Get analytics data
     */
    public function get_analytics_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $current_date = current_time('Y-m-d');
        $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return $this->get_empty_analytics_data();
        }
        
        $data = array();
        
        // Total conversations
        $data['total_conversations'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Conversations today
        $data['conversations_today'] = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s", $current_date)
        );
        
        // Conversations this month
        $data['conversations_this_month'] = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) >= %s", date('Y-m-01'))
        );
        
        // Average response time
        $avg_response_time = $wpdb->get_var("SELECT AVG(response_time) FROM $table_name WHERE response_time > 0");
        $data['avg_response_time'] = round($avg_response_time ?: 0);
        
        // User satisfaction (based on ratings)
        $satisfaction = $wpdb->get_var("SELECT AVG(rating) FROM $table_name WHERE rating > 0");
        $data['user_satisfaction'] = round($satisfaction ?: 0, 1);
        
        // Most active hours
        $data['active_hours'] = $wpdb->get_results("
            SELECT HOUR(created_at) as hour, COUNT(*) as count 
            FROM $table_name 
            WHERE DATE(created_at) >= '$thirty_days_ago'
            GROUP BY HOUR(created_at) 
            ORDER BY count DESC 
            LIMIT 5
        ");
        
        // Daily conversation trends (last 30 days)
        $data['daily_trends'] = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM $table_name 
            WHERE DATE(created_at) >= %s 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC
        ", $thirty_days_ago));
        
        // Top conversation topics/intents
        $data['top_topics'] = $wpdb->get_results("
            SELECT intent, COUNT(*) as count 
            FROM $table_name 
            WHERE intent IS NOT NULL AND intent != '' 
            AND DATE(created_at) >= '$thirty_days_ago'
            GROUP BY intent 
            ORDER BY count DESC 
            LIMIT 10
        ");
        
        // Conversation status distribution
        $data['status_distribution'] = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM $table_name 
            WHERE DATE(created_at) >= '$thirty_days_ago'
            GROUP BY status
        ");
        
        // User engagement metrics
        $data['engagement_metrics'] = array(
            'total_messages' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_chatbot_messages WHERE DATE(created_at) >= '$thirty_days_ago'"),
            'avg_messages_per_conversation' => $wpdb->get_var("
                SELECT AVG(message_count) FROM (
                    SELECT conversation_id, COUNT(*) as message_count 
                    FROM {$wpdb->prefix}ai_chatbot_messages 
                    WHERE DATE(created_at) >= '$thirty_days_ago'
                    GROUP BY conversation_id
                ) as subquery
            "),
            'bounce_rate' => $this->calculate_bounce_rate($thirty_days_ago)
        );
        
        return $data;
    }
    
    /**
     * Get empty analytics data structure
     */
    private function get_empty_analytics_data() {
        return array(
            'total_conversations' => 0,
            'conversations_today' => 0,
            'conversations_this_month' => 0,
            'avg_response_time' => 0,
            'user_satisfaction' => 0,
            'active_hours' => array(),
            'daily_trends' => array(),
            'top_topics' => array(),
            'status_distribution' => array(),
            'engagement_metrics' => array(
                'total_messages' => 0,
                'avg_messages_per_conversation' => 0,
                'bounce_rate' => 0
            )
        );
    }
    
    /**
     * Calculate bounce rate (conversations with only 1 user message)
     */
    private function calculate_bounce_rate($since_date) {
        global $wpdb;
        
        $total_conversations = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ai_chatbot_conversations 
            WHERE DATE(created_at) >= %s
        ", $since_date));
        
        if ($total_conversations == 0) {
            return 0;
        }
        
        $bounce_conversations = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM (
                SELECT conversation_id, COUNT(*) as user_messages 
                FROM {$wpdb->prefix}ai_chatbot_messages 
                WHERE sender_type = 'user' AND DATE(created_at) >= %s
                GROUP BY conversation_id 
                HAVING user_messages = 1
            ) as bounced
        ", $since_date));
        
        return round(($bounce_conversations / $total_conversations) * 100, 1);
    }
    
    /**
     * AJAX: Get analytics data
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $date_range = sanitize_text_field($_POST['date_range'] ?? '30');
        $data = $this->get_analytics_data_by_range($date_range);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get analytics data by date range
     */
    private function get_analytics_data_by_range($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        $data = array();
        
        // Conversations in range
        $data['conversations_in_range'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) >= %s",
            $start_date
        ));
        
        // Daily trends for the range
        $data['daily_trends'] = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM $table_name 
            WHERE DATE(created_at) >= %s 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC
        ", $start_date));
        
        // Hourly distribution
        $data['hourly_distribution'] = $wpdb->get_results($wpdb->prepare("
            SELECT HOUR(created_at) as hour, COUNT(*) as count 
            FROM $table_name 
            WHERE DATE(created_at) >= %s
            GROUP BY HOUR(created_at) 
            ORDER BY hour ASC
        ", $start_date));
        
        return $data;
    }
    
    /**
     * AJAX: Export analytics
     */
    public function ajax_export_analytics() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $date_range = sanitize_text_field($_POST['date_range'] ?? '30');
        
        $data = $this->get_export_data($date_range);
        
        if ($format === 'json') {
            $this->export_json($data);
        } else {
            $this->export_csv($data);
        }
    }
    
    /**
     * Get data for export
     */
    private function get_export_data($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                id,
                user_name,
                user_message,
                ai_response,
                intent,
                status,
                rating,
                response_time,
                created_at
            FROM $table_name 
            WHERE DATE(created_at) >= %s 
            ORDER BY created_at DESC
        ", $start_date), ARRAY_A);
    }
    
    /**
     * Export data as CSV
     */
    private function export_csv($data) {
        $filename = 'ai-chatbot-analytics-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        if (!empty($data)) {
            // Write headers
            fputcsv($output, array_keys($data[0]));
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data as JSON
     */
    private function export_json($data) {
        $filename = 'ai-chatbot-analytics-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Get chart data for dashboard
     */
    public function get_chart_data($type = 'conversations', $days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        $labels = array();
        $data = array();
        
        // Generate date labels
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));
            
            // Get count for this date
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
                $date
            ));
            
            $data[] = intval($count);
        }
        
        return array(
            'labels' => $labels,
            'data' => $data
        );
    }
}