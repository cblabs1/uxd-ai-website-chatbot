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
        $this->register_shortcodes();
    }

    /**
     * Register all shortcodes
     *
     * @since 1.0.0
     */
    public function register_shortcodes() {
        error_log('register_shortcodes called');
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
        $saved_settings = get_option('ai_chatbot_settings', array());

        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'type' => 'inline',
            'width' => '100%',
            'height' => '500px',
            'title' => isset($saved_settings['title']) ? $saved_settings['title'] : __('AI Assistant', 'ai-website-chatbot'),
            'theme' => get_option('ai_chatbot_theme', 'default'),
            'welcome_message' => get_option('ai_chatbot_welcome_message', __('Hello! How can I help you today?', 'ai-website-chatbot')),
            'position' => 'static',
            'show_header' => 'true',
            'show_powered_by' => get_option('ai_chatbot_show_powered_by', 'true'),
            'show_starter_buttons' => get_option('ai_chatbot_show_starter_buttons', 'true'),
            'enable_file_upload' => get_option('ai_chatbot_enable_file_upload', 'false'),
            'enable_voice_input' => get_option('ai_chatbot_enable_voice_input', 'false'),
            'enable_conversation_save' => get_option('ai_chatbot_enable_conversation_save', 'false'),
            'class' => '',
            'id' => '',
            'force_enabled' => 'true',
        ), $atts, 'ai_chatbot');

        // Sanitize attributes
        $atts = array_map('sanitize_text_field', $atts);

        $force_enabled = filter_var($atts['force_enabled'], FILTER_VALIDATE_BOOLEAN);

        $is_enabled = !empty($saved_settings['enabled']) && ($saved_settings['enabled'] === true || $saved_settings['enabled'] === 1 || $saved_settings['enabled'] === '1');

        // Check if chatbot is enabled
        $is_enabled = !empty($saved_settings['enabled']);
        $shortcodes_when_disabled = !empty($saved_settings['enable_shortcodes_when_disabled']);

        // Check if should work
        if (!$is_enabled && !$shortcodes_when_disabled) {
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
        
        $container_class = 'ai-chatbot-shortcode ai-chatbot-inline-container';
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
                <div class="ai-chatbot-header-content">
                    <div class="ai-chatbot-avatar">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="16" cy="16" r="16" fill="#6366f1"></circle>
                            <path d="M12 14C12.5523 14 13 13.5523 13 13C13 12.4477 12.5523 12 12 12C11.4477 12 11 12.4477 11 13C11 13.5523 11.4477 14 12 14Z" fill="white"></path>
                            <path d="M20 14C20.5523 14 21 13.5523 21 13C21 12.4477 20.5523 12 20 12C19.4477 12 19 12.4477 19 13C19 13.5523 19.4477 14 20 14Z" fill="white"></path>
                            <path d="M16 20C18.2091 20 20 18.2091 20 16H12C12 18.2091 13.7909 20 16 20Z" fill="white"></path>
                        </svg>
                    </div>
                    <div class="ai-chatbot-header-info">
                        <h3 class="ai-chatbot-title"><?php echo esc_html($atts['title']); ?></h3>
                        <p class="ai-chatbot-subtitle"><?php echo wp_kses_post($atts['welcome_message']); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="ai-chatbot-messages">
                <div class="ai-chatbot-welcome">
                    <?php if (!empty($atts['welcome_message'])): ?>
                        <div class="ai-chatbot-message  ai-chatbot-message-bot">
                            <div class="ai-chatbot-message-content">
                                <div class="ai-chatbot-message-text">
                                    <?php echo wp_kses_post($atts['welcome_message']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ai-chatbot-input-area">
                <div class="ai-chatbot-typing-indicator" style="display: none;">
                    <div class="message-avatar">🤖</div>
                    <div class="typing-content">
                        <div class="typing-bubble">
                            <div class="typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                        <div class="typing-text"><?php esc_html_e('AI is thinking...', 'ai-website-chatbot'); ?></div>
                    </div>
                </div>
                
                <div class="ai-chatbot-input-area">
                    <form class="ai-chatbot-input-form" id="ai-chatbot-input-form">
                        <div class="ai-chatbot-input-container">
                            <textarea class="ai-chatbot-input ai-chatbot-input-empty" id="ai-chatbot-input" placeholder="Type your message..." rows="1" maxlength="1000" style="height: 44px;"></textarea>
                            <button type="submit" class="ai-chatbot-send-button" id="ai-chatbot-send-button" disabled="">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18 10L2 18L5 10L2 2L18 10Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"></path>
                                </svg>
                            </button>
                        </div>
                    </form>
                    <?php if (filter_var($atts['show_powered_by'], FILTER_VALIDATE_BOOLEAN)): ?>
                    <div class="ai-chatbot-footer">
                        <span class="ai-chatbot-powered-by">
                            <?php _e('Powered by', 'ai-website-chatbot'); ?> <strong><?php echo esc_html(get_bloginfo('name')); ?></strong>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
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
        $popup_id = esc_attr($atts['id']);
        $trigger_id = $popup_id . '-trigger';

        ?>
        <!-- Popup Trigger Button -->
        <div id="<?php echo $trigger_id; ?>" 
             class="ai-chatbot-popup-trigger <?php echo esc_attr($atts['class']); ?>"
             data-chatbot-type="popup"
             data-chatbot-target="<?php echo $popup_id; ?>"
             data-chatbot-config="<?php echo esc_attr(wp_json_encode($this->get_shortcode_config($atts))); ?>">
            
            <button type="button" class="ai-chatbot-popup-button">
                <span class="button-icon">💬</span>
                <span class="button-text"><?php echo esc_html($atts['title']); ?></span>
            </button>
        </div>

        <!-- Popup Modal Container -->
        <div id="<?php echo $popup_id; ?>" 
             class="ai-chatbot-popup-modal" 
             style="display: none;"
             role="dialog"
             aria-labelledby="<?php echo $popup_id; ?>-title"
             aria-hidden="true"
             data-popup-id="<?php echo $popup_id; ?>">
            
            <?php
            // Prepare config for the popup partial template
            $config = array(
                'popup_id' => $popup_id,
                'title' => $atts['title'],
                'welcome_message' => $atts['welcome_message'],
                'theme' => $atts['theme'],
                'show_powered_by' => filter_var($atts['show_powered_by'], FILTER_VALIDATE_BOOLEAN),
                'show_starter_buttons' => filter_var($atts['show_starter_buttons'], FILTER_VALIDATE_BOOLEAN),
                'starter_button_1' => $atts['starter_button_1'],
                'starter_button_2' => $atts['starter_button_2'],
                'starter_button_3' => $atts['starter_button_3'],
                'enable_file_upload' => filter_var($atts['enable_file_upload'], FILTER_VALIDATE_BOOLEAN),
                'enable_voice_input' => filter_var($atts['enable_voice_input'], FILTER_VALIDATE_BOOLEAN),
                'enable_conversation_save' => filter_var($atts['enable_conversation_save'], FILTER_VALIDATE_BOOLEAN),
            );

            // Include the popup chatbot partial template  
            $template_path = AI_CHATBOT_PLUGIN_DIR . 'public/partials/chatbot-popup.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                // Fallback if template doesn't exist
                ?>
                <div class="ai-chatbot-popup-backdrop"></div>
                <div class="ai-chatbot-popup-container">
                    <div class="ai-chatbot-popup-header">
                        <h3 id="<?php echo $popup_id; ?>-title" class="popup-title">
                            <?php echo esc_html($atts['title']); ?>
                        </h3>
                        <div class="popup-controls">
                            <button type="button" class="ai-chatbot-minimize-popup" title="<?php esc_attr_e('Minimize', 'ai-website-chatbot'); ?>">
                                <span class="dashicons dashicons-minus"></span>
                            </button>
                            <button type="button" class="ai-chatbot-close-popup" aria-label="<?php esc_attr_e('Close', 'ai-website-chatbot'); ?>">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="ai-chatbot-popup-body">
                        <p><?php esc_html_e('Chatbot popup template not found.', 'ai-website-chatbot'); ?></p>
                    </div>
                </div>
                <?php
            }
            ?>
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
            'enableFileUpload' => filter_var($atts['enable_file_upload'], FILTER_VALIDATE_BOOLEAN),
            'enableVoiceInput' => filter_var($atts['enable_voice_input'], FILTER_VALIDATE_BOOLEAN),
            'enableConversationSave' => filter_var($atts['enable_conversation_save'], FILTER_VALIDATE_BOOLEAN),
            'showStarterButtons' => filter_var($atts['show_starter_buttons'], FILTER_VALIDATE_BOOLEAN),
            'settings' => array(
                'maxMessageLength' => get_option('ai_chatbot_max_message_length', 1000),
                'enableRating' => get_option('ai_chatbot_enable_rating', true),
                'enableHistory' => get_option('ai_chatbot_enable_history', true),
                'autoScroll' => get_option('ai_chatbot_auto_scroll', true),
                'soundEnabled' => get_option('ai_chatbot_sound_enabled', false),
            ),
            'strings' => array(
                'send' => __('Send', 'ai-website-chatbot'),
                'thinking' => __('Thinking...', 'ai-website-chatbot'),
                'error' => __('Sorry, something went wrong. Please try again.', 'ai-website-chatbot'),
                'networkError' => __('Network error. Please check your connection.', 'ai-website-chatbot'),
                'typing' => __('AI is thinking...', 'ai-website-chatbot'),
                'fileUploadError' => __('File upload failed. Please try again.', 'ai-website-chatbot'),
                'fileTooLarge' => __('File is too large. Maximum size is 10MB.', 'ai-website-chatbot'),
                'unsupportedFileType' => __('Unsupported file type.', 'ai-website-chatbot'),
                'voiceNotSupported' => __('Voice input is not supported in your browser.', 'ai-website-chatbot'),
                'conversationSaved' => __('Conversation saved successfully.', 'ai-website-chatbot'),
                'conversationSaveError' => __('Failed to save conversation.', 'ai-website-chatbot'),
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
            return '<div class="ai-chatbot-disabled notice notice-warning inline">' . 
                   '<p>' . esc_html__('AI Chatbot is currently disabled. Enable it in the admin settings.', 'ai-website-chatbot') . '</p>' .
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
        // Enqueue main frontend JS
        if (!wp_script_is($this->plugin_name . '-frontend', 'enqueued')) {
            wp_enqueue_script(
                $this->plugin_name . '-frontend',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-frontend.js',
                array('jquery'),
                $this->version,
                true
            );
        }

        // Enqueue shortcode-specific JS
        if (!wp_script_is($this->plugin_name . '-shortcodes', 'enqueued')) {
            wp_enqueue_script(
                $this->plugin_name . '-shortcodes',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-widget.js',
                array('jquery', $this->plugin_name . '-frontend'),
                $this->version,
                true
            );
        }

        // Enqueue main CSS
        if (!wp_style_is($this->plugin_name . '-frontend', 'enqueued')) {
            wp_enqueue_style(
                $this->plugin_name . '-frontend',
                AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-frontend.css',
                array(),
                $this->version
            );
        }

        // Enqueue shortcode-specific CSS
        // if (!wp_style_is($this->plugin_name . '-shortcodes', 'enqueued')) {
        //     wp_enqueue_style(
        //         $this->plugin_name . '-shortcodes',
        //         AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-shortcodes.css',
        //         array($this->plugin_name . '-frontend'),
        //         $this->version
        //     );
        // }

        // Enqueue theme CSS
        if (!wp_style_is($this->plugin_name . '-themes', 'enqueued')) {
            wp_enqueue_style(
                $this->plugin_name . '-themes',
                AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-themes.css',
                array($this->plugin_name . '-frontend'),
                $this->version
            );
        }
    }
}