/**
 * AI Chatbot Audio Mode - Voice Conversation Manager
 * Handles continuous voice conversation in modal
 */

(function($) {
    'use strict';

    window.AIChatbotAudioMode = {
        isActive: false,
        isPaused: false,
        recognition: null,
        synthesis: null,
        currentState: 'idle',
        settings: null,
        sessionId: null,
        silenceTimer: null,
        conversationTimer: null,
        maxConversationTime: 300000, // 5 minutes
        
        activate: function() {
            console.log('Activating audio conversation mode...');
            
            this.settings = window.aiChatbotAudio || {};
            this.isActive = true;
            this.isPaused = false;
            this.sessionId = this.generateSessionId();
            
            this.initializeSpeechRecognition();
            this.initializeSpeechSynthesis();
            this.startListening();
            
            // Set conversation timeout
            this.conversationTimer = setTimeout(() => {
                this.handleTimeout();
            }, this.maxConversationTime);

            // Update UI
            if (window.AIChatbotAudio) {
                window.AIChatbotAudio.updateAudioStatus('listening', 'Listening... Speak now');
            }
        },

        deactivate: function() {
            console.log('Deactivating audio conversation mode...');
            
            this.stopListening();
            this.stopSpeaking();
            this.clearTimers();
            
            this.isActive = false;
            this.isPaused = false;
            this.currentState = 'idle';
            
            // Log session end
            this.logAudioSession('end');
        },

        pause: function() {
            if (!this.isActive) return;
            
            console.log('Pausing audio mode...');
            this.isPaused = true;
            this.stopListening();
            
            // Update button state
            $('.pause-btn').html(`
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
                <span>Resume</span>
            `).data('action', 'resume');

            if (window.AIChatbotAudio) {
                window.AIChatbotAudio.updateAudioStatus('paused', 'Paused - Click Resume to continue');
            }
        },

        resume: function() {
            if (!this.isActive) return;
            
            console.log('Resuming audio mode...');
            this.isPaused = false;
            this.startListening();
            
            // Update button state
            $('.pause-btn').html(`
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="6" y="4" width="4" height="16"></rect>
                    <rect x="14" y="4" width="4" height="16"></rect>
                </svg>
                <span>Pause</span>
            `).data('action', 'pause');

            if (window.AIChatbotAudio) {
                window.AIChatbotAudio.updateAudioStatus('listening', 'Listening... Speak now');
            }
        },

        initializeSpeechRecognition: function() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            
            if (!SpeechRecognition) {
                console.error('Speech recognition not supported');
                return;
            }

            this.recognition = new SpeechRecognition();
            this.recognition.continuous = true;
            this.recognition.interimResults = true;
            this.recognition.lang = this.settings.voice_language || 'en-US';
            this.recognition.maxAlternatives = 1;

            this.bindRecognitionEvents();
        },

        bindRecognitionEvents: function() {
            const self = this;

            this.recognition.onstart = function() {
                console.log('Speech recognition started');
                self.currentState = 'listening';
            };

            this.recognition.onresult = function(event) {
                self.handleSpeechResult(event);
            };

            this.recognition.onerror = function(event) {
                console.error('Speech recognition error:', event.error);
                self.handleRecognitionError(event.error);
            };

            this.recognition.onend = function() {
                console.log('Speech recognition ended');
                
                // Auto-restart if still active and not paused
                if (self.isActive && !self.isPaused) {
                    setTimeout(function() {
                        self.startListening();
                    }, 500);
                }
            };
        },

        handleSpeechResult: function(event) {
            let interimTranscript = '';
            let finalTranscript = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                
                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interimTranscript += transcript;
                }
            }

            // Update UI with interim results
            if (interimTranscript && window.AIChatbotAudio) {
                window.AIChatbotAudio.updateTranscript(interimTranscript, false);
            }

            // Process final transcript
            if (finalTranscript) {
                this.processFinalTranscript(finalTranscript);
            }

            // Reset silence timer
            this.resetSilenceTimer();
        },

        processFinalTranscript: function(transcript) {
            const cleanTranscript = transcript.trim();
            
            if (!cleanTranscript) return;

            console.log('Final transcript:', cleanTranscript);

            // Update UI
            if (window.AIChatbotAudio) {
                window.AIChatbotAudio.updateTranscript(cleanTranscript, true);
                window.AIChatbotAudio.addConversationMessage('user', cleanTranscript);
                window.AIChatbotAudio.updateAudioStatus('processing', 'Processing your message...');
            }

            // Check for voice commands
            const command = this.detectVoiceCommand(cleanTranscript);
            if (command) {
                this.executeVoiceCommand(command);
                return;
            }

            // Send to chatbot
            this.sendMessageToChatbot(cleanTranscript);
        },

        detectVoiceCommand: function(text) {
            const lowerText = text.toLowerCase().trim();
            
            const commands = {
                exit: ['exit', 'stop', 'quit', 'close', 'end conversation', 'goodbye'],
                pause: ['pause', 'wait', 'hold on', 'stop listening'],
                resume: ['resume', 'continue', 'go on'],
                repeat: ['repeat', 'say that again', 'what did you say'],
                clear: ['clear', 'reset', 'start over', 'new conversation'],
                help: ['help', 'what can you do', 'commands', 'instructions']
            };

            for (const [command, phrases] of Object.entries(commands)) {
                for (const phrase of phrases) {
                    if (lowerText === phrase || lowerText.includes(phrase)) {
                        return command;
                    }
                }
            }

            return null;
        },

        executeVoiceCommand: function(command) {
            console.log('Executing voice command:', command);

            switch(command) {
                case 'exit':
                    this.speakResponse('Closing audio mode. Goodbye!', () => {
                        if (window.AIChatbotAudio) {
                            window.AIChatbotAudio.closeAudioMode();
                        }
                    });
                    break;

                case 'pause':
                    this.pause();
                    this.speakResponse('Audio mode paused. Say resume to continue.');
                    break;

                case 'resume':
                    this.resume();
                    break;

                case 'repeat':
                    const lastMessage = $('.conversation-messages .audio-message.bot').last().find('.message-text').text();
                    if (lastMessage) {
                        this.speakResponse(lastMessage);
                    } else {
                        this.speakResponse('No previous message to repeat.');
                    }
                    break;

                case 'clear':
                    this.speakResponse('Clearing conversation history.', () => {
                        $('.conversation-messages').empty();
                        $(document).trigger('ai_chatbot_clear_chat');
                    });
                    break;

                case 'help':
                    const helpText = 'Available commands: exit to close, pause to stop listening, repeat to hear the last message, clear to start over, or just speak naturally to chat.';
                    this.speakResponse(helpText);
                    break;
            }
        },

        sendMessageToChatbot: function(message) {
            const self = this;

            // Stop listening while processing
            this.stopListening();

            // Get AJAX URL and nonce
            const ajaxUrl = window.aiChatbotAudio?.ajaxUrl || '/wp-admin/admin-ajax.php';
            const nonce = window.aiChatbotAudio?.nonce || '';

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_send_message',
                    message: message,
                    session_id: this.sessionId,
                    audio_mode: true,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.handleChatbotResponse(response.data);
                    } else {
                        self.handleError('Failed to get response from chatbot');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    self.handleError('Network error occurred');
                }
            });
        },

        handleChatbotResponse: function(data) {
            const responseText = data.response || data.message || 'I received your message.';
            
            console.log('Chatbot response:', responseText);

            // Add to conversation display
            if (window.AIChatbotAudio) {
                window.AIChatbotAudio.addConversationMessage('bot', responseText);
                window.AIChatbotAudio.updateAudioStatus('speaking', 'Speaking response...');
            }

            // Speak the response
            this.speakResponse(responseText, () => {
                // After speaking, resume listening
                if (this.isActive && !this.isPaused) {
                    setTimeout(() => {
                        this.startListening();
                    }, 500);
                }
            });
        },

        initializeSpeechSynthesis: function() {
            if (!('speechSynthesis' in window)) {
                console.error('Speech synthesis not supported');
                return;
            }

            this.synthesis = window.speechSynthesis;
        },

        speakResponse: function(text, callback) {
            if (!this.synthesis) {
                console.error('Speech synthesis not initialized');
                if (callback) callback();
                return;
            }

            // Cancel any ongoing speech
            this.synthesis.cancel();

            const utterance = new SpeechSynthesisUtterance(text);
            
            // Configure voice settings
            const settings = this.settings.tts || {};
            utterance.rate = parseFloat(settings.rate) || 1.0;
            utterance.pitch = parseFloat(settings.pitch) || 1.0;
            utterance.volume = parseFloat(settings.volume) || 0.8;
            utterance.lang = settings.language || 'en-US';

            // Select voice if specified
            if (settings.voice) {
                const voices = this.synthesis.getVoices();
                const selectedVoice = voices.find(v => v.name === settings.voice);
                if (selectedVoice) {
                    utterance.voice = selectedVoice;
                }
            }

            utterance.onstart = () => {
                console.log('Started speaking');
                this.currentState = 'speaking';
            };

            utterance.onend = () => {
                console.log('Finished speaking');
                this.currentState = 'idle';
                if (callback) callback();
            };

            utterance.onerror = (event) => {
                console.error('Speech synthesis error:', event);
                if (callback) callback();
            };

            this.synthesis.speak(utterance);
        },

        startListening: function() {
            if (!this.recognition || this.isPaused) return;

            try {
                this.recognition.start();
                console.log('Started listening...');
            } catch (error) {
                console.error('Error starting recognition:', error);
            }
        },

        stopListening: function() {
            if (!this.recognition) return;

            try {
                this.recognition.stop();
                console.log('Stopped listening');
            } catch (error) {
                console.error('Error stopping recognition:', error);
            }
        },

        stopSpeaking: function() {
            if (!this.synthesis) return;

            this.synthesis.cancel();
            console.log('Stopped speaking');
        },

        resetSilenceTimer: function() {
            if (this.silenceTimer) {
                clearTimeout(this.silenceTimer);
            }

            const self = this;
            const silenceTimeout = (this.settings.audio_mode?.silence_timeout || 30) * 1000;

            this.silenceTimer = setTimeout(function() {
                console.log('Silence detected - prompting user');
                self.speakResponse('Are you still there? Say something to continue, or say exit to close.');
            }, silenceTimeout);
        },

        clearTimers: function() {
            if (this.silenceTimer) {
                clearTimeout(this.silenceTimer);
                this.silenceTimer = null;
            }

            if (this.conversationTimer) {
                clearTimeout(this.conversationTimer);
                this.conversationTimer = null;
            }
        },

        handleTimeout: function() {
            console.log('Conversation timeout reached');
            this.speakResponse('Maximum conversation time reached. Closing audio mode.', () => {
                if (window.AIChatbotAudio) {
                    window.AIChatbotAudio.closeAudioMode();
                }
            });
        },

        handleRecognitionError: function(error) {
            console.error('Recognition error:', error);

            let message = 'An error occurred. ';
            
            switch(error) {
                case 'no-speech':
                    message = 'No speech detected. Please try again.';
                    break;
                case 'audio-capture':
                    message = 'Microphone not accessible. Please check permissions.';
                    break;
                case 'not-allowed':
                    message = 'Microphone permission denied. Please enable it in your browser settings.';
                    break;
                case 'network':
                    message = 'Network error. Please check your connection.';
                    break;
                default:
                    message += 'Please try again.';
            }

            if (window.AIChatbotAudio) {
                window.AIChatbotAudio.showNotification('error', message);
            }
        },

        handleError: function(message) {
            console.error('Audio mode error:', message);

            if (window.AIChatbotAudio) {
                window.AIChatbotAudio.showNotification('error', message);
                window.AIChatbotAudio.updateAudioStatus('error', message);
            }

            // Resume listening after error
            setTimeout(() => {
                if (this.isActive && !this.isPaused) {
                    this.startListening();
                }
            }, 2000);
        },

        generateSessionId: function() {
            return 'audio_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        logAudioSession: function(event) {
            // Log session events for analytics
            console.log('Audio session event:', event, this.sessionId);
            
            // Send to backend if analytics enabled
            if (this.settings.analytics_enabled) {
                $.ajax({
                    url: window.aiChatbotPublic?.ajax_url || '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    data: {
                        action: 'ai_chatbot_log_audio_session',
                        event: event,
                        session_id: this.sessionId,
                        nonce: window.aiChatbotPublic?.nonce || ''
                    }
                });
            }
        }
    };

})(jQuery);