<?php
/**
 * The widget functionality of the plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Chatbot Widget Class
 */
class AI_Chatbot_Widget extends WP_Widget {

    /**
     * Register widget with WordPress.
     *
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct(
            'ai_chatbot_widget',
            __('AI Chatbot', 'ai-website-chatbot'),
            array(
                'description' => __('Display an AI chatbot in your sidebar or widget area.', 'ai-website-chatbot'),
                'classname' => 'ai-chatbot-widget-container',
            )
        );
    }

    /**
     * Front-end display of widget.
     *
     * @param array $args Widget arguments.
     * @param array $instance Saved values from database.
     * @since 1.0.0
     */
    public function widget($args, $instance) {
        // Check if chatbot is enabled
        $settings = get_option('ai_chatbot_settings', array());
        // Check if chatbot is enabled
        if (empty($settings['enabled']) || $settings['enabled'] !== true) {
            if (current_user_can('manage_options')) {
                echo '<div class="ai-chatbot-disabled">' . 
                     esc_html__('AI Chatbot is currently disabled.', 'ai-website-chatbot') . 
                     '</div>';
            }
            return;
        }

        // Check user permissions
        if (!$this->check_user_permissions()) {
            return;
        }

        $title = !empty($instance['title']) ? $instance['title'] : __('AI Assistant', 'ai-website-chatbot');
        $title = apply_filters('widget_title', $title);

        $height = !empty($instance['height']) ? intval($instance['height']) : 400;
        $welcome_message = !empty($instance['welcome_message']) ? $instance['welcome_message'] : get_option('ai_chatbot_welcome_message', '');
        $show_header = !empty($instance['show_header']);
        $show_powered_by = !empty($instance['show_powered_by']);
        $theme = !empty($instance['theme']) ? $instance['theme'] : 'default';

        // Enqueue necessary scripts and styles
        $this->enqueue_widget_assets();

        echo $args['before_widget'];
        
        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        $widget_id = 'ai-chatbot-widget-' . uniqid();
        
        ?>
        <div id="<?php echo esc_attr($widget_id); ?>" 
             class="ai-chatbot-widget-content"
             data-chatbot-type="widget"
             data-chatbot-config="<?php echo esc_attr(wp_json_encode($this->get_widget_config($instance))); ?>"
             style="height: <?php echo esc_attr($height); ?>px;">
            
            <?php if ($show_header): ?>
            <div class="ai-chatbot-header">
                <div class="ai-chatbot-status">
                    <span class="status-indicator"></span>
                    <span class="status-text"><?php esc_html_e('Online', 'ai-website-chatbot'); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="ai-chatbot-messages">
                <?php if (!empty($welcome_message)): ?>
                <div class="ai-chatbot-message bot-message">
                    <div class="message-avatar">🤖</div>
                    <div class="message-content"><?php echo wp_kses_post($welcome_message); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="ai-chatbot-input-area">
                <div class="ai-chatbot-typing-indicator" style="display: none;">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="typing-text"><?php esc_html_e('TeekayDee is typing...', 'ai-website-chatbot'); ?></span>
                </div>
                
                <form class="ai-chatbot-form">
                    <div class="input-group">
                        <input type="text" 
                               class="ai-chatbot-input" 
                               placeholder="<?php esc_attr_e('Type your message...', 'ai-website-chatbot'); ?>"
                               maxlength="<?php echo esc_attr(get_option('ai_chatbot_max_message_length', 1000)); ?>">
                        <button type="submit" class="ai-chatbot-send-btn" disabled>
                            <span class="send-icon">📤</span>
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($show_powered_by): ?>
            <div class="ai-chatbot-powered-by">
                <small><?php esc_html_e('Powered by AI Website Chatbot', 'ai-website-chatbot'); ?></small>
            </div>
            <?php endif; ?>
        </div>
        <?php

        echo $args['after_widget'];
    }

    /**
     * Back-end widget form.
     *
     * @param array $instance Previously saved values from database.
     * @since 1.0.0
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('AI Assistant', 'ai-website-chatbot');
        $height = !empty($instance['height']) ? $instance['height'] : 400;
        $welcome_message = !empty($instance['welcome_message']) ? $instance['welcome_message'] : '';
        $show_header = !empty($instance['show_header']);
        $show_powered_by = !empty($instance['show_powered_by']);
        $theme = !empty($instance['theme']) ? $instance['theme'] : 'default';
        ?>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'ai-website-chatbot'); ?>
            </label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('height')); ?>">
                <?php esc_html_e('Height (px):', 'ai-website-chatbot'); ?>
            </label>
            <input class="small-text" 
                   id="<?php echo esc_attr($this->get_field_id('height')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('height')); ?>" 
                   type="number" 
                   min="200" 
                   max="800" 
                   value="<?php echo esc_attr($height); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('welcome_message')); ?>">
                <?php esc_html_e('Welcome Message:', 'ai-website-chatbot'); ?>
            </label>
            <textarea class="widefat" 
                      id="<?php echo esc_attr($this->get_field_id('welcome_message')); ?>" 
                      name="<?php echo esc_attr($this->get_field_name('welcome_message')); ?>" 
                      rows="3"><?php echo esc_textarea($welcome_message); ?></textarea>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('theme')); ?>">
                <?php esc_html_e('Theme:', 'ai-website-chatbot'); ?>
            </label>
            <select class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('theme')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('theme')); ?>">
                <option value="default" <?php selected($theme, 'default'); ?>><?php esc_html_e('Default', 'ai-website-chatbot'); ?></option>
                <option value="modern" <?php selected($theme, 'modern'); ?>><?php esc_html_e('Modern', 'ai-website-chatbot'); ?></option>
                <option value="classic" <?php selected($theme, 'classic'); ?>><?php esc_html_e('Classic', 'ai-website-chatbot'); ?></option>
                <option value="minimal" <?php selected($theme, 'minimal'); ?>><?php esc_html_e('Minimal', 'ai-website-chatbot'); ?></option>
            </select>
        </p>

        <p>
            <input class="checkbox" 
                   type="checkbox" 
                   <?php checked($show_header); ?> 
                   id="<?php echo esc_attr($this->get_field_id('show_header')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_header')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_header')); ?>">
                <?php esc_html_e('Show Header', 'ai-website-chatbot'); ?>
            </label>
        </p>

        <p>
            <input class="checkbox" 
                   type="checkbox" 
                   <?php checked($show_powered_by); ?> 
                   id="<?php echo esc_attr($this->get_field_id('show_powered_by')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_powered_by')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_powered_by')); ?>">
                <?php esc_html_e('Show "Powered By"', 'ai-website-chatbot'); ?>
            </label>
        </p>

        <?php
    }

    /**
     * Sanitize widget form values as they are saved.
     *
     * @param array $new_instance Values just sent to be saved.
     * @param array $old_instance Previously saved values from database.
     * @return array Updated safe values to be saved.
     * @since 1.0.0
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['height'] = (!empty($new_instance['height'])) ? absint($new_instance['height']) : 400;
        $instance['welcome_message'] = (!empty($new_instance['welcome_message'])) ? wp_kses_post($new_instance['welcome_message']) : '';
        $instance['show_header'] = !empty($new_instance['show_header']);
        $instance['show_powered_by'] = !empty($new_instance['show_powered_by']);
        $instance['theme'] = (!empty($new_instance['theme'])) ? sanitize_text_field($new_instance['theme']) : 'default';

        return $instance;
    }

    /**
     * Get widget configuration
     *
     * @param array $instance Widget instance.
     * @return array Configuration array.
     * @since 1.0.0
     */
    private function get_widget_config($instance) {
        return array(
            'type' => 'widget',
            'sessionId' => 'widget_' . uniqid(),
            'welcomeMessage' => !empty($instance['welcome_message']) ? $instance['welcome_message'] : '',
            'theme' => !empty($instance['theme']) ? $instance['theme'] : 'default',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'settings' => array(
                'maxMessageLength' => get_option('ai_chatbot_max_message_length', 1000),
                'enableRating' => get_option('ai_chatbot_enable_rating', true),
                'enableHistory' => get_option('ai_chatbot_enable_history', true),
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
     * Enqueue widget-specific assets
     *
     * @since 1.0.0
     */
    private function enqueue_widget_assets() {
        wp_enqueue_script(
            'ai-chatbot-widget',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/chatbot-widget.js',
            array('jquery', 'ai-chatbot-frontend-js'),
            AI_CHATBOT_VERSION,
            true
        );

        wp_enqueue_style(
            'ai-chatbot-widget',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/public/chatbot-widget.css',
            array(),
            AI_CHATBOT_VERSION,
            'all'
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
    }
}

/**
 * AI Chatbot Widgets Handler
 */
class AI_Chatbot_Widgets {

    /**
     * Initialize widgets
     *
     * @since 1.0.0
     */
    public function init() {
        add_action('widgets_init', array($this, 'register_widgets'));
    }

    /**
     * Register all chatbot widgets
     *
     * @since 1.0.0
     */
    public function register_widgets() {
        register_widget('AI_Chatbot_Widget');
    }
}
