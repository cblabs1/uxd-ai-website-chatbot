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
                    enableHistory: true
                },
                strings: {
                    loading: 'Loading...',
                    error: 'Something went wrong. Please try again.',
                    typing: 'AI is typing...'
                }
            }, config);

            this.currentSessionId = this.config.sessionId || this.generateSessionId();
            this.currentConversationId = this.generateConversationId();
            
            this.initializeUI();
            this.bindEvents();
            this.initializeAuth();
            this.initialized = true;
            
            console.log('AIChatbot: Core initialization complete');
            
            // Notify Pro features (if loaded) that core is ready
            $(document).trigger('aichatbot:core:ready');
        },

        // =======================
        // UI INITIALIZATION
        // =======================
        
        initializeUI: function() {
            this.$widget = $('.ai-chatbot-widget, #ai-chatbot-widget');
            this.$container = $('#ai-chatbot-container, .ai-chatbot-container');
            this.$messages = $('.ai-chatbot-messages, .messages-container, .inline-messages-container, .popup-messages-container');
            this.$input = $('.ai-chatbot-input');
            this.$sendBtn = $('.ai-chatbot-send-button, .send-button, .ai-chatbot-send');
            
            console.log('AIChatbot: UI elements initialized', {
                widget: this.$widget.length,
                container: this.$container.length,
                messages: this.$messages.length,
                input: this.$input.length,
                sendBtn: this.$sendBtn.length
            });
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
                    self.showPreChatForm();
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
                        self.showPreChatForm();
                    }
                }
            });

            // Input validation
            $(document).on('input.aichatbot', '.ai-chatbot-input', function() {
                self.validateInput($(this));
            });

            // Pre-chat form submission
            $(document).on('submit.aichatbot', '.pre-chat-form', function(e) {
                e.preventDefault();
                self.handlePreChatSubmission($(this));
            });

            // Rating submission
            $(document).on('click.aichatbot', '.rating-btn', function(e) {
                e.preventDefault();
                var rating = $(this).data('rating');
                self.submitRating(rating);
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

            // Widget toggle button
            $(document).on('click.aichatbot', '.ai-chatbot-toggle', function(e) {
                e.preventDefault();
                self.toggleWidget();
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
            }
        },

        closeWidget: function() {
            if (this.$widget && this.$widget.length) {
                this.$widget.removeClass('ai-chatbot-open');
                if (this.$container && this.$container.length) {
                    this.$container.hide();
                }
            }
        },

        minimizeWidget: function() {
            if (this.$widget && this.$widget.length) {
                this.$widget.addClass('ai-chatbot-minimized');
                if (this.$container && this.$container.length) {
                    this.$container.hide();
                }
            }
        },

        // =======================
        // AUTHENTICATION & USER DATA
        // =======================
        
        initializeAuth: function() {
            var userData = localStorage.getItem('ai_chatbot_user_data');
            var isAuthenticated = localStorage.getItem('ai_chatbot_authenticated');
            
            if (userData && isAuthenticated === 'true') {
                try {
                    this.currentUserData = JSON.parse(userData);
                    console.log('User data loaded from storage');
                } catch (e) {
                    console.error('Error parsing stored user data:', e);
                    this.clearUserData();
                }
            }
        },

        showPreChatForm: function() {
            if ($('.pre-chat-form').length > 0) return;
            
            var formHtml = `
                <div class="pre-chat-overlay">
                    <div class="pre-chat-form">
                        <h3>Start Conversation</h3>
                        <div class="form-group">
                            <input type="text" name="user_name" placeholder="Your Name" required>
                        </div>
                        <div class="form-group">
                            <input type="email" name="user_email" placeholder="Your Email" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Start Chat</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(formHtml);
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
            $('.pre-chat-overlay').remove();
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
                        self.addBotMessage(response.data.message || 'Sorry, something went wrong.');
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
        // TYPING INDICATOR
        // =======================
        
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
            }
            this.$typing = null;
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
            
            var ratingHtml = `
                <div class="end-conversation-rating">
                    <div class="rating-title">How was your experience?</div>
                    <div class="rating-buttons">
                        <button class="end-rating-btn" data-rating="5">😊 Great</button>
                        <button class="end-rating-btn" data-rating="3">😐 Okay</button>
                        <button class="end-rating-btn" data-rating="1">😞 Poor</button>
                    </div>
                    <button class="skip-rating-btn">Skip</button>
                </div>
            `;
            
            if (this.$messages && this.$messages.length) {
                this.$messages.append(ratingHtml);
                this.scrollToBottom();
            }
        },

        submitConversationRating: function(rating) {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_conversation_rating',
                    rating: rating,
                    conversation_id: this.currentConversationId,
                    session_id: this.currentSessionId,
                    nonce: this.config.nonce
                },
                success: function() {
                    $('.end-conversation-rating').fadeOut(300);
                }
            });
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

        clearConversation: function() {
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
                    success: function() {
                        if (self.$messages) {
                            self.$messages.empty();
                        }
                        self.messageCount = 0;
                        self.messageHistory = [];
                        self.currentConversationId = self.generateConversationId();
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
            var sessionKey = 'ai_chatbot_session_' + window.location.hostname;
            var existingSession = localStorage.getItem(sessionKey) || this.getCookie('ai_chatbot_session');
            
            if (existingSession && existingSession.length > 10) {
                return existingSession;
            }

            var sessionId = 'session_' + Date.now() + '_' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });

            localStorage.setItem(sessionKey, sessionId);
            this.setCookie('ai_chatbot_session', sessionId, 7);

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
                ajaxUrl: ai_chatbot_ajax.ajax_url,
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