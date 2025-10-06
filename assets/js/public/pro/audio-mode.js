/**
 * AI Chatbot Audio Mode - Following chatbot-frontend.js Pattern
 * Only shows user info form if not already provided in text mode
 */

(function($) {
    'use strict';

    window.AIChatbotAudioMode = {
        // State management
        isActive: false,
        isListening: false,
        isSpeaking: false,
        isPaused: false,
        recognition: null,
        synthesis: null,
        currentUtterance: null,
        conversationHistory: [],
        sessionId: null,
        conversationId: null,
        
        // User information (from chatbot-frontend.js pattern)
        currentUserData: null,

        /**
         * Initialize audio mode
         */
        init: function() {
            console.log('🎤 Initializing Audio Mode...');
            
            if (!this.checkBrowserSupport()) {
                console.error('❌ Browser does not support audio features');
                return;
            }

            this.setupRecognition();
            this.setupSynthesis();
            this.setupEventListeners();
            
            console.log('✅ Audio Mode initialized');
        },

        /**
         * Activate audio mode (called from audio-features.js)
         */
        activate: function() {
            console.log('🚀 Activating audio mode...');
            this.showModal();
        },

        /**
         * Deactivate audio mode
         */
        deactivate: function() {
            console.log('🛑 Deactivating audio mode...');
            this.stopAudioMode();
        },

        /**
         * Check browser support
         */
        checkBrowserSupport: function() {
            const hasRecognition = 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window;
            const hasSynthesis = 'speechSynthesis' in window;
            
            if (!hasRecognition) console.warn('⚠️ Speech recognition not supported');
            if (!hasSynthesis) console.warn('⚠️ Speech synthesis not supported');
            
            return hasRecognition && hasSynthesis;
        },

        /**
         * Setup speech recognition
         */
        setupRecognition: function() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.lang = 'en-US';
            this.recognition.maxAlternatives = 1;

            this.recognition.onstart = () => {
                console.log('🎤 Recognition started');
                this.isListening = true;
                this.updateUI();
            };

            this.recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                console.log('📝 Transcript:', transcript);
                
                // Mark as not listening immediately to prevent overlap
                this.isListening = false;
                this.updateUI();
                
                this.handleVoiceInput(transcript);
            };

            this.recognition.onerror = (event) => {
                console.error('❌ Recognition error:', event.error);
                this.isListening = false;
                this.updateUI();
                
                // Don't restart if audio mode is no longer active
                if (!this.isActive) {
                    console.log('⚠️ Audio mode not active, not restarting recognition');
                    return;
                }
                
                if (event.error === 'no-speech') {
                    this.showStatus('No speech detected. Please try again.');
                    if (this.isActive && !this.isPaused) {
                        setTimeout(() => {
                            if (this.isActive) { // Double check before restarting
                                this.startListening();
                            }
                        }, 1000);
                    }
                } else if (event.error === 'aborted') {
                    console.log('⚠️ Recognition aborted (this is normal when stopping)');
                    // Don't restart on abort - user intentionally stopped
                    this.showStatus('Audio conversation stopped');
                } else {
                    // For other errors, try to restart only if still active
                    if (this.isActive && !this.isPaused) {
                        setTimeout(() => {
                            if (this.isActive) {
                                this.startListening();
                            }
                        }, 1000);
                    }
                }
            };

            this.recognition.onend = () => {
                console.log('🎤 Recognition ended');
                this.isListening = false;
                this.updateUI();
                
                // Check if audio mode is still active before doing anything
                if (!this.isActive) {
                    console.log('ℹ️ Audio mode not active, not restarting recognition');
                }
            };
        },

        /**
         * Setup speech synthesis
         */
        setupSynthesis: function() {
            this.synthesis = window.speechSynthesis;
        },

        /**
         * Setup event listeners
         */
        setupEventListeners: function() {
            const self = this;

            $(document).on('click', '.audio-mode-close', function() {
                self.stopAudioMode();
            });

            $(document).on('click', '.audio-control-pause', function() {
                self.togglePause();
            });

            $(document).on('click', '.audio-control-stop', function() {
                self.stopAudioMode();
            });

            $(document).on('click', '.audio-mode-modal', function(e) {
                if ($(e.target).hasClass('audio-mode-modal')) {
                    self.stopAudioMode();
                }
            });

            $(document).on('submit', '#audio-mode-user-form', function(e) {
                e.preventDefault();
                self.submitUserInfo();
            });
        },

        /**
         * Get existing user data (following chatbot-frontend.js pattern)
         */
        getUserData: function() {
            // Try to get from AIChatbotFrontend (if available)
            if (window.AIChatbotFrontend && window.AIChatbotFrontend.currentUserData) {
                console.log('✅ Found user data from AIChatbotFrontend');
                return window.AIChatbotFrontend.currentUserData;
            }

            // Try localStorage
            const storedData = localStorage.getItem('ai_chatbot_user_data');
            if (storedData) {
                try {
                    const userData = JSON.parse(storedData);
                    if (userData.email && userData.name) {
                        console.log('✅ Found user data in localStorage');
                        return userData;
                    }
                } catch (e) {
                    console.error('Error parsing stored user data:', e);
                }
            }

            console.log('ℹ️ No existing user data found');
            return null;
        },

        /**
         * Show audio mode modal (following chatbot-frontend.js pattern)
         */
        showModal: function() {
            if ($('.audio-mode-modal').length > 0) {
                $('.audio-mode-modal').show();
                return;
            }

            // Get existing user data
            this.currentUserData = this.getUserData();
            
            if (this.currentUserData && this.currentUserData.email && this.currentUserData.name) {
                // User already authenticated - skip form
                console.log('👤 Using existing user data:', this.currentUserData);
                this.createModalWithoutForm();
                this.startAudioConversation();
            } else {
                // Show form to collect user info
                this.createModalWithForm();
            }
        },

        /**
         * Create modal with user info form
         */
        createModalWithForm: function() {
            const modalHTML = `
                <div class="audio-mode-modal" id="audio-mode-modal">
                    <div class="audio-mode-container">
                        <button class="audio-mode-close" title="Close">×</button>
                        
                        <div class="audio-mode-user-form" id="audio-mode-user-form-container">
                            <div class="audio-form-icon">
                                <i class="fas fa-microphone-alt"></i>
                            </div>
                            <h2>🎤 Start Voice Conversation</h2>
                            <p>Please provide your information to begin</p>
                            
                            <form id="audio-mode-user-form">
                                <div class="form-group">
                                    <label for="audio-user-name">
                                        <i class="fas fa-user"></i> Name *
                                    </label>
                                    <input 
                                        type="text" 
                                        id="audio-user-name" 
                                        name="user_name" 
                                        placeholder="Enter your name"
                                        required
                                    />
                                </div>
                                
                                <div class="form-group">
                                    <label for="audio-user-email">
                                        <i class="fas fa-envelope"></i> Email *
                                    </label>
                                    <input 
                                        type="email" 
                                        id="audio-user-email" 
                                        name="user_email" 
                                        placeholder="Enter your email"
                                        required
                                    />
                                </div>
                                
                                <button type="submit" class="btn-start-audio">
                                    <i class="fas fa-microphone"></i> Start Conversation
                                </button>
                                
                                <p class="privacy-note">
                                    <i class="fas fa-lock"></i> Your information is secure and private
                                </p>
                            </form>
                        </div>
                        
                        <div class="audio-mode-content" id="audio-mode-interface" style="display: none;">
                            ${this.getAudioInterfaceHTML()}
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
        },

        /**
         * Create modal without form
         */
        createModalWithoutForm: function() {
            const modalHTML = `
                <div class="audio-mode-modal" id="audio-mode-modal">
                    <div class="audio-mode-container">
                        <button class="audio-mode-close" title="Close">×</button>
                        
                        <div class="audio-mode-content" id="audio-mode-interface">
                            ${this.getAudioInterfaceHTML()}
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
            $('.audio-user-info .user-name').text(this.currentUserData.name);
        },

        /**
         * Get audio interface HTML
         */
        getAudioInterfaceHTML: function() {
            return `
                <div class="audio-mode-header">
                    <h2>🎤 Voice Conversation</h2>
                    <div class="audio-user-info">
                        <i class="fas fa-user-circle"></i>
                        <span class="user-name"></span>
                    </div>
                </div>
                
                <div class="audio-mode-status">
                    <div class="audio-status-icon">
                        <i class="fas fa-microphone"></i>
                    </div>
                    <p class="audio-status-text">Initializing...</p>
                </div>
                
                <div class="audio-waveform">
                    <span></span><span></span><span></span><span></span><span></span>
                </div>
                
                <div class="audio-transcript">
                    <div class="transcript-content"></div>
                </div>
                
                <div class="audio-controls">
                    <button class="audio-control-btn audio-control-pause" title="Pause">
                        <i class="fas fa-pause"></i>
                    </button>
                    <button class="audio-control-btn audio-control-stop" title="Stop">
                        <i class="fas fa-stop"></i>
                    </button>
                </div>
                
                <div class="audio-commands">
                    <p><strong>Voice Commands:</strong></p>
                    <span>"Exit" or "Stop"</span>
                    <span>"Pause"</span>
                    <span>"Resume"</span>
                    <span>"Repeat"</span>
                </div>
            `;
        },

        /**
         * Submit user info from form (following chatbot-frontend.js pattern)
         */
        submitUserInfo: function() {
            const name = $('#audio-user-name').val().trim();
            const email = $('#audio-user-email').val().trim();

            if (!name || !email) {
                this.showFormError('Please provide both name and email');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                this.showFormError('Please provide a valid email address');
                return;
            }

            // Create user data object (same as chatbot-frontend.js)
            this.currentUserData = {
                name: name,
                email: email,
                user_id: 0
            };

            console.log('👤 New user info submitted:', this.currentUserData);

            // Save to localStorage (same as chatbot-frontend.js)
            localStorage.setItem('ai_chatbot_user_data', JSON.stringify(this.currentUserData));
            localStorage.setItem('ai_chatbot_authenticated', 'true');

            // Hide form, show audio interface
            $('#audio-mode-user-form-container').fadeOut(300, () => {
                $('#audio-mode-interface').fadeIn(300);
                $('.audio-user-info .user-name').text(this.currentUserData.name);
                this.startAudioConversation();
            });
        },

        /**
         * Show form error
         */
        showFormError: function(message) {
            $('.form-error').remove();
            
            const errorHTML = `<div class="form-error"><i class="fas fa-exclamation-circle"></i> ${message}</div>`;
            $('#audio-mode-user-form').prepend(errorHTML);
            
            setTimeout(() => {
                $('.form-error').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Start audio conversation
         */
        startAudioConversation: function() {
            if (!this.currentUserData) {
                console.error('❌ Cannot start audio conversation without user data');
                return;
            }

            this.isActive = true;
            this.isPaused = false;
            this.conversationHistory = [];
            
            // Get session/conversation IDs from AIChatbotFrontend if available
            if (window.AIChatbotFrontend) {
                this.sessionId = window.AIChatbotFrontend.currentSessionId;
                this.conversationId = window.AIChatbotFrontend.currentConversationId;
            } else {
                this.sessionId = 'audio_' + Date.now();
                this.conversationId = 'conv_' + Date.now();
            }

            console.log('🚀 Starting audio conversation:', {
                userData: this.currentUserData,
                sessionId: this.sessionId,
                conversationId: this.conversationId
            });

            // Log session start
            this.logAudioSession('start');

            // Welcome message
            const welcomeMessage = `Hello ${this.currentUserData.name}! I'm ready to help. What would you like to talk about?`;
            this.speak(welcomeMessage, () => {
                this.startListening();
            });
        },

        /**
         * Start listening
         */
        startListening: function() {
            if (!this.isActive || this.isPaused || this.isListening || this.isSpeaking) {
                console.log('⚠️ Cannot start listening - State:', {
                    isActive: this.isActive,
                    isPaused: this.isPaused,
                    isListening: this.isListening,
                    isSpeaking: this.isSpeaking
                });
                return;
            }

            console.log('👂 Starting to listen...');
            this.showStatus('Listening... Speak now');
            
            try {
                this.recognition.start();
            } catch (error) {
                console.error('Error starting recognition:', error);
                
                // If already started, stop and restart
                if (error.name === 'InvalidStateError') {
                    console.log('🔄 Recognition already running, restarting...');
                    this.isListening = false;
                    this.recognition.stop();
                    
                    // Wait a moment then try again
                    setTimeout(() => {
                        if (this.isActive && !this.isPaused && !this.isListening && !this.isSpeaking) {
                            this.startListening();
                        }
                    }, 500);
                }
            }
        },

        /**
         * Handle voice input
         */
        handleVoiceInput: function(transcript) {
            if (!transcript) {
                console.warn('⚠️ Empty transcript received');
                return;
            }

            const lowerTranscript = transcript.toLowerCase().trim();
            
            console.log('🎤 Voice input received:', transcript);
            
            // Add to transcript display FIRST
            this.addToTranscript('You', transcript);

            // Check for voice commands FIRST before processing as message
            if (this.handleVoiceCommand(lowerTranscript)) {
                console.log('✅ Voice command handled, not sending to AI');
                return; // Don't send command to AI
            }

            this.showStatus('Processing your message...');
            this.sendToAI(transcript);
        },

        /**
         * Handle voice commands
         */
        handleVoiceCommand: function(transcript) {
            console.log('🎯 Checking for voice commands in:', transcript);
            
            // Check for exit/stop commands
            if (transcript.includes('exit') || transcript.includes('stop conversation') || transcript.includes('end conversation')) {
                console.log('🛑 Exit/Stop command detected!');
                this.speak('Goodbye! Closing audio conversation.', () => {
                    this.stopAudioMode();
                });
                return true;
            }
            
            // Check for pause command
            if (transcript.includes('pause')) {
                console.log('⏸️ Pause command detected!');
                this.setPause(true);
                this.speak('Audio conversation paused. Say resume to continue.');
                return true;
            }
            
            // Check for resume/continue commands
            if (transcript.includes('resume') || transcript.includes('continue')) {
                console.log('▶️ Resume command detected!');
                this.setPause(false);
                this.speak('Resuming audio conversation.');
                return true;
            }
            
            // Check for repeat command
            if (transcript.includes('repeat') || transcript.includes('say that again')) {
                console.log('🔁 Repeat command detected!');
                this.repeatLastResponse();
                return true;
            }

            console.log('ℹ️ No voice command detected, treating as normal message');
            return false;
        },

        /**
         * Send message to AI (FOLLOWING chatbot-frontend.js PATTERN)
         */
        sendToAI: function(message) {
            const self = this;

            // Build request data EXACTLY like chatbot-frontend.js
            const requestData = {
                action: 'ai_chatbot_send_message',
                message: message,
                session_id: this.sessionId,
                conversation_id: this.conversationId,
                audio_mode: true,  // Additional flag for audio mode
                nonce: window.ai_chatbot_ajax.nonce
            };

            // Add user data if available (EXACTLY like chatbot-frontend.js)
            if (this.currentUserData) {
                requestData.user_name = this.currentUserData.name;
                requestData.user_email = this.currentUserData.email;
                requestData.user_id = this.currentUserData.user_id || 0;
            }

            console.log('📤 Sending to AI:', requestData);

            $.ajax({
                url: window.ai_chatbot_ajax.ajaxUrl,
                type: 'POST',
                data: requestData,
                timeout: 30000,
                success: function(response) {
                    console.log('📥 Response received:', response);
                    
                    if (response.success && response.data && response.data.response) {
                        self.handleAIResponse(response.data.response);
                    } else {
                        const errorMsg = response.data && response.data.message ? response.data.message : 'Sorry, I encountered an error. Please try again.';
                        self.handleError(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX error:', { xhr, status, error });
                    self.handleError('Sorry, I could not connect to the server. Please try again.');
                }
            });
        },

        /**
         * Handle AI response
         */
        handleAIResponse: function(response) {
            this.conversationHistory.push({
                type: 'ai',
                message: response,
                timestamp: Date.now()
            });

            this.addToTranscript('AI', response);

            this.speak(response, () => {
                // Only start listening if audio mode is still active and not paused
                if (this.isActive && !this.isPaused && !this.isListening) {
                    setTimeout(() => {
                        // Double check before starting
                        if (!this.isListening && !this.isSpeaking) {
                            this.startListening();
                        }
                    }, 500);
                }
            });
        },

        /**
         * Handle error
         */
        handleError: function(errorMessage) {
            this.addToTranscript('System', errorMessage);
            this.speak(errorMessage, () => {
                if (this.isActive && !this.isPaused) {
                    setTimeout(() => {
                        this.startListening();
                    }, 1000);
                }
            });
        },

        /**
         * Speak text
         */
        speak: function(text, onEndCallback) {
            if (!text || this.isPaused) return;

            this.synthesis.cancel();

            this.isSpeaking = true;
            this.showStatus('Speaking...');

            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = 1.0;
            utterance.pitch = 1.0;
            utterance.volume = 1.0;

            utterance.onend = () => {
                console.log('🔊 Speech ended');
                this.isSpeaking = false;
                this.updateUI();
                if (onEndCallback) onEndCallback();
            };

            utterance.onerror = (event) => {
                console.error('❌ Speech error:', event);
                this.isSpeaking = false;
                this.updateUI();
            };

            this.currentUtterance = utterance;
            this.synthesis.speak(utterance);
        },

        /**
         * Toggle pause
         */
        togglePause: function() {
            this.isPaused = !this.isPaused;
            
            if (this.isPaused) {
                this.showStatus('Paused - Click resume to continue');
                $('.audio-control-pause i').removeClass('fa-pause').addClass('fa-play');
                this.synthesis.cancel();
            } else {
                this.showStatus('Resumed');
                $('.audio-control-pause i').removeClass('fa-play').addClass('fa-pause');
                this.startListening();
            }

            console.log(this.isPaused ? '⏸️ Paused' : '▶️ Resumed');
        },

        /**
         * Set pause state
         */
        setPause: function(paused) {
            if (this.isPaused === paused) return;
            this.togglePause();
        },

        /**
         * Repeat last response
         */
        repeatLastResponse: function() {
            const lastAIMessage = this.conversationHistory
                .slice()
                .reverse()
                .find(msg => msg.type === 'ai');

            if (lastAIMessage) {
                this.speak(lastAIMessage.message, () => {
                    if (this.isActive && !this.isPaused) {
                        this.startListening();
                    }
                });
            } else {
                this.speak('I don\'t have any previous message to repeat.', () => {
                    this.startListening();
                });
            }
        },

        /**
         * Stop audio mode
         */
        stopAudioMode: function() {
            console.log('🛑 Stopping audio mode...');

            // Update state immediately
            this.isActive = false;
            this.isPaused = false;
            this.isListening = false;
            this.isSpeaking = false;

            // Stop recognition immediately
            if (this.recognition) {
                try {
                    this.recognition.abort(); // Use abort instead of stop for immediate effect
                    console.log('✅ Recognition aborted');
                } catch (e) {
                    console.log('⚠️ Recognition already stopped:', e.message);
                }
            }

            // Cancel any ongoing speech
            if (this.synthesis) {
                try {
                    this.synthesis.cancel();
                    console.log('✅ Speech synthesis cancelled');
                } catch (e) {
                    console.log('⚠️ Speech synthesis error:', e.message);
                }
            }

            // Log session end
            this.logAudioSession('end');

            // Remove modal with animation
            const $modal = $('.audio-mode-modal');
            if ($modal.length) {
                $modal.fadeOut(300, function() {
                    $(this).remove();
                    console.log('✅ Modal removed');
                });
            }

            // Clear conversation history
            this.conversationHistory = [];
            this.sessionId = null;
            this.conversationId = null;

            console.log('✅ Audio mode stopped successfully');
        },

        /**
         * Safely stop recognition
         */
        stopRecognition: function() {
            if (this.isListening && this.recognition) {
                try {
                    this.recognition.abort(); // Use abort for immediate stop
                    this.isListening = false;
                    console.log('✅ Recognition stopped');
                } catch (e) {
                    console.log('⚠️ Could not stop recognition:', e.message);
                    this.isListening = false;
                }
            }
        },

        /**
         * Update UI
         */
        updateUI: function() {
            const $statusIcon = $('.audio-status-icon i');
            const $waveform = $('.audio-waveform');

            if (this.isListening) {
                $statusIcon.removeClass('fa-microphone fa-comment').addClass('fa-microphone');
                $waveform.addClass('active');
            } else if (this.isSpeaking) {
                $statusIcon.removeClass('fa-microphone fa-comment').addClass('fa-comment');
                $waveform.addClass('active');
            } else {
                $waveform.removeClass('active');
            }
        },

        /**
         * Show status
         */
        showStatus: function(message) {
            $('.audio-status-text').text(message);
        },

        /**
         * Add to transcript
         */
        addToTranscript: function(speaker, message) {
            console.log('📝 Adding to transcript:', speaker, message);
            
            const timestamp = new Date().toLocaleTimeString();
            const speakerClass = speaker.toLowerCase() === 'you' ? 'transcript-you' : 
                                speaker.toLowerCase() === 'ai' || speaker.toLowerCase() === 'assistant' ? 'transcript-ai' : 
                                'transcript-system';
            
            const transcriptHTML = `
                <div class="transcript-item ${speakerClass}">
                    <div class="transcript-header">
                        <span class="transcript-speaker">${speaker}</span>
                        <span class="transcript-time">${timestamp}</span>
                    </div>
                    <p class="transcript-message">${this.escapeHtml(message)}</p>
                </div>
            `;

            // Append to transcript
            const $transcriptContent = $('.transcript-content');
            if ($transcriptContent.length) {
                $transcriptContent.append(transcriptHTML);
                
                // Auto-scroll to bottom
                const transcriptContainer = $transcriptContent.parent('.audio-transcript');
                if (transcriptContainer.length) {
                    transcriptContainer.scrollTop(transcriptContainer[0].scrollHeight);
                }
                
                console.log('✅ Transcript updated, items:', $transcriptContent.find('.transcript-item').length);
            } else {
                console.error('❌ .transcript-content not found!');
            }
            
            // Also add to conversation history mini view
            this.addToConversationMini(speaker, message);
        },

        /**
         * Add message to the mini conversation view
         */
        addToConversationMini: function(speaker, message) {
            const $conversationMessages = $('.conversation-messages');
            
            if (!$conversationMessages.length) {
                console.warn('⚠️ .conversation-messages not found');
                return;
            }
            
            const isUser = speaker.toLowerCase() === 'you';
            const type = isUser ? 'user' : 'bot';
            const icon = isUser ? '👤' : '🤖';
            
            const messageHTML = `
                <div class="audio-message ${type}">
                    <span class="message-icon">${icon}</span>
                    <span class="message-text">${this.escapeHtml(message)}</span>
                </div>
            `;
            
            $conversationMessages.append(messageHTML);
            
            // Auto-scroll to bottom
            $conversationMessages.scrollTop($conversationMessages[0].scrollHeight);
            
            // Limit to last 5 messages
            const messages = $conversationMessages.find('.audio-message');
            if (messages.length > 5) {
                messages.first().remove();
            }
            
            console.log('✅ Conversation mini updated, messages:', messages.length);
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Log audio session
         */
        logAudioSession: function(action) {
            // Check if logging is enabled
            if (!window.aiChatbotAudio || !window.aiChatbotAudio.log_sessions) {
                console.log('📊 Audio session logging disabled');
                return;
            }

            if (!this.currentUserData) {
                console.log('⚠️ No user data for session logging');
                return;
            }

            console.log('📊 Logging audio session:', action);

            $.ajax({
                url: window.ai_chatbot_ajax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_log_audio_session',
                    session_action: action,
                    session_id: this.sessionId,
                    conversation_id: this.conversationId,
                    user_name: this.currentUserData.name,
                    user_email: this.currentUserData.email,
                    messages_count: this.conversationHistory.length,
                    nonce: window.ai_chatbot_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Audio session logged:', action);
                    } else {
                        console.warn('⚠️ Failed to log audio session:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.warn('⚠️ Audio session logging error (non-critical):', {
                        status: xhr.status,
                        error: error
                    });
                    // Don't show error to user - this is non-critical
                }
            });
        },

        /**
         * Get current state
         */
        getState: function() {
            return {
                isActive: this.isActive,
                isListening: this.isListening,
                isSpeaking: this.isSpeaking,
                isPaused: this.isPaused,
                hasUserData: !!this.currentUserData,
                transcriptItems: $('.transcript-content .transcript-item').length,
                modalVisible: $('.audio-mode-modal').is(':visible')
            };
        },

        /**
         * Check if audio mode is active
         */
        isAudioModeActive: function() {
            return this.isActive;
        },

        /**
         * Debug transcript
         */
        debugTranscript: function() {
            console.log('🔍 Transcript Debug:');
            console.log('- Modal exists:', $('.audio-mode-modal').length > 0);
            console.log('- Modal visible:', $('.audio-mode-modal').is(':visible'));
            console.log('- Transcript container exists:', $('.audio-transcript').length > 0);
            console.log('- Transcript content exists:', $('.transcript-content').length > 0);
            console.log('- Transcript items count:', $('.transcript-content .transcript-item').length);
            console.log('- Conversation history:', this.conversationHistory);
            
            // Try to add a test message
            this.addToTranscript('Test', 'This is a test message');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if (typeof window.AIChatbotAudioMode !== 'undefined') {
            window.AIChatbotAudioMode.init();
        }
    });

})(jQuery);