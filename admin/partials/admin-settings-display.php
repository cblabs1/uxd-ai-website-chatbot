<?php
/**
 * Provide a admin settings view for the plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$all_settings = $this->get_settings();
$default_settings = array(
    'enabled' => false,
    'cache_responses' => false,
    'show_typing_indicator' => true,
    'show_timestamp' => false,
    'show_on_pages' => array('all'),
    'custom_css' => '',
    'gdpr' => array(
        'enabled' => false,
        'cookie_consent' => false,
        'anonymize_data' => false
    ),
    'rate_limiting' => array(
        'enabled' => false
    ),
    'content_sync' => array(
        'enabled' => false,
    ),
    'debug_mode' => false,
    'log_conversations' => true,
);
$providers = $this->get_available_providers();
$post_types = $this->get_available_post_types();
$widget_positions = $this->get_widget_positions();
$widget_sizes = $this->get_widget_sizes();
$animation_styles = $this->get_animation_styles();

// Merge with defaults to ensure all keys exist
$settings = wp_parse_args($all_settings, $default_settings);

?>

<div class="wrap ai-chatbot-settings-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('ai_chatbot_settings'); ?>
    
    <div class="nav-tab-wrapper">
        <a href="#general-settings" class="nav-tab nav-tab-active"><?php _e('General', 'ai-website-chatbot'); ?></a>
        <a href="#ai-provider-settings" class="nav-tab"><?php _e('AI Provider', 'ai-website-chatbot'); ?></a>
        <a href="#display-settings" class="nav-tab"><?php _e('Display', 'ai-website-chatbot'); ?></a>
        <a href="#advanced-settings" class="nav-tab"><?php _e('Advanced', 'ai-website-chatbot'); ?></a>
        <a href="#privacy-settings" class="nav-tab"><?php _e('Privacy', 'ai-website-chatbot'); ?></a>
    </div>
    
    <form method="post" action="" class="ai-chatbot-settings-form">
        <?php wp_nonce_field('ai_chatbot_admin_nonce', 'nonce');  ?>
        
        <!-- General Settings Tab -->
        <div id="general-settings" class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="chatbot_enabled"><?php _e('Enable Chatbot', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" id="chatbot_enabled" name="ai_chatbot_settings[enabled]" value="1" <?php checked($settings['enabled'] ?? false); ?>>                            
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Enable or disable the AI chatbot on your website.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="welcome_message"><?php _e('Welcome Message', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <textarea id="welcome_message" name="ai_chatbot_settings[welcome_message]" rows="3" cols="50" class="large-text"><?php echo esc_textarea($settings['welcome_message']); ?></textarea>
                        <p class="description"><?php _e('The first message users will see when they open the chatbot.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="offline_message"><?php _e('Offline Message', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <textarea id="offline_message" name="ai_chatbot_settings[offline_message]" rows="3" cols="50" class="large-text"><?php echo esc_textarea($settings['offline_message']); ?></textarea>
                        <p class="description"><?php _e('Message to show when the chatbot is unavailable.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- AI Provider Settings Tab -->
        <div id="ai-provider-settings" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ai_provider"><?php _e('AI Provider', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <select id="ai_provider" name="ai_chatbot_settings[ai_provider]">
                            <?php foreach ($providers as $provider_key => $provider_info): ?>
                                <option value="<?php echo esc_attr($provider_key); ?>" <?php selected($settings['ai_provider'], $provider_key); ?>>
                                    <?php echo esc_html($provider_info['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description provider-help-text">
                            <?php 
                            $current_provider = $providers[$settings['ai_provider']] ?? $providers['openai'];
                            echo wp_kses_post($current_provider['description']); 
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="api_key"><?php _e('API Key', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="api_key" name="ai_chatbot_settings[api_key]" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text">
                        <button type="button" class="button test-api-connection"><?php _e('Test Connection', 'ai-website-chatbot'); ?></button>
                        <p class="description"><?php _e('Enter your API key from your chosen AI provider.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ai_model"><?php _e('AI Model', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <select id="ai_model" name="ai_chatbot_settings[model]">
                            <?php 
                            $current_provider_models = $providers[$settings['ai_provider']]['models'] ?? [];
                            foreach ($current_provider_models as $model_key => $model_name): 
                            ?>
                                <option value="<?php echo esc_attr($model_key); ?>" <?php selected($settings['model'], $model_key); ?>>
                                    <?php echo esc_html($model_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the AI model to use for generating responses.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_tokens"><?php _e('Max Tokens', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="range" id="max_tokens" name="ai_chatbot_settings[max_tokens]" min="50" max="4000" value="<?php echo esc_attr($settings['max_tokens']); ?>" class="slider-input">
                        <span class="max-tokens-value"><?php echo esc_html($settings['max_tokens']); ?></span>
                        <p class="description"><?php _e('Maximum number of tokens for AI responses. Higher values allow longer responses but cost more.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="temperature"><?php _e('Temperature', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="range" id="temperature" name="ai_chatbot_settings[temperature]" min="0" max="2" step="0.1" value="<?php echo esc_attr($settings['temperature']); ?>" class="slider-input">
                        <span class="temperature-value"><?php echo esc_html($settings['temperature']); ?></span>
                        <p class="description"><?php _e('Controls randomness in responses. Lower values = more focused, higher values = more creative.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="system_prompt"><?php _e('System Prompt', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <textarea id="system_prompt" name="ai_chatbot_settings[system_prompt]" rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['system_prompt']); ?></textarea>
                        <p class="description"><?php _e('Instructions that define how the AI should behave and respond.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Display Settings Tab -->
        <div id="display-settings" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="widget_position"><?php _e('Widget Position', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <select id="widget_position" name="ai_chatbot_settings[widget_position]">
                            <?php foreach ($widget_positions as $position_key => $position_name): ?>
                                <option value="<?php echo esc_attr($position_key); ?>" <?php selected($settings['widget_position'], $position_key); ?>>
                                    <?php echo esc_html($position_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Choose where the chatbot widget appears on your website.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="widget_color"><?php _e('Widget Color', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="widget_color" name="ai_chatbot_settings[widget_color]" value="<?php echo esc_attr($settings['widget_color']); ?>" class="color-picker">
                        <p class="description"><?php _e('Choose the primary color for the chatbot widget.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="widget_size"><?php _e('Widget Size', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <select id="widget_size" name="ai_chatbot_settings[widget_size]">
                            <?php foreach ($widget_sizes as $size_key => $size_name): ?>
                                <option value="<?php echo esc_attr($size_key); ?>" <?php selected($settings['widget_size'], $size_key); ?>>
                                    <?php echo esc_html($size_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the size of the chatbot widget.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="animation_style"><?php _e('Animation Style', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <select id="animation_style" name="ai_chatbot_settings[animation_style]">
                            <?php foreach ($animation_styles as $animation_key => $animation_name): ?>
                                <option value="<?php echo esc_attr($animation_key); ?>" <?php selected($settings['animation_style'], $animation_key); ?>>
                                    <?php echo esc_html($animation_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Choose how the chatbot widget appears and disappears.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Show Typing Indicator', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="ai_chatbot_settings[show_typing_indicator]" value="1" <?php checked($settings['show_typing_indicator'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Show a typing indicator while the AI is generating a response.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Show Timestamps', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="ai_chatbot_settings[show_timestamp]" value="1" <?php checked($settings['show_timestamp'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Display timestamps for each message in the chat.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="show_on_pages"><?php _e('Show On Pages', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <select id="show_on_pages" name="ai_chatbot_settings[show_on_pages][]" multiple>
                            <option value="all" <?php selected(in_array('all', $settings['show_on_pages'])); ?>><?php _e('All Pages', 'ai-website-chatbot'); ?></option>
                            <option value="home" <?php selected(in_array('home', $settings['show_on_pages'])); ?>><?php _e('Home Page', 'ai-website-chatbot'); ?></option>
                            <option value="posts" <?php selected(in_array('posts', $settings['show_on_pages'])); ?>><?php _e('Blog Posts', 'ai-website-chatbot'); ?></option>
                            <option value="pages" <?php selected(in_array('pages', $settings['show_on_pages'])); ?>><?php _e('Static Pages', 'ai-website-chatbot'); ?></option>
                            <option value="shop" <?php selected(in_array('shop', $settings['show_on_pages'])); ?>><?php _e('Shop Pages', 'ai-website-chatbot'); ?></option>
                        </select>
                        <p class="description"><?php _e('Select which pages should display the chatbot widget.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
            </table>
            
            <!-- Widget Preview -->
            <div class="widget-preview-section">
                <h3><?php _e('Widget Preview', 'ai-website-chatbot'); ?></h3>
                <div class="widget-preview-container">
                    <div class="widget-preview pos-bottom-right size-medium">
                        <div class="widget-button">
                            <span class="chat-icon">💬</span>
                        </div>
                        <div class="widget-chat-window" style="display: none;">
                            <div class="chat-header">
                                <span class="chat-title"><?php _e('AI Assistant', 'ai-website-chatbot'); ?></span>
                                <button class="close-chat">×</button>
                            </div>
                            <div class="chat-messages">
                                <div class="message ai-message">
                                    <div class="message-content"><?php echo esc_html($settings['welcome_message']); ?></div>
                                </div>
                            </div>
                            <div class="chat-input">
                                <input type="text" placeholder="<?php _e('Type your message...', 'ai-website-chatbot'); ?>">
                                <button><?php _e('Send', 'ai-website-chatbot'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Advanced Settings Tab -->
        <div id="advanced-settings" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row" colspan="2">
                        <h3><?php _e('Rate Limiting', 'ai-website-chatbot'); ?></h3>
                    </th>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Enable Rate Limiting', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" id="rate_limiting_enabled" name="ai_chatbot_settings[rate_limiting][enabled]" value="1" <?php checked($settings['rate_limiting']['enabled'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Limit the number of requests per user to prevent abuse.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr class="rate-limiting-settings" <?php if (!$settings['rate_limiting']['enabled']) echo 'style="display: none;"'; ?>>
                    <th scope="row">
                        <label for="max_requests"><?php _e('Max Requests', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max_requests" name="ai_chatbot_settings[rate_limiting][max_requests]" value="<?php echo esc_attr($settings['rate_limiting']['max_requests']); ?>" min="1" max="1000" class="small-text">
                        <p class="description"><?php _e('Maximum number of requests allowed per time window.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr class="rate-limiting-settings" <?php if (!$settings['rate_limiting']['enabled']) echo 'style="display: none;"'; ?>>
                    <th scope="row">
                        <label for="time_window"><?php _e('Time Window (seconds)', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="time_window" name="ai_chatbot_settings[rate_limiting][time_window]" value="<?php echo esc_attr($settings['rate_limiting']['time_window']); ?>" min="60" max="86400" class="small-text">
                        <p class="description"><?php _e('Time window for rate limiting in seconds (default: 3600 = 1 hour).', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row" colspan="2">
                        <h3><?php _e('Content Synchronization', 'ai-website-chatbot'); ?></h3>
                    </th>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Enable Content Sync', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" id="content_sync_enabled" name="ai_chatbot_settings[content_sync][enabled]" value="1" <?php checked(empty($settings['content_sync']['enabled'] ?? false)); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Automatically sync your website content for better AI responses.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>

                <tr class="content-sync-settings" <?php if (empty($settings['content_sync']['enabled'] ?? false)) echo 'style="display: none;"'; ?>>
                    <th scope="row">
                        <label for="sync_post_types"><?php _e('Post Types to Sync', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <select id="sync_post_types" name="ai_chatbot_settings[content_sync][post_types][]" multiple>
                            <?php foreach ($post_types as $post_type_key => $post_type_name): ?>
                                <option value="<?php echo esc_attr($post_type_key); ?>" <?php selected(in_array($post_type_key, $settings['content_sync']['post_types'])); ?>>
                                    <?php echo esc_html($post_type_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select which post types should be synchronized with the AI.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr class="content-sync-settings" <?php if (empty($settings['content_sync']['enabled'] ?? false)) echo 'style="display: none;"'; ?>>
                    <th scope="row">
                        <label for="sync_frequency"><?php _e('Sync Frequency', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <select id="sync_frequency" name="ai_chatbot_settings[content_sync][sync_frequency]">
                            <option value="hourly" <?php selected($settings['content_sync']['sync_frequency'], 'hourly'); ?>><?php _e('Hourly', 'ai-website-chatbot'); ?></option>
                            <option value="daily" <?php selected($settings['content_sync']['sync_frequency'], 'daily'); ?>><?php _e('Daily', 'ai-website-chatbot'); ?></option>
                            <option value="weekly" <?php selected($settings['content_sync']['sync_frequency'], 'weekly'); ?>><?php _e('Weekly', 'ai-website-chatbot'); ?></option>
                        </select>
                        <button type="button" class="button sync-content-now"><?php _e('Sync Content', 'ai-website-chatbot'); ?></button>
                        <p class="description">
                            <?php _e('How often to automatically sync content.', 'ai-website-chatbot'); ?>
                            <?php 
                            $last_sync = get_option('ai_chatbot_last_content_sync');
                            if ($last_sync) {
                                echo '<br><em>' . sprintf(__('Last sync: %s', 'ai-website-chatbot'), human_time_diff($last_sync) . ' ago') . '</em>';
                            }
                            ?>
                        </p>
                    </td>
                </tr>

                <tr class="content-sync-settings">
                    <th scope="row"><?php _e('Website Data Training', 'ai-website-chatbot'); ?></th>
                    <td>
                        <button type="button" class="button button-primary train-website-data"><?php _e('Train from Website Data', 'ai-website-chatbot'); ?></button>
                        <p class="description"><?php _e('First sync content, then train AI from synced website data.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row" colspan="2">
                        <h3><?php _e('Performance', 'ai-website-chatbot'); ?></h3>
                    </th>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Debug Mode', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="ai_chatbot_settings[debug_mode]" value="1" <?php checked($settings['debug_mode'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Enable debug mode to log detailed information (for troubleshooting).', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Log Conversations', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="ai_chatbot_settings[log_conversations]" value="1" <?php checked($settings['log_conversations'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Store conversations in the database for analytics and training.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Cache Responses', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="ai_chatbot_settings[cache_responses]" value="1" <?php checked($settings['cache_responses'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <button type="button" class="button clear-cache"><?php _e('Clear Cache', 'ai-website-chatbot'); ?></button>
                        <p class="description"><?php _e('Cache AI responses to improve performance and reduce API costs.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="custom_css"><?php _e('Custom CSS', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <textarea id="custom_css" name="ai_chatbot_settings[custom_css]" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($settings['custom_css']); ?></textarea>
                        <p class="description"><?php _e('Add custom CSS to style the chatbot widget.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Privacy Settings Tab -->
        <div id="privacy-settings" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row" colspan="2">
                        <h3><?php _e('GDPR Compliance', 'ai-website-chatbot'); ?></h3>
                    </th>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Enable GDPR Features', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" id="gdpr_enabled" name="ai_chatbot_settings[gdpr][enabled]" value="1" <?php checked($settings['gdpr']['enabled'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Enable GDPR compliance features for data protection.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr class="gdpr-settings" <?php if (empty($settings['gdpr']['enabled'] ?? false)) echo 'style="display: none;"'; ?>>
                    <th scope="row">
                        <label for="data_retention_days"><?php _e('Data Retention (days)', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="range" id="data_retention_days" name="ai_chatbot_settings[gdpr][data_retention_days]" min="1" max="365" value="<?php echo esc_attr($settings['gdpr']['data_retention_days']); ?>" class="slider-input">
                        <span class="retention-days-value"><?php echo esc_html($settings['gdpr']['data_retention_days']); ?></span>
                        <p class="description"><?php _e('Number of days to keep conversation data before automatic deletion.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr class="gdpr-settings" <?php if (empty($settings['gdpr']['enabled'] ?? false)) echo 'style="display: none;"'; ?>>
                    <th scope="row">
                        <label for="privacy_policy_url"><?php _e('Privacy Policy URL', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="privacy_policy_url" name="ai_chatbot_settings[gdpr][privacy_policy_url]" value="<?php echo esc_attr($settings['gdpr']['privacy_policy_url']); ?>" class="regular-text">
                        <p class="description"><?php _e('Link to your privacy policy that will be shown to users.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr class="gdpr-settings" <?php if (empty($settings['gdpr']['enabled'] ?? false)) echo 'style="display: none;"'; ?>>
                    <th scope="row"><?php _e('Cookie Consent', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="ai_chatbot_settings[gdpr][cookie_consent]" value="1" <?php checked(empty($settings['gdpr']['cookie_consent'] ?? false)); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Require user consent before storing any data or cookies.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
                
                <tr class="gdpr-settings" <?php if (empty($settings['gdpr']['enabled'] ?? false)) echo 'style="display: none;"'; ?>>
                    <th scope="row"><?php _e('Anonymize Data', 'ai-website-chatbot'); ?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="ai_chatbot_settings[gdpr][anonymize_data]" value="1" <?php checked($settings['gdpr']['anonymize_data'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Automatically anonymize personal data in conversations.', 'ai-website-chatbot'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="submit-section">
            <?php submit_button(__('Save Settings', 'ai-website-chatbot'), 'primary', 'submit', false); ?>
            <button type="button" class="button reset-settings"><?php _e('Reset to Defaults', 'ai-website-chatbot'); ?></button>
            <button type="button" class="button export-settings"><?php _e('Export Settings', 'ai-website-chatbot'); ?></button>
            <input type="file" class="import-settings-file" accept=".json" style="display: none;">
            <button type="button" class="button" onclick="$('.import-settings-file').click();"><?php _e('Import Settings', 'ai-website-chatbot'); ?></button>
            <span class="save-indicator"></span>
        </div>
    </form>
</div>

<style>
.ai-chatbot-settings-wrap {
    max-width: 1200px;
}

.nav-tab-wrapper {
    margin-bottom: 20px;
}

.tab-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: none;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #0073aa;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.slider-input {
    width: 300px;
    margin-right: 10px;
}

.widget-preview-section {
    margin-top: 30px;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.widget-preview-container {
    position: relative;
    height: 300px;
    background: #e0e0e0;
    border-radius: 5px;
    overflow: hidden;
}

.widget-preview {
    position: absolute;
    z-index: 10;
}

.widget-preview.pos-bottom-right {
    bottom: 20px;
    right: 20px;
}

.widget-preview.pos-bottom-left {
    bottom: 20px;
    left: 20px;
}

.widget-preview.pos-top-right {
    top: 20px;
    right: 20px;
}

.widget-preview.pos-top-left {
    top: 20px;
    left: 20px;
}

.widget-preview.pos-center {
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.widget-button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #0073aa;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.widget-preview.size-small .widget-button {
    width: 45px;
    height: 45px;
    font-size: 18px;
}

.widget-preview.size-large .widget-button {
    width: 75px;
    height: 75px;
    font-size: 30px;
}

.submit-section {
    margin-top: 20px;
    padding: 20px 0;
    border-top: 1px solid #ddd;
}

.submit-section .button {
    margin-right: 10px;
}

.save-indicator {
    margin-left: 10px;
    color: #46b450;
    font-weight: bold;
}

.save-indicator.saved {
    opacity: 1;
    transition: opacity 0.3s ease;
}

@media (max-width: 768px) {
    .slider-input {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .submit-section .button {
        display: block;
        margin-bottom: 10px;
        margin-right: 0;
    }
}
</style>