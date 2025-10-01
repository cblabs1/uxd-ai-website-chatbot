/**
 * AI Chatbot Audio Features
 * Handles voice input and text-to-speech
 */
(function($) {
    'use strict';

    window.AIChatbotAudio = {
        recognition: null,
        synthesis: null,
        isListening: false,
        isSpeaking: false,
        config: {},

        init: function() {
            if (typeof ai_chatbot_audio === 'undefined') {
                return;
            }

            this.config = ai_chatbot_audio;
            
            // Initialize features
            if (this.config.voice_input && this.config.voice_input.enabled) {
                this.initVoiceInput();
            }

            if (this.config.text_to_speech && this.config.text_to_speech.enabled) {
                this.initTextToSpeech();
            }

            // Integrate with main chatbot
            this.integrateWithChatbot();
        },

        initVoiceInput: function() {
            // Check browser support
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                console.warn('Speech recognition not supported');
                return;
            }

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            
            this.recognition.continuous = this.config.voice_input.continuous;
            this.recognition.interimResults = this.config.voice_input.interim_results;
            this.recognition.lang = this.config.voice_input.language;

            this.bindRecognitionEvents();
        },

        bindRecognitionEvents: function() {
            const self = this;

            this.recognition.onstart = function() {
                self.isListening = true;
                self.onVoiceStart();
            };

            this.recognition.onresult = function(event) {
                let transcript = '';
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        transcript += event.results[i][0].transcript;
                    }
                }
                if (transcript) {
                    self.onVoiceResult(transcript);
                }
            };

            this.recognition.onerror = function(event) {
                self.onVoiceError(event.error);
            };

            this.recognition.onend = function() {
                self.isListening = false;
                self.onVoiceEnd();
            };
        },

        initTextToSpeech: function() {
            if ('speechSynthesis' in window) {
                this.synthesis = window.speechSynthesis;
            } else {
                console.warn('Text-to-speech not supported');
            }
        },

        startListening: function() {
            if (!this.recognition) {
                alert(this.config.strings.not_supported);
                return;
            }

            try {
                this.recognition.start();
            } catch (e) {
                console.error('Failed to start recognition:', e);
            }
        },

        stopListening: function() {
            if (this.recognition && this.isListening) {
                this.recognition.stop();
            }
        },

        speak: function(text) {
            if (!this.synthesis) return;

            // Stop any ongoing speech
            this.synthesis.cancel();

            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = this.config.text_to_speech.rate;
            utterance.pitch = this.config.text_to_speech.pitch;
            utterance.volume = this.config.text_to_speech.volume;
            utterance.lang = this.config.voice_input.language;

            const self = this;
            utterance.onstart = function() {
                self.isSpeaking = true;
            };
            utterance.onend = function() {
                self.isSpeaking = false;
            };

            this.synthesis.speak(utterance);
        },

        stopSpeaking: function() {
            if (this.synthesis) {
                this.synthesis.cancel();
                this.isSpeaking = false;
            }
        },

        onVoiceStart: function() {
            $('.voice-btn').addClass('listening');
            $('.voice-status').text(this.config.strings.listening).show();
        },

        onVoiceResult: function(transcript) {
            // Send transcript to chat input
            const $input = $('#chatbot-message-input, .chatbot-input');
            if ($input.length) {
                $input.val(transcript).trigger('input');
                
                // Auto-send if configured
                if (this.config.voice_input.auto_send) {
                    $('.chatbot-send-btn, .send-btn').trigger('click');
                }
            }
        },

        onVoiceError: function(error) {
            console.error('Voice recognition error:', error);
            let message = this.config.strings.error;
            
            if (error === 'not-allowed') {
                message = this.config.strings.permission_denied;
            }
            
            alert(message);
            this.stopListening();
        },

        onVoiceEnd: function() {
            $('.voice-btn').removeClass('listening');
            $('.voice-status').hide();
        },

        integrateWithChatbot: function() {
            const self = this;

            // Add voice button to chatbot
            $(document).on('aichatbot:ready', function() {
                self.addVoiceButton();
            });

            // Auto-speak bot responses if enabled
            if (this.config.text_to_speech && this.config.text_to_speech.auto_play) {
                $(document).on('aichatbot:message:received', function(e, data) {
                    if (data && data.response) {
                        self.speak(data.response);
                    }
                });
            }
        },

        addVoiceButton: function() {
            if (!this.config.voice_input || !this.config.voice_input.enabled) {
                return;
            }

            const voiceButtonHtml = `
                <button type="button" class="voice-btn" title="Voice input">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                        <line x1="12" y1="19" x2="12" y2="23"></line>
                        <line x1="8" y1="23" x2="16" y2="23"></line>
                    </svg>
                </button>
                <span class="voice-status" style="display:none;"></span>
            `;

            // Add to chat input area
            const $inputContainer = $('.chatbot-input-wrapper, .chat-input-wrapper');
            if ($inputContainer.length && !$('.voice-btn').length) {
                $inputContainer.append(voiceButtonHtml);
                this.bindVoiceButtonEvents();
            }
        },

        bindVoiceButtonEvents: function() {
            const self = this;

            $(document).on('click', '.voice-btn', function(e) {
                e.preventDefault();
                if (self.isListening) {
                    self.stopListening();
                } else {
                    self.startListening();
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AIChatbotAudio.init();
    });

})(jQuery);