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
        
        // Initialize chatbotf
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
                    requirePreChat: true  // NEW: Make pre-chat mandatory
                },
                strings: {
                    loading: 'Loading...',
                    error: 'Something went wrong. Please try again.',
                    typing: 'AI is typing...',
                    networkError: 'Network error. Please check your connection.',
                    messageTooLong: 'Message is too long.',
                    thankYou: 'Thank you for your feedback!',
                    confirmClear: 'Are you sure you want to clear the conversation?',
                    pleaseProvideEmail: 'Please provide your email to start chatting.' // NEW
                }
            }, config);

            // Initialize session
            this.currentSessionId = this.generateSessionId();
            this.currentConversationId = this.generateConversationId();
            this.lastActivityTime = Date.now();

            // Initialize UI
            this.initializeUI();
            
            // Check if user data exists - NEW
            this.checkUserAuthentication();
            
            // Setup event handlers
            this.setupEventHandlers();
            
            // Start inactivity monitoring only if user is authenticated
            if (this.isUserAuthenticated()) {
                this.startInactivityTimer();
            }

            this.initialized = true;
            var self = this;

            setTimeout(function() {
                console.log('Loading conversation history...');
                self.loadConversationHistory();
            }, 1000); // Increased delay to ensure UI is ready
            
            console.log('AI Chatbot initialization complete');
        },

        // NEW METHOD: Check if user is authenticated
        checkUserAuthentication: function() {
            console.log('Checking user authentication...');
            
            // Try localStorage first
            var userData = localStorage.getItem('ai_chatbot_user_data');
            
            // Fallback to sessionStorage
            if (!userData) {
                userData = sessionStorage.getItem('ai_chatbot_user_data');
            }
            
            if (userData) {
                try {
                    this.currentUserData = JSON.parse(userData);
                    console.log('User data found:', this.currentUserData);
                    
                    // Validate that we have required fields
                    if (this.currentUserData && 
                        this.currentUserData.email && 
                        this.currentUserData.name && 
                        this.currentUserData.authenticated) {
                        
                        console.log('User is authenticated, enabling chat');
                        this.enableChatInterface();
                        return;
                    } else {
                        console.log('User data incomplete:', this.currentUserData);
                        this.clearUserData();
                    }
                } catch (e) {
                    console.log('Invalid user data in storage, removing...', e);
                    this.clearUserData();
                }
            }
            
            console.log('No valid user data found, showing pre-chat form');
            this.showPreChatForm();
        },

        isUserAuthenticated: function() {
            var isAuth = this.currentUserData && 
                        this.currentUserData.email && 
                        this.currentUserData.name && 
                        this.currentUserData.authenticated;
            
            console.log('Authentication check:', {
                hasCurrentUserData: !!this.currentUserData,
                hasEmail: !!(this.currentUserData && this.currentUserData.email),
                hasName: !!(this.currentUserData && this.currentUserData.name),
                isAuthenticated: !!(this.currentUserData && this.currentUserData.authenticated),
                result: isAuth
            });
            
            return isAuth;
        },

        // NEW: Clear user data method
        clearUserData: function() {
            this.currentUserData = null;
            localStorage.removeItem('ai_chatbot_user_data');
            sessionStorage.removeItem('ai_chatbot_user_data');
            localStorage.removeItem('ai_chatbot_authenticated');
        },

        // NEW METHOD: Show pre-chat form
        showPreChatForm: function() {
            // Add dynamic height class for prechat form
            $('.ai-chatbot-container').addClass('has-prechat-form');
            
            this.disableChatInterface();
            this.createPreChatModal();
        },

        // NEW METHOD: Create pre-chat modal (using your existing structure)
        createPreChatModal: function() {
            // Remove any existing pre-chat forms
            $('.ai-chatbot-prechat-form').remove();
            
            // Create inline form HTML that goes inside the chat
            var inlineFormHTML = `
                <div class="ai-chatbot-prechat-form">
                    <div class="prechat-header">
                        <div class="prechat-avatar">👋</div>
                        <h3>Hi, I am Teekeydee!</h3>
                        <p>To get started, please share your details.</p>
                    </div>
                    <form id="ai-chatbot-prechat-inline-form" class="prechat-form">
                        <div class="form-group">
                            <input type="text" id="prechat-name" name="name" required 
                                placeholder="Enter your full name" autocomplete="name">
                        </div>
                        <div class="form-group">
                            <input type="email" id="prechat-email" name="email" required 
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
            `;
            
            // Find the messages container and inject the form
            var $messagesContainer = this.$messages || $('.ai-chatbot-messages, .messages-container, .popup-messages-container');
            
            if ($messagesContainer.length > 0) {
                // Clear existing messages and add the form
                $messagesContainer.html(inlineFormHTML);
                
                // Focus on name field
                setTimeout(() => {
                    $('#prechat-name').focus();
                }, 300);
                
                console.log('Pre-chat form injected into chat container');
            } 
        },


        // NEW METHOD: Disable chat interface
        disableChatInterface: function() {
            this.$input.prop('disabled', true).attr('placeholder', this.config.strings.pleaseProvideEmail);
            this.$sendBtn.prop('disabled', true);
            
            // Hide any existing messages or show a placeholder
            if (this.$messages.children('.auth-required-message').length === 0) {
                this.$messages.append(`
                    <div class="auth-required-message ai-chatbot-message bot-message">
                        <div class="message-content">
                            <div class="message-bubble">
                                Please provide your email address to start chatting with our AI assistant.
                            </div>
                        </div>
                    </div>
                `);
            }
        },

        // NEW METHOD: Enable chat interface
        enableChatInterface: function() {
            // Remove any pre-chat forms
            $('.ai-chatbot-prechat-form').remove();
            $('#ai-chatbot-prechat-overlay').remove();
            
            this.$input.prop('disabled', false).attr('placeholder', 'Type your message...');
            this.$sendBtn.prop('disabled', false);
            
            // Remove auth required message
            $('.auth-required-message').remove();
            
            // Show welcome message if configured and no other messages exist
            if (this.config.welcomeMessage && this.$messages.children('.ai-chatbot-message').length === 0) {
                this.addBotMessage(this.config.welcomeMessage);
            }
        },

        // Generate unique session ID
        generateSessionId: function() {
            var sessionKey = 'ai_chatbot_session_id';

            // First, check localStorage
            var sessionId = localStorage.getItem(sessionKey);
            if (sessionId) {
                console.log('✅ Using existing localStorage session:', sessionId);
                return sessionId;
            }

            // Then, check cookie (to sync with backend)
            sessionId = this.getCookie('ai_chatbot_session');
            if (sessionId) {
                console.log('✅ Using existing cookie session:', sessionId);
                // Store in localStorage for faster access next time
                localStorage.setItem(sessionKey, sessionId);
                return sessionId;
            }
            
            // Generate a new unique ID if none exists
            sessionId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0,
                    v = c == 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });

            // Store in both localStorage and cookie
            localStorage.setItem(sessionKey, sessionId);
            this.setCookie('ai_chatbot_session', sessionId, 7); // 7 days

            console.log('🆕 Created new session:', sessionId);
            return sessionId;
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

        // Add cookie helper methods (around line 150-170)
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

        // UI Initialization
        initializeUI: function() {
            this.$widget = $('.ai-chatbot-widget');
            this.$container = $('#ai-chatbot-container');
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
            
            // Pre-chat inline form submission - NEW
            $(document).on('submit.aichatbot', '#ai-chatbot-prechat-inline-form', function(e) {
                e.preventDefault();
                self.handleInlinePreChatSubmission();
            });

            $(document).on('input.aichatbot keyup.aichatbot', 
                '.ai-chatbot-input, #ai-chatbot-input, input[name="message"], textarea.ai-chatbot-input', 
                function() {
                    self.handleInputChange($(this));
                }
            );
            
            // Pre-chat overlay form submission (fallback)
            $(document).on('submit.aichatbot', '#ai-chatbot-user-form', function(e) {
                e.preventDefault();
                self.handlePreChatSubmission();
            });
            
            // Send button click
            $(document).on('click.aichatbot', 
                '.ai-chatbot-send-btn, #ai-chatbot-send, .popup-send-btn, #ai-chatbot-send-button, .ai-chatbot-send-button', 
                function(e) {
                    e.preventDefault();
                    if (!self.isUserAuthenticated()) {
                        self.showPreChatForm();
                        return;
                    }
                    self.resetInactivityTimer();
                    self.handleSendMessage();
                }
            );
            
            // Enter key press
            $(document).on('keypress.aichatbot', '.ai-chatbot-input input, #ai-chatbot-input, input[name="message"]', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    if (!self.isUserAuthenticated()) {
                        self.showPreChatForm();
                        return;
                    }
                    self.resetInactivityTimer();
                    self.handleSendMessage();
                }
            });
            
            // Input change for validation and activity tracking
            $(document).on('input.aichatbot', '.ai-chatbot-input input, #ai-chatbot-input, input[name="message"]', function() {
                if (self.isUserAuthenticated()) {
                    self.resetInactivityTimer();
                    self.onInputChange();
                }
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

            console.log('Event handlers set up successfully');
        },

        handleInputChange: function($input) {
            var message = $input.val().trim();
            var charCount = message.length;
            
            // Find the corresponding send button
            var $sendBtn = $input.closest('.ai-chatbot-input-container, .ai-chatbot-input-form')
                                .find('.ai-chatbot-send-btn, #ai-chatbot-send, .popup-send-btn, #ai-chatbot-send-button, .ai-chatbot-send-button');
            
            // Update character counter if exists
            $('.char-count').text(charCount);
            
            // Update send button state
            if (message && charCount <= (this.config.settings?.maxMessageLength || 1000) && !this.isTyping) {
                $sendBtn.prop('disabled', false).removeClass('disabled');
                $input.removeClass('ai-chatbot-input-empty');
            } else {
                $sendBtn.prop('disabled', true).addClass('disabled');
                if (!message) {
                    $input.addClass('ai-chatbot-input-empty');
                }
            }
            
            // Handle validation styling
            if (charCount > (this.config.settings?.maxMessageLength || 1000)) {
                $input.addClass('error');
                $('.char-count').addClass('over-limit');
            } else {
                $input.removeClass('error');
                $('.char-count').removeClass('over-limit');
            }
        },

        handleInlinePreChatSubmission: function() {
            var name = $('#prechat-name').val().trim();
            var email = $('#prechat-email').val().trim();
            
            if (!name || !email || !this.isValidEmail(email)) {
                this.showInlineFormError('Please provide a valid name and email address.');
                return;
            }
            
            var self = this;
            var $submitBtn = $('.start-chat-btn');
            
            // Show loading state
            $submitBtn.addClass('loading').prop('disabled', true);
            $submitBtn.find('.btn-text').text('Saving...');
            
            // Send user data to server
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_save_user_data',
                    name: name,
                    email: email,
                    session_id: this.currentSessionId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    console.log('Server response:', response);
                    
                    if (response.success) {
                        // Store user data with all required fields
                        self.currentUserData = {
                            name: name,
                            email: email,
                            user_id: response.data.user_id || null,
                            session_id: self.currentSessionId,
                            authenticated: true,
                            authenticated_at: Date.now()
                        };
                        
                        // Store in multiple places for redundancy
                        localStorage.setItem('ai_chatbot_user_data', JSON.stringify(self.currentUserData));
                        sessionStorage.setItem('ai_chatbot_user_data', JSON.stringify(self.currentUserData));
                        
                        // Also store a simple flag
                        localStorage.setItem('ai_chatbot_authenticated', 'true');
                        
                        console.log('User data stored:', self.currentUserData);
                        
                        // Remove pre-chat form and enable chat
                        $('.ai-chatbot-prechat-form').fadeOut(300, function() {
                            $(this).remove();
                            
                            // Enable chat interface
                            self.enableChatInterface();
                            
                            // Start inactivity timer
                            self.startInactivityTimer();
                            
                            // Add a welcome message mentioning the user
                            var personalizedWelcome = `Hi ${name}! 👋 How can I help you today?`;
                            self.addBotMessage(personalizedWelcome);
                            
                            // Focus on chat input
                            setTimeout(() => {
                                self.$input.focus();
                                
                                // Double-check authentication state
                                console.log('Authentication check after form completion:', {
                                    isAuthenticated: self.isUserAuthenticated(),
                                    currentUserData: self.currentUserData,
                                    localStorage: localStorage.getItem('ai_chatbot_user_data')
                                });
                            }, 500);
                        });
                        
                        console.log('User authenticated successfully');
                    } else {
                        self.showInlineFormError(response.data.message || 'Failed to save user data. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', { xhr: xhr, status: status, error: error });
                    self.showInlineFormError('Connection error. Please check your internet connection and try again.');
                },
                complete: function() {
                    $submitBtn.removeClass('loading').prop('disabled', false);
                    $submitBtn.find('.btn-text').text('Start Chatting');
                }
            });
        },

        showInlineFormError: function(message) {
            // Remove existing error
            $('.prechat-error').remove();
            
            // Add error message
            $('.prechat-form').prepend(`
                <div class="prechat-error">
                    <span class="error-icon">⚠️</span>
                    ${message}
                </div>
            `);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                $('.prechat-error').fadeOut();
            }, 5000);
        },

        handlePreChatSubmission: function() {
            var name = $('#ai-chatbot-user-name').val().trim();
            var email = $('#ai-chatbot-user-email').val().trim();
            
            if (!name || !email || !this.isValidEmail(email)) {
                this.showPreChatError('Please provide a valid name and email address.');
                return;
            }
            
            var self = this;
            var $submitBtn = $('.ai-chatbot-form-submit');
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Saving...');
            
            // Send user data to server
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_save_user_data',
                    name: name,
                    email: email,
                    session_id: this.currentSessionId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Store user data locally
                        self.currentUserData = {
                            name: name,
                            email: email,
                            user_id: response.data.user_id || null
                        };
                        
                        localStorage.setItem('ai_chatbot_user_data', JSON.stringify(self.currentUserData));
                        
                        // Hide pre-chat form
                        $('#ai-chatbot-prechat-overlay').fadeOut(300);
                        
                        // Enable chat interface
                        self.enableChatInterface();
                        
                        // Start inactivity timer
                        self.startInactivityTimer();
                        
                        // Focus on chat input
                        setTimeout(() => {
                            self.$input.focus();
                        }, 400);
                        
                        console.log('User authenticated successfully:', self.currentUserData);
                    } else {
                        self.showPreChatError(response.data.message || 'Failed to save user data. Please try again.');
                    }
                },
                error: function() {
                    self.showPreChatError('Connection error. Please check your internet connection and try again.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text('Start Chatting');
                }
            });
        },

        // NEW METHOD: Show pre-chat form error
        showPreChatError: function(message) {
            // Remove existing error
            $('.ai-chatbot-form-error').remove();
            
            // Add error message
            $('#ai-chatbot-user-form').prepend(`
                <div class="ai-chatbot-form-error" style="background: #fee; border: 1px solid #fcc; color: #c66; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px;">
                    ${message}
                </div>
            `);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                $('.ai-chatbot-form-error').fadeOut();
            }, 5000);
        },

        // NEW METHOD: Validate email
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
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

        initInactivityTimer: function() {
            var self = this;
            
            // Clear existing timer
            if (this.inactivityTimer) {
                clearTimeout(this.inactivityTimer);
            }
            
            // Set new timer - show rating after 60 seconds of inactivity
            this.inactivityTimer = setTimeout(function() {
                // Only show if we have meaningful conversation and no rating shown yet
                if (self.messageCount >= 4 && $('.end-conversation-rating').length === 0) {
                    console.log('Showing rating due to inactivity');
                    self.showEndOfConversationRating();
                }
            }, 60000); // 60 seconds
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

            console.log('=== SEND MESSAGE DEBUG ===');
            console.log('this context check:', this === window.AIChatbot);

            var self = this;
            
            // Enhanced authentication check
            if (!this.isUserAuthenticated()) {
                console.log('User not authenticated, showing pre-chat form');
                this.showPreChatForm();
                return;
            }
            
            // Get user data with validation
            var userData = this.currentUserData || {};

            console.log('userData after fix:', userData);
            console.log('userData.email:', userData.email);
            console.log('==============================');
            
            // Debug logging
            console.log('Sending message:', {
                message: message,
                userData: userData,
                sessionId: this.currentSessionId,
                conversationId: this.currentConversationId
            });
            
            // Validate we have required user data
            if (!userData.email) {
                console.error('No email found in user data:', userData);
                this.showError('Session expired. Please refresh and try again.');
                this.clearUserData();
                this.showPreChatForm();
                return;
            }
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 60000,
                data: {
                    action: 'ai_chatbot_send_message',
                    message: message,
                    session_id: this.currentSessionId,
                    conversation_id: this.currentConversationId,
                    user_email: userData.email,
                    user_name: userData.name,
                    user_id: userData.user_id || 0,
                    nonce: this.config.nonce
                },
                beforeSend: function() {
                    console.log('Sending AJAX request with data:', {
                        user_email: userData.email,
                        user_name: userData.name,
                        user_id: userData.user_id
                    });
                },
                success: function(response) {
                    console.log('Message sent successfully:', response);
                    
                    self.hideTypingIndicator();
                    self.setInputState(true);
                    self.retryCount = 0;
                    
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
                            tokens_used: response.data.tokens_used,
                            user_email: userData.email
                        });
                        
                        self.hideSuggestions();
                    } else {
                        console.error('Server error:', response.data);
                        self.showError(response.data.message || self.config.strings.error);
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

        buildMessageHtml: function(message, sender, timestamp, messageId) {
            var senderClass = sender === 'user' ? ' ai-chatbot-message-user' : ' ai-chatbot-message-bot';
            var timeString = this.formatTime(timestamp / 1000);
            // var avatar = sender === 'user' ? '👤' : '🤖';
            
            var html = '<div class="ai-chatbot-message ' + senderClass + '" data-message-id="' + messageId + '">';
            // html += '<div class="message-avatar">' + avatar + '</div>';
            html += '<div class="ai-chatbot-message-content">';
            html += '<div class="ai-chatbot-message-text">' + this.escapeHtml(message) + '</div>';
            html += '<div class="ai-chatbot-message-time">';
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
            console.log('Checking if conversation rating needed...');
            
            // Don't show if already exists in DOM
            if ($('.end-conversation-rating').length > 0) {
                console.log('Rating form already exists');
                return;
            }
            
            // ✅ Check if this conversation was already rated
            if (this.ratingStorage.isConversationRated(this.currentConversationId)) {
                console.log('Conversation already rated, showing previous rating');
                this.showPreviousRating();
                return;
            }
            
            // Show new rating form
            console.log('Showing new rating form');
            this.displayNewRatingForm();
        },

        // NEW: Show previous rating from localStorage
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

        // NEW: Display new rating form (extracted from showEndOfConversationRating)
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
                        
                        // Show enhanced rating thanks with permanent display
                        var ratingDetails = self.getRatingDetails(rating);
                        $rating.html(`
                            <div class="rating-thanks-enhanced">
                                <span class="rating-emoji">${ratingDetails.emoji}</span>
                                <span class="rating-text">Rated: ${ratingDetails.label}</span>
                                <span class="thank-icon">🙏</span>
                            </div>
                        `);
                        
                        // Add completed class for styling
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
                        // ✅ Store rating in localStorage
                        self.ratingStorage.storeConversationRating(
                            self.currentConversationId, 
                            rating, 
                            feedback
                        );
                        
                        // ✅ Show submitted rating permanently in place
                        self.showRatingSubmittedInPlace(rating, feedback);
                        
                        // ✅ No session reset - continue same conversation!
                        
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

        // NEW: Show submitted rating in place (no session reset)
        showRatingSubmittedInPlace: function(rating, feedback) {
            var self = this;
            var ratingDetails = this.getRatingDetails(rating);
            
            // Hide the rating form elements
            $('.rating-smilies, .rating-feedback').slideUp(300, function() {
                // Replace with submitted rating display
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
                
                // Replace the rating bubble content
                $('.rating-bubble').html(submittedHtml);
                
                // Add completed class for styling
                $('.end-conversation-rating').addClass('rating-completed-inline');
                
                // Scroll to show the completed rating
                self.scrollToBottom();
            });
            
            console.log('✅ Rating displayed in place for conversation:', this.currentConversationId);
        },

        // NEW: Handle session reset after rating
        handleSessionReset: function(newSessionId) {
            var self = this;
            
            console.log('Handling session reset. Old session:', this.currentSessionId, 'New session:', newSessionId);
            
            // Update current session data
            this.currentSessionId = newSessionId;
            this.currentConversationId = this.generateConversationId();
            
            // Clear message history
            this.messageHistory = [];
            this.messageCount = 0;
            
            // Update localStorage with new session
            localStorage.setItem('ai_chatbot_session', newSessionId);
            localStorage.removeItem('ai_chatbot_conversation_' + this.currentSessionId);
            
            // Clear any cached user data that might be session-specific
            // (Keep user data like name/email, but clear session-specific data)
            if (this.currentUserData) {
                // Preserve user info but mark as new session
                this.currentUserData.session_reset = true;
            }
            
            // Add visual indicator that conversation has ended
            this.showConversationEndedMessage();
            
            // Prepare for new conversation
            setTimeout(function() {
                self.prepareForNewConversation();
            }, 2000); // Wait 2 seconds before preparing new conversation
        },

        // NEW: Show conversation ended message
        showConversationEndedMessage: function() {
            var endMessageHtml = `
                <div class="ai-chatbot-message system-message conversation-ended">
                    <div class="message-content">
                        <div class="message-bubble system-bubble">
                            <div class="conversation-end-notice">
                                <span class="end-icon">🏁</span>
                                <div class="end-text">
                                    <strong>Conversation Completed</strong>
                                    <p>Thank you for your feedback! Starting a fresh conversation...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.$messages.append(endMessageHtml);
            this.scrollToBottom();
        },

        // NEW: Prepare for new conversation
        prepareForNewConversation: function() {
            var self = this;
            
            // Add new conversation starter
            var newConversationHtml = `
                <div class="ai-chatbot-message system-message new-conversation">
                    <div class="message-content">
                        <div class="message-bubble system-bubble">
                            <div class="new-conversation-notice">
                                <span class="start-icon">✨</span>
                                <div class="start-text">
                                    <strong>New Conversation Started</strong>
                                    <p>Hello again! How can I help you today?</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.$messages.append(newConversationHtml);
            this.scrollToBottom();
            
            // Re-enable input for new conversation
            this.setInputState(true);
            this.$input.attr('placeholder', this.config.strings.inputPlaceholder || 'Type your message...');
            
            // Focus on input for immediate use
            setTimeout(function() {
                self.$input.focus();
            }, 500);
            
            console.log('Prepared for new conversation with session:', this.currentSessionId);
        },

        // NEW: Rating storage functions
        ratingStorage: {
            // Store a conversation rating
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
            
            // Store a message rating
            storeMessageRating: function(messageId, rating) {
                var ratings = JSON.parse(localStorage.getItem('ai_chatbot_message_ratings') || '{}');
                ratings[messageId] = {
                    rating: rating,
                    timestamp: Date.now(),
                    type: 'message'
                };
                localStorage.setItem('ai_chatbot_message_ratings', JSON.stringify(ratings));
                console.log('📝 Stored message rating:', messageId, rating);
            },
            
            // Get conversation rating
            getConversationRating: function(conversationId) {
                var ratings = JSON.parse(localStorage.getItem('ai_chatbot_conversation_ratings') || '{}');
                return ratings[conversationId] || null;
            },
            
            // Get message rating
            getMessageRating: function(messageId) {
                var ratings = JSON.parse(localStorage.getItem('ai_chatbot_message_ratings') || '{}');
                return ratings[messageId] || null;
            },
            
            // Check if conversation has been rated
            isConversationRated: function(conversationId) {
                return this.getConversationRating(conversationId) !== null;
            },
            
            // Get all conversation ratings for current session
            getSessionConversations: function(sessionId) {
                var ratings = JSON.parse(localStorage.getItem('ai_chatbot_conversation_ratings') || '{}');
                var sessionRatings = {};
                
                // Filter ratings for conversations in this session
                Object.keys(ratings).forEach(function(convId) {
                    if (convId.includes(sessionId) || ratings[convId].sessionId === sessionId) {
                        sessionRatings[convId] = ratings[convId];
                    }
                });
                
                return sessionRatings;
            }
        },

        showRatingSubmitted: function(rating, feedback) {
            var self = this;
            
            // Get the rating details
            var ratingDetails = this.getRatingDetails(rating);
            
            // Hide the rating form elements with animation
            $('.rating-smilies, .rating-feedback').slideUp(300, function() {
                // Replace with submitted rating display
                var submittedHtml = `
                    <div class="rating-submitted">
                        <div class="submitted-header">
                            <span class="check-icon">✅</span>
                            <span class="submitted-text">Rating Submitted</span>
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
                                    <div class="feedback-label">Your feedback:</div>
                                    <div class="feedback-text-display">"${feedback}"</div>
                                </div>
                            ` : ''}
                        </div>
                        <div class="thank-you-note">
                            <span class="thank-icon">🙏</span>
                            <span>Thank you for helping us improve!</span>
                        </div>
                    </div>
                `;
                
                // Replace the rating bubble content
                $('.rating-bubble').html(submittedHtml);
                
                // Add permanent styling
                $('.end-conversation-rating').addClass('rating-completed');
                
                // Scroll to show the completed rating
                self.scrollToBottom();
            });
        },

        // NEW: Get rating details (emoji, label, etc.)
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

        checkForConversationEnd: function(botMessage) {
            var endPhrases = [
                'goodbye', 'bye', 'have a great day','thanks',
                'thank you', 'take care', 'cheers', 'see you later',
                'talk to you later'
            ];
            var messageText = botMessage.toLowerCase();
            
            var isEndingMessage = endPhrases.some(phrase => messageText.includes(phrase));
            
            // ALSO show rating after 3+ messages regardless of content
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
            // Always check for rating on close if we have meaningful conversation
            var hasConversation = this.messageCount > 2;
            var hasRatingShown = $('.end-conversation-rating').length > 0;
            
            // Clear inactivity timer
            if (this.inactivityTimer) {
                clearTimeout(this.inactivityTimer);
            }
            
            if (hasConversation && !hasRatingShown) {
                // Show feedback form instead of closing immediately
                this.showEndOfConversationRating();
                this.feedbackMode = true;
                
                // Auto-hide after 30 seconds if no interaction
                setTimeout(() => {
                    if (this.feedbackMode) {
                        this.hideWidget();
                    }
                }, 30000);
            } else {
                // Close directly
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
            // Always scroll the messages container directly, not parent containers
            if (this.$messages && this.$messages.length) {
                var messagesElement = this.$messages[0];
                if (messagesElement) {
                    // Use setTimeout to ensure DOM updates are complete
                    setTimeout(function() {
                        messagesElement.scrollTop = messagesElement.scrollHeight;
                    }, 50);
                }
            }
            
            // Also handle cases where $messages might be jQuery collection of multiple elements
            this.$messages.each(function() {
                setTimeout(function() {
                    this.scrollTop = this.scrollHeight;
                }.bind(this), 50);
            });
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
            // Remove dynamic height classes
            $('.ai-chatbot-container').removeClass('has-feedback-form has-prechat-form');
            var $container = this.$messages.closest('.ai-chatbot-container, .chat-container');
            
            // Reset feedback mode
            this.feedbackMode = false;
            
            // Hide the widget
            if (this.$widget.length) {
                this.$widget.removeClass('ai-chatbot-open');
                this.$container.hide();
            }
        },

        // Conversation Management
        loadConversationHistory: function() {
            var self = this;
            
            // Check if we have a session - if not, start fresh
            if (!this.currentSessionId) {
                this.currentSessionId = this.generateSessionId();
                localStorage.setItem('ai_chatbot_session', this.currentSessionId);
                return;
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
                        self.displayConversationHistory(response.data.messages);
                        self.scrollToBottom();
                    } else {
                        // No history found - start fresh
                        console.log('No conversation history found for session:', self.currentSessionId);
                    }
                },
                error: function() {
                    console.log('Could not load conversation history');
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
                    
                    // Restore individual message ratings if they exist
                    if (msg.message_rating && msg.message_rated_at) {
                        this.restoreMessageRating(msg.id, msg.message_rating);
                    }
                }
            }
            
            // Check for overall conversation rating
            this.checkAndRestoreConversationRating();
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
            if (confirm(this.config.strings.confirmClear)) {
                var self = this;
                
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ai_chatbot_clear_conversation',
                        session_id: this.currentSessionId,
                        user_email: this.currentUserData ? this.currentUserData.email : '', // NEW
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
                    user_email: this.currentUserData.email, // NEW
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
        logoutUser: function() {
            // Clear user data
            this.currentUserData = null;
            localStorage.removeItem('ai_chatbot_user_data');
            
            // Clear conversations
            this.clearConversation();
            
            // Show pre-chat form again
            this.showPreChatForm();
            
            console.log('User logged out');
        },

        refreshWidget: function() {
            // Reload the widget
            location.reload();
        },
        debugSessionState: function() {
            console.log('=== CHATBOT SESSION DEBUG ===');
            console.log('Current User Data:', this.currentUserData);
            console.log('Is Authenticated:', this.isUserAuthenticated());
            console.log('Session ID:', this.currentSessionId);
            console.log('Conversation ID:', this.currentConversationId);
            console.log('LocalStorage Data:', localStorage.getItem('ai_chatbot_user_data'));
            console.log('SessionStorage Data:', sessionStorage.getItem('ai_chatbot_user_data'));
            console.log('Auth Flag:', localStorage.getItem('ai_chatbot_authenticated'));
            console.log('Message Count:', this.messageCount);
            console.log('DOM Elements:', {
                messages: this.$messages ? this.$messages.length : 0,
                input: this.$input ? this.$input.length : 0,
                sendBtn: this.$sendBtn ? this.$sendBtn.length : 0
            });
            console.log('===============================');
        },

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

    //----- Pro Upgrade -----//

    // Check if AIChatbot exists (it should from your existing code)
    if (typeof window.AIChatbot === 'undefined') {
        console.error('AIChatbot not found - make sure base frontend.js is loaded first');
        return;
    }
    
    // Store original methods before extending
    var originalMethods = {
        sendMessageToServer: window.AIChatbot.sendMessageToServer,
        addBotMessage: window.AIChatbot.addBotMessage,
        buildMessageHtml: window.AIChatbot.buildMessageHtml,
        showTypingIndicator: window.AIChatbot.showTypingIndicator,
        hideTypingIndicator: window.AIChatbot.hideTypingIndicator
    };
    
    // Pro feature detection
    window.AIChatbot.isPro = function() {
        return window.ai_chatbot_pro_enabled || false;
    };
    
    // Enhanced sendMessageToServer with Pro routing
    window.AIChatbot.sendMessageToServer = function(message) {
        var self = this;
        
        // Use Pro endpoint if available
        var action = this.isPro() ? 'ai_chatbot_message_pro' : 'ai_chatbot_message';
        
        // Prepare request data (keeping your existing structure)
        var requestData = {
            action: action,
            message: message,
            nonce: this.config.nonce,
            session_id: this.currentSessionId,
            conversation_id: this.currentConversationId
        };
        
        // Add Pro context if available
        if (this.isPro()) {
            requestData.context = this.buildProContext();
        }
        
        // Add user data if exists (preserve your existing logic)
        var userData = this.currentUserData || {};
        $.extend(requestData, userData);
        
        // Show enhanced typing for Pro users
        if (this.isPro()) {
            this.showProTypingIndicator();
        } else {
            this.showTypingIndicator();
        }
        
        // Make AJAX request (keeping your existing error handling)
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: requestData,
            timeout: 30000,
            success: function(response) {
                self.hideTypingIndicator();
                self.setInputState(true);
                
                if (response.success) {
                    var botResponse = response.data.response;
                    self.currentConversationId = response.data.conversation_id;
                    
                    // Add message with Pro enhancements
                    if (self.isPro()) {
                        self.addProBotMessage(botResponse, response.data);
                    } else {
                        self.addBotMessage(botResponse);
                    }
                    
                    // Your existing analytics code
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'chatbot_message_sent', {
                            user_message: message,
                            bot_response: botResponse,
                            response_time: response.data.response_time,
                            tokens_used: response.data.tokens_used,
                            user_email: userData.email
                        });
                    }
                    
                    self.hideSuggestions();
                } else {
                    console.error('Server error:', response.data);
                    self.showError(response.data.message || self.config.strings.error);
                }
            },
            error: function(xhr, status, error) {
                // Keep your existing error handling exactly as is
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText,
                    userData: userData
                });
                
                self.hideTypingIndicator();
                self.setInputState(true);
                
                // Retry logic (keep your existing logic)
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
    };
    
    // Enhanced addBotMessage for Pro features
    window.AIChatbot.addProBotMessage = function(message, responseData) {
        // Use your existing addBotMessage as base
        var messageId = this.generateRandomString(8);
        
        // Build enhanced HTML for Pro
        var html = this.buildProMessageHtml(message, 'bot', Date.now(), messageId, responseData);
        this.$messages.append(html);
        this.scrollToBottom();
        this.animateMessageAppearance(messageId);
        
        // Add Pro features
        this.addProFeatures(messageId, responseData);
    };
    
    // Enhanced message HTML builder for Pro
    window.AIChatbot.buildProMessageHtml = function(message, sender, timestamp, messageId, responseData) {
        // Start with your existing HTML structure
        var senderClass = sender === 'user' ? ' ai-chatbot-message-user' : ' ai-chatbot-message-bot';
        var timeString = this.formatTime(timestamp / 1000);
        
        // Add Pro enhancements
        var sourceIndicator = this.isPro() ? this.getSourceIndicator(responseData.source) : '';
        var proClass = this.isPro() ? ' pro-message' : '';
        
        var html = '<div class="ai-chatbot-message' + senderClass + proClass + '" data-message-id="' + messageId + '">';
        
        // Pro header
        if (this.isPro() && sender === 'bot') {
            html += '<div class="message-header">';
            html += '<span class="message-source">' + sourceIndicator + '</span>';
            html += '<span class="message-time">' + timeString + '</span>';
            html += '</div>';
        }
        
        html += '<div class="ai-chatbot-message-content">';
        html += '<div class="ai-chatbot-message-text">' + this.formatMessage(message) + '</div>';
        
        // Regular time for non-Pro
        if (!this.isPro() || sender === 'user') {
            html += '<span class="ai-chatbot-message-time">' + timeString + '</span>';
        }
        
        html += '</div>';
        html += '</div>';
        
        return html;
    };
    
    // Pro typing indicator
    window.AIChatbot.showProTypingIndicator = function() {
        if (!this.isPro()) {
            return this.showTypingIndicator();
        }
        
        var typingHtml = '<div class="ai-chatbot-typing-indicator pro-typing">' +
            '<div class="thinking-animation">' +
                '<div class="dot"></div>' +
                '<div class="dot"></div>' +
                '<div class="dot"></div>' +
            '</div>' +
            '<span class="thinking-text">' + this.config.strings.thinking + '</span>' +
        '</div>';
        
        this.$messages.append(typingHtml);
        this.scrollToBottom();
        this.isTyping = true;
    };
    
    // Enhanced hideTypingIndicator
    var originalHideTyping = window.AIChatbot.hideTypingIndicator;
    window.AIChatbot.hideTypingIndicator = function() {
        // Remove both regular and Pro typing indicators
        this.$messages.find('.ai-chatbot-typing-indicator, .pro-typing').remove();
        this.isTyping = false;
    };
    
    // Pro context building
    window.AIChatbot.buildProContext = function() {
        if (!this.isPro()) return {};
        
        return {
            page_url: window.location.href,
            page_title: document.title,
            user_agent: navigator.userAgent,
            timestamp: new Date().toISOString(),
            scroll_position: $(window).scrollTop(),
            time_on_page: this.lastActivityTime ? Math.round((Date.now() - this.lastActivityTime) / 1000) : 0,
            message_count: this.messageCount
        };
    };
    
    // Source indicator for Pro
    window.AIChatbot.getSourceIndicator = function(source) {
        if (!this.isPro()) return '';
        
        var indicators = {
            'semantic_training': '🧠 Smart Match',
            'ai_provider_enhanced': '🤖 AI Enhanced',
            'ai_provider': '🤖 AI Generated',
            'semantic_search': '🔍 Semantic Search',
            'training_data': '📚 Knowledge Base'
        };
        
        return indicators[source] || '💬 Response';
    };
    
    // Enhanced message formatting
    var originalFormatMessage = window.AIChatbot.formatMessage;
    window.AIChatbot.formatMessage = function(message) {
        // Apply basic formatting first (your existing logic)
        if (originalFormatMessage) {
            message = originalFormatMessage.call(this, message);
        }
        
        // Add Pro enhancements
        if (this.isPro()) {
            // Email links
            message = message.replace(/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/g, 
                '<a href="mailto:$1">$1</a>');
            
            // Phone links
            message = message.replace(/(\+?1[-.\s]?)?\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})/g, 
                '<a href="tel:+1$2$3$4">$1($2) $3-$4</a>');
            
            // Enhanced formatting
            message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            message = message.replace(/`([^`]+)`/g, '<code>$1</code>');
        }
        
        return message;
    };
    
    // Add Pro features to messages
    window.AIChatbot.addProFeatures = function(messageId, responseData) {
        if (!this.isPro()) return;
        
        var $message = $('[data-message-id="' + messageId + '"]');
        
        // Add confidence indicator
        if (responseData.confidence && window.ai_chatbot_debug) {
            this.addConfidenceIndicator(responseData.confidence, $message);
        }
        
        // Add suggestions
        if (responseData.suggestions && responseData.suggestions.length > 0) {
            this.addSuggestions(responseData.suggestions);
        }
        
        // Add feedback option
        this.addFeedbackOption($message, responseData);
    };
    
    // Add suggestions (Pro feature)
    window.AIChatbot.addSuggestions = function(suggestions) {
        if (!this.isPro() || !suggestions || suggestions.length === 0) return;
        
        var self = this;
        var $suggestionsContainer = $('<div class="pro-suggestions">' +
            '<div class="suggestions-header">💡 You might also ask:</div>' +
            '<div class="suggestions-list"></div>' +
        '</div>');
        
        var $suggestionsList = $suggestionsContainer.find('.suggestions-list');
        
        suggestions.forEach(function(suggestion) {
            var $suggestionButton = $('<button class="suggestion-button">' + suggestion + '</button>');
            
            $suggestionButton.on('click', function() {
                self.sendMessage(suggestion);
                $suggestionsContainer.fadeOut();
            });
            
            $suggestionsList.append($suggestionButton);
        });
        
        this.$messages.append($suggestionsContainer);
        this.scrollToBottom();
        
        // Auto-hide after 30 seconds
        setTimeout(function() {
            $suggestionsContainer.fadeOut();
        }, 30000);
    };
    
    // Add confidence indicator (Pro feature)
    window.AIChatbot.addConfidenceIndicator = function(confidence, $messageElement) {
        if (!this.isPro()) return;
        
        var confidencePercent = Math.round(confidence * 100);
        var confidenceClass = confidencePercent > 80 ? 'high' : 
                             confidencePercent > 60 ? 'medium' : 'low';
        
        var $confidenceIndicator = $('<div class="confidence-indicator ' + confidenceClass + '">' +
            '<span class="confidence-label">Confidence:</span>' +
            '<span class="confidence-value">' + confidencePercent + '%</span>' +
        '</div>');
        
        $messageElement.find('.ai-chatbot-message-content').append($confidenceIndicator);
    };
    
    // Add feedback option (Pro feature)
    window.AIChatbot.addFeedbackOption = function($messageElement, responseData) {
        if (!this.isPro()) return;
        
        var self = this;
        var $feedbackContainer = $('<div class="message-feedback">' +
            '<div class="feedback-question">Was this helpful?</div>' +
            '<div class="feedback-buttons">' +
                '<button class="feedback-btn positive" data-rating="5" title="Very helpful">👍</button>' +
                '<button class="feedback-btn negative" data-rating="1" title="Not helpful">👎</button>' +
            '</div>' +
        '</div>');
        
        $feedbackContainer.find('.feedback-btn').on('click', function() {
            var rating = $(this).data('rating');
            var isPositive = $(this).hasClass('positive');
            
            // Send feedback
            self.sendFeedback(responseData.conversation_id, rating);
            
            // Update UI
            $feedbackContainer.html(
                '<div class="feedback-thanks">' +
                    (isPositive ? '✅ Thank you for your feedback!' : '📝 Thank you for your feedback!') +
                '</div>'
            );
        });
        
        $messageElement.find('.ai-chatbot-message-content').append($feedbackContainer);
    };
    
    // Send feedback (Pro feature)
    window.AIChatbot.sendFeedback = function(conversationId, rating) {
        if (!this.isPro()) return;
        
        $.ajax({
            url: this.config.ajaxUrl,
            method: 'POST',
            data: {
                action: 'ai_chatbot_conversation_feedback',
                conversation_id: conversationId,
                rating: rating,
                nonce: this.config.nonce
            }
        });
    };
    
    // Initialize Pro features when chatbot is ready
    var originalInit = window.AIChatbot.init;
    window.AIChatbot.init = function(config) {
        // Call original init first
        if (originalInit) {
            originalInit.call(this, config);
        }
        
        // Add Pro initialization
        if (this.isPro()) {
            this.initProFeatures();
        }
    };
    
    // Initialize Pro features
    window.AIChatbot.initProFeatures = function() {
        console.log('AIChatbot: Initializing Pro features...');
        
        // Add Pro badge to header
        var $header = $('.ai-chatbot-header');
        if ($header.length && !$header.find('.pro-badge').length) {
            $header.append('<span class="pro-badge">⭐ Pro</span>');
        }
        
        // Track page context
        this.lastActivityTime = Date.now();
        
        // Load Pro styles
        this.loadProStyles();
    };
    
    // Load Pro styles
    window.AIChatbot.loadProStyles = function() {
        if (document.getElementById('ai-chatbot-pro-styles')) {
            return; // Already loaded
        }
        
        var proStyles = `
            .pro-message {
                border-left: 4px solid #6366f1;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            }
            
            .message-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 0.85em;
                color: #64748b;
                margin-bottom: 8px;
            }
            
            .message-source {
                font-weight: 600;
                color: #6366f1;
            }
            
            .pro-typing {
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                color: white;
                border-radius: 20px;
                padding: 15px 20px;
            }
            
            .thinking-animation {
                display: inline-flex;
                align-items: center;
                margin-right: 10px;
            }
            
            .thinking-animation .dot {
                width: 8px;
                height: 8px;
                background: white;
                border-radius: 50%;
                margin: 0 2px;
                animation: thinking 1.4s infinite ease-in-out;
            }
            
            .thinking-animation .dot:nth-child(1) { animation-delay: -0.32s; }
            .thinking-animation .dot:nth-child(2) { animation-delay: -0.16s; }
            
            @keyframes thinking {
                0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
                40% { transform: scale(1); opacity: 1; }
            }
            
            .pro-suggestions {
                margin: 15px 0;
                padding: 15px;
                background: #f8fafc;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
            }
            
            .suggestions-header {
                font-weight: 600;
                color: #374151;
                margin-bottom: 10px;
            }
            
            .suggestion-button {
                background: white;
                border: 1px solid #d1d5db;
                border-radius: 20px;
                padding: 8px 16px;
                font-size: 0.9em;
                color: #374151;
                cursor: pointer;
                margin: 0 8px 8px 0;
                transition: all 0.2s ease;
            }
            
            .suggestion-button:hover {
                background: #6366f1;
                color: white;
                border-color: #6366f1;
                transform: translateY(-1px);
            }
            
            .confidence-indicator {
                margin-top: 10px;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 0.8em;
                display: inline-block;
            }
            
            .confidence-indicator.high { background: #dcfce7; color: #166534; }
            .confidence-indicator.medium { background: #fef3c7; color: #92400e; }
            .confidence-indicator.low { background: #fee2e2; color: #991b1b; }
            
            .message-feedback {
                margin-top: 12px;
                padding: 12px;
                background: rgba(99, 102, 241, 0.05);
                border-radius: 8px;
            }
            
            .feedback-btn {
                background: none;
                border: 1px solid #d1d5db;
                border-radius: 20px;
                padding: 6px 12px;
                font-size: 1.2em;
                cursor: pointer;
                margin-right: 10px;
                transition: all 0.2s ease;
            }
            
            .feedback-btn:hover { transform: scale(1.1); }
            .feedback-btn.positive:hover { background: #dcfce7; border-color: #16a34a; }
            .feedback-btn.negative:hover { background: #fee2e2; border-color: #dc2626; }
            
            .pro-badge {
                background: linear-gradient(135deg, #ffd700 0%, #ffed4a 100%);
                color: #92400e;
                font-size: 0.7em;
                font-weight: bold;
                padding: 2px 8px;
                border-radius: 10px;
                margin-left: 8px;
            }
        `;
        
        var styleSheet = document.createElement('style');
        styleSheet.id = 'ai-chatbot-pro-styles';
        styleSheet.textContent = proStyles;
        document.head.appendChild(styleSheet);
    };
    
    // Set Pro detection
    $(document).ready(function() {
        window.ai_chatbot_pro_enabled = typeof ai_chatbot_pro_enabled !== 'undefined' && ai_chatbot_pro_enabled;
        
        if (window.ai_chatbot_pro_enabled) {
            console.log('AIChatbot: Pro features enabled');
        }
    });

})(jQuery);