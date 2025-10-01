<?php
/**
 * Chatbot Widget Template
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$settings = get_option('ai_chatbot_settings', array());
$widget_position = isset($settings['widget_position']) ? $settings['widget_position'] : 'bottom-right';
$widget_color = isset($settings['widget_color']) ? $settings['widget_color'] : '#0073aa';
$welcome_message = isset($settings['welcome_message']) ? $settings['welcome_message'] : __('Hello! How can I help you today?', 'ai-website-chatbot');
$theme = 'dark';
?>

<div id="ai-chatbot-widget" 
    class="ai-chatbot-widget ai-chatbot-theme-<?php echo esc_attr($theme); ?> ai-chatbot-position-<?php echo esc_attr($widget_position); ?>" 
    data-position="<?php echo esc_attr($widget_position); ?>"
    style="--ai-chatbot-primary: <?php echo esc_attr($widget_color); ?>;">
    <!-- Chatbot Toggle Button -->
    <div class="ai-chatbot-toggle" id="ai-chatbot-toggle">
        <div class="ai-chatbot-toggle-icon">
            <svg class="ai-chatbot-icon-chat" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="currentColor"/>
                <path d="M7 9H17V11H7V9Z" fill="currentColor"/>
                <path d="M7 12H15V14H7V12Z" fill="currentColor"/>
            </svg>
            <svg class="ai-chatbot-icon-close" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12L19 6.41Z" fill="currentColor"/>
            </svg>
        </div>
        <div class="ai-chatbot-toggle-text">
            <span class="ai-chatbot-toggle-open-text"><?php _e('Chat with us', 'ai-website-chatbot'); ?></span>
            <span class="ai-chatbot-toggle-close-text"><?php _e('Close chat', 'ai-website-chatbot'); ?></span>
        </div>
    </div>

    <!-- Chatbot Container -->
    <div class="ai-chatbot-container" id="ai-chatbot-container">
        <!-- Header -->
        <div class="ai-chatbot-header">
            <div class="ai-chatbot-header-content">
                <div class="ai-chatbot-avatar">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="16" cy="16" r="16" fill="#6366f1"/>
                        <path d="M12 14C12.5523 14 13 13.5523 13 13C13 12.4477 12.5523 12 12 12C11.4477 12 11 12.4477 11 13C11 13.5523 11.4477 14 12 14Z" fill="white"/>
                        <path d="M20 14C20.5523 14 21 13.5523 21 13C21 12.4477 20.5523 12 20 12C19.4477 12 19 12.4477 19 13C19 13.5523 19.4477 14 20 14Z" fill="white"/>
                        <path d="M16 20C18.2091 20 20 18.2091 20 16H12C12 18.2091 13.7909 20 16 20Z" fill="white"/>
                    </svg>
                </div>
                <div class="ai-chatbot-header-info">
                    <h3 class="ai-chatbot-title"><?php echo esc_html($settings['chatbot_name'] ?? __('AI Assistant', 'ai-website-chatbot')); ?></h3>
                    <p class="ai-chatbot-subtitle"><?php echo esc_html($settings['welcome_message'] ?? __('How can I help you today?', 'ai-website-chatbot')); ?></p>
                </div>
                <div class="ai-chatbot-header-actions">
                    <button class="ai-chatbot-close" id="ai-chatbot-close" title="<?php _e('Close', 'ai-website-chatbot'); ?>">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="ai-chatbot-messages" id="ai-chatbot-messages">
            <div class="ai-chatbot-message ai-chatbot-message-bot">
                <div class="ai-chatbot-message-content">
                    <div class="ai-chatbot-message-text">
                        <?php echo esc_html($settings['welcome_message'] ?? __('Hello! How can I help you today?', 'ai-website-chatbot')); ?>
                    </div>
                    <div class="ai-chatbot-message-time">
                        <?php echo date('H:i'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div class="ai-chatbot-typing" id="ai-chatbot-typing" style="display: none;">
            <div class="ai-chatbot-typing-content">
                <div class="ai-chatbot-typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span class="ai-chatbot-typing-text"><?php _e('AI is typing...', 'ai-website-chatbot'); ?></span>
            </div>
        </div>

        <!-- Input Area -->
        <div class="ai-chatbot-input-area">
            <form class="ai-chatbot-input-form" id="ai-chatbot-input-form">
                <div class="ai-chatbot-input-container">
                    <?php 
                    // Check if voice input is enabled
                    $enable_voice = !empty($settings['voice_input_enabled']);
                    if ($enable_voice): 
                    ?>
                    <!-- Voice Input Button -->
                    <button type="button" class="ai-chatbot-voice-btn voice-btn" 
                            aria-label="<?php esc_attr_e('Voice input', 'ai-website-chatbot'); ?>"
                            title="<?php esc_attr_e('Click to use voice input', 'ai-website-chatbot'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" stroke="currentColor" stroke-width="2"/>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2" stroke="currentColor" stroke-width="2"/>
                            <line x1="12" y1="19" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                            <line x1="8" y1="23" x2="16" y2="23" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                    
                    <!-- Text Input -->
                    <textarea 
                        class="ai-chatbot-input" 
                        id="ai-chatbot-input" 
                        placeholder="<?php esc_attr_e('Type your message...', 'ai-website-chatbot'); ?>"
                        rows="1"
                        maxlength="1000"
                    ></textarea>
                    
                    <!-- Send Button -->
                    <button type="submit" class="ai-chatbot-send-button" id="ai-chatbot-send-button" disabled>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 10L2 18L5 10L2 2L18 10Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </form>
            <div class="ai-chatbot-footer">
                <span class="ai-chatbot-powered-by">
                    <?php _e('Powered by', 'ai-website-chatbot'); ?> <strong><?php echo esc_html(get_bloginfo('name')); ?></strong>
                </span>
            </div>
        </div>
    </div>
</div>