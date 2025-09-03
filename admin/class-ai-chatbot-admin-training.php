<?php
/**
 * Admin Training Class
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin training functionality.
 */
class AI_Chatbot_Admin_Training {

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

        add_action('wp_ajax_ai_chatbot_save_training_data', array($this, 'save_training_data'));
        add_action('wp_ajax_ai_chatbot_delete_training_data', array($this, 'delete_training_data'));
        add_action('wp_ajax_ai_chatbot_import_training_data', array($this, 'import_training_data'));
    }

    /**
     * Display training page.
     *
     * @since 1.0.0
     */
    public function display_training_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/admin-training-display.php';
    }

    /**
     * Save training data via AJAX.
     *
     * @since 1.0.0
     */
    public function save_training_data() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }

        $question = sanitize_textarea_field($_POST['question'] ?? '');
        $answer = sanitize_textarea_field($_POST['answer'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        $id = intval($_POST['id'] ?? 0);

        if (empty($question) || empty($answer)) {
            wp_send_json_error(__('Question and answer are required', 'ai-website-chatbot'));
        }

        $result = $this->save_qa_pair($id, $question, $answer, $category);

        if ($result) {
            wp_send_json_success(__('Training data saved successfully', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Failed to save training data', 'ai-website-chatbot'));
        }
    }

    /**
     * Delete training data via AJAX.
     *
     * @since 1.0.0
     */
    public function delete_training_data() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(__('Invalid training data ID', 'ai-website-chatbot'));
        }

        $result = $this->delete_qa_pair($id);

        if ($result) {
            wp_send_json_success(__('Training data deleted successfully', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Failed to delete training data', 'ai-website-chatbot'));
        }
    }

    /**
     * Import training data via AJAX.
     *
     * @since 1.0.0
     */
    public function import_training_data() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }

        if (!isset($_FILES['training_file'])) {
            wp_send_json_error(__('No file uploaded', 'ai-website-chatbot'));
        }

        $file = $_FILES['training_file'];
        $result = $this->import_from_file($file);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(sprintf(__('Successfully imported %d training pairs', 'ai-website-chatbot'), $result));
        }
    }

    /**
     * Save Q&A pair.
     *
     * @param int $id Existing ID or 0 for new.
     * @param string $question Question text.
     * @param string $answer Answer text.
     * @param string $category Category.
     * @return bool Success status.
     * @since 1.0.0
     */
    private function save_qa_pair($id, $question, $answer, $category) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        $data = array(
            'question' => $question,
            'answer' => $answer,
            'category' => $category,
            'updated_at' => current_time('mysql'),
        );

        if ($id > 0) {
            // Update existing
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%s', '%s', '%s', '%s', '%s')
            );
        }

        return $result !== false;
    }

    /**
     * Delete Q&A pair.
     *
     * @param int $id Training data ID.
     * @return bool Success status.
     * @since 1.0.0
     */
    private function delete_qa_pair($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Import training data from file.
     *
     * @param array $file Uploaded file data.
     * @return int|WP_Error Number of imported pairs or error.
     * @since 1.0.0
     */
    private function import_from_file($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload failed', 'ai-website-chatbot'));
        }

        $file_type = wp_check_filetype($file['name']);
        if (!in_array($file_type['ext'], array('csv', 'json'))) {
            return new WP_Error('invalid_file_type', __('Only CSV and JSON files are supported', 'ai-website-chatbot'));
        }

        $file_content = file_get_contents($file['tmp_name']);
        
        if ($file_type['ext'] === 'csv') {
            return $this->import_from_csv($file_content);
        } else {
            return $this->import_from_json($file_content);
        }
    }

    /**
     * Import from CSV content.
     *
     * @param string $content CSV content.
     * @return int|WP_Error Number of imported pairs or error.
     * @since 1.0.0
     */
    private function import_from_csv($content) {
        $lines = str_getcsv($content, "\n");
        $imported = 0;

        foreach ($lines as $line) {
            $data = str_getcsv($line);
            
            if (count($data) < 2) {
                continue; // Skip invalid lines
            }

            $question = sanitize_textarea_field($data[0]);
            $answer = sanitize_textarea_field($data[1]);
            $category = isset($data[2]) ? sanitize_text_field($data[2]) : '';

            if ($this->save_qa_pair(0, $question, $answer, $category)) {
                $imported++;
            }
        }

        return $imported;
    }

    /**
     * Import from JSON content.
     *
     * @param string $content JSON content.
     * @return int|WP_Error Number of imported pairs or error.
     * @since 1.0.0
     */
    private function import_from_json($content) {
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON format', 'ai-website-chatbot'));
        }

        $imported = 0;

        foreach ($data as $item) {
            if (!isset($item['question']) || !isset($item['answer'])) {
                continue; // Skip invalid items
            }

            $question = sanitize_textarea_field($item['question']);
            $answer = sanitize_textarea_field($item['answer']);
            $category = isset($item['category']) ? sanitize_text_field($item['category']) : '';

            if ($this->save_qa_pair(0, $question, $answer, $category)) {
                $imported++;
            }
        }

        return $imported;
    }

    /**
     * Get all training data.
     *
     * @return array Training data.
     * @since 1.0.0
     */
    public function get_training_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC"
        );

        return $results ?? array();
    }

    /**
     * Get training data categories.
     *
     * @return array Categories.
     * @since 1.0.0
     */
    public function get_categories() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        $results = $wpdb->get_col(
            "SELECT DISTINCT category FROM {$table_name} WHERE category != '' ORDER BY category"
        );

        return $results ?? array();
    }

    /**
     * Export training data.
     *
     * @param string $format Export format.
     * @return string|WP_Error Export content or error.
     * @since 1.0.0
     */
    public function export_training_data($format = 'csv') {
        $data = $this->get_training_data();
        
        switch ($format) {
            case 'csv':
                return $this->export_training_to_csv($data);
            case 'json':
                return $this->export_training_to_json($data);
            default:
                return new WP_Error('invalid_format', __('Invalid export format', 'ai-website-chatbot'));
        }
    }

    /**
     * Export training data to CSV.
     *
     * @param array $data Training data.
     * @return string CSV content.
     * @since 1.0.0
     */
    private function export_training_to_csv($data) {
        ob_start();
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, array('Question', 'Answer', 'Category', 'Created', 'Updated'));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, array(
                $row->question,
                $row->answer,
                $row->category,
                $row->created_at,
                $row->updated_at
            ));
        }
        
        fclose($output);
        
        return ob_get_clean();
    }

    /**
     * Export training data to JSON.
     *
     * @param array $data Training data.
     * @return string JSON content.
     * @since 1.0.0
     */
    private function export_training_to_json($data) {
        return wp_json_encode($data, JSON_PRETTY_PRINT);
    }
}
