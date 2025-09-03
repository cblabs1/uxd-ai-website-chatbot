/**
 * AI Chatbot Frontend JavaScript
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
                settings: {},
                strings: {},
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
            this.widget = new ChatbotWidget(this.config);
            this.widget.init();
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
        },

        // Initialize shortcode instances
        initializeShortcode: function($element) {
            var config = $element.data('chatbot-config') || {};
            var shortcodeInstance = new ChatbotShortcode($element, config);
            shortcodeInstance.init();
        },

        // Open main chatbot
        openChatbot: function() {
            if (this.widget) {
                this.widget.open();
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

        // Window resize handler
        onWindowResize: function() {
            if (this.widget) {
                this.widget.onResize();
            }
        },

        // Utility methods
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

            formatTime: function(date) {
                return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            },

            generateId: function() {
                return 'ai-' + Math.random().toString(36).substr(2, 9);
            },

            debounce: function(func, wait, immediate) {
                var timeout;
                return function() {
                    var context = this, args = arguments;
                    var later = function() {
                        timeout = null;
                        if (!immediate) func.apply(context, args);
                    };
                    var callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func.apply(context, args);
                };
            },

            throttle: function(func, limit) {
                var inThrottle;
                return function() {
                    var args = arguments;
                    var context = this;
                    if (!inThrottle) {
                        func.apply(context, args);
                        inThrottle = true;
                        setTimeout(function() { inThrottle = false; }, limit);
                    }
                };
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
        this.currentSessionId = config.sessionId;
        this.isTyping = false;
    }

    ChatbotWidget.prototype = {
        init: function() {
            this.bindEvents();
            this.loadSuggestions();
            this.checkStatus();
            this.setupAutoResize();
            this.loadConversationHistory();
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

            // Input handling
            this.$input.on('input', function() {
                self.onInputChange();
            });

            this.$input.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // Control buttons
            $('.minimize-btn').on('click', function() {
                self.minimize();
            });

            $('.close-btn').on('click', function() {
                self.close();
            });

            // Quick actions
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

            // Rating buttons
            $(document).on('click', '.rating-btn', function() {
                var rating = $(this).data('rating');
                var conversationId = $(this).data('conversation-id');
                self.submitRating(conversationId, rating);
            });

            // File upload
            if (this.config.settings.enableFileUpload) {
                this.initFileUpload();
            }
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
        },

        close: function() {
            this.$container.hide();
            this.$toggle.removeClass('active');
            this.$toggle.find('.toggle-icon-chat').show();
            this.$toggle.find('.toggle-icon-close').hide();
            this.isOpen = false;
            this.isMinimized = false;
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

            this.addUserMessage(message);
            this.$input.val('');
            this.updateInputState();
            this.showTypingIndicator();

            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_message',
                    message: message,
                    session_id: this.currentSessionId,
                    page_url: window.location.href,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.hideTypingIndicator();
                    
                    if (response.success) {
                        self.addBotMessage(response.data.response, response.data.conversation_id);
                        self.currentSessionId = response.data.session_id;
                    } else {
                        self.showError(response.data.message || self.config.strings.error);
                    }
                },
                error: function() {
                    self.hideTypingIndicator();
                    self.showError(self.config.strings.networkError);
                },
                complete: function() {
                    self.focusInput();
                }
            });
        },

        addUserMessage: function(message) {
            var messageHtml = this.buildMessageHtml('user', message);
            this.$messages.find('.messages-container').append(messageHtml);
            this.scrollToBottom();
            this.hideSuggestions();
        },

        addBotMessage: function(message, conversationId) {
            var messageHtml = this.buildMessageHtml('bot', message, conversationId);
            this.$messages.find('.messages-container').append(messageHtml);
            this.scrollToBottom();
            
            // Add to history
            this.messageHistory.push({
                type: 'bot',
                message: message,
                timestamp: new Date(),
                conversationId: conversationId
            });
        },

        buildMessageHtml: function(type, message, conversationId) {
            var time = AIChatbot.utils.formatTime(new Date());
            var html = '<div class="ai-chatbot-message ' + type + '-message">';
            
            if (type === 'bot') {
                html += '<div class="message-avatar">🤖</div>';
            }
            
            html += '<div class="message-content">';
            html += '<div class="message-bubble">' + AIChatbot.utils.escapeHtml(message) + '</div>';
            html += '<div class="message-time">' + time + '</div>';
            
            if (type === 'bot' && this.config.settings.enableRating && conversationId) {
                html += this.buildRatingHtml(conversationId);
            }
            
            html += '</div></div>';
            
            return html;
        },

        buildRatingHtml: function(conversationId) {
            return '<div class="message-rating" data-conversation-id="' + conversationId + '">' +
                   '<span class="rating-label">' + this.config.strings.ratePositive + '</span>' +
                   '<button class="rating-btn positive" data-rating="1" data-conversation-id="' + conversationId + '">' +
                   '<span class="dashicons dashicons-thumbs-up"></span></button>' +
                   '<button class="rating-btn negative" data-rating="-1" data-conversation-id="' + conversationId + '">' +
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
                        var $rating = $('[data-conversation-id="' + conversationId + '"]');
                        $rating.html('<span class="rating-thanks">' + self.config.strings.thankYou + '</span>');
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
        },

        onInputChange: function() {
            var message = this.$input.val().trim();
            var charCount = message.length;
            
            // Update character counter
            $('#char-count').text(charCount);
            
            // Update send button state
            this.updateInputState();
            
            // Handle input validation
            if (charCount > this.config.settings.maxMessageLength) {
                this.$input.addClass('error');
                $('#char-count').addClass('over-limit');
            } else {
                this.$input.removeClass('error');
                $('#char-count').removeClass('over-limit');
            }
        },

        updateInputState: function() {
            var message = this.$input.val().trim();
            var $sendBtn = $('#ai-chatbot-send');
            
            if (message && message.length <= this.config.settings.maxMessageLength) {
                $sendBtn.prop('disabled', false);
            } else {
                $sendBtn.prop('disabled', true);
            }
        },

        setupAutoResize: function() {
            var self = this;
            
            this.$input.on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        },

        focusInput: function() {
            if (this.isOpen && !this.isMinimized) {
                setTimeout(function() {
                    this.$input.focus();
                }.bind(this), 100);
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
            var $container = $('#ai-suggested-responses .suggestions-list');
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
                $text.text(this.config.strings.online || 'Online');
            } else {
                $indicator.removeClass('online').addClass('offline');
                $text.text(this.config.strings.offline || 'Offline');
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
            if (confirm(this.config.strings.confirmClear || 'Clear conversation history?')) {
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
                        }
                    }
                });
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
                        var dataStr = JSON.stringify(response.data.data, null, 2);
                        var dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
                        
                        var exportFileDefaultName = response.data.filename;
                        var linkElement = document.createElement('a');
                        linkElement.setAttribute('href', dataUri);
                        linkElement.setAttribute('download', exportFileDefaultName);
                        linkElement.click();
                    }
                }
            });
        },

        refreshWidget: function() {
            location.reload();
        },

        loadConversationHistory: function() {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_history',
                    session_id: this.currentSessionId,
                    limit: 10,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.conversations) {
                        self.displayConversationHistory(response.data.conversations);
                    }
                }
            });
        },

        displayConversationHistory: function(conversations) {
            var $container = this.$messages.find('.messages-container');
            
            conversations.forEach(function(conv) {
                var userMessage = this.buildMessageHtml('user', conv.user_message);
                var botMessage = this.buildMessageHtml('bot', conv.bot_response, conv.id);
                
                $container.append(userMessage);
                $container.append(botMessage);
            }.bind(this));
            
            this.scrollToBottom();
        },

        initFileUpload: function() {
            var self = this;
            var $fileInput = $('#ai-chatbot-file-input');
            var $fileBtn = $('.file-upload-btn');
            
            $fileBtn.on('click', function() {
                $fileInput.click();
            });
            
            $fileInput.on('change', function() {
                var file = this.files[0];
                if (file) {
                    self.uploadFile(file);
                }
            });
        },

        uploadFile: function(file) {
            var self = this;
            var formData = new FormData();
            
            formData.append('action', 'ai_chatbot_file_upload');
            formData.append('file', file);
            formData.append('session_id', this.currentSessionId);
            formData.append('nonce', this.config.nonce);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.addUserMessage('📎 ' + file.name);
                        self.addBotMessage(self.config.strings.fileProcessed || 'File processed successfully.');
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError(self.config.strings.fileUploadError || 'File upload failed.');
                }
            });
        },

        onPageHidden: function() {
            // Pause any active timers or animations
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
    });

})(jQuery);
