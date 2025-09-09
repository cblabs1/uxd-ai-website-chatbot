<?php
/**
 * AI Chatbot Admin Class
 * 
 * Handles all admin-related functionality for the AI Chatbot plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AI_Chatbot_Admin
 */
class AI_Chatbot_Admin {
    
    /**
     * The settings management instance
     */
    private $settings;
    
    /**
     * The dashboard instance
     */
    private $dashboard;
    
    /**
     * The analytics instance
     */
    private $analytics;
    
    /**
     * The training instance
     */
    private $training;
    
    /**
     * The conversations instance
     */
    private $conversations;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load admin dependencies
     */
    private function load_dependencies() {
        // Load admin settings
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-settings.php';
        $this->settings = new AI_Chatbot_Admin_Settings();
        
        // Load dashboard (but don't initialize dashboard widgets here)
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-dashboard.php';
        $this->dashboard = new AI_Chatbot_Admin_Dashboard();
        
        // Load analytics
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-analytics.php';
        $this->analytics = new AI_Chatbot_Admin_Analytics();
        
        // Load training
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-training.php';
        $this->training = new AI_Chatbot_Admin_Training();
        
        // Load conversations
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-conversations.php';
        $this->conversations = new AI_Chatbot_Admin_Conversations();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Admin init - but NOT for dashboard widgets
        add_action('admin_init', array($this, 'admin_init'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . AI_CHATBOT_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
        
        // AJAX handlers
        add_action('wp_ajax_ai_chatbot_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_ai_chatbot_refresh_system_status', array($this, 'ajax_refresh_system_status'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('AI Chatbot', 'ai-website-chatbot'),
            __('AI Chatbot', 'ai-website-chatbot'),
            'manage_options',
            'ai-chatbot',
            array($this, 'render_main_page'),
            'dashicons-format-chat',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'ai-chatbot',
            __('Settings', 'ai-website-chatbot'),
            __('Settings', 'ai-website-chatbot'),
            'manage_options',
            'ai-chatbot-settings',
            array($this->settings, 'render_settings_page')
        );
        
        // Analytics submenu
        add_submenu_page(
            'ai-chatbot',
            __('Analytics', 'ai-website-chatbot'),
            __('Analytics', 'ai-website-chatbot'),
            'manage_options',
            'ai-chatbot-analytics',
            array($this->analytics, 'render_analytics_page')
        );
        
        // Training submenu
        add_submenu_page(
            'ai-chatbot',
            __('Training', 'ai-website-chatbot'),
            __('Training', 'ai-website-chatbot'),
            'manage_options',
            'ai-chatbot-training',
            array($this->training, 'render_training_page')
        );
        
        // Conversations submenu
        add_submenu_page(
            'ai-chatbot',
            __('Conversations', 'ai-website-chatbot'),
            __('Conversations', 'ai-website-chatbot'),
            'manage_options',
            'ai-chatbot-conversations',
            array($this->conversations, 'render_conversations_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'ai-chatbot') === false) {
            return;
        }
        
        // Enqueue admin styles
        wp_enqueue_style(
            'ai-chatbot-admin-css',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/admin/admin-main.css',
            array(),
            AI_CHATBOT_VERSION
        );
        
        // Enqueue Chart.js for analytics
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );
        
        // Enqueue main admin script
        wp_enqueue_script(
            'ai-chatbot-admin-js',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/admin/admin-main.js',
            array('jquery', 'chart-js'),
            AI_CHATBOT_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('ai-chatbot-admin-js', 'aiChatbotAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_admin_nonce'), // CONSISTENT NONCE NAME
            'strings' => array(
                'saved' => __('Settings saved successfully!', 'ai-website-chatbot'),
                'error' => __('An error occurred. Please try again.', 'ai-website-chatbot'),
                'testing' => __('Testing connection...', 'ai-website-chatbot'),
                'connected' => __('Connection successful!', 'ai-website-chatbot'),
                'connection_failed' => __('Connection failed. Please check your settings.', 'ai-website-chatbot'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'ai-website-chatbot'),
                'loading' => __('Loading...', 'ai-website-chatbot'),
                'saving' => __('Saving...', 'ai-website-chatbot')
            )
        ));
        
        // Page-specific scripts
        $this->enqueue_page_specific_scripts($hook);
    }
    
    /**
     * Enqueue page-specific scripts
     */
    private function enqueue_page_specific_scripts($hook) {
        if (strpos($hook, 'ai-chatbot-settings') !== false) {
            wp_enqueue_script(
                'ai-chatbot-settings-js',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/admin/settings.js',
                array('jquery', 'ai-chatbot-admin-js'),
                AI_CHATBOT_VERSION,
                true
            );
        }
        
        if (strpos($hook, 'ai-chatbot-analytics') !== false) {
            wp_enqueue_script(
                'ai-chatbot-analytics-js',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/admin/analytics.js',
                array('jquery', 'chart-js', 'ai-chatbot-admin-js'),
                AI_CHATBOT_VERSION,
                true
            );
        }
        
        if (strpos($hook, 'ai-chatbot-training') !== false) {
            wp_enqueue_script(
                'ai-chatbot-training-js',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/admin/training.js',
                array('jquery', 'ai-chatbot-admin-js'),
                AI_CHATBOT_VERSION,
                true
            );
        }
    }
    
    /**
     * Admin initialization (NOT for dashboard widgets)
     */
    public function admin_init() {
        // Register settings
        register_setting('ai_chatbot_settings', 'ai_chatbot_settings');
        
        // Add settings sections and fields
        $this->add_settings_sections();
        
        // Handle bulk actions
        $this->handle_bulk_actions();
        
        // Check for plugin updates or first-time setup
        $this->maybe_show_setup_notice();
    }
    
    /**
     * Add settings sections
     */
    private function add_settings_sections() {
        // General Settings Section
        add_settings_section(
            'ai_chatbot_general',
            __('General Settings', 'ai-website-chatbot'),
            array($this, 'render_general_section_description'),
            'ai-chatbot-settings'
        );
        
        // AI Provider Settings Section
        add_settings_section(
            'ai_chatbot_provider',
            __('AI Provider Settings', 'ai-website-chatbot'),
            array($this, 'render_provider_section_description'),
            'ai-chatbot-settings'
        );
        
        // Display Settings Section
        add_settings_section(
            'ai_chatbot_display',
            __('Display Settings', 'ai-website-chatbot'),
            array($this, 'render_display_section_description'),
            'ai-chatbot-settings'
        );
        
        // Advanced Settings Section
        add_settings_section(
            'ai_chatbot_advanced',
            __('Advanced Settings', 'ai-website-chatbot'),
            array($this, 'render_advanced_section_description'),
            'ai-chatbot-settings'
        );
    }
    
    /**
     * Render main admin page
     */
    public function render_main_page() {
        include AI_CHATBOT_PLUGIN_DIR . 'admin/partials/admin-display.php';
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        if ($this->analytics && method_exists($this->analytics, 'render_analytics_page')) {
            $this->analytics->render_analytics_page();
        } else {
            echo '<div class="wrap"><h1>' . __('Analytics', 'ai-website-chatbot') . '</h1>';
            echo '<p>' . __('Analytics functionality is being loaded...', 'ai-website-chatbot') . '</p></div>';
        }
    }
    
    /**
     * Render training page
     */
    public function render_training_page() {
        if ($this->training && method_exists($this->training, 'render_training_page')) {
            $this->training->render_training_page();
        } else {
            echo '<div class="wrap"><h1>' . __('Training', 'ai-website-chatbot') . '</h1>';
            echo '<p>' . __('Training functionality is being loaded...', 'ai-website-chatbot') . '</p></div>';
        }
    }
    
    /**
     * Render conversations page
     */
    public function render_conversations_page() {
        if ($this->conversations && method_exists($this->conversations, 'render_conversations_page')) {
            $this->conversations->render_conversations_page();
        } else {
            echo '<div class="wrap"><h1>' . __('Conversations', 'ai-website-chatbot') . '</h1>';
            echo '<p>' . __('Conversations functionality is being loaded...', 'ai-website-chatbot') . '</p></div>';
        }
    }
    
    /**
     * Section descriptions
     */
    public function render_general_section_description() {
        echo '<p>' . __('Configure basic chatbot settings.', 'ai-website-chatbot') . '</p>';
    }
    
    public function render_provider_section_description() {
        echo '<p>' . __('Configure your AI provider settings and API keys.', 'ai-website-chatbot') . '</p>';
    }
    
    public function render_display_section_description() {
        echo '<p>' . __('Customize how the chatbot appears on your website.', 'ai-website-chatbot') . '</p>';
    }
    
    public function render_advanced_section_description() {
        echo '<p>' . __('Advanced configuration options for power users.', 'ai-website-chatbot') . '</p>';
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!isset($_POST['action']) || !isset($_POST['_wpnonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_chatbot_bulk_action')) {
            wp_die(__('Security check failed', 'ai-website-chatbot'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'delete_conversations':
                $this->bulk_delete_conversations();
                break;
            case 'export_conversations':
                $this->bulk_export_conversations();
                break;
            case 'clear_training_data':
                $this->clear_training_data();
                break;
        }
    }
    
    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ai-chatbot-settings') . '">' . __('Settings', 'ai-website-chatbot') . '</a>';
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check if API key is configured
        $settings = get_option('ai_chatbot_settings', array());
        if (empty($settings['api_key']) && $this->is_chatbot_admin_page()) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('AI Chatbot is not configured yet.', 'ai-website-chatbot'); ?>
                    <a href="<?php echo admin_url('admin.php?page=ai-chatbot-settings'); ?>">
                        <?php _e('Configure it now', 'ai-website-chatbot'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        // Show success messages
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved successfully!', 'ai-website-chatbot'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Check if we're on a chatbot admin page
     */
    private function is_chatbot_admin_page() {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'ai-chatbot') !== false;
    }
    
    /**
     * Maybe show setup notice for first-time users
     */
    private function maybe_show_setup_notice() {
        $setup_completed = get_option('ai_chatbot_setup_completed', false);
        
        if (!$setup_completed && $this->is_chatbot_admin_page()) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-info">
                    <p>
                        <strong><?php _e('Welcome to AI Website Chatbot!', 'ai-website-chatbot'); ?></strong>
                        <?php _e('Complete the setup to get started.', 'ai-website-chatbot'); ?>
                        <a href="<?php echo admin_url('admin.php?page=ai-chatbot-settings'); ?>" class="button button-primary">
                            <?php _e('Start Setup', 'ai-website-chatbot'); ?>
                        </a>
                    </p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        $api_key = sanitize_text_field($_POST['api_key']);
        
        // Load the appropriate AI provider class
        $provider_class = 'AI_Chatbot_' . ucfirst($provider);
        $provider_file = AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/class-ai-chatbot-' . $provider . '.php';
        
        if (!file_exists($provider_file)) {
            wp_send_json_error(__('Provider not found', 'ai-website-chatbot'));
        }
        
        require_once $provider_file;
        
        if (!class_exists($provider_class)) {
            wp_send_json_error(__('Provider class not found', 'ai-website-chatbot'));
        }
        
        $provider_instance = new $provider_class();
        $result = $provider_instance->test_connection($api_key);
        
        if ($result) {
            wp_send_json_success(__('Connection successful!', 'ai-website-chatbot'));
        } else {
            wp_send_json_error(__('Connection failed. Please check your API key.', 'ai-website-chatbot'));
        }
    }
    
    /**
     * AJAX: Refresh system status
     */
    public function ajax_refresh_system_status() {
        check_ajax_referer('ai_chatbot_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        // Force refresh system status
        delete_transient('ai_chatbot_system_status');
        
        wp_send_json_success(__('System status refreshed', 'ai-website-chatbot'));
    }
    
    /**
     * Bulk delete conversations
     */
    private function bulk_delete_conversations() {
        if (!isset($_POST['conversations']) || !is_array($_POST['conversations'])) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $conversation_ids = array_map('absint', $_POST['conversations']);
        
        if (!empty($conversation_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($conversation_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE id IN ($ids_placeholder)",
                $conversation_ids
            ));
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Conversations deleted successfully.', 'ai-website-chatbot') . '</p></div>';
            });
        }
    }
    
    /**
     * Bulk export conversations
     */
    private function bulk_export_conversations() {
        if (!isset($_POST['conversations']) || !is_array($_POST['conversations'])) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $conversation_ids = array_map('absint', $_POST['conversations']);
        
        if (!empty($conversation_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($conversation_ids), '%d'));
            $conversations = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id IN ($ids_placeholder)",
                $conversation_ids
            ), ARRAY_A);
            
            // Set headers for download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="chatbot-conversations-' . date('Y-m-d-H-i-s') . '.json"');
            
            echo json_encode($conversations, JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    /**
     * Clear training data
     */
    private function clear_training_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Training data cleared successfully.', 'ai-website-chatbot') . '</p></div>';
        });
    }
}