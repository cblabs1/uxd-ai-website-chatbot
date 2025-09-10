<?php
/**
 * AI Chatbot Frontend Class
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
        if (!$this->is_chatbot_enabled()) {
            return;
        }

        // Enqueue frontend CSS
        wp_enqueue_style(
            'ai-chatbot-frontend-css',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-frontend.css',
            array(),
            AI_CHATBOT_VERSION
        );

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

        // Enqueue frontend JavaScript
        wp_enqueue_script(
            'ai-chatbot-frontend-js',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-frontend.js',
            array('jquery'),
            AI_CHATBOT_VERSION,
            true
        );

        //Enqueue widget JavaScript
        wp_enqueue_script(
            'ai-chatbot-widget-js',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-widget.js',
            array('jquery', 'ai-chatbot-frontend-js'),
            AI_CHATBOT_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('ai-chatbot-frontend-js', 'ai_chatbot_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'plugin_url' => AI_CHATBOT_PLUGIN_URL,
            'strings' => array(
                'loading' => __('Loading...', 'ai-website-chatbot'),
                'error' => __('Something went wrong. Please try again.', 'ai-website-chatbot'),
                'type_message' => __('Type your message...', 'ai-website-chatbot'),
                'send' => __('Send', 'ai-website-chatbot'),
                'minimize' => __('Minimize', 'ai-website-chatbot'),
                'maximize' => __('Maximize', 'ai-website-chatbot'),
                'close' => __('Close', 'ai-website-chatbot'),
                'pre_chat_title' => __('Welcome to AI Chat', 'ai-website-chatbot'),
                'pre_chat_subtitle' => __('Let\'s get started with your conversation', 'ai-website-chatbot'),
                'pre_chat_description' => __('To provide you with the best assistance, we\'d like to know a bit about you. Your information helps us give more personalized responses.', 'ai-website-chatbot'),
                'email_label' => __('Email Address', 'ai-website-chatbot'),
                'email_placeholder' => __('your.email@example.com', 'ai-website-chatbot'),
                'email_error' => __('Please enter a valid email address', 'ai-website-chatbot'),
                'name_label' => __('Your Name (Optional)', 'ai-website-chatbot'),
                'name_placeholder' => __('John Doe', 'ai-website-chatbot'),
                'name_error' => __('Name should be between 2-50 characters', 'ai-website-chatbot'),
                'privacy_text' => __('Your information is secure and will only be used to improve your chat experience. We respect your privacy and follow GDPR guidelines.', 'ai-website-chatbot'),
                'learn_more' => __('Learn more', 'ai-website-chatbot'),
                'start_chat' => __('Start Chatting', 'ai-website-chatbot'),
            ),
            'settings' => array(
                'user_collection_enabled' => get_option('ai_chatbot_user_collection_enabled', true),
                'require_email' => get_option('ai_chatbot_require_email', true),
                'require_name' => get_option('ai_chatbot_require_name', false),
                'max_message_length' => get_option('ai_chatbot_max_message_length', 1000),
            )
        ));
    }

    /**
     * Check if chatbot is enabled
     */
    private function is_chatbot_enabled() {
        $settings = get_option('ai_chatbot_settings', array());
    	return !empty($settings['enabled']) && ($settings['enabled'] === true || $settings['enabled'] === 1 || $settings['enabled'] === '1');
    }

    /**
     * Render chatbot widget in footer
     */
    public function render_chatbot_widget() {
        if (!$this->is_chatbot_enabled()) {
            return;
        }

        // Get widget template
        include AI_CHATBOT_PLUGIN_DIR . 'public/partials/chatbot-widget.php';
    }
}
