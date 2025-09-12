<?php
/**
 * Testing Mode Toggle
 * 
 * Add this to your admin settings page or create a separate file
 * includes/class-ai-chatbot-testing.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chatbot_Testing {
    
    public function __construct() {
        add_action('admin_init', array($this, 'init_testing_settings'));
        add_action('admin_notices', array($this, 'show_testing_notice'));
        add_action('wp_ajax_toggle_ai_chatbot_testing', array($this, 'ajax_toggle_testing'));
    }
    
    /**
     * Initialize testing settings
     */
    public function init_testing_settings() {
        // Add testing section to existing settings
        add_settings_section(
            'ai_chatbot_testing',
            __('Testing Mode', 'ai-website-chatbot'),
            array($this, 'render_testing_section_description'),
            'ai-chatbot-settings'
        );
        
        add_settings_field(
            'testing_mode',
            __('Enable Testing Mode', 'ai-website-chatbot'),
            array($this, 'render_testing_mode_field'),
            'ai-chatbot-settings',
            'ai_chatbot_testing'
        );
    }
    
    /**
     * Render testing section description
     */
    public function render_testing_section_description() {
        echo '<p><strong style="color: #d63638;">' . __('WARNING: Testing Mode Only!', 'ai-website-chatbot') . '</strong></p>';
        echo '<p>' . __('This bypasses all Pro license checks for development and testing purposes.', 'ai-website-chatbot') . '</p>';
        echo '<p>' . __('DISABLE before going to production!', 'ai-website-chatbot') . '</p>';
    }
    
    /**
     * Render testing mode field
     */
    public function render_testing_mode_field() {
        $testing_mode = get_option('ai_chatbot_testing_mode', '0');
        ?>
        <label>
            <input type="checkbox" 
                   name="ai_chatbot_testing_mode" 
                   value="1" 
                   <?php checked($testing_mode, '1'); ?>
                   onchange="toggleTestingMode(this);">
            <?php _e('Enable Pro Features for Testing', 'ai-website-chatbot'); ?>
        </label>
        
        <script>
        function toggleTestingMode(checkbox) {
            var data = {
                'action': 'toggle_ai_chatbot_testing',
                'enabled': checkbox.checked ? '1' : '0',
                'nonce': '<?php echo wp_create_nonce('ai_chatbot_testing_nonce'); ?>'
            };
            
            jQuery.post(ajaxurl, data, function(response) {
                if (response.success) {
                    location.reload(); // Reload to apply changes
                } else {
                    alert('Error: ' + response.data);
                    checkbox.checked = !checkbox.checked; // Revert checkbox
                }
            });
        }
        </script>
        
        <?php if ($testing_mode === '1'): ?>
            <p style="color: #d63638; font-weight: bold;">
                <?php _e('🚨 TESTING MODE ACTIVE - All Pro features unlocked!', 'ai-website-chatbot'); ?>
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * AJAX handler for toggling testing mode
     */
    public function ajax_toggle_testing() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('ai_chatbot_testing_nonce', 'nonce');
        
        $enabled = sanitize_text_field($_POST['enabled']);
        
        if ($enabled === '1') {
            // Enable testing mode
            update_option('ai_chatbot_testing_mode', '1');
            update_option('ai_chatbot_pro_enabled_features', json_encode(array(
                'intelligence_engine',
                'context_builder', 
                'intent_recognition',
                'response_reasoning',
                'advanced_analytics',
                'conversation_insights',
                'lead_qualification',
                'custom_integrations',
                'priority_support',
                'smart_responses',
                'conversation_context'
            )));
            
            wp_send_json_success(__('Testing mode enabled!', 'ai-website-chatbot'));
        } else {
            // Disable testing mode
            update_option('ai_chatbot_testing_mode', '0');
            delete_option('ai_chatbot_pro_enabled_features');
            
            wp_send_json_success(__('Testing mode disabled!', 'ai-website-chatbot'));
        }
    }
    
    /**
     * Show testing mode notice in admin
     */
    public function show_testing_notice() {
        if (get_option('ai_chatbot_testing_mode') === '1') {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php _e('AI Chatbot Testing Mode Active!', 'ai-website-chatbot'); ?></strong>
                    <?php _e('All Pro features are unlocked for testing. Remember to disable before production!', 'ai-website-chatbot'); ?>
                    <a href="<?php echo admin_url('admin.php?page=ai-chatbot-settings'); ?>" style="margin-left: 10px;">
                        <?php _e('Manage Testing Mode', 'ai-website-chatbot'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
}

// Initialize testing mode
new AI_Chatbot_Testing();