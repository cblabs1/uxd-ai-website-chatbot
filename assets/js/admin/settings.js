/**
 * AI Chatbot Admin Settings JavaScript
 * 
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var AIChatbotSettings = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initConditionalFields();
            this.initProviderSettings();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // AI Provider change
            $(document).on('change', '#ai_provider', this.handleProviderChange);
            
            // Enable/disable chatbot
            $(document).on('change', '#chatbot_enabled', this.handleChatbotToggle);
            
            // Rate limiting toggle
            $(document).on('change', '#rate_limiting_enabled', this.handleRateLimitingToggle);
            
            // Content sync toggle
            $(document).on('change', '#content_sync_enabled', this.handleContentSyncToggle);
            
            // GDPR toggle
            $(document).on('change', '#gdpr_enabled', this.handleGdprToggle);
            
            // Widget position preview
            $(document).on('change', '#widget_position', this.updateWidgetPreview);
            $(document).on('change', '#widget_color', this.updateWidgetPreview);
            $(document).on('change', '#widget_size', this.updateWidgetPreview);
            
            // Import/Export settings
            $(document).on('click', '.export-settings', this.exportSettings);
            $(document).on('change', '.import-settings-file', this.importSettings);
            
            // Sync content manually
            $(document).on('click', '.sync-content-now', this.syncContentNow);

            // Train Website Manually
            $(document).on('click', '.train-website-data', this.trainWebsiteNow);
            
            // Clear cache
            $(document).on('click', '.clear-cache', this.clearCache);
        },
        
        /**
         * Handle AI provider change
         */
        handleProviderChange: function() {
            var provider = $(this).val();
            
            // Hide all provider-specific settings
            $('.provider-settings').hide();
            
            // Show settings for selected provider
            $('.provider-settings-' + provider).show();
            
            // Update model options
            AIChatbotSettings.updateModelOptions(provider);
            
            // Update help text
            AIChatbotSettings.updateProviderHelpText(provider);
        },
        
        /**
         * Update model options based on provider
         */
        updateModelOptions: function(provider) {
            var $modelSelect = $('#ai_model');
            var $currentModel = $modelSelect.val();
            var models = {
                'openai': {
                    // GPT-5 level (o1 series)
                    'o1-preview': 'o1-preview (GPT-5 level reasoning)',
                    'o1-mini': 'o1-mini (GPT-5 level, faster)',
                    // GPT-4 models
                    'gpt-4o': 'GPT-4o (Latest flagship)',
                    'gpt-4o-mini': 'GPT-4o Mini (Recommended)',
                    'gpt-4-turbo': 'GPT-4 Turbo',
                    'gpt-4': 'GPT-4',
                    // GPT-3.5
                    'gpt-3.5-turbo': 'GPT-3.5 Turbo (Budget)'
                },
                'claude': {
                    // Claude 3.5 (Latest)
                    'claude-3-5-sonnet-20241022': 'Claude 3.5 Sonnet (New)',
                    'claude-3-5-sonnet-20240620': 'Claude 3.5 Sonnet',
                    'claude-3-5-haiku-20241022': 'Claude 3.5 Haiku',
                    // Claude 3
                    'claude-3-opus-20240229': 'Claude 3 Opus',
                    'claude-3-sonnet-20240229': 'Claude 3 Sonnet',
                    'claude-3-haiku-20240307': 'Claude 3 Haiku'
                },
                'gemini': {
                    // Latest Gemini models
                    'gemini-2.0-flash': 'Gemini 2.0 Flash (Latest)',
                    'gemini-1.5-pro': 'Gemini 1.5 Pro',
                    'gemini-1.5-flash': 'Gemini 1.5 Flash',
                    'gemini-1.5-flash-8b': 'Gemini 1.5 Flash-8B',
                    'gemini-pro': 'Gemini Pro (Legacy)',
                    'gemini-pro-vision': 'Gemini Pro Vision (Legacy)'
                },
                'custom': {
                    'custom': 'Custom Model'
                }
            };
            
            $modelSelect.empty();
            
            if (models[provider]) {
                $.each(models[provider], function(value, text) {
                    var selected = '';
                    // Check if this was the previously selected model
                    if (value === $currentModel) {
                        selected = ' selected="selected"';
                    }
                    $modelSelect.append('<option value="' + value + '"' + selected + '>' + text + '</option>');
                });
                
                // If no model was selected (first option gets auto-selected), 
                // explicitly select the first option to ensure consistency
                if (!$currentModel || !models[provider][$currentModel]) {
                    var firstModel = Object.keys(models[provider])[0];
                    $modelSelect.val(firstModel);
                }
            }
            
            // Trigger change event to ensure form knows about the selection
            $modelSelect.trigger('change');
        },
        
        /**
         * Update provider help text
         */
        updateProviderHelpText: function(provider) {
            var helpTexts = {
                'openai': 'Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Dashboard</a>',
                'claude': 'Get your API key from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>',
                'gemini': 'Get your API key from <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>',
                'custom': 'Enter your custom API endpoint URL and authentication details'
            };
            
            $('.provider-help-text').html(helpTexts[provider] || '');
        },
        
        /**
         * Handle chatbot toggle
         */
        handleChatbotToggle: function() {
            var isEnabled = $(this).is(':checked');
            
            if (isEnabled) {
                $('.chatbot-dependent').removeClass('disabled');
                $('#chatbot-status').removeClass('status-disabled').addClass('status-enabled').text('Enabled');
            } else {
                $('.chatbot-dependent').addClass('disabled');
                $('#chatbot-status').removeClass('status-enabled').addClass('status-disabled').text('Disabled');
            }
            
            AIChatbotSettings.updateWidgetPreview();
        },
        
        /**
         * Handle rate limiting toggle
         */
        handleRateLimitingToggle: function() {
            var isEnabled = $(this).is(':checked');
            
            if (isEnabled) {
                $('.rate-limiting-settings').slideDown();
            } else {
                $('.rate-limiting-settings').slideUp();
            }
        },
        
        /**
         * Handle content sync toggle
         */
        handleContentSyncToggle: function() {
            var isEnabled = $(this).is(':checked');
            
            if (isEnabled) {
                $('.content-sync-settings').slideDown();
            } else {
                $('.content-sync-settings').slideUp();
            }
        },
        
        /**
         * Handle GDPR toggle
         */
        handleGdprToggle: function() {
            var isEnabled = $(this).is(':checked');
            
            if (isEnabled) {
                $('.gdpr-settings').slideDown();
            } else {
                $('.gdpr-settings').slideUp();
            }
        },
        
        /**
         * Update widget preview
         */
        updateWidgetPreview: function() {
            var position = $('#widget_position').val();
            var color = $('#widget_color').val();
            var size = $('#widget_size').val();
            var enabled = $('#chatbot_enabled').is(':checked');
            
            var $preview = $('.widget-preview');
            
            if (!$preview.length) {
                return;
            }
            
            // Update position
            $preview.removeClass('pos-bottom-right pos-bottom-left pos-top-right pos-top-left pos-center')
                    .addClass('pos-' + position);
            
            // Update color
            $preview.find('.widget-button').css('background-color', color);
            
            // Update size
            $preview.removeClass('size-small size-medium size-large').addClass('size-' + size);
            
            // Update enabled state
            if (enabled) {
                $preview.removeClass('disabled');
            } else {
                $preview.addClass('disabled');
            }
        },
        
        /**
         * Initialize conditional fields
         */
        initConditionalFields: function() {
            // Trigger initial state
            $('#ai_provider').trigger('change');
            $('#chatbot_enabled').trigger('change');
            $('#rate_limiting_enabled').trigger('change');
            $('#content_sync_enabled').trigger('change');
            $('#gdpr_enabled').trigger('change');
            
            // Initialize widget preview
            this.updateWidgetPreview();
        },
        
        /**
         * Initialize provider-specific settings
         */
        initProviderSettings: function() {
            // Temperature slider
            $('#temperature').on('input', function() {
                $('.temperature-value').text($(this).val());
            });
            
            // Max tokens slider
            $('#max_tokens').on('input', function() {
                $('.max-tokens-value').text($(this).val());
            });
            
            // Data retention slider
            $('#data_retention_days').on('input', function() {
                $('.retention-days-value').text($(this).val());
            });
        },
        
        /**
         * Export settings
         */
        exportSettings: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.prop('disabled', true).text('Exporting...');
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_export_settings',
                    nonce: aiChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create download
                        var blob = new Blob([JSON.stringify(response.data, null, 2)], {
                            type: 'application/json'
                        });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'ai-chatbot-settings-' + new Date().toISOString().slice(0, 10) + '.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        
                        AIChatbotAdmin.showNotification('Settings exported successfully!', 'success');
                    } else {
                        AIChatbotAdmin.showNotification(response.data, 'error');
                    }
                },
                error: function() {
                    AIChatbotAdmin.showNotification('Export failed', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Export Settings');
                }
            });
        },
        
        /**
         * Import settings
         */
        importSettings: function() {
            var file = this.files[0];
            
            if (!file) {
                return;
            }
            
            if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                AIChatbotAdmin.showNotification('Please select a valid JSON file', 'error');
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var settings = JSON.parse(e.target.result);
                    
                    if (!confirm('Are you sure you want to import these settings? This will overwrite your current configuration.')) {
                        return;
                    }
                    
                    $.ajax({
                        url: aiChatbotAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ai_chatbot_import_settings',
                            nonce: aiChatbotAdmin.nonce,
                            settings: settings
                        },
                        success: function(response) {
                            if (response.success) {
                                AIChatbotAdmin.showNotification('Settings imported successfully!', 'success');
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                AIChatbotAdmin.showNotification(response.data, 'error');
                            }
                        },
                        error: function() {
                            AIChatbotAdmin.showNotification('Import failed', 'error');
                        }
                    });
                } catch (error) {
                    AIChatbotAdmin.showNotification('Invalid JSON file', 'error');
                }
            };
            reader.readAsText(file);
        },
        
        /**
         * Sync content now
         */
        syncContentNow: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.prop('disabled', true).text('Syncing...');
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_sync_content',
                    nonce: aiChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AIChatbotAdmin.showNotification(response.data, 'success');
                        $('.last-sync-time').text('Just now');
                    } else {
                        AIChatbotAdmin.showNotification(response.data, 'error');
                    }
                },
                error: function() {
                    AIChatbotAdmin.showNotification('Sync failed', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Sync Now');
                }
            });
        },

        /**
         * 
         * Train Ai with website Data 
         */
        trainWebsiteNow:function(e){
            e.preventDefault();
            const $button = $(this);
            $button.prop('disabled', true).text('Training...');
            
            $.post(ajaxurl, {
                action: 'ai_chatbot_train_website_data',
                nonce: aiChatbotAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    AIChatbotAdmin.showNotification(response.data, 'success');
                } else {
                    AIChatbotAdmin.showNotification(response.data, 'error');
                }
            })
            .fail(function() {
                AIChatbotAdmin.showNotification('Training failed', 'error');
            })
            .always(function() {
                $button.prop('disabled', false).text('Train from Website Data');
            });
        },
        
        /**
         * Clear cache
         */
        clearCache: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_clear_cache',
                    nonce: aiChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AIChatbotAdmin.showNotification('Cache cleared successfully!', 'success');
                    } else {
                        AIChatbotAdmin.showNotification(response.data, 'error');
                    }
                },
                error: function() {
                    AIChatbotAdmin.showNotification('Failed to clear cache', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear Cache');
                }
            });
        },
        
        /**
         * Validate settings before save
         */
        validateSettings: function() {
            var errors = [];
            
            // Check if API key is provided
            var provider = $('#ai_provider').val();
            var apiKey = $('#api_key').val();
            
            if (!apiKey && provider !== 'custom') {
                errors.push('API key is required for ' + provider);
            }
            
            // Check temperature range
            var temperature = parseFloat($('#temperature').val());
            if (temperature < 0 || temperature > 2) {
                errors.push('Temperature must be between 0 and 2');
            }
            
            // Check max tokens
            var maxTokens = parseInt($('#max_tokens').val());
            if (maxTokens < 1 || maxTokens > 4000) {
                errors.push('Max tokens must be between 1 and 4000');
            }
            
            // Check rate limiting values if enabled
            if ($('#rate_limiting_enabled').is(':checked')) {
                var maxRequests = parseInt($('#max_requests').val());
                var timeWindow = parseInt($('#time_window').val());
                
                if (maxRequests < 1) {
                    errors.push('Max requests must be at least 1');
                }
                
                if (timeWindow < 60) {
                    errors.push('Time window must be at least 60 seconds');
                }
            }
            
            if (errors.length > 0) {
                AIChatbotAdmin.showNotification('Please fix the following errors:<br>' + errors.join('<br>'), 'error', 8000);
                return false;
            }
            
            return true;
        }
    };
    
    /**
     * Document ready
     */
    $(document).ready(function() {
        AIChatbotSettings.init();
        
        // Override form submission to add validation
        $('.ai-chatbot-settings-form').on('submit', function(e) {
            if (!AIChatbotSettings.validateSettings()) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    /**
     * Make AIChatbotSettings globally available
     */
    window.AIChatbotSettings = AIChatbotSettings;
    
})(jQuery);