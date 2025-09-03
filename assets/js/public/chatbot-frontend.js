/**
 * AI Website Chatbot - Frontend JavaScript
 * 
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Chatbot class definition
    class AIChatbot {
        constructor() {
            this.config = window.aiChatbotConfig || {};
            this.isOpen = false;
            this.isMinimized = false;
            this.isTyping = false;
            this.messageQueue = [];
            this.rateLimitTimer = null;
            this.conversationHistory = [];
            
            this.init();
        }

        /**
         * Initialize the chatbot
         */
        init() {
            this.bindEvents();
            this.setupWidget();
            this.handleConsentForm();
            this.loadConversationHistory();
            
            // Show widget after initialization
            $('#ai-chatbot-widget').fadeIn(300);
            
            // Log initialization for debugging
            this.debugLog('Chatbot initialized', this.config);
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            const self = this;

            // Toggle chatbot
            $(document).on('click', '#ai-chatbot-toggle', function(e) {
                e.preventDefault();
                self.toggleChatbot();
            });

            // Minimize chatbot
            $(document).on('click', '#ai-chatbot-minimize', function(e) {
                e.preventDefault();
                self.minimizeChatbot();
            });

            // Send message
            $(document).on('click', '#ai-chatbot-send', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Handle enter key in input
            $(document).on('keypress', '#ai-chatbot-input', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // Auto-resize textarea
            $(document).on('input', '#ai-chatbot-input', function() {
                self.autoResizeTextarea(this);
                self.updateCharacterCount();
            });

            // Handle suggestion clicks
            $(document).on('click', '.ai-chatbot-suggestion', function(e) {
                e.preventDefault();
                const suggestion = $(this).text();
                $('#ai-chatbot-input').val(suggestion);
                self.sendMessage();
            });

            // Handle rating clicks
            $(document).on('click', '.ai-chatbot-rating-btn', function(e) {
                e.preventDefault();
                const rating = $(this).data('rating');
                const conversationId = $(this).data('conversation-id');
                self.submitRating(conversationId, rating);
            });

            // Handle consent checkbox
            $(document).on('change', '#ai-chatbot-consent', function() {
                const isChecked = $(this).is(':checked');
                $('#ai-chatbot-send').prop('disabled', !isChecked);
                $('#ai-chatbot-input').prop('disabled', !isChecked);
            });

            // Close on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.closeChatbot();
                }
            });

            // Handle window resize
            $(window).on('resize', function() {
                self.adjustForMobile();
            });
        }

        /**
         * Setup the chatbot widget
         */
        setupWidget() {
            this.adjustForMobile();
            this.loadSuggestions();
            
            // Set initial focus
            if (this.isOpen) {
                $('#ai-chatbot-input').focus();
            }
        }

        /**
         * Toggle chatbot open/closed
         */
        toggleChatbot() {
            if (this.isOpen) {
                this.closeChatbot();
            } else {
                this.openChatbot();
            }
        }

        /**
         * Open chatbot
         */
        openChatbot() {
            this.isOpen = true;
            this.isMinimized = false;
            
            $('#ai-chatbot-container').slideDown(300);
            $('#ai-chatbot-toggle .ai-chatbot-icon').hide();
            $('#ai-chatbot-toggle .ai-chatbot-close-icon').show();
            
            // Focus input after animation
            setTimeout(() => {
                if (!this.requiresConsent() || this.hasConsent()) {
                    $('#ai-chatbot-input').focus();
                }
            }, 350);
            
            // Mark as opened for analytics
            this.trackEvent('chatbot_opened');
            
            // Scroll to bottom
            this.scrollToBottom();
        }

        /**
         * Close chatbot
         */
        closeChatbot() {
            this.isOpen = false;
            
            $('#ai-chatbot-container').slideUp(300);
            $('#ai-chatbot-toggle .ai-chatbot-close-icon').hide();
            $('#ai-chatbot-toggle .ai-chatbot-icon').show();
            
            this.trackEvent('chatbot_closed');
        }

        /**
         * Minimize chatbot
         */
        minimizeChatbot() {
            this.isMinimized = true;
            $('#ai-chatbot-container').slideUp(200);
            $('#ai-chatbot-toggle .ai-chatbot-close-icon').hide();
            $('#ai-chatbot-toggle .ai-chatbot-icon').show();
        }

        /**
         * Send message to AI
         */
        sendMessage() {
            const input = $('#ai-chatbot-input');
            const message = input.val().trim();
            
            if (!message) {
                this.showError(this.config.strings.error);
                return;
            }

            // Check consent
            if (this.requiresConsent() && !this.hasConsent()) {
                this.showError(this.config.strings.consentRequired);
                return;
            }

            // Check message length
            if (message.length > this.config.settings.maxMessageLength) {
                this.showError(this.config.strings.messageTooLong);
                return;
            }

            // Check rate limiting
            if (this.rateLimitTimer) {
                this.showError(this.config.strings.rateLimitExceeded);
                return;
            }

            // Add user message to chat
            this.addMessage(message, 'user');
            
            // Clear input
            input.val('').trigger('input');
            
            // Show typing indicator
            this.showTyping();
            
            // Send to server
            this.sendToServer(message);
            
            // Set rate limit timer
            this.setRateLimit();
        }

        /**
         * Send message to server
         */
        sendToServer(message) {
            const self = this;
            
            const data = {
                action: 'ai_chatbot_send_message',
                nonce: this.config.nonce,
                message: message,
                session_id: this.config.sessionId,
                page_url: this.config.pageUrl,
                conversation_history: this.conversationHistory.slice(-5) // Last 5 exchanges
            };

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                timeout: 30000,
                success: function(response) {
                    self.hideTyping();
                    
                    if (response.success) {
                        self.handleSuccessResponse(response.data);
                    } else {
                        self.handleErrorResponse(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    self.hideTyping();
                    self.handleAjaxError(xhr, status, error);
                }
            });
        }

        /**
         * Handle successful AI response
         */
        handleSuccessResponse(data) {
            // Add AI response to chat
            this.addMessage(data.response, 'bot', data.conversation_id);
            
            // Update conversation history
            this.conversationHistory.push({
                user_message: this.getLastUserMessage(),
                bot_response: data.response
            });
            
            // Show rating if enabled
            if (data.enable_rating && this.config.settings.enableRating) {
                this.addRatingButtons(data.conversation_id);
            }
            
            // Track successful interaction
            this.trackEvent('message_sent');
        }

        /**
         * Handle error response
         */
        handleErrorResponse(data) {
            const errorMessage = data.message || this.config.strings.error;
            this.showError(errorMessage);
            this.trackEvent('message_error', { error: errorMessage });
        }

        /**
         * Handle AJAX errors
         */
        handleAjaxError(xhr, status, error) {
            let errorMessage = this.config.strings.networkError;
            
            if (status === 'timeout') {
                errorMessage = this.config.strings.timeout || 'Request timed out';
            } else if (xhr.status === 429) {
                errorMessage = this.config.strings.rateLimitExceeded;
            } else if (xhr.status >= 500) {
                errorMessage = 'Server error. Please try again later.';
            }
            
            this.showError(errorMessage);
            this.debugLog('AJAX Error:', { xhr, status, error });
        }

        /**
         * Add message to chat
         */
        addMessage(message, sender, conversationId = null) {
            const messagesContainer = $('#ai-chatbot-messages');
            const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            const messageHtml = `
                <div class="ai-chatbot-message ai-chatbot-message-${sender}" data-conversation-id="${conversationId || ''}">
                    <div class="ai-chatbot-message-content">
                        ${this.formatMessage(message)}
                    </div>
                    <div class="ai-chatbot-message-time">
                        ${timestamp}
                    </div>
                </div>
            `;
            
            messagesContainer.append(messageHtml);
            this.scrollToBottom();
            
            // Animate message in
            messagesContainer.find('.ai-chatbot-message').last().hide().fadeIn(300);
        }

        /**
         * Format message content
         */
        formatMessage(message) {
            // Convert URLs to links
            message = message.replace(
                /(https?:\/\/[^\s]+)/g,
                '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
            );
            
            // Convert line breaks
            message = message.replace(/\n/g, '<br>');
            
            // Basic markdown support
            message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            message = message.replace(/\*(.*?)\*/g, '<em>$1</em>');
            
            return message;
        }

        /**
         * Show typing indicator
         */
        showTyping() {
            this.isTyping = true;
            $('#ai-chatbot-typing').show();
            $('#ai-chatbot-send').prop('disabled', true);
            this.scrollToBottom();
        }

        /**
         * Hide typing indicator
         */
        hideTyping() {
            this.isTyping = false;
            $('#ai-chatbot-typing').hide();
            $('#ai-chatbot-send').prop('disabled', false);
        }

        /**
         * Show error message
         */
        showError(message) {
            this.addMessage(`⚠️ ${message}`, 'system');
        }

        /**
         * Add rating buttons to last message
         */
        addRatingButtons(conversationId) {
            const lastMessage = $('#ai-chatbot-messages .ai-chatbot-message-bot').last();
            
            if (lastMessage.find('.ai-chatbot-rating').length === 0) {
                const ratingHtml = `
                    <div class="ai-chatbot-rating">
                        <span class="ai-chatbot-rating-text">${this.config.strings.ratePositive}</span>
                        <button class="ai-chatbot-rating-btn ai-chatbot-rating-positive" data-rating="1" data-conversation-id="${conversationId}">
                            👍 ${this.config.strings.rateThumbsUp}
                        </button>
                        <button class="ai-chatbot-rating-btn ai-chatbot-rating-negative" data-rating="-1" data-conversation-id="${conversationId}">
                            👎 ${this.config.strings.rateThumbsDown}
                        </button>
                    </div>
                `;
                
                lastMessage.append(ratingHtml);
            }
        }

        /**
         * Submit rating
         */
        submitRating(conversationId, rating) {
            const self = this;
            const ratingContainer = $(`.ai-chatbot-rating button[data-conversation-id="${conversationId}"]`).parent();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_send_rating',
                    nonce: this.config.nonce,
                    conversation_id: conversationId,
                    rating: rating
                },
                success: function(response) {
                    if (response.success) {
                        ratingContainer.html(`<span class="ai-chatbot-rating-thanks">${self.config.strings.thankYou}</span>`);
                        self.trackEvent('rating_submitted', { rating: rating });
                    }
                },
                error: function() {
                    self.debugLog('Rating submission failed');
                }
            });
        }

        /**
         * Load conversation suggestions
         */
        loadSuggestions() {
            const self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_suggestions',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.suggestions) {
                        self.renderSuggestions(response.data.suggestions);
                    }
                }
            });
        }

        /**
         * Render suggestion buttons
         */
        renderSuggestions(suggestions) {
            const messagesContainer = $('#ai-chatbot-messages');
            let suggestionsHtml = '<div class="ai-chatbot-suggestions">';
            
            suggestions.forEach(function(suggestion) {
                suggestionsHtml += `<button class="ai-chatbot-suggestion">${suggestion}</button>`;
            });
            
            suggestionsHtml += '</div>';
            messagesContainer.find('.ai-chatbot-welcome-message').after(suggestionsHtml);
        }

        /**
         * Auto-resize textarea
         */
        autoResizeTextarea(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        /**
         * Update character count
         */
        updateCharacterCount() {
            const input = $('#ai-chatbot-input');
            const charCount = $('#ai-chatbot-char-count');
            const currentLength = input.val().length;
            const maxLength = this.config.settings.maxMessageLength;
            
            charCount.text(currentLength);
            
            if (currentLength > maxLength * 0.9) {
                charCount.parent().show();
                if (currentLength >= maxLength) {
                    charCount.parent().addClass('ai-chatbot-char-limit-exceeded');
                }
            } else {
                charCount.parent().hide().removeClass('ai-chatbot-char-limit-exceeded');
            }
        }

        /**
         * Scroll to bottom of messages
         */
        scrollToBottom() {
            const messagesContainer = $('#ai-chatbot-messages');
            messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
        }

        /**
         * Handle consent form
         */
        handleConsentForm() {
            if (this.requiresConsent()) {
                $('#ai-chatbot-send').prop('disabled', !this.hasConsent());
                $('#ai-chatbot-input').prop('disabled', !this.hasConsent());
            }
        }

        /**
         * Check if consent is required
         */
        requiresConsent() {
            return this.config.privacy && this.config.privacy.consentRequired;
        }

        /**
         * Check if user has given consent
         */
        hasConsent() {
            return $('#ai-chatbot-consent').is(':checked');
        }

        /**
         * Adjust layout for mobile
         */
        adjustForMobile() {
            const isMobile = window.innerWidth <= 768;
            const widget = $('#ai-chatbot-widget');
            
            if (isMobile) {
                widget.addClass('ai-chatbot-mobile');
            } else {
                widget.removeClass('ai-chatbot-mobile');
            }
        }

        /**
         * Set rate limit timer
         */
        setRateLimit() {
            const self = this;
            this.rateLimitTimer = setTimeout(function() {
                self.rateLimitTimer = null;
            }, 2000); // 2 second rate limit
        }

        /**
         * Load conversation history from localStorage
         */
        loadConversationHistory() {
            // Skip localStorage as it's not supported in Claude.ai artifacts
            // In production, this would load from server or browser storage
            this.debugLog('Conversation history loading skipped');
        }

        /**
         * Get last user message
         */
        getLastUserMessage() {
            const userMessages = $('#ai-chatbot-messages .ai-chatbot-message-user');
            return userMessages.last().find('.ai-chatbot-message-content').text().trim();
        }

        /**
         * Track events for analytics
         */
        trackEvent(event, data = {}) {
            if (this.config.debug) {
                this.debugLog('Event:', event, data);
            }
            
            // Send to server for analytics if enabled
            if (this.config.settings.enableAnalytics) {
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ai_chatbot_track_event',
                        nonce: this.config.nonce,
                        event: event,
                        data: data,
                        session_id: this.config.sessionId
                    }
                });
            }
        }

        /**
         * Debug logging
         */
        debugLog(...args) {
            if (this.config.debug) {
                console.log('[AI Chatbot]', ...args);
            }
        }

        /**
         * Clear conversation
         */
        clearConversation() {
            const self = this;
            
            if (confirm('Are you sure you want to clear this conversation?')) {
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ai_chatbot_clear_conversation',
                        nonce: this.config.nonce,
                        session_id: this.config.sessionId
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#ai-chatbot-messages').empty();
                            self.conversationHistory = [];
                            self.config.sessionId = response.data.new_session_id;
                            
                            // Re-add welcome message
                            self.addMessage(self.config.settings.welcomeMessage, 'bot');
                            self.loadSuggestions();
                        }
                    }
                });
            }
        }

        /**
         * Export conversation data
         */
        exportConversation() {
            const self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_export_data',
                    nonce: this.config.nonce,
                    session_id: this.config.sessionId
                },
                success: function(response) {
                    if (response.success) {
                        const dataStr = JSON.stringify(response.data.data, null, 2);
                        const dataBlob = new Blob([dataStr], { type: 'application/json' });
                        const url = URL.createObjectURL(dataBlob);
                        
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = response.data.filename;
                        link.click();
                        
                        URL.revokeObjectURL(url);
                    }
                }
            });
        }
    }

    // Initialize chatbot when document is ready
    $(document).ready(function() {
        if (typeof window.aiChatbotConfig !== 'undefined') {
            window.aiChatbotInstance = new AIChatbot();
        }
    });

    // Expose chatbot to global scope for external access
    window.AIChatbot = AIChatbot;

})(jQuery);