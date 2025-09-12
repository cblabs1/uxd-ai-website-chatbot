<?php
/**
 * The Gutenberg blocks functionality of the plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Chatbot Gutenberg Blocks Handler
 */
class AI_Chatbot_Gutenberg {

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
     * Initialize Gutenberg blocks
     *
     * @since 1.0.0
     */
    public function init() {
        // Only initialize if Gutenberg is available
        if (!function_exists('register_block_type')) {
            return;
        }

        add_action('init', array($this, 'register_blocks'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
    }

    /**
     * Register all blocks
     *
     * @since 1.0.0
     */
    public function register_blocks() {
        // Register main chatbot block
        register_block_type('ai-chatbot/chatbot', array(
            'attributes' => $this->get_chatbot_block_attributes(),
            'render_callback' => array($this, 'render_chatbot_block'),
            'editor_script' => 'ai-chatbot-blocks',
            'editor_style' => 'ai-chatbot-blocks-editor',
            'style' => 'ai-chatbot-blocks',
        ));

        // Register chatbot button block
        register_block_type('ai-chatbot/button', array(
            'attributes' => $this->get_button_block_attributes(),
            'render_callback' => array($this, 'render_button_block'),
            'editor_script' => 'ai-chatbot-blocks',
            'editor_style' => 'ai-chatbot-blocks-editor',
            'style' => 'ai-chatbot-blocks',
        ));

        // Register chatbot popup block
        register_block_type('ai-chatbot/popup', array(
            'attributes' => $this->get_popup_block_attributes(),
            'render_callback' => array($this, 'render_popup_block'),
            'editor_script' => 'ai-chatbot-blocks',
            'editor_style' => 'ai-chatbot-blocks-editor',
            'style' => 'ai-chatbot-blocks',
        ));
    }

    /**
     * Enqueue block editor assets
     *
     * @since 1.0.0
     */
    public function enqueue_block_editor_assets() {

        $settings = get_option('ai_chatbot_settings', array());
        // Enqueue block editor script
        wp_enqueue_script(
            'ai-chatbot-blocks',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/blocks.js',
            array(
                'wp-blocks',
                'wp-i18n',
                'wp-element',
                'wp-editor',
                'wp-components',
                'wp-compose',
            ),
            $this->version,
            true
        );

        // Enqueue block editor styles
        wp_enqueue_style(
            'ai-chatbot-blocks-editor',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/public/blocks-editor.css',
            array('wp-edit-blocks'),
            $this->version
        );

        // Enqueue block styles for frontend and editor
        wp_enqueue_style(
            'ai-chatbot-blocks',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/public/blocks.css',
            array(),
            $this->version
        );

        // Localize script with configuration
        wp_localize_script(
            'ai-chatbot-blocks',
            'aiChatbotBlocks',
            array(
                'isEnabled' => $settings['enabled'] ?? false,
                'themes' => $this->get_available_themes(),
                'strings' => array(
                    'title' => __('AI Chatbot', 'ai-website-chatbot'),
                    'description' => __('Add an AI chatbot to your content', 'ai-website-chatbot'),
                    'buttonTitle' => __('AI Chatbot Button', 'ai-website-chatbot'),
                    'buttonDescription' => __('Add a button that opens the AI chatbot', 'ai-website-chatbot'),
                    'popupTitle' => __('AI Chatbot Popup', 'ai-website-chatbot'),
                    'popupDescription' => __('Add a popup AI chatbot', 'ai-website-chatbot'),
                    'disabled' => __('AI Chatbot is currently disabled', 'ai-website-chatbot'),
                ),
            )
        );
    }

    /**
     * Get chatbot block attributes
     *
     * @return array Block attributes.
     * @since 1.0.0
     */
    private function get_chatbot_block_attributes() {
        return array(
            'title' => array(
                'type' => 'string',
                'default' => get_option('ai_chatbot_widget_title', __('AI Assistant', 'ai-website-chatbot')),
            ),
            'welcomeMessage' => array(
                'type' => 'string',
                'default' => get_option('ai_chatbot_welcome_message', ''),
            ),
            'height' => array(
                'type' => 'number',
                'default' => 400,
            ),
            'showHeader' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showPoweredBy' => array(
                'type' => 'boolean',
                'default' => get_option('ai_chatbot_show_powered_by', true),
            ),
            'theme' => array(
                'type' => 'string',
                'default' => 'default',
            ),
            'align' => array(
                'type' => 'string',
            ),
        );
    }

    /**
     * Get button block attributes
     *
     * @return array Block attributes.
     * @since 1.0.0
     */
    private function get_button_block_attributes() {
        return array(
            'text' => array(
                'type' => 'string',
                'default' => __('Chat with AI', 'ai-website-chatbot'),
            ),
            'size' => array(
                'type' => 'string',
                'default' => 'medium',
            ),
            'style' => array(
                'type' => 'string',
                'default' => 'primary',
            ),
            'align' => array(
                'type' => 'string',
            ),
        );
    }

    /**
     * Get popup block attributes
     *
     * @return array Block attributes.
     * @since 1.0.0
     */
    private function get_popup_block_attributes() {
        return array(
            'triggerText' => array(
                'type' => 'string',
                'default' => __('Open AI Chat', 'ai-website-chatbot'),
            ),
            'popupTitle' => array(
                'type' => 'string',
                'default' => __('AI Assistant', 'ai-website-chatbot'),
            ),
            'welcomeMessage' => array(
                'type' => 'string',
                'default' => get_option('ai_chatbot_welcome_message', ''),
            ),
            'theme' => array(
                'type' => 'string',
                'default' => 'default',
            ),
        );
    }

    /**
     * Render chatbot block
     *
     * @param array $attributes Block attributes.
     * @return string Block HTML output.
     * @since 1.0.0
     */
    public function render_chatbot_block($attributes) {
        $settings = get_option('ai_chatbot_settings', array());
        // Check if chatbot is enabled
        if (empty($settings['enabled']) || $settings['enabled'] !== true) {
            return $this->get_disabled_block_message();
        }

        // Check user permissions
        if (!$this->check_user_permissions()) {
            return '';
        }

        $shortcode_atts = array(
            'type' => 'inline',
            'title' => $attributes['title'],
            'welcome_message' => $attributes['welcomeMessage'],
            'height' => $attributes['height'] . 'px',
            'show_header' => $attributes['showHeader'] ? 'true' : 'false',
            'show_powered_by' => $attributes['showPoweredBy'] ? 'true' : 'false',
            'theme' => $attributes['theme'],
            'class' => !empty($attributes['align']) ? 'align' . $attributes['align'] : '',
        );

        $shortcode = new AI_Chatbot_Shortcodes($this->plugin_name, $this->version);
        return $shortcode->chatbot_shortcode($shortcode_atts);
    }

    /**
     * Render button block
     *
     * @param array $attributes Block attributes.
     * @return string Block HTML output.
     * @since 1.0.0
     */
    public function render_button_block($attributes) {
        $settings = get_option('ai_chatbot_settings', array());
        // Check if chatbot is enabled
        if (empty($settings['enabled']) || $settings['enabled'] !== true) {
            return $this->get_disabled_block_message();
        }

        // Check user permissions
        if (!$this->check_user_permissions()) {
            return '';
        }

        $button_class = 'ai-chatbot-block-button';
        $button_class .= ' size-' . esc_attr($attributes['size']);
        $button_class .= ' style-' . esc_attr($attributes['style']);

        if (!empty($attributes['align'])) {
            $button_class .= ' align' . esc_attr($attributes['align']);
        }

        ob_start();
        ?>
        <div class="ai-chatbot-button-block">
            <button type="button" 
                    class="<?php echo esc_attr($button_class); ?>"
                    data-chatbot-trigger="button">
                <span class="button-icon">💬</span>
                <span class="button-text"><?php echo esc_html($attributes['text']); ?></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render popup block
     *
     * @param array $attributes Block attributes.
     * @return string Block HTML output.
     * @since 1.0.0
     */
    public function render_popup_block($attributes) {
        // Check if chatbot is enabled
        $settings = get_option('ai_chatbot_settings', array());
        // Check if chatbot is enabled
        if (empty($settings['enabled']) || $settings['enabled'] !== true) {
            return $this->get_disabled_block_message();
        }

        // Check user permissions
        if (!$this->check_user_permissions()) {
            return '';
        }

        $shortcode_atts = array(
            'type' => 'popup',
            'title' => $attributes['popupTitle'],
            'welcome_message' => $attributes['welcomeMessage'],
            'theme' => $attributes['theme'],
        );

        $shortcode = new AI_Chatbot_Shortcodes($this->plugin_name, $this->version);
        
        // Get the popup HTML from shortcode
        $popup_html = $shortcode->chatbot_shortcode($shortcode_atts);
        
        // Modify the trigger button text
        $popup_html = str_replace(
            '<span class="button-text">' . $attributes['popupTitle'] . '</span>',
            '<span class="button-text">' . esc_html($attributes['triggerText']) . '</span>',
            $popup_html
        );

        return $popup_html;
    }

    /**
     * Get available themes
     *
     * @return array Available themes.
     * @since 1.0.0
     */
    private function get_available_themes() {
        return array(
            'default' => __('Default', 'ai-website-chatbot'),
            'modern' => __('Modern', 'ai-website-chatbot'),
            'classic' => __('Classic', 'ai-website-chatbot'),
            'minimal' => __('Minimal', 'ai-website-chatbot'),
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
     * Get disabled block message for admin users
     *
     * @return string HTML message.
     * @since 1.0.0
     */
    private function get_disabled_block_message() {
        if (current_user_can('manage_options')) {
            return '<div class="ai-chatbot-block-disabled notice notice-warning inline">' . 
                   '<p>' . esc_html__('AI Chatbot is currently disabled. Enable it in the admin settings.', 'ai-website-chatbot') . '</p>' .
                   '</div>';
        }
        return '';
    }
}
