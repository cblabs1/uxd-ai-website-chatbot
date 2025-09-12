<?php
/**
 * AI Chatbot Frontend Class - Updated for Pro Features Split
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class AI_Chatbot_Frontend {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_footer', array($this, 'render_chatbot_widget'));
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize frontend
     */
    public function init() {
        // Initialize frontend functionality
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        // Only enqueue if chatbot is enabled
        $settings = get_option('ai_chatbot_settings', array());
        $shortcodes_when_disabled = !empty($settings['enable_shortcodes_when_disabled']);
        
        // Only enqueue if chatbot enabled OR shortcodes allowed when disabled
        if (!$this->is_chatbot_enabled() && !$shortcodes_when_disabled) {
            return;
        }

        // Enqueue main CSS if not already loaded
        if (!wp_style_is('ai-chatbot-frontend-css', 'enqueued')) {
            wp_enqueue_style(
                'ai-chatbot-frontend-css',
                AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-frontend.css',
                array(),
                AI_CHATBOT_VERSION,
                'all'
            );
        }

        // Enqueue themes CSS if not already loaded
        if (!wp_style_is('ai-chatbot-themes-css', 'enqueued')) {
            wp_enqueue_style(
                'ai-chatbot-themes-css',
                AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-themes.css',
                array('ai-chatbot-frontend-css'),
                AI_CHATBOT_VERSION,
                'all'
            );
        }

        // Enqueue CORE frontend JavaScript (always load)
        wp_enqueue_script(
            'ai-chatbot-frontend-js',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-frontend.js',
            array('jquery'),
            AI_CHATBOT_VERSION,
            true
        );

        // Enqueue widget JavaScript
        wp_enqueue_script(
            'ai-chatbot-widget-js',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-widget.js',
            array('jquery', 'ai-chatbot-frontend-js'),
            AI_CHATBOT_VERSION,
            true
        );

        // Check if Pro features should be loaded
        $pro_enabled = $this->should_load_pro_features();
        
        // Enqueue Pro features JavaScript (conditional)
        if ($pro_enabled) {
            wp_enqueue_script(
                'ai-chatbot-pro-features-js',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-pro-features.js',
                array('jquery', 'ai-chatbot-frontend-js'),
                AI_CHATBOT_VERSION,
                true
            );
        }

        // Build configuration for frontend
        $frontend_config = $this->build_frontend_config($pro_enabled);
        
        // Localize script for AJAX
        wp_localize_script('ai-chatbot-frontend-js', 'ai_chatbot_ajax', $frontend_config);
        
        // If Pro features are enabled, also localize the Pro script
        if ($pro_enabled) {
            wp_localize_script('ai-chatbot-pro-features-js', 'ai_chatbot_pro_ajax', $this->build_pro_config());
        }
    }

    /**
     * Build frontend configuration
     */
    private function build_frontend_config($pro_enabled = false) {
        $config = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'plugin_url' => AI_CHATBOT_PLUGIN_URL,
            'session_id' => $this->generate_session_id(),
            'pro_enabled' => $pro_enabled,
            'settings' => array(
                'maxMessageLength' => get_option('ai_chatbot_max_message_length', 1000),
                'enableRating' => get_option('ai_chatbot_enable_rating', true),
                'enableHistory' => get_option('ai_chatbot_enable_history', true),
                'requireAuth' => get_option('ai_chatbot_require_auth', false),
            ),
            'strings' => array(
                'loading' => __('Loading...', 'ai-website-chatbot'),
                'error' => __('Something went wrong. Please try again.', 'ai-website-chatbot'),
                'typing' => __('AI is typing...', 'ai-website-chatbot'),
                'confirmClear' => __('Are you sure you want to clear this conversation?', 'ai-website-chatbot'),
                'rateExperience' => __('How was your experience?', 'ai-website-chatbot'),
                'thankYou' => __('Thank you for your feedback!', 'ai-website-chatbot'),
                'fileTooLarge' => __('File is too large. Maximum size is 5MB.', 'ai-website-chatbot'),
                'unsupportedFileType' => __('Unsupported file type.', 'ai-website-chatbot'),
                'voiceNotSupported' => __('Voice input is not supported in your browser.', 'ai-website-chatbot'),
                'conversationSaved' => __('Conversation saved successfully.', 'ai-website-chatbot'),
                'conversationSaveError' => __('Failed to save conversation.', 'ai-website-chatbot'),
            )
        );

        // Add Pro configuration if enabled
        if ($pro_enabled) {
            $config['pro_config'] = array(
                'semantic_search' => get_option('ai_chatbot_semantic_search_enabled', true),
                'voice_input' => get_option('ai_chatbot_voice_input_enabled', false),
                'file_upload' => get_option('ai_chatbot_file_upload_enabled', false),
                'suggestions' => get_option('ai_chatbot_suggestions_enabled', true),
                'follow_up' => get_option('ai_chatbot_follow_up_enabled', true),
                'analytics' => get_option('ai_chatbot_analytics_enabled', true),
                'export_data' => get_option('ai_chatbot_export_data_enabled', true),
                'intent_recognition' => get_option('ai_chatbot_intent_recognition_enabled', true)
            );
        }

        return $config;
    }

    /**
     * Build Pro-specific configuration
     */
    private function build_pro_config() {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'semantic_endpoint' => 'ai_chatbot_message_pro',
            'analytics_endpoint' => 'ai_chatbot_track_event',
            'upload_endpoint' => 'ai_chatbot_upload_file',
            'export_endpoint' => 'ai_chatbot_export_data',
            'features' => array(
                'semantic_search' => $this->is_feature_enabled('semantic_search'),
                'intelligence_engine' => $this->is_feature_enabled('intelligence_engine'),
                'voice_input' => $this->is_feature_enabled('voice_input'),
                'file_upload' => $this->is_feature_enabled('file_upload'),
                'advanced_analytics' => $this->is_feature_enabled('advanced_analytics')
            ),
            'settings' => array(
                'max_file_size' => 5 * 1024 * 1024, // 5MB
                'allowed_file_types' => array('image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'),
                'voice_language' => get_option('ai_chatbot_voice_language', 'en-US'),
                'semantic_threshold' => get_option('ai_chatbot_semantic_threshold', 0.7)
            )
        );
    }

    /**
     * Check if Pro features should be loaded
     */
    private function should_load_pro_features() {
        // Check if Pro version is active
        if (!function_exists('ai_chatbot_pro_init') && !defined('AI_CHATBOT_PRO_VERSION')) {
            return false;
        }

        // Check if Pro features are enabled in settings
        $pro_enabled = get_option('ai_chatbot_pro_features_enabled', false);
        if (!$pro_enabled) {
            return false;
        }

        // Check if Pro license is valid (if using license system)
        $license_valid = get_option('ai_chatbot_pro_license_valid', true);
        if (!$license_valid) {
            return false;
        }

        // Check if at least one Pro feature is enabled
        $enabled_features = $this->get_enabled_pro_features();
        return !empty($enabled_features);
    }

    /**
     * Get list of enabled Pro features
     */
    private function get_enabled_pro_features() {
        $features = array();
        
        if (get_option('ai_chatbot_semantic_search_enabled', false)) {
            $features[] = 'semantic_search';
        }
        
        if (get_option('ai_chatbot_voice_input_enabled', false)) {
            $features[] = 'voice_input';
        }
        
        if (get_option('ai_chatbot_file_upload_enabled', false)) {
            $features[] = 'file_upload';
        }
        
        if (get_option('ai_chatbot_suggestions_enabled', true)) {
            $features[] = 'suggestions';
        }
        
        if (get_option('ai_chatbot_follow_up_enabled', true)) {
            $features[] = 'follow_up';
        }
        
        if (get_option('ai_chatbot_analytics_enabled', true)) {
            $features[] = 'analytics';
        }
        
        if (get_option('ai_chatbot_export_data_enabled', true)) {
            $features[] = 'export_data';
        }
        
        if (get_option('ai_chatbot_intent_recognition_enabled', false)) {
            $features[] = 'intent_recognition';
        }
        
        return $features;
    }

    /**
     * Check if specific feature is enabled
     */
    private function is_feature_enabled($feature) {
        $feature_map = array(
            'semantic_search' => 'ai_chatbot_semantic_search_enabled',
            'intelligence_engine' => 'ai_chatbot_intelligence_engine_enabled',
            'voice_input' => 'ai_chatbot_voice_input_enabled',
            'file_upload' => 'ai_chatbot_file_upload_enabled',
            'advanced_analytics' => 'ai_chatbot_advanced_analytics_enabled',
            'suggestions' => 'ai_chatbot_suggestions_enabled',
            'follow_up' => 'ai_chatbot_follow_up_enabled',
            'export_data' => 'ai_chatbot_export_data_enabled',
            'intent_recognition' => 'ai_chatbot_intent_recognition_enabled'
        );
        
        $option_name = $feature_map[$feature] ?? null;
        if (!$option_name) {
            return false;
        }
        
        return get_option($option_name, false);
    }

    /**
     * Generate session ID
     */
    private function generate_session_id() {
        // Check for existing session
        if (session_id()) {
            return 'wp_session_' . session_id();
        }
        
        // Generate new session ID
        return 'wp_session_' . wp_generate_uuid4();
    }

    /**
     * Check if chatbot is enabled
     */
    private function is_chatbot_enabled() {
        $settings = get_option('ai_chatbot_settings', array());
    	return !empty($settings['enabled']) && ($settings['enabled'] === true || $settings['enabled'] === 1 || $settings['enabled'] === '1');
    }

    /**
     * Check user permissions for chatbot access
     *
     * @return bool True if user has permission.
     * @since 1.0.0
     */
    private function check_user_permissions() {
        $show_to_logged_users = get_option('ai_chatbot_show_to_logged_users', true);
        $show_to_guests = get_option('ai_chatbot_show_to_guests', true);

        if (is_user_logged_in()) {
            return $show_to_logged_users;
        } else {
            return $show_to_guests;
        }
    }

    /**
     * Render chatbot widget in footer
     */
    public function render_chatbot_widget() {
        // Only render if chatbot is enabled and user has permission
        if (!$this->is_chatbot_enabled() || !$this->check_user_permissions()) {
            if (current_user_can('manage_options')) {
                echo $this->get_disabled_message();
            }
            return;
        }

        // Include widget template
        $template_path = AI_CHATBOT_PLUGIN_DIR . 'public/partials/chatbot-widget.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    /**
     * Get disabled chatbot message for admins
     *
     * @return string HTML message.
     * @since 1.0.0
     */
    private function get_disabled_message() {
        if (current_user_can('manage_options')) {
            return '<div class="ai-chatbot-disabled notice notice-warning inline">' . 
                   '<p>' . esc_html__('AI Chatbot is currently disabled. Enable it in the admin settings.', 'ai-website-chatbot') . '</p>' .
                   '</div>';
        }
        return '';
    }

    /**
     * Enqueue shortcode-specific assets (for backward compatibility)
     *
     * @since 1.0.0
     */
    public function enqueue_shortcode_assets() {
        // This method is kept for backward compatibility with shortcodes
        if (!wp_script_is('ai-chatbot-frontend-js', 'enqueued')) {
            $this->enqueue_frontend_scripts();
        }
    }

    /**
     * Add Pro feature detection to body class
     */
    public function add_body_classes($classes) {
        if ($this->should_load_pro_features()) {
            $classes[] = 'ai-chatbot-pro-enabled';
            
            $enabled_features = $this->get_enabled_pro_features();
            foreach ($enabled_features as $feature) {
                $classes[] = 'ai-chatbot-' . str_replace('_', '-', $feature) . '-enabled';
            }
        } else {
            $classes[] = 'ai-chatbot-core-only';
        }
        
        return $classes;
    }

    /**
     * Initialize body class filter
     */
    public function init_body_classes() {
        add_filter('body_class', array($this, 'add_body_classes'));
    }

    /**
     * Get chatbot configuration for JavaScript
     * This method can be called externally by shortcodes/widgets
     */
    public function get_chatbot_config() {
        $pro_enabled = $this->should_load_pro_features();
        return $this->build_frontend_config($pro_enabled);
    }

    /**
     * Check if Pro features file exists
     */
    private function pro_features_file_exists() {
        return file_exists(AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-pro-features.js');
    }

    /**
     * Add inline styles for Pro features
     */
    private function add_pro_styles() {
        if (!$this->should_load_pro_features()) {
            return;
        }

        $custom_css = "
        .ai-chatbot-suggestions, .ai-chatbot-followup {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .suggestion-btn, .followup-btn {
            display: inline-block;
            margin: 2px;
            padding: 6px 12px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .suggestion-btn:hover, .followup-btn:hover {
            background: #005a87;
        }
        
        .ai-chatbot-confidence {
            font-size: 10px;
            opacity: 0.7;
            margin-top: 2px;
        }
        
        .ai-chatbot-confidence.high { color: #46b450; }
        .ai-chatbot-confidence.medium { color: #ffb900; }
        .ai-chatbot-confidence.low { color: #dc3232; }
        
        .voice-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            text-align: center;
        }
        
        .voice-animation {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            position: relative;
        }
        
        .voice-circle {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: #0073aa;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .pro-typing .typing-text {
            color: #0073aa;
            font-weight: 500;
        }
        ";

        wp_add_inline_style('ai-chatbot-frontend-css', $custom_css);
    }

    /**
     * Initialize Pro-specific functionality
     */
    public function init_pro_features() {
        if (!$this->should_load_pro_features()) {
            return;
        }

        // Add Pro styles
        add_action('wp_enqueue_scripts', array($this, 'add_pro_styles'), 20);
        
        // Initialize body classes
        $this->init_body_classes();
        
        // Add Pro-specific hooks
        do_action('ai_chatbot_pro_frontend_init');
    }
}