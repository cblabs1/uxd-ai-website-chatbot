/**
 * Fixed Inline Pre-Chat - Shows on first message attempt + hides input area
 * Replace the previous JavaScript with this fixed version
 */

(function($) {
    'use strict';

    /**
     * Fixed Inline Pre-Chat Class
     */
    class AIChatbotInlinePreChat {
        constructor() {
            this.isUserIdentified = false;
            this.userData = null;
            this.pendingMessage = null;
            this.formElement = null;
            this.isFormShown = false;
            this.init();
        }

        /**
         * Initialize inline pre-chat
         */
        init() {
            this.checkUserSession();
            // DON'T create form immediately - wait for first message attempt
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
         * Show form when user tries to send first message
         */
        showFormOnFirstMessage(message) {
            if (this.isFormShown || this.isUserIdentified) {
                return false; // Don't show form
            }

            this.pendingMessage = message;
            this.createAndShowForm();
            this.hideInputArea(); // Hide the input area when form is shown
            this.isFormShown = true;
            return true; // Block the message
        }

        /**
         * Create and inject inline form into chat
         */
        createAndShowForm() {
            const formHTML = `
                <div id="ai-chatbot-pre-chat-inline" class="ai-chatbot-pre-chat-inline">
                    <div class="ai-chatbot-pre-chat-header">
                        <div class="ai-chatbot-pre-chat-icon">📝</div>
                        <h3 class="ai-chatbot-pre-chat-title">Quick Setup</h3>
                        <p class="ai-chatbot-pre-chat-subtitle">Help us personalize your experience</p>
                    </div>

                    <form id="ai-chatbot-pre-chat-form" class="ai-chatbot-pre-chat-form">
                        <div class="ai-chatbot-form-row">
                            <div class="ai-chatbot-form-field">
                                <input 
                                    type="email" 
                                    id="ai-chatbot-user-email" 
                                    name="user_email"
                                    class="ai-chatbot-form-input" 
                                    placeholder="Email*"
                                    required
                                    autocomplete="email"
                                >
                                <div class="ai-chatbot-form-error" data-field="email">
                                    Please enter a valid email
                                </div>
                            </div>
                            <div class="ai-chatbot-form-field">
                                <input 
                                    type="text" 
                                    id="ai-chatbot-user-name" 
                                    name="user_name"
                                    class="ai-chatbot-form-input" 
                                    placeholder="Name (optional)"
                                    autocomplete="name"
                                >
                                <div class="ai-chatbot-form-error" data-field="name">
                                    Name too short
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="ai-chatbot-form-submit" id="ai-chatbot-start-chat">
                            Start Chatting
                        </button>
                        
                        <div class="ai-chatbot-privacy-note">
                            🔒 Your info is secure. <a href="#" target="_blank">Privacy Policy</a>
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
                // Add form at the end of messages (not beginning)
                messagesContainer.append(formHTML);
                this.formElement = $('#ai-chatbot-pre-chat-inline');
                
                // Scroll to form
                this.scrollToForm();
                
                // Focus on email field
                setTimeout(() => {
                    $('#ai-chatbot-user-email').focus();
                }, 300);
            } else {
                console.warn('AI Chatbot: Could not find messages container for pre-chat form');
            }
        }

        /**
         * Hide chat input area completely when form is shown
         */
        hideInputArea() {
            // Hide various input areas
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
                $(selector).hide();
            });
        }

        /**
         * Show chat input area after user identification
         */
        showInputArea() {
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

        /**
         * Scroll to form
         */
        scrollToForm() {
            if (this.formElement && this.formElement.length > 0) {
                this.formElement[0].scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
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
                        this.showFormError(response.data?.message || 'Failed to save information');
                    }
                })
                .catch((error) => {
                    console.error('AI Chatbot: Form submission failed', error);
                    
                    // More specific error handling
                    let errorMessage = 'Connection error. Please try again.';
                    if (error.responseJSON && error.responseJSON.data) {
                        errorMessage = error.responseJSON.data.message || error.responseJSON.data;
                    } else if (error.statusText) {
                        errorMessage = 'Server error: ' + error.statusText;
                    }
                    
                    this.showFormError(errorMessage);
                })
                .finally(() => {
                    submitBtn.removeClass('loading').prop('disabled', false);
                });
        }

        /**
         * Submit user data to server
         */
        submitUserData(email, name) {
            return $.ajax({
                url: ai_chatbot_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                timeout: 30000, // 30 second timeout
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

            // Show input area and hide form
            setTimeout(() => {
                this.showInputArea();
                
                // Hide form after a moment
                setTimeout(() => {
                    this.hideForm();
                    
                    // Send pending message if exists
                    if (this.pendingMessage) {
                        this.sendPendingMessage();
                    }
                }, 1000);
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
         * Send pending message after identification
         */
        sendPendingMessage() {
            if (this.pendingMessage) {
                // Clear and set the input with pending message
                const inputs = $('.ai-chatbot-input, #ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input');
                inputs.val(this.pendingMessage);
                
                // Trigger send after a short delay
                setTimeout(() => {
                    // Try different methods to send the message
                    if (window.AIChatbotFrontend && window.AIChatbotFrontend.handleSendMessage) {
                        window.AIChatbotFrontend.handleSendMessage();
                    } else {
                        // Fallback: trigger submit on form
                        const form = inputs.closest('form');
                        if (form.length > 0) {
                            form.submit();
                        } else {
                            // Fallback: click send button
                            $('.ai-chatbot-send-button, #ai-chatbot-send-button, .ai-chatbot-send-btn, .popup-send-btn').first().click();
                        }
                    }
                    this.pendingMessage = null;
                }, 200);
            }
        }

        /**
         * Check if user needs identification (main entry point)
         */
        requiresUserIdentification(message) {
            if (this.isUserIdentified) {
                return false; // User already identified, allow message
            }

            // Show form and block message
            return this.showFormOnFirstMessage(message);
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

        /**
         * Clear form errors
         */
        clearFormErrors() {
            $('.ai-chatbot-form-error').removeClass('show');
            $('.ai-chatbot-form-input').removeClass('error shake');
        }

        /**
         * Get user data
         */
        getUserData() {
            return this.userData;
        }
    }

    /**
     * Integration with existing chatbot functionality
     */
    $(document).ready(function() {
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

        // Hook into starter buttons and suggestion chips
        $(document).on('click', '.starter-btn, .suggestion-chip', function(e) {
            const message = $(this).data('message') || $(this).data('suggestion') || $(this).text();
            
            if (message && window.AIChatbotInlinePreChat.requiresUserIdentification(message)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    });

})(jQuery);