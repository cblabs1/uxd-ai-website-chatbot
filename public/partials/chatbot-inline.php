<?php
/**
 * Inline chatbot template
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get template configuration
$config = isset($config) ? $config : array();
$widget_title = isset($config['title']) ? $config['title'] : get_option('ai_chatbot_widget_title', __('AI Assistant', 'ai-website-chatbot'));
$welcome_message = isset($config['welcome_message']) ? $config['welcome_message'] : get_option('ai_chatbot_welcome_message', '');
$height = isset($config['height']) ? $config['height'] : '400px';
$theme = isset($config['theme']) ? $config['theme'] : 'default';
$show_header = isset($config['show_header']) ? $config['show_header'] : true;
$show_powered_by = isset($config['show_powered_by']) ? $config['show_powered_by'] : true;
?>

<div class="ai-chatbot-inline theme-<?php echo esc_attr($theme); ?>" 
     style="height: <?php echo esc_attr($height); ?>;"
     data-chatbot-type="inline">

    <?php if ($show_header): ?>
    <!-- Header -->
    <div class="inline-chatbot-header">
        <div class="header-info">
            <div class="bot-avatar">🤖</div>
            <div class="bot-details">
                <h4 class="bot-name"><?php echo esc_html($widget_title); ?></h4>
                <div class="bot-status">
                    <span class="status-dot online"></span>
                    <span class="status-label"><?php esc_html_e('Online', 'ai-website-chatbot'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="header-actions">
            <button type="button" class="header-btn fullscreen-btn" aria-label="<?php esc_attr_e('Fullscreen', 'ai-website-chatbot'); ?>">
                <span class="dashicons dashicons-fullscreen-alt"></span>
            </button>
            <button type="button" class="header-btn settings-btn" aria-label="<?php esc_attr_e('Settings', 'ai-website-chatbot'); ?>">
                <span class="dashicons dashicons-admin-generic"></span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Messages Container -->
    <div class="inline-messages-container">
        <div class="messages-wrapper" id="inline-messages-wrapper">
            <!-- System Message -->
            <div class="system-message">
                <div class="system-content">
                    <span class="system-icon">ℹ️</span>
                    <span class="system-text"><?php esc_html_e('This is a secure AI conversation', 'ai-website-chatbot'); ?></span>
                </div>
            </div>

            <!-- Welcome Message -->
            <?php if (!empty($welcome_message)): ?>
            <div class="ai-chatbot-message bot-message initial-message">
                <div class="message-wrapper">
                    <div class="message-avatar">
                        <span class="avatar-icon">🤖</span>
                    </div>
                    <div class="message-content">
                        <div class="message-header">
                            <span class="sender-name"><?php echo esc_html($widget_title); ?></span>
                            <span class="message-timestamp"><?php echo esc_html(current_time('g:i A')); ?></span>
                        </div>
                        <div class="message-bubble">
                            <div class="message-text">
                                <?php echo wp_kses_post($welcome_message); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Suggestions -->
            <div class="quick-suggestions" id="inline-suggestions">
                <div class="suggestions-header">
                    <span class="suggestions-label"><?php esc_html_e('Suggested questions:', 'ai-website-chatbot'); ?></span>
                </div>
                <div class="suggestions-grid">
                    <button type="button" class="suggestion-chip" data-suggestion="<?php esc_attr_e('What can you help me with?', 'ai-website-chatbot'); ?>">
                        <span class="chip-icon">❓</span>
                        <span class="chip-text"><?php esc_html_e('What can you help me with?', 'ai-website-chatbot'); ?></span>
                    </button>
                    <button type="button" class="suggestion-chip" data-suggestion="<?php esc_attr_e('Tell me about your services', 'ai-website-chatbot'); ?>">
                        <span class="chip-icon">🛎️</span>
                        <span class="chip-text"><?php esc_html_e('Our services', 'ai-website-chatbot'); ?></span>
                    </button>
                    <button type="button" class="suggestion-chip" data-suggestion="<?php esc_attr_e('How do I get started?', 'ai-website-chatbot'); ?>">
                        <span class="chip-icon">🚀</span>
                        <span class="chip-text"><?php esc_html_e('Getting started', 'ai-website-chatbot'); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div class="inline-typing-indicator" id="inline-typing" style="display: none;">
            <div class="typing-wrapper">
                <div class="message-avatar">
                    <span class="avatar-icon">🤖</span>
                </div>
                <div class="typing-content">
                    <div class="typing-bubble">
                        <div class="typing-animation">
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                        </div>
                    </div>
                    <div class="typing-label"><?php esc_html_e('AI is typing...', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Input Section -->
    <div class="inline-input-section">
        <form id="inline-chatbot-form" class="inline-form">
            <div class="input-container">
                <!-- File Upload Area (if enabled) -->
                <?php if (get_option('ai_chatbot_enable_file_uploads', false)): ?>
                <div class="file-upload-area" id="file-upload-area" style="display: none;">
                    <div class="upload-content">
                        <span class="upload-icon">📎</span>
                        <span class="upload-text"><?php esc_html_e('Drag & drop a file or click to browse', 'ai-website-chatbot'); ?></span>
                    </div>
                    <input type="file" id="inline-file-input" class="file-input" accept="<?php echo esc_attr(implode(',', get_option('ai_chatbot_allowed_file_types', array('.txt', '.pdf')))); ?>">
                </div>
                <?php endif; ?>

                <!-- Input Field -->
                <div class="input-field-container">
                    <div class="input-field-wrapper">
                        <!-- Pre-input Actions -->
                        <div class="pre-input-actions">
                            <?php if (get_option('ai_chatbot_enable_file_uploads', false)): ?>
                            <button type="button" class="pre-action-btn file-btn" aria-label="<?php esc_attr_e('Attach file', 'ai-website-chatbot'); ?>">
                                <span class="dashicons dashicons-paperclip"></span>
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Text Input -->
                        <textarea id="inline-chatbot-input" 
                                  class="inline-input" 
                                  placeholder="<?php esc_attr_e('Type your message here...', 'ai-website-chatbot'); ?>"
                                  rows="1"
                                  maxlength="<?php echo esc_attr(get_option('ai_chatbot_max_message_length', 1000)); ?>"></textarea>

                        <!-- Post-input Actions -->
                        <div class="post-input-actions">
                            <button type="submit" id="inline-send-btn" class="inline-send-btn" disabled>
                                <span class="send-icon default-icon">🚀</span>
                                <span class="send-icon loading-icon" style="display: none;">
                                    <span class="loading-dots">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Input Metadata -->
                    <div class="input-metadata">
                        <div class="char-count-container">
                            <span class="char-count" id="inline-char-count">0</span>
                            <span class="char-limit">/ <?php echo esc_html(get_option('ai_chatbot_max_message_length', 1000)); ?></span>
                        </div>
                        <div class="input-hints">
                            <span class="hint"><?php esc_html_e('Press Shift+Enter for new line', 'ai-website-chatbot'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Message Actions -->
        <div class="message-actions" style="display: none;">
            <button type="button" class="action-btn scroll-to-bottom" aria-label="<?php esc_attr_e('Scroll to bottom', 'ai-website-chatbot'); ?>">
                <span class="dashicons dashicons-arrow-down-alt"></span>
            </button>
        </div>
    </div>

    <!-- Footer -->
    <?php if ($show_powered_by): ?>
    <div class="inline-chatbot-footer">
        <div class="footer-content">
            <div class="powered-by-inline">
                <small><?php esc_html_e('Powered by AI Website Chatbot', 'ai-website-chatbot'); ?></small>
            </div>
            <div class="footer-stats">
                <span class="messages-count">0 <?php esc_html_e('messages', 'ai-website-chatbot'); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Settings Panel (if enabled) -->
    <div class="settings-panel" id="inline-settings-panel" style="display: none;">
        <div class="settings-header">
            <h4><?php esc_html_e('Chat Settings', 'ai-website-chatbot'); ?></h4>
            <button type="button" class="close-settings">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="settings-content">
            <div class="setting-item">
                <label class="setting-label">
                    <input type="checkbox" class="setting-checkbox" id="enable-sounds">
                    <span><?php esc_html_e('Enable notification sounds', 'ai-website-chatbot'); ?></span>
                </label>
            </div>
            <div class="setting-item">
                <label class="setting-label">
                    <input type="checkbox" class="setting-checkbox" id="auto-scroll">
                    <span><?php esc_html_e('Auto-scroll to new messages', 'ai-website-chatbot'); ?></span>
                </label>
            </div>
            <div class="setting-item">
                <button type="button" class="setting-button clear-history">
                    <?php esc_html_e('Clear conversation history', 'ai-website-chatbot'); ?>
                </button>
            </div>
        </div>
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