/**
 * AI Chatbot Widget JavaScript - Optimized Lightweight Version
 * Delegates core functionality to chatbot-frontend.js
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * AI Chatbot Widget Class - Lightweight Version
     * Focuses only on widget-specific UI interactions
     */
    class AIChatbotWidget {
        constructor() {
            this.widget = null;
            this.container = null;
            this.messages = null;
            this.input = null;
            this.isOpen = false;
            this.isMinimized = false;
            this.conversationId = this.generateConversationId();
            this.initializationAttempts = 0;
            this.maxInitAttempts = 50; // 5 seconds max wait
            
            // Wait for main AIChatbot before initializing
            this.waitForMainChatbot().then(() => {
                this.init();
            }).catch((error) => {
                console.error('AIChatbotWidget initialization failed:', error);
                // Still initialize basic widget functionality
                this.initBasicWidget();
            });
        }

        /**
         * Wait for main AIChatbot to be available
         */
        async waitForMainChatbot() {
            return new Promise((resolve, reject) => {
                const checkAIChatbot = () => {
                    this.initializationAttempts++;
                    
                    if (typeof window.AIChatbot !== 'undefined' && 
                        window.AIChatbot.init && 
                        (window.AIChatbot.initialized || window.AIChatbot.config)) {
                        console.log('AIChatbotWidget: Main AIChatbot found and ready');
                        resolve();
                    } else if (this.initializationAttempts >= this.maxInitAttempts) {
                        reject(new Error('Main AIChatbot not available after timeout'));
                    } else {
                        setTimeout(checkAIChatbot, 100);
                    }
                };
                
                checkAIChatbot();
            });
        }

        /**
         * Initialize widget with main chatbot integration
         */
        init() {
            console.log('AIChatbotWidget: Initializing with main AIChatbot integration');
            
            this.initializeElements();
            this.bindEvents();
            this.setupAutoResize();
            
            console.log('AIChatbotWidget: Initialization complete');
        }

        /**
         * Initialize basic widget functionality (fallback)
         */
        initBasicWidget() {
            console.log('AIChatbotWidget: Initializing basic widget (fallback mode)');
            
            this.initializeElements();
            this.bindBasicEvents();
            this.setupAutoResize();
            
            // Show error message in widget
            setTimeout(() => {
                if (this.messages && this.messages.length) {
                    this.addErrorMessage('Chat functionality unavailable. Please refresh the page.');
                }
            }, 1000);
        }

        /**
         * Initialize DOM elements
         */
        initializeElements() {
            this.widget = $('#ai-chatbot-widget');
            
            if (this.widget.length) {
                this.container = this.widget.find('.ai-chatbot-container');
                this.messages = this.widget.find('.ai-chatbot-messages');
                this.input = this.widget.find('.ai-chatbot-input');
            } else {
                // Try alternative selectors
                this.widget = $('.ai-chatbot-widget');
                this.container = $('.ai-chatbot-container');
                this.messages = $('.ai-chatbot-messages, .messages-container, .inline-messages-container, .popup-messages-container');
                this.input = $('.ai-chatbot-input');
            }
            
            console.log('AIChatbotWidget: Elements initialized', {
                widget: this.widget.length,
                container: this.container.length,
                messages: this.messages.length,
                input: this.input.length
            });
        }

        /**
         * Bind events with main chatbot integration
         */
        bindEvents() {
            const self = this;

            // Widget toggle
            $(document).on('click.aiwidget', '.ai-chatbot-toggle', function(e) {
                e.preventDefault();
                self.toggle();
            });

            // Close and minimize buttons
            $(document).on('click.aiwidget', '.ai-chatbot-close', function(e) {
                e.preventDefault();
                self.close();
            });

            $(document).on('click.aiwidget', '.ai-chatbot-minimize', function(e) {
                e.preventDefault();
                self.minimize();
            });

            // Message sending - DELEGATE to main chatbot
            $(document).on('submit.aiwidget', '.ai-chatbot-input-form', function(e) {
                e.preventDefault();
                const input = $(this).find('.ai-chatbot-input');
                const message = input.val().trim();
                if (message) {
                    self.delegateMessageSending(message, input);
                }
            });

            $(document).on('keydown.aiwidget', '.ai-chatbot-input', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const message = $(this).val().trim();
                    if (message) {
                        self.delegateMessageSending(message, $(this));
                    }
                }
            });

            $(document).on('click.aiwidget', '.ai-chatbot-send-button', function(e) {
                e.preventDefault();
                const container = $(this).closest('.ai-chatbot-input-container, .ai-chatbot-input-form');
                const input = container.find('.ai-chatbot-input');
                const message = input.val().trim();
                
                if (message) {
                    self.delegateMessageSending(message, input);
                }
            });

            // Input change handling
            $(document).on('input.aiwidget keyup.aiwidget', '.ai-chatbot-input', function() {
                self.handleInputChange($(this));
            });

            // Starter buttons - DELEGATE to main chatbot
            $(document).on('click.aiwidget', '.starter-btn', function(e) {
                e.preventDefault();
                const message = $(this).data('message');
                if (message && self.input && self.input.length) {
                    self.delegateMessageSending(message, self.input);
                    // Hide starter buttons
                    $('.conversation-starters').fadeOut(300);
                }
            });
        }

        /**
         * Bind basic events (fallback mode)
         */
        bindBasicEvents() {
            const self = this;

            // Only widget control events
            $(document).on('click.aiwidget', '.ai-chatbot-toggle', function(e) {
                e.preventDefault();
                self.toggle();
            });

            $(document).on('click.aiwidget', '.ai-chatbot-close', function(e) {
                e.preventDefault();
                self.close();
            });

            $(document).on('click.aiwidget', '.ai-chatbot-minimize', function(e) {
                e.preventDefault();
                self.minimize();
            });

            // Show error for message attempts
            $(document).on('click.aiwidget', '.ai-chatbot-send-button, .starter-btn', function(e) {
                e.preventDefault();
                self.addErrorMessage('Please refresh the page to enable chat functionality.');
            });
        }

        /**
         * Delegate message sending to main chatbot
         */
        delegateMessageSending(message, inputElement) {
            if (window.AIChatbot && typeof window.AIChatbot.handleSendMessage === 'function') {
                // Method 1: Use handleSendMessage
                window.AIChatbot.handleSendMessage(message);
                
            } else if (window.AIChatbot && typeof window.AIChatbot.sendMessageToServer === 'function') {
                // Method 2: Add user message and send to server
                if (window.AIChatbot.addUserMessage) {
                    window.AIChatbot.addUserMessage(message);
                }
                window.AIChatbot.sendMessageToServer(message);
                
            } else if (window.AIChatbot && typeof window.AIChatbot.sendMessage === 'function') {
                // Method 3: Use generic sendMessage
                window.AIChatbot.sendMessage(message);
                
            } else {
                // Fallback error
                console.error('No main chatbot method available');
                this.addErrorMessage('Chat functionality unavailable. Please refresh the page.');
                return;
            }
            
            // Clear input and update UI
            if (inputElement && inputElement.length) {
                inputElement.val('');
                this.handleInputChange(inputElement);
            }
        }

        /**
         * Widget toggle functionality
         */
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }

        /**
         * Open widget
         */
        open() {
            if (!this.widget || !this.widget.length) {
                this.initializeElements();
            }

            this.widget.addClass('ai-chatbot-open');
            
            if (this.container && this.container.length) {
                this.container.slideDown(300);
            }
            
            this.isOpen = true;
            this.isMinimized = false;

            // Focus input after animation
            setTimeout(() => {
                if (this.input && this.input.length) {
                    this.input.focus();
                }
            }, 350);

            // Scroll to bottom
            this.scrollToBottom();
            
            // Notify main chatbot of widget open
            if (window.AIChatbot && window.AIChatbot.onWidgetOpen) {
                window.AIChatbot.onWidgetOpen();
            }
        }

        /**
         * Close widget
         */
        close() {
            if (this.container && this.container.length) {
                this.container.slideUp(300, () => {
                    this.widget.removeClass('ai-chatbot-open ai-chatbot-minimized');
                });
            } else {
                this.widget.removeClass('ai-chatbot-open ai-chatbot-minimized');
            }
            
            this.isOpen = false;
            this.isMinimized = false;
            
            // Notify main chatbot of widget close
            if (window.AIChatbot && window.AIChatbot.onWidgetClose) {
                window.AIChatbot.onWidgetClose();
            }
        }

        /**
         * Minimize widget
         */
        minimize() {
            if (this.container && this.container.length) {
                this.widget.addClass('ai-chatbot-minimized');
                this.container.slideUp(300);
            }
            this.isMinimized = true;
        }

        /**
         * Handle input changes
         */
        handleInputChange(input) {
            if (!input || !input.length) return;
            
            const message = input.val().trim();
            const sendButton = input.closest('.ai-chatbot-input-container, .ai-chatbot-input-form').find('.ai-chatbot-send-button');
            
            if (message) {
                sendButton.prop('disabled', false);
                input.removeClass('ai-chatbot-input-empty');
            } else {
                sendButton.prop('disabled', true);
                input.addClass('ai-chatbot-input-empty');
            }
        }

        /**
         * Setup auto-resize for textarea
         */
        setupAutoResize() {
            $(document).on('input.aiwidget', '.ai-chatbot-input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
        }

        /**
         * Scroll to bottom - DELEGATE to main chatbot or use simple version
         */
        scrollToBottom() {
            if (window.AIChatbot && window.AIChatbot.scrollToBottom) {
                window.AIChatbot.scrollToBottom();
            } else {
                // Simple fallback
                if (this.messages && this.messages.length) {
                    setTimeout(() => {
                        this.messages[0].scrollTop = this.messages[0].scrollHeight;
                    }, 50);
                }
            }
        }

        /**
         * Add error message (fallback only)
         */
        addErrorMessage(text) {
            if (!this.messages || !this.messages.length) return;
            
            const timestamp = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const messageHtml = `
                <div class="ai-chatbot-message ai-chatbot-message-bot ai-chatbot-message-error">
                    <div class="ai-chatbot-message-content">
                        <div class="ai-chatbot-message-text">${this.escapeHtml(text)}</div>
                        <div class="ai-chatbot-message-time">${timestamp}</div>
                    </div>
                </div>
            `;
            
            this.messages.append(messageHtml);
            this.scrollToBottom();
        }

        /**
         * Generate conversation ID
         */
        generateConversationId() {
            return 'widget_conv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        /**
         * Escape HTML - MINIMAL version
         */
        escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        /**
         * Destroy widget and cleanup
         */
        destroy() {
            // Unbind all widget events
            $(document).off('.aiwidget');
            
            // Reset state
            this.isOpen = false;
            this.isMinimized = false;
            
            console.log('AIChatbotWidget: Destroyed');
        }
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        console.log('chatbot-widget.js: DOM ready');
        
        // Always initialize widget (it will wait for main chatbot)
        if (!window.aiChatbotWidget) {
            window.aiChatbotWidget = new AIChatbotWidget();
        }

        // Handle inline chatbots
        $('.ai-chatbot-inline').each(function() {
            // Basic inline initialization - delegate complex functionality to main chatbot
            console.log('chatbot-widget.js: Found inline chatbot, delegating to main chatbot');
        });
    });

    /**
     * Standalone scroll function for compatibility
     */
    function scrollToBottom() {
        // Delegate to main chatbot if available
        if (window.AIChatbot && window.AIChatbot.scrollToBottom) {
            window.AIChatbot.scrollToBottom();
            return;
        }
        
        // Fallback for multiple containers
        const containers = [
            '.ai-chatbot-messages',
            '.messages-container', 
            '.inline-messages-container',
            '.popup-messages-container'
        ];
        
        containers.forEach(selector => {
            const container = document.querySelector(selector);
            if (container) {
                setTimeout(() => {
                    container.scrollTop = container.scrollHeight;
                }, 50);
            }
        });
    }

    /**
     * Additional event listener for chat toggle - for backward compatibility
     */
    document.addEventListener('DOMContentLoaded', function() {
        const chatToggle = document.querySelector('.ai-chatbot-toggle');
        if (chatToggle) {
            chatToggle.addEventListener('click', function() {
                setTimeout(scrollToBottom, 300); // Delay to allow container to open
            });
        }
    });

    // Expose the widget class globally
    window.AIChatbotWidget = AIChatbotWidget;

})(jQuery);