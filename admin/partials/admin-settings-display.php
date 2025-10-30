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
        <?php if (ai_chatbot_is_pro() && ai_chatbot_has_feature('audio_features')): ?>
        <!-- Show audio tab for Pro users -->
        <a href="#audio-settings" class="nav-tab"><?php _e('Audio Features', 'ai-website-chatbot'); ?></a>
        <?php else: ?>
            <!-- Show locked audio tab for free users -->
            <a href="#audio-settings" class="nav-tab nav-tab-locked" title="<?php _e('Pro Feature - Upgrade to unlock', 'ai-website-chatbot'); ?>">
                <?php _e('Audio Features', 'ai-website-chatbot'); ?> 🔒
            </a>
        <?php endif; ?>
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
                        <label for="enable_shortcodes_when_disabled"><?php _e('Enable Shortcodes When Disabled', 'ai-website-chatbot'); ?></label>
                    </th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" id="enable_shortcodes_when_disabled" name="ai_chatbot_settings[enable_shortcodes_when_disabled]" value="1" <?php checked($settings['enable_shortcodes_when_disabled'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php _e('Allow shortcodes and blocks to work even when the main chatbot widget is disabled.', 'ai-website-chatbot'); ?></p>
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

                <tr>
                    <th scope="row">
                        <label for="custom_branding_text">
                            <?php _e('Custom Branding Text', 'ai-website-chatbot'); ?>
                            <span class="pro-badge">PRO</span>
                        </label>
                    </th>
                    <td>
                        <?php 
                        $has_white_label = function_exists('ai_chatbot_has_feature') && ai_chatbot_has_feature('white_label');
                        $custom_branding = isset($settings['custom_branding_text']) ? $settings['custom_branding_text'] : '';
                        ?>
                        
                        <div class="custom-branding-wrapper <?php echo !$has_white_label ? 'pro-feature-locked' : ''; ?>">
                            <input 
                                type="text" 
                                id="custom_branding_text" 
                                name="ai_chatbot_settings[custom_branding_text]" 
                                value="<?php echo esc_attr($custom_branding); ?>"
                                class="regular-text"
                                placeholder="<?php esc_attr_e('e.g., Powered by Your Company', 'ai-website-chatbot'); ?>"
                                <?php echo !$has_white_label ? 'disabled readonly' : ''; ?>
                            />
                            
                            <?php if (!$has_white_label): ?>
                                <div class="pro-feature-overlay">
                                    <span class="dashicons dashicons-lock"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <p class="description">
                            <?php if ($has_white_label): ?>
                                <?php _e('Custom text to display instead of "Powered by AI Website Chatbot". Leave empty to hide branding completely.', 'ai-website-chatbot'); ?>
                            <?php else: ?>
                                <?php _e('Replace the default "Powered by" text with your own branding.', 'ai-website-chatbot'); ?>
                                <br>
                                <strong><?php _e('This is a Pro feature.', 'ai-website-chatbot'); ?></strong>
                                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-license'); ?>" class="button button-small">
                                    <?php _e('Upgrade to Pro', 'ai-website-chatbot'); ?>
                                </a>
                            <?php endif; ?>
                        </p>
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
            <!-- <div class="widget-preview-section">
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
            </div> -->
        </div>

        <!-- Audio Features Settings Tab -->
        <div id="audio-settings" class="tab-content" style="display:none;">

            <?php if (!ai_chatbot_is_pro() || !ai_chatbot_has_feature('audio_features')): ?>
            
                <!-- Pro Feature Locked Message -->
                <div class="ai-chatbot-pro-feature-locked">
                    <div class="pro-feature-lock-icon">
                        <span class="dashicons dashicons-lock" style="font-size: 80px; color: #ddd;"></span>
                    </div>
                    
                    <h2><?php _e('🔒 Audio Features (Pro)', 'ai-website-chatbot'); ?></h2>
                    
                    <p class="lead-text">
                        <?php _e('Unlock powerful voice and audio capabilities for your chatbot', 'ai-website-chatbot'); ?>
                    </p>
                    
                    <div class="pro-features-grid">
                        <div class="pro-feature-card">
                            <span class="dashicons dashicons-microphone"></span>
                            <h3><?php _e('Voice Input', 'ai-website-chatbot'); ?></h3>
                            <p><?php _e('Let users speak instead of type with advanced speech-to-text', 'ai-website-chatbot'); ?></p>
                        </div>
                        
                        <div class="pro-feature-card">
                            <span class="dashicons dashicons-controls-volumeon"></span>
                            <h3><?php _e('Text-to-Speech', 'ai-website-chatbot'); ?></h3>
                            <p><?php _e('AI responses spoken aloud with natural-sounding voices', 'ai-website-chatbot'); ?></p>
                        </div>
                        
                        <div class="pro-feature-card">
                            <span class="dashicons dashicons-format-audio"></span>
                            <h3><?php _e('Audio Mode', 'ai-website-chatbot'); ?></h3>
                            <p><?php _e('Hands-free conversation mode for complete voice interaction', 'ai-website-chatbot'); ?></p>
                        </div>
                        <div class="pro-feature-card">
                            <span class="dashicons dashicons-translation"></span>
                            <h3><?php _e('Multiple Voices', 'ai-website-chatbot'); ?></h3>
                            <p><?php _e('Choose from various male and   female voices to customize your chatbot\'s audio responses', 'ai-website-chatbot'); ?></p>
                        </div>
                    </div>
                    
                    <div class="pro-upgrade-actions">
                        <a href="<?php echo esc_url('https://uxdesignexperts.com/ai-chatbot'); ?>"
                        class="button button-primary button-hero" 
                        target="_blank">
                            <?php _e('Upgrade to Pro', 'ai-website-chatbot'); ?>
                        </a>
                    </div>
                    
                    <p class="pro-feature-note">
                        <?php _e('All Pro features include priority support and regular updates', 'ai-website-chatbot'); ?>
                    </p>
                </div>
            
            <?php else: ?>
                <h2><?php _e('Audio Features', 'ai-website-chatbot'); ?></h2>
                <p class="description">
                    <?php _e('Configure voice input and text-to-speech features for your chatbot.', 'ai-website-chatbot'); ?>
                </p>

                <table class="form-table">
                    <!-- Enable Audio Features -->
                    <tr>
                        <th scope="row">
                            <label for="audio_features_enabled">
                                <?php _e('Enable Audio Features', 'ai-website-chatbot'); ?>
                            </label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="audio_features_enabled" 
                                    name="ai_chatbot_settings[audio_features][enabled]" 
                                    value="1" 
                                    <?php checked($settings['audio_features']['enabled'] ?? false); ?>>
                                <span class="slider"></span>
                            </label>
                            <p class="description">
                                <?php _e('Enable voice input and text-to-speech features.', 'ai-website-chatbot'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Voice Input Settings -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Voice Input (Speech-to-Text)', 'ai-website-chatbot'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="voice_input_enabled"><?php _e('Enable Voice Input', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="voice_input_enabled" 
                                    name="ai_chatbot_settings[audio_features][voice_input_enabled]" 
                                    value="1" 
                                    <?php checked($settings['audio_features']['voice_input_enabled'] ?? false); ?>>
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="voice_language"><?php _e('Voice Language', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="voice_language" name="ai_chatbot_settings[audio_features][voice_language]">
                                <?php
                                $voice_languages = array(
                                    'en-US' => __('English (US)', 'ai-website-chatbot'),
                                    'en-GB' => __('English (UK)', 'ai-website-chatbot'),
                                    'es-ES' => __('Spanish (Spain)', 'ai-website-chatbot'),
                                    'fr-FR' => __('French', 'ai-website-chatbot'),
                                    'de-DE' => __('German', 'ai-website-chatbot'),
                                    'it-IT' => __('Italian', 'ai-website-chatbot'),
                                    'pt-BR' => __('Portuguese (Brazil)', 'ai-website-chatbot'),
                                    'ja-JP' => __('Japanese', 'ai-website-chatbot'),
                                    'zh-CN' => __('Chinese (Simplified)', 'ai-website-chatbot'),
                                );
                                $current_language = $settings['audio_features']['voice_language'] ?? 'en-US';
                                foreach ($voice_languages as $code => $name) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($code),
                                        selected($current_language, $code, false),
                                        esc_html($name)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="voice_continuous"><?php _e('Continuous Listening', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="voice_continuous" 
                                    name="ai_chatbot_settings[audio_features][voice_continuous]" 
                                    value="1" 
                                    <?php checked($settings['audio_features']['voice_continuous'] ?? false); ?>>
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="voice_interim_results"><?php _e('Show Interim Results', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="voice_interim_results" 
                                    name="ai_chatbot_settings[audio_features][voice_interim_results]" 
                                    value="1" 
                                    <?php checked($settings['audio_features']['voice_interim_results'] ?? true); ?>>
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="voice_auto_send"><?php _e('Auto-Send Message', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="voice_auto_send" 
                                    name="ai_chatbot_settings[audio_features][voice_auto_send]" 
                                    value="1" 
                                    <?php checked($settings['audio_features']['voice_auto_send'] ?? false); ?>>
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>

                    <!-- Text-to-Speech Settings -->
                    <tr>
                        <th colspan="2">
                            <h3 style="margin-top: 30px;"><?php _e('Text-to-Speech (TTS)', 'ai-website-chatbot'); ?></h3>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="tts_enabled"><?php _e('Enable Text-to-Speech', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="tts_enabled" 
                                    name="ai_chatbot_settings[audio_features][tts_enabled]" 
                                    value="1" 
                                    <?php checked($settings['audio_features']['tts_enabled'] ?? false); ?>>
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="tts_auto_play"><?php _e('Auto-Play Responses', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="tts_auto_play" 
                                    name="ai_chatbot_settings[audio_features][tts_auto_play]" 
                                    value="1" 
                                    <?php checked($settings['audio_features']['tts_auto_play'] ?? false); ?>>
                                <span class="slider"></span>
                            </label>
                            <p class="description">
                                <?php _e('Only plays in audio conversation mode.', 'ai-website-chatbot'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="tts_rate"><?php _e('Speech Rate', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="range" id="tts_rate" 
                                name="ai_chatbot_settings[audio_features][tts_rate]" 
                                min="0.5" max="2" step="0.1" 
                                value="<?php echo esc_attr($settings['audio_features']['tts_rate'] ?? 1.0); ?>">
                            <span class="range-value"><?php echo esc_html($settings['audio_features']['tts_rate'] ?? 1.0); ?>x</span>
                            <script>
                                document.getElementById('tts_rate').addEventListener('input', function(e) {
                                    e.target.nextElementSibling.textContent = e.target.value + 'x';
                                });
                            </script>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="tts_pitch"><?php _e('Speech Pitch', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="range" id="tts_pitch" 
                                name="ai_chatbot_settings[audio_features][tts_pitch]" 
                                min="0.5" max="2" step="0.1" 
                                value="<?php echo esc_attr($settings['audio_features']['tts_pitch'] ?? 1.0); ?>">
                            <span class="range-value"><?php echo esc_html($settings['audio_features']['tts_pitch'] ?? 1.0); ?></span>
                            <script>
                                document.getElementById('tts_pitch').addEventListener('input', function(e) {
                                    e.target.nextElementSibling.textContent = e.target.value;
                                });
                            </script>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="tts_volume"><?php _e('Speech Volume', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="range" id="tts_volume" 
                                name="ai_chatbot_settings[audio_features][tts_volume]" 
                                min="0" max="1" step="0.1" 
                                value="<?php echo esc_attr($settings['audio_features']['tts_volume'] ?? 0.8); ?>">
                            <span class="range-value"><?php echo esc_html(round(($settings['audio_features']['tts_volume'] ?? 0.8) * 100)); ?>%</span>
                            <script>
                                document.getElementById('tts_volume').addEventListener('input', function(e) {
                                    e.target.nextElementSibling.textContent = Math.round(e.target.value * 100) + '%';
                                });
                            </script>
                        </td>
                    </tr>

                    <!-- ADD VOICE SELECTION SETTINGS HERE -->
                    <tr>
                        <th scope="row">
                            <label for="voice_selection_enabled"><?php _e('Enable Voice Selection', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="voice_selection_enabled" 
                                    name="ai_chatbot_settings[audio_features][voice_selection_enabled]" 
                                    value="1" 
                                    <?php checked($settings['audio_features']['voice_selection_enabled'] ?? false); ?>>
                                <span class="slider"></span>
                            </label>
                            <p class="description"><?php _e('Allow users to choose voice options.', 'ai-website-chatbot'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="voice_gender"><?php _e('Default Voice Gender', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="voice_gender" name="ai_chatbot_settings[audio_features][voice_gender]">
                                <option value="female" <?php selected($settings['audio_features']['voice_gender'] ?? 'female', 'female'); ?>><?php _e('Female Voice', 'ai-website-chatbot'); ?></option>
                                <option value="male" <?php selected($settings['audio_features']['voice_gender'] ?? 'female', 'male'); ?>><?php _e('Male Voice', 'ai-website-chatbot'); ?></option>
                                <option value="neutral" <?php selected($settings['audio_features']['voice_gender'] ?? 'female', 'neutral'); ?>><?php _e('Neutral Voice', 'ai-website-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose the default voice gender for text-to-speech.', 'ai-website-chatbot'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="voice_language"><?php _e('Voice Language', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="voice_language" name="ai_chatbot_settings[audio_features][voice_language]">
                                <option value="en-US" <?php selected($settings['audio_features']['voice_language'] ?? 'en-US', 'en-US'); ?>><?php _e('English (US)', 'ai-website-chatbot'); ?></option>
                                <option value="en-GB" <?php selected($settings['audio_features']['voice_language'] ?? 'en-US', 'en-GB'); ?>><?php _e('English (UK)', 'ai-website-chatbot'); ?></option>
                                <option value="en-IN" <?php selected($settings['audio_features']['voice_language'] ?? 'en-US', 'en-IN'); ?>><?php _e('English (India)', 'ai-website-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php _e('Select the language for voice synthesis.', 'ai-website-chatbot'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="specific_voice"><?php _e('Specific Voice', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="specific_voice" name="ai_chatbot_settings[audio_features][specific_voice]" data-saved-value="<?php echo esc_attr($settings['audio_features']['specific_voice'] ?? ''); ?>">
                                <option value=""><?php _e('Auto-select best voice', 'ai-website-chatbot'); ?></option>
                            </select>
                            <button type="button" id="test-voice-btn" class="button" style="margin-left: 10px;">
                                <?php _e('Test Voice', 'ai-website-chatbot'); ?>
                            </button>
                            <p class="description"><?php _e('Choose a specific voice or let the system auto-select based on gender and language.', 'ai-website-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="voice_personality"><?php _e('Voice Personality', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="voice_personality" name="ai_chatbot_settings[audio_features][voice_personality]">
                                <option value="friendly" <?php selected($settings['audio_features']['voice_personality'] ?? 'friendly', 'friendly'); ?>><?php _e('Friendly', 'ai-website-chatbot'); ?></option>
                                <option value="professional" <?php selected($settings['audio_features']['voice_personality'] ?? 'friendly', 'professional'); ?>><?php _e('Professional', 'ai-website-chatbot'); ?></option>
                                <option value="warm" <?php selected($settings['audio_features']['voice_personality'] ?? 'friendly', 'warm'); ?>><?php _e('Warm', 'ai-website-chatbot'); ?></option>
                                <option value="authoritative" <?php selected($settings['audio_features']['voice_personality'] ?? 'friendly', 'authoritative'); ?>><?php _e('Authoritative', 'ai-website-chatbot'); ?></option>
                                <option value="cheerful" <?php selected($settings['audio_features']['voice_personality'] ?? 'friendly', 'cheerful'); ?>><?php _e('Cheerful', 'ai-website-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose the voice personality style.', 'ai-website-chatbot'); ?></p>
                        </td>
                    </tr>

                    <!-- Audio Mode Settings -->
                    <tr>
                        <th colspan="2">
                            <h3 style="margin-top: 30px;"><?php _e('Audio Conversation Mode', 'ai-website-chatbot'); ?></h3>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="audio_mode_enabled"><?php _e('Enable Audio Mode', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="audio_mode_enabled" 
                                    name="ai_chatbot_settings[audio_features][audio_mode_enabled]" 
                                    value="1" 
                                    <?php checked($settings['audio_features']['audio_mode_enabled'] ?? false); ?>>
                                <span class="slider"></span>
                            </label>
                            <p class="description">
                                <?php _e('Enable modal-based audio conversation mode.', 'ai-website-chatbot'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="audio_mode_silence_timeout"><?php _e('Silence Timeout (seconds)', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="audio_mode_silence_timeout" 
                                name="ai_chatbot_settings[audio_features][audio_mode_silence_timeout]" 
                                value="<?php echo esc_attr($settings['audio_features']['audio_mode_silence_timeout'] ?? 30); ?>" 
                                min="10" max="120" class="small-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="audio_mode_max_time"><?php _e('Max Conversation Time (seconds)', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="audio_mode_max_time" 
                                name="ai_chatbot_settings[audio_features][audio_mode_max_time]" 
                                value="<?php echo esc_attr($settings['audio_features']['audio_mode_max_time'] ?? 300); ?>" 
                                min="60" max="1800" class="small-text">
                        </td>
                    </tr>

                    <!-- Test Voice -->
                    <tr>
                        <th scope="row">
                            <label><?php _e('Test Voice Settings', 'ai-website-chatbot'); ?></label>
                        </th>
                        <td>
                            <button type="button" class="button" id="test-tts-button">
                                <?php _e('Test Voice', 'ai-website-chatbot'); ?>
                            </button>
                            <script>
                                document.getElementById('test-tts-button').addEventListener('click', function() {
                                    if ('speechSynthesis' in window) {
                                        const text = 'Hello! This is a test of the text-to-speech feature.';
                                        const utterance = new SpeechSynthesisUtterance(text);
                                        utterance.rate = parseFloat(document.getElementById('tts_rate').value);
                                        utterance.pitch = parseFloat(document.getElementById('tts_pitch').value);
                                        utterance.volume = parseFloat(document.getElementById('tts_volume').value);
                                        utterance.lang = document.getElementById('voice_language').value;
                                        window.speechSynthesis.speak(utterance);
                                    } else {
                                        alert('Text-to-speech is not supported in your browser.');
                                    }
                                });
                            </script>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
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
                            <input type="checkbox" id="content_sync_enabled" name="ai_chatbot_settings[content_sync][enabled]" value="1" <?php checked($settings['content_sync']['enabled'] ?? false); ?>>
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

                <tr>
                    <th scope="row">
                        <label for="keep_data_on_uninstall">
                            <?php _e('Keep Data on Uninstall', 'ai-website-chatbot'); ?>
                        </label>
                    </th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" 
                                id="keep_data_on_uninstall" 
                                name="ai_chatbot_settings[ai_chatbot_keep_data_on_uninstall]" 
                                value="1" <?php checked($settings['ai_chatbot_keep_data_on_uninstall'] ?? false); ?>
                               >
                            <span class="slider"></span>
                        </label>
                        <p class="description">
                            <?php _e('If enabled, all plugin data (conversations, settings, training data) will be preserved when you uninstall the plugin.', 'ai-website-chatbot'); ?>
                        </p>
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
                            <input type="checkbox" name="ai_chatbot_settings[gdpr][cookie_consent]" value="1" <?php checked($settings['gdpr']['cookie_consent'] ?? false); ?>>
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
            <!-- <button type="button" class="button export-settings"><?php _e('Export Settings', 'ai-website-chatbot'); ?></button>
            <input type="file" class="import-settings-file" accept=".json" style="display: none;">
            <button type="button" class="button" onclick="$('.import-settings-file').click();"><?php _e('Import Settings', 'ai-website-chatbot'); ?></button> -->
            <span class="save-indicator"></span>
        </div>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // IMPROVED: More flexible gender detection
    function detectVoiceGender(voiceName) {
        const name = voiceName.toLowerCase();
        
        // Explicit gender in name
        if (name.includes('female') && !name.includes('male female')) return 'female';
        if (name.includes('male') && !name.includes('female')) return 'male';
        
        // Known male names
        const maleNames = ['david', 'mark', 'daniel', 'george', 'james', 'oliver', 
                          'thomas', 'william', 'ryan', 'christopher', 'andrew',
                          'rishi', 'amit', 'arun', 'rajan', 'vivek'];
        if (maleNames.some(n => name.includes(n))) return 'male';
        
        // Known female names
        const femaleNames = ['zira', 'susan', 'helen', 'samantha', 'karen', 'victoria', 
                            'kate', 'hazel', 'fiona', 'moira', 'tessa', 'sarah',
                            'priya', 'swara', 'shruti', 'kavya', 'natasha', 'allison',
                            'ava', 'emma', 'aria', 'jenny', 'michelle', 'emily', 'chloe'];
        if (femaleNames.some(n => name.includes(n))) return 'female';
        
        // Heuristic: If no clear indicator, return null (unknown)
        return null;
    }
    
    function loadAvailableVoices() {
        if ('speechSynthesis' in window) {
            let voicesLoadedOnce = false;
            
            function updateVoices() {
                const voices = speechSynthesis.getVoices();
                
                if (voices.length === 0 || voicesLoadedOnce) {
                    return;
                }
                
                voicesLoadedOnce = true;
                
                const $voiceSelect = $('#specific_voice');
                const savedVoice = $voiceSelect.attr('data-saved-value');
                const selectedGender = $('#voice_gender').val();
                const selectedLanguage = $('#voice_language').val();
                
                console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                console.log('🎙️ Admin Voice Filtering');
                console.log('Total voices:', voices.length);
                console.log('Selected gender:', selectedGender);
                console.log('Selected language:', selectedLanguage);
                
                // Clear options
                $voiceSelect.find('option:not(:first)').remove();
                
                // First: Filter by language
                const languageMatches = voices.filter(voice => {
                    return voice.lang.startsWith(selectedLanguage.split('-')[0]) || 
                           voice.lang.includes(selectedLanguage);
                });
                
                console.log('Language matches:', languageMatches.length);
                
                // Analyze available genders
                const genderAnalysis = {
                    male: [],
                    female: [],
                    unknown: []
                };
                
                languageMatches.forEach(voice => {
                    const gender = detectVoiceGender(voice.name);
                    if (gender === 'male') {
                        genderAnalysis.male.push(voice);
                    } else if (gender === 'female') {
                        genderAnalysis.female.push(voice);
                    } else {
                        genderAnalysis.unknown.push(voice);
                    }
                });
                
                console.log('Gender breakdown:');
                console.log('  Male:', genderAnalysis.male.length);
                console.log('  Female:', genderAnalysis.female.length);
                console.log('  Unknown:', genderAnalysis.unknown.length);
                
                // Select voices based on gender preference
                let filteredVoices = [];
                
                if (selectedGender === 'male') {
                    filteredVoices = genderAnalysis.male;
                    // If not enough male voices, include unknowns
                    if (filteredVoices.length < 5) {
                        console.log('⚠️ Only', filteredVoices.length, 'male voices found, including unknowns');
                        filteredVoices = [...filteredVoices, ...genderAnalysis.unknown];
                    }
                } else if (selectedGender === 'female') {
                    filteredVoices = genderAnalysis.female;
                    // If not enough female voices, include unknowns
                    if (filteredVoices.length < 5) {
                        console.log('⚠️ Only', filteredVoices.length, 'female voices found, including unknowns');
                        filteredVoices = [...filteredVoices, ...genderAnalysis.unknown];
                    }
                } else if (selectedGender === 'neutral') {
                    // For neutral, prefer unknowns but show all
                    filteredVoices = [...genderAnalysis.unknown, ...genderAnalysis.male, ...genderAnalysis.female];
                }
                
                console.log('✅ Final filtered:', filteredVoices.length, 'voices');
                console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                
                // Add voices to dropdown
                filteredVoices.forEach(voice => {
                    const gender = detectVoiceGender(voice.name);
                    const genderIcon = gender === 'male' ? '♂️' : gender === 'female' ? '♀️' : '⚪';
                    
                    const option = $('<option></option>')
                        .attr('value', voice.name)
                        .text(genderIcon + ' ' + voice.name + ' (' + voice.lang + ')');
                    
                    if (voice.name === savedVoice) {
                        option.attr('selected', 'selected');
                        console.log('✓ Restored saved voice:', voice.name);
                    }
                    
                    $voiceSelect.append(option);
                });
                
                if (filteredVoices.length === 0) {
                    const warningOption = $('<option></option>')
                        .attr('value', '')
                        .attr('disabled', true)
                        .text('⚠️ No voices available for ' + selectedLanguage);
                    $voiceSelect.append(warningOption);
                }
            }
            
            updateVoices();
            
            if (speechSynthesis.onvoiceschanged !== undefined) {
                speechSynthesis.onvoiceschanged = updateVoices;
            }
            
            setTimeout(updateVoices, 100);
            setTimeout(updateVoices, 500);
        }
    }
    
    loadAvailableVoices();
    
    // Reload on gender/language change
    $('#voice_gender, #voice_language').on('change', function() {
        console.log('🔄 Reloading voices...');
        $('#specific_voice').attr('data-saved-value', '');
        
        const $voiceSelect = $('#specific_voice');
        $voiceSelect.find('option:not(:first)').remove();
        
        const voices = speechSynthesis.getVoices();
        const selectedGender = $('#voice_gender').val();
        const selectedLanguage = $('#voice_language').val();
        
        // Filter by language first
        const languageMatches = voices.filter(voice => {
            return voice.lang.startsWith(selectedLanguage.split('-')[0]) || 
                   voice.lang.includes(selectedLanguage);
        });
        
        // Then by gender
        let filteredVoices = [];
        
        if (selectedGender === 'male') {
            const males = languageMatches.filter(v => {
                const gender = detectVoiceGender(v.name);
                return gender === 'male' || gender === null; // Include unknowns
            });
            filteredVoices = males;
        } else if (selectedGender === 'female') {
            const females = languageMatches.filter(v => {
                const gender = detectVoiceGender(v.name);
                return gender === 'female' || gender === null; // Include unknowns
            });
            filteredVoices = females;
        } else {
            filteredVoices = languageMatches;
        }
        
        console.log('Found', filteredVoices.length, 'voices for', selectedGender);
        
        filteredVoices.forEach(voice => {
            const gender = detectVoiceGender(voice.name);
            const genderIcon = gender === 'male' ? '♂️' : gender === 'female' ? '♀️' : '⚪';
            
            const option = $('<option></option>')
                .attr('value', voice.name)
                .text(genderIcon + ' ' + voice.name + ' (' + voice.lang + ')');
            $voiceSelect.append(option);
        });
    });
    
    // Test voice
    $('#test-voice-btn').on('click', function() {
        const selectedVoice = $('#specific_voice').val();
        const testText = 'Hello! This is a test of the selected voice. How do I sound?';
        
        if ('speechSynthesis' in window) {
            speechSynthesis.cancel();
            
            const utterance = new SpeechSynthesisUtterance(testText);
            
            if (selectedVoice) {
                const voices = speechSynthesis.getVoices();
                const voice = voices.find(v => v.name === selectedVoice);
                if (voice) {
                    utterance.voice = voice;
                    console.log('Testing voice:', voice.name);
                }
            }
            
            utterance.rate = parseFloat($('#tts_rate').val() || 1.0);
            utterance.pitch = parseFloat($('#tts_pitch').val() || 1.0);
            utterance.volume = parseFloat($('#tts_volume').val() || 0.8);
            
            speechSynthesis.speak(utterance);
            
            const $btn = $(this);
            const originalText = $btn.text();
            $btn.text('<?php _e('Speaking...', 'ai-website-chatbot'); ?>').prop('disabled', true);
            
            utterance.onend = function() {
                $btn.text(originalText).prop('disabled', false);
            };
        } else {
            alert('<?php _e('Speech synthesis is not supported in this browser.', 'ai-website-chatbot'); ?>');
        }
    });
});
</script>

<style>

/* Locked Tab Styling */
.nav-tab-locked {
    opacity: 0.6;
    cursor: not-allowed !important;
    position: relative;
}

.nav-tab-locked:hover {
    background-color: #f0f0f1 !important;
    border-bottom-color: transparent !important;
}

/* Pro Feature Locked Page */
.ai-chatbot-pro-feature-locked {
    text-align: center;
    padding: 60px 40px;
    max-width: 900px;
    margin: 0 auto;
}

.pro-feature-lock-icon {
    margin-bottom: 30px;
}

.ai-chatbot-pro-feature-locked h2 {
    font-size: 36px;
    margin-bottom: 15px;
    color: #1e293b;
}

.ai-chatbot-pro-feature-locked .lead-text {
    font-size: 18px;
    color: #64748b;
    margin-bottom: 50px;
}

.pro-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 25px;
    margin: 40px 0;
}

.pro-feature-card {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 30px 20px;
    transition: all 0.3s ease;
}

.pro-feature-card:hover {
    border-color: #667eea;
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.1);
}

.pro-feature-card .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #667eea;
    margin-bottom: 15px;
}

.pro-feature-card h3 {
    font-size: 18px;
    margin: 0 0 10px 0;
    color: #1e293b;
}

.pro-feature-card p {
    font-size: 14px;
    color: #64748b;
    margin: 0;
    line-height: 1.6;
}

.pro-upgrade-actions {
    margin: 40px 0 20px 0;
}

.pro-upgrade-actions .button {
    margin: 0 10px;
}

.pro-feature-note {
    font-size: 14px;
    color: #64748b;
    margin-top: 30px;
    font-style: italic;
}

.pro-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: bold;
    margin-left: 8px;
    vertical-align: middle;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.custom-branding-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 400px;
}

.custom-branding-wrapper.pro-feature-locked {
    opacity: 0.6;
}

.custom-branding-wrapper.pro-feature-locked input {
    background-color: #f5f5f5;
    cursor: not-allowed;
    color: #999;
}

.pro-feature-overlay {
    position: absolute;
    top: 0;
    right: 10px;
    bottom: 0;
    display: flex;
    align-items: center;
    pointer-events: none;
}

.pro-feature-overlay .dashicons {
    font-size: 20px;
    color: #999;
}

.custom-branding-wrapper .button-small {
    margin-left: 10px;
    vertical-align: middle;
}

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