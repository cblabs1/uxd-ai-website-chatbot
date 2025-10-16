/**
 * AI Chatbot Pro Text-to-Speech Frontend
 * Advanced TTS functionality for frontend
 * 
 * @package AI_Website_Chatbot
 * @subpackage Pro\Audio\Frontend
 * @since 1.0.0
 */

(function($) {
    'use strict';

    window.AIChatbotAudioTextToSpeech = {
        // Core properties
        audioCore: null,
        config: {},
        
        // Speech synthesis
        synthesis: null,
        voices: [],
        currentUtterance: null,
        isSpeaking: false,
        isPaused: false,
        
        // Queue management
        speechQueue: [],
        isProcessingQueue: false,
        
        // UI elements
        $speechControls: null,
        $speechIndicator: null,
        
        // User preferences
        userPreferences: {},
        
        // Statistics
        speechStats: {
            totalSpeeches: 0,
            totalDuration: 0,
            averageRate: 1.0,
            preferredVoice: null,
            lastSpeechTime: null
        },

        /**
         * Initialize Text-to-Speech module
         */
        init: function(audioCore) {
            this.audioCore = audioCore;
            this.config = audioCore.config.text_to_speech || {};
            
            console.log('Initializing Text-to-Speech...');

            if (!this.isTTSSupported()) {
                console.warn('Text-to-Speech not supported in this browser');
                return false;
            }

            if (window.aiChatbotTTS && window.aiChatbotTTS.config) {
                this.config = Object.assign(this.config, window.aiChatbotTTS.config);
                
                // Add voice selection config if available
                if (window.aiChatbotAudio && window.aiChatbotAudio.voice_selection) {
                    this.config.voice_selection = window.aiChatbotAudio.voice_selection;
                }
            }

            this.setupSpeechSynthesis();
            this.loadVoices();
            this.setupUI();
            this.bindEvents();
            this.loadUserPreferences();

            console.log('Text-to-Speech initialized successfully');
            return true;
        },

        /**
         * Check if TTS is supported
         */
        isTTSSupported: function() {
            return !!(window.speechSynthesis && window.SpeechSynthesisUtterance);
        },

        /**
         * Setup speech synthesis
         */
        setupSpeechSynthesis: function() {
            this.synthesis = window.speechSynthesis;
            
            // Handle synthesis events
            this.synthesis.addEventListener('voiceschanged', () => {
                this.loadVoices();
            });
        },

        /**
         * Load available voices
         */
        loadVoices: function() {
            this.voices = this.synthesis.getVoices();
            
            if (this.voices.length === 0) {
                // Voices might not be loaded yet, try again after a delay
                setTimeout(() => {
                    this.voices = this.synthesis.getVoices();
                    this.selectPreferredVoice();
                }, 100);
            } else {
                this.selectPreferredVoice();
            }

            this.audioCore.debug('Available voices:', this.voices.length);
        },

        /**
         * Select preferred voice
         */
        selectPreferredVoice: function() {
            if (this.voices.length === 0) return null;

            let selectedVoice = null;

            // Try configured voice
            if (!selectedVoice && this.config.voice_name) {
                selectedVoice = this.voices.find(voice => voice.name === this.config.voice_name);
            }

            // Try user preference first
            if (this.userPreferences.voiceName) {
                selectedVoice = this.voices.find(voice => voice.name === this.userPreferences.voiceName);
            }

            // Try language-specific voice
            if (!selectedVoice) {
                const language = this.config.language || 'en-US';
                selectedVoice = this.voices.find(voice => voice.lang === language);
            }

            // Try language family (e.g., en-* for en-US)
            if (!selectedVoice) {
                const langFamily = (this.config.language || 'en-US').split('-')[0];
                selectedVoice = this.voices.find(voice => voice.lang.startsWith(langFamily));
            }

            // Default to first available voice
            if (!selectedVoice) {
                selectedVoice = this.voices[0];
            }

            this.preferredVoice = selectedVoice;
            this.audioCore.debug('Selected voice:', selectedVoice?.name);
            
            return selectedVoice;
        },

        /**
         * Setup UI elements
         */
        setupUI: function() {
            this.createSpeechControls();
            this.createSpeechIndicator();
            this.updateUI();
        },

        /**
         * Create speech controls
         */
        createSpeechControls: function() {
            const controlsHtml = `
                <div class="ai-chatbot-speech-controls" style="display: none;">
                    <div class="speech-controls-header">
                        <span class="speech-controls-title">${this.audioCore.strings.speechControls || 'Speech Controls'}</span>
                        <button class="speech-controls-toggle" aria-label="Toggle speech controls">
                            <span class="dashicons dashicons-controls-volumeon"></span>
                        </button>
                    </div>
                    <div class="speech-controls-body">
                        <div class="speech-control-group">
                            <div class="speech-action-buttons">
                                <button class="speech-action-btn test-speech-btn">
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <span>${this.audioCore.strings.testSpeech || 'Test Speech'}</span>
                                </button>
                                <button class="speech-action-btn stop-speech-btn" style="display: none;">
                                    <span class="dashicons dashicons-controls-pause"></span>
                                    <span>${this.audioCore.strings.stopSpeech || 'Stop Speech'}</span>
                                </button>
                                <button class="speech-action-btn repeat-speech-btn">
                                    <span class="dashicons dashicons-controls-repeat"></span>
                                    <span>${this.audioCore.strings.repeatLast || 'Repeat Last'}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Find appropriate container
            const $container = $('.ai-chatbot-widget, .ai-chatbot-popup, .ai-chatbot-inline').first();
            if ($container.length) {
                $container.find('.ai-chatbot-input-container, .popup-input-container, .inline-input-container')
                    .before(controlsHtml);
                this.$speechControls = $container.find('.ai-chatbot-speech-controls');
            }

            // Populate voice selection dropdown
            this.populateVoiceSelection();
        },

        /**
         * Populate voice selection dropdown
         */
        populateVoiceSelection: function() {
            const $voiceSelect = this.$speechControls?.find('.voice-selection');
            if (!$voiceSelect?.length) return;

            $voiceSelect.empty().append('<option value="">' + (this.audioCore.strings.defaultVoice || 'Default Voice') + '</option>');

            // Group voices by language
            const voicesByLang = {};
            this.voices.forEach(voice => {
                if (!voicesByLang[voice.lang]) {
                    voicesByLang[voice.lang] = [];
                }
                voicesByLang[voice.lang].push(voice);
            });

            // Add voices grouped by language
            Object.keys(voicesByLang).sort().forEach(lang => {
                const $optgroup = $(`<optgroup label="${lang}"></optgroup>`);
                
                voicesByLang[lang].forEach(voice => {
                    const $option = $(`<option value="${voice.name}">${voice.name}</option>`);
                    if (voice === this.preferredVoice) {
                        $option.prop('selected', true);
                    }
                    $optgroup.append($option);
                });
                
                $voiceSelect.append($optgroup);
            });
        },

        /**
         * Create speech indicator
         */
        createSpeechIndicator: function() {
            const indicatorHtml = `
                <div class="ai-chatbot-speech-indicator" style="display: none;">
                    <div class="speech-indicator-content">
                        <div class="speech-wave">
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <div class="speech-info">
                            <span class="speech-text">${this.audioCore.strings.aiSpeaking || 'AI is speaking...'}</span>
                            <div class="speech-progress">
                                <div class="progress-bar"></div>
                            </div>
                        </div>
                        <div class="speech-controls">
                            <button class="speech-control-btn pause-resume-btn" data-action="pause">
                                <span class="dashicons dashicons-controls-pause"></span>
                            </button>
                            <button class="speech-control-btn stop-btn" data-action="stop">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Add to messages container
            const $messagesContainer = $('.ai-chatbot-messages, .popup-messages-container, .inline-messages-container').first();
            if ($messagesContainer.length) {
                $messagesContainer.prepend(indicatorHtml);
                this.$speechIndicator = $messagesContainer.find('.ai-chatbot-speech-indicator');
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Speech controls
            $(document).on('change', '.tts-enabled-toggle', function() {
                self.toggleTTS($(this).is(':checked'));
            });

            $(document).on('change', '.auto-play-toggle', function() {
                self.toggleAutoPlay($(this).is(':checked'));
            });

            $(document).on('input', '.speech-rate-slider', function() {
                const rate = parseFloat($(this).val());
                self.setRate(rate);
                $(this).siblings('.speech-control-label').find('.rate-display').text(rate);
            });

            $(document).on('input', '.speech-volume-slider', function() {
                const volume = parseFloat($(this).val());
                self.setVolume(volume);
                $(this).siblings('.speech-control-label').find('.volume-display').text(Math.round(volume * 100) + '%');
            });

            $(document).on('change', '.voice-selection', function() {
                const voiceName = $(this).val();
                self.setVoice(voiceName);
            });

            // Speech action buttons
            $(document).on('click', '.test-speech-btn', function() {
                self.testSpeech();
            });

            $(document).on('click', '.stop-speech-btn', function() {
                self.stopSpeaking();
            });

            $(document).on('click', '.repeat-speech-btn', function() {
                self.repeatLastSpeech();
            });

            // Speech indicator controls
            $(document).on('click', '.speech-control-btn', function() {
                const action = $(this).data('action');
                self.handleSpeechControlAction(action);
            });

            // Controls toggle
            $(document).on('click', '.speech-controls-toggle', function() {
                const $body = $(this).closest('.ai-chatbot-speech-controls').find('.speech-controls-body');
                $body.slideToggle(200);
            });

            // Integration with chatbot responses
            this.audioCore.on('audioCore:chatbotResponseReceived', function(data) {
                self.handleResponse(data);
            });

            // Handle browser speech synthesis events
            if (this.synthesis) {
                // Some browsers fire events on the synthesis object
                this.synthesis.addEventListener('start', function() {
                    self.handleSynthesisStart();
                });

                this.synthesis.addEventListener('end', function() {
                    self.handleSynthesisEnd();
                });
            }
        },

        /**
         * Handle chatbot response
         */
        handleResponse: function(data) {
            if (!this.config.enabled || !data.tts) {
                return;
            }

            // Check if voice selection should override admin settings
            let shouldSpeak = data.tts.should_speak;
            let voiceSettings = data.tts.voice_settings || {};
            
            // If user has voice selection preferences, respect them
            if (window.simpleVoiceSelection) {
                const userPrefs = window.simpleVoiceSelection.loadUserPreferences();
                
                // Override voice settings with user preferences
                if (userPrefs.gender || userPrefs.language || userPrefs.specificVoice) {
                    voiceSettings = this.mergeVoiceSettings(voiceSettings, userPrefs);
                }
            }

            if (shouldSpeak) {
                this.speak(data.tts.speech_text, voiceSettings);
            }
        },

        /**
         * Fallback voice selection when SimpleVoiceSelection isn't available
         */
        selectVoiceFromAdminSettings: function(utterance) {
            if (!this.config.voice_selection || !this.config.voice_selection.enabled) {
                return utterance;
            }
            
            const adminDefaults = this.config.voice_selection.admin_defaults;
            const voices = this.synthesis.getVoices();
            
            if (voices.length === 0) {
                return utterance;
            }
            
            // Use the findBestVoice method we added earlier
            const bestVoice = this.findBestVoice(voices, {
                gender: adminDefaults.gender,
                language: adminDefaults.language,
                specificVoice: adminDefaults.specific_voice
            });
            
            if (bestVoice) {
                utterance.voice = bestVoice;
            }
            
            return utterance;
        },

        /**
         * Main speak method
         */
        speak: function(text, settings = {}) {
            if (!this.isTTSSupported() || !text?.trim()) {
                return false;
            }

            // Stop any current speech
            this.stopSpeaking();

            // Create utterance
            const utterance = new SpeechSynthesisUtterance(text);

            // PRIORITY 1: Check if user has voice selection preferences (highest priority)
            let voiceApplied = false;
            if (window.simpleVoiceSelection && typeof window.simpleVoiceSelection.applyUserPreferences === 'function') {
                console.log('Applying voice selection preferences...');
                window.simpleVoiceSelection.applyUserPreferences(utterance);
                voiceApplied = true;
            } 
            // PRIORITY 2: Fall back to admin voice selection settings
            else if (this.config.voice_selection && this.config.voice_selection.enabled) {
                console.log('Applying admin voice selection defaults...');
                this.applyVoiceSelectionSettings(utterance, settings);
                voiceApplied = true;
            }

            // PRIORITY 3: Use legacy TTS settings if no voice selection
            if (!voiceApplied) {
                console.log('Using legacy TTS settings...');
                // Apply rate, pitch, volume from config or settings
                utterance.rate = settings.rate || this.userPreferences.rate || this.config.rate || 1.0;
                utterance.pitch = settings.pitch || this.userPreferences.pitch || this.config.pitch || 1.0;
                utterance.volume = settings.volume || this.userPreferences.volume || this.config.volume || 0.8;

                // Try to set voice from config
                if (this.config.voice_name && this.voices.length > 0) {
                    const voice = this.voices.find(v => v.name === this.config.voice_name);
                    if (voice) utterance.voice = voice;
                }
            }

            // Store current utterance
            this.currentUtterance = utterance;
            this.lastSpeechText = text;

            // Set up event handlers
            utterance.onstart = () => {
                this.isSpeaking = true;
                this.isPaused = false;
                this.updateSpeechIndicator('speaking');
                console.log('Speech started');
            };

            utterance.onend = () => {
                this.isSpeaking = false;
                this.isPaused = false;
                this.currentUtterance = null;
                this.updateSpeechIndicator('idle');
                console.log('Speech ended');
            };

            utterance.onerror = (event) => {
                console.error('Speech error:', event.error);
                this.isSpeaking = false;
                this.isPaused = false;
                this.currentUtterance = null;
                this.updateSpeechIndicator('error');
            };

            utterance.onpause = () => {
                this.isPaused = true;
                this.updateSpeechIndicator('paused');
            };

            utterance.onresume = () => {
                this.isPaused = false;
                this.updateSpeechIndicator('speaking');
            };

            // Speak the utterance
            console.log('Speaking text with settings:', {
                voice: utterance.voice?.name,
                rate: utterance.rate,
                pitch: utterance.pitch,
                volume: utterance.volume
            });
            
            this.synthesis.speak(utterance);
            
            return true;
        },

        /**
         * Apply voice selection settings from admin configuration - UPDATED METHOD
         */
        applyVoiceSelectionSettings: function(utterance, settings) {
            const adminVoiceSettings = this.config.voice_selection || {};
            
            if (!adminVoiceSettings.enabled || !adminVoiceSettings.admin_defaults) {
                return utterance;
            }
            
            const defaults = adminVoiceSettings.admin_defaults;
            
            // Apply rate, pitch, volume from admin defaults
            if (defaults.rate) utterance.rate = defaults.rate;
            if (defaults.pitch) utterance.pitch = defaults.pitch;
            if (defaults.volume) utterance.volume = defaults.volume;
            
            // Find and apply best voice based on admin settings
            const voices = this.synthesis.getVoices();
            if (voices.length === 0) {
                return utterance;
            }
            
            const bestVoice = this.findBestVoice(voices, {
                gender: defaults.gender,
                language: defaults.language,
                specificVoice: defaults.specific_voice
            });
            
            if (bestVoice) {
                utterance.voice = bestVoice;
                console.log('Applied admin default voice:', bestVoice.name);
            }
            
            return utterance;
        },

        /**
         * Merge voice settings - NEW METHOD
         */
        mergeVoiceSettings: function(adminSettings, userPreferences) {
            return Object.assign({}, adminSettings, {
                rate: userPreferences.speed || adminSettings.rate,
                volume: userPreferences.volume || adminSettings.volume,
                // Note: voice and pitch are handled by simpleVoiceSelection.applyUserPreferences
            });
        },

        /**
         * Apply admin voice settings as fallback - NEW METHOD
         */
        applyAdminVoiceSettings: function(utterance, settings) {
            // Get admin voice selection settings
            const adminVoiceSettings = this.config.voice_selection || {};
            
            if (adminVoiceSettings.enabled && adminVoiceSettings.admin_defaults) {
                const defaults = adminVoiceSettings.admin_defaults;
                
                // Try to find best voice based on admin settings
                const voices = this.synthesis.getVoices();
                const bestVoice = this.findBestVoice(voices, {
                    gender: defaults.gender,
                    language: defaults.language,
                    specificVoice: defaults.specific_voice
                });
                
                if (bestVoice) {
                    utterance.voice = bestVoice;
                }
            }
            
            return utterance;
        },

        /**
         * Find best voice based on criteria - NEW METHOD
         */
        findBestVoice: function(voices, criteria) {
            // If specific voice is requested, find it
            if (criteria.specificVoice) {
                const specificVoice = voices.find(voice => voice.name === criteria.specificVoice);
                if (specificVoice) {
                    console.log('Found specific voice:', specificVoice.name);
                    return specificVoice;
                }
            }
            
            // Filter by language and gender
            const language = criteria.language || 'en-US';
            const gender = criteria.gender || 'female';
            const langPrefix = language.split('-')[0];
            
            const filteredVoices = voices.filter(voice => {
                // Check language match
                const matchesLanguage = voice.lang.toLowerCase().startsWith(langPrefix.toLowerCase()) ||
                                        voice.lang.toLowerCase().includes(langPrefix.toLowerCase());
                
                const voiceName = voice.name.toLowerCase();
                
                // Check gender match
                let matchesGender = true;
                if (gender === 'male') {
                    matchesGender = voiceName.includes('male') || 
                                    voiceName.includes('david') || 
                                    voiceName.includes('mark') ||
                                    voiceName.includes('daniel') || 
                                    voiceName.includes('george') ||
                                    voiceName.includes('james') ||
                                    (!voiceName.includes('female') && 
                                    !voiceName.includes('zira') && 
                                    !voiceName.includes('susan'));
                } else if (gender === 'female') {
                    matchesGender = voiceName.includes('female') || 
                                    voiceName.includes('zira') || 
                                    voiceName.includes('susan') || 
                                    voiceName.includes('helen') ||
                                    voiceName.includes('samantha') || 
                                    voiceName.includes('karen') ||
                                    voiceName.includes('moira') ||
                                    voiceName.includes('tessa') ||
                                    voiceName.includes('fiona');
                }
                
                return matchesLanguage && matchesGender;
            });
            
            // Return first filtered voice or first available voice
            if (filteredVoices.length > 0) {
                console.log('Found matching voice:', filteredVoices[0].name, 'from', filteredVoices.length, 'options');
                return filteredVoices[0];
            }
            
            // Fallback to first voice matching language only
            const langMatches = voices.filter(voice => 
                voice.lang.toLowerCase().startsWith(langPrefix.toLowerCase())
            );
            
            if (langMatches.length > 0) {
                console.log('Using language-only match:', langMatches[0].name);
                return langMatches[0];
            }
            
            // Ultimate fallback
            console.log('Using fallback voice:', voices[0]?.name);
            return voices[0];
        },
        /**
         * Speak single utterance
         */
        speakSingle: function(text, settings) {
            // Stop current speech if any
            this.stopSpeaking();

            const utterance = new SpeechSynthesisUtterance(text);
            
            // Apply settings
            utterance.rate = settings.rate;
            utterance.pitch = settings.pitch;
            utterance.volume = settings.volume;
            utterance.lang = settings.language;
            
            if (settings.voice) {
                utterance.voice = settings.voice;
            }

            // Bind events
            this.bindUtteranceEvents(utterance, text);

            // Store current utterance
            this.currentUtterance = utterance;

            // Start speaking
            this.synthesis.speak(utterance);
            
            return true;
        },

        /**
         * Speak text in chunks for long content
         */
        speakInChunks: function(text, settings) {
            const chunks = this.splitIntoChunks(text, this.config.chunk_size || 200);
            
            // Clear existing queue
            this.speechQueue = [];
            
            // Add chunks to queue
            chunks.forEach((chunk, index) => {
                this.speechQueue.push({
                    text: chunk,
                    settings: settings,
                    isLast: index === chunks.length - 1
                });
            });

            // Start processing queue
            this.processNextInQueue();
            
            return true;
        },

        /**
         * Split text into speech-friendly chunks
         */
        splitIntoChunks: function(text, maxLength) {
            const sentences = text.split(/[.!?]+/).filter(s => s.trim());
            const chunks = [];
            let currentChunk = '';

            sentences.forEach(sentence => {
                const trimmedSentence = sentence.trim();
                if (!trimmedSentence) return;

                if (currentChunk.length + trimmedSentence.length <= maxLength) {
                    currentChunk += (currentChunk ? '. ' : '') + trimmedSentence;
                } else {
                    if (currentChunk) {
                        chunks.push(currentChunk + '.');
                    }
                    currentChunk = trimmedSentence;
                }
            });

            if (currentChunk) {
                chunks.push(currentChunk + '.');
            }

            return chunks.length > 0 ? chunks : [text];
        },

        /**
         * Process next item in speech queue
         */
        processNextInQueue: function() {
            if (this.speechQueue.length === 0 || this.isProcessingQueue) {
                this.isProcessingQueue = false;
                return;
            }

            this.isProcessingQueue = true;
            const nextItem = this.speechQueue.shift();
            
            const utterance = new SpeechSynthesisUtterance(nextItem.text);
            
            // Apply settings
            Object.assign(utterance, nextItem.settings);
            
            if (nextItem.settings.voice) {
                utterance.voice = nextItem.settings.voice;
            }

            // Handle end of this chunk
            utterance.onend = () => {
                if (nextItem.isLast) {
                    this.handleSpeechComplete();
                } else {
                    // Small pause between chunks
                    setTimeout(() => {
                        this.processNextInQueue();
                    }, this.config.chunk_pause || 500);
                }
            };

            utterance.onerror = (event) => {
                console.error('TTS error in chunk:', event.error);
                this.processNextInQueue(); // Continue with next chunk
            };

            this.currentUtterance = utterance;
            this.synthesis.speak(utterance);
        },

        /**
         * Bind utterance events
         */
        bindUtteranceEvents: function(utterance, originalText) {
            const self = this;

            utterance.onstart = function() {
                self.handleSpeechStart(originalText);
            };

            utterance.onend = function() {
                self.handleSpeechEnd();
            };

            utterance.onpause = function() {
                self.handleSpeechPause();
            };

            utterance.onresume = function() {
                self.handleSpeechResume();
            };

            utterance.onerror = function(event) {
                self.handleSpeechError(event);
            };

            utterance.onboundary = function(event) {
                self.handleSpeechBoundary(event);
            };

            utterance.onmark = function(event) {
                self.handleSpeechMark(event);
            };
        },

        /**
         * Speech event handlers
         */
        handleSpeechStart: function(text) {
            this.isSpeaking = true;
            this.isPaused = false;
            this.updateUI();
            this.showSpeechIndicator();
            this.updateSpeechStats('start', text);
            
            this.audioCore.trigger('tts:speechStart', { text });
        },

        handleSpeechEnd: function() {
            this.isSpeaking = false;
            this.isPaused = false;
            this.currentUtterance = null;
            this.updateUI();
            this.hideSpeechIndicator();
            this.updateSpeechStats('end');
            
            this.audioCore.trigger('tts:speechEnd');
        },

        handleSpeechPause: function() {
            this.isPaused = true;
            this.updateUI();
            this.updateSpeechIndicatorState('paused');
            
            this.audioCore.trigger('tts:speechPause');
        },

        handleSpeechResume: function() {
            this.isPaused = false;
            this.updateUI();
            this.updateSpeechIndicatorState('speaking');
            
            this.audioCore.trigger('tts:speechResume');
        },

        handleSpeechError: function(event) {
            console.error('TTS Error:', event.error);
            this.isSpeaking = false;
            this.isPaused = false;
            this.currentUtterance = null;
            this.updateUI();
            this.hideSpeechIndicator();
            
            this.audioCore.handleAudioError(new Error(`TTS Error: ${event.error}`));
        },

        handleSpeechBoundary: function(event) {
            // Update progress indicator
            if (this.currentUtterance) {
                const progress = (event.charIndex / this.currentUtterance.text.length) * 100;
                this.updateSpeechProgress(progress);
            }
        },

        handleSpeechMark: function(event) {
            // Handle SSML marks if supported
            this.audioCore.debug('Speech mark:', event.name);
        },

        handleSpeechComplete: function() {
            this.isProcessingQueue = false;
            this.handleSpeechEnd();
        },

        handleSynthesisStart: function() {
            this.audioCore.debug('Speech synthesis started');
        },

        handleSynthesisEnd: function() {
            this.audioCore.debug('Speech synthesis ended');
        },

        /**
         * Speech control actions
         */
        handleSpeechControlAction: function(action) {
            switch (action) {
                case 'pause':
                    this.pauseSpeech();
                    break;
                case 'resume':
                    this.resumeSpeech();
                    break;
                case 'stop':
                    this.stopSpeaking();
                    break;
            }
        },

        pauseSpeech: function() {
            if (this.synthesis && this.isSpeaking && !this.isPaused) {
                this.synthesis.pause();
            }
        },

        resumeSpeech: function() {
            if (this.synthesis && this.isSpeaking && this.isPaused) {
                this.synthesis.resume();
            }
        },

        stopSpeaking: function() {
            if (this.synthesis) {
                this.synthesis.cancel();
                this.isSpeaking = false;
                this.isPaused = false;
                this.currentUtterance = null;
                this.updateUI();
                this.updateSpeechIndicator('idle');
            }
            
            // Clear queue
            this.speechQueue = [];
            this.isProcessingQueue = false;
            
            this.audioCore.trigger('tts:speechStopped');
        },

        /**
         * Settings control methods
         */
        toggleTTS: function(enabled) {
            this.config.enabled = enabled;
            if (!enabled) {
                this.stopSpeaking();
            }
            this.updateUI();
            this.saveUserPreferences({ enabled });
        },

        toggleAutoPlay: function(autoPlay) {
            this.config.auto_play = autoPlay;
            this.saveUserPreferences({ autoPlay });
        },

        setRate: function(rate) {
            this.userPreferences.rate = Math.max(0.1, Math.min(2.0, rate));
            this.saveUserPreferences({ rate: this.userPreferences.rate });
            
            if (this.currentUtterance) {
                this.currentUtterance.rate = this.userPreferences.rate;
            }
        },

        setVolume: function(volume) {
            this.userPreferences.volume = Math.max(0.0, Math.min(1.0, volume));
            this.saveUserPreferences({ volume: this.userPreferences.volume });
            
            if (this.currentUtterance) {
                this.currentUtterance.volume = this.userPreferences.volume;
            }
        },

        setVoice: function(voiceName) {
            if (voiceName) {
                this.preferredVoice = this.voices.find(voice => voice.name === voiceName);
            } else {
                this.preferredVoice = null;
            }
            
            this.userPreferences.voiceName = voiceName;
            this.saveUserPreferences({ voiceName });
        },

        /**
         * Test and utility methods
         */
        testSpeech: function() {
            const testText = this.audioCore.strings.testSpeechText || 
                'Hello! This is a test of the text-to-speech feature. How do I sound?';
            this.speak(testText);
        },

        repeatLastSpeech: function() {
            // Get last AI response
            const $lastResponse = $('.ai-chatbot-message-bot').last().find('.ai-chatbot-message-content').text();
            if ($lastResponse) {
                this.speak($lastResponse);
            } else {
                this.speak(this.audioCore.strings.noLastResponse || 'No previous response to repeat.');
            }
        },

        /**
         * UI update methods
         */
        updateUI: function() {
            // Update controls visibility
            if (this.$speechControls) {
                this.$speechControls.toggle(this.config.enabled);
            }

            // Update speech action buttons
            $('.test-speech-btn').toggle(!this.isSpeaking);
            $('.stop-speech-btn').toggle(this.isSpeaking);
            $('.repeat-speech-btn').toggle(!this.isSpeaking);

            // Update speech indicator controls
            const $pauseResumeBtn = this.$speechIndicator?.find('.pause-resume-btn');
            if ($pauseResumeBtn?.length) {
                if (this.isPaused) {
                    $pauseResumeBtn.attr('data-action', 'resume')
                        .find('.dashicons').removeClass('dashicons-controls-pause').addClass('dashicons-controls-play');
                } else {
                    $pauseResumeBtn.attr('data-action', 'pause')
                        .find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-controls-pause');
                }
            }
        },

        showSpeechIndicator: function() {
            if (this.$speechIndicator) {
                this.$speechIndicator.fadeIn(200);
            }
        },

        hideSpeechIndicator: function() {
            if (this.$speechIndicator) {
                this.$speechIndicator.fadeOut(200);
            }
        },

        updateSpeechIndicator: function(state) {
            const indicator = document.querySelector('.ai-chatbot-speech-indicator');
            if (!indicator) return;
            
            indicator.className = 'ai-chatbot-speech-indicator ai-chatbot-speech-' + state;
            
            const statusText = indicator.querySelector('.speech-status-text');
            if (statusText) {
                const statusTexts = {
                    'idle': '',
                    'speaking': this.config.strings?.speaking || 'Speaking...',
                    'paused': this.config.strings?.paused || 'Paused',
                    'error': this.config.strings?.error || 'Error'
                };
                statusText.textContent = statusTexts[state] || '';
            }
        },

        updateSpeechIndicatorState: function(state) {
            if (!this.$speechIndicator) return;

            this.$speechIndicator
                .removeClass('speaking paused stopped')
                .addClass(state);
        },

        updateSpeechProgress: function(percentage) {
            if (!this.$speechIndicator) return;

            this.$speechIndicator.find('.progress-bar').css('width', percentage + '%');
        },

        /**
         * User preferences management
         */
        loadUserPreferences: function() {
            if (window.aiChatbotAudio && window.aiChatbotAudio.voice_selection) {
                return window.aiChatbotAudio.voice_selection;
            }
            try {
                const stored = localStorage.getItem('aiChatbotTTSPreferences');
                this.userPreferences = stored ? JSON.parse(stored) : {};
            } catch (error) {
                this.userPreferences = {};
            }
        },

        saveUserPreferences: function(updates) {
            try {
                Object.assign(this.userPreferences, updates);
                localStorage.setItem('aiChatbotTTSPreferences', JSON.stringify(this.userPreferences));
            } catch (error) {
                console.warn('Could not save TTS preferences:', error);
            }
        },

        /**
         * Statistics tracking
         */
        updateSpeechStats: function(event, text) {
            switch (event) {
                case 'start':
                    this.speechStats.totalSpeeches++;
                    this.speechStats.lastSpeechTime = Date.now();
                    if (text) {
                        this.speechStats.lastSpeechText = text;
                    }
                    break;
                case 'end':
                    if (this.speechStats.lastSpeechTime) {
                        const duration = Date.now() - this.speechStats.lastSpeechTime;
                        this.speechStats.totalDuration += duration;
                    }
                    break;
            }
        },

        /**
         * Module control methods
         */
        enable: function() {
            this.config.enabled = true;
            this.updateUI();
        },

        disable: function() {
            this.config.enabled = false;
            this.stopSpeaking();
            this.updateUI();
        },

        pause: function() {
            this.pauseSpeech();
        },

        resume: function() {
            this.resumeSpeech();
        },

        cleanup: function() {
            this.stopSpeaking();
            
            if (this.$speechControls) {
                this.$speechControls.remove();
            }
            
            if (this.$speechIndicator) {
                this.$speechIndicator.remove();
            }
        }
    };

})(jQuery);