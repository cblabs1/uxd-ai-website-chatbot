/**
 * AI Chatbot Frontend JavaScript - Complete Version with Rating System
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
        messageCount: 0,
        inactivityTimer: null,
        lastActivityTime: null,
        
        // DOM elements cache
        $widget: null,
        $messages: null,
        $input: null,
        $sendBtn: null,
        $typing: null,
        
        // Initialize chatbot
        init: function(config) {
            if (this.initialized) {
                return;
            }

            console.log('AIChatbot: Initializing...');

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
                    confirmClear: 'Are you sure you want to clear the conversation?'
                }
            }, config);

            // Initialize session
            this.currentSessionId = this.config.sessionId || this.generateSessionId();
            this.currentConversationId = this.generateConversationId();
            this.lastActivityTime = Date.now();

            // Initialize UI
            this.initializeUI();
            
            // Setup event handlers
            this.setupEventHandlers();
            
            // Load conversation history if enabled
            if (this.config.settings.enableHistory) {
                this.loadConversationHistory();
            }

            // Start inactivity monitoring
            this.startInactivityTimer();

            this.initialized = true;
            console.log('AIChatbot: Initialized successfully');
        },

        // Generate unique session ID
        generateSessionId: function() {
            return 'session_' + Date.now() + '_' + this.generateRandomString(12);
        },

        // Generate conversation ID
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
            this.$messages = $('.ai-chatbot-messages, .messages-container, .inline-messages-container, .popup-messages-container');
            this.$input = $('.ai-chatbot-input input, #ai-chatbot-input, input[name="message"]');
            this.$sendBtn = $('.ai-chatbot-send-btn, #ai-chatbot-send, .popup-send-btn');
            this.$typing = $('.ai-chatbot-typing, .typing-indicator');
            
            console.log('UI Elements found:', {
                widget: this.$widget.length,
                messages: this.$messages.length,
                input: this.$input.length,
                sendBtn: this.$sendBtn.length
            });
            
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

        // Event Handlers Setup
        setupEventHandlers: function() {
            var self = this;
            console.log('Setting up event handlers...');
            
            // Remove existing handlers to avoid duplicates
            $(document).off('.aichatbot');
            
            // Send button click
            $(document).on('click.aichatbot', '.ai-chatbot-send-btn, #ai-chatbot-send, .popup-send-btn', function(e) {
                e.preventDefault();
                self.resetInactivityTimer();
                self.handleSendMessage();
            });
            
            // Enter key press
            $(document).on('keypress.aichatbot', '.ai-chatbot-input input, #ai-chatbot-input, input[name="message"]', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.resetInactivityTimer();
                    self.handleSendMessage();
                }
            });
            
            // Input change for validation and activity tracking
            $(document).on('input.aichatbot', '.ai-chatbot-input input, #ai-chatbot-input, input[name="message"]', function() {
                self.resetInactivityTimer();
                self.onInputChange();
            });
            
            // Rating buttons - Quick rating for individual messages
            $(document).on('click.aichatbot', '.rating-btn, .quick-rating-btn', function(e) {
                e.preventDefault();
                var rating = $(this).data('rating');
                var conversationId = $(this).data('conversation-id');
                console.log('Rating clicked:', rating, conversationId);
                self.submitRating(conversationId, rating);
            });
            
            // Smiley rating buttons for end-of-conversation
            $(document).on('click.aichatbot', '.smiley-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var rating = parseInt($btn.data('rating'));
                console.log('Smiley rating clicked:', rating);
                
                // Update UI to show selected state
                $('.smiley-btn').removeClass('selected');
                $btn.addClass('selected');
                
                // Show feedback section
                $('.rating-feedback').slideDown(300);
                
                // Store rating for submission
                $('.end-conversation-rating').data('selected-rating', rating);
                
                // Add animation
                $btn.addClass('bounce');
                setTimeout(() => $btn.removeClass('bounce'), 600);
            });
            
            // Submit conversation rating
            $(document).on('click.aichatbot', '.submit-rating-btn', function(e) {
                e.preventDefault();
                var rating = $('.end-conversation-rating').data('selected-rating');
                var feedback = $('.feedback-text').val().trim();
                console.log('Submitting conversation rating:', rating, feedback);
                
                if (rating) {
                    self.submitConversationRating(rating, feedback);
                }
            });
            
            // Skip rating
            $(document).on('click.aichatbot', '.skip-rating-btn', function(e) {
                e.preventDefault();
                $('.end-conversation-rating').fadeOut(300);
            });
            
            // Clear conversation
            $(document).on('click.aichatbot', '.clear-chat-btn, [data-action="clear"]', function(e) {
                e.preventDefault();
                self.clearConversation();
            });
            
            // Close chat (for popup/widget)
            $(document).on('click.aichatbot', '.ai-chatbot-close, .close-chat-btn', function(e) {
                e.preventDefault();
                self.closeChat();
            });
            
            // Quick actions
            $(document).on('click.aichatbot', '[data-action]', function() {
                var action = $(this).data('action');
                self.handleQuickAction(action);
            });
        },

        // Inactivity Timer Management
        startInactivityTimer: function() {
            console.log('Starting inactivity timer...');
            this.resetInactivityTimer();
        },

        resetInactivityTimer: function() {
            var self = this;
            this.lastActivityTime = Date.now();
            
            clearTimeout(this.inactivityTimer);
            
            // Set 5-minute inactivity timer
            this.inactivityTimer = setTimeout(function() {
                console.log('Inactivity detected, showing rating...');
                if (!$('.end-conversation-rating').length && self.messageCount > 1) {
                    self.showEndOfConversationRating();
                }
            }, 300000); // 5 minutes = 300,000 milliseconds
        },

        // Message Handling
        handleSendMessage: function() {
            var message = this.$input.val().trim();
            console.log('Sending message:', message);
            
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
                    message: message,
                    session_id: this.currentSessionId,
                    conversation_id: this.currentConversationId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.hideTypingIndicator();
                    self.setInputState(true);
                    self.retryCount = 0; // Reset retry count on success
                    
                    if (response.success) {
                        var botResponse = response.data.response;
                        var messageId = response.data.conversation_id || self.generateRandomString(8);
                        
                        // Add to message history
                        self.messageHistory.push({
                            sender: 'user',
                            message: message,
                            timestamp: Date.now()
                        });
                        
                        self.messageHistory.push({
                            sender: 'bot',
                            message: botResponse,
                            timestamp: Date.now(),
                            id: messageId
                        });
                        
                        self.addBotMessage(botResponse, messageId);
                        self.messageCount++;
                        
                        // Check if conversation seems to be ending
                        self.checkForConversationEnd(botResponse);
                        
                        // Update session and conversation IDs if provided
                        if (response.data.session_id) {
                            self.currentSessionId = response.data.session_id;
                        }
                        if (response.data.conversation_id) {
                            self.currentConversationId = response.data.conversation_id;
                        }
                        
                        self.trackEvent('message_sent', {
                            user_message: message,
                            bot_response: botResponse,
                            response_time: response.data.response_time,
                            tokens_used: response.data.tokens_used
                        });
                        
                        // Hide suggestions after successful response
                        self.hideSuggestions();
                    } else {
                        self.showError(response.data.message || self.config.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    self.hideTypingIndicator();
                    self.setInputState(true);
                    
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    
                    // Retry logic
                    if (self.retryCount < self.maxRetries) {
                        self.retryCount++;
                        setTimeout(function() {
                            self.sendMessageToServer(message);
                        }, 2000 * self.retryCount); // Exponential backoff
                    } else {
                        var errorMessage = status === 'timeout' ? 
                            'Request timed out. Please try again.' : 
                            self.config.strings.networkError;
                        self.showError(errorMessage);
                        self.retryCount = 0;
                    }
                }
            });
        },

        // UI Message Methods
        addUserMessage: function(message) {
            var messageId = this.generateRandomString(8);
            var html = this.buildMessageHtml(message, 'user', Date.now(), messageId);
            this.$messages.append(html);
            this.scrollToBottom();
            this.animateMessageAppearance(messageId);
            this.messageCount++;
        },

        addBotMessage: function(message, messageId) {
            messageId = messageId || this.generateRandomString(8);
            var html = this.buildMessageHtml(message, 'bot', Date.now(), messageId);
            this.$messages.append(html);
            this.scrollToBottom();
            this.animateMessageAppearance(messageId);
        },

        buildMessageHtml: function(message, sender, timestamp, messageId) {
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
            return `
                <div class="message-rating" data-message-id="${messageId}">
                    <span class="rating-label">Rate this response:</span>
                    <div class="quick-rating">
                        <button class="quick-rating-btn positive" data-rating="1" data-conversation-id="${messageId}" title="Helpful">
                            😊
                        </button>
                        <button class="quick-rating-btn negative" data-rating="-1" data-conversation-id="${messageId}" title="Not Helpful">
                            😞
                        </button>
                    </div>
                </div>
            `;
        },

        // Rating System Methods
        showEndOfConversationRating: function() {
            console.log('Showing end of conversation rating');
            
            // Don't show if already exists
            if ($('.end-conversation-rating').length > 0) {
                console.log('Rating already exists');
                return;
            }
            
            var ratingHtml = `
                <div class="ai-chatbot-message bot-message end-conversation-rating">
                    <div class="message-content">
                        <div class="message-bubble rating-bubble">
                            <h4>How was your experience?</h4>
                            <div class="conversation-rating">
                                <div class="rating-smilies">
                                    <button class="smiley-btn" data-rating="1" title="Very Poor">
                                        <span class="smiley">😡</span>
                                        <span class="smiley-label">Very Poor</span>
                                    </button>
                                    <button class="smiley-btn" data-rating="2" title="Poor">
                                        <span class="smiley">😞</span>
                                        <span class="smiley-label">Poor</span>
                                    </button>
                                    <button class="smiley-btn" data-rating="3" title="Okay">
                                        <span class="smiley">😐</span>
                                        <span class="smiley-label">Okay</span>
                                    </button>
                                    <button class="smiley-btn" data-rating="4" title="Good">
                                        <span class="smiley">😊</span>
                                        <span class="smiley-label">Good</span>
                                    </button>
                                    <button class="smiley-btn" data-rating="5" title="Excellent">
                                        <span class="smiley">🤩</span>
                                        <span class="smiley-label">Excellent</span>
                                    </button>
                                </div>
                                <div class="rating-feedback" style="display: none;">
                                    <textarea placeholder="Tell us more about your experience (optional)..." class="feedback-text" maxlength="500"></textarea>
                                    <div class="rating-actions">
                                        <button class="submit-rating-btn">Submit Feedback</button>
                                        <button class="skip-rating-btn">Skip</button>
                                    </div>
                                </div>
                                <div class="rating-thank-you" style="display: none;">
                                    <div class="thank-you-message">
                                        <span class="thank-you-emoji">🙏</span>
                                        <p>Thank you for your feedback!</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.$messages.append(ratingHtml);
            this.scrollToBottom();
            
            console.log('End of conversation rating added to DOM');
        },

        submitRating: function(conversationId, rating) {
            var self = this;
            console.log('Submitting rating:', conversationId, rating);
            
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
                    console.log('Rating response:', response);
                    if (response.success) {
                        var $rating = $('[data-message-id="' + conversationId + '"] .message-rating');
                        var thankYouEmoji = rating === 1 ? '🙂' : '😔';
                        $rating.html(`
                            <div class="rating-thanks">
                                <span class="thank-emoji">${thankYouEmoji}</span>
                                <span class="thank-text">${self.config.strings.thankYou}</span>
                            </div>
                        `);
                        
                        self.trackEvent('message_rated', { rating: rating });
                    }
                },
                error: function() {
                    console.log('Failed to submit rating');
                }
            });
        },

        submitConversationRating: function(rating, feedback) {
            var self = this;
            console.log('Submitting conversation rating:', rating, feedback);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_conversation_rating',
                    conversation_id: this.currentConversationId,
                    rating: rating,
                    feedback: feedback || '',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    console.log('Conversation rating response:', response);
                    if (response.success) {
                        // Show thank you message
                        $('.rating-smilies, .rating-feedback').slideUp(200);
                        $('.rating-thank-you').slideDown(300);
                        
                        // Auto-hide after 3 seconds
                        setTimeout(function() {
                            $('.end-conversation-rating').fadeOut(500);
                        }, 3000);
                        
                        self.trackEvent('conversation_rated', { 
                            rating: rating, 
                            has_feedback: feedback.length > 0 
                        });
                    }
                },
                error: function() {
                    console.log('Failed to submit conversation rating');
                }
            });
        },

        checkForConversationEnd: function(botMessage) {
            var endPhrases = ['goodbye', 'bye', 'have a great day', 'anything else', 'help you with anything else'];
            var messageText = botMessage.toLowerCase();
            
            if (endPhrases.some(phrase => messageText.includes(phrase))) {
                setTimeout(() => {
                    this.showEndOfConversationRating();
                }, 2000);
            }
        },

        closeChat: function() {
            if (!$('.end-conversation-rating').length && this.messageCount > 2) {
                this.showEndOfConversationRating();
                
                // Give user time to rate, then close
                setTimeout(() => {
                    this.hideWidget();
                }, 30000);
            } else {
                this.hideWidget();
            }
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

        hideWidget: function() {
            if (this.$widget.length) {
                this.$widget.hide();
            }
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
                            self.$messages.find('.ai-chatbot-message:not(.typing-indicator)').remove();
                            self.currentSessionId = response.data.new_session_id;
                            self.currentConversationId = self.generateConversationId();
                            self.messageHistory = [];
                            self.messageCount = 0;
                            
                            // Update localStorage
                            localStorage.setItem('ai_chatbot_session', self.currentSessionId);
                            
                            // Show welcome message if configured
                            if (self.config.welcomeMessage) {
                                self.addBotMessage(self.config.welcomeMessage);
                            }
                            
                            // Reset inactivity timer
                            self.startInactivityTimer();
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
            console.log('Event tracked:', eventType, data);
            
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
        console.log('DOM ready, checking for chatbot config...');
        
        // Initialize chatbot if config exists
        if (typeof ai_chatbot_ajax !== 'undefined') {
            console.log('Found chatbot config, initializing...');
            window.AIChatbot.init({
                ajaxUrl: ai_chatbot_ajax.ajax_url,
                nonce: ai_chatbot_ajax.nonce,
                sessionId: ai_chatbot_ajax.session_id || '',
                settings: ai_chatbot_ajax.settings || {},
                strings: ai_chatbot_ajax.strings || {}
            });
        } else {
            console.log('No chatbot config found');
        }
        
        // Test function for immediate rating display (for debugging)
        setTimeout(function() {
            console.log('Testing rating system availability...');
            if (window.AIChatbot && window.AIChatbot.showEndOfConversationRating) {
                // Uncomment next line to test rating immediately
                // window.AIChatbot.showEndOfConversationRating();
            }
        }, 3000);
    });

})(jQuery);