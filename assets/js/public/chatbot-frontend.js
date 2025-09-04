/**
 * AI Chatbot Frontend JavaScript
 * Updated for latest AI models and improved functionality
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
                    online: 'Online',
                    offline: 'Offline'
                },
                debug: false
            }, config || {});

            this.initialized = true;
            this.bindEvents();
            this.initializeWidget();
            
            if (this.config.debug) {
                console.log('AI Chatbot initialized:', this.config);
            }
        },

        // Initialize main widget
        initializeWidget: function() {
            if ($('#ai-chatbot-widget').length) {
                this.widget = new ChatbotWidget(this.config);
                this.widget.init();
            }
        },

        // Bind global events
        bindEvents: function() {
            var self = this;

            // Handle page visibility changes
            $(document).on('visibilitychange', function() {
                if (document.hidden) {
                    self.onPageHidden();
                } else {
                    self.onPageVisible();
                }
            });

            // Handle window resize
            $(window).on('resize', function() {
                self.onWindowResize();
            });

            // Handle shortcode chatbots
            $('.ai-chatbot-shortcode').each(function() {
                self.initializeShortcode($(this));
            });

            // Handle trigger buttons
            $(document).on('click', '[data-chatbot-trigger]', function(e) {
                e.preventDefault();
                self.openChatbot();
            });

            // Handle escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.widget && self.widget.isOpen) {
                    self.widget.close();
                }
            });
        },

        // Initialize shortcode instances
        initializeShortcode: function($element) {
            var config = $element.data('chatbot-config') || {};
            var shortcodeInstance = new ChatbotShortcode($element, $.extend({}, this.config, config));
            shortcodeInstance.init();
        },

        // Open main chatbot
        openChatbot: function() {
            if (this.widget) {
                this.widget.open();
            }
        },

        // Close main chatbot
        closeChatbot: function() {
            if (this.widget) {
                this.widget.close();
            }
        },

        // Page visibility handlers
        onPageHidden: function() {
            if (this.widget) {
                this.widget.onPageHidden();
            }
        },

        onPageVisible: function() {
            if (this.widget) {
                this.widget.onPageVisible();
            }
        },

        onWindowResize: function() {
            if (this.widget) {
                this.widget.onResize();
            }
        },

        // Utility functions
        utils: {
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

            generateId: function() {
                return 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            },

            detectModelCapabilities: function(provider, model) {
                var capabilities = {
                    supportsVision: false,
                    supportsFunction: false,
                    supportsReasoning: false,
                    isLatestModel: false
                };

                if (provider === 'openai') {
                    if (model.includes('gpt-4o') || model.includes('gpt-4-turbo')) {
                        capabilities.supportsVision = true;
                        capabilities.supportsFunction = true;
                    }
                    if (model.includes('o1-')) {
                        capabilities.supportsReasoning = true;
                    }
                    if (model === 'gpt-4o' || model === 'o1-preview') {
                        capabilities.isLatestModel = true;
                    }
                } else if (provider === 'claude') {
                    if (model.includes('claude-3')) {
                        capabilities.supportsVision = true;
                        capabilities.supportsFunction = true;
                    }
                    if (model.includes('20241022')) {
                        capabilities.isLatestModel = true;
                    }
                } else if (provider === 'gemini') {
                    if (model.includes('gemini-2.0') || model.includes('gemini-1.5')) {
                        capabilities.supportsVision = true;
                        capabilities.supportsFunction = true;
                    }
                    if (model === 'gemini-2.0-flash') {
                        capabilities.isLatestModel = true;
                    }
                }

                return capabilities;
            }
        }
    };

    // ChatbotWidget class
    function ChatbotWidget(config) {
        this.config = config;
        this.$widget = $('#ai-chatbot-widget');
        this.$container = $('#ai-chatbot-container');
        this.$toggle = $('#ai-chatbot-toggle');
        this.$messages = $('#ai-chatbot-messages');
        this.$input = $('#ai-chatbot-input');
        this.$form = $('#ai-chatbot-form');
        this.$typing = $('#ai-chatbot-typing');
        
        this.isOpen = false;
        this.isMinimized = false;
        this.messageHistory = [];
        this.currentSessionId = config.sessionId || 'session_' + Date.now();
        this.isTyping = false;
        this.retryCount = 0;
        this.maxRetries = 3;
    }

    ChatbotWidget.prototype = {
        init: function() {
            this.bindEvents();
            this.loadSuggestions();
            this.checkStatus();
            this.setupAutoResize();
            this.loadConversationHistory();
            this.setupAccessibility();
        },

        bindEvents: function() {
            var self = this;

            // Toggle button
            this.$toggle.on('click', function() {
                self.toggle();
            });

            // Form submission
            this.$form.on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Input handling with improved UX
            this.$input.on('input', function() {
                self.onInputChange();
                self.handleTypingIndicator();
            });

            this.$input.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                } else if (e.key === 'Escape') {
                    self.close();
                }
            });

            // Control buttons
            $(document).on('click', '.minimize-btn', function() {
                self.minimize();
            });

            $(document).on('click', '.close-btn', function() {
                self.close();
            });

            // Quick actions with improved error handling
            $(document).on('click', '[data-action]', function() {
                var action = $(this).data('action');
                self.handleQuickAction(action);
            });

            // Suggestion clicks
            $(document).on('click', '.suggestion-chip, .starter-btn', function() {
                var message = $(this).data('message') || $(this).text();
                self.$input.val(message);
                self.sendMessage();
            });

            // Rating buttons with analytics
            $(document).on('click', '.rating-btn', function() {
                var rating = $(this).data('rating');
                var conversationId = $(this).data('conversation-id');
                self.submitRating(conversationId, rating);
            });

            // File upload (if enabled)
            if (this.config.settings.enableFileUpload) {
                this.initFileUpload();
            }

            // Network status monitoring
            $(window).on('online offline', function() {
                self.handleNetworkChange();
            });
        },

        toggle: function() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },

        open: function() {
            this.$container.show();
            this.$toggle.addClass('active');
            this.$toggle.find('.toggle-icon-chat').hide();
            this.$toggle.find('.toggle-icon-close').show();
            this.isOpen = true;
            this.focusInput();
            this.scrollToBottom();
            
            // Analytics
            this.trackEvent('chatbot_opened');
        },

        close: function() {
            this.$container.hide();
            this.$toggle.removeClass('active');
            this.$toggle.find('.toggle-icon-chat').show();
            this.$toggle.find('.toggle-icon-close').hide();
            this.isOpen = false;
            this.isMinimized = false;
            
            // Analytics
            this.trackEvent('chatbot_closed');
        },

        minimize: function() {
            this.$container.addClass('minimized');
            this.isMinimized = true;
        },

        sendMessage: function() {
            var message = this.$input.val().trim();
            
            if (!message) {
                return;
            }

            if (message.length > this.config.settings.maxMessageLength) {
                this.showError(this.config.strings.messageTooLong);
                return;
            }

            // Check network status
            if (!navigator.onLine) {
                this.showError(this.config.strings.networkError);
                return;
            }

            this.addUserMessage(message);
            this.$input.val('');
            this.updateInputState();
            this.showTypingIndicator();
            this.retryCount = 0;

            var self = this;
            
            this.makeApiRequest(message)
                .done(function(response) {
                    self.handleApiSuccess(response);
                })
                .fail(function(xhr, status, error) {
                    self.handleApiError(xhr, status, error, message);
                });
        },

        makeApiRequest: function(message) {
            return $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_message',
                    message: message,
                    session_id: this.currentSessionId,
                    page_url: window.location.href,
                    nonce: this.config.nonce
                },
                timeout: 30000 // 30 second timeout
            });
        },

        handleApiSuccess: function(response) {
            this.hideTypingIndicator();
            
            if (response.success) {
                this.addBotMessage(response.data.response);
                this.currentSessionId = response.data.session_id;
                
                // Track successful interaction
                this.trackEvent('message_sent_success');
            } else {
                this.showError(response.data.message || this.config.strings.error);
                this.trackEvent('message_sent_error', { error: response.data.message });
            }
        },

        handleApiError: function(xhr, status, error, originalMessage) {
            this.hideTypingIndicator();
            
            if (this.retryCount < this.maxRetries && status !== 'abort') {
                this.retryCount++;
                setTimeout(() => {
                    this.showTypingIndicator();
                    this.makeApiRequest(originalMessage)
                        .done((response) => this.handleApiSuccess(response))
                        .fail((xhr, status, error) => this.handleApiError(xhr, status, error, originalMessage));
                }, 1000 * this.retryCount); // Exponential backoff
                
                return;
            }
            
            var errorMessage = this.config.strings.error;
            
            if (status === 'timeout') {
                errorMessage = 'Request timed out. Please try again.';
            } else if (xhr.status === 429) {
                errorMessage = 'Too many requests. Please wait a moment.';
            } else if (xhr.status >= 500) {
                errorMessage = 'Server error. Please try again later.';
            }
            
            this.showError(errorMessage);
            this.trackEvent('message_sent_failed', { 
                status: status, 
                error: error, 
                retryCount: this.retryCount 
            });
        },

        addUserMessage: function(message) {
            var messageId = AIChatbot.utils.generateId();
            var timestamp = Date.now();
            
            var messageHtml = this.buildMessageHtml('user', message, messageId, timestamp);
            this.$messages.find('.messages-container').append(messageHtml);
            
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
            messageId = messageId || AIChatbot.utils.generateId();
            var timestamp = Date.now();
            
            var messageHtml = this.buildMessageHtml('bot', message, messageId, timestamp);
            this.$messages.find('.messages-container').append(messageHtml);
            
            this.messageHistory.push({
                id: messageId,
                sender: 'bot',
                message: message,
                timestamp: timestamp
            });
            
            this.scrollToBottom();
            
            // Add typing animation effect
            this.animateMessageAppearance(messageId);
        },

        buildMessageHtml: function(sender, message, messageId, timestamp) {
            var senderClass = sender === 'user' ? 'user-message' : 'bot-message';
            var timeString = AIChatbot.utils.formatTime(timestamp / 1000);
            var avatar = sender === 'user' ? '👤' : '🤖';
            
            var html = '<div class="ai-chatbot-message ' + senderClass + '" data-message-id="' + messageId + '">';
            html += '<div class="message-avatar">' + avatar + '</div>';
            html += '<div class="message-content">';
            html += '<div class="message-bubble">' + AIChatbot.utils.escapeHtml(message) + '</div>';
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
                }
            });
        },

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
                           '<div class="message-bubble error">' + AIChatbot.utils.escapeHtml(message) + '</div>' +
                           '</div></div>';
            
            this.$messages.find('.messages-container').append(errorHtml);
            this.scrollToBottom();
            
            // Auto-remove error after 5 seconds
            setTimeout(() => {
                $('.error-message').fadeOut();
            }, 5000);
        },

        onInputChange: function() {
            var message = this.$input.val().trim();
            var charCount = message.length;
            
            // Update character counter
            $('.char-count').text(charCount);
            
            // Update send button state
            this.updateInputState();
            
            // Handle input validation
            if (charCount > this.config.settings.maxMessageLength) {
                this.$input.addClass('error');
                $('.char-count').addClass('over-limit');
            } else {
                this.$input.removeClass('error');
                $('.char-count').removeClass('over-limit');
            }
        },

        updateInputState: function() {
            var message = this.$input.val().trim();
            var $sendBtn = $('.ai-chatbot-send-btn, #ai-chatbot-send');
            
            if (message && message.length <= this.config.settings.maxMessageLength && !this.isTyping) {
                $sendBtn.prop('disabled', false);
            } else {
                $sendBtn.prop('disabled', true);
            }
        },

        setupAutoResize: function() {
            this.$input.on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        },

        setupAccessibility: function() {
            // Add ARIA labels and roles
            this.$widget.attr('role', 'application');
            this.$widget.attr('aria-label', 'AI Chatbot');
            this.$messages.attr('role', 'log');
            this.$messages.attr('aria-live', 'polite');
            this.$input.attr('aria-label', 'Type your message');
        },

        focusInput: function() {
            if (this.isOpen && !this.isMinimized) {
                setTimeout(() => {
                    this.$input.focus();
                }, 100);
            }
        },

        scrollToBottom: function() {
            var $container = this.$messages;
            $container.scrollTop($container[0].scrollHeight);
        },

        hideSuggestions: function() {
            $('.suggested-responses, .conversation-starters').fadeOut();
        },

        loadSuggestions: function() {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_suggestions',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.suggestions) {
                        self.displaySuggestions(response.data.suggestions);
                    }
                }
            });
        },

        displaySuggestions: function(suggestions) {
            var $container = $('.suggestions-list');
            $container.empty();
            
            suggestions.forEach(function(suggestion) {
                var html = '<button class="suggestion-chip" data-message="' + 
                          AIChatbot.utils.escapeHtml(suggestion) + '">' + 
                          AIChatbot.utils.escapeHtml(suggestion) + '</button>';
                $container.append(html);
            });
        },

        checkStatus: function() {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_status_check',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateStatus(response.data);
                    }
                }
            });
        },

        updateStatus: function(status) {
            var $indicator = $('.status-indicator');
            var $text = $('.status-text');
            
            if (status.online) {
                $indicator.removeClass('offline').addClass('online');
                $text.text(this.config.strings.online);
            } else {
                $indicator.removeClass('online').addClass('offline');
                $text.text(this.config.strings.offline);
            }
        },

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
                            self.$messages.find('.messages-container').empty();
                            self.currentSessionId = response.data.new_session_id;
                            self.messageHistory = [];
                            
                            // Show welcome message again
                            if (self.config.settings.welcomeMessage) {
                                self.addBotMessage(self.config.settings.welcomeMessage);
                            }
                            
                            self.loadSuggestions();
                            self.trackEvent('conversation_cleared');
                        }
                    }
                });
            }
        },

        loadConversationHistory: function() {
            // Load previous conversation if enabled
            if (this.config.settings.enableHistory) {
                // Implementation for loading history
            }
        },

        animateMessageAppearance: function(messageId) {
            var $message = $('[data-message-id="' + messageId + '"]');
            $message.addClass('message-animate-in');
        },

        handleTypingIndicator: function() {
            // Show user typing indicator to other users if in multi-user mode
            clearTimeout(this.typingTimeout);
            this.typingTimeout = setTimeout(() => {
                // Stop typing indicator
            }, 1000);
        },

        handleNetworkChange: function() {
            if (navigator.onLine) {
                $('.network-status').removeClass('offline').addClass('online');
            } else {
                $('.network-status').removeClass('online').addClass('offline');
            }
        },

        trackEvent: function(event, data) {
            if (this.config.debug) {
                console.log('Chatbot Event:', event, data);
            }
            
            // Send analytics if enabled
            if (typeof gtag !== 'undefined') {
                gtag('event', event, {
                    event_category: 'ai_chatbot',
                    ...data
                });
            }
        },

        onPageHidden: function() {
            // Pause any active timers or animations
            clearTimeout(this.typingTimeout);
        },

        onPageVisible: function() {
            // Resume any paused functionality
            if (this.isOpen) {
                this.checkStatus();
            }
        },

        onResize: function() {
            // Handle responsive behavior
            if (this.isOpen) {
                this.scrollToBottom();
            }
        }
    };

    // ChatbotShortcode class for shortcode instances
    function ChatbotShortcode($element, config) {
        this.$element = $element;
        this.config = config;
        this.type = config.type || 'inline';
    }

    ChatbotShortcode.prototype = {
        init: function() {
            switch (this.type) {
                case 'inline':
                    this.initInline();
                    break;
                case 'popup':
                    this.initPopup();
                    break;
                case 'button':
                    this.initButton();
                    break;
            }
        },

        initInline: function() {
            // Initialize inline chatbot functionality
            var widget = new ChatbotWidget(this.config);
            widget.init();
        },

        initPopup: function() {
            // Initialize popup chatbot functionality
            var self = this;
            
            this.$element.find('.ai-chatbot-popup-button').on('click', function() {
                self.openPopup();
            });
        },

        initButton: function() {
            // Initialize button functionality
            var self = this;
            
            this.$element.on('click', function() {
                AIChatbot.openChatbot();
            });
        },

        openPopup: function() {
            var $modal = $('#' + this.$element.data('chatbot-target'));
            $modal.show();
            
            // Initialize chatbot in popup if not already done
            if (!$modal.data('initialized')) {
                var widget = new ChatbotWidget(this.config);
                widget.init();
                $modal.data('initialized', true);
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Auto-initialize if config is available
        if (typeof aiChatbotConfig !== 'undefined') {
            AIChatbot.init(aiChatbotConfig);
        }
        
        // Initialize if ai_chatbot_ajax is available (backward compatibility)
        if (typeof ai_chatbot_ajax !== 'undefined') {
            AIChatbot.init(ai_chatbot_ajax);
        }
    });

})(jQuery);