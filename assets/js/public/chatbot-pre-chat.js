(function($) {
    'use strict';

    /**
     * Enhanced Pre-Chat Class with Better Debugging
     */
    class AIChatbotInlinePreChat {
        
        constructor() {
            console.log('AI Chatbot: Initializing pre-chat...');
            this.isUserIdentified = false;
            this.userData = null;
            this.pendingMessage = null;
            this.formElement = null;
            this.isFormShown = false;
            this.debugMode = true; // Enable debug mode
            this.init();
        }

        init() {
            this.log('Pre-chat init started');
            this.checkUserSession();
            this.bindEvents();
            this.injectCSS(); // Ensure CSS is loaded
            this.log('Pre-chat init completed. User identified:', this.isUserIdentified);
        }

        log(message, ...args) {
            if (this.debugMode) {
                console.log('AI Chatbot Pre-Chat:', message, ...args);
            }
        }

        /**
         * Inject CSS styles if not already present
         */
        injectCSS() {
            if (!$('#ai-chatbot-inline-prechat-css').length) {
                const css = `
                    .ai-chatbot-pre-chat-inline {
                        background: #f8fafc;
                        border: 1px solid #e2e8f0;
                        border-radius: 12px;
                        margin: 0 0 16px 0;
                        padding: 20px;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        animation: ai-chatbot-slide-in 0.3s ease-out;
                    }
                    
                    @keyframes ai-chatbot-slide-in {
                        from { opacity: 0; transform: translateY(10px); }
                        to { opacity: 1; transform: translateY(0); }
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
                    
                    .ai-chatbot-form-error {
                        color: #ef4444;
                        font-size: 11px;
                        margin-top: 4px;
                        display: none;
                    }
                    
                    .ai-chatbot-form-error.show {
                        display: block;
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
                    }
                    
                    .ai-chatbot-form-submit:hover {
                        transform: translateY(-1px);
                        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
                    }
                    
                    .ai-chatbot-form-submit.loading {
                        color: transparent;
                        position: relative;
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
                        animation: spin 1s linear infinite;
                    }
                    
                    @keyframes spin {
                        0% { transform: translate(-50%, -50%) rotate(0deg); }
                        100% { transform: translate(-50%, -50%) rotate(360deg); }
                    }
                    
                    .ai-chatbot-privacy-note {
                        font-size: 11px;
                        color: #64748b;
                        text-align: center;
                        margin-top: 8px;
                    }
                    
                    .ai-chatbot-privacy-note a {
                        color: #6366f1;
                        text-decoration: none;
                    }
                    
                    .ai-chatbot-pre-chat-inline.success {
                        background: #f0fdf4;
                        border-color: #16a34a;
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
                    
                    @media (max-width: 480px) {
                        .ai-chatbot-form-row {
                            flex-direction: column;
                        }
                    }
                `;
                
                $('<style id="ai-chatbot-inline-prechat-css">' + css + '</style>').appendTo('head');
                this.log('CSS injected');
            }
        }

        checkUserSession() {
            const userData = sessionStorage.getItem('ai_chatbot_user_data');
            this.log('Checking session storage:', userData);
            
            if (userData) {
                try {
                    this.userData = JSON.parse(userData);
                    this.isUserIdentified = true;
                    this.log('User already identified:', this.userData);
                } catch (e) {
                    this.log('Invalid session data, clearing');
                    sessionStorage.removeItem('ai_chatbot_user_data');
                }
            } else {
                this.log('No user session found');
            }
        }

        requiresUserIdentification(message) {
            this.log('requiresUserIdentification called with message:', message);
            this.log('Current state - identified:', this.isUserIdentified, 'form shown:', this.isFormShown);
            
            if (this.isUserIdentified) {
                this.log('User already identified, allowing message');
                return false;
            }

            if (this.isFormShown) {
                this.log('Form already shown, blocking message');
                return true;
            }

            this.log('Showing form for first message');
            return this.showFormOnFirstMessage(message);
        }

        showFormOnFirstMessage(message) {
            this.log('showFormOnFirstMessage called with:', message);
            
            this.pendingMessage = message;
            this.createAndShowForm();
            this.hideInputArea();
            this.isFormShown = true;
            
            this.log('Form should be visible now');
            return true;
        }

        createAndShowForm() {
            // Create modal overlay that appears on top of everything
            const modalHTML = `
                <div id="ai-chatbot-prechat-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    backdrop-filter: blur(3px);
                    animation: fadeIn 0.3s ease;
                ">
                    <div id="ai-chatbot-pre-chat-modal" style="
                        background: white;
                        border-radius: 16px;
                        padding: 0;
                        max-width: 420px;
                        width: 90%;
                        max-height: 90vh;
                        overflow: hidden;
                        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                        animation: slideInScale 0.4s ease;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    ">
                        <!-- Header -->
                        <div style="
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 30px 25px 25px 25px;
                            text-align: center;
                            position: relative;
                        ">
                            <div style="
                                background: rgba(255, 255, 255, 0.2);
                                width: 60px;
                                height: 60px;
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin: 0 auto 15px auto;
                                font-size: 28px;
                            ">💬</div>
                            <h2 style="
                                margin: 0 0 8px 0;
                                font-size: 24px;
                                font-weight: 600;
                                letter-spacing: -0.5px;
                            ">Welcome to Our Chat</h2>
                            <p style="
                                margin: 0;
                                opacity: 0.9;
                                font-size: 16px;
                                line-height: 1.4;
                            ">Let's get you started with a quick setup</p>
                        </div>

                        <!-- Form Content -->
                        <div style="padding: 30px 25px;">
                            <form id="ai-chatbot-pre-chat-form" class="ai-chatbot-pre-chat-form">
                                <div style="margin-bottom: 20px;">
                                    <label style="
                                        display: block;
                                        margin-bottom: 8px;
                                        font-weight: 500;
                                        color: #374151;
                                        font-size: 14px;
                                    ">Email Address *</label>
                                    <input 
                                        type="email" 
                                        id="ai-chatbot-user-email" 
                                        name="user_email"
                                        class="ai-chatbot-form-input" 
                                        placeholder="your.email@example.com"
                                        required
                                        style="
                                            width: 100%;
                                            padding: 14px 16px;
                                            border: 2px solid #e5e7eb;
                                            border-radius: 12px;
                                            font-size: 16px;
                                            transition: all 0.2s ease;
                                            background: #f9fafb;
                                            box-sizing: border-box;
                                        "
                                        onfocus="this.style.borderColor='#667eea'; this.style.background='white';"
                                        onblur="this.style.borderColor='#e5e7eb'; this.style.background='#f9fafb';"
                                    >
                                    <div class="ai-chatbot-form-error" data-field="email" style="
                                        color: #ef4444;
                                        font-size: 13px;
                                        margin-top: 6px;
                                        display: none;
                                        font-weight: 500;
                                    ">
                                        ⚠️ Please enter a valid email address
                                    </div>
                                </div>

                                <div style="margin-bottom: 25px;">
                                    <label style="
                                        display: block;
                                        margin-bottom: 8px;
                                        font-weight: 500;
                                        color: #374151;
                                        font-size: 14px;
                                    ">Your Name (Optional)</label>
                                    <input 
                                        type="text" 
                                        id="ai-chatbot-user-name" 
                                        name="user_name"
                                        class="ai-chatbot-form-input" 
                                        placeholder="John Doe"
                                        style="
                                            width: 100%;
                                            padding: 14px 16px;
                                            border: 2px solid #e5e7eb;
                                            border-radius: 12px;
                                            font-size: 16px;
                                            transition: all 0.2s ease;
                                            background: #f9fafb;
                                            box-sizing: border-box;
                                        "
                                        onfocus="this.style.borderColor='#667eea'; this.style.background='white';"
                                        onblur="this.style.borderColor='#e5e7eb'; this.style.background='#f9fafb';"
                                    >
                                    <div class="ai-chatbot-form-error" data-field="name" style="
                                        color: #ef4444;
                                        font-size: 13px;
                                        margin-top: 6px;
                                        display: none;
                                        font-weight: 500;
                                    ">
                                        ⚠️ Name should be at least 2 characters
                                    </div>
                                </div>

                                <button type="submit" id="ai-chatbot-start-chat" style="
                                    width: 100%;
                                    padding: 16px;
                                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                    color: white;
                                    border: none;
                                    border-radius: 12px;
                                    font-size: 16px;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.2s ease;
                                    margin-bottom: 20px;
                                " 
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 20px rgba(102, 126, 234, 0.4)';"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <span class="button-text">🚀 Start Chatting</span>
                                    <span class="loading-text" style="display: none;">⏳ Setting up...</span>
                                </button>
                                
                                <div style="
                                    text-align: center;
                                    color: #6b7280;
                                    font-size: 13px;
                                    line-height: 1.5;
                                    background: #f3f4f6;
                                    padding: 12px;
                                    border-radius: 8px;
                                ">
                                    🔒 Your information is secure and will only be used to personalize your chat experience
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;

            // Add to page body (not inside chat)
            $('body').append(modalHTML);
            
            // Store reference
            this.formElement = $('#ai-chatbot-pre-chat-modal');
            
            // Focus on email field after animation
            setTimeout(() => {
                $('#ai-chatbot-user-email').focus();
            }, 400);
            
            // Prevent closing by clicking overlay (make it mandatory)
            $('#ai-chatbot-prechat-overlay').on('click', function(e) {
                if (e.target === this) {
                    // Shake animation when trying to close
                    $('#ai-chatbot-pre-chat-modal').css('animation', 'shake 0.5s ease');
                    setTimeout(() => {
                        $('#ai-chatbot-pre-chat-modal').css('animation', 'slideInScale 0.4s ease');
                    }, 500);
                }
            });
        }
    

        injectFormIntoChat(formHTML) {
            const selectors = [
                '.ai-chatbot-messages',
                '.messages-container', 
                '.inline-messages-container',
                '.popup-messages-container',
                '.ai-chatbot-popup-body .messages-wrapper',
                '#inline-messages-wrapper'
            ];

            let messagesContainer = null;
            
            for (const selector of selectors) {
                messagesContainer = $(selector);
                this.log('Trying selector:', selector, 'found:', messagesContainer.length);
                if (messagesContainer.length > 0) {
                    break;
                }
            }

            if (messagesContainer && messagesContainer.length > 0) {
                this.log('Injecting form into:', messagesContainer);
                messagesContainer.append(formHTML);
                this.formElement = $('#ai-chatbot-pre-chat-inline');
                
                // Scroll to form
                setTimeout(() => {
                    this.formElement[0].scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                    $('#ai-chatbot-user-email').focus();
                }, 300);
            } else {
                this.log('ERROR: Could not find messages container!');
                console.error('AI Chatbot: No messages container found. Available elements:', {
                    messages: $('.ai-chatbot-messages').length,
                    containers: $('.messages-container').length,
                    inline: $('.inline-messages-container').length,
                    popup: $('.popup-messages-container').length
                });
            }
        }

        hideInputArea() {
            this.log('Hiding input areas');
            const inputAreas = [
                '.ai-chatbot-input-area',
                '.ai-chatbot-input-form',
                '.popup-input-area', 
                '.inline-input-section',
                '#ai-chatbot-input-form',
                '#ai-popup-form',
                '#inline-chatbot-form'
            ];

            inputAreas.forEach(selector => {
                const elements = $(selector);
                this.log('Hiding:', selector, 'found:', elements.length);
                elements.hide();
            });
        }

        showInputArea() {
            this.log('Showing input areas');
            const inputAreas = [
                '.ai-chatbot-input-area',
                '.ai-chatbot-input-form',
                '.popup-input-area',
                '.inline-input-section', 
                '#ai-chatbot-input-form',
                '#ai-popup-form',
                '#inline-chatbot-form'
            ];

            inputAreas.forEach(selector => {
                $(selector).show();
            });
        }

        bindEvents() {
            const self = this;

            // Form submission
            $(document).on('submit', '#ai-chatbot-pre-chat-form', function(e) {
                e.preventDefault();
                self.log('Form submitted');
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

        handleFormSubmit() {
            const email = $('#ai-chatbot-user-email').val().trim();
            const name = $('#ai-chatbot-user-name').val().trim();

            if (!this.validateForm(email, name)) {
                return;
            }

            // Show loading state with better animation
            const submitBtn = $('#ai-chatbot-start-chat');
            const buttonText = submitBtn.find('.button-text');
            const loadingText = submitBtn.find('.loading-text');
            
            buttonText.hide();
            loadingText.show();
            submitBtn.prop('disabled', true).css({
                'background': '#9ca3af',
                'cursor': 'not-allowed'
            });

            // Submit to server
            this.submitUserData(email, name)
                .then((response) => {
                    if (response.success) {
                        this.handleSubmissionSuccess(response.data, email, name);
                    } else {
                        this.showFormError(response.data?.message || 'Failed to save information');
                        this.resetSubmitButton(submitBtn, buttonText, loadingText);
                    }
                })
                .catch((error) => {
                    console.error('AI Chatbot: Form submission failed', error);
                    this.showFormError('Connection error. Please try again.');
                    this.resetSubmitButton(submitBtn, buttonText, loadingText);
                });
        }

        resetSubmitButton(submitBtn, buttonText, loadingText) {
            buttonText.show();
            loadingText.hide();
            submitBtn.prop('disabled', false).css({
                'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'cursor': 'pointer'
            });
        }

        submitUserData(email, name) {
            return $.ajax({
                url: ai_chatbot_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                timeout: 30000,
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

        handleSubmissionSuccess(responseData, email, name) {
            this.userData = {
                id: responseData.user_id,
                email: email,
                name: name,
                session_id: responseData.session_id
            };
            
            sessionStorage.setItem('ai_chatbot_user_data', JSON.stringify(this.userData));
            this.isUserIdentified = true;

            this.log('User identification successful:', this.userData);

            // Show success message
            this.showSuccessMessage(name);

            // Show input area and hide form
            setTimeout(() => {
                this.showInputArea();
                
                setTimeout(() => {
                    this.hideForm();
                    
                    if (this.pendingMessage) {
                        this.sendPendingMessage();
                    }
                }, 1000);
            }, 800);
        }

        showSuccessMessage(name) {
            if (this.formElement) {
                const successHTML = `
                    <div style="
                        text-align: center;
                        padding: 30px 25px;
                        animation: successPulse 0.6s ease;
                    ">
                        <div style="
                            background: #10b981;
                            color: white;
                            width: 80px;
                            height: 80px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 20px auto;
                            font-size: 36px;
                        ">✅</div>
                        <h3 style="
                            margin: 0 0 10px 0;
                            color: #065f46;
                            font-size: 22px;
                            font-weight: 600;
                        ">Welcome${name ? ', ' + name : ''}! 🎉</h3>
                        <p style="
                            margin: 0;
                            color: #6b7280;
                            font-size: 16px;
                        ">You're all set to start chatting</p>
                    </div>
                `;
                this.formElement.html(successHTML);
            }
        }

        hideForm() {
            const overlay = $('#ai-chatbot-prechat-overlay');
            if (overlay.length > 0) {
                overlay.css({
                    'opacity': '0',
                    'transition': 'opacity 0.3s ease'
                });
                
                setTimeout(() => {
                    overlay.remove();
                }, 300);
            }
            
            // Reset states
            this.isFormShown = false;
            this.formElement = null;
        }

        sendPendingMessage() {
            if (this.pendingMessage) {
                this.log('Sending pending message:', this.pendingMessage);
                
                const inputs = $('.ai-chatbot-input, #ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input');
                inputs.val(this.pendingMessage);
                
                setTimeout(() => {
                    if (window.AIChatbotFrontend && window.AIChatbotFrontend.handleSendMessage) {
                        this.log('Using AIChatbotFrontend.handleSendMessage');
                        window.AIChatbotFrontend.handleSendMessage();
                    } else {
                        this.log('Using fallback send method');
                        const form = inputs.closest('form');
                        if (form.length > 0) {
                            form.submit();
                        } else {
                            $('.ai-chatbot-send-button, #ai-chatbot-send-button, .ai-chatbot-send-btn, .popup-send-btn').first().click();
                        }
                    }
                    this.pendingMessage = null;
                }, 200);
            }
        }

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

        validateEmail(email) {
            const isValid = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            if (!isValid) {
                this.showFieldError('email');
                return false;
            }
            return true;
        }

        validateName(name) {
            if (name && name.length < 2) {
                this.showFieldError('name');
                return false;
            }
            return true;
        }

        showFieldError(fieldName) {
            const errorElement = $(`.ai-chatbot-form-error[data-field="${fieldName}"]`);
            const inputElement = fieldName === 'email' ? 
                $('#ai-chatbot-user-email') : 
                $('#ai-chatbot-user-name');

            errorElement.addClass('show');
            inputElement.addClass('error');
        }

        showFormError(message) {
            let generalError = $('.ai-chatbot-general-error');
            if (generalError.length === 0) {
                generalError = $(`<div class="ai-chatbot-form-error ai-chatbot-general-error show">${message}</div>`);
                $('#ai-chatbot-pre-chat-form').prepend(generalError);
            } else {
                generalError.text(message).addClass('show');
            }

            setTimeout(() => {
                generalError.removeClass('show');
            }, 8000);
        }

        clearFormErrors() {
            $('.ai-chatbot-form-error').removeClass('show');
            $('.ai-chatbot-form-input').removeClass('error');
        }
    }

    // Initialize immediately when script loads
    $(document).ready(function() {
        // Always initialize pre-chat
        window.AIChatbotInlinePreChat = new AIChatbotInlinePreChat();
        console.log('AI Chatbot: Pre-chat initialized');

        // Hook into existing functionality with better error handling
        setTimeout(() => {
            // Hook into form submissions
            $(document).on('submit', '.ai-chatbot-input-form, #ai-chatbot-input-form, #ai-popup-form, #inline-chatbot-form', function(e) {
                const form = $(this);
                const input = form.find('.ai-chatbot-input, #ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input');
                const message = input.val()?.trim();

                console.log('Form submit intercepted, message:', message);

                if (message && window.AIChatbotInlinePreChat && window.AIChatbotInlinePreChat.requiresUserIdentification(message)) {
                    console.log('Blocking form submission for pre-chat');
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

                console.log('Send button clicked, message:', message);

                if (message && window.AIChatbotInlinePreChat && window.AIChatbotInlinePreChat.requiresUserIdentification(message)) {
                    console.log('Blocking send button for pre-chat');
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });

            // Hook into enter key
            $(document).on('keypress', '.ai-chatbot-input, #ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    const message = $(this).val()?.trim();
                    
                    console.log('Enter key pressed, message:', message);

                    if (message && window.AIChatbotInlinePreChat && window.AIChatbotInlinePreChat.requiresUserIdentification(message)) {
                        console.log('Blocking enter key for pre-chat');
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                }
            });
        }, 500);
    });

})(jQuery);