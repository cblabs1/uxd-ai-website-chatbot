/**
 * Minimal Inline Pre-Chat JavaScript Integration
 * Replace the previous pre-chat implementation with this
 */

(function($) {
    'use strict';

    /**
     * Inline Pre-Chat Class
     */
    class AIChatbotInlinePreChat {
        constructor() {
            this.isUserIdentified = false;
            this.userData = null;
            this.pendingMessage = null;
            this.formElement = null;
            this.init();
        }

        /**
         * Initialize inline pre-chat
         */
        init() {
            this.checkUserSession();
            this.createInlineForm();
            this.bindEvents();
        }

        /**
         * Check if user is already identified
         */
        checkUserSession() {
            const userData = sessionStorage.getItem('ai_chatbot_user_data');
            if (userData) {
                try {
                    this.userData = JSON.parse(userData);
                    this.isUserIdentified = true;
                } catch (e) {
                    sessionStorage.removeItem('ai_chatbot_user_data');
                }
            }
        }

        /**
         * Create and inject inline form into chat
         */
        createInlineForm() {
            // Don't show form if user is already identified
            if (this.isUserIdentified) {
                return;
            }

            const formHTML = `
                <div id="ai-chatbot-pre-chat-inline" class="ai-chatbot-pre-chat-inline">
                    <div class="ai-chatbot-pre-chat-header">
                        <div class="ai-chatbot-pre-chat-icon">📝</div>
                        <h3 class="ai-chatbot-pre-chat-title">${ai_chatbot_ajax.strings.pre_chat_title || 'Quick Setup'}</h3>
                        <p class="ai-chatbot-pre-chat-subtitle">${ai_chatbot_ajax.strings.pre_chat_subtitle || 'Help us personalize your experience'}</p>
                    </div>

                    <form id="ai-chatbot-pre-chat-form" class="ai-chatbot-pre-chat-form">
                        <div class="ai-chatbot-form-row">
                            <div class="ai-chatbot-form-field">
                                <input 
                                    type="email" 
                                    id="ai-chatbot-user-email" 
                                    name="user_email"
                                    class="ai-chatbot-form-input" 
                                    placeholder="${ai_chatbot_ajax.strings.email_placeholder || 'Email*'}"
                                    required
                                    autocomplete="email"
                                >
                                <div class="ai-chatbot-form-error" data-field="email">
                                    ${ai_chatbot_ajax.strings.email_error || 'Please enter a valid email'}
                                </div>
                            </div>
                            <div class="ai-chatbot-form-field">
                                <input 
                                    type="text" 
                                    id="ai-chatbot-user-name" 
                                    name="user_name"
                                    class="ai-chatbot-form-input" 
                                    placeholder="${ai_chatbot_ajax.strings.name_placeholder || 'Name (optional)'}"
                                    autocomplete="name"
                                >
                                <div class="ai-chatbot-form-error" data-field="name">
                                    ${ai_chatbot_ajax.strings.name_error || 'Name too short'}
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="ai-chatbot-form-submit" id="ai-chatbot-start-chat">
                            ${ai_chatbot_ajax.strings.start_chat || 'Start Chatting'}
                        </button>
                        
                        <div class="ai-chatbot-privacy-note">
                            🔒 ${ai_chatbot_ajax.strings.privacy_text || 'Your info is secure.'} 
                            <a href="${ai_chatbot_ajax.privacy_url || '#'}" target="_blank">
                                ${ai_chatbot_ajax.strings.learn_more || 'Privacy Policy'}
                            </a>
                        </div>
                    </form>
                </div>
            `;

            // Find messages container and inject form
            this.injectFormIntoChat(formHTML);
        }

        /**
         * Inject form into different chat layouts
         */
        injectFormIntoChat(formHTML) {
            let messagesContainer = null;
            
            // Try different selectors for different layouts
            const selectors = [
                '.ai-chatbot-messages',           // Widget layout
                '.messages-container',            // Alternative widget
                '.inline-messages-container',     // Inline layout
                '.popup-messages-container',      // Popup layout
                '.ai-chatbot-popup-body .messages-wrapper', // Popup variant
                '#inline-messages-wrapper'       // Inline variant
            ];

            for (const selector of selectors) {
                messagesContainer = $(selector);
                if (messagesContainer.length > 0) {
                    break;
                }
            }

            if (messagesContainer && messagesContainer.length > 0) {
                // Add form at the beginning of messages
                messagesContainer.prepend(formHTML);
                this.formElement = $('#ai-chatbot-pre-chat-inline');
                
                // Disable input area until form is completed
                this.disableInputArea();
            } else {
                console.warn('AI Chatbot: Could not find messages container for pre-chat form');
            }
        }

        /**
         * Disable chat input until user info is provided
         */
        disableInputArea() {
            // Disable various input selectors
            const inputSelectors = [
                '.ai-chatbot-input',
                '#ai-chatbot-input',
                '.popup-chatbot-input',
                '.inline-chatbot-input'
            ];

            const buttonSelectors = [
                '.ai-chatbot-send-button',
                '#ai-chatbot-send-button', 
                '.ai-chatbot-send-btn',
                '.popup-send-btn'
            ];

            inputSelectors.forEach(selector => {
                $(selector).prop('disabled', true)
                    .attr('placeholder', ai_chatbot_ajax.strings.setup_required || 'Complete setup above to start chatting...')
                    .addClass('ai-chatbot-disabled');
            });

            buttonSelectors.forEach(selector => {
                $(selector).prop('disabled', true).addClass('ai-chatbot-disabled');
            });
        }

        /**
         * Enable chat input after user identification
         */
        enableInputArea() {
            const inputSelectors = [
                '.ai-chatbot-input',
                '#ai-chatbot-input', 
                '.popup-chatbot-input',
                '.inline-chatbot-input'
            ];

            const buttonSelectors = [
                '.ai-chatbot-send-button',
                '#ai-chatbot-send-button',
                '.ai-chatbot-send-btn',
                '.popup-send-btn'
            ];

            inputSelectors.forEach(selector => {
                $(selector).prop('disabled', false)
                    .attr('placeholder', ai_chatbot_ajax.strings.placeholder || 'Type your message...')
                    .removeClass('ai-chatbot-disabled');
            });

            buttonSelectors.forEach(selector => {
                $(selector).prop('disabled', false).removeClass('ai-chatbot-disabled');
            });
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            const self = this;

            // Form submission
            $(document).on('submit', '#ai-chatbot-pre-chat-form', function(e) {
                e.preventDefault();
                self.handleFormSubmit();
            });

            // Real-time validation
            $(document).on('blur', '#ai-chatbot-user-email', function() {
                self.validateEmail($(this).val());
            });

            $(document).on('blur', '#ai-chatbot-user-name', function() {
                self.validateName($(this).val());
            });
        }

        /**
         * Handle form submission
         */
        handleFormSubmit() {
            const email = $('#ai-chatbot-user-email').val().trim();
            const name = $('#ai-chatbot-user-name').val().trim();

            if (!this.validateForm(email, name)) {
                return;
            }

            // Show loading state
            const submitBtn = $('#ai-chatbot-start-chat');
            submitBtn.addClass('loading').prop('disabled', true);

            // Submit to server
            this.submitUserData(email, name)
                .then((response) => {
                    if (response.success) {
                        this.handleSubmissionSuccess(response.data, email, name);
                    } else {
                        this.showFormError(response.data || 'Failed to save information');
                    }
                })
                .catch((error) => {
                    console.error('AI Chatbot: Form submission failed', error);
                    this.showFormError('Connection error. Please try again.');
                })
                .finally(() => {
                    submitBtn.removeClass('loading').prop('disabled', false);
                });
        }

        /**
         * Handle successful form submission
         */
        handleSubmissionSuccess(responseData, email, name) {
            // Store user data
            this.userData = {
                id: responseData.user_id,
                email: email,
                name: name,
                session_id: responseData.session_id
            };
            
            sessionStorage.setItem('ai_chatbot_user_data', JSON.stringify(this.userData));
            this.isUserIdentified = true;

            // Show success message
            this.showSuccessMessage(name);

            // Enable input area
            setTimeout(() => {
                this.enableInputArea();
                
                // Hide form after a moment
                setTimeout(() => {
                    this.hideForm();
                    
                    // Send pending message if exists
                    if (this.pendingMessage) {
                        this.sendPendingMessage();
                    }
                }, 1500);
            }, 800);
        }

        /**
         * Show success message
         */
        showSuccessMessage(name) {
            if (this.formElement) {
                this.formElement.addClass('success');
                this.formElement.html(`
                    <div class="ai-chatbot-success-message">
                        <span>✅</span>
                        <span>Welcome${name ? ', ' + name : ''}! You can now start chatting.</span>
                    </div>
                `);
            }
        }

        /**
         * Hide the form with animation
         */
        hideForm() {
            if (this.formElement) {
                this.formElement.css({
                    'transform': 'scale(0.95)',
                    'opacity': '0',
                    'transition': 'all 0.2s ease'
                });
                
                setTimeout(() => {
                    this.formElement.slideUp(200, () => {
                        this.formElement.remove();
                    });
                }, 200);
            }
        }

        /**
         * Submit user data to server
         */
        submitUserData(email, name) {
            return $.ajax({
                url: ai_chatbot_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ai_chatbot_save_user_data',
                    nonce: ai_chatbot_ajax.nonce,
                    user_email: email,
                    user_name: name,
                    page_url: window.location.href,
                    user_agent: navigator.userAgent
                }
            });
        }

        /**
         * Validate form
         */
        validateForm(email, name) {
            let isValid = true;
            this.clearFormErrors();

            if (!this.validateEmail(email)) {
                isValid = false;
            }

            if (name && !this.validateName(name)) {
                isValid = false;
            }

            return isValid;
        }

        /**
         * Validate email
         */
        validateEmail(email) {
            const isValid = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            if (!isValid) {
                this.showFieldError('email');
                return false;
            }
            return true;
        }

        /**
         * Validate name
         */
        validateName(name) {
            if (name && name.length < 2) {
                this.showFieldError('name');
                return false;
            }
            return true;
        }

        /**
         * Show field error
         */
        showFieldError(fieldName) {
            const errorElement = $(`.ai-chatbot-form-error[data-field="${fieldName}"]`);
            const inputElement = fieldName === 'email' ? 
                $('#ai-chatbot-user-email') : 
                $('#ai-chatbot-user-name');

            errorElement.addClass('show');
            inputElement.addClass('error shake');

            setTimeout(() => {
                inputElement.removeClass('shake');
            }, 400);
        }

        /**
         * Show general form error
         */
        showFormError(message) {
            // Show error in the form
            let generalError = $('.ai-chatbot-general-error');
            if (generalError.length === 0) {
                generalError = $(`<div class="ai-chatbot-form-error ai-chatbot-general-error show">${message}</div>`);
                $('#ai-chatbot-pre-chat-form').prepend(generalError);
            } else {
                generalError.text(message).addClass('show');
            }

            setTimeout(() => {
                generalError.removeClass('show');
            }, 5000);
        }

        /**
         * Clear form errors
         */
        clearFormErrors() {
            $('.ai-chatbot-form-error').removeClass('show');
            $('.ai-chatbot-form-input').removeClass('error shake');
        }

        /**
         * Check if user needs identification
         */
        requiresUserIdentification(message) {
            if (this.isUserIdentified) {
                return false;
            }

            // Store message to send after identification
            this.pendingMessage = message;
            
            // Scroll to form if not visible
            if (this.formElement && this.formElement.length > 0) {
                this.formElement[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Highlight the form briefly
                this.formElement.css('box-shadow', '0 0 0 2px #6366f1');
                setTimeout(() => {
                    this.formElement.css('box-shadow', '');
                }, 1000);
            }
            
            return true;
        }

        /**
         * Send pending message after identification
         */
        sendPendingMessage() {
            if (this.pendingMessage && window.AIChatbotFrontend) {
                // Clear the input and set the pending message
                const inputs = $('.ai-chatbot-input, #ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input');
                inputs.val(this.pendingMessage);
                
                // Trigger send
                setTimeout(() => {
                    if (window.AIChatbotFrontend.handleSendMessage) {
                        window.AIChatbotFrontend.handleSendMessage();
                    } else {
                        // Fallback: trigger form submission
                        $('.ai-chatbot-send-button, #ai-chatbot-send-button, .ai-chatbot-send-btn').first().click();
                    }
                    this.pendingMessage = null;
                }, 100);
            }
        }

        /**
         * Get user data
         */
        getUserData() {
            return this.userData;
        }
    }

    /**
     * CSS Styles for Inline Pre-Chat
     */
    const inlinePreChatCSS = `
        /* Inline Pre-Chat Form Styles */
        .ai-chatbot-pre-chat-inline {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin: 0 0 16px 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            animation: ai-chatbot-slide-in 0.3s ease-out;
            transition: all 0.3s ease;
        }

        @keyframes ai-chatbot-slide-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ai-chatbot-pre-chat-header {
            text-align: center;
            margin-bottom: 16px;
        }

        .ai-chatbot-pre-chat-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .ai-chatbot-pre-chat-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 4px;
        }

        .ai-chatbot-pre-chat-subtitle {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        .ai-chatbot-pre-chat-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .ai-chatbot-form-row {
            display: flex;
            gap: 8px;
        }

        .ai-chatbot-form-field {
            flex: 1;
            position: relative;
        }

        .ai-chatbot-form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }

        .ai-chatbot-form-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
        }

        .ai-chatbot-form-input.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1);
        }

        .ai-chatbot-form-input::placeholder {
            color: #9ca3af;
            font-size: 13px;
        }

        .ai-chatbot-form-input[required] {
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"><text x="1" y="6" font-size="6" fill="%23ef4444">*</text></svg>');
            background-repeat: no-repeat;
            background-position: right 8px center;
            padding-right: 24px;
        }

        .ai-chatbot-form-error {
            color: #ef4444;
            font-size: 11px;
            margin-top: 4px;
            display: none;
        }

        .ai-chatbot-form-error.show {
            display: block;
        }

        .ai-chatbot-general-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 12px;
        }

        .ai-chatbot-form-submit {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .ai-chatbot-form-submit:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .ai-chatbot-form-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .ai-chatbot-form-submit.loading {
            color: transparent;
        }

        .ai-chatbot-form-submit.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: ai-chatbot-spin 1s linear infinite;
        }

        @keyframes ai-chatbot-spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .ai-chatbot-privacy-note {
            font-size: 11px;
            color: #64748b;
            text-align: center;
            margin-top: 8px;
            line-height: 1.4;
        }

        .ai-chatbot-privacy-note a {
            color: #6366f1;
            text-decoration: none;
        }

        @keyframes ai-chatbot-shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }

        .ai-chatbot-form-input.shake {
            animation: ai-chatbot-shake 0.4s ease-in-out;
        }

        .ai-chatbot-pre-chat-inline.success {
            background: #f0fdf4;
            border-color: #16a34a;
        }

        .ai-chatbot-pre-chat-inline.success .ai-chatbot-pre-chat-title {
            color: #16a34a;
        }

        .ai-chatbot-success-message {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #16a34a;
            font-size: 14px;
            font-weight: 500;
        }

        /* Disabled input styles */
        .ai-chatbot-disabled {
            background: #f9fafb !important;
            color: #9ca3af !important;
            cursor: not-allowed !important;
        }

        /* Mobile responsive */
        @media (max-width: 480px) {
            .ai-chatbot-pre-chat-inline {
                margin: 0 0 12px 0;
                padding: 16px;
            }
            
            .ai-chatbot-form-row {
                flex-direction: column;
                gap: 8px;
            }
            
            .ai-chatbot-form-input {
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }

        /* Integration with different layouts */
        .ai-chatbot-popup .ai-chatbot-pre-chat-inline {
            margin: 16px 20px 16px 20px;
        }

        .ai-chatbot-widget .ai-chatbot-pre-chat-inline {
            margin: 0 16px 16px 16px;
        }
    `;

    /**
     * Integration with existing chatbot functionality
     */
    $(document).ready(function() {
        // Inject CSS
        if (!$('#ai-chatbot-inline-prechat-css').length) {
            $('<style id="ai-chatbot-inline-prechat-css">' + inlinePreChatCSS + '</style>').appendTo('head');
        }

        // Initialize inline pre-chat
        window.AIChatbotInlinePreChat = new AIChatbotInlinePreChat();

        // Hook into existing message sending functionality
        const originalHandleSendMessage = window.AIChatbotFrontend?.handleSendMessage;
        if (originalHandleSendMessage && window.AIChatbotFrontend) {
            window.AIChatbotFrontend.handleSendMessage = function() {
                const message = this.$input?.val()?.trim() || 
                               $('.ai-chatbot-input input, #ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input').val()?.trim();

                // Check if user identification is required
                if (window.AIChatbotInlinePreChat.requiresUserIdentification(message)) {
                    return; // Form will handle message sending after identification
                }

                // Call original function
                return originalHandleSendMessage.call(this);
            };
        }

        // Hook into form submissions
        $(document).on('submit', '.ai-chatbot-input-form, #ai-chatbot-input-form, #ai-popup-form, #inline-chatbot-form', function(e) {
            const form = $(this);
            const input = form.find('.ai-chatbot-input, #ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input');
            const message = input.val()?.trim();

            if (message && window.AIChatbotInlinePreChat.requiresUserIdentification(message)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        // Hook into send button clicks
        $(document).on('click', '.ai-chatbot-send-button, #ai-chatbot-send-button, .ai-chatbot-send-btn, .popup-send-btn', function(e) {
            const button = $(this);
            const form = button.closest('form, .ai-chatbot-input-area, .popup-input-area, .inline-input-section');
            const input = form.find('.ai-chatbot-input, #ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input');
            const message = input.val()?.trim();

            if (message && window.AIChatbotInlinePreChat.requiresUserIdentification(message)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        // Hook into starter buttons and suggestion chips
        $(document).on('click', '.starter-btn, .suggestion-chip', function(e) {
            const message = $(this).data('message') || $(this).data('suggestion') || $(this).text();
            
            if (message && window.AIChatbotInlinePreChat.requiresUserIdentification(message)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        // Hook into enter key in inputs
        $(document).on('keypress', '.ai-chatbot-input, #ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                const message = $(this).val()?.trim();
                
                if (message && window.AIChatbotInlinePreChat.requiresUserIdentification(message)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }
        });
    });

})(jQuery);