/**
 * AI Chatbot Widget JavaScript
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * AI Chatbot Widget Class
     */
    class AIChatbotWidget {
        constructor() {
            this.widget = null;
            this.container = null;
            this.messages = null;
            this.input = null;
            this.isOpen = false;
            this.isMinimized = false;
            this.inactivityTimer = null;
            this.messageCount = 0;
            this.feedbackMode = false;
            this.conversationId = this.generateConversationId();
            this.init();
        }

        /**
         * Initialize widget
         */
        init() {
            this.bindEvents();
            this.setupAutoResize();
            this.loadConversationHistory();
        }

        /**
         * Bind events
         */
        bindEvents() {
            const self = this;

            // Toggle chatbot
            $(document).on('click', '#ai-chatbot-toggle', function() {
                self.toggle();
            });

            // Close chatbot
            $(document).on('click', '#ai-chatbot-close', function() {
                self.close();
            });

            // Minimize chatbot
            $(document).on('click', '#ai-chatbot-minimize', function() {
                self.minimize();
            });

            // Handle form submission
            $(document).on('submit', '#ai-chatbot-input-form, #ai-chatbot-inline-input-form', function(e) {
                e.preventDefault();
                const form = $(this);
                const input = form.find('.ai-chatbot-input');
                const message = input.val().trim();
                
                if (message) {
                    self.sendMessage(message, input);
                }
            });

            // Handle input changes
            $(document).on('input', '.ai-chatbot-input', function() {
                self.handleInputChange($(this));
            });

            // Handle Enter key
            $(document).on('keydown', '.ai-chatbot-input', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $(this).closest('form').submit();
                }
            });

            // Handle click outside to close (optional)
            $(document).on('click', function(e) {
                if (self.isOpen && !$(e.target).closest('#ai-chatbot-widget').length) {
                    // Uncomment to close on outside click
                    // self.close();
                }
            });
        }

        /**
         * Toggle chatbot widget
         */
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }

        /**
         * Open chatbot widget
         */
        open() {
            this.widget = $('#ai-chatbot-widget');
            this.container = $('#ai-chatbot-container');
            this.messages = $('#ai-chatbot-messages');
            this.input = $('#ai-chatbot-input');

            this.widget.addClass('ai-chatbot-open');
            this.container.slideDown(300);
            this.isOpen = true;
            this.isMinimized = false;

            // Focus input after animation
            setTimeout(() => {
                this.input.focus();
            }, 350);

            // Scroll to bottom
            this.scrollToBottom();
        }

        /**
         * Close chatbot widget
         */
        close() {
            if (this.container) {
                this.container.slideUp(300, () => {
                    this.widget.removeClass('ai-chatbot-open ai-chatbot-minimized');
                });
            }
            this.isOpen = false;
            this.isMinimized = false;
        }

        /**
         * Minimize chatbot widget
         */
        minimize() {
            if (this.container) {
                this.widget.addClass('ai-chatbot-minimized');
                this.container.slideUp(300);
            }
            this.isMinimized = true;
        }

        /**
         * Send message to AI
         */
        sendMessage(message, inputElement) {
            // Add user message to chat
            if (window.AIChatbot && typeof window.AIChatbot.handleSendMessage === 'function') {
                // Use the main chatbot's method which has proper authentication
                window.AIChatbot.handleSendMessage(message);
                
                // Clear the input
                inputElement.val('');
                this.handleInputChange(inputElement);
            } else {
                console.error('Main AIChatbot not available. Please refresh the page.');
                this.addMessage('Please refresh the page and try again.', 'bot', true);
            }
        }

        /**
         * Add message to chat
         */
        addMessage(text, sender, type = 'normal') {
            // Ensure text is always a string
            if (text === null || text === undefined) {
                text = '';
            }
            
            if (typeof text !== 'string') {
                if (typeof text === 'object' && text.message) {
                    text = text.message;
                } else {
                    try {
                        text = String(text);
                    } catch (e) {
                        text = 'Invalid message content';
                    }
                }
            }
            
            const timestamp = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const messageClass = `ai-chatbot-message ai-chatbot-message-${sender}`;
            const errorClass = type === 'error' ? ' ai-chatbot-message-error' : '';
            
            const messageHtml = `
                <div class="${messageClass}${errorClass}">
                    <div class="ai-chatbot-message-content">
                        <div class="ai-chatbot-message-text">${this.escapeHtml(text)}</div>
                        <div class="ai-chatbot-message-time">${timestamp}</div>
                    </div>
                </div>
            `;
            
            if (this.messages) {
                this.messages.append(messageHtml);
                this.scrollToBottom();
            } else {
                // Fallback to standalone function
                setTimeout(scrollToBottom, 100);
            }
            setTimeout(scrollToBottom, 100);
        }

        /**
         * Show typing indicator
         */
        showTyping() {
            $('#ai-chatbot-typing').show();
            this.scrollToBottom();
        }

        /**
         * Hide typing indicator
         */
        hideTyping() {
            $('#ai-chatbot-typing').hide();
        }

        /**
         * Handle input change
         */
        handleInputChange(input) {
            const message = input.val().trim();
            const sendButton = input.closest('.ai-chatbot-input-container').find('.ai-chatbot-send-button');
            
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
            $(document).on('input', '.ai-chatbot-input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
        }

        /**
         * Scroll messages to bottom
         */
        scrollToBottom() {
            // Handle both class property and jQuery selector
            var messagesContainer = this.messages || $('.ai-chatbot-messages');
            
            if (messagesContainer && messagesContainer.length) {
                setTimeout(() => {
                    if (messagesContainer.jquery) {
                        // jQuery object
                        messagesContainer[0].scrollTop = messagesContainer[0].scrollHeight;
                    } else {
                        // DOM element
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }, 50);
            }
        }
        


        /**
         * Generate unique conversation ID
         */
        generateConversationId() {
            return 'conv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        /**
         * Load conversation history
         */
        loadConversationHistory() {
            // Implementation for loading previous conversations
        }

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(unsafe) {
            // Handle null, undefined, or non-string values
            if (unsafe === null || unsafe === undefined) {
                return '';
            }
            
            // Convert to string if it's not already a string
            if (typeof unsafe !== 'string') {
                // Handle objects (like error responses from API)
                if (typeof unsafe === 'object') {
                    // If it has a message property (common for error objects)
                    if (unsafe.message && typeof unsafe.message === 'string') {
                        unsafe = unsafe.message;
                    } else {
                        // Convert object to JSON string or fallback
                        try {
                            unsafe = JSON.stringify(unsafe);
                        } catch (e) {
                            unsafe = '[Object object]';
                        }
                    }
                } else {
                    // Convert other types to string
                    unsafe = String(unsafe);
                }
            }
            
            // Now safely escape the string
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    }

    /**
     * AI Chatbot Utils
     */
    window.AIChatbotUtils = {
        /**
         * Format message text (handle line breaks, links, etc.)
         */
        formatMessage: function(text) {
            // Convert line breaks
            text = text.replace(/\n/g, '<br>');
            
            // Convert URLs to links (basic implementation)
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            text = text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener">$1</a>');
            
            return text;
        },

        /**
         * Get user timezone
         */
        getUserTimezone: function() {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        },

        /**
         * Check if user is on mobile
         */
        isMobile: function() {
            return window.innerWidth <= 768;
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize if chatbot widget exists
        if ($('#ai-chatbot-widget').length) {
            new AIChatbotWidget();
        }

        // Handle inline chatbots
        $('.ai-chatbot-inline').each(function() {
            // Initialize inline chatbot functionality
            // Similar to widget but without toggle functionality
        });
    });

    function scrollToBottom() {
        // Handle multiple possible containers
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


    document.addEventListener('DOMContentLoaded', function() {
        const chatToggle = document.querySelector('.ai-chatbot-toggle');
        if (chatToggle) {
            chatToggle.addEventListener('click', function() {
                setTimeout(scrollToBottom, 300); // Delay to allow container to open
            });
        }
    });

})(jQuery);