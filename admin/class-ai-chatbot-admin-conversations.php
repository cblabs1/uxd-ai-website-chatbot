<?php
/**
 * Admin Conversations Class
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin conversations functionality.
 */
class AI_Chatbot_Admin_Conversations {

    /**
     * The plugin name.
     *
     * @var string
     * @since 1.0.0
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @var string
     * @since 1.0.0
     */
    private $version;

    /**
     * Initialize the class.
     *
     * @param string $plugin_name Plugin name.
     * @param string $version Plugin version.
     * @since 1.0.0
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('wp_ajax_ai_chatbot_get_conversation_details', array($this, 'get_conversation_details'));
        add_action('wp_ajax_ai_chatbot_delete_conversation', array($this, 'delete_conversation'));
        add_action('wp_ajax_ai_chatbot_export_conversations', array($this, 'export_conversations'));
    }

    /**
     * Display conversations page.
     *
     * @since 1.0.0
     */
    public function display_conversations_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/admin-conversations-display.php';
    }

    /**
     * Get conversation details via AJAX.
     *
     * @since 1.0.0
     */
    public function get_conversation_details() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(__('Invalid conversation ID', 'ai-website-chatbot'));
        }

        $conversation = $this->get_conversation_by_id($conversation_id);

        if (!$conversation) {
            wp_send_json_error(__('Conversation not found', 'ai-website-chatbot'));
        }

        wp_send_json_success($conversation);
    }

    /**
     * Delete conversation via AJAX.
     *
     * @since 1.0.0
     */
    public function delete_conversation() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(__('Invalid conversation ID', 'ai-website-chatbot'));
        }

        $result = $this->delete_conversation_by_id($conversation_id);

        if ($result) {
            wp_send_json_success(__('Conversation deleted successfully', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Failed to delete conversation', 'ai-website-chatbot'));
        }
    }

    /**
     * Export conversations via AJAX.
     *
     * @since 1.0.0
     */
    public function export_conversations() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }

        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');

        $conversations = $this->get_conversations_for_export($date_from, $date_to);
        
        if ($format === 'csv') {
            $content = $this->export_to_csv($conversations);
            $filename = 'chatbot-conversations-' . date('Y-m-d') . '.csv';
            $content_type = 'text/csv';
        } else {
            $content = $this->export_to_json($conversations);
            $filename = 'chatbot-conversations-' . date('Y-m-d') . '.json';
            $content_type = 'application/json';
        }

        wp_send_json_success(array(
            'content' => base64_encode($content),
            'filename' => $filename,
            'content_type' => $content_type
        ));
    }

    /**
     * Get conversations with pagination.
     *
     * @param int $page Page number.
     * @param int $per_page Items per page.
     * @param array $filters Filters.
     * @return array Conversations data.
     * @since 1.0.0
     */
    public function get_conversations($page = 1, $per_page = 20, $filters = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Build WHERE clause
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(created_at) >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(created_at) <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = '(user_message LIKE %s OR bot_response LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $total_query = $wpdb->prepare($total_query, $where_values);
        }
        $total_items = $wpdb->get_var($total_query);
        
        // Get conversations
        $offset = ($page - 1) * $per_page;
        $conversations_query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        $conversations_query = $wpdb->prepare($conversations_query, $query_values);
        $conversations = $wpdb->get_results($conversations_query);
        
        return array(
            'conversations' => $conversations ?? array(),
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $per_page),
            'current_page' => $page,
            'per_page' => $per_page,
        );
    }

    /**
     * Get conversation by ID.
     *
     * @param int $id Conversation ID.
     * @return object|null Conversation data.
     * @since 1.0.0
     */
    private function get_conversation_by_id($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Delete conversation by ID.
     *
     * @param int $id Conversation ID.
     * @return bool Success status.
     * @since 1.0.0
     */
    private function delete_conversation_by_id($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get conversations for export.
     *
     * @param string $date_from Start date.
     * @param string $date_to End date.
     * @return array Conversations.
     * @since 1.0.0
     */
    private function get_conversations_for_export($date_from = '', $date_to = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($date_from)) {
            $where_conditions[] = 'DATE(created_at) >= %s';
            $where_values[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = 'DATE(created_at) <= %s';
            $where_values[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC";
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query) ?? array();
    }

    /**
     * Export conversations to CSV.
     *
     * @param array $conversations Conversations data.
     * @return string CSV content.
     * @since 1.0.0
     */
    private function export_to_csv($conversations) {
        ob_start();
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, array(
            'ID',
            'User Message',
            'Bot Response',
            'User IP',
            'User Agent',
            'Response Time',
            'Tokens Used',
            'Created At'
        ));
        
        // Write data
        foreach ($conversations as $conversation) {
            fputcsv($output, array(
                $conversation->id,
                $conversation->user_message,
                $conversation->bot_response,
                $conversation->user_ip ?? '',
                $conversation->user_agent ?? '',
                $conversation->response_time ?? '',
                $conversation->tokens_used ?? '',
                $conversation->created_at
            ));
        }
        
        fclose($output);
        
        return ob_get_clean();
    }

    /**
     * Export conversations to JSON.
     *
     * @param array $conversations Conversations data.
     * @return string JSON content.
     * @since 1.0.0
     */
    private function export_to_json($conversations) {
        return wp_json_encode($conversations, JSON_PRETTY_PRINT);
    }

    /**
     * Get conversation statistics.
     *
     * @return array Statistics.
     * @since 1.0.0
     */
    public function get_conversation_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $stats = array();
        
        // Total conversations
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Today's conversations
        $stats['today'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )
        );
        
        // This week's conversations
        $stats['this_week'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE YEARWEEK(created_at) = YEARWEEK(NOW())"
        );
        
        // Average response time
        $stats['avg_response_time'] = $wpdb->get_var(
            "SELECT AVG(response_time) FROM {$table_name} WHERE response_time > 0"
        );
        
        return $stats;
    }

    /**
     * Clean old conversations based on retention policy.
     *
     * @return int Number of deleted conversations.
     * @since 1.0.0
     */
    public function cleanup_old_conversations() {
        $retention_days = get_option('ai_chatbot_data_retention_days', 30);
        
        if (!$retention_days) {
            return 0; // No cleanup if retention is 0
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
        
        return $result;
    }
}
