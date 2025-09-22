/**
 * AI Chatbot Pro Audio Core
 * Core audio functionality framework for frontend
 * 
 * @package AI_Website_Chatbot
 * @subpackage Pro\Audio\Frontend
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main Audio Core Object
    window.AIChatbotAudioCore = {
        // Configuration
        config: {},
        strings: {},
        
        // Core states
        initialized: false,
        audioSupported: false,
        
        // Audio modules
        modules: {},
        
        // Event handlers
        eventHandlers: {},
        
        /**
         * Initialize the audio core system
         */
        init: function() {
            if (this.initialized) {
                return;
            }

            console.log('Initializing AI Chatbot Audio Core...');

            // Load configuration
            this.loadConfiguration();
            
            // Check browser support
            this.checkBrowserSupport();
            
            // Initialize modules
            this.initializeModules();
            
            // Bind global events
            this.bindGlobalEvents();
            
            // Setup error handling
            this.setupErrorHandling();
            
            this.initialized = true;
            
            // Trigger initialization complete event
            this.trigger('audioCore:initialized');
            
            console.log('AI Chatbot Audio Core initialized successfully');
        },

        /**
         * Load configuration from localized data
         */
        loadConfiguration: function() {
            // Load from localized script data
            if (window.aiChatbotProAudio) {
                this.config = window.aiChatbotProAudio.config || {};
                this.strings = window.aiChatbotProAudio.strings || {};
            }
            
            // Set defaults if not provided
            this.config = $.extend(true, {
                voice_input: { enabled: false },
                text_to_speech: { enabled: false },
                audio_mode: { enabled: false },
                voice_commands: { enabled: false },
                debug_mode: false
            }, this.config);

            this.debug('Configuration loaded:', this.config);
        },

        /**
         * Check browser support for audio features
         */
        checkBrowserSupport: function() {
            const support = {
                speechRecognition: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
                speechSynthesis: !!(window.speechSynthesis),
                mediaDevices: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
                audioContext: !!(window.AudioContext || window.webkitAudioContext),
                webAudio: !!(window.AudioContext || window.webkitAudioContext)
            };

            this.audioSupported = support.speechRecognition && support.speechSynthesis;
            
            // Store support info
            this.browserSupport = support;
            
            this.debug('Browser support check:', support);
            
            if (!this.audioSupported) {
                console.warn('Limited audio support detected. Some features may not work.');
                this.showBrowserSupportWarning();
            }

            return support;
        },

        /**
         * Initialize audio modules
         */
        initializeModules: function() {
            const moduleLoadOrder = [
                'voiceInput',
                'textToSpeech', 
                'audioMode',
                'voiceCommands'
            ];

            moduleLoadOrder.forEach(moduleName => {
                if (this.shouldLoadModule(moduleName)) {
                    this.loadModule(moduleName);
                }
            });
        },

        /**
         * Check if module should be loaded
         */
        shouldLoadModule: function(moduleName) {
            const moduleMap = {
                voiceInput: 'voice_input',
                textToSpeech: 'text_to_speech',
                audioMode: 'audio_mode',
                voiceCommands: 'voice_commands'
            };

            const configKey = moduleMap[moduleName];
            return this.config[configKey] && this.config[configKey].enabled;
        },

        /**
         * Load individual module
         */
        loadModule: function(moduleName) {
            try {
                const moduleClass = window['AIChatbotAudio' + moduleName.charAt(0).toUpperCase() + moduleName.slice(1)];
                
                if (moduleClass && typeof moduleClass.init === 'function') {
                    this.modules[moduleName] = moduleClass;
                    moduleClass.init(this);
                    this.debug(`Module ${moduleName} loaded successfully`);
                    
                    this.trigger('audioCore:moduleLoaded', { module: moduleName });
                } else {
                    console.warn(`Audio module ${moduleName} not found or invalid`);
                }
            } catch (error) {
                console.error(`Failed to load audio module ${moduleName}:`, error);
            }
        },

        /**
         * Bind global events
         */
        bindGlobalEvents: function() {
            const self = this;

            // Page visibility changes
            $(document).on('visibilitychange', function() {
                if (document.hidden) {
                    self.handlePageHidden();
                } else {
                    self.handlePageVisible();
                }
            });

            // Window focus/blur
            $(window).on('focus', function() {
                self.handleWindowFocus();
            }).on('blur', function() {
                self.handleWindowBlur();
            });

            // Before page unload
            $(window).on('beforeunload', function() {
                self.cleanup();
            });

            // Audio permission changes (if supported)
            if (navigator.permissions) {
                navigator.permissions.query({name: 'microphone'}).then(function(result) {
                    result.onchange = function() {
                        self.handlePermissionChange('microphone', result.state);
                    };
                });
            }

            // Integration with main chatbot events
            $(document).on('ai_chatbot_message_sent', function(e, data) {
                self.handleChatbotMessageSent(data);
            });

            $(document).on('ai_chatbot_response_received', function(e, data) {
                self.handleChatbotResponseReceived(data);
            });
        },

        /**
         * Setup error handling
         */
        setupErrorHandling: function() {
            const self = this;

            // Global audio error handler
            window.addEventListener('error', function(event) {
                if (event.error && event.error.message && 
                    (event.error.message.includes('speech') || 
                     event.error.message.includes('audio') ||
                     event.error.message.includes('microphone'))) {
                    self.handleAudioError(event.error);
                }
            });

            // Unhandled promise rejections for audio operations
            window.addEventListener('unhandledrejection', function(event) {
                if (event.reason && event.reason.message && 
                    event.reason.message.includes('audio')) {
                    self.handleAudioError(event.reason);
                    event.preventDefault();
                }
            });
        },

        /**
         * Handle page visibility changes
         */
        handlePageHidden: function() {
            this.debug('Page hidden - pausing audio operations');
            
            // Pause active audio operations
            Object.keys(this.modules).forEach(moduleName => {
                const module = this.modules[moduleName];
                if (module && typeof module.pause === 'function') {
                    module.pause();
                }
            });

            this.trigger('audioCore:pageHidden');
        },

        handlePageVisible: function() {
            this.debug('Page visible - resuming audio operations');
            
            // Resume audio operations if appropriate
            Object.keys(this.modules).forEach(moduleName => {
                const module = this.modules[moduleName];
                if (module && typeof module.resume === 'function') {
                    module.resume();
                }
            });

            this.trigger('audioCore:pageVisible');
        },

        /**
         * Handle window focus changes
         */
        handleWindowFocus: function() {
            this.trigger('audioCore:windowFocus');
        },

        handleWindowBlur: function() {
            // Optionally pause audio operations when window loses focus
            if (this.config.audio_mode && this.config.audio_mode.auto_pause_on_page_blur) {
                this.handlePageHidden();
            }
            
            this.trigger('audioCore:windowBlur');
        },

        /**
         * Handle permission changes
         */
        handlePermissionChange: function(permission, state) {
            this.debug(`Permission ${permission} changed to:`, state);
            
            if (permission === 'microphone') {
                if (state === 'denied') {
                    this.showPermissionDeniedError();
                    this.disableVoiceFeatures();
                } else if (state === 'granted') {
                    this.enableVoiceFeatures();
                }
            }

            this.trigger('audioCore:permissionChange', { permission, state });
        },

        /**
         * Handle chatbot integration events
         */
        handleChatbotMessageSent: function(data) {
            this.debug('Chatbot message sent:', data);
            this.trigger('audioCore:chatbotMessageSent', data);
        },

        handleChatbotResponseReceived: function(data) {
            this.debug('Chatbot response received:', data);
            
            // Handle TTS if enabled and response has audio data
            if (data.audio && this.modules.textToSpeech) {
                this.modules.textToSpeech.handleResponse(data);
            }

            this.trigger('audioCore:chatbotResponseReceived', data);
        },

        /**
         * Handle audio errors
         */
        handleAudioError: function(error) {
            console.error('Audio error occurred:', error);

            const errorInfo = {
                message: error.message || 'Unknown audio error',
                type: this.classifyAudioError(error),
                timestamp: Date.now(),
                userAgent: navigator.userAgent
            };

            // Show user-friendly error message
            this.showAudioError(errorInfo);

            // Log error for debugging
            this.logAudioError(errorInfo);

            // Trigger error event
            this.trigger('audioCore:error', errorInfo);
        },

        /**
         * Classify audio error type
         */
        classifyAudioError: function(error) {
            const message = error.message ? error.message.toLowerCase() : '';
            
            if (message.includes('permission') || message.includes('denied')) {
                return 'permission_denied';
            } else if (message.includes('not supported') || message.includes('unavailable')) {
                return 'not_supported';
            } else if (message.includes('network') || message.includes('connection')) {
                return 'network_error';
            } else if (message.includes('timeout')) {
                return 'timeout';
            } else {
                return 'unknown';
            }
        },

        /**
         * Show user-friendly error messages
         */
        showAudioError: function(errorInfo) {
            let message = '';
            
            switch (errorInfo.type) {
                case 'permission_denied':
                    message = this.strings.micPermissionDenied || 'Microphone access denied. Please grant permission to use voice features.';
                    break;
                case 'not_supported':
                    message = this.strings.voiceNotSupported || 'Voice features are not supported in your browser.';
                    break;
                case 'network_error':
                    message = 'Network error occurred. Please check your connection.';
                    break;
                case 'timeout':
                    message = 'Voice operation timed out. Please try again.';
                    break;
                default:
                    message = 'An audio error occurred. Please try again.';
            }

            this.showUserNotification(message, 'error');
        },

        /**
         * Show browser support warning
         */
        showBrowserSupportWarning: function() {
            const message = 'Your browser has limited support for voice features. Some functionality may not be available.';
            this.showUserNotification(message, 'warning');
        },

        /**
         * Show permission denied error
         */
        showPermissionDeniedError: function() {
            const message = this.strings.micPermissionDenied || 'Microphone permission denied. Voice features are disabled.';
            this.showUserNotification(message, 'error');
        },

        /**
         * Show user notification
         */
        showUserNotification: function(message, type = 'info') {
            // Create notification element
            const notification = $('<div class="ai-chatbot-audio-notification">')
                .addClass(`notification-${type}`)
                .html(`
                    <div class="notification-content">
                        <span class="notification-icon"></span>
                        <span class="notification-message">${message}</span>
                        <button class="notification-close" aria-label="Close">&times;</button>
                    </div>
                `)
                .hide();

            // Add to page
            $('body').append(notification);
            
            // Show with animation
            notification.fadeIn(300);

            // Auto-hide after delay
            setTimeout(() => {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, type === 'error' ? 8000 : 5000);

            // Close button handler
            notification.find('.notification-close').on('click', function() {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Disable voice features
         */
        disableVoiceFeatures: function() {
            $('.voice-input-btn, .audio-mode-btn').prop('disabled', true).addClass('disabled');
            
            if (this.modules.voiceInput) {
                this.modules.voiceInput.disable();
            }
            
            if (this.modules.audioMode) {
                this.modules.audioMode.disable();
            }
        },

        /**
         * Enable voice features
         */
        enableVoiceFeatures: function() {
            $('.voice-input-btn, .audio-mode-btn').prop('disabled', false).removeClass('disabled');
            
            if (this.modules.voiceInput) {
                this.modules.voiceInput.enable();
            }
            
            if (this.modules.audioMode) {
                this.modules.audioMode.enable();
            }
        },

        /**
         * Log audio error
         */
        logAudioError: function(errorInfo) {
            if (this.config.debug_mode) {
                console.group('Audio Error Details');
                console.error('Message:', errorInfo.message);
                console.error('Type:', errorInfo.type);
                console.error('Timestamp:', new Date(errorInfo.timestamp));
                console.error('User Agent:', errorInfo.userAgent);
                console.error('Browser Support:', this.browserSupport);
                console.groupEnd();
            }

            // Send error to server if error reporting is enabled
            if (this.config.audio_error_reporting) {
                this.sendErrorReport(errorInfo);
            }
        },

        /**
         * Send error report to server
         */
        sendErrorReport: function(errorInfo) {
            $.ajax({
                url: window.aiChatbotProAudio?.ajaxUrl || '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ai_chatbot_log_audio_error',
                    nonce: window.aiChatbotProAudio?.nonce,
                    error_info: errorInfo,
                    browser_support: this.browserSupport
                },
                success: function(response) {
                    console.log('Error report sent successfully');
                },
                error: function(xhr, status, error) {
                    console.warn('Failed to send error report:', error);
                }
            });
        },

        /**
         * Event system
         */
        on: function(event, handler) {
            if (!this.eventHandlers[event]) {
                this.eventHandlers[event] = [];
            }
            this.eventHandlers[event].push(handler);
        },

        off: function(event, handler) {
            if (this.eventHandlers[event]) {
                const index = this.eventHandlers[event].indexOf(handler);
                if (index > -1) {
                    this.eventHandlers[event].splice(index, 1);
                }
            }
        },

        trigger: function(event, data) {
            if (this.eventHandlers[event]) {
                this.eventHandlers[event].forEach(handler => {
                    try {
                        handler(data);
                    } catch (error) {
                        console.error(`Error in event handler for ${event}:`, error);
                    }
                });
            }
        },

        /**
         * Utility methods
         */
        debug: function(...args) {
            if (this.config.debug_mode || (window.aiChatbotProAudio && window.aiChatbotProAudio.debug)) {
                console.log('[AudioCore]', ...args);
            }
        },

        /**
         * Get module instance
         */
        getModule: function(moduleName) {
            return this.modules[moduleName] || null;
        },

        /**
         * Check if feature is available
         */
        isFeatureAvailable: function(feature) {
            const featureMap = {
                voiceInput: () => this.browserSupport.speechRecognition && this.config.voice_input?.enabled,
                textToSpeech: () => this.browserSupport.speechSynthesis && this.config.text_to_speech?.enabled,
                audioMode: () => this.isFeatureAvailable('voiceInput') && this.isFeatureAvailable('textToSpeech') && this.config.audio_mode?.enabled,
                voiceCommands: () => this.isFeatureAvailable('voiceInput') && this.config.voice_commands?.enabled
            };

            return featureMap[feature] ? featureMap[feature]() : false;
        },

        /**
         * Cleanup resources
         */
        cleanup: function() {
            this.debug('Cleaning up audio core resources...');

            // Cleanup modules
            Object.keys(this.modules).forEach(moduleName => {
                const module = this.modules[moduleName];
                if (module && typeof module.cleanup === 'function') {
                    module.cleanup();
                }
            });

            // Clear event handlers
            this.eventHandlers = {};

            // Remove notifications
            $('.ai-chatbot-audio-notification').remove();

            this.trigger('audioCore:cleanup');
        }
    };

    // Auto-initialize when document is ready
    $(document).ready(function() {
        // Wait a bit for other scripts to load
        setTimeout(function() {
            if (window.aiChatbotProAudio) {
                window.AIChatbotAudioCore.init();
            }
        }, 100);
    });

    // Expose global reference
    window.AudioCore = window.AIChatbotAudioCore;

})(jQuery);