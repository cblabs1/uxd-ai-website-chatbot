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
$show_starter_buttons = isset($config['show_starter_buttons']) ? $config['show_starter_buttons'] : true;
$starter_button_1 = isset($config['starter_button_1']) ? $config['starter_button_1'] : __('What services do you offer?', 'ai-website-chatbot');
$starter_button_2 = isset($config['starter_button_2']) ? $config['starter_button_2'] : __('How can I contact support?', 'ai-website-chatbot');
$starter_button_3 = isset($config['starter_button_3']) ? $config['starter_button_3'] : __('Tell me about pricing', 'ai-website-chatbot');
$enable_file_upload = isset($config['enable_file_upload']) ? $config['enable_file_upload'] : false;
$enable_voice_input = isset($config['enable_voice_input']) ? $config['enable_voice_input'] : false;
$enable_conversation_save = isset($config['enable_conversation_save']) ? $config['enable_conversation_save'] : false;
?>

<div class="ai-chatbot-inline theme-<?php echo esc_attr($theme); ?>" 
     style="height: <?php echo esc_attr($height); ?>;"
     data-chatbot-type="inline">

    <?php if ($show_header): ?>
    <!-- Header -->
    <div class="ai-chatbot-header-actions">
        <?php if (filter_var($config['enableAudioMode'] ?? false, FILTER_VALIDATE_BOOLEAN)): ?>
        <button type="button" class="ai-chatbot-audio-mode-btn" title="<?php _e('Start Audio Conversation', 'ai-website-chatbot'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                <line x1="12" y1="19" x2="12" y2="23"></line>
                <line x1="8" y1="23" x2="16" y2="23"></line>
            </svg>
        </button>
        <?php endif; ?>
        
        <button type="button" class="ai-chatbot-minimize-btn" title="<?php _e('Minimize', 'ai-website-chatbot'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>
        
        <button type="button" class="ai-chatbot-close-btn" title="<?php _e('Close', 'ai-website-chatbot'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
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
            <?php if ($enable_conversation_save): ?>
            <button type="button" class="header-btn save-conversation-btn" aria-label="<?php esc_attr_e('Save conversation', 'ai-website-chatbot'); ?>">
                <span class="dashicons dashicons-download"></span>
            </button>
            <?php endif; ?>
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
                        <div class="message-bubble">
                            <?php echo wp_kses_post($welcome_message); ?>
                        </div>
                        <div class="message-meta">
                            <span class="message-time"><?php echo esc_html(current_time('g:i A')); ?></span>
                            <span class="message-status">✓</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Conversation Starters -->
            <?php if ($show_starter_buttons): ?>
            <div class="conversation-starters" id="inline-conversation-starters">
                <div class="starters-label"><?php esc_html_e('Quick questions to get started:', 'ai-website-chatbot'); ?></div>
                <div class="starter-buttons">
                    <button type="button" class="starter-btn" data-message="<?php echo esc_attr($starter_button_1); ?>">
                        <span class="starter-icon">💼</span>
                        <span class="starter-text"><?php echo esc_html($starter_button_1); ?></span>
                    </button>
                    <button type="button" class="starter-btn" data-message="<?php echo esc_attr($starter_button_2); ?>">
                        <span class="starter-icon">🆘</span>
                        <span class="starter-text"><?php echo esc_html($starter_button_2); ?></span>
                    </button>
                    <button type="button" class="starter-btn" data-message="<?php echo esc_attr($starter_button_3); ?>">
                        <span class="starter-icon">💰</span>
                        <span class="starter-text"><?php echo esc_html($starter_button_3); ?></span>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Typing Indicator -->
            <div class="ai-chatbot-typing inline-typing" id="inline-typing-indicator" style="display: none;">
                <div class="message-wrapper">
                    <div class="message-avatar">
                        <span class="avatar-icon typing-avatar">🤖</span>
                    </div>
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
        </div>

        <!-- Scroll to bottom button -->
        <div class="scroll-controls" style="display: none;">
            <button type="button" class="scroll-to-bottom-btn" aria-label="<?php esc_attr_e('Scroll to bottom', 'ai-website-chatbot'); ?>">
                <span class="dashicons dashicons-arrow-down-alt"></span>
                <span class="new-message-indicator"><?php esc_html_e('New message', 'ai-website-chatbot'); ?></span>
            </button>
        </div>
    </div>

    <!-- Input Area -->
    <div class="inline-input-area">
        <!-- File Upload Overlay -->
        <?php if ($enable_file_upload): ?>
        <div class="file-upload-overlay" id="inline-file-overlay" style="display: none;">
            <div class="upload-content">
                <div class="upload-icon">📎</div>
                <div class="upload-text"><?php esc_html_e('Drop files here or click to browse', 'ai-website-chatbot'); ?></div>
                <div class="upload-hint"><?php esc_html_e('Supported: PDF, DOC, TXT, Images', 'ai-website-chatbot'); ?></div>
            </div>
            <input type="file" id="inline-file-input" class="file-input" accept="<?php echo esc_attr(implode(',', get_option('ai_chatbot_allowed_file_types', array('.txt', '.pdf')))); ?>">
        </div>
        <?php endif; ?>

        <!-- Input Form -->
        <form id="inline-chatbot-form" class="inline-chatbot-form">
            <div class="input-field-container">
                <div class="input-field-wrapper">
                    <!-- Pre-input Actions -->
                    <div class="pre-input-actions">
                        <?php if ($enable_file_upload): ?>
                        <button type="button" class="pre-action-btn file-btn" aria-label="<?php esc_attr_e('Attach file', 'ai-website-chatbot'); ?>">
                            <span class="dashicons dashicons-paperclip"></span>
                        </button>
                        <?php endif; ?>
                        <?php if ($enable_voice_input): ?>
                        <button type="button" class="pre-action-btn voice-btn" aria-label="<?php esc_attr_e('Voice input', 'ai-website-chatbot'); ?>">
                            <span class="dashicons dashicons-microphone"></span>
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
                        <?php if ($enable_voice_input): ?>
                        <span class="hint-separator">•</span>
                        <span class="hint voice-hint"><?php esc_html_e('Click mic for voice input', 'ai-website-chatbot'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>

        <!-- Message Actions -->
        <div class="message-actions" style="display: none;">
            <button type="button" class="action-btn scroll-to-bottom" aria-label="<?php esc_attr_e('Scroll to bottom', 'ai-website-chatbot'); ?>">
                <span class="dashicons dashicons-arrow-down-alt"></span>
            </button>
            <button type="button" class="action-btn clear-chat" aria-label="<?php esc_attr_e('Clear chat', 'ai-website-chatbot'); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>

    <!-- Footer -->
    <?php if ($show_powered_by): ?>
    <div class="inline-chatbot-footer">
        <div class="footer-content">
            <div class="powered-by">
                <small><?php esc_html_e('Powered by UXD AI Website Chatbot', 'ai-website-chatbot'); ?></small>
            </div>
            <div class="footer-stats">
                <span class="message-count" id="inline-message-count">0 <?php esc_html_e('messages', 'ai-website-chatbot'); ?></span>
                <span class="response-time" id="inline-avg-response-time" style="display: none;">
                    <?php esc_html_e('Avg: 2.1s', 'ai-website-chatbot'); ?>
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Voice Input Modal -->
    <?php if ($enable_voice_input): ?>
    <div class="voice-input-modal" id="inline-voice-modal" style="display: none;">
        <div class="voice-modal-content">
            <div class="voice-animation">
                <div class="voice-circle"></div>
                <div class="voice-pulse"></div>
            </div>
            <div class="voice-status">
                <span class="voice-text"><?php esc_html_e('Listening...', 'ai-website-chatbot'); ?></span>
                <span class="voice-hint"><?php esc_html_e('Speak now or click to stop', 'ai-website-chatbot'); ?></span>
            </div>
            <div class="voice-controls">
                <button type="button" class="voice-stop-btn"><?php esc_html_e('Stop', 'ai-website-chatbot'); ?></button>
                <button type="button" class="voice-cancel-btn"><?php esc_html_e('Cancel', 'ai-website-chatbot'); ?></button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>