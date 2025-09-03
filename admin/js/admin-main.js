/**
 * AI Chatbot Admin Main JavaScript
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        initModals();
        initNotifications();
        initTabs();
        initTooltips();

        // Provider switching
        $('#ai_chatbot_provider').on('change', function() {
            const selectedProvider = $(this).val();
            $('.provider-config').hide();
            $('.provider-config[data-provider="' + selectedProvider + '"]').show();
        });

        // Test connection functionality
        $('.test-connection').on('click', function() {
            const button = $(this);
            const provider = button.data('provider');
            
            button.prop('disabled', true).html('<span class="spinner is-active"></span> Testing...');
            
            $.ajax({
                url: ai_chatbot_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_test_connection',
                    provider: provider,
                    nonce: ai_chatbot_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('Connection successful!', 'success');
                    } else {
                        showNotification('Connection failed: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotification('Connection test failed', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Test Connection');
                }
            });
        });

        // Reset statistics functionality
        $('.reset-stats').on('click', function() {
            if (!confirm('Are you sure you want to reset all usage statistics? This cannot be undone.')) {
                return;
            }

            const button = $(this);
            const provider = button.data('provider');
            
            button.prop('disabled', true);
            
            $.ajax({
                url: ai_chatbot_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_reset_stats',
                    provider: provider,
                    nonce: ai_chatbot_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('Statistics reset successfully!', 'success');
                        location.reload(); // Refresh to show updated stats
                    } else {
                        showNotification('Failed to reset statistics: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotification('Failed to reset statistics', 'error');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        });
    });

    // Initialize modal functionality
    function initModals() {
        // Open modal
        $('[data-modal]').on('click', function() {
            const modalId = $(this).data('modal');
            $('#' + modalId).show();
        });

        // Close modal
        $('.modal-close, .ai-chatbot-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).closest('.ai-chatbot-modal').hide();
            }
        });

        // Prevent modal content clicks from closing modal
        $('.modal-content').on('click', function(e) {
            e.stopPropagation();
        });

        // Close modal with Escape key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // Escape key
                $('.ai-chatbot-modal:visible').hide();
            }
        });
    }

    // Initialize notification system
    function initNotifications() {
        // Create notification container if it doesn't exist
        if ($('#ai-chatbot-notifications').length === 0) {
            $('body').append('<div id="ai-chatbot-notifications"></div>');
        }
    }

    // Show notification
    function showNotification(message, type) {
        type = type || 'info';
        
        const notification = $('<div class="ai-chatbot-notification ' + type + '">' + message + '</div>');
        
        $('#ai-chatbot-notifications').append(notification);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            notification.fadeOut(function() {
                notification.remove();
            });
        }, 5000);

        // Close button
        notification.on('click', function() {
            notification.fadeOut(function() {
                notification.remove();
            });
        });
    }

    // Initialize tab functionality
    function initTabs() {
        $('.tab-button').on('click', function() {
            const tab = $(this).data('tab');
            const container = $(this).closest('.table-container, .chart-container');
            
            // Update active states
            container.find('.tab-button').removeClass('active');
            $(this).addClass('active');
            
            container.find('.tab-content').removeClass('active');
            container.find('#' + tab + '-table, #' + tab + '-chart').addClass('active');
        });
    }

    // Initialize tooltips
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            const element = $(this);
            const tooltip = element.data('tooltip');
            
            element.on('mouseenter', function() {
                const tooltipDiv = $('<div class="ai-chatbot-tooltip">' + tooltip + '</div>');
                $('body').append(tooltipDiv);
                
                const offset = element.offset();
                tooltipDiv.css({
                    top: offset.top - tooltipDiv.outerHeight() - 10,
                    left: offset.left + (element.outerWidth() / 2) - (tooltipDiv.outerWidth() / 2)
                });
            });
            
            element.on('mouseleave', function() {
                $('.ai-chatbot-tooltip').remove();
            });
        });
    }

    // Utility function to format numbers
    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    // Export global functions
    window.AIChatbotAdmin = {
        showNotification: showNotification,
        formatNumber: formatNumber
    };

})(jQuery);
