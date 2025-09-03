<?php
/**
 * The main chatbot widget template
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$position_class = 'position-' . sanitize_html_class(get_option('ai_chatbot_position', 'bottom-right'));
$widget_title = esc_html(get_option('ai_chatbot_widget_title', __('AI Assistant', 'ai-website-chatbot')));
$welcome_message = get_option('ai_chatbot_welcome_message', __('Hello! How can I help you today?', 'ai-website-chatbot'));
$show_powered_by = get_option('ai_chatbot_show_powered_by', true);
$primary_color = get_option('ai_chatbot_primary_color', '#0073aa');
$theme = get_option('ai_chatbot_theme', 'modern');
?>

<div id="ai-chatbot-widget" 
     class="ai-chatbot-widget <?php echo esc_attr($position_class); ?> theme-<?php echo esc_attr($theme); ?>" 
     data-chatbot-config="<?php echo esc_attr(wp_json_encode(aiChatbotConfig)); ?>"
     style="--chatbot-primary-color: <?php echo esc_attr($primary_color); ?>;">
     
    <!-- Chatbot Toggle Button -->
    <button id="ai-chatbot-toggle" class="ai-chatbot-toggle" aria-label="<?php esc_attr_e('Open AI Chat', 'ai-website-chatbot'); ?>">
        <span class="toggle-icon toggle-icon-chat">💬</span>
        <span class="toggle-icon toggle-icon-close" style="display: none;">✕</span>
        <div class="notification-badge" style="display: none;"></div>
    </button>

    <!-- Chatbot Container -->
    <div id="ai-chatbot-container" class="ai-chatbot-container" style="display: none;">
        <!-- Header -->
        <div class="ai-chatbot-header">
            <div class="header-content">
                <div class="bot-info">
                    <div class="bot-avatar">🤖</div>
                    <div class="bot-details">
                        <h3 class="bot-name"><?php echo esc_html($widget_title); ?></h3>
                        <div class="bot-status">
                            <span class="status-indicator online"></span>
                            <span class="status-text"><?php esc_html_e('Online', 'ai-website-chatbot'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="header-controls">
                    <button class="control-btn minimize-btn" aria-label="<?php esc_attr_e('Minimize', 'ai-website-chatbot'); ?>">
                        <span class="dashicons dashicons-minus"></span>
                    </button>
                    <button class="control-btn close-btn" aria-label="<?php esc_attr_e('Close', 'ai-website-chatbot'); ?>">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="ai-chatbot-messages" id="ai-chatbot-messages">
            <div class="messages-container">
                <!-- Welcome Message -->
                <?php if (!empty($welcome_message)): ?>
                <div class="ai-chatbot-message bot-message">
                    <div class="message-avatar">🤖</div>
                    <div class="message-content">
                        <div class="message-bubble">
                            <?php echo wp_kses_post($welcome_message); ?>
                        </div>
                        <div class="message-time"><?php echo esc_html(current_time('g:i A')); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Suggested Responses (if enabled) -->
                <?php if (get_option('ai_chatbot_show_suggested_responses', false)): ?>
                <div class="suggested-responses" id="ai-suggested-responses">
                    <div class="suggestions-label"><?php esc_html_e('Quick questions:', 'ai-website-chatbot'); ?></div>
                    <div class="suggestions-list">
                        <!-- Suggestions will be loaded via AJAX -->
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Typing Indicator -->
            <div class="ai-chatbot-typing" id="ai-chatbot-typing" style="display: none;">
                <div class="message-avatar">🤖</div>
                <div class="typing-content">
                    <div class="typing-bubble">
                        <div class="typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="ai-chatbot-input-area">
            <form id="ai-chatbot-form" class="chatbot-form">
                <div class="input-container">
                    <div class="input-wrapper">
                        <textarea id="ai-chatbot-input" 
                                  class="chatbot-input" 
                                  placeholder="<?php esc_attr_e('Type your message...', 'ai-website-chatbot'); ?>"
                                  rows="1"
                                  maxlength="<?php echo esc_attr(get_option('ai_chatbot_max_message_length', 1000)); ?>"></textarea>
                        
                        <!-- File Upload Button (if enabled) -->
                        <?php if (get_option('ai_chatbot_enable_file_uploads', false)): ?>
                        <button type="button" class="file-upload-btn" aria-label="<?php esc_attr_e('Upload file', 'ai-website-chatbot'); ?>">
                            <span class="dashicons dashicons-paperclip"></span>
                        </button>
                        <input type="file" id="ai-chatbot-file-input" class="file-input" style="display: none;" accept="<?php echo esc_attr(implode(',', get_option('ai_chatbot_allowed_file_types', array('.txt', '.pdf')))); ?>">
                        <?php endif; ?>
                        
                        <button type="submit" id="ai-chatbot-send" class="send-btn" disabled>
                            <span class="send-icon">📤</span>
                            <span class="loading-spinner" style="display: none;">
                                <span class="spinner"></span>
                            </span>
                        </button>
                    </div>
                    
                    <!-- Character Counter -->
                    <div class="input-footer">
                        <div class="char-counter">
                            <span id="char-count">0</span>/<span id="char-limit"><?php echo esc_html(get_option('ai_chatbot_max_message_length', 1000)); ?></span>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Quick Actions -->
            <div class="quick-actions" style="display: none;">
                <button type="button" class="quick-action" data-action="clear">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Clear Chat', 'ai-website-chatbot'); ?>
                </button>
                <?php if (get_option('ai_chatbot_enable_data_export', false)): ?>
                <button type="button" class="quick-action" data-action="export">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export Chat', 'ai-website-chatbot'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <?php if ($show_powered_by): ?>
        <div class="ai-chatbot-footer">
            <div class="powered-by">
                <small><?php esc_html_e('Powered by AI Website Chatbot', 'ai-website-chatbot'); ?></small>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Privacy Notice (if required) -->
    <?php if (get_option('ai_chatbot_show_privacy_notice', false)): ?>
    <div class="ai-chatbot-privacy-notice" id="ai-privacy-notice" style="display: none;">
        <div class="privacy-content">
            <h4><?php esc_html_e('Privacy Notice', 'ai-website-chatbot'); ?></h4>
            <p><?php echo wp_kses_post(get_option('ai_chatbot_privacy_notice_text', '')); ?></p>
            <div class="privacy-actions">
                <button type="button" class="btn-accept"><?php esc_html_e('Accept', 'ai-website-chatbot'); ?></button>
                <button type="button" class="btn-decline"><?php esc_html_e('Decline', 'ai-website-chatbot'); ?></button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Add structured data
$this->add_structured_data();
?>
