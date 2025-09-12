/**
 * AI Chatbot Frontend JavaScript - COMPLETE FIXED VERSION
 * All original methods preserved, only fixing the undefined errors
 * 
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // =======================
    // SAFE ESCAPE HTML FUNCTION
    // =======================
    function safeEscapeHtml(text) {
        // Handle null, undefined, or non-string values
        if (text === null || text === undefined) {
            return '';
        }
        
        // Convert to string if it's not already a string
        if (typeof text !== 'string') {
            if (typeof text === 'object') {
                if (text.message && typeof text.message === 'string') {
                    text = text.message;
                } else {
                    try {
                        text = JSON.stringify(text);
                    } catch (e) {
                        text = '[Object]';
                    }
                }
            } else {
                text = String(text);
            }
        }
        
        // Now safely escape the string
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // =======================
    // MAIN CHATBOT OBJECT - ALL ORIGINAL METHODS PRESERVED
    // =======================
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
        feedbackMode: false,
        currentUserData: null,
        
        // DOM elements cache
        $widget: null,
        $messages: null,
        $input: null,
        $sendBtn: null,
        $typing: null,

        // Pro detection
        isPro: function() {
            return typeof window.ai_chatbot_pro_enabled !== 'undefined' && window.ai_chatbot_pro_enabled;
        },

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
                    autoResponse: true,
                    requirePreChat: true
                },
                strings: {
                    loading: 'Loading...',
                    error: 'Something went wrong. Please try again.',
                    typing: 'AI is typing...',
                    networkError: 'Network error. Please check your connection.',
                    messageTooLong: 'Message is too long.',
                    thankYou: 'Thank you for your feedback!',
                    confirmClear: 'Are you sure you want to clear the conversation?',
                    pleaseProvideEmail: 'Please provide your email to start chatting.'
                }
            }, config);

            this.currentSessionId = this.config.sessionId || this.generateRandomString(16);
            this.currentConversationId = this.generateConversationId();
            this.lastActivityTime = Date.now();
            this.initialized = true;

            console.log('AIChatbot: Initialized successfully');
        },

        // FIXED: Send message to server
        sendMessageToServer: function(message) {
            var self = this;
            
            // Validate message
            if (!message || typeof message !== 'string') {
                console.error('Invalid message:', message);
                return;
            }

            message = message.trim();
            if (!message) return;

            // Determine action
            var action = this.isPro() ? 'ai_chatbot_message_pro' : 'ai_chatbot_send_message';
            
            // Prepare request data
            var requestData = {
                action: action,
                message: message,
                nonce: this.config.nonce,
                session_id: this.currentSessionId,
                conversation_id: this.currentConversationId || ''
            };

            // Add Pro context if available
            if (this.isPro() && typeof this.buildProContext === 'function') {
                requestData.context = this.buildProContext();
            }

            // Add user data
            var userData = this.currentUserData || {};
            $.extend(requestData, userData);

            // Show typing
            if (this.isPro() && typeof this.showProTypingIndicator === 'function') {
                this.showProTypingIndicator();
            } else {
                this.showTypingIndicator();
            }
            this.setInputState(false);

            // AJAX request
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: requestData,
                timeout: 30000,
                success: function(response) {
                    console.log('Server response:', response);
                    
                    self.hideTypingIndicator();
                    self.setInputState(true);
                    
                    if (response && response.success && response.data) {
                        var botResponse = response.data.response || response.data.message || 'No response received';
                        self.currentConversationId = response.data.conversation_id || self.currentConversationId;
                        
                        // Add message with Pro enhancements if available
                        if (self.isPro() && typeof self.addProBotMessage === 'function') {
                            self.addProBotMessage(botResponse, response.data);
                        } else {
                            self.addBotMessage(botResponse);
                        }
                        
                        // Reset retry count
                        self.retryCount = 0;
                        
                        // Analytics
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'chatbot_message_sent', {
                                user_message: message,
                                bot_response: botResponse,
                                response_time: response.data.response_time,
                                tokens_used: response.data.tokens_used,
                                user_email: userData.email
                            });
                        }
                        
                        // Check for conversation end
                        self.checkForConversationEnd(botResponse);
                        
                        // Hide suggestions if method exists
                        if (typeof self.hideSuggestions === 'function') {
                            self.hideSuggestions();
                        }
                        
                        // Start inactivity timer if method exists
                        if (typeof self.startInactivityTimer === 'function') {
                            self.startInactivityTimer();
                        }
                        
                    } else {
                        console.error('Server error:', response);
                        var errorMsg = (response && response.data && response.data.message) ? 
                                     response.data.message : self.config.strings.error;
                        self.showError(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText,
                        userData: userData
                    });
                    
                    self.hideTypingIndicator();
                    self.setInputState(true);
                    
                    // Retry logic
                    if (self.retryCount < self.maxRetries) {
                        self.retryCount++;
                        setTimeout(function() {
                            self.sendMessageToServer(message);
                        }, 2000 * self.retryCount);
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

        // FIXED: Build message HTML with safe escaping
        buildMessageHtml: function(message, sender, timestamp, messageId) {
            if (!message) message = '';
            
            var senderClass = sender === 'user' ? ' ai-chatbot-message-user' : ' ai-chatbot-message-bot';
            var timeString = this.formatTime(timestamp / 1000);
            
            var html = '<div class="ai-chatbot-message' + senderClass + '" data-message-id="' + messageId + '">';
            html += '<div class="ai-chatbot-message-content">';
            html += '<div class="ai-chatbot-message-text">' + safeEscapeHtml(message) + '</div>';
            html += '<div class="ai-chatbot-message-time">';
            html += '<span class="message-time">' + timeString + '</span>';
            
            // Add rating for bot messages
            if (sender === 'bot' && this.config.settings.enableRating) {
                html += this.buildRatingHtml(messageId);
            }
            
            html += '</div></div></div>';
            
            return html;
        },

        // Rating HTML builder
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

        // =======================
        // RATING SYSTEM METHODS - ALL PRESERVED
        // =======================
        
        showEndOfConversationRating: function() {
            console.log('Checking if conversation rating needed...');
            
            if ($('.end-conversation-rating').length > 0) {
                console.log('Rating form already exists');
                return;
            }
            
            if (this.ratingStorage.isConversationRated(this.currentConversationId)) {
                console.log('Conversation already rated, showing previous rating');
                this.showPreviousRating();
                return;
            }
            
            console.log('Showing new rating form');
            this.displayNewRatingForm();
        },

        showPreviousRating: function() {
            var ratingData = this.ratingStorage.getConversationRating(this.currentConversationId);
            if (!ratingData) return;
            
            var ratingDetails = this.getRatingDetails(ratingData.rating);
            
            var previousRatingHtml = `
                <div class="ai-chatbot-message bot-message end-conversation-rating rating-completed-inline">
                    <div class="message-content">
                        <div class="message-bubble rating-bubble">
                            <div class="rating-submitted-inline">
                                <div class="submitted-header">
                                    <span class="check-icon">📋</span>
                                    <span class="submitted-text">Previous Rating</span>
                                </div>
                                <div class="submitted-rating">
                                    <div class="rating-display-inline" data-rating="${ratingData.rating}">
                                        <span class="submitted-emoji">${ratingDetails.emoji}</span>
                                        <div class="rating-info">
                                            <div class="rating-label">${ratingDetails.label}</div>
                                            <div class="rating-stars">${this.generateStars(ratingData.rating)}</div>
                                        </div>
                                    </div>
                                    ${ratingData.feedback ? `
                                        <div class="submitted-feedback">
                                            <div class="feedback-label">Your previous feedback:</div>
                                            <div class="feedback-text-display">"${ratingData.feedback}"</div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.$messages.append(previousRatingHtml);
            this.scrollToBottom();
            
            console.log('📋 Restored previous rating from localStorage');
        },

        displayNewRatingForm: function() {
            $('.ai-chatbot-container').addClass('has-feedback-form');
            
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
                                    <textarea class="feedback-text" placeholder="Tell us more about your experience (optional)..." maxlength="500"></textarea>
                                    <div class="feedback-actions">
                                        <button class="submit-rating-btn">Submit Rating</button>
                                        <button class="skip-rating-btn">Skip</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.$messages.append(ratingHtml);
            this.scrollToBottom();
            
            console.log('📝 New rating form displayed');
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
                        
                        var ratingDetails = self.getRatingDetails(rating);
                        $rating.html(`
                            <div class="rating-thanks-enhanced">
                                <span class="rating-emoji">${ratingDetails.emoji}</span>
                                <span class="rating-text">Rated: ${ratingDetails.label}</span>
                                <span class="thank-icon">🙏</span>
                            </div>
                        `);
                        
                        $rating.addClass('rating-completed');
                        
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
                        self.ratingStorage.storeConversationRating(
                            self.currentConversationId, 
                            rating, 
                            feedback
                        );
                        
                        self.showRatingSubmittedInPlace(rating, feedback);
                        
                        self.trackEvent('conversation_rated', { 
                            rating: rating, 
                            has_feedback: feedback.length > 0,
                            persistent_storage: true
                        });
                    }
                },
                error: function() {
                    console.log('Failed to submit conversation rating');
                }
            });
        },

        showRatingSubmittedInPlace: function(rating, feedback) {
            var self = this;
            var ratingDetails = this.getRatingDetails(rating);
            
            $('.rating-smilies, .rating-feedback').slideUp(300, function() {
                var submittedHtml = `
                    <div class="rating-submitted-inline">
                        <div class="submitted-header">
                            <span class="check-icon">✅</span>
                            <span class="submitted-text">Rating Submitted</span>
                        </div>
                        <div class="submitted-rating">
                            <div class="rating-display-inline" data-rating="${rating}">
                                <span class="submitted-emoji">${ratingDetails.emoji}</span>
                                <div class="rating-info">
                                    <div class="rating-label">${ratingDetails.label}</div>
                                    <div class="rating-stars">${self.generateStars(rating)}</div>
                                </div>
                            </div>
                            ${feedback ? `
                                <div class="submitted-feedback">
                                    <div class="feedback-label">Your feedback:</div>
                                    <div class="feedback-text-display">"${feedback}"</div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                $('.rating-bubble').html(submittedHtml);
                $('.end-conversation-rating').addClass('rating-completed-inline');
                self.scrollToBottom();
            });
            
            console.log('✅ Rating displayed in place for conversation:', this.currentConversationId);
        },

        getRatingDetails: function(rating) {
            var ratingMap = {
                1: { emoji: '😡', label: 'Very Poor', color: '#ef4444' },
                2: { emoji: '😞', label: 'Poor', color: '#f97316' },
                3: { emoji: '😐', label: 'Okay', color: '#eab308' },
                4: { emoji: '😊', label: 'Good', color: '#22c55e' },
                5: { emoji: '🤩', label: 'Excellent', color: '#16a34a' }
            };
            
            return ratingMap[rating] || ratingMap[3];
        },

        generateStars: function(rating) {
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                if (i <= rating) {
                    stars += '<span class="star filled">★</span>';
                } else {
                    stars += '<span class="star empty">☆</span>';
                }
            }
            return stars;
        },

        // =======================
        // RATING STORAGE - ALL PRESERVED
        // =======================
        ratingStorage: {
            storeConversationRating: function(conversationId, rating, feedback) {
                var ratings = JSON.parse(localStorage.getItem('ai_chatbot_conversation_ratings') || '{}');
                ratings[conversationId] = {
                    rating: rating,
                    feedback: feedback,
                    timestamp: Date.now(),
                    type: 'conversation'
                };
                localStorage.setItem('ai_chatbot_conversation_ratings', JSON.stringify(ratings));
                console.log('📝 Stored conversation rating:', conversationId, rating);
            },
            
            storeMessageRating: function(messageId, rating) {
                var ratings = JSON.parse(localStorage.getItem('ai_chatbot_message_ratings') || '{}');
                ratings[messageId] = {
                    rating: rating,
                    timestamp: Date.now(),
                    type: 'message'
                };
                localStorage.setItem('ai_chatbot_message_ratings', JSON.stringify(ratings));
            },
            
            getConversationRating: function(conversationId) {
                var ratings = JSON.parse(localStorage.getItem('ai_chatbot_conversation_ratings') || '{}');
                return ratings[conversationId] || null;
            },
            
            isConversationRated: function(conversationId) {
                return this.getConversationRating(conversationId) !== null;
            },
            
            getAllSessionRatings: function(sessionId) {
                var ratings = JSON.parse(localStorage.getItem('ai_chatbot_conversation_ratings') || '{}');
                var sessionRatings = {};
                
                Object.keys(ratings).forEach(function(convId) {
                    if (convId.includes(sessionId) || ratings[convId].sessionId === sessionId) {
                        sessionRatings[convId] = ratings[convId];
                    }
                });
                
                return sessionRatings;
            }
        },

        // =======================
        // UTILITY METHODS - ALL PRESERVED
        // =======================
        
        checkForConversationEnd: function(botMessage) {
            var endPhrases = [
                'goodbye', 'bye', 'have a great day','thanks',
                'thank you', 'take care', 'cheers', 'see you later',
                'talk to you later'
            ];
            var messageText = botMessage.toLowerCase();
            
            var isEndingMessage = endPhrases.some(phrase => messageText.includes(phrase));
            var hasEnoughMessages = this.messageCount >= 4;
            
            if (isEndingMessage || hasEnoughMessages) {
                setTimeout(() => {
                    if ($('.end-conversation-rating').length === 0) {
                        this.showEndOfConversationRating();
                    }
                }, 3000);
            }
        },

        closeChat: function() {
            var hasConversation = this.messageCount > 2;
            var hasRatingShown = $('.end-conversation-rating').length > 0;
            
            if (this.inactivityTimer) {
                clearTimeout(this.inactivityTimer);
            }
            
            if (hasConversation && !hasRatingShown) {
                this.showEndOfConversationRating();
                this.feedbackMode = true;
                
                setTimeout(() => {
                    if (this.feedbackMode) {
                        this.hideWidget();
                    }
                }, 30000);
            } else {
                this.hideWidget();
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
                        user_email: this.currentUserData ? this.currentUserData.email : '',
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.$messages.find('.ai-chatbot-message:not(.typing-indicator)').remove();
                            self.currentSessionId = response.data.new_session_id;
                            self.currentConversationId = self.generateConversationId();
                            self.messageHistory = [];
                            self.messageCount = 0;
                            
                            localStorage.setItem('ai_chatbot_session', self.currentSessionId);
                            
                            if (self.config.welcomeMessage) {
                                self.addBotMessage(self.config.welcomeMessage);
                            }
                            
                            if (typeof self.startInactivityTimer === 'function') {
                                self.startInactivityTimer();
                            }
                        }
                    }
                });
            }
        },

        exportConversation: function() {
            if (!this.isUserAuthenticated()) {
                this.showError('Please login to export conversations.');
                return;
            }
            
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_export_data',
                    session_id: this.currentSessionId,
                    user_email: this.currentUserData.email,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
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
            location.reload();
        },

        getLastUserMessage: function() {
            for (var i = this.messageHistory.length - 1; i >= 0; i--) {
                if (this.messageHistory[i].sender === 'user') {
                    return this.messageHistory[i].message;
                }
            }
            return null;
        },

        // FIXED: Safe escape HTML
        escapeHtml: safeEscapeHtml,

        formatTime: function(timestamp) {
            try {
                var date = new Date(timestamp * 1000);
                return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            } catch (e) {
                return '00:00';
            }
        },

        generateRandomString: function(length) {
            length = length || 8;
            return 'id_' + Date.now() + '_' + Math.random().toString(36).substr(2, length);
        },

        generateConversationId: function() {
            return 'conv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        trackEvent: function(eventType, data) {
            console.log('Event tracked:', eventType, data);
            
            if (typeof gtag !== 'undefined') {
                gtag('event', eventType, {
                    custom_parameter_1: JSON.stringify(data)
                });
            }
            
            if (this.config.analyticsCallback && typeof this.config.analyticsCallback === 'function') {
                this.config.analyticsCallback(eventType, data);
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

        // UI Helper Methods
        showTypingIndicator: function() {
            if (!this.$messages) return;
            
            if (!this.$typing || !this.$typing.length) {
                var typingHtml = '<div class="ai-chatbot-message ai-chatbot-message-bot ai-chatbot-typing">';
                typingHtml += '<div class="ai-chatbot-message-content">';
                typingHtml += '<div class="ai-chatbot-typing-indicator">';
                typingHtml += '<span></span><span></span><span></span>';
                typingHtml += '</div>';
                typingHtml += '</div>';
                typingHtml += '</div>';
                
                this.$messages.append(typingHtml);
                this.$typing = this.$messages.find('.ai-chatbot-typing');
            }
            
            this.$typing.show();
            this.scrollToBottom();
            this.isTyping = true;
        },

        hideTypingIndicator: function() {
            if (this.$typing && this.$typing.length) {
                this.$typing.remove();
                this.$typing = null;
            }
            this.isTyping = false;
        },

        showError: function(message) {
            if (!message || typeof message !== 'string') {
                message = this.config.strings.error;
            }
            
            var errorHtml = '<div class="ai-chatbot-message error-message">' +
                           '<div class="message-content">' +
                           '<div class="message-bubble error">❌ ' + safeEscapeHtml(message) + '</div>' +
                           '</div></div>';
            
            this.$messages.append(errorHtml);
            this.scrollToBottom();
            
            setTimeout(function() {
                $('.error-message').fadeOut();
            }, 5000);
        },

        setInputState: function(enabled) {
            if (this.$input) {
                this.$input.prop('disabled', !enabled);
            }
            if (this.$sendBtn) {
                this.$sendBtn.prop('disabled', !enabled);
            }
        },

        scrollToBottom: function() {
            if (this.$messages && this.$messages.length) {
                setTimeout(() => {
                    this.$messages.scrollTop(this.$messages[0].scrollHeight);
                }, 50);
            }
        },

        animateMessageAppearance: function(messageId) {
            if (!messageId) return;
            
            var $message = this.$messages.find('[data-message-id="' + messageId + '"]');
            if ($message.length) {
                $message.addClass('ai-chatbot-message-appear');
            }
        },

        hideWidget: function() {
            if (this.$widget) {
                this.$widget.removeClass('open');
            }
        },

        isUserAuthenticated: function() {
            return this.currentUserData && this.currentUserData.email;
        },

        debugSessionState: function() {
            console.log('=== CHATBOT SESSION DEBUG ===');
            console.log('Current User Data:', this.currentUserData);
            console.log('Is Authenticated:', this.isUserAuthenticated());
            console.log('Session ID:', this.currentSessionId);
            console.log('Conversation ID:', this.currentConversationId);
            console.log('LocalStorage Data:', localStorage.getItem('ai_chatbot_user_data'));
            console.log('Auth Flag:', localStorage.getItem('ai_chatbot_authenticated'));
            console.log('Message Count:', this.messageCount);
            console.log('DOM Elements:', {
                messages: this.$messages ? this.$messages.length : 0,
                input: this.$input ? this.$input.length : 0,
                sendBtn: this.$sendBtn ? this.$sendBtn.length : 0
            });
            console.log('===============================');
        }
    };

    // =======================
    // PRO EXTENSIONS (SAFE) - ONLY IF PRO IS ENABLED
    // =======================
    
    // Store original methods before extending (if they exist)
    var originalMethods = {};
    
    // Only add Pro extensions if Pro is detected
    if (typeof window.ai_chatbot_pro_enabled !== 'undefined' && window.ai_chatbot_pro_enabled) {
        
        // Store original methods
        originalMethods = {
            sendMessageToServer: window.AIChatbot.sendMessageToServer,
            addBotMessage: window.AIChatbot.addBotMessage,
            buildMessageHtml: window.AIChatbot.buildMessageHtml,
            showTypingIndicator: window.AIChatbot.showTypingIndicator,
            hideTypingIndicator: window.AIChatbot.hideTypingIndicator
        };
        
        // Pro context builder
        window.AIChatbot.buildProContext = function() {
            return {
                page_url: window.location.href,
                page_title: document.title,
                timestamp: Date.now(),
                user_agent: navigator.userAgent,
                referrer: document.referrer
            };
        };

        // Pro typing indicator
        window.AIChatbot.showProTypingIndicator = function() {
            // Enhanced typing with semantic indicators
            if (!this.$messages) return;
            
            var proTypingHtml = '<div class="ai-chatbot-message ai-chatbot-message-bot ai-chatbot-typing pro-typing">';
            proTypingHtml += '<div class="ai-chatbot-message-content">';
            proTypingHtml += '<div class="ai-chatbot-typing-indicator pro-indicator">';
            proTypingHtml += '<span class="typing-text">🧠 AI is analyzing...</span>';
            proTypingHtml += '<div class="typing-dots"><span></span><span></span><span></span></div>';
            proTypingHtml += '</div>';
            proTypingHtml += '</div>';
            proTypingHtml += '</div>';
            
            this.$messages.append(proTypingHtml);
            this.$typing = this.$messages.find('.ai-chatbot-typing');
            this.scrollToBottom();
            this.isTyping = true;
        };

        // Pro bot message
        window.AIChatbot.addProBotMessage = function(message, responseData) {
            if (!responseData || typeof responseData !== 'object') {
                return this.addBotMessage(message);
            }
            
            var messageId = this.generateRandomString(8);
            var html = this.buildProMessageHtml(message, 'bot', Date.now(), messageId, responseData);
            
            if (this.$messages && this.$messages.length) {
                this.$messages.append(html);
                this.scrollToBottom();
                this.animateMessageAppearance(messageId);
                this.addProFeatures(messageId, responseData);
            }
        };

        // Pro message HTML builder
        window.AIChatbot.buildProMessageHtml = function(message, sender, timestamp, messageId, responseData) {
            var baseHtml = this.buildMessageHtml(message, sender, timestamp, messageId);
            
            // Add Pro enhancements
            var proEnhancements = '';
            
            if (responseData.source) {
                proEnhancements += '<div class="ai-chatbot-source">Source: ' + safeEscapeHtml(responseData.source) + '</div>';
            }
            
            if (responseData.confidence) {
                var confidence = Math.round(responseData.confidence * 100);
                proEnhancements += '<div class="ai-chatbot-confidence">Confidence: ' + confidence + '%</div>';
            }
            
            if (responseData.tokens_used) {
                proEnhancements += '<div class="ai-chatbot-tokens">Tokens: ' + responseData.tokens_used + '</div>';
            }
            
            if (proEnhancements) {
                baseHtml = baseHtml.replace('</div></div></div>', 
                    proEnhancements + '</div></div></div>');
            }
            
            return baseHtml;
        };

        // Pro features addition
        window.AIChatbot.addProFeatures = function(messageId, responseData) {
            var $message = this.$messages.find('[data-message-id="' + messageId + '"]');
            
            if (responseData.suggestions && responseData.suggestions.length > 0) {
                this.addSuggestions(responseData.suggestions, messageId);
            }
            
            if (responseData.follow_up_questions) {
                this.addFollowUpQuestions(responseData.follow_up_questions, messageId);
            }
            
            // Add Pro styling
            $message.addClass('pro-message');
        };

        // Add suggestions
        window.AIChatbot.addSuggestions = function(suggestions, messageId) {
            var suggestionsHtml = '<div class="ai-chatbot-suggestions" data-for-message="' + messageId + '">';
            suggestionsHtml += '<div class="suggestions-title">💡 Suggestions:</div>';
            suggestionsHtml += '<div class="suggestions-list">';
            
            suggestions.forEach(function(suggestion, index) {
                suggestionsHtml += '<button class="suggestion-btn" data-suggestion="' + safeEscapeHtml(suggestion) + '">';
                suggestionsHtml += safeEscapeHtml(suggestion);
                suggestionsHtml += '</button>';
            });
            
            suggestionsHtml += '</div>';
            suggestionsHtml += '</div>';
            
            this.$messages.append(suggestionsHtml);
            this.scrollToBottom();
        };

        // Hide suggestions
        window.AIChatbot.hideSuggestions = function() {
            $('.ai-chatbot-suggestions').fadeOut();
        };

        // Add follow-up questions
        window.AIChatbot.addFollowUpQuestions = function(questions, messageId) {
            var questionsHtml = '<div class="ai-chatbot-followup" data-for-message="' + messageId + '">';
            questionsHtml += '<div class="followup-title">❓ Follow-up questions:</div>';
            questionsHtml += '<div class="followup-list">';
            
            questions.forEach(function(question) {
                questionsHtml += '<button class="followup-btn" data-question="' + safeEscapeHtml(question) + '">';
                questionsHtml += safeEscapeHtml(question);
                questionsHtml += '</button>';
            });
            
            questionsHtml += '</div>';
            questionsHtml += '</div>';
            
            this.$messages.append(questionsHtml);
            this.scrollToBottom();
        };
        
        console.log('✅ Pro extensions loaded successfully');
    }

    // =======================
    // EVENT HANDLERS - ALL PRESERVED
    // =======================
    
    $(document).ready(function() {
        // Prevent multiple event bindings
        $(document).off('.aichatbot');
        
        var self = window.AIChatbot;
        
        // Message rating handlers
        $(document).on('click.aichatbot', '.quick-rating-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var rating = parseInt($btn.data('rating'));
            var conversationId = $btn.data('conversation-id');
            
            console.log('Quick rating clicked:', rating, conversationId);
            
            // Update UI
            $btn.siblings().removeClass('selected');
            $btn.addClass('selected');
            
            // Submit rating
            self.submitRating(conversationId, rating);
        });
        
        // Conversation rating handlers
        $(document).on('click.aichatbot', '.smiley-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var rating = parseInt($btn.data('rating'));
            console.log('Smiley rating clicked:', rating);
            
            $('.smiley-btn').removeClass('selected');
            $btn.addClass('selected');
            
            $('.rating-feedback').slideDown(300);
            $('.end-conversation-rating').data('selected-rating', rating);
            
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
        
        // Close chat
        $(document).on('click.aichatbot', '.ai-chatbot-close, .close-chat-btn', function(e) {
            e.preventDefault();
            self.closeChat();
        });
        
        // Suggestion clicks (Pro)
        $(document).on('click.aichatbot', '.suggestion-btn', function(e) {
            e.preventDefault();
            var suggestion = $(this).data('suggestion');
            if (self.$input && self.$input.length) {
                self.$input.val(suggestion);
                self.sendMessageToServer(suggestion);
                self.addUserMessage(suggestion);
            }
        });
        
        // Follow-up question clicks (Pro)
        $(document).on('click.aichatbot', '.followup-btn', function(e) {
            e.preventDefault();
            var question = $(this).data('question');
            if (self.$input && self.$input.length) {
                self.$input.val(question);
                self.sendMessageToServer(question);
                self.addUserMessage(question);
            }
        });
    });

    // =======================
    // UTILITIES NAMESPACE - PRESERVED
    // =======================
    window.AIChatbot.utils = {
        generateId: function() {
            return 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },
        
        escapeHtml: function(text) {
            return safeEscapeHtml(text);
        },
        
        formatTime: function(timestamp) {
            return window.AIChatbot.formatTime(timestamp);
        },
        
        formatMessage: function(text) {
            if (!text) return '';
            
            // Convert line breaks
            text = text.replace(/\n/g, '<br>');
            
            // Convert URLs to links
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            text = text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener">$1</a>');
            
            return text;
        }
    };

    // =======================
    // AUTO-INITIALIZATION - PRESERVED
    // =======================
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
        
        // Test function for rating system (debugging)
        setTimeout(function() {
            console.log('Testing rating system availability...');
            if (window.AIChatbot && window.AIChatbot.showEndOfConversationRating) {
                // Uncomment next line to test rating immediately
                // window.AIChatbot.showEndOfConversationRating();
            }
        }, 3000);
    });

})(jQuery);