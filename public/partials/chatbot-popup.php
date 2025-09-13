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

// Get template configuration
$config = isset($config) ? $config : array();
$popup_id = isset($config['popup_id']) ? $config['popup_id'] : 'ai-chatbot-popup';
$widget_title = isset($config['title']) ? $config['title'] : get_option('ai_chatbot_widget_title', __('AI Assistant', 'ai-website-chatbot'));
$welcome_message = isset($config['welcome_message']) ? $config['welcome_message'] : get_option('ai_chatbot_welcome_message', __('Hello! How can I help you today?', 'ai-website-chatbot'));
$theme = isset($config['theme']) ? $config['theme'] : get_option('ai_chatbot_theme', 'modern');
$show_powered_by = isset($config['show_powered_by']) ? $config['show_powered_by'] : true;
$show_starter_buttons = isset($config['show_starter_buttons']) ? $config['show_starter_buttons'] : true;
$starter_button_1 = isset($config['starter_button_1']) ? $config['starter_button_1'] : __('What services do you offer?', 'ai-website-chatbot');
$starter_button_2 = isset($config['starter_button_2']) ? $config['starter_button_2'] : __('How can I contact support?', 'ai-website-chatbot');
$starter_button_3 = isset($config['starter_button_3']) ? $config['starter_button_3'] : __('Tell me about pricing', 'ai-website-chatbot');
$enable_file_upload = isset($config['enable_file_upload']) ? $config['enable_file_upload'] : false;
$enable_voice_input = isset($config['enable_voice_input']) ? $config['enable_voice_input'] : false;
$enable_conversation_save = isset($config['enable_conversation_save']) ? $config['enable_conversation_save'] : false;
?>

<!-- Modal Backdrop -->
<div class="modal-backdrop"></div>

<!-- Modal Container -->
<div class="modal-container theme-<?php echo esc_attr($theme); ?>">
    <!-- Modal Header -->
    <div class="modal-header">
        <div class="header-info">
            <h2 id="<?php echo esc_attr($popup_id); ?>-title" class="modal-title">
                <span class="title-icon">🤖</span>
                <?php echo esc_html($widget_title); ?>
            </h2>
            <div class="connection-status">
                <span class="status-indicator online"></span>
                <span class="status-text"><?php esc_html_e('Online', 'ai-website-chatbot'); ?></span>
            </div>
        </div>
        
        <div class="modal-controls">
            <?php if ($enable_conversation_save): ?>
            <button type="button" class="control-btn save-conversation-btn" aria-label="<?php esc_attr_e('Save conversation', 'ai-website-chatbot'); ?>">
                <span class="dashicons dashicons-download"></span>
            </button>
            <?php endif; ?>
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
                <span class="status-text"><?php esc_html_e('AI Assistant is ready', 'ai-website-chatbot'); ?></span>
            </div>
            <div class="connection-info">
                <span class="connection-status" id="popup-connection-status">🟢</span>
                <span class="encryption-info" title="<?php esc_attr_e('Secure connection', 'ai-website-chatbot'); ?>">🔒</span>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="popup-messages" id="popup-messages-<?php echo esc_attr($popup_id); ?>">
            <div class="messages-container">
                <!-- Welcome Message -->
                <?php if (!empty($welcome_message)): ?>
                <div class="ai-chatbot-message bot-message welcome-message">
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

                <!-- Conversation Starter -->
                <?php if ($show_starter_buttons): ?>
                <div class="conversation-starters" id="popup-conversation-starters">
                    <div class="starters-label"><?php esc_html_e('How can I help you today?', 'ai-website-chatbot'); ?></div>
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
            </div>

            <!-- Typing Indicator -->
            <div class="ai-chatbot-typing popup-typing" id="popup-typing-<?php echo esc_attr($popup_id); ?>" style="display: none;">
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

            <!-- Scroll to bottom button -->
            <div class="scroll-controls" style="display: none;">
                <button type="button" class="scroll-to-bottom-btn" aria-label="<?php esc_attr_e('Scroll to bottom', 'ai-website-chatbot'); ?>">
                    <span class="dashicons dashicons-arrow-down-alt"></span>
                    <span class="new-message-indicator"><?php esc_html_e('New message', 'ai-website-chatbot'); ?></span>
                </button>
            </div>
        </div>

        <!-- Input Area -->
        <div class="popup-input-area">
            <!-- File Upload Overlay -->
            <?php if ($enable_file_upload): ?>
            <div class="file-upload-overlay" id="popup-file-overlay" style="display: none;">
                <div class="upload-content">
                    <div class="upload-icon">📎</div>
                    <div class="upload-text"><?php esc_html_e('Drop files here or click to browse', 'ai-website-chatbot'); ?></div>
                    <div class="upload-hint"><?php esc_html_e('Supported: PDF, DOC, TXT, Images', 'ai-website-chatbot'); ?></div>
                </div>
                <input type="file" id="popup-file-input" class="file-input" accept="<?php echo esc_attr(implode(',', get_option('ai_chatbot_allowed_file_types', array('.txt', '.pdf')))); ?>">
            </div>
            <?php endif; ?>

            <!-- Input Form -->
            <form id="popup-chatbot-form-<?php echo esc_attr($popup_id); ?>" class="popup-chatbot-form">
                <div class="input-container">
                    <div class="input-wrapper">
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
                        <textarea id="popup-chatbot-input-<?php echo esc_attr($popup_id); ?>" 
                                  class="popup-chatbot-input" 
                                  placeholder="<?php esc_attr_e('Ask me anything...', 'ai-website-chatbot'); ?>"
                                  rows="1"
                                  maxlength="<?php echo esc_attr(get_option('ai_chatbot_max_message_length', 1000)); ?>"></textarea>
                        
                        <!-- Emoji Button -->
                        <button type="button" class="emoji-btn" aria-label="<?php esc_attr_e('Insert emoji', 'ai-website-chatbot'); ?>">
                            😊
                        </button>
                        
                        <!-- Send Button -->
                        <button type="submit" id="popup-send-<?php echo esc_attr($popup_id); ?>" class="popup-send-btn" disabled>
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
                                <span id="popup-char-count-<?php echo esc_attr($popup_id); ?>">0</span>/<span id="popup-char-limit"><?php echo esc_html(get_option('ai_chatbot_max_message_length', 1000)); ?></span>
                            </span>
                            <div class="input-hints">
                                <span class="input-hint"><?php esc_html_e('Press Enter to send', 'ai-website-chatbot'); ?></span>
                                <?php if ($enable_voice_input): ?>
                                <span class="hint-separator">•</span>
                                <span class="input-hint voice-hint"><?php esc_html_e('Click mic for voice', 'ai-website-chatbot'); ?></span>
                                <?php endif; ?>
                            </div>
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
                <?php if ($enable_conversation_save): ?>
                <button type="button" class="action-btn export-btn">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export', 'ai-website-chatbot'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Footer -->
    <div class="modal-footer">
        <div class="footer-content">
            <?php if ($show_powered_by): ?>
            <div class="powered-by">
                <small><?php esc_html_e('Powered by AI Website Chatbot', 'ai-website-chatbot'); ?></small>
            </div>
            <?php endif; ?>
            
            <div class="footer-stats">
                <span class="message-count" id="popup-message-count">0 <?php esc_html_e('messages', 'ai-website-chatbot'); ?></span>
                <span class="response-time" id="popup-avg-response-time" style="display: none;">
                    <?php esc_html_e('Avg response: <span>2.3s</span>', 'ai-website-chatbot'); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Minimized Modal -->
<div class="minimized-modal" id="minimized-modal-<?php echo esc_attr($popup_id); ?>" style="display: none;">
    <button type="button" class="restore-modal-btn" aria-label="<?php esc_attr_e('Restore chat', 'ai-website-chatbot'); ?>">
        <span class="modal-icon">💬</span>
        <span class="modal-title-mini"><?php echo esc_html($widget_title); ?></span>
        <div class="notification-dot" style="display: none;"></div>
        <div class="unread-count" style="display: none;">0</div>
    </button>
</div>

<!-- Voice Input Modal -->
<?php if ($enable_voice_input): ?>
<div class="voice-input-modal" id="popup-voice-modal" style="display: none;">
    <div class="voice-modal-content">
        <div class="voice-animation">
            <div class="voice-circle"></div>
            <div class="voice-pulse"></div>
        </div>
        <div class="voice-status">
            <span class="voice-text"><?php esc_html_e('Listening...', 'ai-website-chatbot'); ?></span>
            <span class="voice-hint"><?php esc_html_e('Speak now or click to stop', 'ai-website-chatbot'); ?></span>
        </div>
        <div class="voice-transcript" id="popup-voice-transcript" style="display: none;">
            <span class="transcript-label"><?php esc_html_e('You said:', 'ai-website-chatbot'); ?></span>
            <span class="transcript-text"></span>
        </div>
        <div class="voice-controls">
            <button type="button" class="voice-stop-btn"><?php esc_html_e('Stop', 'ai-website-chatbot'); ?></button>
            <button type="button" class="voice-cancel-btn"><?php esc_html_e('Cancel', 'ai-website-chatbot'); ?></button>
        </div>
    </div>
</div>
<?php endif; ?>