/**
 * AI Chatbot Pro Features JavaScript
 * Enhanced functionality that extends the core chatbot
 *
 * @package AI_Website_Chatbot_Pro
 * @since 1.0.0
 * 
 * Dependencies:
 * - chatbot-frontend.js (core functionality)
 * - jQuery
 */

(function($) {
    'use strict';

    // =======================
    // PRO FEATURES NAMESPACE
    // =======================
    
    window.AIChatbotPro = {
        isEnabled: false,
        config: {},
        
        // Feature flags
        features: {
            semanticSearch: false,
            voiceInput: false,
            fileUpload: false,
            suggestions: false,
            followUp: false,
            analytics: false,
            exportData: false,
            intentRecognition: false
        },

        // =======================
        // INITIALIZATION
        // =======================
        
        init: function(config) {
            console.log('AIChatbot Pro: Initializing...');
            
            // Wait for core chatbot to be ready
            if (typeof window.AIChatbot === 'undefined' || !window.AIChatbot.initialized) {
                $(document).on('aichatbot:core:ready', () => {
                    this.initAfterCore(config);
                });
                return;
            }
            
            this.initAfterCore(config);
        },

        initAfterCore: function(config) {
            this.config = $.extend({
                enableSemanticSearch: true,
                enableVoiceInput: false,
                enableFileUpload: false,
                enableSuggestions: true,
                enableFollowUp: true,
                enableAnalytics: true,
                enableExportData: true,
                enableIntentRecognition: true
            }, config);

            this.setupFeatureFlags();
            this.extendCoreMethod();
            this.bindProEvents();
            this.isEnabled = true;
            
            console.log('AIChatbot Pro: Initialization complete');
        },

        setupFeatureFlags: function() {
            this.features.semanticSearch = this.config.enableSemanticSearch;
            this.features.voiceInput = this.config.enableVoiceInput;
            this.features.fileUpload = this.config.enableFileUpload;
            this.features.suggestions = this.config.enableSuggestions;
            this.features.followUp = this.config.enableFollowUp;
            this.features.analytics = this.config.enableAnalytics;
            this.features.exportData = this.config.enableExportData;
            this.features.intentRecognition = this.config.enableIntentRecognition;
        },

        // =======================
        // CORE METHOD EXTENSIONS
        // =======================
        
        extendCoreMethod: function() {
            // Store original methods
            const original = {
                sendMessageToServer: window.AIChatbot.sendMessageToServer,
                addBotMessage: window.AIChatbot.addBotMessage,
                showTypingIndicator: window.AIChatbot.showTypingIndicator
            };

            // Enhanced message sending with Pro features
            window.AIChatbot.sendMessageToServer = function(message) {
                const self = this;
                
                // Pre-process with intent recognition
                if (window.AIChatbotPro.features.intentRecognition) {
                    window.AIChatbotPro.analyzeIntent(message);
                }
                
                // Enhanced typing indicator
                if (window.AIChatbotPro.features.semanticSearch) {
                    window.AIChatbotPro.showProTypingIndicator();
                } else {
                    this.showTypingIndicator();
                }
                
                // Use Pro AJAX action if available
                const action = window.AIChatbotPro.shouldUseProEndpoint() ? 
                    'ai_chatbot_message_pro' : 'ai_chatbot_send_message';
                
                const requestData = {
                    action: action,
                    message: message,
                    session_id: this.currentSessionId,
                    conversation_id: this.currentConversationId,
                    nonce: this.config.nonce
                };
                
                if (this.currentUserData) {
                    requestData.user_name = this.currentUserData.name;
                    requestData.user_email = this.currentUserData.email;
                }
                
                // Add Pro context
                if (window.AIChatbotPro.features.semanticSearch) {
                    requestData.enable_semantic = true;
                    requestData.context = window.AIChatbotPro.buildProContext();
                }
                
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: requestData,
                    timeout: 45000, // Longer timeout for Pro features
                    success: function(response) {
                        self.hideTypingIndicator();
                        
                        if (response.success) {
                            // Enhanced bot message with Pro features
                            if (window.AIChatbotPro.features.suggestions || window.AIChatbotPro.features.followUp) {
                                window.AIChatbotPro.addProBotMessage(response.data.response, response.data);
                            } else {
                                self.addBotMessage(response.data.response);
                            }
                            
                            // Store message in history
                            self.messageHistory.push({
                                message: response.data.response,
                                sender: 'bot',
                                timestamp: Date.now()
                            });
                            
                            // Pro analytics tracking
                            if (window.AIChatbotPro.features.analytics) {
                                window.AIChatbotPro.trackConversationAnalytics(message, response.data);
                            }
                            
                            // Check for end-of-conversation
                            self.checkEndOfConversation(response.data.response);
                            
                        } else {
                            self.addBotMessage(response.data.message || 'Sorry, something went wrong.');
                        }
                    },
                    error: function() {
                        self.hideTypingIndicator();
                        self.addBotMessage('Sorry, I encountered an error. Please try again.');
                    }
                });
            };

            // Enhanced bot message display
            const originalAddBotMessage = window.AIChatbot.addBotMessage;
            window.AIChatbot.addBotMessage = function(message, responseData) {
                if (responseData && window.AIChatbotPro.isEnabled) {
                    window.AIChatbotPro.addProBotMessage(message, responseData);
                } else {
                    originalAddBotMessage.call(this, message);
                }
            };
        },

        // =======================
        // PRO EVENT HANDLERS
        // =======================
        
        bindProEvents: function() {
            // Suggestion clicks
            $(document).on('click.aichatbotpro', '.suggestion-btn', (e) => {
                e.preventDefault();
                const suggestion = $(e.target).data('suggestion');
                this.handleSuggestionClick(suggestion);
            });
            
            // Follow-up question clicks
            $(document).on('click.aichatbotpro', '.followup-btn', (e) => {
                e.preventDefault();
                const question = $(e.target).data('question');
                this.handleFollowUpClick(question);
            });
            
            // Voice input
            if (this.features.voiceInput) {
                $(document).on('click.aichatbotpro', '.voice-input-btn', (e) => {
                    e.preventDefault();
                    this.toggleVoiceInput();
                });
            }
            
            // File upload
            if (this.features.fileUpload) {
                $(document).on('change.aichatbotpro', '.file-input', (e) => {
                    this.handleFileUpload(e.target.files);
                });
            }
            
            // Export conversation
            if (this.features.exportData) {
                $(document).on('click.aichatbotpro', '.export-conversation-btn', (e) => {
                    e.preventDefault();
                    this.exportConversation();
                });
            }
        },

        // =======================
        // ENHANCED MESSAGE DISPLAY
        // =======================
        
        addProBotMessage: function(message, responseData) {
            const messageId = window.AIChatbot.generateRandomString(8);
            const html = this.buildProMessageHtml(message, 'bot', Date.now(), messageId, responseData);
            
            if (window.AIChatbot.$messages && window.AIChatbot.$messages.length) {
                window.AIChatbot.$messages.append(html);
                window.AIChatbot.scrollToBottom();
                window.AIChatbot.animateMessageAppearance(messageId);
                
                // Add Pro features after message is displayed
                setTimeout(() => {
                    this.addProFeatures(messageId, responseData);
                }, 500);
            }
        },

        buildProMessageHtml: function(message, sender, timestamp, messageId, responseData) {
            const baseHtml = window.AIChatbot.buildMessageHtml(message, sender, timestamp, messageId);
            
            // Add Pro enhancements container
            let proEnhancements = '';
            
            // Source indicator
            if (responseData && responseData.source) {
                proEnhancements += `<div class="ai-chatbot-source">Source: ${this.escapeHtml(responseData.source)}</div>`;
            }
            
            // Confidence indicator
            if (responseData && responseData.confidence) {
                const confidence = Math.round(responseData.confidence * 100);
                const confidenceClass = confidence >= 80 ? 'high' : confidence >= 60 ? 'medium' : 'low';
                proEnhancements += `<div class="ai-chatbot-confidence ${confidenceClass}">Confidence: ${confidence}%</div>`;
            }
            
            // Response time (for debugging)
            if (responseData && responseData.response_time && window.AIChatbot.config.debug) {
                proEnhancements += `<div class="ai-chatbot-response-time">Response time: ${responseData.response_time}ms</div>`;
            }
            
            if (proEnhancements) {
                return baseHtml.replace('</div></div>', proEnhancements + '</div></div>');
            }
            
            return baseHtml;
        },

        addProFeatures: function(messageId, responseData) {
            const $message = window.AIChatbot.$messages.find(`[data-message-id="${messageId}"]`);
            
            // Add suggestions
            if (this.features.suggestions && responseData.suggestions && responseData.suggestions.length > 0) {
                this.addSuggestions(responseData.suggestions, messageId);
            }
            
            // Add follow-up questions
            if (this.features.followUp && responseData.follow_up_questions && responseData.follow_up_questions.length > 0) {
                this.addFollowUpQuestions(responseData.follow_up_questions, messageId);
            }
            
            // Add semantic results indicator
            if (responseData.semantic_results_count && responseData.semantic_results_count > 0) {
                $message.addClass('has-semantic-results');
                $message.attr('data-semantic-count', responseData.semantic_results_count);
            }
        },

        // =======================
        // SUGGESTIONS SYSTEM
        // =======================
        
        addSuggestions: function(suggestions, messageId) {
            let suggestionsHtml = `<div class="ai-chatbot-suggestions" data-for-message="${messageId}">`;
            suggestionsHtml += '<div class="suggestions-title">💡 Suggestions:</div>';
            suggestionsHtml += '<div class="suggestions-list">';
            
            suggestions.slice(0, 3).forEach((suggestion, index) => {
                suggestionsHtml += `<button class="suggestion-btn" data-suggestion="${this.escapeHtml(suggestion)}">`;
                suggestionsHtml += this.escapeHtml(suggestion);
                suggestionsHtml += '</button>';
            });
            
            suggestionsHtml += '</div></div>';
            
            window.AIChatbot.$messages.append(suggestionsHtml);
            window.AIChatbot.scrollToBottom();
        },

        addFollowUpQuestions: function(questions, messageId) {
            let questionsHtml = `<div class="ai-chatbot-followup" data-for-message="${messageId}">`;
            questionsHtml += '<div class="followup-title">❓ Follow-up questions:</div>';
            questionsHtml += '<div class="followup-list">';
            
            questions.slice(0, 3).forEach((question) => {
                questionsHtml += `<button class="followup-btn" data-question="${this.escapeHtml(question)}">`;
                questionsHtml += this.escapeHtml(question);
                questionsHtml += '</button>';
            });
            
            questionsHtml += '</div></div>';
            
            window.AIChatbot.$messages.append(questionsHtml);
            window.AIChatbot.scrollToBottom();
        },

        handleSuggestionClick: function(suggestion) {
            if (window.AIChatbot.$input && window.AIChatbot.$input.length) {
                window.AIChatbot.$input.val(suggestion);
                window.AIChatbot.handleSendMessage(suggestion);
                this.hideSuggestions();
            }
        },

        handleFollowUpClick: function(question) {
            if (window.AIChatbot.$input && window.AIChatbot.$input.length) {
                window.AIChatbot.$input.val(question);
                window.AIChatbot.handleSendMessage(question);
                this.hideFollowUp();
            }
        },

        hideSuggestions: function() {
            $('.ai-chatbot-suggestions').fadeOut(300);
        },

        hideFollowUp: function() {
            $('.ai-chatbot-followup').fadeOut(300);
        },

        // =======================
        // ENHANCED TYPING INDICATOR
        // =======================
        
        showProTypingIndicator: function() {
            if (!window.AIChatbot.$messages) return;
            
            const proTypingHtml = `
                <div class="ai-chatbot-message ai-chatbot-message-bot ai-chatbot-typing pro-typing">
                    <div class="ai-chatbot-message-content">
                        <div class="ai-chatbot-typing-indicator pro-indicator">
                            <span class="typing-text">🧠 AI is analyzing...</span>
                            <div class="typing-dots">
                                <span></span><span></span><span></span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            window.AIChatbot.$messages.append(proTypingHtml);
            window.AIChatbot.$typing = window.AIChatbot.$messages.find('.ai-chatbot-typing');
            window.AIChatbot.scrollToBottom();
            window.AIChatbot.isTyping = true;
        },

        // =======================
        // VOICE INPUT (PRO FEATURE)
        // =======================
        
        toggleVoiceInput: function() {
            if (!this.features.voiceInput) return;
            
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                alert('Voice input is not supported in your browser.');
                return;
            }
            
            if (this.isListening) {
                this.stopVoiceInput();
            } else {
                this.startVoiceInput();
            }
        },

        startVoiceInput: function() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            
            this.recognition.continuous = false;
            this.recognition.interimResults = true;
            this.recognition.lang = 'en-US';
            
            this.recognition.onstart = () => {
                this.isListening = true;
                $('.voice-input-btn').addClass('listening');
                this.showVoiceModal();
            };
            
            this.recognition.onresult = (event) => {
                let transcript = '';
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    transcript += event.results[i][0].transcript;
                }
                
                if (event.results[event.results.length - 1].isFinal) {
                    this.handleVoiceResult(transcript);
                }
            };
            
            this.recognition.onerror = () => {
                this.stopVoiceInput();
                alert('Voice recognition error. Please try again.');
            };
            
            this.recognition.onend = () => {
                this.stopVoiceInput();
            };
            
            this.recognition.start();
        },

        stopVoiceInput: function() {
            if (this.recognition) {
                this.recognition.stop();
            }
            this.isListening = false;
            $('.voice-input-btn').removeClass('listening');
            this.hideVoiceModal();
        },

        handleVoiceResult: function(transcript) {
            if (transcript.trim() && window.AIChatbot.$input && window.AIChatbot.$input.length) {
                window.AIChatbot.$input.val(transcript.trim());
                window.AIChatbot.handleSendMessage(transcript.trim());
            }
        },

        showVoiceModal: function() {
            if ($('.voice-modal').length === 0) {
                const modalHtml = `
                    <div class="voice-modal">
                        <div class="voice-animation">
                            <div class="voice-circle"></div>
                            <div class="voice-pulse"></div>
                        </div>
                        <div class="voice-text">Listening...</div>
                        <button class="voice-stop-btn">Stop</button>
                    </div>
                `;
                $('body').append(modalHtml);
                
                $('.voice-stop-btn').on('click', () => {
                    this.stopVoiceInput();
                });
            }
        },

        hideVoiceModal: function() {
            $('.voice-modal').remove();
        },

        // =======================
        // FILE UPLOAD (PRO FEATURE)
        // =======================
        
        handleFileUpload: function(files) {
            if (!this.features.fileUpload || !files || files.length === 0) return;
            
            const file = files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
            
            if (file.size > maxSize) {
                alert('File size must be less than 5MB');
                return;
            }
            
            if (!allowedTypes.includes(file.type)) {
                alert('Unsupported file type. Please upload an image, PDF, or text file.');
                return;
            }
            
            this.uploadFile(file);
        },

        uploadFile: function(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'ai_chatbot_upload_file');
            formData.append('nonce', window.AIChatbot.config.nonce);
            formData.append('session_id', window.AIChatbot.currentSessionId);
            
            $.ajax({
                url: window.AIChatbot.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        window.AIChatbot.addUserMessage(`📎 Uploaded: ${file.name}`);
                        // Process file content with AI
                        window.AIChatbot.sendMessageToServer(`Please analyze this file: ${response.data.file_id}`);
                    } else {
                        alert('Failed to upload file: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('File upload failed. Please try again.');
                }
            });
        },

        // =======================
        // ANALYTICS & TRACKING
        // =======================
        
        trackConversationAnalytics: function(userMessage, responseData) {
            if (!this.features.analytics) return;
            
            const analyticsData = {
                action: 'ai_chatbot_track_event',
                event_type: 'message_exchange',
                user_message: userMessage,
                response_source: responseData.source,
                response_time: responseData.response_time,
                confidence: responseData.confidence,
                intent: responseData.intent,
                session_id: window.AIChatbot.currentSessionId,
                conversation_id: window.AIChatbot.currentConversationId,
                nonce: window.AIChatbot.config.nonce
            };
            
            $.ajax({
                url: window.AIChatbot.config.ajaxUrl,
                type: 'POST',
                data: analyticsData,
                success: function() {
                    console.log('Analytics tracked successfully');
                }
            });
        },

        // =======================
        // INTENT RECOGNITION
        // =======================
        
        analyzeIntent: function(message) {
            if (!this.features.intentRecognition) return;
            
            // Simple intent keywords (can be enhanced with ML)
            const intents = {
                purchase: ['buy', 'purchase', 'order', 'price', 'cost', 'payment'],
                support: ['help', 'support', 'problem', 'issue', 'error', 'broken'],
                information: ['what', 'how', 'when', 'where', 'why', 'tell me'],
                greeting: ['hello', 'hi', 'hey', 'good morning', 'good afternoon'],
                goodbye: ['bye', 'goodbye', 'see you', 'thanks', 'thank you']
            };
            
            const lowerMessage = message.toLowerCase();
            let detectedIntent = 'general';
            let maxScore = 0;
            
            Object.keys(intents).forEach(intent => {
                const score = intents[intent].reduce((acc, keyword) => {
                    return acc + (lowerMessage.includes(keyword) ? 1 : 0);
                }, 0);
                
                if (score > maxScore) {
                    maxScore = score;
                    detectedIntent = intent;
                }
            });
            
            console.log('Intent detected:', detectedIntent, 'Score:', maxScore);
            return detectedIntent;
        },

        // =======================
        // DATA EXPORT
        // =======================
        
        exportConversation: function() {
            if (!this.features.exportData) return;
            
            if (!window.AIChatbot.isUserAuthenticated()) {
                alert('Please provide your details to export conversation.');
                return;
            }
            
            $.ajax({
                url: window.AIChatbot.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_export_data',
                    session_id: window.AIChatbot.currentSessionId,
                    user_email: window.AIChatbot.currentUserData.email,
                    nonce: window.AIChatbot.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data.data));
                        const dlAnchorElem = document.createElement('a');
                        dlAnchorElem.setAttribute("href", dataStr);
                        dlAnchorElem.setAttribute("download", response.data.filename);
                        dlAnchorElem.click();
                    }
                }
            });
        },

        // =======================
        // UTILITY METHODS
        // =======================
        
        shouldUseProEndpoint: function() {
            return this.features.semanticSearch || this.features.intentRecognition;
        },

        buildProContext: function() {
            return {
                page_url: window.location.href,
                page_title: document.title,
                timestamp: Date.now(),
                user_agent: navigator.userAgent,
                referrer: document.referrer,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight
            };
        },

        escapeHtml: function(text) {
            if (!text) return '';
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        // =======================
        // CLEANUP
        // =======================
        
        destroy: function() {
            // Unbind Pro events
            $(document).off('.aichatbotpro');
            
            // Stop voice recognition
            if (this.recognition) {
                this.recognition.stop();
            }
            
            // Remove Pro UI elements
            $('.voice-modal').remove();
            $('.ai-chatbot-suggestions').remove();
            $('.ai-chatbot-followup').remove();
            
            this.isEnabled = false;
            console.log('AIChatbot Pro: Destroyed');
        }
    };

    // =======================
    // AUTO-INITIALIZATION
    // =======================
    
    $(document).ready(function() {
        // Check if Pro features should be enabled
        if (typeof ai_chatbot_ajax !== 'undefined' && ai_chatbot_ajax.pro_enabled) {
            console.log('AIChatbot Pro: Pro features enabled, initializing...');
            
            window.AIChatbotPro.init({
                enableSemanticSearch: ai_chatbot_ajax.pro_config?.semantic_search !== false,
                enableVoiceInput: ai_chatbot_ajax.pro_config?.voice_input === true,
                enableFileUpload: ai_chatbot_ajax.pro_config?.file_upload === true,
                enableSuggestions: ai_chatbot_ajax.pro_config?.suggestions !== false,
                enableFollowUp: ai_chatbot_ajax.pro_config?.follow_up !== false,
                enableAnalytics: ai_chatbot_ajax.pro_config?.analytics !== false,
                enableExportData: ai_chatbot_ajax.pro_config?.export_data !== false,
                enableIntentRecognition: ai_chatbot_ajax.pro_config?.intent_recognition !== false
            });
        } else {
            console.log('AIChatbot Pro: Pro features not enabled');
        }
    });

})(jQuery);