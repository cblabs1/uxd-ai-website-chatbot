/**
 * AI Chatbot Frontend JavaScript - Core Version
 * Essential functionality only - Pro features moved to separate file
 *
 * @package AI_Website_Chatbot  
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // =======================
    // UTILITIES - ESSENTIAL ONLY
    // =======================
    
    /**
     * Safe HTML escape function
     */
    function safeEscapeHtml(text) {
        if (!text) return '';
        if (typeof text !== 'string') {
            try {
                text = String(text);
            } catch (e) {
                return '';
            }
        }
        
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =======================
    // MAIN CHATBOT OBJECT
    // =======================
    
    window.AIChatbot = {
        // Core state
        config: {},
        currentUserData: null,
        currentSessionId: null,
        currentConversationId: null,
        messageCount: 0,
        messageHistory: [],
        isTyping: false,
        initialized: false,
        
        // DOM elements
        $widget: null,
        $container: null,
        $messages: null,
        $input: null,
        $sendBtn: null,
        $typing: null,

        inactivityTimer: null,
        inactivityTimeout: 10000, // 3 minutes (configurable)
        inactivityFeedbackShown: false,

        // =======================
        // INITIALIZATION
        // =======================
        
        init: function(config) {
            console.log('AIChatbot: Initializing core functionality...');
            
            this.config = $.extend({
                ajaxUrl: '',
                nonce: '',
                sessionId: '',
                settings: {
                    maxMessageLength: 1000,
                    enableRating: true,
                    enableHistory: true,
                },
                strings: {
                    loading: 'Loading...',
                    error: 'Something went wrong. Please try again.',
                    typing: 'AI is typing...'
                }
            }, config);

            this.currentSessionId = this.generateSessionId();
            this.currentConversationId = this.generateConversationId();
            
            this.initializeUI();
            this.bindEvents();
            this.bindWidgetEvents();
            this.bindInactivityEvents();
            this.initializeAuth();
            this.initialized = true;

            this.conversationRated = false; 
            this.ratingMessageIndex = null;
            
            this.loadConversationHistory();
            
            console.log('AIChatbot: Core initialization complete');
            
            // Notify Pro features (if loaded) that core is ready
            $(document).trigger('aichatbot:core:ready');
        },

        // =======================
        // UI INITIALIZATION
        // =======================
        
        initializeUI: function() {
            this.$widget = $('.ai-chatbot-widget, #ai-chatbot-widget');
            this.$container = $('#ai-chatbot-container, .ai-chatbot-container, ai-chatbot-inline');
            this.$messages = $('.ai-chatbot-messages, .messages-container, .inline-messages-container, .popup-messages-container');
            this.$input = $('.ai-chatbot-input');
            this.$sendBtn = $('.ai-chatbot-send-button, .send-button, .ai-chatbot-send');
            this.$typing = $('.ai-chatbot-typing, .typing-indicator');
        },

        // =======================
        // WIDGET FUNCTIONALITY
        // =======================

        bindWidgetEvents: function() {
            var self = this;
                    
            // Widget toggle button - CRITICAL MISSING EVENT
            $(document).on('click.aichatbot', '.ai-chatbot-toggle', function(e) {
                e.preventDefault();
                self.toggleWidget();
            });
            
            // Close button
            $(document).on('click.aichatbot', '.ai-chatbot-close', function(e) {
                e.preventDefault();
                self.closeWidget();
            });
            
            // Minimize button  
            $(document).on('click.aichatbot', '.ai-chatbot-minimize', function(e) {
                e.preventDefault();
                self.minimizeWidget();
            });
        },

        toggleWidget: function() {
            if (this.$widget && this.$widget.length) {
                if (this.$widget.hasClass('ai-chatbot-open')) {
                    this.closeWidget();
                } else {
                    this.openWidget();
                }
            }
        },

        openWidget: function() {
            if (this.$widget && this.$widget.length) {
                this.$widget.addClass('ai-chatbot-open');
                if (this.$container && this.$container.length) {
                    this.$container.show();
                }
                
                // Focus input after opening
                setTimeout(() => {
                    if (this.$input && this.$input.length) {
                        this.$input.focus();
                    }
                }, 300);
                
                this.scrollToBottom();
                
                // Update toggle icons
                this.$widget.find('.ai-chatbot-icon-chat').hide();
                this.$widget.find('.ai-chatbot-icon-close').show();
                
                // Update toggle text
                this.$widget.find('.ai-chatbot-toggle-open-text').hide();
                this.$widget.find('.ai-chatbot-toggle-close-text').show();
                
            }
        },

        closeWidget: function() {
            if (this.$widget && this.$widget.length) {
                this.$widget.removeClass('ai-chatbot-open ai-chatbot-minimized');
                if (this.$container && this.$container.length) {
                    this.$container.hide();
                }
                
                // Update toggle icons
                this.$widget.find('.ai-chatbot-icon-chat').show();
                this.$widget.find('.ai-chatbot-icon-close').hide();
                
                // Update toggle text
                this.$widget.find('.ai-chatbot-toggle-open-text').show();
                this.$widget.find('.ai-chatbot-toggle-close-text').hide();
                
            }
        },

        minimizeWidget: function() {
            if (this.$widget && this.$widget.length) {
                this.$widget.addClass('ai-chatbot-minimized').removeClass('ai-chatbot-open');
                if (this.$container && this.$container.length) {
                    this.$container.hide();
                }
            }
        },

        disableWidgetJS: function() {
            // Disable the widget.js functionality to prevent conflicts
            if (window.aiChatbotWidget && window.aiChatbotWidget.destroy) {
                window.aiChatbotWidget.destroy();
            }
            // Remove widget.js event handlers
            $(document).off('.aiwidget');
        },

        // =======================
        // EVENT HANDLING
        // =======================
        
        bindEvents: function() {
            var self = this;

            // Send button click
            $(document).on('click.aichatbot', '.ai-chatbot-send-button, .send-button, .ai-chatbot-send', function(e) {
                e.preventDefault();
                var container = $(this).closest('.ai-chatbot-input-container, .ai-chatbot-input-form');
                var input = container.find('.ai-chatbot-input');
                var message = input.val().trim();
                
                if (message && self.isUserAuthenticated()) {
                    self.handleSendMessage(message);
                    input.val('');
                } else if (!self.isUserAuthenticated()) {
                    console.log('AIChatbot: User not authenticated, showing pre-chat form');
                }
            });

            // Input enter key
            $(document).on('keydown.aichatbot', '.ai-chatbot-input', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    var message = $(this).val().trim();
                    if (message && self.isUserAuthenticated()) {
                        self.handleSendMessage(message);
                        $(this).val('');
                    } else if (!self.isUserAuthenticated()) {
                        console.log('AIChatbot: User not authenticated, showing pre-chat form');
                    }
                }
            });

            // Input validation
            $(document).on('input.aichatbot', '.ai-chatbot-input', function() {
                self.validateInput($(this));
            });

            // Pre-chat form submission
            $(document).on('submit.aichatbot', '.prechat-form', function(e) {
                e.preventDefault();
                self.handlePreChatSubmission($(this));
            });

            // Rating submission
            $(document).on('click.aichatbot', '.rating-btn', function(e) {
                e.preventDefault();
                var rating = $(this).data('rating');
                self.submitRating(rating);
            });

            $(document).on('click', '.submit-conversation-rating', function() {
                var $ratingForm = $('.end-conversation-rating');
                var selectedRating = $ratingForm.find('.smiley-btn.selected').data('rating');
                var feedback = $ratingForm.find('.feedback-text').val().trim();
                
                if (!selectedRating) {
                    alert('Please select a rating before submitting');
                    return;
                }
                
                self.submitConversationRating(selectedRating, feedback);
                $ratingForm.fadeOut(300);
                self.conversationRated = true;
                self.ratingMessageIndex = self.messageCount;
                self.insertRatingAtPosition(selectedRating, feedback, self.messageCount);
            });

            // Conversation rating
            $(document).on('click.aichatbot', '.end-rating-btn', function(e) {
                e.preventDefault();
                var rating = $(this).data('rating');
                self.submitConversationRating(rating);
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
        },

        // =======================
        // AUTHENTICATION & USER DATA
        // =======================
        
        initializeAuth: function() {
            var userData = localStorage.getItem('ai_chatbot_user_data');
            var isAuthenticated = localStorage.getItem('ai_chatbot_authenticated');
            console.log('AIChatbot: Initializing authentication...', userData);
            
            if (userData && isAuthenticated === 'true') {
                try {
                    this.currentUserData = JSON.parse(userData);
                } catch (e) {
                    this.clearUserData();
                }
            }else {
                this.showPreChatForm();
            }
        },

        showPreChatForm: function() {
            console.log('AIChatbot: Showing pre-chat form');
            var formHtml = `
            <div class="pre-chat-overlay">
                <div class="ai-chatbot-prechat-form">
                    <div class="prechat-header">
                        <div class="prechat-avatar">👋</div>
                        <h3>Hi, Buddy!</h3>
                        <p>To get started, please share your details.</p>
                    </div>
                    <form id="ai-chatbot-prechat-inline-form" class="prechat-form">
                        <div class="form-group">
                            <input type="text" id="prechat-name" name="user_name" required 
                                placeholder="Enter your full name" autocomplete="name">
                        </div>
                        <div class="form-group">
                            <input type="email" id="prechat-email" name="user_email" required 
                                placeholder="Enter your email" autocomplete="email">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="start-chat-btn">
                                <span class="btn-text">Start Chatting</span>
                                <span class="btn-icon">💬</span>
                            </button>
                        </div>
                        <div class="form-footer">
                            <small>🔒 Your information is secure and private</small>
                        </div>
                    </form>
                </div>
            </div>
            `;

            console.log('AIChatbot: Preparing to display pre-chat form');
            console.log('AIChatbot: Current user data:', this.$container.length);
            if (this.$messages && this.$messages.length) {
                console.log('AIChatbot: Displaying pre-chat form');
                console.log('AIChatbot: Current user data:', this.currentUserData);
                console.log('AIChatbot: Is user authenticated?', this.isUserAuthenticated());
                this.$messages.append(formHtml);
            }

        },

        handlePreChatSubmission: function($form) {
            var name = $form.find('[name="user_name"]').val().trim();
            var email = $form.find('[name="user_email"]').val().trim();
            
            if (!name || !email) {
                alert('Please fill in all fields');
                return;
            }
            
            if (!this.isValidEmail(email)) {
                alert('Please enter a valid email address');
                return;
            }
            
            this.currentUserData = {
                name: name,
                email: email,
                timestamp: Date.now()
            };
            
            this.saveUserData();
            $('.pre-chat-overlay').fadeOut();
        },

        saveUserData: function() {
            if (!this.currentUserData) return;
            
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_save_user_data',
                    nonce: this.config.nonce,
                    user_data: this.currentUserData
                },
                success: function() {
                    localStorage.setItem('ai_chatbot_user_data', JSON.stringify(self.currentUserData));
                    localStorage.setItem('ai_chatbot_authenticated', 'true');
                }
            });
        },

        // =======================
        // MESSAGE HANDLING
        // =======================
        
        handleSendMessage: function(message) {
            if (!message || message.length > this.config.settings.maxMessageLength) {
                return;
            }
            
            this.addUserMessage(message);
            this.sendMessageToServer(message);
            this.messageCount++;
            this.messageHistory.push({
                message: message,
                sender: 'user',
                timestamp: Date.now()
            });
        },

        sendMessageToServer: function(message) {
            var self = this;
            this.resetInactivityTimer();
            this.showTypingIndicator();
            
            var requestData = {
                action: 'ai_chatbot_send_message',
                message: message,
                session_id: this.currentSessionId,
                conversation_id: this.currentConversationId,
                nonce: this.config.nonce
            };
            
            if (this.currentUserData) {
                requestData.user_name = this.currentUserData.name;
                requestData.user_email = this.currentUserData.email;
            }
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: requestData,
                timeout: 30000,
                success: function(response) {
                    self.hideTypingIndicator();
                    
                    if (response.success) {
                        self.addBotMessage(response.data.response);
                        self.messageHistory.push({
                            message: response.data.response,
                            sender: 'bot',
                            timestamp: Date.now()
                        });
                        
                        // Check for end-of-conversation conditions
                        self.checkEndOfConversation(response.data.response);
                        
                        // Trigger Pro processing if available
                       $(document).trigger('aichatbot:message:received', [response.data, message]);
        
                        } else {
                            // Handle both string and object error responses
                            var errorMessage = 'Sorry, something went wrong.';
                            
                            if (typeof response.data === 'string') {
                                // When wp_send_json_error() is called with a string
                                errorMessage = response.data;
                            } else if (response.data && response.data.message) {
                                // When wp_send_json_error() is called with an array
                                errorMessage = response.data.message;
                            }
                            
                            self.addBotMessage(errorMessage);
                        }
                },
                error: function() {
                    self.hideTypingIndicator();
                    self.addBotMessage('Sorry, I encountered an error. Please try again.');
                }
            });
        },

        // =======================
        // MESSAGE DISPLAY
        // =======================
        
        addUserMessage: function(message) {
            var messageId = this.generateRandomString(8);
            var html = this.buildMessageHtml(message, 'user', Date.now(), messageId);
            
            if (this.$messages && this.$messages.length) {
                this.$messages.append(html);
                this.scrollToBottom();
                this.animateMessageAppearance(messageId);
            }
        },

        addBotMessage: function(message) {
            var messageId = this.generateRandomString(8);
            var html = this.buildMessageHtml(message, 'bot', Date.now(), messageId);
            
            if (this.$messages && this.$messages.length) {
                this.$messages.append(html);
                this.scrollToBottom();
                this.animateMessageAppearance(messageId);
            }

            var self = this;
            setTimeout(function() {
                self.startInactivityTimer();
            }, 2000);
        },

        buildMessageHtml: function(message, sender, timestamp, messageId) {
            var formattedMessage = this.formatMessage(message);
            var timeString = this.formatTime(timestamp);
            
            var html = '<div class="ai-chatbot-message ai-chatbot-message-' + sender + '" data-message-id="' + messageId + '">';
            html += '<div class="ai-chatbot-message-content">';
            html += '<div class="ai-chatbot-message-text">' + formattedMessage + '</div>';
            html += '<div class="ai-chatbot-message-time">' + timeString + '</div>';
            html += '</div>';
            html += '</div>';
            
            return html;
        },

        formatMessage: function(text) {
            if (!text) return '';
            
            text = safeEscapeHtml(text);
            text = text.replace(/\n/g, '<br>');
            
            // Convert URLs to links
            var urlRegex = /(https?:\/\/[^\s]+)/g;
            text = text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener">$1</a>');
            
            return text;
        },

        // =======================
        // INACTIVITY MONITORING
        // =======================

        bindInactivityEvents: function() {
            var self = this;
            var events = ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'];
            
            events.forEach(function(event) {
                $(document).on(event + '.inactivity', function() {
                    self.resetInactivityTimer();
                });
            });
        },

        startInactivityTimer: function() {
            var self = this;
            
            // Don't start if feedback already shown or no conversation
            if (this.inactivityFeedbackShown || this.messageCount < 2) {
                return;
            }
            
            // Clear existing timer
            if (this.inactivityTimer) {
                clearTimeout(this.inactivityTimer);
            }
            
            this.inactivityTimer = setTimeout(function() {
                self.showInactivityFeedback();
            }, this.inactivityTimeout);
            
            console.log('Inactivity timer started');
        },
        
        /**
         * Reset inactivity timer
         */
        resetInactivityTimer: function() {
            if (this.inactivityTimer) {
                clearTimeout(this.inactivityTimer);
            }
            
            // Restart timer if chat is open and has conversation
            if (this.$widget && this.$widget.hasClass('ai-chatbot-open') && 
                this.messageCount >= 2 && !this.inactivityFeedbackShown) {
                this.startInactivityTimer();
            }
        },
        
        /**
         * Show feedback due to inactivity
         */
        showInactivityFeedback: function() {
            // Only show if no rating form is currently visible
            if ($('.end-conversation-rating').length === 0) {
                this.inactivityFeedbackShown = true;
                
                // Use your existing showEndOfConversationRating method
                this.showEndOfConversationRating();
                
                console.log('Feedback form shown due to inactivity');
            }
        },
        

        // =======================
        // TYPING INDICATOR
        // =======================
        
        showTypingIndicator: function() {
            this.$typing.show();
            this.scrollToBottom();
            this.isTyping = true;
        },

        hideTypingIndicator: function() {
            this.$typing.hide();
            this.isTyping = false;
        },

        // =======================
        // RATING SYSTEM
        // =======================
        
        submitRating: function(rating) {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_rating',
                    rating: rating,
                    session_id: this.currentSessionId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.rating-buttons').fadeOut();
                        self.addBotMessage('Thank you for your feedback!');
                    }
                }
            });
        },

        showEndOfConversationRating: function() {
            if ($('.end-conversation-rating').length > 0) return;
            
            $('.ai-chatbot-container').addClass('has-feedback-form');
            
            var ratingHtml = `
                <div class="ai-chatbot-message bot-message end-conversation-rating">
                    <div class="message-content">
                        <div class="message-bubble rating-bubble">
                            <h4>How was your experience?</h4>
                            <div class="conversation-rating">
                                <div class="rating-smilies">
                                    <button class="end-rating-btn smiley-btn" data-rating="1" title="Very Poor">
                                        <span class="smiley">😡</span>
                                        <span class="smiley-label">Very Poor</span>
                                    </button>
                                    <button class="end-rating-btn smiley-btn" data-rating="2" title="Poor">
                                        <span class="smiley">😞</span>
                                        <span class="smiley-label">Poor</span>
                                    </button>
                                    <button class="end-rating-btn smiley-btn" data-rating="3" title="Okay">
                                        <span class="smiley">😐</span>
                                        <span class="smiley-label">Okay</span>
                                    </button>
                                    <button class="end-rating-btn smiley-btn" data-rating="4" title="Good">
                                        <span class="smiley">😊</span>
                                        <span class="smiley-label">Good</span>
                                    </button>
                                    <button class="end-rating-btn smiley-btn" data-rating="5" title="Excellent">
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
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (this.$messages && this.$messages.length) {
                this.$messages.append(ratingHtml);
                this.scrollToBottom();
            }
        },

        submitConversationRating: function(selectedRating, feedback) {
            var self = this;
            
             $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ai_chatbot_conversation_rating',
                        conversation_id: self.currentConversationId,
                        rating: selectedRating,
                        feedback: feedback,
                        session_id: self.currentSessionId,
                        nonce: self.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // ✅ Mark conversation as rated
                            self.conversationRated = true;
                            self.ratingMessageIndex = self.messageCount;
                            
                            // Transform rating form to "submitted" state
                            //self.showRatingSubmitted(selectedRating, feedback);
                            
                            // ✅ Show "New Conversation" message
                            setTimeout(function() {
                                self.addSystemMessage('new-conversation', '🎉 Ready for a new conversation!', 'Feel free to ask me anything.');
                                
                                // Reset for next conversation
                                self.conversationRated = false;
                                self.messageCount = 0;
                                
                                // Update session/conversation IDs if provided
                                if (response.data.new_session_id) {
                                    self.currentSessionId = response.data.new_session_id;
                                }
                                self.currentConversationId = self.generateConversationId();
                            }, 1000);
                        }
                    }
                });
        },

        addSystemMessage: function(type, title, message) {
            var icon = type === 'new-conversation' ? '🎉' : '👋';
            var systemHtml = `
                <div class="ai-chatbot-message bot-message ${type}">
                    <div class="message-content">
                        <div class="message-bubble system-bubble">
                            <div class="${type}-notice">
                                <span class="${type === 'new-conversation' ? 'start-icon' : 'end-icon'}">${icon}</span>
                                <div class="${type === 'new-conversation' ? 'start-text' : 'end-text'}">
                                    <strong>${title}</strong>
                                    <p>${message}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.$messages.append(systemHtml);
            this.scrollToBottom();
        },

        

        // =======================
        // CONVERSATION MANAGEMENT
        // =======================
        
        checkEndOfConversation: function(botMessage) {
            var endPhrases = [
                'goodbye', 'bye', 'see you', 'take care', 
                'have a great day', 'thanks for chatting',
                'anything else', 'help you with', 'is there anything else'
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

        loadConversationHistory: function() {
            var self = this;
            console.log('Loading conversation history for session:', this.currentSessionId);
            
            // Don't generate new session if we already have one
            if (!this.currentSessionId) {
                this.currentSessionId = this.generateSessionId();
                return; // No history to load for new session
            }
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_history',
                    session_id: this.currentSessionId,
                    user_email: this.currentUserData ? this.currentUserData.email : '',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.messages && response.data.messages.length > 0) {
                        console.log('Found conversation history:', response.data.messages.length + ' messages');
                        self.displayConversationHistory(response.data.messages);
                        self.scrollToBottom();
                    } else {
                        console.log('No conversation history found for session:', self.currentSessionId);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Could not load conversation history:', error);
                }
            });
        },

        displayConversationHistory: function(messages) {
            var messageIndex = 0;
            var ratingData = null;
            var ratingPosition = null;
            
            for (var i = 0; i < messages.length; i++) {
                var msg = messages[i];
                
                if (msg.sender === 'user') {
                    this.addUserMessage(msg.message);
                    messageIndex++;
                } else {
                    this.addBotMessage(msg.message, msg.id);
                    messageIndex++;
                    
                    // Check if there's a rating for this message
                    if (msg.message_rating && msg.message_rated_at) {
                        this.addRatingSeparator(msg.id, msg.message_rating, messageIndex);
                    }
                    
                    // Check for conversation-level rating
                    if (msg.conversation_rating && msg.conversation_rated_at && !ratingData) {
                        ratingData = {
                            rating: msg.conversation_rating,
                            feedback: msg.conversation_feedback,
                            rated_at: msg.conversation_rated_at
                        };
                        ratingPosition = messageIndex; // Mark where to insert rating
                    }
                }
            }
            
            // Insert conversation rating at the correct position
            if (ratingData) {
                this.conversationRated = true;
                this.ratingMessageIndex = ratingPosition;
                this.insertRatingAtPosition(ratingData.rating, ratingData.feedback, ratingPosition);
            }
            
            // Check for overall conversation rating (fallback to old method)
            if (!ratingData) {
                this.checkAndRestoreConversationRating();
            }
        },

        insertRatingAtPosition: function(rating, feedback, position) {
            var self = this;
            
            // Don't show if rating already exists
            if ($('.end-conversation-rating').length > 0) {
                return;
            }

            $('.ai-chatbot-container').addClass('has-feedback-form');
            
            var ratingDetails = this.getRatingDetails(rating);
            
            var restoredRatingHtml = `
                <div class="ai-chatbot-message bot-message end-conversation-rating rating-completed restored" data-position="${position}">
                    <div class="message-content">
                        <div class="message-bubble rating-bubble">
                            <div class="rating-submitted">
                                <div class="submitted-header">
                                    <span class="check-icon">✅</span>
                                    <span class="submitted-text">Previous Rating</span>
                                </div>
                                <div class="submitted-rating">
                                    <div class="rating-display" data-rating="${rating}">
                                        <span class="submitted-emoji">${ratingDetails.emoji}</span>
                                        <div class="rating-info">
                                            <div class="rating-label">${ratingDetails.label}</div>
                                            <div class="rating-stars">${self.generateStars(rating)}</div>
                                        </div>
                                    </div>
                                    ${feedback ? `
                                        <div class="submitted-feedback">
                                            <div class="feedback-label">Your previous feedback:</div>
                                            <div class="feedback-text-display">"${feedback}"</div>
                                        </div>
                                    ` : ''}
                                </div>
                                <div class="restored-note">
                                    <span class="history-icon">📋</span>
                                    <span>Rating from this conversation</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // ✅ Insert at the correct position instead of appending
            var $allMessages = this.$messages.find('.ai-chatbot-message');
            if (position > 0 && position <= $allMessages.length) {
                $allMessages.eq(position - 1).after(restoredRatingHtml);
            } else {
                this.$messages.append(restoredRatingHtml);
            }
            
            console.log('Inserted conversation rating at position:', position);
        },

        addRatingSeparator: function(messageId, rating, position) {
            var ratingDetails = this.getRatingDetails(rating);
            
            var ratingSeparatorHtml = `
                <div class="rating-separator" data-message-id="${messageId}" data-position="${position}">
                    <div class="rating-line"></div>
                    <div class="rating-indicator">
                        <span class="rating-emoji">${ratingDetails.emoji}</span>
                        <span class="rating-text">Rated: ${ratingDetails.label}</span>
                        <div class="rating-stars">${this.generateStars(rating)}</div>
                    </div>
                    <div class="rating-line"></div>
                </div>
            `;
            
            this.$messages.append(ratingSeparatorHtml);
        },

        // NEW: Get rating details for display
        getRatingDetails: function(rating) {
            var ratingMap = {
                1: { emoji: '😞', label: 'Poor' },
                2: { emoji: '😐', label: 'Fair' },
                3: { emoji: '🙂', label: 'Good' },
                4: { emoji: '😊', label: 'Very Good' },
                5: { emoji: '🤩', label: 'Excellent' }
            };
            
            return ratingMap[rating] || { emoji: '⭐', label: 'Rated' };
        },

        // NEW: Generate star display
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

        // NEW: Restore individual message rating display
        restoreMessageRating: function(messageId, rating) {
            var self = this;
            var $rating = $('[data-message-id="' + messageId + '"] .message-rating');
            
            if ($rating.length > 0) {
                var ratingDetails = this.getRatingDetails(rating);
                $rating.html(`
                    <div class="rating-thanks-enhanced">
                        <span class="rating-emoji">${ratingDetails.emoji}</span>
                        <span class="rating-text">Rated: ${ratingDetails.label}</span>
                        <span class="thank-icon">🙏</span>
                    </div>
                `);
                
                // Add completed class for styling
                $rating.addClass('rating-completed');
            }
        },

        // NEW: Check and restore conversation rating
        checkAndRestoreConversationRating: function() {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_conversation_rating_status',
                    session_id: this.currentSessionId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.has_rating) {
                        // Show the submitted rating instead of rating form
                        self.showRestoredConversationRating(
                            response.data.rating, 
                            response.data.feedback || ''
                        );
                    }
                },
                error: function() {
                    console.log('Could not check conversation rating status');
                }
            });
        },

        // NEW: Show restored conversation rating from database
        showRestoredConversationRating: function(rating, feedback) {
            var self = this;
            
            // Don't show if rating form already exists or if already restored
            if ($('.end-conversation-rating').length > 0) {
                return;
            }

            $('.ai-chatbot-container').addClass('has-feedback-form');
            
            // Get the rating details
            var ratingDetails = this.getRatingDetails(rating);
            
            var restoredRatingHtml = `
                <div class="ai-chatbot-message bot-message end-conversation-rating rating-completed">
                    <div class="message-content">
                        <div class="message-bubble rating-bubble">
                            <div class="rating-submitted">
                                <div class="submitted-header">
                                    <span class="check-icon">✅</span>
                                    <span class="submitted-text">Previous Rating</span>
                                </div>
                                <div class="submitted-rating">
                                    <div class="rating-display" data-rating="${rating}">
                                        <span class="submitted-emoji">${ratingDetails.emoji}</span>
                                        <div class="rating-info">
                                            <div class="rating-label">${ratingDetails.label}</div>
                                            <div class="rating-stars">${self.generateStars(rating)}</div>
                                        </div>
                                    </div>
                                    ${feedback ? `
                                        <div class="submitted-feedback">
                                            <div class="feedback-label">Your previous feedback:</div>
                                            <div class="feedback-text-display">"${feedback}"</div>
                                        </div>
                                    ` : ''}
                                </div>
                                <div class="restored-note">
                                    <span class="history-icon">📋</span>
                                    <span>Loaded from conversation history</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.$messages.append(restoredRatingHtml);
            this.scrollToBottom();
            
            console.log('Restored conversation rating from database');
        },

        clearConversation: function() {
            if (this.inactivityTimer) {
                clearTimeout(this.inactivityTimer);
            }
            this.inactivityFeedbackShown = false;

            if (confirm('Are you sure you want to clear this conversation?')) {
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
                            // Clear UI
                            self.$messages.empty();
                            self.$input.val('');
                            
                            // ✅ RESET rating state
                            self.conversationRated = false;
                            self.ratingMessageIndex = null;
                            self.messageCount = 0;
                            
                            // Update session if new one provided
                            if (response.data.new_session_id) {
                                self.currentSessionId = response.data.new_session_id;
                                self.currentConversationId = self.generateConversationId();
                            }
                            
                            // Show welcome message
                            setTimeout(function() {
                                self.addBotMessage(self.config.welcomeMessage);
                            }, 300);
                        }
                    }
                });
            }
        },

        closeChat: function() {
            var hasConversation = this.messageCount > 2;
            var hasRatingShown = $('.end-conversation-rating').length > 0;
            
            if (hasConversation && !hasRatingShown) {
                this.showEndOfConversationRating();
                
                setTimeout(() => {
                    this.hideWidget();
                }, 30000);
            } else {
                this.hideWidget();
            }
        },

        hideWidget: function() {
            if (this.$widget) {
                this.$widget.removeClass('open');
            }
        },

        // =======================
        // UTILITY METHODS
        // =======================
        
        generateSessionId: function() {
            
            var existingSession = this.getCookie('ai_chatbot_session') || localStorage.getItem('ai_chatbot_session');
            
            if (existingSession && existingSession.length >= 20) {
                console.log('Using existing session:', existingSession);
                // Sync to localStorage if not there
                localStorage.setItem('ai_chatbot_session', existingSession);
                return existingSession;
            }


            var sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
            // Store in both localStorage and cookie with SAME NAME as PHP
            localStorage.setItem('ai_chatbot_session', sessionId);
            this.setCookie('ai_chatbot_session', sessionId, 30); // Match PHP 30 days
            
            console.log('Generated new session:', sessionId);
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

        getCookie: function(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for(var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        },

        setCookie: function(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
        },

        validateInput: function($input) {
            var message = $input.val();
            var charCount = message.length;
            
            var $charCount = $input.siblings('.char-count');
            if ($charCount.length) {
                $charCount.text(charCount + '/' + this.config.settings.maxMessageLength);
            }
            
            if (charCount > this.config.settings.maxMessageLength) {
                $input.addClass('error');
                $('.char-count').addClass('over-limit');
            } else {
                $input.removeClass('error');
                $('.char-count').removeClass('over-limit');
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

        isUserAuthenticated: function() {
            return this.currentUserData && this.currentUserData.email;
        },

        isValidEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        clearUserData: function() {
            this.currentUserData = null;
            localStorage.removeItem('ai_chatbot_user_data');
            localStorage.removeItem('ai_chatbot_authenticated');
        },

        formatTime: function(timestamp) {
            try {
                var date = new Date(timestamp);
                return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            } catch (e) {
                return '00:00';
            }
        },

        getFirstName: function(fullName) {
            if (!fullName || typeof fullName !== 'string') {
                return '';
            }
            var nameParts = fullName.trim().split(' ');
            return nameParts[0]; // Return only first name
        },

        personalizeWelcomeMessage: function(welcomeMessage, userData) {
            if (!welcomeMessage || !userData || !userData.name) {
                return welcomeMessage;
            }
            
            var firstName = this.getFirstName(userData.name);
            
            // Replace all placeholder variations
            welcomeMessage = welcomeMessage.replace(/\{\{user_name\}\}/g, firstName);
            welcomeMessage = welcomeMessage.replace(/\{\{userName\}\}/g, firstName);
            welcomeMessage = welcomeMessage.replace(/\{\{name\}\}/g, firstName);
            
            return welcomeMessage;
        },

        // Safe escape HTML
        escapeHtml: safeEscapeHtml,

        // =======================
        // WIDGET CALLBACKS (for widget.js integration)
        // =======================
        
        onWidgetOpen: function() {
            // Called when widget opens
            console.log('AIChatbot: Widget opened');
        },

        onWidgetClose: function() {
            // Called when widget closes
            console.log('AIChatbot: Widget closed');
        }
    };

    // =======================
    // AUTO-INITIALIZATION
    // =======================
    
    $(document).ready(function() {
        console.log('AIChatbot Core: DOM ready, checking for config...');
        
        if (typeof ai_chatbot_ajax !== 'undefined') {
            console.log('AIChatbot Core: Config found, initializing...');
            window.AIChatbot.init({
                ajaxUrl: ai_chatbot_ajax.ajaxUrl,
                nonce: ai_chatbot_ajax.nonce,
                sessionId: ai_chatbot_ajax.session_id || '',
                settings: ai_chatbot_ajax.settings || {},
                strings: ai_chatbot_ajax.strings || {}
            });
        } else {
            console.log('AIChatbot Core: No config found');
        }
    });

})(jQuery);