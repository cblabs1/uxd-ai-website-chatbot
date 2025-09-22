/**
 * AI Chatbot Pro Voice Input Enhanced
 * Advanced voice input functionality for frontend
 * 
 * @package AI_Website_Chatbot
 * @subpackage Pro\Audio\Frontend
 * @since 1.0.0
 */

(function($) {
    'use strict';

    window.AIChatbotAudioVoiceInput = {
        // Core properties
        audioCore: null,
        config: {},
        
        // Voice recognition
        recognition: null,
        isListening: false,
        isEnabled: true,
        
        // UI elements
        $voiceButton: null,
        $voiceModal: null,
        $transcriptDisplay: null,
        
        // Voice processing
        currentTranscript: '',
        finalTranscript: '',
        interimTranscript: '',
        
        // Statistics
        sessionStats: {
            startTime: null,
            totalInputs: 0,
            successfulRecognitions: 0,
            averageConfidence: 0,
            languageDetected: null
        },

        /**
         * Initialize voice input module
         */
        init: function(audioCore) {
            this.audioCore = audioCore;
            this.config = audioCore.config.voice_input || {};
            
            console.log('Initializing Voice Input Enhanced...');

            if (!this.isVoiceInputSupported()) {
                console.warn('Voice input not supported in this browser');
                return false;
            }

            this.setupVoiceRecognition();
            this.setupUI();
            this.bindEvents();
            this.loadUserPreferences();

            console.log('Voice Input Enhanced initialized successfully');
            return true;
        },

        /**
         * Check if voice input is supported
         */
        isVoiceInputSupported: function() {
            return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
        },

        /**
         * Setup speech recognition
         */
        setupVoiceRecognition: function() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            
            if (!SpeechRecognition) {
                return;
            }

            this.recognition = new SpeechRecognition();
            this.configureRecognition();
            this.bindRecognitionEvents();
        },

        /**
         * Configure speech recognition settings
         */
        configureRecognition: function() {
            if (!this.recognition) return;

            // Basic settings
            this.recognition.continuous = this.config.continuous || false;
            this.recognition.interimResults = this.config.interim_results !== false;
            this.recognition.lang = this.config.language || 'en-US';
            this.recognition.maxAlternatives = this.config.max_alternatives || 1;

            // Advanced settings (if supported)
            if ('grammars' in this.recognition) {
                this.recognition.grammars = this.createSpeechGrammar();
            }
            
            // Service hints (if supported)
            if ('serviceURI' in this.recognition) {
                this.recognition.serviceURI = this.config.service_uri;
            }
        },

        /**
         * Create speech grammar for better recognition
         */
        createSpeechGrammar: function() {
            if (!window.SpeechGrammarList) {
                return null;
            }

            const grammarList = new SpeechGrammarList();
            
            // Add common phrases and commands
            const commonPhrases = [
                'hello', 'help', 'thank you', 'yes', 'no', 'okay', 'stop', 'start',
                'clear chat', 'repeat that', 'audio mode', 'voice commands'
            ];
            
            const grammar = `#JSGF V1.0; grammar phrases; public <phrase> = ${commonPhrases.join(' | ')};`;
            grammarList.addFromString(grammar, 1);
            
            return grammarList;
        },

        /**
         * Bind speech recognition events
         */
        bindRecognitionEvents: function() {
            if (!this.recognition) return;

            const self = this;

            this.recognition.onstart = function() {
                self.handleRecognitionStart();
            };

            this.recognition.onresult = function(event) {
                self.handleRecognitionResult(event);
            };

            this.recognition.onerror = function(event) {
                self.handleRecognitionError(event);
            };

            this.recognition.onend = function() {
                self.handleRecognitionEnd();
            };

            this.recognition.onsoundstart = function() {
                self.handleSoundStart();
            };

            this.recognition.onsoundend = function() {
                self.handleSoundEnd();
            };

            this.recognition.onspeechstart = function() {
                self.handleSpeechStart();
            };

            this.recognition.onspeechend = function() {
                self.handleSpeechEnd();
            };

            this.recognition.onnomatch = function() {
                self.handleNoMatch();
            };
        },

        /**
         * Setup UI elements
         */
        setupUI: function() {
            this.findUIElements();
            this.enhanceVoiceButton();
            this.createVoiceModal();
            this.updateUIState();
        },

        /**
         * Find existing UI elements
         */
        findUIElements: function() {
            this.$voiceButton = $('.voice-btn, .voice-input-btn, .pre-action-btn.voice-btn').first();
            
            if (!this.$voiceButton.length) {
                this.createVoiceButton();
            }
        },

        /**
         * Create voice button if not exists
         */
        createVoiceButton: function() {
            const buttonHtml = `
                <button type="button" class="voice-btn enhanced-voice-btn" 
                        aria-label="${this.audioCore.strings.voiceInputLabel || 'Voice input'}"
                        title="${this.audioCore.strings.voiceInputTooltip || 'Click to start voice input'}">
                    <span class="voice-icon">
                        <span class="dashicons dashicons-microphone"></span>
                    </span>
                    <span class="voice-status-indicator"></span>
                    <span class="voice-level-indicator">
                        <span class="level-bar"></span>
                        <span class="level-bar"></span>
                        <span class="level-bar"></span>
                        <span class="level-bar"></span>
                        <span class="level-bar"></span>
                    </span>
                </button>
            `;

            // Try to add to input container
            const $inputContainer = $('.ai-chatbot-input-container, .popup-input-container, .inline-input-container').first();
            if ($inputContainer.length) {
                $inputContainer.find('.pre-action-buttons').append(buttonHtml);
                this.$voiceButton = $inputContainer.find('.voice-btn').last();
            }
        },

        /**
         * Enhance voice button with Pro features
         */
        enhanceVoiceButton: function() {
            if (!this.$voiceButton.length) return;

            // Add Pro classes
            this.$voiceButton.addClass('voice-btn-enhanced pro-voice-btn');
            
            // Add status indicator if not present
            if (!this.$voiceButton.find('.voice-status-indicator').length) {
                this.$voiceButton.append('<span class="voice-status-indicator"></span>');
            }

            // Add level indicator for audio visualization
            if (!this.$voiceButton.find('.voice-level-indicator').length) {
                const levelIndicator = $(`
                    <span class="voice-level-indicator">
                        <span class="level-bar"></span>
                        <span class="level-bar"></span>
                        <span class="level-bar"></span>
                        <span class="level-bar"></span>
                        <span class="level-bar"></span>
                    </span>
                `);
                this.$voiceButton.append(levelIndicator);
            }

            // Update button tooltip
            this.$voiceButton.attr('title', this.getButtonTooltip());
        },

        /**
         * Create enhanced voice modal
         */
        createVoiceModal: function() {
            const modalHtml = `
                <div class="ai-chatbot-voice-modal enhanced-voice-modal" style="display: none;">
                    <div class="voice-modal-overlay"></div>
                    <div class="voice-modal-content">
                        <div class="voice-modal-header">
                            <h3 class="voice-modal-title">${this.audioCore.strings.listeningStart || 'Listening...'}</h3>
                            <button class="voice-modal-close" aria-label="Close">&times;</button>
                        </div>
                        
                        <div class="voice-animation-container">
                            <div class="voice-circle">
                                <div class="voice-pulse"></div>
                                <div class="voice-pulse pulse-2"></div>
                                <div class="microphone-icon">🎤</div>
                            </div>
                            <div class="voice-level-visualization">
                                <div class="level-bars">
                                    ${Array.from({length: 20}, (_, i) => `<div class="level-bar" data-level="${i}"></div>`).join('')}
                                </div>
                            </div>
                        </div>

                        <div class="voice-status">
                            <div class="status-text">${this.audioCore.strings.speakNow || 'Speak now...'}</div>
                            <div class="status-details">
                                <span class="confidence-indicator">
                                    <span class="label">Confidence:</span>
                                    <span class="value">--</span>
                                </span>
                                <span class="language-indicator">
                                    <span class="label">Language:</span>
                                    <span class="value">${this.config.language || 'Auto'}</span>
                                </span>
                            </div>
                        </div>

                        <div class="voice-transcript">
                            <div class="transcript-label">${this.audioCore.strings.transcriptLabel || 'You said:'}</div>
                            <div class="transcript-content">
                                <div class="final-transcript"></div>
                                <div class="interim-transcript"></div>
                            </div>
                        </div>

                        <div class="voice-controls">
                            <button class="voice-control-btn stop-btn" data-action="stop">
                                <span class="dashicons dashicons-controls-pause"></span>
                                <span class="btn-text">${this.audioCore.strings.stopListening || 'Stop'}</span>
                            </button>
                            <button class="voice-control-btn cancel-btn" data-action="cancel">
                                <span class="dashicons dashicons-no"></span>
                                <span class="btn-text">${this.audioCore.strings.cancelVoice || 'Cancel'}</span>
                            </button>
                            <button class="voice-control-btn send-btn" data-action="send" style="display: none;">
                                <span class="dashicons dashicons-yes"></span>
                                <span class="btn-text">${this.audioCore.strings.sendTranscript || 'Send'}</span>
                            </button>
                        </div>

                        <div class="voice-tips">
                            <div class="tips-toggle">
                                <span class="tips-label">${this.audioCore.strings.voiceTips || 'Voice Tips'}</span>
                                <span class="tips-arrow">▼</span>
                            </div>
                            <div class="tips-content" style="display: none;">
                                <ul class="tips-list">
                                    <li>• ${this.audioCore.strings.tip1 || 'Speak clearly and at normal pace'}</li>
                                    <li>• ${this.audioCore.strings.tip2 || 'Minimize background noise'}</li>
                                    <li>• ${this.audioCore.strings.tip3 || 'You can interrupt and restart anytime'}</li>
                                    <li>• ${this.audioCore.strings.tip4 || 'Try voice commands like "clear chat"'}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal
            $('.ai-chatbot-voice-modal').remove();
            
            // Add new modal to body
            $('body').append(modalHtml);
            this.$voiceModal = $('.ai-chatbot-voice-modal');
        },

        /**
         * Bind UI and interaction events
         */
        bindEvents: function() {
            const self = this;

            // Voice button click
            $(document).on('click', '.voice-btn, .voice-input-btn', function(e) {
                e.preventDefault();
                self.toggleVoiceInput();
            });

            // Modal controls
            $(document).on('click', '.voice-modal-close, .voice-modal-overlay', function() {
                self.hideVoiceModal();
                self.stopListening();
            });

            $(document).on('click', '.voice-control-btn', function() {
                const action = $(this).data('action');
                self.handleVoiceControlAction(action);
            });

            // Tips toggle
            $(document).on('click', '.tips-toggle', function() {
                const $tipsContent = $(this).siblings('.tips-content');
                const $arrow = $(this).find('.tips-arrow');
                
                $tipsContent.slideToggle(200);
                $arrow.text($tipsContent.is(':visible') ? '▲' : '▼');
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (self.isEnabled) {
                    self.handleKeyboardShortcuts(e);
                }
            });

            // Integration with main chatbot
            this.audioCore.on('audioCore:chatbotMessageSent', function(data) {
                self.handleChatbotMessageSent(data);
            });

            // Audio level monitoring (if supported)
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                this.setupAudioLevelMonitoring();
            }
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + Shift + V for voice input
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'V') {
                e.preventDefault();
                this.toggleVoiceInput();
            }
            
            // Escape to stop listening
            if (e.key === 'Escape' && this.isListening) {
                e.preventDefault();
                this.stopListening();
            }
        },

        /**
         * Setup audio level monitoring
         */
        setupAudioLevelMonitoring: function() {
            this.audioLevelMonitor = {
                stream: null,
                analyser: null,
                dataArray: null,
                animationFrame: null
            };
        },

        /**
         * Start audio level monitoring
         */
        startAudioLevelMonitoring: function() {
            if (!navigator.mediaDevices || !this.audioLevelMonitor) return;

            const self = this;

            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(function(stream) {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const analyser = audioContext.createAnalyser();
                    const source = audioContext.createMediaStreamSource(stream);
                    
                    source.connect(analyser);
                    analyser.fftSize = 256;
                    
                    self.audioLevelMonitor.stream = stream;
                    self.audioLevelMonitor.analyser = analyser;
                    self.audioLevelMonitor.dataArray = new Uint8Array(analyser.frequencyBinCount);
                    
                    self.updateAudioLevelVisualization();
                })
                .catch(function(error) {
                    console.warn('Could not access microphone for level monitoring:', error);
                });
        },

        /**
         * Update audio level visualization
         */
        updateAudioLevelVisualization: function() {
            if (!this.audioLevelMonitor.analyser || !this.isListening) {
                return;
            }

            const analyser = this.audioLevelMonitor.analyser;
            const dataArray = this.audioLevelMonitor.dataArray;
            
            analyser.getByteFrequencyData(dataArray);
            
            // Calculate average level
            let sum = 0;
            for (let i = 0; i < dataArray.length; i++) {
                sum += dataArray[i];
            }
            const averageLevel = sum / dataArray.length;
            
            // Update level indicators
            this.updateLevelIndicators(averageLevel);
            
            // Continue animation
            this.audioLevelMonitor.animationFrame = requestAnimationFrame(() => {
                this.updateAudioLevelVisualization();
            });
        },

        /**
         * Update level indicators
         */
        updateLevelIndicators: function(level) {
            // Update button level indicator
            const $buttonLevels = this.$voiceButton.find('.level-bar');
            const buttonLevelCount = Math.floor((level / 255) * $buttonLevels.length);
            
            $buttonLevels.each(function(index) {
                $(this).toggleClass('active', index < buttonLevelCount);
            });

            // Update modal level visualization
            const $modalLevels = this.$voiceModal.find('.level-bars .level-bar');
            const modalLevelCount = Math.floor((level / 255) * $modalLevels.length);
            
            $modalLevels.each(function(index) {
                $(this).toggleClass('active', index < modalLevelCount);
            });
        },

        /**
         * Toggle voice input
         */
        toggleVoiceInput: function() {
            if (this.isListening) {
                this.stopListening();
            } else {
                this.startListening();
            }
        },

        /**
         * Start listening
         */
        startListening: function() {
            if (!this.isEnabled || !this.recognition || this.isListening) {
                return;
            }

            console.log('Starting voice input...');

            try {
                // Reset transcripts
                this.currentTranscript = '';
                this.finalTranscript = '';
                this.interimTranscript = '';
                
                // Start recognition
                this.recognition.start();
                
                // Update session stats
                this.sessionStats.startTime = Date.now();
                this.sessionStats.totalInputs++;

            } catch (error) {
                console.error('Failed to start voice recognition:', error);
                this.audioCore.handleAudioError(error);
            }
        },

        /**
         * Stop listening
         */
        stopListening: function() {
            if (!this.isListening || !this.recognition) {
                return;
            }

            console.log('Stopping voice input...');

            try {
                this.recognition.stop();
            } catch (error) {
                console.error('Error stopping voice recognition:', error);
            }

            this.stopAudioLevelMonitoring();
        },

        /**
         * Stop audio level monitoring
         */
        stopAudioLevelMonitoring: function() {
            if (this.audioLevelMonitor) {
                if (this.audioLevelMonitor.animationFrame) {
                    cancelAnimationFrame(this.audioLevelMonitor.animationFrame);
                }
                
                if (this.audioLevelMonitor.stream) {
                    this.audioLevelMonitor.stream.getTracks().forEach(track => track.stop());
                }
                
                this.audioLevelMonitor.stream = null;
                this.audioLevelMonitor.analyser = null;
            }
        },

        /**
         * Handle speech recognition events
         */
        handleRecognitionStart: function() {
            console.log('Voice recognition started');
            
            this.isListening = true;
            this.updateUIState();
            this.showVoiceModal();
            this.startAudioLevelMonitoring();
            
            // Update modal status
            this.updateModalStatus('listening', this.audioCore.strings.listeningStart || 'Listening...');
            
            this.audioCore.trigger('voiceInput:started');
        },

        handleRecognitionResult: function(event) {
            let interimTranscript = '';
            let finalTranscript = '';

            // Process all results
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                const transcript = result[0].transcript;
                const confidence = result[0].confidence;

                if (result.isFinal) {
                    finalTranscript += transcript;
                    this.updateConfidenceDisplay(confidence);
                    this.sessionStats.averageConfidence = 
                        (this.sessionStats.averageConfidence + confidence) / 2;
                } else {
                    interimTranscript += transcript;
                }
            }

            // Update transcripts
            this.finalTranscript += finalTranscript;
            this.interimTranscript = interimTranscript;
            this.currentTranscript = this.finalTranscript + this.interimTranscript;

            // Update display
            this.updateTranscriptDisplay();

            // If we have final results, show send button
            if (finalTranscript) {
                this.showSendButton();
            }

            this.audioCore.trigger('voiceInput:result', {
                transcript: this.currentTranscript,
                final: finalTranscript,
                interim: interimTranscript,
                confidence: event.results[event.results.length - 1][0].confidence
            });
        },

        handleRecognitionError: function(event) {
            console.error('Voice recognition error:', event.error);
            
            this.isListening = false;
            this.updateUIState();
            
            let errorMessage = '';
            switch (event.error) {
                case 'not-allowed':
                case 'permission-denied':
                    errorMessage = this.audioCore.strings.micPermissionDenied || 'Microphone permission denied';
                    this.isEnabled = false;
                    break;
                case 'no-speech':
                    errorMessage = this.audioCore.strings.noSpeechDetected || 'No speech detected';
                    break;
                case 'audio-capture':
                    errorMessage = this.audioCore.strings.audioCaptureFailed || 'Microphone not available';
                    break;
                case 'network':
                    errorMessage = this.audioCore.strings.networkError || 'Network error occurred';
                    break;
                default:
                    errorMessage = this.audioCore.strings.voiceRecognitionError || 'Voice recognition error occurred';
            }

            this.updateModalStatus('error', errorMessage);
            
            // Auto-hide modal after error
            setTimeout(() => {
                this.hideVoiceModal();
            }, 3000);

            this.audioCore.handleAudioError(new Error(`Voice recognition error: ${event.error}`));
        },

        handleRecognitionEnd: function() {
            console.log('Voice recognition ended');
            
            this.isListening = false;
            this.updateUIState();
            this.stopAudioLevelMonitoring();
            
            // If we have a transcript, process it
            if (this.finalTranscript.trim()) {
                this.processVoiceInput(this.finalTranscript);
            } else {
                this.hideVoiceModal();
            }

            this.audioCore.trigger('voiceInput:ended');
        },

        handleSoundStart: function() {
            this.updateModalStatus('sound_detected', this.audioCore.strings.soundDetected || 'Sound detected...');
        },

        handleSoundEnd: function() {
            this.updateModalStatus('sound_ended', this.audioCore.strings.soundEnded || 'Processing...');
        },

        handleSpeechStart: function() {
            this.updateModalStatus('speech_detected', this.audioCore.strings.speechDetected || 'Speech detected...');
        },

        handleSpeechEnd: function() {
            this.updateModalStatus('speech_ended', this.audioCore.strings.speechEnded || 'Speech ended...');
        },

        handleNoMatch: function() {
            this.updateModalStatus('no_match', this.audioCore.strings.noMatch || 'No speech recognized');
        },

        /**
         * Process voice input
         */
        processVoiceInput: function(transcript) {
            console.log('Processing voice input:', transcript);

            // Send to server for processing
            this.sendVoiceInputToServer(transcript);
        },

        /**
         * Send voice input to server for processing
         */
        sendVoiceInputToServer: function(transcript) {
            const self = this;
            
            $.ajax({
                url: window.aiChatbotProAudio?.ajaxUrl || '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ai_chatbot_voice_process',
                    nonce: window.aiChatbotProAudio?.nonce,
                    transcript: transcript,
                    confidence: this.sessionStats.averageConfidence,
                    language: this.config.language,
                    session_stats: this.sessionStats
                },
                success: function(response) {
                    if (response.success) {
                        self.handleServerResponse(response.data);
                    } else {
                        console.error('Server error processing voice input:', response.data);
                        self.audioCore.handleAudioError(new Error(response.data.message || 'Server processing error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error processing voice input:', error);
                    self.audioCore.handleAudioError(new Error('Network error processing voice input'));
                },
                complete: function() {
                    self.hideVoiceModal();
                }
            });
        },

        /**
         * Handle server response
         */
        handleServerResponse: function(data) {
            if (data.type === 'command') {
                // Voice command was executed
                this.handleVoiceCommand(data);
            } else {
                // Regular message processing
                this.handleRegularMessage(data);
            }
        },

        /**
         * Handle voice command
         */
        handleVoiceCommand: function(data) {
            console.log('Voice command executed:', data.command);
            
            // Show command execution feedback
            this.audioCore.showUserNotification(
                data.execution_result.message || 'Voice command executed',
                'success'
            );

            // Execute command action if needed
            if (data.execution_result.action) {
                this.executeCommandAction(data.execution_result);
            }
        },

        /**
         * Handle regular message
         */
        handleRegularMessage: function(data) {
            // Insert processed text into chat input
            const $chatInput = $('#chatbot-input, .ai-chatbot-input, .popup-chatbot-input, .inline-chatbot-input').first();
            
            if ($chatInput.length) {
                $chatInput.val(data.processed_text).trigger('input');
                
                // Auto-send if configured
                if (this.config.auto_send_after_voice) {
                    $chatInput.closest('form').find('.send-btn, .popup-send-btn, .inline-send-btn').click();
                }
            }
        },

        /**
         * Execute command action
         */
        executeCommandAction: function(commandResult) {
            switch (commandResult.action) {
                case 'clear_chat':
                    if (window.AIChatbot && window.AIChatbot.clearChat) {
                        window.AIChatbot.clearChat();
                    }
                    break;
                    
                case 'repeat_last':
                    if (this.audioCore.getModule('textToSpeech')) {
                        this.audioCore.getModule('textToSpeech').repeatLastResponse();
                    }
                    break;
                    
                case 'stop_speaking':
                    if (this.audioCore.getModule('textToSpeech')) {
                        this.audioCore.getModule('textToSpeech').stopSpeaking();
                    }
                    break;
                    
                case 'toggle_audio_mode':
                    if (this.audioCore.getModule('audioMode')) {
                        this.audioCore.getModule('audioMode').toggle();
                    }
                    break;
                    
                case 'adjust_speech_rate':
                    if (this.audioCore.getModule('textToSpeech')) {
                        this.audioCore.getModule('textToSpeech').setRate(commandResult.rate);
                    }
                    break;
                    
                case 'adjust_volume':
                    if (this.audioCore.getModule('textToSpeech')) {
                        this.audioCore.getModule('textToSpeech').setVolume(commandResult.volume);
                    }
                    break;
            }
        },

        /**
         * Handle voice control actions
         */
        handleVoiceControlAction: function(action) {
            switch (action) {
                case 'stop':
                    this.stopListening();
                    break;
                case 'cancel':
                    this.stopListening();
                    this.hideVoiceModal();
                    break;
                case 'send':
                    if (this.finalTranscript.trim()) {
                        this.processVoiceInput(this.finalTranscript);
                    }
                    break;
            }
        },

        /**
         * Handle chatbot message sent
         */
        handleChatbotMessageSent: function(data) {
            // Update statistics
            this.sessionStats.successfulRecognitions++;
        },

        /**
         * UI Update Methods
         */
        updateUIState: function() {
            if (!this.$voiceButton.length) return;

            this.$voiceButton
                .toggleClass('listening', this.isListening)
                .toggleClass('disabled', !this.isEnabled)
                .attr('title', this.getButtonTooltip());

            // Update status indicator
            const $statusIndicator = this.$voiceButton.find('.voice-status-indicator');
            $statusIndicator
                .removeClass('idle listening processing error')
                .addClass(this.getButtonState());
        },

        getButtonState: function() {
            if (!this.isEnabled) return 'disabled';
            if (this.isListening) return 'listening';
            return 'idle';
        },

        getButtonTooltip: function() {
            if (!this.isEnabled) {
                return this.audioCore.strings.voiceDisabled || 'Voice input disabled';
            }
            if (this.isListening) {
                return this.audioCore.strings.stopListening || 'Click to stop listening';
            }
            return this.audioCore.strings.startListening || 'Click to start voice input';
        },

        showVoiceModal: function() {
            if (!this.$voiceModal.length) return;

            this.$voiceModal.fadeIn(200);
            $('body').addClass('voice-modal-open');
        },

        hideVoiceModal: function() {
            if (!this.$voiceModal.length) return;

            this.$voiceModal.fadeOut(200);
            $('body').removeClass('voice-modal-open');
            
            // Reset modal state
            this.resetModalState();
        },

        resetModalState: function() {
            this.updateTranscriptDisplay();
            this.hideSendButton();
            this.updateConfidenceDisplay(0);
        },

        updateModalStatus: function(status, message) {
            if (!this.$voiceModal.length) return;

            this.$voiceModal.find('.status-text').text(message);
            this.$voiceModal.find('.voice-modal-content')
                .removeClass('status-listening status-error status-processing')
                .addClass(`status-${status}`);
        },

        updateTranscriptDisplay: function() {
            if (!this.$voiceModal.length) return;

            this.$voiceModal.find('.final-transcript').text(this.finalTranscript);
            this.$voiceModal.find('.interim-transcript').text(this.interimTranscript);
        },

        updateConfidenceDisplay: function(confidence) {
            if (!this.$voiceModal.length) return;

            const percentage = Math.round(confidence * 100);
            this.$voiceModal.find('.confidence-indicator .value').text(percentage + '%');
        },

        showSendButton: function() {
            this.$voiceModal.find('.send-btn').show();
        },

        hideSendButton: function() {
            this.$voiceModal.find('.send-btn').hide();
        },

        /**
         * User preferences
         */
        loadUserPreferences: function() {
            const preferences = this.getUserPreferences();
            
            if (preferences.language) {
                this.config.language = preferences.language;
                if (this.recognition) {
                    this.recognition.lang = preferences.language;
                }
            }
        },

        getUserPreferences: function() {
            try {
                return JSON.parse(localStorage.getItem('aiChatbotVoicePreferences') || '{}');
            } catch (error) {
                return {};
            }
        },

        saveUserPreferences: function(preferences) {
            try {
                const current = this.getUserPreferences();
                const updated = Object.assign(current, preferences);
                localStorage.setItem('aiChatbotVoicePreferences', JSON.stringify(updated));
            } catch (error) {
                console.warn('Could not save voice preferences:', error);
            }
        },

        /**
         * Module control methods
         */
        enable: function() {
            this.isEnabled = true;
            this.updateUIState();
        },

        disable: function() {
            this.isEnabled = false;
            if (this.isListening) {
                this.stopListening();
            }
            this.updateUIState();
        },

        pause: function() {
            if (this.isListening) {
                this.stopListening();
            }
        },

        resume: function() {
            // Only resume if user was previously in the middle of input
            // Generally, voice input shouldn't auto-resume
        },

        cleanup: function() {
            this.stopListening();
            this.stopAudioLevelMonitoring();
            
            if (this.$voiceModal) {
                this.$voiceModal.remove();
            }
            
            // Clear event handlers
            $(document).off('.voiceInput');
        }
    };

})(jQuery);