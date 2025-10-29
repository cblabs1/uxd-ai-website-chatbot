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
            'enable_audio_mode' => get_option('ai_chatbot_audio_mode_enabled', 'true'),
            'enable_tts' => get_option('ai_chatbot_tts_enabled', 'false'),
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

                <div class="ai-chatbot-typing" id="ai-chatbot-typing" style="display: none;">
                    <div class="ai-chatbot-typing-content">
                        <div class="ai-chatbot-typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span class="ai-chatbot-typing-text"><?php esc_html_e('AI is typing...', 'ai-website-chatbot'); ?></span>
                    </div>
                </div>
                
                <div class="ai-chatbot-input-area">
                    <form class="ai-chatbot-input-form" id="ai-chatbot-input-form">
                        <div class="ai-chatbot-input-container">
                            <?php 
                                $has_audio_features = function_exists('ai_chatbot_has_feature') && ai_chatbot_has_feature('audio_features');
                                $audio_mode_enabled = filter_var($atts['enable_audio_mode'], FILTER_VALIDATE_BOOLEAN);

                                if ($has_audio_features && $audio_mode_enabled): 
                            ?>
                            <button type="button" class="ai-chatbot-voice-btn inline-widget voice-btn" title="<?php _e('Start Audio Conversation', 'ai-website-chatbot'); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                    <line x1="12" y1="19" x2="12" y2="23"></line>
                                    <line x1="8" y1="23" x2="16" y2="23"></line>
                                </svg>
                            </button>
                            <?php endif; ?>
                            <textarea class="ai-chatbot-input ai-chatbot-input-empty" id="ai-chatbot-input" placeholder="Type your message..." rows="1" maxlength="1000" ></textarea>
                            <button type="submit" class="ai-chatbot-send-button" id="ai-chatbot-send-button" disabled="">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18 10L2 18L5 10L2 2L18 10Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"></path>
                                </svg>
                            </button>
                        </div>
                    </form>
                    <?php 
                    $has_white_label = function_exists('ai_chatbot_has_feature') && ai_chatbot_has_feature('white_label');
                    $settings = get_option('ai_chatbot_settings', array());
                    $custom_branding = isset($settings['custom_branding_text']) ? $settings['custom_branding_text'] : '';

                    // Determine what to show
                    $show_branding = filter_var($atts['show_powered_by'], FILTER_VALIDATE_BOOLEAN) && !($has_white_label && empty($custom_branding));
                    $branding_text = ($has_white_label && !empty($custom_branding)) ? $custom_branding : sprintf(__('Powered by UXD AI Chatbot', 'ai-website-chatbot'));

                    if ($show_branding): 
                    ?>
                    <div class="ai-chatbot-footer">
                        <span class="ai-chatbot-powered-by">
                            <?php echo esc_html($branding_text); ?>
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
            $has_audio_features = function_exists('ai_chatbot_has_feature') && ai_chatbot_has_feature('audio_features');

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
                'enable_voice_input' => filter_var($atts['enable_voice_input'], FILTER_VALIDATE_BOOLEAN) && $has_audio_features,
                'enable_conversation_save' => filter_var($atts['enable_conversation_save'], FILTER_VALIDATE_BOOLEAN),
                'enableAudioMode' => filter_var($atts['enable_audio_mode'], FILTER_VALIDATE_BOOLEAN) && $has_audio_features,
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
            'enableAudioMode' => filter_var($atts['enable_audio_mode'], FILTER_VALIDATE_BOOLEAN),
            'enableTTS' => filter_var($atts['enable_tts'], FILTER_VALIDATE_BOOLEAN),
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

        

        // ✅ NEW: Check if audio features are enabled
        $audio_enabled = get_option('ai_chatbot_audio_features_enabled', false);
        $audio_mode_enabled = get_option('ai_chatbot_audio_mode_enabled', false);
        
        if ($audio_enabled || $audio_mode_enabled) {
            // Enqueue audio features CSS
            wp_enqueue_style(
                $this->plugin_name . '-audio-css',
                AI_CHATBOT_PLUGIN_URL . 'assets/css/public/pro/audio-features.css',
                array($this->plugin_name . '-frontend'),
                $this->version
            );
            
            // Enqueue audio features JS
            wp_enqueue_script(
                $this->plugin_name . '-audio-features',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio-features.js',
                array('jquery', $this->plugin_name . '-audio-core'),
                $this->version,
                true
            );
            
            // Enqueue audio mode JS if enabled
            if ($audio_mode_enabled) {
                wp_enqueue_script(
                    $this->plugin_name . '-audio-mode',
                    AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio-mode.js',
                    array('jquery', $this->plugin_name . '-audio-core'),
                    $this->version,
                    true
                );
            }

            
        }

         wp_localize_script('ai-chatbot-audio-features', 'aiChatbotAudio', $this->get_audio_configuration());
           

    }

    /**
     * Get audio configuration
     */
    private function get_audio_configuration() {
        $settings = get_option('ai_chatbot_settings', array());
        $audio_settings = $settings['audio_features'] ?? array(); 
        
        return array(
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'audio_enabled' => !empty($audio_settings['enabled']),
            'voice_input' => array(
                'enabled' => !empty($audio_settings['voice_input_enabled']),
                'language' => $audio_settings['voice_language'] ?? 'en-US',
                'continuous' => !empty($audio_settings['voice_continuous']),
                'interim_results' => isset($audio_settings['voice_interim_results']) ? !empty($audio_settings['voice_interim_results']) : true,
                'auto_send' => !empty($audio_settings['voice_auto_send']),
            ),
            'tts' => array(
                'enabled' => !empty($audio_settings['tts_enabled']),
                'auto_play' => !empty($audio_settings['tts_auto_play']),
                'rate' => floatval($audio_settings['tts_rate'] ?? 1.0),
                'pitch' => floatval($audio_settings['tts_pitch'] ?? 1.0),
                'volume' => floatval($audio_settings['tts_volume'] ?? 0.8),
                'language' => $audio_settings['voice_language'] ?? 'en-US',
            ),
            'voice_selection' => array(
                'enabled' => !empty($audio_settings['voice_selection_enabled']),
                'gender' => $audio_settings['voice_gender'] ?? 'female',
                'language' => $audio_settings['voice_language'] ?? 'en-US',
                'specific_voice' => $audio_settings['specific_voice'] ?? '',
                'personality' => $audio_settings['voice_personality'] ?? 'friendly',
            ),
            'audio_mode' => array(
                'enabled' => !empty($audio_settings['audio_mode_enabled']),
                'auto_listen' => true,
                'silence_timeout' => intval($audio_settings['audio_mode_silence_timeout'] ?? 30),
                'max_time' => intval($audio_settings['audio_mode_max_time'] ?? 300),
            ),
            'strings' => array(
                'listening' => __('Listening...', 'ai-website-chatbot'),
                'speaking' => __('Speaking...', 'ai-website-chatbot'),
                'processing' => __('Processing...', 'ai-website-chatbot'),
                'paused' => __('Paused', 'ai-website-chatbot'),
                'error' => __('Error occurred', 'ai-website-chatbot'),
            )
        );
    }
    
    public function add_audio_config($config) {
        if (!is_array($config)) {
            $config = array();
        }
        $config['audio'] = $this->get_audio_configuration();
        return $config;
    }
}