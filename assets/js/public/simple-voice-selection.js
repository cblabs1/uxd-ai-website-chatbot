/**
 * AI Chatbot Widget JavaScript - Simple Frontend Voice Selection
 * Add this to your existing chatbot JavaScript file or create a simple voice-selection.js
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    class SimpleVoiceSelection {
        constructor() {
            this.voices = [];
            this.selectedVoice = null;
            this.userPreferences = this.loadUserPreferences();
            this.init();
        }

        init() {
            this.loadVoices();
            this.createVoiceButton();
            this.bindEvents();
        }

        loadVoices() {
            if ('speechSynthesis' in window) {
                const updateVoices = () => {
                    this.voices = speechSynthesis.getVoices();
                    this.populateVoiceOptions();
                };
                
                updateVoices();
                speechSynthesis.onvoiceschanged = updateVoices;
            }
        }

        createVoiceButton() {
            // Add voice selection button to chatbot header
            const chatbotHeader = document.querySelector('.ai-chatbot-header .ai-chatbot-controls');
            if (chatbotHeader && !document.querySelector('.ai-chatbot-voice-btn')) {
                const voiceBtn = document.createElement('button');
                voiceBtn.className = 'ai-chatbot-voice-btn';
                voiceBtn.innerHTML = '🎤';
                voiceBtn.title = 'Voice Settings';
                voiceBtn.onclick = () => this.toggleVoiceModal();
                
                chatbotHeader.insertBefore(voiceBtn, chatbotHeader.firstChild);
            }

            // Create simple voice modal
            this.createVoiceModal();
        }

        createVoiceModal() {
            if (document.querySelector('#simple-voice-modal')) return;

            const modal = document.createElement('div');
            modal.id = 'simple-voice-modal';
            modal.className = 'simple-voice-modal';
            modal.style.display = 'none';
            
            modal.innerHTML = `
                <div class="voice-modal-content">
                    <div class="voice-modal-header">
                        <h3>🎤 Voice Settings</h3>
                        <span class="voice-modal-close">&times;</span>
                    </div>
                    <div class="voice-modal-body">
                        <div class="voice-option">
                            <label for="voice-gender">Voice Gender:</label>
                            <select id="voice-gender">
                                <option value="female">👩 Female</option>
                                <option value="male">👨 Male</option>
                                <option value="neutral">🤖 Neutral</option>
                            </select>
                        </div>
                        
                        <div class="voice-option">
                            <label for="voice-language">Language:</label>
                            <select id="voice-language">
                                <option value="en-US">🇺🇸 English (US)</option>
                                <option value="en-GB">🇬🇧 English (UK)</option>
                                <option value="en-IN">🇮🇳 English (India)</option>
                            </select>
                        </div>
                        
                        <div class="voice-option">
                            <label for="specific-voice">Specific Voice:</label>
                            <select id="specific-voice">
                                <option value="">Auto-select best voice</option>
                            </select>
                        </div>
                        
                        <div class="voice-option">
                            <label for="voice-speed">Speech Speed:</label>
                            <input type="range" id="voice-speed" min="0.5" max="2" step="0.1" value="1">
                            <span class="range-value">1.0x</span>
                        </div>
                        
                        <div class="voice-option">
                            <label for="voice-volume">Volume:</label>
                            <input type="range" id="voice-volume" min="0" max="1" step="0.1" value="0.8">
                            <span class="range-value">80%</span>
                        </div>
                        
                        <div class="voice-actions">
                            <button id="test-voice" class="voice-btn primary">🔊 Test Voice</button>
                            <button id="save-voice" class="voice-btn primary">💾 Save Settings</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            this.loadUserSettings();
        }

        populateVoiceOptions() {
            const voiceSelect = document.querySelector('#specific-voice');
            if (!voiceSelect) return;

            const currentVoice = voiceSelect.value;
            const gender = document.querySelector('#voice-gender')?.value || 'female';
            const language = document.querySelector('#voice-language')?.value || 'en';

            // Clear existing options except first
            voiceSelect.innerHTML = '<option value="">Auto-select best voice</option>';

            // Filter voices
            const filteredVoices = this.voices.filter(voice => {
                const matchesLanguage = voice.lang.toLowerCase().startsWith(language);
                const voiceName = voice.name.toLowerCase();
                
                let matchesGender = true;
                if (gender === 'male') {
                    matchesGender = voiceName.includes('male') || voiceName.includes('david') || 
                                voiceName.includes('mark') || voiceName.includes('george') ||
                                voiceName.includes('daniel') || voiceName.includes('alex male');
                } else if (gender === 'female') {
                    matchesGender = voiceName.includes('female') || voiceName.includes('zira') || 
                                voiceName.includes('susan') || voiceName.includes('helen') ||
                                voiceName.includes('sarah') || voiceName.includes('samantha');
                }
                
                return matchesLanguage && matchesGender;
            });

            // Add filtered voices
            filteredVoices.forEach(voice => {
                const option = document.createElement('option');
                option.value = voice.name;
                option.textContent = `${voice.name} (${voice.lang})`;
                if (voice.name === currentVoice) {
                    option.selected = true;
                }
                voiceSelect.appendChild(option);
            });
        }

        bindEvents() {
            // Modal close events
            document.addEventListener('click', (e) => {
                if (e.target.matches('.voice-modal-close') || e.target.matches('.simple-voice-modal')) {
                    this.toggleVoiceModal(false);
                }
            });

            // Voice selection changes
            document.addEventListener('change', (e) => {
                if (e.target.matches('#voice-gender, #voice-language')) {
                    this.populateVoiceOptions();
                }
            });

            // Range input updates
            document.addEventListener('input', (e) => {
                if (e.target.type === 'range') {
                    const valueSpan = e.target.parentNode.querySelector('.range-value');
                    if (valueSpan) {
                        let value = e.target.value;
                        if (e.target.id === 'voice-volume') {
                            value = Math.round(value * 100) + '%';
                        } else if (e.target.id === 'voice-speed') {
                            value = value + 'x';
                        }
                        valueSpan.textContent = value;
                    }
                }
            });

            // Button events
            document.addEventListener('click', (e) => {
                if (e.target.matches('#test-voice')) {
                    this.testVoice();
                } else if (e.target.matches('#save-voice')) {
                    this.saveVoiceSettings();
                }
            });
        }

        toggleVoiceModal(show = null) {
            const modal = document.querySelector('#simple-voice-modal');
            if (!modal) return;

            if (show === null) {
                show = modal.style.display === 'none';
            }

            modal.style.display = show ? 'block' : 'none';
            
            if (show) {
                this.populateVoiceOptions();
            }
        }

        testVoice() {
            const testText = "Hello! This is how I sound with the current voice settings. How do you like it?";
            this.speak(testText);
        }

        speak(text) {
            if (!('speechSynthesis' in window)) {
                alert('Speech synthesis is not supported in this browser.');
                return;
            }

            speechSynthesis.cancel(); // Stop any current speech

            const utterance = new SpeechSynthesisUtterance(text);
            
            // Apply current settings
            const specificVoice = document.querySelector('#specific-voice')?.value;
            if (specificVoice) {
                const voice = this.voices.find(v => v.name === specificVoice);
                if (voice) {
                    utterance.voice = voice;
                }
            } else {
                // Auto-select voice based on gender and language
                utterance.voice = this.getBestVoice();
            }

            utterance.rate = parseFloat(document.querySelector('#voice-speed')?.value || 1);
            utterance.pitch = 1.0; // Keep pitch neutral
            utterance.volume = parseFloat(document.querySelector('#voice-volume')?.value || 0.8);

            speechSynthesis.speak(utterance);
        }

        getBestVoice() {
            const gender = document.querySelector('#voice-gender')?.value || 'female';
            const language = document.querySelector('#voice-language')?.value || 'en-US';
            const specificVoice = document.querySelector('#specific-voice')?.value;
            
            // If specific voice is selected, use it
            if (specificVoice && specificVoice !== '') {
                const voice = this.voices.find(v => v.name === specificVoice);
                if (voice) {
                    console.log('Using specific voice:', voice.name);
                    return voice;
                }
            }
            
            // Otherwise find best match based on gender and language
            const langPrefix = language.split('-')[0].toLowerCase();
            
            const matchedVoice = this.voices.find(voice => {
                const voiceLang = voice.lang.toLowerCase();
                const matchesLanguage = voiceLang.includes(language.toLowerCase()) || 
                                        voiceLang.startsWith(langPrefix);
                const voiceName = voice.name.toLowerCase();
                
                let matchesGender = true;
                if (gender === 'male') {
                    matchesGender = voiceName.includes('male') || 
                                    voiceName.includes('david') || voiceName.includes('mark') || 
                                    voiceName.includes('daniel') || voiceName.includes('george') ||
                                    voiceName.includes('oliver') || voiceName.includes('thomas') ||
                                    voiceName.includes('james') || voiceName.includes('william') ||
                                    voiceName.includes('arthur') || voiceName.includes('ryan') ||
                                    voiceName.includes('christopher') || voiceName.includes('andrew') ||
                                    voiceName.includes('arun') || voiceName.includes('amit') ||
                                    voiceName.includes('rajan') || voiceName.includes('vivek') ||
                                    (!voiceName.includes('female') && !voiceName.includes('zira') && 
                                    !voiceName.includes('susan') && !voiceName.includes('helen'));
                } else if (gender === 'female') {
                    matchesGender = voiceName.includes('female') || 
                                    voiceName.includes('zira') || voiceName.includes('susan') ||
                                    voiceName.includes('helen') || voiceName.includes('hazel') ||
                                    voiceName.includes('samantha') || voiceName.includes('allison') ||
                                    voiceName.includes('ava') || voiceName.includes('emma') ||
                                    voiceName.includes('aria') || voiceName.includes('jenny') ||
                                    voiceName.includes('michelle') || voiceName.includes('natasha') ||
                                    voiceName.includes('emily') || voiceName.includes('chloe') ||
                                    voiceName.includes('priya') || voiceName.includes('swara') ||
                                    voiceName.includes('shruti') || voiceName.includes('kavya') ||
                                    (!voiceName.includes('male') && !voiceName.includes('david') && 
                                    !voiceName.includes('mark') && !voiceName.includes('george'));
                } else if (gender === 'neutral') {
                    // For neutral, prefer voices that don't explicitly indicate gender
                    matchesGender = !voiceName.includes('male') && !voiceName.includes('female');
                }
                
                return matchesLanguage && matchesGender;
            });
            
            // Return matched voice, or fallback to language match, or first English voice, or first voice
            return matchedVoice || 
                this.voices.find(voice => voice.lang.toLowerCase().startsWith(langPrefix)) || 
                this.voices.find(voice => voice.lang.toLowerCase().startsWith('en')) || 
                this.voices[0];
        }


        /**
         * Check if user has custom preferences (different from admin defaults)
         * This helps determine priority in audio-mode.js
         */
        hasCustomPreferences() {
            const saved = this.loadUserPreferences();
            
            // If no saved preferences, no custom preferences
            if (!saved || Object.keys(saved).length === 0) {
                return false;
            }
            
            // If we have admin defaults from window.aiChatbotVoiceSelection
            if (window.aiChatbotVoiceSelection && window.aiChatbotVoiceSelection.adminDefaults) {
                const adminDefaults = window.aiChatbotVoiceSelection.adminDefaults;
                
                // Check if any saved preference is different from admin default
                if (saved.gender && saved.gender !== adminDefaults.gender) return true;
                if (saved.language && saved.language !== adminDefaults.language) return true;
                if (saved.specificVoice && saved.specificVoice !== adminDefaults.specificVoice) return true;
                if (saved.speed && saved.speed !== adminDefaults.rate) return true;
                if (saved.volume && saved.volume !== adminDefaults.volume) return true;
            }
            
            // If we have any saved preferences and no admin defaults, consider them custom
            if (saved.gender || saved.language || saved.specificVoice) {
                return true;
            }
            
            return false;
        }

        saveVoiceSettings() {
            const settings = {
                gender: document.querySelector('#voice-gender')?.value || 'female',
                language: document.querySelector('#voice-language')?.value || 'en',
                specificVoice: document.querySelector('#specific-voice')?.value || '',
                speed: parseFloat(document.querySelector('#voice-speed')?.value || 1),
                volume: parseFloat(document.querySelector('#voice-volume')?.value || 0.8)
            };

            // Save to localStorage
            localStorage.setItem('ai_chatbot_voice_preferences', JSON.stringify(settings));
            
            // Send to server if user is logged in
            if (window.aiChatbotAjax) {
                jQuery.post(window.aiChatbotAjax.ajaxUrl, {
                    action: 'ai_chatbot_save_user_voice_preferences',
                    nonce: window.aiChatbotAjax.nonce,
                    preferences: settings
                });
            }

            // Show success message
            this.showMessage('Voice settings saved successfully!', 'success');
            
            // Close modal after short delay
            setTimeout(() => {
                this.toggleVoiceModal(false);
            }, 1000);
        }

        loadUserSettings() {
            const saved = this.loadUserPreferences();
            
            if (saved.gender) document.querySelector('#voice-gender').value = saved.gender;
            if (saved.language) document.querySelector('#voice-language').value = saved.language;
            if (saved.speed) document.querySelector('#voice-speed').value = saved.speed;
            if (saved.volume) document.querySelector('#voice-volume').value = saved.volume;

            // Update range displays
            document.querySelectorAll('input[type="range"]').forEach(input => {
                input.dispatchEvent(new Event('input'));
            });

            this.populateVoiceOptions();
            
            if (saved.specificVoice) {
                setTimeout(() => {
                    const voiceSelect = document.querySelector('#specific-voice');
                    if (voiceSelect) voiceSelect.value = saved.specificVoice;
                }, 100);
            }
        }

        loadUserPreferences() {
            try {
                const saved = localStorage.getItem('ai_chatbot_voice_preferences');
                return saved ? JSON.parse(saved) : {};
            } catch (e) {
                return {};
            }
        }

        showMessage(text, type = 'info') {
            const existing = document.querySelector('.voice-message');
            if (existing) existing.remove();

            const message = document.createElement('div');
            message.className = `voice-message voice-message-${type}`;
            message.textContent = text;
            
            const modal = document.querySelector('.voice-modal-body');
            if (modal) {
                modal.insertBefore(message, modal.firstChild);
                
                setTimeout(() => {
                    message.remove();
                }, 3000);
            }
        }

        // Method to be called by your existing TTS system
        getSelectedVoice() {
            const preferences = this.loadUserPreferences();
            
            if (preferences.specificVoice) {
                return this.voices.find(v => v.name === preferences.specificVoice);
            }
            
            return this.getBestVoice();
        }

        // Method to apply user preferences to any utterance
        applyUserPreferences(utterance) {
            const preferences = this.loadUserPreferences();
            const voice = this.getSelectedVoice();
            
            if (voice) utterance.voice = voice;
            if (preferences.speed) utterance.rate = preferences.speed;
            if (preferences.volume) utterance.volume = preferences.volume;
            
            return utterance;
        }
    }

    // Initialize the voice selection
    document.addEventListener('DOMContentLoaded', function() {
        // Add CSS to the page
        const style = document.createElement('style');
        
        // Initialize voice selection if chatbot exists
        if (document.querySelector('.ai-chatbot-widget') || document.querySelector('#ai-chatbot-widget')) {
            window.simpleVoiceSelection = new SimpleVoiceSelection();
        }
    });

    // Export for use in other scripts
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = SimpleVoiceSelection;
    }
})(jQuery);