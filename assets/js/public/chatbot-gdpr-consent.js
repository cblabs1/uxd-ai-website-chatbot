/**
 * AI Chatbot - GDPR Cookie Consent Banner
 * 
 * Handles cookie consent for GDPR compliance
 * 
 * @package AI_Website_Chatbot
 * @since 11.6.4
 */

(function($) {
    'use strict';

    const AIChatbotConsent = {
        // Configuration
        config: {
            cookieName: 'ai_chatbot_consent',
            cookieExpiry: 365, // days
            consentVersion: '1.0'
        },

        /**
         * Initialize consent system
         */
        init: function() {
            // Check if GDPR is enabled and consent is required
            if (!window.aiChatbotConfig || !window.aiChatbotConfig.gdpr_enabled) {
                return;
            }

            if (!window.aiChatbotConfig.cookie_consent_required) {
                return;
            }

            // Check if user has already consented
            if (this.hasConsent()) {
                // User has consented, enable chatbot
                this.enableChatbot();
            } else {
                // Show consent banner
                this.showConsentBanner();
                // Disable chatbot until consent
                this.disableChatbot();
            }

            // Bind events
            this.bindEvents();
        },

        /**
         * Check if user has given consent
         */
        hasConsent: function() {
            const consent = this.getCookie(this.config.cookieName);
            return consent === 'accepted';
        },

        /**
         * Show consent banner
         */
        showConsentBanner: function() {
            // Remove existing banner if any
            $('.ai-chatbot-consent-banner').remove();

            const privacyUrl = window.aiChatbotConfig.privacy_policy_url || '';
            
            const bannerHtml = `
                <div class="ai-chatbot-consent-banner">
                    <div class="ai-chatbot-consent-content">
                        <div class="ai-chatbot-consent-icon">
                            🍪
                        </div>
                        <div class="ai-chatbot-consent-text">
                            <h4>${window.aiChatbotConfig.consent_title || 'Cookie Consent'}</h4>
                            <p>
                                ${window.aiChatbotConfig.consent_message || 'We use cookies and collect conversation data to improve our AI chatbot service. Your conversations may be processed by third-party AI providers.'}
                                ${privacyUrl ? `<a href="${privacyUrl}" target="_blank" class="consent-privacy-link">Learn more</a>` : ''}
                            </p>
                        </div>
                        <div class="ai-chatbot-consent-actions">
                            <button class="ai-chatbot-consent-btn ai-chatbot-consent-accept">
                                ${window.aiChatbotConfig.consent_accept_text || 'Accept'}
                            </button>
                            <button class="ai-chatbot-consent-btn ai-chatbot-consent-decline">
                                ${window.aiChatbotConfig.consent_decline_text || 'Decline'}
                            </button>
                        </div>
                    </div>
                    <button class="ai-chatbot-consent-close" aria-label="Close">
                        ×
                    </button>
                </div>
            `;

            $('body').append(bannerHtml);

            // Animate in
            setTimeout(function() {
                $('.ai-chatbot-consent-banner').addClass('visible');
            }, 100);
        },

        /**
         * Hide consent banner
         */
        hideConsentBanner: function() {
            $('.ai-chatbot-consent-banner').removeClass('visible');
            setTimeout(function() {
                $('.ai-chatbot-consent-banner').remove();
            }, 300);
        },

        /**
         * Handle consent acceptance
         */
        acceptConsent: function() {
            // Set cookie
            this.setCookie(this.config.cookieName, 'accepted', this.config.cookieExpiry);
            
            // Hide banner
            this.hideConsentBanner();
            
            // Enable chatbot
            this.enableChatbot();
            
            // Show success message
            this.showConsentMessage('success', window.aiChatbotConfig.consent_accepted_message || 'Thank you! You can now use the chatbot.');
            
            // Trigger event
            $(document).trigger('aiChatbotConsentAccepted');
            
            // Log to analytics if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'consent_accepted', {
                    'event_category': 'GDPR',
                    'event_label': 'AI Chatbot'
                });
            }
        },

        /**
         * Handle consent decline
         */
        declineConsent: function() {
            // Set cookie
            this.setCookie(this.config.cookieName, 'declined', this.config.cookieExpiry);
            
            // Hide banner
            this.hideConsentBanner();
            
            // Keep chatbot disabled
            this.disableChatbot();
            
            // Show message
            this.showConsentMessage('info', window.aiChatbotConfig.consent_declined_message || 'You have declined. The chatbot will not be available.');
            
            // Trigger event
            $(document).trigger('aiChatbotConsentDeclined');
            
            // Log to analytics if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'consent_declined', {
                    'event_category': 'GDPR',
                    'event_label': 'AI Chatbot'
                });
            }
        },

        /**
         * Enable chatbot
         */
        enableChatbot: function() {
            $('.ai-chatbot-widget').removeClass('consent-required');
            $('.ai-chatbot-widget').addClass('consent-given');
            
            // If chatbot has a disable method, call it
            if (window.AIChatbot && typeof window.AIChatbot.enable === 'function') {
                window.AIChatbot.enable();
            }
        },

        /**
         * Disable chatbot
         */
        disableChatbot: function() {
            $('.ai-chatbot-widget').addClass('consent-required');
            $('.ai-chatbot-widget').removeClass('consent-given');
            
            // If chatbot has a disable method, call it
            if (window.AIChatbot && typeof window.AIChatbot.disable === 'function') {
                window.AIChatbot.disable();
            }
        },

        /**
         * Show consent message (toast notification)
         */
        showConsentMessage: function(type, message) {
            const toast = $(`
                <div class="ai-chatbot-consent-toast ${type}">
                    <div class="toast-content">
                        ${type === 'success' ? '✓' : 'ℹ'}
                        <span>${message}</span>
                    </div>
                </div>
            `);

            $('body').append(toast);

            setTimeout(function() {
                toast.addClass('visible');
            }, 100);

            setTimeout(function() {
                toast.removeClass('visible');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, 4000);
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Accept button
            $(document).on('click', '.ai-chatbot-consent-accept', function(e) {
                e.preventDefault();
                self.acceptConsent();
            });

            // Decline button
            $(document).on('click', '.ai-chatbot-consent-decline', function(e) {
                e.preventDefault();
                self.declineConsent();
            });

            // Close button (acts as decline)
            $(document).on('click', '.ai-chatbot-consent-close', function(e) {
                e.preventDefault();
                self.declineConsent();
            });

            // Revoke consent link (if user wants to change their mind)
            $(document).on('click', '.ai-chatbot-revoke-consent', function(e) {
                e.preventDefault();
                self.revokeConsent();
            });
        },

        /**
         * Revoke consent
         */
        revokeConsent: function() {
            // Delete cookie
            this.deleteCookie(this.config.cookieName);
            
            // Show banner again
            this.showConsentBanner();
            
            // Disable chatbot
            this.disableChatbot();
            
            // Show message
            this.showConsentMessage('info', 'Your consent has been revoked. Please choose again.');
            
            // Trigger event
            $(document).trigger('aiChatbotConsentRevoked');
        },

        /**
         * Get cookie value
         */
        getCookie: function(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }
            return null;
        },

        /**
         * Set cookie
         */
        setCookie: function(name, value, days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = `expires=${date.toUTCString()}`;
            const sameSite = 'SameSite=Lax';
            const secure = window.location.protocol === 'https:' ? 'Secure' : '';
            document.cookie = `${name}=${value};${expires};path=/;${sameSite};${secure}`;
        },

        /**
         * Delete cookie
         */
        deleteCookie: function(name) {
            document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;`;
        },

        /**
         * Check consent status (public method for external use)
         */
        getConsentStatus: function() {
            const consent = this.getCookie(this.config.cookieName);
            return {
                hasConsent: consent === 'accepted',
                consentValue: consent,
                timestamp: new Date().toISOString()
            };
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AIChatbotConsent.init();
    });

    // Expose to window for external access
    window.AIChatbotConsent = AIChatbotConsent;

})(jQuery);
