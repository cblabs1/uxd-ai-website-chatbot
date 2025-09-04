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
$theme = isset($settings['theme']) ? $settings['theme'] : 'default';
$position = isset($settings['position']) ? $settings['position'] : 'bottom-right';
?>

<div id="ai-chatbot-widget" class="ai-chatbot-widget ai-chatbot-theme-<?php echo esc_attr($theme); ?> ai-chatbot-position-<?php echo esc_attr($position); ?>" data-position="<?php echo esc_attr($position); ?>">
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
                    <button class="ai-chatbot-minimize" id="ai-chatbot-minimize" title="<?php _e('Minimize', 'ai-website-chatbot'); ?>">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
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
                    <textarea 
                        class="ai-chatbot-input" 
                        id="ai-chatbot-input" 
                        placeholder="<?php esc_attr_e('Type your message...', 'ai-website-chatbot'); ?>"
                        rows="1"
                        maxlength="1000"
                    ></textarea>
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

## File 2: public/partials/chatbot-popup.php
<?php
/**
 * Chatbot Popup Template
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$width = isset($atts['width']) ? $atts['width'] : '350';
$height = isset($atts['height']) ? $atts['height'] : '500';
$theme = isset($atts['theme']) ? $atts['theme'] : 'default';
$position = isset($atts['position']) ? $atts['position'] : 'bottom-right';
?>

<div class="ai-chatbot-popup ai-chatbot-theme-<?php echo esc_attr($theme); ?>" 
     data-width="<?php echo esc_attr($width); ?>" 
     data-height="<?php echo esc_attr($height); ?>"
     data-position="<?php echo esc_attr($position); ?>">
    
    <?php include AI_CHATBOT_PLUGIN_PATH . 'public/partials/chatbot-widget.php'; ?>
    
</div>

## File 3: public/partials/chatbot-inline.php
<?php
/**
 * Chatbot Inline Template
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$width = isset($atts['width']) ? $atts['width'] : '100%';
$height = isset($atts['height']) ? $atts['height'] : '400px';
$theme = isset($atts['theme']) ? $atts['theme'] : 'default';
?>

<div class="ai-chatbot-inline ai-chatbot-theme-<?php echo esc_attr($theme); ?>" 
     style="width: <?php echo esc_attr($width); ?>; height: <?php echo esc_attr($height); ?>;">
    
    <div class="ai-chatbot-inline-container">
        <!-- Messages Area -->
        <div class="ai-chatbot-messages" id="ai-chatbot-inline-messages">
            <div class="ai-chatbot-message ai-chatbot-message-bot">
                <div class="ai-chatbot-message-content">
                    <div class="ai-chatbot-message-text">
                        <?php _e('Hello! How can I help you today?', 'ai-website-chatbot'); ?>
                    </div>
                    <div class="ai-chatbot-message-time">
                        <?php echo date('H:i'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div class="ai-chatbot-typing" id="ai-chatbot-inline-typing" style="display: none;">
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
            <form class="ai-chatbot-input-form" id="ai-chatbot-inline-input-form">
                <div class="ai-chatbot-input-container">
                    <textarea 
                        class="ai-chatbot-input" 
                        id="ai-chatbot-inline-input" 
                        placeholder="<?php esc_attr_e('Type your message...', 'ai-website-chatbot'); ?>"
                        rows="1"
                        maxlength="1000"
                    ></textarea>
                    <button type="submit" class="ai-chatbot-send-button" id="ai-chatbot-inline-send-button" disabled>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 10L2 18L5 10L2 2L18 10Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>