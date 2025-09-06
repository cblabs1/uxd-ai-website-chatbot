/**
 * AI Chatbot Frontend JavaScript
 * Updated with proper session management and latest AI models
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Global chatbot object
    window.AIChatbot = {
        config: {},
        initialized: false,
        widget: null,
        currentSessionId: null,
        currentConversationId: null,
        messageHistory: [],
        isTyping: false,
        retryCount: 0,
        maxRetries: 3,
        
        // Initialize chatbot
        init: function(config) {
            if (this.initialized) {
                return;
            }

            this.config = $.extend({
                ajaxUrl: '',
                nonce: '',
                sessionId: '',
                settings: {
                    maxMessageLength: 1000,
                    enableRating: true,
                    enableHistory: true,
                    enableFileUpload: false,
                    typingSpeed: 50,
                    autoResponse: true
                },
                strings: {
                    loading: 'Loading...',
                    error: 'Something went wrong. Please try again.',
                    typing: 'AI is typing...',
                    networkError: 'Network error. Please check your connection.',
                    messageTooLong: 'Message is too long.',
                    thankYou: 'Thank you for your feedback!',
                    confirmClear: 'Are you sure you want to clear the conversation?',
                    send: 'Send',
                    thinking: 'Thinking...'
                }
            }, config);

            // Initialize session management
            this.initializeSession();
            
            // Setup event handlers
            this.setupEventHandlers();
            
            // Initialize UI elements
            this.initializeUI();
            
            this.initialized = true;
            
            // Load conversation history if enabled
            if (this.config.settings.enableHistory) {
                this.loadConversationHistory();
            }
        },

        // Session Management Methods
        initializeSession: function() {
            // Get or generate session ID
            this.currentSessionId = this.getSessionId();
            
            // Generate conversation ID for this page/widget instance
            this.currentConversationId = this.generateConversationId();
            
            console.log('AI Chatbot Session:', {
                sessionId: this.currentSessionId,
                conversationId: this.currentConversationId
            });
        },

        getSessionId: function() {
            // Check localStorage first
            let sessionId = localStorage.getItem('ai_chatbot_session');
            
            // Validate existing session ID
            if (sessionId && sessionId.length >= 20) {
                return sessionId;
            }
            
            // Check if passed from config
            if (this.config.sessionId && this.config.sessionId.length >= 20) {
                sessionId = this.config.sessionId;
                localStorage.setItem('ai_chatbot_session', sessionId);
                return sessionId;
            }
            
            // Generate new session ID
            sessionId = 'session_' + Date.now() + '_' + this.generateRandomString(16);
            
            // Store in localStorage
            localStorage.setItem('ai_chatbot_session', sessionId);
            
            return sessionId;
        },

        generateConversationId: function() {
            return 'conv_' + Date.now() + '_' + this.generateRandomString(12);
        },

        generateRandomString: function(length) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';
            for (let i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        },

        // UI Initialization
        initializeUI: function() {
            this.$widget = $('.ai-chatbot-widget');
            this.$messages = $('.ai-chatbot-messages, .messages-container');
            this.$input = $('.ai-chatbot-input input, #ai-chatbot-input');
            this.$sendBtn = $('.ai-chatbot-send-btn, #ai-chatbot-send');
            this.$typing = $('.ai-chatbot-typing, .typing-indicator');
            
            // Create typing indicator if it doesn't exist
            if (this.$typing.length === 0) {
                this.$typing = $('<div class="ai-chatbot-typing typing-indicator" style="display:none;">' +
                    '<div class="typing-dots">' +
                    '<span></span><span></span><span></span>' +
                    '</div>' +
                    '<span class="typing-text">' + this.config.strings.typing + '</span>' +
                    '</div>');
                this.$messages.append(this.$typing);
            }
        },

        // Event Handlers
        setupEventHandlers: function() {
            var self = this;
            
            // Send button click
            $(document).on('click', '.ai-chatbot-send-btn, #ai-chatbot-send', function(e) {
                e.preventDefault();
                self.handleSendMessage();
            });
            
            // Enter key press
            $(document).on('keypress', '.ai-chatbot-input input, #ai-chatbot-input', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.handleSendMessage();
                }
            });
            
            // Input change for validation
            $(document).on('input', '.ai-chatbot-input input, #ai-chatbot-input', function() {
                self.onInputChange();
            });
            
            // Rating buttons
            $(document).on('click', '.rating-btn', function() {
                var rating = $(this).data('rating');
                var conversationId = $(this).data('conversation-id');
                self.submitRating(conversationId, rating);
            });
            
            // Quick actions
            $(document).on('click', '[data-action]', function() {
                var action = $(this).data('action');
                self.handleQuickAction(action);
            });
        },

        // Message Handling
        handleSendMessage: function() {
            var message = this.$input.val().trim();
            
            // Validate message
            if (!this.validateMessage(message)) {
                return;
            }
            
            // Disable input while processing
            this.setInputState(false);
            
            // Add user message to UI
            this.addUserMessage(message);
            
            // Clear input
            this.$input.val('');
            this.onInputChange();
            
            // Show typing indicator
            this.showTypingIndicator();
            
            // Send to server
            this.sendMessageToServer(message);
        },

        validateMessage: function(message) {
            if (!message) {
                this.showError(this.config.strings.error);
                return false;
            }
            
            if (message.length > this.config.settings.maxMessageLength) {
                this.showError(this.config.strings.messageTooLong);
                return false;
            }
            
            if (this.isTyping) {
                return false;
            }
            
            return true;
        },

        sendMessageToServer: function(message) {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 60000, // 60 seconds timeout
                data: {
                    action: 'ai_chatbot_send_message',
                    nonce: this.config.nonce,
                    message: message,
                    session_id: this.currentSessionId,
                    conversation_id: this.currentConversationId,
                    page_url: window.location.href
                },
                success: function(response) {
                    self.handleMessageSuccess(response);
                },
                error: function(xhr, status, error) {
                    self.handleMessageError(xhr, status, error);
                },
                complete: function() {
                    self.hideTypingIndicator();
                    self.setInputState(true);
                }
            });
        },

        handleMessageSuccess: function(response) {
            this.retryCount = 0; // Reset retry count on success
            
            if (response.success && response.data) {
                var botResponse = response.data.response || this.config.strings.error;
                var messageId = response.data.message_id || this.generateRandomString(8);
                
                // Update session info if provided
                if (response.data.session_id) {
                    this.currentSessionId = response.data.session_id;
                    localStorage.setItem('ai_chatbot_session', this.currentSessionId);
                }
                
                if (response.data.conversation_id) {
                    this.currentConversationId = response.data.conversation_id;
                }
                
                // Add bot message to UI
                this.addBotMessage(botResponse, messageId);
                
                // Track analytics
                this.trackEvent('message_sent_success', {
                    responseTime: response.data.response_time || 0,
                    tokensUsed: response.data.tokens_used || 0,
                    source: response.data.source || 'api'
                });
                
                // Show typing animation
                this.animateMessageAppearance(messageId);
                
            } else {
                var errorMsg = response.data ? response.data.message : this.config.strings.error;
                this.showError(errorMsg);
                this.trackEvent('message_sent_failed', { 
                    error: errorMsg,
                    response: response 
                });
            }
        },

        handleMessageError: function(xhr, status, error) {
            var errorMessage = this.config.strings.networkError;
            
            // Specific error handling
            if (xhr.status === 400) {
                errorMessage = 'Invalid request. Please try again.';
            } else if (xhr.status === 429) {
                errorMessage = 'Too many requests. Please wait a moment.';
            } else if (xhr.status >= 500) {
                errorMessage = 'Server error. Please try again later.';
            } else if (status === 'timeout') {
                errorMessage = 'Request timed out. Please try again.';
            }
            
            // Retry logic
            if (this.retryCount < this.maxRetries && xhr.status !== 429) {
                this.retryCount++;
                setTimeout(() => {
                    var lastMessage = this.getLastUserMessage();
                    if (lastMessage) {
                        this.sendMessageToServer(lastMessage);
                    }
                }, 2000 * this.retryCount); // Exponential backoff
                return;
            }
            
            this.showError(errorMessage);
            this.trackEvent('message_sent_failed', { 
                status: xhr.status, 
                error: error, 
                retryCount: this.retryCount 
            });
        },

        // Message Display Methods
        addUserMessage: function(message) {
            var messageId = this.generateRandomString(8);
            var timestamp = Date.now();
            
            var messageHtml = this.buildMessageHtml('user', message, messageId, timestamp);
            this.$messages.append(messageHtml);
            
            this.messageHistory.push({
                id: messageId,
                sender: 'user',
                message: message,
                timestamp: timestamp
            });
            
            this.scrollToBottom();
            this.hideSuggestions();
        },

        addBotMessage: function(message, messageId) {
            messageId = messageId || this.generateRandomString(8);
            var timestamp = Date.now();
            
            var messageHtml = this.buildMessageHtml('bot', message, messageId, timestamp);
            this.$messages.append(messageHtml);
            
            this.messageHistory.push({
                id: messageId,
                sender: 'bot',
                message: message,
                timestamp: timestamp
            });
            
            this.scrollToBottom();
        },

        buildMessageHtml: function(sender, message, messageId, timestamp) {
            var senderClass = sender === 'user' ? 'user-message' : 'bot-message';
            var timeString = this.formatTime(timestamp / 1000);
            var avatar = sender === 'user' ? '👤' : '🤖';
            
            var html = '<div class="ai-chatbot-message ' + senderClass + '" data-message-id="' + messageId + '">';
            html += '<div class="message-avatar">' + avatar + '</div>';
            html += '<div class="message-content">';
            html += '<div class="message-bubble">' + this.escapeHtml(message) + '</div>';
            html += '<div class="message-meta">';
            html += '<span class="message-time">' + timeString + '</span>';
            
            // Add rating for bot messages
            if (sender === 'bot' && this.config.settings.enableRating) {
                html += this.buildRatingHtml(messageId);
            }
            
            html += '</div></div></div>';
            
            return html;
        },

        buildRatingHtml: function(messageId) {
            return '<div class="message-rating" data-message-id="' + messageId + '">' +
                   '<button class="rating-btn positive" data-rating="1" data-conversation-id="' + messageId + '">' +
                   '<span class="dashicons dashicons-thumbs-up"></span></button>' +
                   '<button class="rating-btn negative" data-rating="-1" data-conversation-id="' + messageId + '">' +
                   '<span class="dashicons dashicons-thumbs-down"></span></button>' +
                   '</div>';
        },

        // UI Helper Methods
        showTypingIndicator: function() {
            this.$typing.show();
            this.scrollToBottom();
            this.isTyping = true;
        },

        hideTypingIndicator: function() {
            this.$typing.hide();
            this.isTyping = false;
        },

        showError: function(message) {
            var errorHtml = '<div class="ai-chatbot-message error-message">' +
                           '<div class="message-content">' +
                           '<div class="message-bubble error">' + this.escapeHtml(message) + '</div>' +
                           '</div></div>';
            
            this.$messages.append(errorHtml);
            this.scrollToBottom();
            
            // Auto-remove error after 5 seconds
            setTimeout(function() {
                $('.error-message').fadeOut();
            }, 5000);
        },

        setInputState: function(enabled) {
            this.$input.prop('disabled', !enabled);
            this.$sendBtn.prop('disabled', !enabled);
            
            if (enabled) {
                this.onInputChange(); // Revalidate
            }
        },

        onInputChange: function() {
            var message = this.$input.val().trim();
            var charCount = message.length;
            
            // Update character counter if exists
            $('.char-count').text(charCount);
            
            // Update send button state
            if (message && charCount <= this.config.settings.maxMessageLength && !this.isTyping) {
                this.$sendBtn.prop('disabled', false);
            } else {
                this.$sendBtn.prop('disabled', true);
            }
            
            // Handle validation styling
            if (charCount > this.config.settings.maxMessageLength) {
                this.$input.addClass('error');
                $('.char-count').addClass('over-limit');
            } else {
                this.$input.removeClass('error');
                $('.char-count').removeClass('over-limit');
            }
        },

        scrollToBottom: function() {
            var $container = this.$messages.closest('.ai-chatbot-container, .chat-container');
            if ($container.length) {
                $container.scrollTop($container[0].scrollHeight);
            } else {
                this.$messages.scrollTop(this.$messages[0].scrollHeight);
            }
        },

        animateMessageAppearance: function(messageId) {
            var $message = $('[data-message-id="' + messageId + '"]');
            if ($message.length) {
                $message.hide().fadeIn(300);
            }
        },

        hideSuggestions: function() {
            $('.ai-chatbot-suggestions').fadeOut();
        },

        // Rating System
        submitRating: function(conversationId, rating) {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_rating',
                    conversation_id: conversationId,
                    rating: rating,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var $rating = $('[data-message-id="' + conversationId + '"] .message-rating');
                        $rating.html('<span class="rating-thanks">' + self.config.strings.thankYou + '</span>');
                        
                        self.trackEvent('message_rated', { rating: rating });
                    }
                },
                error: function() {
                    console.log('Failed to submit rating');
                }
            });
        },

        // Conversation Management
        loadConversationHistory: function() {
            // Load conversation history from server if available
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_history',
                    session_id: this.currentSessionId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.messages) {
                        self.displayConversationHistory(response.data.messages);
                    }
                }
            });
        },

        displayConversationHistory: function(messages) {
            for (var i = 0; i < messages.length; i++) {
                var msg = messages[i];
                if (msg.sender === 'user') {
                    this.addUserMessage(msg.message);
                } else {
                    this.addBotMessage(msg.message, msg.id);
                }
            }
        },

        clearConversation: function() {
            if (confirm(this.config.strings.confirmClear)) {
                var self = this;
                
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ai_chatbot_clear_conversation',
                        session_id: this.currentSessionId,
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.$messages.find('.ai-chatbot-message').remove();
                            self.currentSessionId = response.data.new_session_id;
                            self.currentConversationId = self.generateConversationId();
                            self.messageHistory = [];
                            
                            // Update localStorage
                            localStorage.setItem('ai_chatbot_session', self.currentSessionId);
                            
                            // Show welcome message if configured
                            if (self.config.welcomeMessage) {
                                self.addBotMessage(self.config.welcomeMessage);
                            }
                        }
                    }
                });
            }
        },

        // Utility Methods
        getLastUserMessage: function() {
            for (var i = this.messageHistory.length - 1; i >= 0; i--) {
                if (this.messageHistory[i].sender === 'user') {
                    return this.messageHistory[i].message;
                }
            }
            return null;
        },

        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        formatTime: function(timestamp) {
            var date = new Date(timestamp * 1000);
            return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        },

        // Analytics
        trackEvent: function(eventType, data) {
            if (typeof gtag !== 'undefined') {
                gtag('event', eventType, {
                    custom_parameter_1: JSON.stringify(data)
                });
            }
            
            // Custom analytics tracking
            if (this.config.analyticsCallback && typeof this.config.analyticsCallback === 'function') {
                this.config.analyticsCallback(eventType, data);
            }
        },

        // Quick Actions Handler
        handleQuickAction: function(action) {
            switch (action) {
                case 'clear':
                    this.clearConversation();
                    break;
                case 'export':
                    this.exportConversation();
                    break;
                case 'refresh':
                    this.refreshWidget();
                    break;
            }
        },

        exportConversation: function() {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_export_data',
                    session_id: this.currentSessionId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger download
                        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data.data));
                        var dlAnchorElem = document.createElement('a');
                        dlAnchorElem.setAttribute("href", dataStr);
                        dlAnchorElem.setAttribute("download", response.data.filename);
                        dlAnchorElem.click();
                    }
                }
            });
        },

        refreshWidget: function() {
            // Reload the widget
            location.reload();
        }
    };

    // Utilities namespace
    window.AIChatbot.utils = {
        generateId: function() {
            return 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },
        
        escapeHtml: function(text) {
            return window.AIChatbot.escapeHtml(text);
        },
        
        formatTime: function(timestamp) {
            return window.AIChatbot.formatTime(timestamp);
        }
    };

    // Auto-initialize when DOM is ready
    $(document).ready(function() {
        // Initialize chatbot if config exists
        if (typeof ai_chatbot_ajax !== 'undefined') {
            window.AIChatbot.init({
                ajaxUrl: ai_chatbot_ajax.ajax_url,
                nonce: ai_chatbot_ajax.nonce,
                sessionId: ai_chatbot_ajax.session_id || '',
                settings: ai_chatbot_ajax.settings || {},
                strings: ai_chatbot_ajax.strings || {}
            });
        }
    });

})(jQuery);