<?php
/**
 * Chatbot popup modal template
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$widget_title = esc_html(get_option('ai_chatbot_widget_title', __('AI Assistant', 'ai-website-chatbot')));
$welcome_message = get_option('ai_chatbot_welcome_message', __('Hello! How can I help you today?', 'ai-website-chatbot'));
$theme = get_option('ai_chatbot_theme', 'modern');
?>

<div id="ai-chatbot-popup-modal" class="ai-chatbot-popup-modal theme-<?php echo esc_attr($theme); ?>" style="display: none;" role="dialog" aria-labelledby="ai-chatbot-modal-title" aria-hidden="true">
    <!-- Modal Backdrop -->
    <div class="modal-backdrop"></div>
    
    <!-- Modal Container -->
    <div class="modal-container">
        <!-- Modal Header -->
        <div class="modal-header">
            <h2 id="ai-chatbot-modal-title" class="modal-title">
                <span class="title-icon">🤖</span>
                <?php echo esc_html($widget_title); ?>
            </h2>
            <div class="modal-controls">
                <button type="button" class="control-btn minimize-modal-btn" aria-label="<?php esc_attr_e('Minimize', 'ai-website-chatbot'); ?>">
                    <span class="dashicons dashicons-minus"></span>
                </button>
                <button type="button" class="control-btn close-modal-btn" aria-label="<?php esc_attr_e('Close', 'ai-website-chatbot'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
            <!-- Status Bar -->
            <div class="chatbot-status-bar">
                <div class="status-info">
                    <span class="status-indicator online"></span>
                    <span class="status-text"><?php esc_html_e('AI Assistant is online', 'ai-website-chatbot'); ?></span>
                </div>
                <div class="connection-info">
                    <span class="connection-status" id="connection-status">🟢</span>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="popup-messages" id="ai-popup-messages">
                <div class="messages-container">
                    <!-- Welcome Message -->
                    <?php if (!empty($welcome_message)): ?>
                    <div class="ai-chatbot-message bot-message welcome-message">
                        <div class="message-avatar">🤖</div>
                        <div class="message-content">
                            <div class="message-bubble">
                                <?php echo wp_kses_post($welcome_message); ?>
                            </div>
                            <div class="message-time"><?php echo esc_html(current_time('g:i A')); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Conversation Starter -->
                    <div class="conversation-starters" id="conversation-starters">
                        <div class="starters-label"><?php esc_html_e('How can I help you today?', 'ai-website-chatbot'); ?></div>
                        <div class="starter-buttons">
                            <button type="button" class="starter-btn" data-message="<?php esc_attr_e('What services do you offer?', 'ai-website-chatbot'); ?>">
                                <?php esc_html_e('Our Services', 'ai-website-chatbot'); ?>
                            </button>
                            <button type="button" class="starter-btn" data-message="<?php esc_attr_e('How can I contact support?', 'ai-website-chatbot'); ?>">
                                <?php esc_html_e('Contact Support', 'ai-website-chatbot'); ?>
                            </button>
                            <button type="button" class="starter-btn" data-message="<?php esc_attr_e('Tell me about pricing', 'ai-website-chatbot'); ?>">
                                <?php esc_html_e('Pricing Info', 'ai-website-chatbot'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Typing Indicator -->
                <div class="ai-chatbot-typing popup-typing" id="ai-popup-typing" style="display: none;">
                    <div class="message-avatar">🤖</div>
                    <div class="typing-content">
                        <div class="typing-bubble">
                            <div class="typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                        <div class="typing-text"><?php esc_html_e('AI is thinking...', 'ai-website-chatbot'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="popup-input-area">
                <form id="ai-popup-form" class="popup-chatbot-form">
                    <div class="input-container">
                        <div class="input-wrapper">
                            <textarea id="ai-popup-input" 
                                      class="popup-chatbot-input" 
                                      placeholder="<?php esc_attr_e('Ask me anything...', 'ai-website-chatbot'); ?>"
                                      rows="1"
                                      maxlength="<?php echo esc_attr(get_option('ai_chatbot_max_message_length', 1000)); ?>"></textarea>
                            
                            <!-- Emoji Button -->
                            <button type="button" class="emoji-btn" aria-label="<?php esc_attr_e('Insert emoji', 'ai-website-chatbot'); ?>">
                                😊
                            </button>
                            
                            <button type="submit" id="ai-popup-send" class="popup-send-btn" disabled>
                                <span class="send-icon">🚀</span>
                                <span class="loading-spinner" style="display: none;">
                                    <span class="spinner"></span>
                                </span>
                            </button>
                        </div>
                        
                        <!-- Input Footer -->
                        <div class="input-footer">
                            <div class="input-info">
                                <span class="char-counter">
                                    <span id="popup-char-count">0</span>/<span id="popup-char-limit"><?php echo esc_html(get_option('ai_chatbot_max_message_length', 1000)); ?></span>
                                </span>
                                <span class="input-hint"><?php esc_html_e('Press Enter to send', 'ai-website-chatbot'); ?></span>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Action Buttons -->
                <div class="popup-actions">
                    <button type="button" class="action-btn clear-chat-btn">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear Chat', 'ai-website-chatbot'); ?>
                    </button>
                    <button type="button" class="action-btn refresh-btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh', 'ai-website-chatbot'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
            <div class="footer-content">
                <?php if (get_option('ai_chatbot_show_powered_by', true)): ?>
                <div class="powered-by">
                    <small><?php esc_html_e('Powered by AI Website Chatbot', 'ai-website-chatbot'); ?></small>
                </div>
                <?php endif; ?>
                
                <div class="footer-stats">
                    <span class="response-time" id="avg-response-time" style="display: none;">
                        <?php esc_html_e('Avg response: <span>2.3s</span>', 'ai-website-chatbot'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Minimized Modal -->
    <div class="minimized-modal" id="minimized-modal" style="display: none;">
        <button type="button" class="restore-modal-btn" aria-label="<?php esc_attr_e('Restore chat', 'ai-website-chatbot'); ?>">
            <span class="modal-icon">💬</span>
            <span class="modal-title-mini"><?php echo esc_html($widget_title); ?></span>
            <div class="notification-dot" style="display: none;"></div>
        </button>
    </div>
</div>

<script>
// Enqueue pre-chat functionality
jQuery(document).ready(function($) {
    // Check if pre-chat modal should be enabled
    if (ai_chatbot_ajax.settings.user_collection_enabled) {
        // Load pre-chat modal script if not already loaded
        if (typeof window.AIChatbotPreChat === 'undefined') {
            $.getScript(ai_chatbot_ajax.plugin_url + 'assets/js/public/chatbot-pre-chat.js')
                .done(function() {
                    console.log('AI Chatbot: Pre-chat modal loaded successfully');
                })
                .fail(function() {
                    console.warn('AI Chatbot: Failed to load pre-chat modal');
                });
        }
    }
});
</script>
