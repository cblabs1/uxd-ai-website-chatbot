/**
 * AI Chatbot Settings JavaScript
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initColorPickers();
        initFormValidation();
        initPreview();
        
        // Real-time preview updates
        $('#ai_chatbot_primary_color, #ai_chatbot_width, #ai_chatbot_height, #ai_chatbot_position').on('change input', function() {
            updatePreview();
        });

        // Provider-specific settings toggle
        $('#ai_chatbot_provider').on('change', function() {
            showProviderSettings($(this).val());
        });

        // Initialize with current provider
        showProviderSettings($('#ai_chatbot_provider').val());
    });

    // Initialize color pickers
    function initColorPickers() {
        if ($.fn.wpColorPicker) {
            $('.color-picker').wpColorPicker({
                change: function() {
                    updatePreview();
                }
            });
        }
    }

    // Show settings for selected provider
    function showProviderSettings(provider) {
        $('.provider-settings').hide();
        $('.provider-settings[data-provider="' + provider + '"]').show();
        
        // Load provider-specific help text
        loadProviderHelp(provider);
    }

    // Load provider help information
    function loadProviderHelp(provider) {
        const helpTexts = {
            'openai': {
                'api_key': 'Get your API key from https://platform.openai.com/api-keys',
                'model': 'GPT-4 provides better responses but costs more than GPT-3.5-turbo',
                'temperature': 'Lower values make responses more focused, higher values more creative'
            },
            'claude': {
                'api_key': 'Get your API key from https://console.anthropic.com/',
                'model': 'Claude 3 Opus is most capable, Haiku is fastest and cheapest',
                'temperature': 'Controls randomness in responses (0-1 range for Claude)'
            },
            'gemini': {
                'api_key': 'Get your API key from https://makersuite.google.com/app/apikey',
                'model': 'Gemini Pro supports text, Pro Vision supports images too',
                'temperature': 'Controls creativity in responses (0-1 range for Gemini)'
            }
        };

        const texts = helpTexts[provider];
        if (texts) {
            Object.keys(texts).forEach(function(field) {
                const helpElement = $('[data-help="' + field + '"]');
                if (helpElement.length) {
                    helpElement.text(texts[field]);
                }
            });
        }
    }

    // Form validation
    function initFormValidation() {
        $('form').on('submit', function(e) {
            let isValid = true;
            
            // Validate required fields
            $('.required').each(function() {
                if (!$(this).val().trim()) {
                    isValid = false;
                    $(this).addClass('error');
                    showFieldError($(this), 'This field is required');
                } else {
                    $(this).removeClass('error');
                    hideFieldError($(this));
                }
            });

            // Validate API key formats
            const provider = $('#ai_chatbot_provider').val();
            const apiKeyField = $('#ai_chatbot_' + provider + '_api_key');
            
            if (apiKeyField.length && apiKeyField.val()) {
                if (!validateApiKey(provider, apiKeyField.val())) {
                    isValid = false;
                    apiKeyField.addClass('error');
                    showFieldError(apiKeyField, 'Invalid API key format');
                } else {
                    apiKeyField.removeClass('error');
                    hideFieldError(apiKeyField);
                }
            }

            // Validate numeric ranges
            $('.numeric-range').each(function() {
                const min = parseFloat($(this).attr('min'));
                const max = parseFloat($(this).attr('max'));
                const value = parseFloat($(this).val());
                
                if (value < min || value > max) {
                    isValid = false;
                    $(this).addClass('error');
                    showFieldError($(this), 'Value must be between ' + min + ' and ' + max);
                } else {
                    $(this).removeClass('error');
                    hideFieldError($(this));
                }
            });

            if (!isValid) {
                e.preventDefault();
                window.AIChatbotAdmin.showNotification('Please fix the errors before saving', 'error');
            }
        });
    }

    // Validate API key format
    function validateApiKey(provider, key) {
        const patterns = {
            'openai': /^sk-[a-zA-Z0-9]{48,}$/,
            'claude': /^sk-ant-api03-[a-zA-Z0-9_-]{95,}$/,
            'gemini': /^[a-zA-Z0-9_-]{39}$/
        };

        return patterns[provider] ? patterns[provider].test(key) : true;
    }

    // Show field error
    function showFieldError(field, message) {
        hideFieldError(field); // Remove existing error first
        
        const errorDiv = $('<div class="field-error">' + message + '</div>');
        field.after(errorDiv);
    }

    // Hide field error
    function hideFieldError(field) {
        field.next('.field-error').remove();
    }

    // Initialize preview functionality
    function initPreview() {
        // Create preview iframe if it doesn't exist
        if ($('#chatbot-preview').length === 0) {
            $('.preview-section').append('<div id="chatbot-preview"><div class="preview-chatbot"><div class="preview-header">AI Chatbot Preview</div><div class="preview-messages"><div class="preview-message bot">Hello! How can I help you today?</div></div></div></div>');
        }
        
        updatePreview();
    }

    // Update preview with current settings
    function updatePreview() {
        const preview = $('#chatbot-preview .preview-chatbot');
        const primaryColor = $('#ai_chatbot_primary_color').val();
        const width = $('#ai_chatbot_width').val() + 'px';
        const height = $('#ai_chatbot_height').val() + 'px';
        const position = $('#ai_chatbot_position').val();
        
        // Update styles
        preview.css({
            'background-color': primaryColor,
            'width': width,
            'height': height
        });
        
        // Update position class
        $('#chatbot-preview').removeClass('bottom-right bottom-left top-right top-left').addClass(position);
    }

    // Auto-save draft functionality
    let autoSaveTimeout;
    $('input, select, textarea').on('input change', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            saveDraft();
        }, 2000);
    });

    // Save draft settings
    function saveDraft() {
        const formData = $('form').serialize();
        
        $.ajax({
            url: ai_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_chatbot_save_draft',
                form_data: formData,
                nonce: ai_chatbot_admin.nonce
            },
            success: function() {
                $('#draft-saved').show().delay(2000).fadeOut();
            }
        });
    }

})(jQuery);
