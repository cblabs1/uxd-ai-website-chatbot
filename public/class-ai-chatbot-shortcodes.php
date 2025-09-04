<?php
/**
 * The shortcodes functionality of the plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The shortcodes functionality of the plugin.
 */
class AI_Chatbot_Shortcodes {

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
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     * @since 1.0.0
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register all shortcodes
     *
     * @since 1.0.0
     */
    public function register_shortcodes() {
        add_shortcode('ai_chatbot', array($this, 'chatbot_shortcode'));
        add_shortcode('ai_chatbot_inline', array($this, 'inline_chatbot_shortcode'));
        add_shortcode('ai_chatbot_button', array($this, 'chatbot_button_shortcode'));
        add_shortcode('ai_chatbot_popup', array($this, 'popup_chatbot_shortcode'));
    }

    /**
     * Main chatbot shortcode handler
     *
     * @param array $atts Shortcode attributes.
     * @param string $content Shortcode content.
     * @return string HTML output.
     * @since 1.0.0
     */
    public function chatbot_shortcode($atts, $content = null) {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'type' => 'inline',
            'width' => '100%',
            'height' => '400px',
            'title' => get_option('ai_chatbot_widget_title', __('AI Assistant', 'ai-website-chatbot')),
            'theme' => get_option('ai_chatbot_theme', 'default'),
            'welcome_message' => get_option('ai_chatbot_welcome_message', ''),
            'position' => 'static',
            'show_header' => 'true',
            'show_powered_by' => get_option('ai_chatbot_show_powered_by', 'true'),
            'class' => '',
            'id' => '',
        ), $atts, 'ai_chatbot');

        // Sanitize attributes
        $atts = array_map('sanitize_text_field', $atts);

        $settings = get_option('ai_chatbot_settings', array());
        $is_enabled = !empty($settings['enabled']) && ($settings['enabled'] === true || $settings['enabled'] === 1 || $settings['enabled'] === '1');

        
        // Check if chatbot is enabled
        if (!get_option('ai_chatbot_enabled', false)) {
            return $this->get_disabled_message();
        }

        // Check user permissions
        if (!$this->check_user_permissions()) {
            return '';
        }

        // Enqueue necessary scripts and styles
        $this->enqueue_shortcode_assets();

        // Generate unique ID if not provided
        if (empty($atts['id'])) {
            $atts['id'] = 'ai-chatbot-' . uniqid();
        }

        // Route to specific shortcode type
        switch ($atts['type']) {
            case 'popup':
                return $this->render_popup_chatbot($atts);
            case 'button':
                return $this->render_chatbot_button($atts);
            case 'inline':
            default:
                return $this->render_inline_chatbot($atts);
        }
    }

    /**
     * Inline chatbot shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     * @since 1.0.0
     */
    public function inline_chatbot_shortcode($atts) {
        $atts['type'] = 'inline';
        return $this->chatbot_shortcode($atts);
    }

    /**
     * Chatbot button shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     * @since 1.0.0
     */
    public function chatbot_button_shortcode($atts) {
        $atts['type'] = 'button';
        return $this->chatbot_shortcode($atts);
    }

    /**
     * Popup chatbot shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     * @since 1.0.0
     */
    public function popup_chatbot_shortcode($atts) {
        $atts['type'] = 'popup';
        return $this->chatbot_shortcode($atts);
    }

    /**
     * Render inline chatbot
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     * @since 1.0.0
     */
    private function render_inline_chatbot($atts) {
        ob_start();
        
        $container_class = 'ai-chatbot-shortcode ai-chatbot-inline';
        if (!empty($atts['class'])) {
            $container_class .= ' ' . esc_attr($atts['class']);
        }

        ?>
        <div id="<?php echo esc_attr($atts['id']); ?>" 
             class="<?php echo esc_attr($container_class); ?>"
             data-chatbot-type="inline"
             data-chatbot-config="<?php echo esc_attr(wp_json_encode($this->get_shortcode_config($atts))); ?>"
             style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
            
            <?php if (filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN)): ?>
            <div class="ai-chatbot-header">
                <h4 class="ai-chatbot-title"><?php echo esc_html($atts['title']); ?></h4>
                <div class="ai-chatbot-controls">
                    <button type="button" class="ai-chatbot-minimize" aria-label="<?php esc_attr_e('Minimize', 'ai-website-chatbot'); ?>">
                        <span class="dashicons dashicons-minus"></span>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="ai-chatbot-messages">
                <div class="ai-chatbot-welcome">
                    <?php if (!empty($atts['welcome_message'])): ?>
                        <div class="ai-chatbot-message bot-message">
                            <div class="message-content"><?php echo wp_kses_post($atts['welcome_message']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ai-chatbot-input-area">
                <div class="ai-chatbot-typing-indicator" style="display: none;">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="typing-text"><?php esc_html_e('AI is typing...', 'ai-website-chatbot'); ?></span>
                </div>
                
                <form class="ai-chatbot-form">
                    <div class="input-group">
                        <textarea class="ai-chatbot-input" 
                                  placeholder="<?php esc_attr_e('Type your message...', 'ai-website-chatbot'); ?>"
                                  rows="1"
                                  maxlength="<?php echo esc_attr(get_option('ai_chatbot_max_message_length', 1000)); ?>"></textarea>
                        <button type="submit" class="ai-chatbot-send-btn" disabled>
                            <span class="send-text"><?php esc_html_e('Send', 'ai-website-chatbot'); ?></span>
                            <span class="loading-spinner" style="display: none;"></span>
                        </button>
                    </div>
                </form>
            </div>

            <?php if (filter_var($atts['show_powered_by'], FILTER_VALIDATE_BOOLEAN)): ?>
            <div class="ai-chatbot-powered-by">
                <small><?php esc_html_e('Powered by AI Website Chatbot', 'ai-website-chatbot'); ?></small>
            </div>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render chatbot button
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     * @since 1.0.0
     */
    private function render_chatbot_button($atts) {
        ob_start();

        $button_class = 'ai-chatbot-trigger-button';
        if (!empty($atts['class'])) {
            $button_class .= ' ' . esc_attr($atts['class']);
        }

        ?>
        <button id="<?php echo esc_attr($atts['id']); ?>"
                class="<?php echo esc_attr($button_class); ?>"
                data-chatbot-type="button"
                data-chatbot-config="<?php echo esc_attr(wp_json_encode($this->get_shortcode_config($atts))); ?>">
            <span class="button-icon">💬</span>
            <span class="button-text"><?php echo esc_html($atts['title']); ?></span>
        </button>
        <?php

        return ob_get_clean();
    }

    /**
     * Render popup chatbot
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     * @since 1.0.0
     */
    private function render_popup_chatbot($atts) {
        ob_start();

        ?>
        <div id="<?php echo esc_attr($atts['id']); ?>-trigger" 
             class="ai-chatbot-popup-trigger <?php echo esc_attr($atts['class']); ?>"
             data-chatbot-type="popup"
             data-chatbot-target="<?php echo esc_attr($atts['id']); ?>"
             data-chatbot-config="<?php echo esc_attr(wp_json_encode($this->get_shortcode_config($atts))); ?>">
            
            <button type="button" class="ai-chatbot-popup-button">
                <span class="button-icon">💬</span>
                <span class="button-text"><?php echo esc_html($atts['title']); ?></span>
            </button>
        </div>

        <div id="<?php echo esc_attr($atts['id']); ?>" 
             class="ai-chatbot-popup-modal" 
             style="display: none;"
             role="dialog"
             aria-labelledby="<?php echo esc_attr($atts['id']); ?>-title"
             aria-hidden="true">
            
            <div class="ai-chatbot-popup-backdrop"></div>
            
            <div class="ai-chatbot-popup-container">
                <div class="ai-chatbot-popup-header">
                    <h3 id="<?php echo esc_attr($atts['id']); ?>-title"><?php echo esc_html($atts['title']); ?></h3>
                    <button type="button" class="ai-chatbot-close-popup" aria-label="<?php esc_attr_e('Close', 'ai-website-chatbot'); ?>">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                
                <div class="ai-chatbot-popup-body">
                    <!-- Inline chatbot will be loaded here -->
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get shortcode configuration
     *
     * @param array $atts Shortcode attributes.
     * @return array Configuration array.
     * @since 1.0.0
     */
    private function get_shortcode_config($atts) {
        return array(
            'type' => $atts['type'],
            'sessionId' => 'shortcode_' . uniqid(),
            'welcomeMessage' => $atts['welcome_message'],
            'theme' => $atts['theme'],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'settings' => array(
                'maxMessageLength' => get_option('ai_chatbot_max_message_length', 1000),
                'enableRating' => get_option('ai_chatbot_enable_rating', true),
                'enableHistory' => get_option('ai_chatbot_enable_history', true),
            ),
            'strings' => array(
                'send' => __('Send', 'ai-website-chatbot'),
                'thinking' => __('Thinking...', 'ai-website-chatbot'),
                'error' => __('Sorry, something went wrong. Please try again.', 'ai-website-chatbot'),
                'networkError' => __('Network error. Please check your connection.', 'ai-website-chatbot'),
                'typing' => __('AI is typing...', 'ai-website-chatbot'),
            ),
        );
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
     * Get disabled chatbot message
     *
     * @return string HTML message.
     * @since 1.0.0
     */
    private function get_disabled_message() {
        if (current_user_can('manage_options')) {
            return '<div class="ai-chatbot-disabled">' . 
                   __('AI Chatbot is currently disabled. Enable it in the admin settings.', 'ai-website-chatbot') . 
                   '</div>';
        }
        return '';
    }

    /**
     * Enqueue shortcode-specific assets
     *
     * @since 1.0.0
     */
    private function enqueue_shortcode_assets() {
        if (!wp_script_is($this->plugin_name . '-frontend', 'enqueued')) {
            wp_enqueue_script(
                $this->plugin_name . '-frontend',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-frontend.js',
                array('jquery'),
                $this->version,
                true
            );
        }

        if (!wp_style_is($this->plugin_name, 'enqueued')) {
            wp_enqueue_style(
                $this->plugin_name,
                AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-frontend.css',
                array(),
                $this->version,
                'all'
            );
        }

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
    }
}
