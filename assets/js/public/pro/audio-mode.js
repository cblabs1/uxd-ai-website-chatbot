/**
 * AI Chatbot Audio Mode JavaScript
 * Handles hands-free audio conversation mode
 * 
 * File: assets/js/public/pro/audio/audio-mode.js
 */

(function($) {
    'use strict';

    /**
     * Audio Mode Class
     */
    window.AIChatbotAudioMode = {
        
        // Configuration
        config: {
            autoListen: true,
            silenceTimeout: 3000,
            listeningTimeout: 30000,
            autoPlay: false,
            confirmationRequired: false
        },
        
        // State
        isActive: false,
        isListening: false,
        isSpeaking: false,
        silenceTimer: null,
        listeningTimer: null,
        recognition: null,
        synthesis: null,
        currentUtterance: null,
        
        // UI Elements
        $toggleBtn: null,
        $indicator: null,
        $statusText: null,
        
        /**
         * Initialize audio mode
         */
        init: function(config) {
            this.config = $.extend(this.config, config || {});
            this.setupUI();
            this.setupSpeechRecognition();
            this.setupSpeechSynthesis();
            this.bindEvents();
        },
        
        /**
         * Setup UI elements
         */
        setupUI: function() {
            this.$toggleBtn = $('.ai-chatbot-audio-mode-toggle');
            this.$indicator = $('.ai-chatbot-audio-indicator');
            this.$statusText = $('.ai-chatbot-audio-status-text');
            
            // Create indicator if it doesn't exist
            if (this.$indicator.length === 0) {
                this.$indicator = $('<div class="ai-chatbot-audio-indicator"></div>');
                $('.ai-chatbot-widget').prepend(this.$indicator);
            }
            
            // Create status text if it doesn't exist
            if (this.$statusText.length === 0) {
                this.$statusText = $('<div class="ai-chatbot-audio-status-text"></div>');
                this.$indicator.append(this.$statusText);
            }
        },
        
        /**
         * Setup speech recognition
         */
        setupSpeechRecognition: function() {
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                console.warn('Speech recognition not supported');
                return;
            }
            
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            
            this.recognition.continuous = true;
            this.recognition.interimResults = true;
            this.recognition.lang = this.config.language || 'en-US';
            
            const self = this;
            
            this.recognition.onstart = function() {
                self.isListening = true;
                self.updateStatus('listening');
                self.startListeningTimer();
            };
            
            this.recognition.onresult = function(event) {
                let finalTranscript = '';
                let interimTranscript = '';
                
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i].transcript;
                    if (event.results[i].isFinal) {
                        finalTranscript += transcript;
                    } else {
                        interimTranscript += transcript;
                    }
                }
                
                if (finalTranscript) {
                    self.processFinalTranscript(finalTranscript);
                } else if (interimTranscript) {
                    self.updateStatus('processing', interimTranscript);
                    self.resetSilenceTimer();
                }
            };
            
            this.recognition.onend = function() {
                self.isListening = false;
                self.clearTimers();
                
                if (self.isActive && self.config.autoListen && !self.isSpeaking) {
                    // Restart listening if audio mode is still active
                    setTimeout(() => {
                        if (self.isActive && !self.isSpeaking) {
                            self.startListening();
                        }
                    }, 1000);
                }
            };
            
            this.recognition.onerror = function(event) {
                console.error('Speech recognition error:', event.error);
                self.handleRecognitionError(event.error);
            };
        },
        
        /**
         * Setup speech synthesis
         */
        setupSpeechSynthesis: function() {
            if (!('speechSynthesis' in window)) {
                console.warn('Speech synthesis not supported');
                return;
            }
            
            this.synthesis = window.speechSynthesis;
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;
            
            // Toggle button click
            this.$toggleBtn.on('click', function(e) {
                e.preventDefault();
                self.toggle();
            });
            
            // Listen for chat responses
            $(document).on('ai_chatbot_message_received', function(e, data) {
                if (self.isActive && data.response && self.config.autoPlay) {
                    self.speakResponse(data.response);
                }
            });
            
            // Listen for speech synthesis events
            if (this.synthesis) {
                $(document).on('ai_chatbot_speech_end', function() {
                    if (self.isActive && self.config.autoListen) {
                        setTimeout(() => {
                            self.startListening();
                        }, 500);
                    }
                });
            }
            
            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.key === 'A') {
                    e.preventDefault();
                    self.toggle();
                }
            });
        },
        
        /**
         * Toggle audio mode
         */
        toggle: function() {
            if (this.isActive) {
                this.stop();
            } else {
                this.start();
            }
        },
        
        /**
         * Start audio mode
         */
        start: function() {
            if (!this.recognition || !this.synthesis) {
                this.showError('Audio features not supported in this browser');
                return;
            }
            
            this.isActive = true;
            this.updateUI();
            this.updateStatus('starting');
            
            // Show confirmation if required
            if (this.config.confirmationRequired) {
                this.speakText('Audio mode activated. Say something to start chatting.', () => {
                    this.startListening();
                });
            } else {
                this.startListening();
            }
            
            // Trigger event
            $(document).trigger('ai_chatbot_audio_mode_started');
        },
        
        /**
         * Stop audio mode
         */
        stop: function() {
            this.isActive = false;
            this.stopListening();
            this.stopSpeaking();
            this.clearTimers();
            this.updateUI();
            this.updateStatus('stopped');
            
            // Trigger event
            $(document).trigger('ai_chatbot_audio_mode_stopped');
        },
        
        /**
         * Start listening
         */
        startListening: function() {
            if (!this.recognition || this.isListening || this.isSpeaking) {
                return;
            }
            
            try {
                this.recognition.start();
                this.startSilenceTimer();
            } catch (error) {
                console.error('Error starting speech recognition:', error);
                this.handleRecognitionError(error);
            }
        },
        
        /**
         * Stop listening
         */
        stopListening: function() {
            if (this.recognition && this.isListening) {
                this.recognition.stop();
            }
            this.clearTimers();
        },
        
        /**
         * Speak response
         */
        speakResponse: function(text) {
            if (!text || !this.synthesis) return;
            
            this.speakText(text, () => {
                $(document).trigger('ai_chatbot_speech_end');
            });
        },
        
        /**
         * Speak text
         */
        speakText: function(text, callback) {
            if (!this.synthesis) return;
            
            // Stop any current speech
            this.stopSpeaking();
            
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = this.config.speechRate || 1.0;
            utterance.pitch = this.config.speechPitch || 1.0;
            utterance.volume = this.config.speechVolume || 0.8;
            utterance.lang = this.config.language || 'en-US';
            
            const self = this;
            
            utterance.onstart = function() {
                self.isSpeaking = true;
                self.updateStatus('speaking');
            };
            
            utterance.onend = function() {
                self.isSpeaking = false;
                self.currentUtterance = null;
                if (callback) callback();
            };
            
            utterance.onerror = function(event) {
                console.error('Speech synthesis error:', event);
                self.isSpeaking = false;
                self.currentUtterance = null;
            };
            
            this.currentUtterance = utterance;
            this.synthesis.speak(utterance);
        },
        
        /**
         * Stop speaking
         */
        stopSpeaking: function() {
            if (this.synthesis) {
                this.synthesis.cancel();
            }
            this.isSpeaking = false;
            this.currentUtterance = null;
        },
        
        /**
         * Process final transcript
         */
        processFinalTranscript: function(transcript) {
            this.clearTimers();
            this.updateStatus('processing');
            
            // Check for voice commands first
            const command = this.detectVoiceCommand(transcript);
            if (command) {
                this.executeVoiceCommand(command);
                return;
            }
            
            // Send message to chatbot
            this.sendMessage(transcript);
        },
        
        /**
         * Detect voice commands
         */
        detectVoiceCommand: function(text) {
            const lowerText = text.toLowerCase().trim();
            const commands = {
                stop: ['stop', 'exit', 'quit', 'end audio mode'],
                pause: ['pause', 'wait', 'hold on'],
                repeat: ['repeat', 'say that again'],
                clear: ['clear', 'reset', 'start over'],
                help: ['help', 'what can you do', 'commands']
            };
            
            for (const [command, phrases] of Object.entries(commands)) {
                for (const phrase of phrases) {
                    if (lowerText.includes(phrase)) {
                        return command;
                    }
                }
            }
            
            return null;
        },
        
        /**
         * Execute voice command
         */
        executeVoiceCommand: function(command) {
            switch (command) {
                case 'stop':
                    this.speakText('Audio mode stopped.', () => {
                        this.stop();
                    });
                    break;
                    
                case 'pause':
                    this.speakText('Audio mode paused. Say something to continue.');
                    break;
                    
                case 'repeat':
                    const lastResponse = this.getLastResponse();
                    if (lastResponse) {
                        this.speakText(lastResponse);
                    } else {
                        this.speakText('No previous response to repeat.');
                    }
                    break;
                    
                case 'clear':
                    this.speakText('Clearing conversation.', () => {
                        $(document).trigger('ai_chatbot_clear_chat');
                    });
                    break;
                    
                case 'help':
                    const helpText = 'Available commands: stop audio mode, pause, repeat last message, clear conversation, or just speak naturally to chat.';
                    this.speakText(helpText);
                    break;
            }
        },
        
        /**
         * Send message to chatbot
         */
        sendMessage: function(message) {
            // Use existing chatbot message sending functionality
            if (typeof window.AIChatbotFrontend !== 'undefined') {
                window.AIChatbotFrontend.sendMessage(message);
            } else {
                // Fallback: trigger message event
                $(document).trigger('ai_chatbot_send_message', [message]);
            }
        },
        
        /**
         * Get last response
         */
        getLastResponse: function() {
            const $lastMessage = $('.ai-chatbot-message.bot').last();
            return $lastMessage.find('.ai-chatbot-message-content').text();
        },
        
        /**
         * Timer management
         */
        startSilenceTimer: function() {
            this.clearSilenceTimer();
            const self = this;
            this.silenceTimer = setTimeout(() => {
                if (self.isListening && !self.isSpeaking) {
                    self.stopListening();
                    self.updateStatus('silence_timeout');
                    setTimeout(() => {
                        if (self.isActive) {
                            self.startListening();
                        }
                    }, 1000);
                }
            }, this.config.silenceTimeout);
        },
        
        startListeningTimer: function() {
            this.clearListeningTimer();
            const self = this;
            this.listeningTimer = setTimeout(() => {
                if (self.isListening) {
                    self.stopListening();
                    self.updateStatus('listening_timeout');
                }
            }, this.config.listeningTimeout);
        },
        
        resetSilenceTimer: function() {
            this.clearSilenceTimer();
            this.startSilenceTimer();
        },
        
        clearSilenceTimer: function() {
            if (this.silenceTimer) {
                clearTimeout(this.silenceTimer);
                this.silenceTimer = null;
            }
        },
        
        clearListeningTimer: function() {
            if (this.listeningTimer) {
                clearTimeout(this.listeningTimer);
                this.listeningTimer = null;
            }
        },
        
        clearTimers: function() {
            this.clearSilenceTimer();
            this.clearListeningTimer();
        },
        
        /**
         * Update UI
         */
        updateUI: function() {
            if (this.isActive) {
                this.$toggleBtn.addClass('active').find('.btn-text').text('Exit Audio Mode');
                this.$indicator.addClass('active').show();
            } else {
                this.$toggleBtn.removeClass('active').find('.btn-text').text('Enter Audio Mode');
                this.$indicator.removeClass('active').hide();
            }
        },
        
        /**
         * Update status
         */
        updateStatus: function(status, text) {
            let statusText = '';
            let statusClass = '';
            
            switch (status) {
                case 'starting':
                    statusText = 'Starting audio mode...';
                    statusClass = 'starting';
                    break;
                case 'listening':
                    statusText = 'Listening...';
                    statusClass = 'listening';
                    break;
                case 'processing':
                    statusText = text || 'Processing...';
                    statusClass = 'processing';
                    break;
                case 'speaking':
                    statusText = 'Speaking...';
                    statusClass = 'speaking';
                    break;
                case 'silence_timeout':
                    statusText = 'Silence detected, restarting...';
                    statusClass = 'timeout';
                    break;
                case 'listening_timeout':
                    statusText = 'Listening timeout, please try again';
                    statusClass = 'timeout';
                    break;
                case 'stopped':
                    statusText = 'Audio mode stopped';
                    statusClass = 'stopped';
                    break;
                default:
                    statusText = text || 'Ready';
                    statusClass = 'ready';
            }
            
            this.$statusText.text(statusText);
            this.$indicator.removeClass('starting listening processing speaking timeout stopped ready')
                          .addClass(statusClass);
        },
        
        /**
         * Handle recognition errors
         */
        handleRecognitionError: function(error) {
            let errorMessage = 'Speech recognition error: ';
            
            switch (error) {
                case 'network':
                    errorMessage += 'Network error';
                    break;
                case 'not-allowed':
                    errorMessage += 'Microphone access denied';
                    break;
                case 'no-speech':
                    errorMessage += 'No speech detected';
                    break;
                case 'audio-capture':
                    errorMessage += 'Audio capture failed';
                    break;
                default:
                    errorMessage += error;
            }
            
            this.showError(errorMessage);
            
            // Auto-restart on certain errors
            if (['no-speech', 'audio-capture'].includes(error) && this.isActive) {
                setTimeout(() => {
                    this.startListening();
                }, 2000);
            }
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            this.updateStatus('error', message);
            console.error('Audio Mode Error:', message);
            
            // Show user-friendly notification
            if (typeof window.AIChatbotFrontend !== 'undefined') {
                window.AIChatbotFrontend.showNotification(message, 'error');
            }
        },
        
        /**
         * Get current state
         */
        getState: function() {
            return {
                isActive: this.isActive,
                isListening: this.isListening,
                isSpeaking: this.isSpeaking
            };
        },
        
        /**
         * Update configuration
         */
        updateConfig: function(newConfig) {
            this.config = $.extend(this.config, newConfig);
            
            if (this.recognition) {
                this.recognition.lang = this.config.language || 'en-US';
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Wait for audio config to be available
        if (typeof window.aiChatbotAudioConfig !== 'undefined') {
            window.AIChatbotAudioMode.init(window.aiChatbotAudioConfig.audio_mode || {});
        }
    });

})(jQuery);