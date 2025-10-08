<?php
/**
 * AI Chatbot Admin Conversations Class
 * 
 * Handles conversation management and viewing
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AI_Chatbot_Admin_Conversations
 */
class AI_Chatbot_Admin_Conversations {
    
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
        add_action('wp_ajax_ai_chatbot_get_conversation_details', array($this, 'ajax_get_conversation_details'));
        add_action('wp_ajax_ai_chatbot_update_conversation_status', array($this, 'ajax_update_conversation_status'));
        add_action('wp_ajax_ai_chatbot_add_conversation_note', array($this, 'ajax_add_conversation_note'));
        add_action('wp_ajax_ai_chatbot_delete_conversation', array($this, 'ajax_delete_conversation'));
        add_action('wp_ajax_ai_chatbot_export_conversations', array($this, 'ajax_export_conversations'));
    }
    
    /**
     * Render conversations page
     */
    public function render_conversations_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ai-website-chatbot'));
        }
        
        // Handle bulk actions
        $this->handle_bulk_actions();
        
        // Get conversations with pagination
        $items_per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $items_per_page;
        
        // Get filters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $date_filter = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '';
        
        $conversations = $this->get_conversations($items_per_page, $offset, $status_filter, $date_filter);
        $total_items = $this->get_conversations_count($status_filter, $date_filter);
        $total_pages = ceil($total_items / $items_per_page);
        
        // Include conversations template
        include AI_CHATBOT_PLUGIN_DIR . 'admin/partials/admin-conversations-display.php';
    }
    
    /**
     * Get conversations
     */
    public function get_conversations($limit = 20, $offset = 0, $status_filter = '', $date_filter = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        // Build WHERE clause
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($status_filter)) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $status_filter;
        }
        
        if (!empty($date_filter)) {
            switch ($date_filter) {
                case 'today':
                    $where_clauses[] = 'DATE(created_at) = CURDATE()';
                    break;
                case 'week':
                    $where_clauses[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'month':
                    $where_clauses[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                    break;
            }
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        // Build query with proper placeholders
        $query = "SELECT 
            id,
            session_id,
            conversation_id,
            user_message,
            ai_response,
            user_name,
            user_email,
            user_ip,
            status,
            rating,
            intent,
            response_time,
            provider,
            model,
            created_at
        FROM $table_name 
        WHERE $where_clause 
        ORDER BY created_at DESC 
        LIMIT %d, %d";
        
        // Add limit and offset to values
        $where_values[] = intval($offset);
        $where_values[] = intval($limit);
        
        // Execute query
        if (!empty($where_values)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);
        } else {
            // If no WHERE values, build simpler query
            $simple_query = "SELECT 
                id,
                session_id,
                conversation_id,
                user_message,
                ai_response,
                user_name,
                user_email,
                user_ip,
                status,
                rating,
                intent,
                response_time,
                provider,
                model,
                created_at
            FROM $table_name 
            ORDER BY created_at DESC 
            LIMIT %d, %d";
            
            $results = $wpdb->get_results($wpdb->prepare($simple_query, intval($offset), intval($limit)), ARRAY_A);
        }
        
        return $results ?: array();
    }
    
    /**
     * Get conversations count
     */
    public function get_conversations_count($status_filter = '', $date_filter = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0;
        }
        
        // Build WHERE clause
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($status_filter)) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $status_filter;
        }
        
        if (!empty($date_filter)) {
            switch ($date_filter) {
                case 'today':
                    $where_clauses[] = 'DATE(created_at) = CURDATE()';
                    break;
                case 'week':
                    $where_clauses[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'month':
                    $where_clauses[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                    break;
            }
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        $query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        
        if (!empty($where_values)) {
            return intval($wpdb->get_var($wpdb->prepare($query, $where_values)));
        } else {
            return intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name"));
        }
    }
    
    /**
     * Get conversation details with messages
     */
    public function get_conversation_details($conversation_id) {
        global $wpdb;
        
        $conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
        $messages_table = $wpdb->prefix . 'ai_chatbot_messages';
        
        // Get conversation
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $conversations_table WHERE id = %d",
            $conversation_id
        ), ARRAY_A);
        
        if (!$conversation) {
            return null;
        }
        
        // Get messages
        $messages = array();
        if ($wpdb->get_var("SHOW TABLES LIKE '$messages_table'") == $messages_table) {
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $messages_table WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation_id
            ), ARRAY_A);
        }
        
        $conversation['messages'] = $messages ?: array();
        
        return $conversation;
    }
    
    /**
     * Update conversation status
     */
    public function update_conversation_status($conversation_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'status' => sanitize_text_field($status),
                'updated_at' => current_time('mysql')
            ),
            array('id' => intval($conversation_id)),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Add conversation note
     */
    public function add_conversation_note($conversation_id, $note) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Get existing notes
        $current_notes = $wpdb->get_var($wpdb->prepare(
            "SELECT admin_notes FROM $table_name WHERE id = %d",
            $conversation_id
        ));
        
        $notes_array = $current_notes ? maybe_unserialize($current_notes) : array();
        if (!is_array($notes_array)) {
            $notes_array = array();
        }
        
        // Add new note
        $notes_array[] = array(
            'note' => sanitize_textarea_field($note),
            'author' => wp_get_current_user()->display_name,
            'date' => current_time('mysql')
        );
        
        $result = $wpdb->update(
            $table_name,
            array(
                'admin_notes' => maybe_serialize($notes_array),
                'updated_at' => current_time('mysql')
            ),
            array('id' => intval($conversation_id)),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete conversation
     */
    public function delete_conversation($conversation_id) {
        global $wpdb;
        
        $conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
        $messages_table = $wpdb->prefix . 'ai_chatbot_messages';
        
        // Delete messages first
        if ($wpdb->get_var("SHOW TABLES LIKE '$messages_table'") == $messages_table) {
            $wpdb->delete(
                $messages_table,
                array('conversation_id' => intval($conversation_id)),
                array('%d')
            );
        }
        
        // Delete conversation
        $result = $wpdb->delete(
            $conversations_table,
            array('id' => intval($conversation_id)),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!isset($_POST['action']) || !isset($_POST['conversation_ids'])) {
            return;
        }
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ai_chatbot_conversations_bulk_action')) {
            wp_die(__('Security check failed....', 'ai-website-chatbot'));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        $ids = array_map('intval', $_POST['conversation_ids']);
        
        switch ($action) {
            case 'delete':
                $this->bulk_delete_conversations($ids);
                break;
            case 'mark_resolved':
                $this->bulk_update_status($ids, 'resolved');
                break;
            case 'mark_pending':
                $this->bulk_update_status($ids, 'pending');
                break;
            case 'mark_active':
                $this->bulk_update_status($ids, 'active');
                break;
        }
    }
    
    /**
     * Bulk delete conversations
     */
    private function bulk_delete_conversations($ids) {
        if (empty($ids)) {
            return;
        }
        
        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->delete_conversation($id)) {
                $deleted++;
            }
        }
        
        if ($deleted > 0) {
            add_action('admin_notices', function() use ($deleted) {
                echo '<div class="notice notice-success"><p>';
                printf(__('%d conversations deleted successfully.', 'ai-website-chatbot'), $deleted);
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Bulk update status
     */
    private function bulk_update_status($ids, $status) {
        if (empty($ids)) {
            return;
        }
        
        $updated = 0;
        foreach ($ids as $id) {
            if ($this->update_conversation_status($id, $status)) {
                $updated++;
            }
        }
        
        if ($updated > 0) {
            add_action('admin_notices', function() use ($updated, $status) {
                echo '<div class="notice notice-success"><p>';
                printf(__('%d conversations marked as %s successfully.', 'ai-website-chatbot'), $updated, $status);
                echo '</p></div>';
            });
        }
    }
    
    /**
     * AJAX: Get conversation details
     */
    public function ajax_get_conversation_details() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        
        if ($conversation_id <= 0) {
            wp_send_json_error(__('Invalid conversation ID', 'ai-website-chatbot'));
        }
        
        $conversation = $this->get_conversation_details($conversation_id);
        
        if ($conversation) {
            wp_send_json_success($conversation);
        } else {
            wp_send_json_error(__('Conversation not found', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Update conversation status
     */
    public function ajax_update_conversation_status() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        if ($conversation_id <= 0) {
            wp_send_json_error(__('Invalid conversation ID', 'ai-website-chatbot'));
        }
        
        if (!in_array($status, ['active', 'pending', 'resolved', 'closed'])) {
            wp_send_json_error(__('Invalid status', 'ai-website-chatbot'));
        }
        
        $result = $this->update_conversation_status($conversation_id, $status);
        
        if ($result) {
            wp_send_json_success(__('Conversation status updated successfully!', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Failed to update conversation status', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Add conversation note
     */
    public function ajax_add_conversation_note() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $note = sanitize_textarea_field($_POST['note'] ?? '');
        
        if ($conversation_id <= 0) {
            wp_send_json_error(__('Invalid conversation ID', 'ai-website-chatbot'));
        }
        
        if (empty($note)) {
            wp_send_json_error(__('Note cannot be empty', 'ai-website-chatbot'));
        }
        
        $result = $this->add_conversation_note($conversation_id, $note);
        
        if ($result) {
            wp_send_json_success(__('Note added successfully!', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Failed to add note', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Delete conversation
     */
    public function ajax_delete_conversation() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        
        if ($conversation_id <= 0) {
            wp_send_json_error(__('Invalid conversation ID', 'ai-website-chatbot'));
        }
        
        $result = $this->delete_conversation($conversation_id);
        
        if ($result) {
            wp_send_json_success(__('Conversation deleted successfully!', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Failed to delete conversation', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Export conversations
     */
    public function ajax_export_conversations() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
        $date_filter = sanitize_text_field($_POST['date_filter'] ?? '');
        
        $conversations = $this->get_all_conversations_for_export($status_filter, $date_filter);
        
        if (empty($conversations)) {
            wp_send_json_error(__('No conversations to export', 'ai-website-chatbot'));
        }
        
        if ($format === 'json') {
            $this->export_conversations_json($conversations);
        } else {
            $this->export_conversations_csv($conversations);
        }
    }
    
    /**
     * Get all conversations for export
     */
    private function get_all_conversations_for_export($status_filter = '', $date_filter = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Build WHERE clause
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($status_filter)) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $status_filter;
        }
        
        if (!empty($date_filter)) {
            switch ($date_filter) {
                case 'today':
                    $where_clauses[] = 'DATE(created_at) = CURDATE()';
                    break;
                case 'week':
                    $where_clauses[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'month':
                    $where_clauses[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                    break;
            }
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC";
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);
        } else {
            return $wpdb->get_results($query, ARRAY_A);
        }
    }
    
    /**
     * Export conversations as CSV
     */
    private function export_conversations_csv($conversations) {
        $filename = 'ai-chatbot-conversations-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, array('ID', 'User Name', 'User Message', 'AI Response', 'Status', 'Intent', 'Rating', 'Response Time (ms)', 'Created At'));
        
        // Write data rows
        foreach ($conversations as $conversation) {
            fputcsv($output, array(
                $conversation['id'],
                $conversation['user_name'] ?: 'Anonymous',
                $conversation['user_message'],
                $conversation['ai_response'],
                $conversation['status'],
                $conversation['intent'] ?: '',
                $conversation['rating'] ?: '',
                $conversation['response_time'] ?: '',
                $conversation['created_at']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export conversations as JSON
     */
    private function export_conversations_json($conversations) {
        $filename = 'ai-chatbot-conversations-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($conversations, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Get conversation statistics
     */
    public function get_conversation_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array(
                'total_conversations' => 0,
                'active_conversations' => 0,
                'resolved_conversations' => 0,
                'pending_conversations' => 0,
                'conversations_today' => 0,
                'average_rating' => 0
            );
        }
        
        $stats = array();
        
        // Total conversations
        $stats['total_conversations'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name"));
        
        // Active conversations
        $stats['active_conversations'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s",
            'active'
        )));
        
        // Resolved conversations
        $stats['resolved_conversations'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s",
            'completed'
        )));
        
        // Pending conversations
        $stats['pending_conversations'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s",
            'pending'
        )));
        
        // Conversations today
        $stats['conversations_today'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = CURDATE()"
        ));
        
        // Average rating
        $avg_rating = $wpdb->get_var("SELECT AVG(rating) FROM $table_name WHERE rating IS NOT NULL");
        $stats['average_rating'] = $avg_rating ? round(floatval($avg_rating), 1) : 0;
        
        return $stats;
    }
}