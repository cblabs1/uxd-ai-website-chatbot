/**
 * AI Chatbot Audio Features - Main Controller
 * Handles voice input and audio mode activation
 */

(function($) {
    'use strict';

    window.AIChatbotAudio = {
        initialized: false,
        config: null,
        isAudioModeActive: false,
        audioModeModal: null,
        recognition: null,
        synthesis: null,

        init: function() {
            if (this.initialized) return;

            this.config = window.aiChatbotAudio || {};
            
            // Check if audio features are enabled
            if (!this.config.audio_enabled) {
                console.log('AI Chatbot Audio: Features disabled');
                return;
            }

            this.checkBrowserSupport();
            this.addVoiceButton();
            this.bindEvents();
            
            this.initialized = true;
            console.log('AI Chatbot Audio: Initialized successfully');
        },

        checkBrowserSupport: function() {
            // Check Web Speech API support
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                console.warn('Speech recognition not supported in this browser');
                $('.voice-btn').prop('disabled', true).attr('title', 'Voice not supported in your browser');
                return false;
            }

            // Check Speech Synthesis
            if (!('speechSynthesis' in window)) {
                console.warn('Speech synthesis not supported in this browser');
            }

            return true;
        },

        addVoiceButton: function() {
            if (!this.config.voice_input || !this.config.voice_input.enabled) {
                return;
            }

            const voiceButtonHtml = `
                <button type="button" class="voice-btn" 
                        title="Start Audio Conversation Mode" 
                        aria-label="Open audio conversation mode">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                        <line x1="12" y1="19" x2="12" y2="23"></line>
                        <line x1="8" y1="23" x2="16" y2="23"></line>
                    </svg>
                    <span class="voice-btn-label">Audio Mode</span>
                </button>
            `;

            // Add to chat input area
            const $inputContainer = $('.chatbot-input-wrapper, .chat-input-wrapper, .ai-chatbot-input-container');
            if ($inputContainer.length && !$('.voice-btn').length) {
                // Try to find button container, otherwise append to input container
                const $buttonContainer = $inputContainer.find('.input-actions, .chat-actions');
                if ($buttonContainer.length) {
                    $buttonContainer.append(voiceButtonHtml);
                } else {
                    $inputContainer.append(voiceButtonHtml);
                }
            }
        },

        bindEvents: function() {
            const self = this;

            // Voice button click - opens audio mode modal
            $(document).on('click', '.voice-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openAudioMode();
            });

            // Listen for audio mode close events
            $(document).on('audio_mode_closed', function() {
                self.isAudioModeActive = false;
            });

            // Intercept TTS - only play in audio mode
            $(document).on('ai_chatbot_before_tts', function(e, data) {
                if (!self.isAudioModeActive) {
                    // Cancel TTS if not in audio mode
                    e.preventDefault();
                    return false;
                }
            });
        },

        openAudioMode: function() {
            console.log('Opening Audio Conversation Mode...');
            
            // Check browser support before opening
            if (!this.checkBrowserSupport()) {
                this.showNotification('error', 'Your browser does not support voice features.');
                return;
            }

            this.isAudioModeActive = true;

            // Create modal if it doesn't exist
            if (!$('.ai-audio-mode-modal').length) {
                this.createAudioModeModal();
            }

            // Show modal with animation
            $('.ai-audio-mode-modal').fadeIn(300);
            $('body').addClass('audio-mode-active');

            // Initialize audio mode features
            if (window.AIChatbotAudioMode) {
                window.AIChatbotAudioMode.activate();
            }

            // Trigger event for other components
            $(document).trigger('audio_mode_opened');
        },

        createAudioModeModal: function() {
            const modalHtml = `
                <div class="ai-audio-mode-modal" style="display: none;">
                    <div class="audio-modal-overlay"></div>
                    <div class="audio-modal-container">
                        <!-- Header -->
                        <div class="audio-modal-header">
                            <div class="audio-header-left">
                                <div class="audio-status-icon">
                                    <div class="pulse-ring"></div>
                                    <div class="microphone-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                            <line x1="12" y1="19" x2="12" y2="23"></line>
                                            <line x1="8" y1="23" x2="16" y2="23"></line>
                                        </svg>
                                    </div>
                                </div>
                                <h2 class="audio-status-text">Initializing...</h2>
                                <!-- Waveform Visualization -->
                                <div class="audio-waveform">
                                    <div class="wave-bar"></div>
                                    <div class="wave-bar"></div>
                                    <div class="wave-bar"></div>
                                    <div class="wave-bar"></div>
                                    <div class="wave-bar"></div>
                                    <div class="wave-bar"></div>
                                    <div class="wave-bar"></div>
                                </div>
                            </div>
                            <div class="audio-header-right">
                                <button class="audio-modal-close" aria-label="Close audio mode">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="audio-modal-content">
                            

                            <!-- Transcript Display -->
                            <div class="audio-transcript-container">
                                <div class="transcript-label">Live Transcript:</div>
                                <div class="transcript-content">
                                    <!-- Items added here -->
                                </div>
                            </div>

                        </div>

                        <!-- Controls -->
                        <div class="audio-modal-controls">
                            <button class="audio-control-btn pause-btn" data-action="pause">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <rect x="6" y="4" width="4" height="16"></rect>
                                    <rect x="14" y="4" width="4" height="16"></rect>
                                </svg>
                                <span>Pause</span>
                            </button>
                            <button class="audio-control-btn stop-btn" data-action="stop">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <rect x="6" y="6" width="12" height="12"></rect>
                                </svg>
                                <span>Stop</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            this.bindModalEvents();
        },

        bindModalEvents: function() {
            const self = this;

            // Close modal
            $(document).on('click', '.audio-modal-close, .audio-modal-overlay', function() {
                self.closeAudioMode();
            });

            // Control buttons
            $(document).on('click', '.audio-control-btn', function() {
                const action = $(this).data('action');
                self.handleAudioControl(action);
            });

            // Escape key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.isAudioModeActive) {
                    self.closeAudioMode();
                }
            });
        },

        handleAudioControl: function(action) {
            console.log('Audio control action:', action);

            switch(action) {
                case 'pause':
                    if (window.AIChatbotAudioMode) {
                        window.AIChatbotAudioMode.togglePause();
                    }
                    break;
                case 'stop':
                    this.closeAudioMode();
                    break;
            }
        },

        closeAudioMode: function() {
            console.log('Closing Audio Conversation Mode...');

            // Stop audio mode
            if (window.AIChatbotAudioMode) {
                window.AIChatbotAudioMode.deactivate();
            }

            // Hide modal
            $('.ai-audio-mode-modal').fadeOut(300, function() {
                // Clean up
                $('.audio-transcript').empty();
                $('.conversation-messages').empty();
            });

            $('body').removeClass('audio-mode-active');
            this.isAudioModeActive = false;

            // Trigger event
            $(document).trigger('audio_mode_closed');
        },

        updateAudioStatus: function(status, text) {
            $('.audio-status-text').text(text || status);
            
            // Update icon state
            const $icon = $('.audio-status-icon');
            $icon.removeClass('listening speaking processing idle');
            $icon.addClass(status);

            // Update waveform animation
            if (status === 'listening' || status === 'speaking') {
                $('.audio-waveform').addClass('active');
            } else {
                $('.audio-waveform').removeClass('active');
            }
        },

        updateTranscript: function(text, isFinal) {
            const $transcript = $('.audio-transcript');
            
            if (isFinal) {
                $transcript.html(`<div class="transcript-final">${text}</div>`);
            } else {
                $transcript.html(`<div class="transcript-interim">${text}</div>`);
            }
        },

        addConversationMessage: function(type, text) {
            const $messages = $('.conversation-messages');
            const messageHtml = `
                <div class="audio-message ${type}">
                    <span class="message-icon">${type === 'user' ? '👤' : '🤖'}</span>
                    <span class="message-text">${text}</span>
                </div>
            `;
            
            $messages.append(messageHtml);
            
            // Auto-scroll to bottom
            $messages.scrollTop($messages[0].scrollHeight);
            
            // Limit to last 5 messages
            if ($messages.children().length > 5) {
                $messages.children().first().remove();
            }
        },

        showNotification: function(type, message) {
            const notificationHtml = `
                <div class="audio-notification ${type}">
                    <span class="notification-icon">${type === 'error' ? '⚠️' : 'ℹ️'}</span>
                    <span class="notification-message">${message}</span>
                </div>
            `;

            $('.audio-modal-content').prepend(notificationHtml);

            setTimeout(function() {
                $('.audio-notification').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AIChatbotAudio.init();
    });

})(jQuery);