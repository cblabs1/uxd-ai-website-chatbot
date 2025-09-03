<?php
/**
 * Admin Settings Class
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings functionality.
 */
class AI_Chatbot_Admin_Settings {

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
     * Settings sections.
     *
     * @var array
     * @since 1.0.0
     */
    private $sections;

    /**
     * Initialize the class.
     *
     * @param string $plugin_name Plugin name.
     * @param string $version Plugin version.
     * @since 1.0.0
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->sections = $this->get_settings_sections();

        add_action('wp_ajax_ai_chatbot_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_ai_chatbot_reset_stats', array($this, 'reset_usage_stats'));
    }

    /**
     * Register settings.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        foreach ($this->sections as $section_id => $section) {
            add_settings_section(
                $section_id,
                $section['title'],
                array($this, 'section_callback'),
                $this->plugin_name . '-settings'
            );

            foreach ($section['fields'] as $field_id => $field) {
                register_setting(
                    $this->plugin_name . '-settings',
                    $field_id,
                    array(
                        'sanitize_callback' => array($this, 'sanitize_field'),
                        'default' => $field['default'] ?? '',
                    )
                );

                add_settings_field(
                    $field_id,
                    $field['label'],
                    array($this, 'field_callback'),
                    $this->plugin_name . '-settings',
                    $section_id,
                    array(
                        'field_id' => $field_id,
                        'field' => $field,
                    )
                );
            }
        }
    }

    /**
     * Display settings page.
     *
     * @since 1.0.0
     */
    public function display_settings_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/admin-settings-display.php';
    }

    /**
     * Get settings sections.
     *
     * @return array Settings sections.
     * @since 1.0.0
     */
    private function get_settings_sections() {
        return array(
            'general' => array(
                'title' => __('General Settings', 'ai-website-chatbot'),
                'fields' => array(
                    'ai_chatbot_enabled' => array(
                        'label' => __('Enable Chatbot', 'ai-website-chatbot'),
                        'type' => 'checkbox',
                        'description' => __('Enable or disable the chatbot', 'ai-website-chatbot'),
                        'default' => '1',
                    ),
                    'ai_chatbot_provider' => array(
                        'label' => __('AI Provider', 'ai-website-chatbot'),
                        'type' => 'select',
                        'options' => array(
                            'openai' => 'OpenAI (ChatGPT)',
                            'claude' => 'Anthropic Claude',
                            'gemini' => 'Google Gemini',
                        ),
                        'description' => __('Choose your AI provider', 'ai-website-chatbot'),
                        'default' => 'openai',
                    ),
                    'ai_chatbot_position' => array(
                        'label' => __('Chatbot Position', 'ai-website-chatbot'),
                        'type' => 'select',
                        'options' => array(
                            'bottom-right' => __('Bottom Right', 'ai-website-chatbot'),
                            'bottom-left' => __('Bottom Left', 'ai-website-chatbot'),
                            'top-right' => __('Top Right', 'ai-website-chatbot'),
                            'top-left' => __('Top Left', 'ai-website-chatbot'),
                        ),
                        'default' => 'bottom-right',
                    ),
                    'ai_chatbot_welcome_message' => array(
                        'label' => __('Welcome Message', 'ai-website-chatbot'),
                        'type' => 'textarea',
                        'description' => __('Initial message shown to users', 'ai-website-chatbot'),
                        'default' => __('Hello! How can I help you today?', 'ai-website-chatbot'),
                    ),
                    'ai_chatbot_custom_prompt' => array(
                        'label' => __('Custom System Prompt', 'ai-website-chatbot'),
                        'type' => 'textarea',
                        'description' => __('Custom instructions for the AI (leave empty for default)', 'ai-website-chatbot'),
                        'default' => '',
                    ),
                ),
            ),
            'appearance' => array(
                'title' => __('Appearance Settings', 'ai-website-chatbot'),
                'fields' => array(
                    'ai_chatbot_theme' => array(
                        'label' => __('Theme', 'ai-website-chatbot'),
                        'type' => 'select',
                        'options' => array(
                            'modern' => __('Modern', 'ai-website-chatbot'),
                            'classic' => __('Classic', 'ai-website-chatbot'),
                            'minimal' => __('Minimal', 'ai-website-chatbot'),
                        ),
                        'default' => 'modern',
                    ),
                    'ai_chatbot_primary_color' => array(
                        'label' => __('Primary Color', 'ai-website-chatbot'),
                        'type' => 'color',
                        'default' => '#007cba',
                    ),
                    'ai_chatbot_width' => array(
                        'label' => __('Chat Width (px)', 'ai-website-chatbot'),
                        'type' => 'number',
                        'min' => 300,
                        'max' => 800,
                        'default' => 350,
                    ),
                    'ai_chatbot_height' => array(
                        'label' => __('Chat Height (px)', 'ai-website-chatbot'),
                        'type' => 'number',
                        'min' => 400,
                        'max' => 800,
                        'default' => 500,
                    ),
                ),
            ),
            'privacy' => array(
                'title' => __('Privacy & Data', 'ai-website-chatbot'),
                'fields' => array(
                    'ai_chatbot_store_conversations' => array(
                        'label' => __('Store Conversations', 'ai-website-chatbot'),
                        'type' => 'checkbox',
                        'description' => __('Store conversation logs for analytics', 'ai-website-chatbot'),
                        'default' => '1',
                    ),
                    'ai_chatbot_data_retention_days' => array(
                        'label' => __('Data Retention (days)', 'ai-website-chatbot'),
                        'type' => 'number',
                        'min' => 1,
                        'max' => 365,
                        'description' => __('How long to keep conversation data', 'ai-website-chatbot'),
                        'default' => 30,
                    ),
                    'ai_chatbot_collect_user_data' => array(
                        'label' => __('Collect User Data', 'ai-website-chatbot'),
                        'type' => 'checkbox',
                        'description' => __('Collect user email/name if provided', 'ai-website-chatbot'),
                        'default' => '0',
                    ),
                ),
            ),
        );
    }

    /**
     * Section callback.
     *
     * @param array $args Section arguments.
     * @since 1.0.0
     */
    public function section_callback($args) {
        $section_descriptions = array(
            'general' => __('Configure basic chatbot settings.', 'ai-website-chatbot'),
            'appearance' => __('Customize the chatbot appearance.', 'ai-website-chatbot'),
            'privacy' => __('Configure privacy and data handling.', 'ai-website-chatbot'),
        );

        if (isset($section_descriptions[$args['id']])) {
            echo '<p>' . esc_html($section_descriptions[$args['id']]) . '</p>';
        }
    }

    /**
     * Field callback.
     *
     * @param array $args Field arguments.
     * @since 1.0.0
     */
    public function field_callback($args) {
        $field_id = $args['field_id'];
        $field = $args['field'];
        $value = get_option($field_id, $field['default'] ?? '');

        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'password':
                echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;

            case 'number':
                echo '<input type="number" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" class="small-text"';
                if (isset($field['min'])) echo ' min="' . esc_attr($field['min']) . '"';
                if (isset($field['max'])) echo ' max="' . esc_attr($field['max']) . '"';
                if (isset($field['step'])) echo ' step="' . esc_attr($field['step']) . '"';
                echo ' />';
                break;

            case 'color':
                echo '<input type="color" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" />';
                break;

            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="1" ' . checked($value, '1', false) . ' />';
                echo '<label for="' . esc_attr($field_id) . '">' . esc_html($field['label']) . '</label>';
                break;

            case 'select':
                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '">';
                foreach ($field['options'] as $option_value => $option_label) {
                    echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
                }
                echo '</select>';
                break;

            case 'textarea':
                echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
                break;
        }

        if (isset($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    /**
     * Sanitize field value.
     *
     * @param mixed $value Field value.
     * @return mixed Sanitized value.
     * @since 1.0.0
     */
    public function sanitize_field($value) {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return sanitize_text_field($value);
    }

    /**
     * Test API connection via AJAX.
     *
     * @since 1.0.0
     */
    public function test_connection() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        
        try {
            $provider_manager = new AI_Chatbot_Provider_Manager();
            $provider_instance = $provider_manager->get_provider($provider);
            
            if (!$provider_instance) {
                wp_send_json_error(__('Invalid provider', 'ai-website-chatbot'));
            }

            $result = $provider_instance->test_connection();
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success(__('Connection successful!', 'ai-website-chatbot'));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Reset usage statistics via AJAX.
     *
     * @since 1.0.0
     */
    public function reset_usage_stats() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        
        try {
            $provider_manager = new AI_Chatbot_Provider_Manager();
            $provider_instance = $provider_manager->get_provider($provider);
            
            if (!$provider_instance) {
                wp_send_json_error(__('Invalid provider', 'ai-website-chatbot'));
            }

            $result = $provider_instance->reset_usage_stats();
            
            if ($result) {
                wp_send_json_success(__('Usage statistics reset successfully!', 'ai-website-chatbot'));
            } else {
                wp_send_json_error(__('Failed to reset statistics', 'ai-website-chatbot'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
