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
				$this->version,
				'all'
			);
		}

		// Enqueue themes CSS if not already loaded
		if (!wp_style_is('ai-chatbot-themes-css', 'enqueued')) {
			wp_enqueue_style(
				'ai-chatbot-themes-css',
				AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-themes.css',
				array('ai-chatbot-frontend-css'),
				$this->version,
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

        // Enqueue widget JavaScript
        wp_enqueue_script(
            'ai-chatbot-widget-js',
            AI_CHATBOT_PLUGIN_URL . 'assets/public/js/chatbot-widget.js',
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
            )
        ));
    }

    /**
     * Check if chatbot is enabled
     */
    private function is_chatbot_enabled() {
        $settings = get_option('ai_chatbot_settings', array());
        return isset($settings['enabled']) && $settings['enabled'] === 'yes';
    }

    /**
     * Render chatbot widget in footer
     */
    public function render_chatbot_widget() {
        if (!$this->is_chatbot_enabled()) {
            return;
        }

        // Get widget template
        include AI_CHATBOT_PLUGIN_PATH . 'public/partials/chatbot-widget.php';
    }
}
