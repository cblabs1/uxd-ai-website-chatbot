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
            this.currentSessionId = this.config.sessionId || this.generateSessionId();
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

            setTimeout(function() {
                if (self.currentSessionId) {
                    self.loadConversationHistory();
                }
            }, 500);
            console.log('AIChatbot: Initialized successfully');
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
            } else {
                console.error('Could not find messages container for pre-chat form');
                // Fallback to overlay modal if container not found
                this.createOverlayModal();
            }
        },

        createOverlayModal: function() {
            var modalHTML = `
                <div id="ai-chatbot-prechat-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; display: flex; align-items: center; justify-content: center;">
                    <div id="ai-chatbot-pre-chat-modal" style="background: white; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <div class="ai-chatbot-form-container">
                            <div class="ai-chatbot-form-header">
                                <h3 style="margin: 0 0 10px 0; color: #333;">Welcome! 👋</h3>
                                <p style="margin: 0 0 20px 0; color: #666;">Please provide your details to start chatting.</p>
                            </div>
                            <form id="ai-chatbot-user-form">
                                <div class="ai-chatbot-form-group" style="margin-bottom: 15px;">
                                    <label for="ai-chatbot-user-name">Name *</label>
                                    <input type="text" id="ai-chatbot-user-name" name="name" required placeholder="Enter your full name">
                                </div>
                                <div class="ai-chatbot-form-group" style="margin-bottom: 20px;">
                                    <label for="ai-chatbot-user-email">Email *</label>
                                    <input type="email" id="ai-chatbot-user-email" name="email" required placeholder="Enter your email address">
                                </div>
                                <button type="submit" class="ai-chatbot-form-submit">Start Chatting</button>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHTML);
            
            setTimeout(() => {
                $('#ai-chatbot-user-name').focus();
            }, 300);
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
            
            // Pre-chat overlay form submission (fallback)
            $(document).on('submit.aichatbot', '#ai-chatbot-user-form', function(e) {
                e.preventDefault();
                self.handlePreChatSubmission();
            });
            
            // Send button click
            $(document).on('click.aichatbot', '.ai-chatbot-send-btn, #ai-chatbot-send, .popup-send-btn', function(e) {
                e.preventDefault();
                if (!self.isUserAuthenticated()) {
                    self.showPreChatForm();
                    return;
                }
                self.resetInactivityTimer();
                self.handleSendMessage();
            });
            
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
            console.log('Showing end of conversation rating');
            
            // Don't show if already exists
            if ($('.end-conversation-rating').length > 0) {
                console.log('Rating already exists');
                return;
            }

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
            // Check if there are messages and no rating shown yet
            var hasConversation = this.messageCount > 2; // More than welcome + first user message
            var hasRatingShown = $('.end-conversation-rating').length > 0;
            
            if (hasConversation && !hasRatingShown) {
                // Add dynamic height class for feedback form
                $('.ai-chatbot-container').addClass('has-feedback-form');
                
                // Show feedback form instead of closing immediately
                this.showEndOfConversationRating();
                
                // Set a flag to indicate we're in feedback mode
                this.feedbackMode = true;
                
                // Auto-hide after 30 seconds if no interaction
                setTimeout(() => {
                    if (this.feedbackMode) {
                        this.hideWidget();
                    }
                }, 30000);
            } else {
                // Close directly if no conversation or feedback already shown
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
            // Only load if user is authenticated
            if (!this.currentSessionId) {
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
                    if (response.success && response.data.messages) {
                        self.displayConversationHistory(response.data.messages);
                        self.scrollToBottom();
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

})(jQuery);