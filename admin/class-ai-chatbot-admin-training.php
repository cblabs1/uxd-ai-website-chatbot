<?php
/**
 * AI Chatbot Admin Training Class
 * 
 * Handles training data management and chatbot training
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AI_Chatbot_Admin_Training
 */
class AI_Chatbot_Admin_Training {
    
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
        add_action('wp_ajax_ai_chatbot_add_training_data', array($this, 'ajax_add_training_data'));
        add_action('wp_ajax_ai_chatbot_get_training_data', array($this, 'ajax_get_training_data'));
        add_action('wp_ajax_ai_chatbot_delete_training_data', array($this, 'ajax_delete_training_data'));
        add_action('wp_ajax_ai_chatbot_import_training_data', array($this, 'ajax_import_training_data'));
        add_action('wp_ajax_ai_chatbot_export_training_data', array($this, 'ajax_export_training_data'));
        add_action('wp_ajax_ai_chatbot_train_model', array($this, 'ajax_train_model'));
        add_action('init', array($this, 'ensure_training_table_exists'));
        
    }
    
    /**
     * Render training page
     */
    public function render_training_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ai-website-chatbot'));
        }
        
        // Handle bulk actions
        $this->handle_bulk_actions();
        
        // Get training data
        $training_data = $this->get_training_data();
        $total_items = $this->get_training_data_count();
        
        // Pagination
        $items_per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $items_per_page;
        
        $training_data = $this->get_training_data($items_per_page, $offset);
        
        // Include training template
        include AI_CHATBOT_PLUGIN_DIR . 'admin/partials/admin-training-display.php';
    }
    
    /**
     * Get training data
     */
    public function get_training_data($limit = 20, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);
        
        return $results ?: array();
    }
    
    /**
     * Get training data count
     */
    public function get_training_data_count() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0;
        }
        
        return intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name"));
    }
    
    /**
     * Add training data
     */
    public function add_training_data($question, $answer, $intent = '', $tags = array()) {
        global $wpdb;
    
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        // Check if table exists first
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Table doesn't exist, create it
            $this->create_training_data_table();
        }
        
        // Prepare data
        $data = array(
            'question' => wp_kses_post($question),
            'answer' => wp_kses_post($answer),
            'intent' => sanitize_text_field($intent),
            'tags' => maybe_serialize($tags),
            'status' => 'active',
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%s');
        
        // Insert data
        $result = $wpdb->insert($table_name, $data, $formats);
        
        if ($result === false) {
            // Log the error for debugging
            error_log('AI Chatbot Training Data Insert Error: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }

    private function create_training_data_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            question longtext NOT NULL,
            answer longtext NOT NULL,
            intent varchar(255) DEFAULT '',
            tags text DEFAULT '',
            status varchar(20) DEFAULT 'active',
            user_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY intent (intent),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Update training data
     */
    public function update_training_data($id, $question, $answer, $intent = '', $tags = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'question' => sanitize_textarea_field($question),
                'answer' => sanitize_textarea_field($answer),
                'intent' => sanitize_text_field($intent),
                'tags' => maybe_serialize($tags),
                'updated_at' => current_time('mysql')
            ),
            array('id' => intval($id)),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete training data
     */
    public function delete_training_data($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => intval($id)),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!isset($_POST['action']) || !isset($_POST['training_ids'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_chatbot_training_bulk_action')) {
            wp_die(__('Security check failed', 'ai-website-chatbot'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        $ids = array_map('intval', $_POST['training_ids']);
        
        switch ($action) {
            case 'delete':
                $this->bulk_delete_training_data($ids);
                break;
            case 'activate':
                $this->bulk_update_status($ids, 'active');
                break;
            case 'deactivate':
                $this->bulk_update_status($ids, 'inactive');
                break;
        }
    }
    
    /**
     * Bulk delete training data
     */
    private function bulk_delete_training_data($ids) {
        global $wpdb;
        
        if (empty($ids)) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE id IN ($ids_placeholder)",
            $ids
        ));
        
        if ($deleted > 0) {
            add_action('admin_notices', function() use ($deleted) {
                echo '<div class="notice notice-success"><p>';
                printf(__('%d training items deleted successfully.', 'ai-website-chatbot'), $deleted);
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Bulk update status
     */
    private function bulk_update_status($ids, $status) {
        global $wpdb;
        
        if (empty($ids)) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET status = %s WHERE id IN ($ids_placeholder)",
            array_merge([$status], $ids)
        ));
        
        if ($updated > 0) {
            add_action('admin_notices', function() use ($updated, $status) {
                echo '<div class="notice notice-success"><p>';
                printf(__('%d training items %s successfully.', 'ai-website-chatbot'), $updated, $status === 'active' ? 'activated' : 'deactivated');
                echo '</p></div>';
            });
        }
    }
    
    /**
     * AJAX: Add training data
     */
    public function ajax_add_training_data() {
        if (!check_ajax_referer('ai_chatbot_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'ai-website-chatbot'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
            return;
        }
        
        // Get and validate input data
        $question = sanitize_textarea_field($_POST['question'] ?? '');
        $answer = sanitize_textarea_field($_POST['answer'] ?? '');
        $intent = sanitize_text_field($_POST['intent'] ?? '');
        
        // Handle tags - they might come as JSON string
        $tags = array();
        if (isset($_POST['tags'])) {
            if (is_string($_POST['tags'])) {
                // Try to decode JSON
                $decoded_tags = json_decode(stripslashes($_POST['tags']), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_tags)) {
                    $tags = array_map('sanitize_text_field', $decoded_tags);
                } else {
                    // Handle as comma-separated string
                    $tags = array_map('sanitize_text_field', explode(',', $_POST['tags']));
                }
            } elseif (is_array($_POST['tags'])) {
                $tags = array_map('sanitize_text_field', $_POST['tags']);
            }
        }
        
        // Validate required fields
        if (empty($question)) {
            wp_send_json_error(__('Question is required', 'ai-website-chatbot'));
            return;
        }
        
        if (empty($answer)) {
            wp_send_json_error(__('Answer is required', 'ai-website-chatbot'));
            return;
        }
        
        // Validate minimum lengths
        if (strlen($question) < 10) {
            wp_send_json_error(__('Question must be at least 10 characters long', 'ai-website-chatbot'));
            return;
        }
        
        if (strlen($answer) < 10) {
            wp_send_json_error(__('Answer must be at least 10 characters long', 'ai-website-chatbot'));
            return;
        }
        
        // Check if this is an update
        $training_id = isset($_POST['training_id']) ? intval($_POST['training_id']) : 0;
        
        if ($training_id > 0) {
            // Update existing training data
            $result = $this->update_training_data($training_id, $question, $answer, $intent, $tags);
            $message = $result ? __('Training data updated successfully!', 'ai-website-chatbot') : __('Failed to update training data', 'ai-website-chatbot');
        } else {
            // Add new training data
            $result = $this->add_training_data($question, $answer, $intent, $tags);
            $message = $result ? __('Training data added successfully!', 'ai-website-chatbot') : __('Failed to add training data', 'ai-website-chatbot');
        }
        
        if ($result) {
            wp_send_json_success($message);
        } else {
            wp_send_json_error($message);
        }
    }
    
    /**
     * AJAX: Delete training data
     */
    public function ajax_delete_training_data() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            wp_send_json_error(__('Invalid training data ID', 'ai-website-chatbot'));
        }
        
        $result = $this->delete_training_data($id);
        
        if ($result) {
            wp_send_json_success(__('Training data deleted successfully!', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Failed to delete training data', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Import training data
     */
    public function ajax_import_training_data() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        if (!isset($_FILES['training_file'])) {
            wp_send_json_error(__('No file uploaded', 'ai-website-chatbot'));
        }
        
        $file = $_FILES['training_file'];
        $file_path = $file['tmp_name'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, ['csv', 'json'])) {
            wp_send_json_error(__('Unsupported file format. Please use CSV or JSON.', 'ai-website-chatbot'));
        }
        
        try {
            if ($file_extension === 'csv') {
                $imported = $this->import_csv($file_path);
            } else {
                $imported = $this->import_json($file_path);
            }
            
            if ($imported > 0) {
                wp_send_json_success(sprintf(__('%d training items imported successfully!', 'ai-website-chatbot'), $imported));
            } else {
                wp_send_json_error(__('No training data was imported. Please check your file format.', 'ai-website-chatbot'));
            }
        } catch (Exception $e) {
            wp_send_json_error(sprintf(__('Import failed: %s', 'ai-website-chatbot'), $e->getMessage()));
        }
    }
    
    /**
     * Import CSV file
     */
    private function import_csv($file_path) {
        $imported = 0;
        
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            
            if (!$headers || !in_array('question', $headers) || !in_array('answer', $headers)) {
                throw new Exception(__('CSV must contain at least "question" and "answer" columns', 'ai-website-chatbot'));
            }
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row = array_combine($headers, $data);
                
                if (!empty($row['question']) && !empty($row['answer'])) {
                    $result = $this->add_training_data(
                        $row['question'],
                        $row['answer'],
                        $row['intent'] ?? '',
                        isset($row['tags']) ? explode(',', $row['tags']) : array()
                    );
                    
                    if ($result) {
                        $imported++;
                    }
                }
            }
            fclose($handle);
        }
        
        return $imported;
    }
    
    /**
     * Import JSON file
     */
    private function import_json($file_path) {
        $json_content = file_get_contents($file_path);
        $data = json_decode($json_content, true);
        
        if (!$data) {
            throw new Exception(__('Invalid JSON format', 'ai-website-chatbot'));
        }
        
        $imported = 0;
        
        foreach ($data as $item) {
            if (isset($item['question']) && isset($item['answer'])) {
                $result = $this->add_training_data(
                    $item['question'],
                    $item['answer'],
                    $item['intent'] ?? '',
                    $item['tags'] ?? array()
                );
                
                if ($result) {
                    $imported++;
                }
            }
        }
        
        return $imported;
    }
    
    /**
     * AJAX: Export training data
     */
    public function ajax_export_training_data() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $training_data = $this->get_all_training_data();
        
        if (empty($training_data)) {
            wp_send_json_error(__('No training data to export', 'ai-website-chatbot'));
        }
        
        if ($format === 'json') {
            $this->export_training_json($training_data);
        } else {
            $this->export_training_csv($training_data);
        }
    }
    
    /**
     * Get all training data
     */
    private function get_all_training_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);
    }
    
    /**
     * Export training data as CSV
     */
    private function export_training_csv($data) {
        $filename = 'ai-chatbot-training-data-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, array('question', 'answer', 'intent', 'tags', 'status', 'created_at'));
        
        // Write data rows
        foreach ($data as $row) {
            $tags = maybe_unserialize($row['tags']);
            if (is_array($tags)) {
                $tags = implode(',', $tags);
            }
            
            fputcsv($output, array(
                $row['question'],
                $row['answer'],
                $row['intent'],
                $tags,
                $row['status'],
                $row['created_at']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export training data as JSON
     */
    private function export_training_json($data) {
        $filename = 'ai-chatbot-training-data-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Process data for JSON export
        $processed_data = array();
        foreach ($data as $row) {
            $tags = maybe_unserialize($row['tags']);
            if (!is_array($tags)) {
                $tags = array();
            }
            
            $processed_data[] = array(
                'question' => $row['question'],
                'answer' => $row['answer'],
                'intent' => $row['intent'],
                'tags' => $tags,
                'status' => $row['status'],
                'created_at' => $row['created_at']
            );
        }
        
        echo json_encode($processed_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * AJAX: Train model
     */
    public function ajax_train_model() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        // This is a placeholder for model training functionality
        // In a real implementation, this would trigger the training process
        $training_data = $this->get_active_training_data();
        
        if (empty($training_data)) {
            wp_send_json_error(__('No active training data found', 'ai-website-chatbot'));
        }
        
        // Simulate training process
        $result = $this->process_training_data($training_data);
        
        if ($result) {
            update_option('ai_chatbot_last_training', current_time('mysql'));
            wp_send_json_success(__('Model training completed successfully!', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Model training failed', 'ai-website-chatbot'));
        }
    }
    
    /**
     * Get active training data
     */
    private function get_active_training_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        return $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active' ORDER BY created_at DESC",
            ARRAY_A
        );
    }
    
    /**
     * Process training data (placeholder implementation)
     */
    private function process_training_data($training_data) {
        // This is where you would implement actual model training
        // For now, we'll just validate the data and return success
        
        $processed_count = 0;
        foreach ($training_data as $item) {
            if (!empty($item['question']) && !empty($item['answer'])) {
                $processed_count++;
            }
        }
        
        return $processed_count > 0;
    }
    
    /**
     * Get training statistics
     */
    public function get_training_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        $stats = array(
            'total_items' => 0,
            'active_items' => 0,
            'inactive_items' => 0,
            'intents_count' => 0,
            'last_training' => get_option('ai_chatbot_last_training', __('Never', 'ai-website-chatbot'))
        );
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return $stats;
        }
        
        $stats['total_items'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name"));
        $stats['active_items'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'"));
        $stats['inactive_items'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'inactive'"));
        $stats['intents_count'] = intval($wpdb->get_var("SELECT COUNT(DISTINCT intent) FROM $table_name WHERE intent != ''"));
        
        return $stats;
    }
    
    /**
     * Get available intents
     */
    public function get_available_intents() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $results = $wpdb->get_col("SELECT DISTINCT intent FROM $table_name WHERE intent != '' ORDER BY intent");
        
        return $results ?: array();
    }
    
    /**
     * Get available tags
     */
    public function get_available_tags() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $results = $wpdb->get_col("SELECT tags FROM $table_name WHERE tags != ''");
        $all_tags = array();
        
        foreach ($results as $tags_string) {
            $tags = maybe_unserialize($tags_string);
            if (is_array($tags)) {
                $all_tags = array_merge($all_tags, $tags);
            }
        }
        
        return array_unique($all_tags);
    }
}