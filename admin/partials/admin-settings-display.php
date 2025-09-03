<?php
/**
 * Settings page display
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current provider to show provider-specific settings
$current_provider = get_option('ai_chatbot_provider', 'openai');
$provider_manager = new AI_Chatbot_Provider_Manager();
$providers = $provider_manager->get_available_providers();
?>

<div class="wrap">
    <h1><?php _e('AI Chatbot Settings', 'ai-website-chatbot'); ?></h1>

    <?php settings_errors(); ?>

    <div class="ai-chatbot-settings">
        <form method="post" action="options.php">
            <?php
            settings_fields($this->plugin_name . '-settings');
            do_settings_sections($this->plugin_name . '-settings');
            ?>

            <!-- Provider-specific settings -->
            <div class="provider-settings" id="provider-settings">
                <h2><?php _e('Provider Settings', 'ai-website-chatbot'); ?></h2>
                
                <?php foreach ($providers as $provider_id => $provider): ?>
                    <div class="provider-config" data-provider="<?php echo esc_attr($provider_id); ?>" 
                         style="display: <?php echo $current_provider === $provider_id ? 'block' : 'none'; ?>">
                        <h3><?php echo esc_html($provider->get_display_name()); ?></h3>
                        
                        <?php
                        $config_fields = $provider->get_config_fields();
                        foreach ($config_fields as $field_id => $field):
                            $value = get_option($field_id, $field['default'] ?? '');
                        ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($field_id); ?>">
                                            <?php echo esc_html($field['label']); ?>
                                            <?php if (isset($field['required']) && $field['required']): ?>
                                                <span class="required">*</span>
                                            <?php endif; ?>
                                        </label>
                                    </th>
                                    <td>
                                        <?php
                                        switch ($field['type']) {
                                            case 'text':
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
                                            case 'select':
                                                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '">';
                                                foreach ($field['options'] as $option_value => $option_label) {
                                                    echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
                                                }
                                                echo '</select>';
                                                break;
                                        }
                                        
                                        if (isset($field['description'])) {
                                            echo '<p class="description">' . esc_html($field['description']) . '</p>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        <?php endforeach; ?>

                        <!-- Test connection button -->
                        <div class="provider-actions">
                            <button type="button" class="button test-connection" data-provider="<?php echo esc_attr($provider_id); ?>">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Test Connection', 'ai-website-chatbot'); ?>
                            </button>
                            <button type="button" class="button reset-stats" data-provider="<?php echo esc_attr($provider_id); ?>">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Reset Statistics', 'ai-website-chatbot'); ?>
                            </button>
                        </div>

                        <!-- Usage statistics -->
                        <?php
                        $stats = $provider->get_usage_stats();
                        if (!empty($stats['total_requests'])):
                        ?>
                            <div class="usage-stats">
                                <h4><?php _e('Usage Statistics', 'ai-website-chatbot'); ?></h4>
                                <div class="stats-grid">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo number_format($stats['total_requests']); ?></span>
                                        <span class="stat-label"><?php _e('Requests', 'ai-website-chatbot'); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo number_format($stats['total_tokens']); ?></span>
                                        <span class="stat-label"><?php _e('Tokens', 'ai-website-chatbot'); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number">$<?php echo number_format($stats['total_cost'], 4); ?></span>
                                        <span class="stat-label"><?php _e('Cost', 'ai-website-chatbot'); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="settings-actions">
                <?php submit_button(); ?>
            </div>
        </form>
    </div>

    <!-- Test results modal -->
    <div id="test-results-modal" class="ai-chatbot-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Connection Test Results', 'ai-website-chatbot'); ?></h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="test-results"></div>
            </div>
        </div>
    </div>
</div>
