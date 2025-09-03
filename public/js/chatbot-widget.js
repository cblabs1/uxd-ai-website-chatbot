/**
 * AI Chatbot Widget JavaScript
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Widget-specific functionality
    window.AIChatbotWidget = {
        init: function() {
            this.initWidgetInstances();
            this.bindWidgetEvents();
        },

        initWidgetInstances: function() {
            $('.ai-chatbot-widget-content').each(function() {
                var $widget = $(this);
                var config = $widget.data('chatbot-config') || {};
                
                var instance = new WidgetInstance($widget, config);
                instance.init();
                
                $widget.data('chatbot-instance', instance);
            });
        },

        bindWidgetEvents: function() {
            // Global widget events can be bound here
        }
    };

    // Widget Instance Class
    function WidgetInstance($container, config) {
        this.$container = $container;
        this.config = $.extend({
            ajaxUrl: '',
            nonce: '',
            sessionId: 'widget_' + Math.random().toString(36).substr(2, 9),
            theme: 'default'
        }, config);
        
        this.isInitialized = false;
        this.messageCount = 0;
    }

    WidgetInstance.prototype = {
        init: function() {
            if (this.isInitialized) {
                return;
            }

            this.bindEvents();
            this.setupWidget();
            this.loadInitialData();
            
            this.isInitialized = true;
        },

        bindEvents: function() {
            var self = this;

            // Form submission
            this.$container.find('.ai-chatbot-form').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Input changes
            this.$container.find('.ai-chatbot-input').on('input', function() {
                self.onInputChange($(this));
            });

            // Enter key handling
            this.$container.find('.ai-chatbot-input').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });
        },

        setupWidget: function() {
            // Apply theme
            this.$container.addClass('theme-' + this.config.theme);
            
            // Set initial state
            this.updateMessageCount(0);
            
            // Show initial status
            this.updateStatus(true);
        },

        loadInitialData: function() {
            // Load any initial data like suggestions
            this.loadSuggestions();
        },

        sendMessage: function() {
            var $input = this.$container.find('.ai-chatbot-input');
            var message = $input.val().trim();

            if (!message) {
                return;
            }

            this.addMessage(message, 'user');
            $input.val('');
            this.showTyping();

            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_message',
                    message: message,
                    session_id: this.config.sessionId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.hideTyping();
                    
                    if (response.success) {
                        self.addMessage(response.data.response, 'bot');
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.hideTyping();
                    self.showError('Network error occurred');
                }
            });
        },

        addMessage: function(message, type) {
            var $messages = this.$container.find('.ai-chatbot-messages');
            var time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            var messageHtml = '<div class="ai-chatbot-message ' + type + '-message">';
            
            if (type === 'bot') {
                messageHtml += '<div class="message-avatar">🤖</div>';
            }
            
            messageHtml += '<div class="message-content">' + this.escapeHtml(message) + '</div>';
            messageHtml += '<div class="message-time">' + time + '</div>';
            messageHtml += '</div>';
            
            $messages.append(messageHtml);
            this.scrollToBottom();
            
            this.messageCount++;
            this.updateMessageCount(this.messageCount);
        },

        showTyping: function() {
            var $typing = this.$container.find('.ai-chatbot-typing-indicator');
            $typing.show();
            this.scrollToBottom();
        },

        hideTyping: function() {
            var $typing = this.$container.find('.ai-chatbot-typing-indicator');
            $typing.hide();
        },

        showError: function(message) {
            var $messages = this.$container.find('.ai-chatbot-messages');
            var errorHtml = '<div class="ai-chatbot-message error-message">' +
                           '<div class="message-content error">' + this.escapeHtml(message) + '</div>' +
                           '</div>';
            
            $messages.append(errorHtml);
            this.scrollToBottom();
        },

        onInputChange: function($input) {
            var message = $input.val().trim();
            var $sendBtn = this.$container.find('.ai-chatbot-send-btn');
            
            if (message) {
                $sendBtn.prop('disabled', false);
            } else {
                $sendBtn.prop('disabled', true);
            }
        },

        scrollToBottom: function() {
            var $messages = this.$container.find('.ai-chatbot-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        },

        updateStatus: function(online) {
            var $indicator = this.$container.find('.status-indicator');
            
            if (online) {
                $indicator.addClass('online').removeClass('offline');
            } else {
                $indicator.addClass('offline').removeClass('online');
            }
        },

        updateMessageCount: function(count) {
            this.$container.find('.messages-count').text(count + ' messages');
        },

        loadSuggestions: function() {
            // Implementation for loading suggested responses
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
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        AIChatbotWidget.init();
    });

})(jQuery);
