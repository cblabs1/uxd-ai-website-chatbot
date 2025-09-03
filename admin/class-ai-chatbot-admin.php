<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The admin-specific functionality of the plugin.
 */
class AI_Chatbot_Admin {

    /**
     * The ID of this plugin.
     *
     * @var string
     * @since 1.0.0
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @var string
     * @since 1.0.0
     */
    private $version;

    /**
     * Admin settings instance.
     *
     * @var AI_Chatbot_Admin_Settings
     * @since 1.0.0
     */
    private $settings;

    /**
     * Admin dashboard instance.
     *
     * @var AI_Chatbot_Admin_Dashboard
     * @since 1.0.0
     */
    private $dashboard;

    /**
     * Admin analytics instance.
     *
     * @var AI_Chatbot_Admin_Analytics
     * @since 1.0.0
     */
    private $analytics;

    /**
     * Admin training instance.
     *
     * @var AI_Chatbot_Admin_Training
     * @since 1.0.0
     */
    private $training;

    /**
     * Admin conversations instance.
     *
     * @var AI_Chatbot_Admin_Conversations
     * @since 1.0.0
     */
    private $conversations;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     * @since 1.0.0
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    /**
     * Load the required dependencies for the Admin area.
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        require_once plugin_dir_path(__FILE__) . 'class-ai-chatbot-admin-settings.php';
        require_once plugin_dir_path(__FILE__) . 'class-ai-chatbot-admin-dashboard.php';
        require_once plugin_dir_path(__FILE__) . 'class-ai-chatbot-admin-analytics.php';
        require_once plugin_dir_path(__FILE__) . 'class-ai-chatbot-admin-training.php';
        require_once plugin_dir_path(__FILE__) . 'class-ai-chatbot-admin-conversations.php';

        $this->settings = new AI_Chatbot_Admin_Settings($this->plugin_name, $this->version);
        $this->dashboard = new AI_Chatbot_Admin_Dashboard($this->plugin_name, $this->version);
        $this->analytics = new AI_Chatbot_Admin_Analytics($this->plugin_name, $this->version);
        $this->training = new AI_Chatbot_Admin_Training($this->plugin_name, $this->version);
        $this->conversations = new AI_Chatbot_Admin_Conversations($this->plugin_name, $this->version);
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since 1.0.0
     */
    private function define_admin_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'admin_init_hook'));
        add_filter('plugin_action_links_' . AI_CHATBOT_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @param string $hook_suffix The current admin page.
     * @since 1.0.0
     */
    public function enqueue_styles($hook_suffix) {
        if (!$this->is_plugin_page($hook_suffix)) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @param string $hook_suffix The current admin page.
     * @since 1.0.0
     */
    public function enqueue_scripts($hook_suffix) {
        if (!$this->is_plugin_page($hook_suffix)) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name . '-admin-main',
            plugin_dir_url(__FILE__) . 'js/admin-main.js',
            array('jquery'),
            $this->version,
            false
        );

        // Enqueue page-specific scripts
        if (strpos($hook_suffix, 'ai-chatbot-settings') !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-settings',
                plugin_dir_url(__FILE__) . 'js/settings.js',
                array('jquery'),
                $this->version,
                false
            );
        }

        if (strpos($hook_suffix, 'ai-chatbot-analytics') !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-analytics',
                plugin_dir_url(__FILE__) . 'js/analytics.js',
                array('jquery'),
                $this->version,
                false
            );
        }

        if (strpos($hook_suffix, 'ai-chatbot-training') !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-training',
                plugin_dir_url(__FILE__) . 'js/training.js',
                array('jquery'),
                $this->version,
                false
            );
        }

        // Localize script with data
        wp_localize_script(
            $this->plugin_name . '-admin-main',
            'ai_chatbot_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_chatbot_admin_nonce'),
                'plugin_url' => plugin_dir_url(dirname(__FILE__)),
            )
        );
    }

    /**
     * Add the plugin admin menu.
     *
     * @since 1.0.0
     */
    public function add_plugin_admin_menu() {
        $capability = 'manage_options';

        // Main menu page
        add_menu_page(
            __('AI Chatbot', 'ai-website-chatbot'),
            __('AI Chatbot', 'ai-website-chatbot'),
            $capability,
            $this->plugin_name,
            array($this, 'display_plugin_admin_page'),
            'dashicons-format-chat',
            30
        );

        // Dashboard submenu (same as main menu)
        add_submenu_page(
            $this->plugin_name,
            __('Dashboard', 'ai-website-chatbot'),
            __('Dashboard', 'ai-website-chatbot'),
            $capability,
            $this->plugin_name,
            array($this, 'display_plugin_admin_page')
        );

        // Settings submenu
        add_submenu_page(
            $this->plugin_name,
            __('Settings', 'ai-website-chatbot'),
            __('Settings', 'ai-website-chatbot'),
            $capability,
            $this->plugin_name . '-settings',
            array($this->settings, 'display_settings_page')
        );

        // Analytics submenu
        add_submenu_page(
            $this->plugin_name,
            __('Analytics', 'ai-website-chatbot'),
            __('Analytics', 'ai-website-chatbot'),
            $capability,
            $this->plugin_name . '-analytics',
            array($this->analytics, 'display_analytics_page')
        );

        // Training submenu
        add_submenu_page(
            $this->plugin_name,
            __('Training', 'ai-website-chatbot'),
            __('Training', 'ai-website-chatbot'),
            $capability,
            $this->plugin_name . '-training',
            array($this->training, 'display_training_page')
        );

        // Conversations submenu
        add_submenu_page(
            $this->plugin_name,
            __('Conversations', 'ai-website-chatbot'),
            __('Conversations', 'ai-website-chatbot'),
            $capability,
            $this->plugin_name . '-conversations',
            array($this->conversations, 'display_conversations_page')
        );
    }

    /**
     * Display the main admin page.
     *
     * @since 1.0.0
     */
    public function display_plugin_admin_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/admin-display.php';
    }

    /**
     * Initialize admin functionality.
     *
     * @since 1.0.0
     */
    public function admin_init_hook() {
        // Register settings
        $this->settings->register_settings();
        
        // Add dashboard widgets
        $this->dashboard->add_dashboard_widgets();
    }

    /**
     * Add action links to plugin page.
     *
     * @param array $links Existing links.
     * @return array Modified links.
     * @since 1.0.0
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-settings') . '">' . __('Settings', 'ai-website-chatbot') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Check if current page is a plugin page.
     *
     * @param string $hook_suffix Current page hook.
     * @return bool True if plugin page.
     * @since 1.0.0
     */
    private function is_plugin_page($hook_suffix) {
        $plugin_pages = array(
            'toplevel_page_' . $this->plugin_name,
            $this->plugin_name . '_page_' . $this->plugin_name . '-settings',
            $this->plugin_name . '_page_' . $this->plugin_name . '-analytics',
            $this->plugin_name . '_page_' . $this->plugin_name . '-training',
            $this->plugin_name . '_page_' . $this->plugin_name . '-conversations',
        );

        return in_array($hook_suffix, $plugin_pages, true);
    }

    /**
     * Get admin instance for specific functionality.
     *
     * @param string $type Admin type.
     * @return object|null Admin instance.
     * @since 1.0.0
     */
    public function get_admin_instance($type) {
        switch ($type) {
            case 'settings':
                return $this->settings;
            case 'dashboard':
                return $this->dashboard;
            case 'analytics':
                return $this->analytics;
            case 'training':
                return $this->training;
            case 'conversations':
                return $this->conversations;
            default:
                return null;
        }
    }
}
