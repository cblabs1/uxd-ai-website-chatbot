/**
 * AI Chatbot Admin Main JavaScript
 * 
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var AIChatbotAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initNotifications();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Test API connection
            $(document).on('click', '.test-api-connection', this.testApiConnection);
            
            // Save settings
            $(document).on('submit', '.ai-chatbot-settings-form', this.saveSettings);
            
            // Reset settings
            $(document).on('click', '.reset-settings', this.resetSettings);
            
            // Toggle sections
            $(document).on('click', '.section-toggle', this.toggleSection);
            
            // Copy to clipboard
            $(document).on('click', '.copy-to-clipboard', this.copyToClipboard);
            
            // Modal controls
            $(document).on('click', '[data-modal-open]', this.openModal);
            $(document).on('click', '[data-modal-close], .modal-overlay', this.closeModal);
            
            // Bulk actions
            $(document).on('change', '.select-all', this.selectAllItems);
            $(document).on('submit', '.bulk-action-form', this.handleBulkAction);
            
                // Tab navigation
            $(document).on('click', '.nav-tab', this.switchTab);
            
            // AJAX loading states
            $(document).ajaxStart(function() {
                $('.ai-chatbot-loading').show();
            }).ajaxStop(function() {
                $('.ai-chatbot-loading').hide();
            });

            // Auto-save settings on change (with debounce)
            var autoSaveTimer;
            $(document).on('change', '.ai-chatbot-settings-form input, .ai-chatbot-settings-form select, .ai-chatbot-settings-form textarea', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(function() {
                    AIChatbotAdmin.autoSaveSettings();
                }, 1500);
            });
        },
        
        /**
         * Test API connection
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var provider = $('#ai_provider').val();
            var apiKey = $('#api_key').val();
            
            if (!provider || !apiKey) {
                AIChatbotAdmin.showNotification(aiChatbotAdmin.strings.error, 'error');
                return;
            }
            
            $button.prop('disabled', true).text(aiChatbotAdmin.strings.testing);
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_test_api_connection',
                    nonce: aiChatbotAdmin.nonce,
                    provider: provider,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        AIChatbotAdmin.showNotification(response.data, 'success');
                    } else {
                        AIChatbotAdmin.showNotification(response.data, 'error');
                    }
                },
                error: function() {
                    AIChatbotAdmin.showNotification(aiChatbotAdmin.strings.connection_failed, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },
        
        /**
         * Save settings
         */
        saveSettings: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            
            // Use the fixed serialization method
            var formData = AIChatbotAdmin.serializeFormWithCheckboxes($form);
            
            console.log('Serialized form data:', formData); // Debug log
            
            AIChatbotAdmin.showNotification('Saving settings...', 'info');
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_save_settings',
                    nonce: aiChatbotAdmin.nonce,
                    settings: formData  // This should now be a proper string
                },
                success: function(response) {
                    if (response.success) {
                        AIChatbotAdmin.showNotification(response.data || 'Settings saved successfully!', 'success');
                        $form.find('.save-indicator').addClass('saved').text('Saved!');
                        setTimeout(function() {
                            $form.find('.save-indicator').removeClass('saved').text('');
                        }, 3000);
                    } else {
                        AIChatbotAdmin.showNotification(response.data || 'Failed to save settings', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.log('Response:', xhr.responseText);
                    AIChatbotAdmin.showNotification('Failed to save settings. Check console for details.', 'error');
                }
            });
        },

        /**
         * Auto-save settings - FIXED VERSION
         */
        autoSaveSettings: function() {
            var $form = $('.ai-chatbot-settings-form');
            if ($form.length === 0) return;
            
            // Use the fixed serialization method
            var formData = AIChatbotAdmin.serializeFormWithCheckboxes($form);
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_save_settings',
                    nonce: aiChatbotAdmin.nonce,
                    settings: formData
                },
                success: function(response) {
                    if (response.success) {
                        $('.auto-save-indicator').addClass('saved').text('Auto-saved');
                        setTimeout(function() {
                            $('.auto-save-indicator').removeClass('saved').text('');
                        }, 2000);
                    }
                }
            });
        },

        /**
         * Serialize form with proper checkbox handling
         * This ensures unchecked checkboxes are included as false values
         */
        serializeFormWithCheckboxes: function($form) {
            // Get all form data using jQuery's built-in serialization
            var formArray = $form.serializeArray();
            var submittedFields = {};
            var allCheckboxes = {};
            
            // Collect all submitted form fields
            $.each(formArray, function(i, field) {
                submittedFields[field.name] = field.value;
            });
            
            // Find all checkboxes in the form
            $form.find('input[type="checkbox"]').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    allCheckboxes[name] = false; // Default to unchecked
                }
            });
            
            // Mark submitted checkboxes as checked
            $.each(submittedFields, function(name, value) {
                if (allCheckboxes.hasOwnProperty(name)) {
                    allCheckboxes[name] = true;
                }
            });
            
            // Add unchecked checkboxes to the form array
            var finalFormArray = formArray.slice(); // Copy existing form data
            
            $.each(allCheckboxes, function(name, isChecked) {
                if (!isChecked) {
                    // Add unchecked checkbox with value '0'
                    finalFormArray.push({
                        name: name,
                        value: '0'
                    });
                }
            });
            
            // Convert back to proper serialized string format
            return $.param(finalFormArray);
        },
        
        /**
         * Reset settings
         */
        resetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true);
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_reset_settings',
                    nonce: aiChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AIChatbotAdmin.showNotification(response.data, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        AIChatbotAdmin.showNotification(response.data, 'error');
                    }
                },
                error: function() {
                    AIChatbotAdmin.showNotification(aiChatbotAdmin.strings.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },
        
        /**
         * Toggle section
         */
        toggleSection: function(e) {
            e.preventDefault();
            
            var $toggle = $(this);
            var $section = $toggle.closest('.section-container').find('.section-content');
            
            $section.slideToggle();
            $toggle.toggleClass('open');
            
            // Save state
            var sectionId = $toggle.data('section');
            if (sectionId) {
                var openSections = JSON.parse(localStorage.getItem('ai_chatbot_open_sections') || '[]');
                if ($toggle.hasClass('open')) {
                    if (openSections.indexOf(sectionId) === -1) {
                        openSections.push(sectionId);
                    }
                } else {
                    openSections = openSections.filter(function(id) {
                        return id !== sectionId;
                    });
                }
                localStorage.setItem('ai_chatbot_open_sections', JSON.stringify(openSections));
            }
        },
        
        /**
         * Copy to clipboard
         */
        copyToClipboard: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var text = $button.data('copy-text') || $button.closest('.copy-container').find('input, textarea').val();
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    AIChatbotAdmin.showNotification('Copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    AIChatbotAdmin.showNotification('Copied to clipboard!', 'success');
                } catch (err) {
                    AIChatbotAdmin.showNotification('Failed to copy', 'error');
                }
                
                document.body.removeChild(textArea);
            }
        },
        
        /**
         * Open modal
         */
        openModal: function(e) {
            e.preventDefault();
            
            var modalId = $(this).data('modal-open');
            var $modal = $('#' + modalId);
            
            if ($modal.length) {
                $modal.addClass('active');
                $('body').addClass('modal-open');
            }
        },
        
        /**
         * Close modal
         */
        closeModal: function(e) {
            if (e.target === e.currentTarget || $(e.target).is('[data-modal-close]')) {
                e.preventDefault();
                
                $('.modal.active').removeClass('active');
                $('body').removeClass('modal-open');
            }
        },
        
        /**
         * Select all items
         */
        selectAllItems: function() {
            var $checkbox = $(this);
            var isChecked = $checkbox.is(':checked');
            
            $checkbox.closest('table').find('tbody input[type="checkbox"]').prop('checked', isChecked);
        },
        
        /**
         * Handle bulk action
         */
        handleBulkAction: function(e) {
            var $form = $(this);
            var action = $form.find('select[name="action"]').val();
            var checkedItems = $form.find('input[type="checkbox"]:checked').not('.select-all');
            
            if (!action || action === '-1') {
                e.preventDefault();
                AIChatbotAdmin.showNotification('Please select an action', 'error');
                return;
            }
            
            if (checkedItems.length === 0) {
                e.preventDefault();
                AIChatbotAdmin.showNotification('Please select at least one item', 'error');
                return;
            }
            
            // Confirm destructive actions
            if (action === 'delete' || action === 'clear') {
                if (!confirm('Are you sure you want to perform this action? This cannot be undone.')) {
                    e.preventDefault();
                    return;
                }
            }
        },
        
        /**
         * Switch tab
         */
        switchTab: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var targetTab = $tab.attr('href');
            
            // Update active tab
            $tab.closest('.nav-tab-wrapper').find('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show target content
            $('.tab-content').hide();
            $(targetTab).show();
            
            // Save active tab
            localStorage.setItem('ai_chatbot_active_tab', targetTab);
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                var $element = $(this);
                var tooltipText = $element.data('tooltip');
                
                $element.on('mouseenter', function() {
                    AIChatbotAdmin.showTooltip($element, tooltipText);
                }).on('mouseleave', function() {
                    AIChatbotAdmin.hideTooltip();
                });
            });
        },
        
        /**
         * Show tooltip
         */
        showTooltip: function($element, text) {
            var $tooltip = $('<div class="ai-chatbot-tooltip">' + text + '</div>');
            $('body').append($tooltip);
            
            var position = $element.offset();
            var elementWidth = $element.outerWidth();
            var tooltipWidth = $tooltip.outerWidth();
            
            $tooltip.css({
                position: 'absolute',
                top: position.top - $tooltip.outerHeight() - 5,
                left: position.left + (elementWidth / 2) - (tooltipWidth / 2),
                zIndex: 9999
            });
            
            $tooltip.fadeIn(200);
        },
        
        /**
         * Hide tooltip
         */
        hideTooltip: function() {
            $('.ai-chatbot-tooltip').fadeOut(200, function() {
                $(this).remove();
            });
        },
        
        /**
         * Initialize notifications
         */
        initNotifications: function() {
            // Auto-hide notifications after 5 seconds
            setTimeout(function() {
                $('.notice.is-dismissible').fadeOut();
            }, 5000);
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 4000;
            
            // Remove existing notifications
            $('.ai-chatbot-notification').remove();
            
            var $notification = $('<div class="ai-chatbot-notification notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Add to page
            if ($('.wrap').length) {
                $('.wrap').prepend($notification);
            } else {
                $('body').prepend($notification);
            }
            
            // Add dismiss button
            $notification.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            // Auto-remove after duration
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, duration);
            
            // Handle dismiss button
            $notification.find('.notice-dismiss').on('click', function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            });
            
            // Scroll to notification
            $('html, body').animate({
                scrollTop: $notification.offset().top - 50
            }, 300);
        },
        
        /**
         * Debounce function
         */
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },
        
        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        
        /**
         * Restore UI state from localStorage
         */
        restoreUIState: function() {
            // Restore active tab
            var activeTab = localStorage.getItem('ai_chatbot_active_tab');
            if (activeTab && $(activeTab).length) {
                $('.nav-tab[href="' + activeTab + '"]').click();
            }
            
            // Restore open sections
            var openSections = JSON.parse(localStorage.getItem('ai_chatbot_open_sections') || '[]');
            openSections.forEach(function(sectionId) {
                $('.section-toggle[data-section="' + sectionId + '"]').addClass('open');
                $('.section-toggle[data-section="' + sectionId + '"]').closest('.section-container').find('.section-content').show();
            });
        },
        
        /**
         * Initialize charts (if Chart.js is available)
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') return;
            
            // Conversation trends chart
            var $conversationChart = $('#conversation-trends-chart');
            if ($conversationChart.length && $conversationChart.data('chart-data')) {
                var chartData = $conversationChart.data('chart-data');
                new Chart($conversationChart[0], {
                    type: 'line',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: 'Conversations',
                            data: chartData.data,
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.1)',
                            borderWidth: 2,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        },
        
        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            // Initialize WordPress color picker if available
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker();
            }
        },
        
        /**
         * Initialize file uploads
         */
        initFileUploads: function() {
            $('.file-upload-button').on('click', function(e) {
                e.preventDefault();
                $(this).siblings('input[type="file"]').click();
            });
            
            $('input[type="file"]').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).siblings('.file-name').text(fileName || 'No file selected');
            });
        },
        
        /**
         * Initialize sortable lists
         */
        initSortables: function() {
            if ($.fn.sortable) {
                $('.sortable-list').sortable({
                    placeholder: 'sortable-placeholder',
                    update: function(event, ui) {
                        // Auto-save order if needed
                        var order = $(this).sortable('toArray', {attribute: 'data-id'});
                        // Save order via AJAX if needed
                    }
                });
            }
        }
    };
    
    /**
     * Document ready
     */
    $(document).ready(function() {
        AIChatbotAdmin.init();
        AIChatbotAdmin.restoreUIState();
        AIChatbotAdmin.initCharts();
        AIChatbotAdmin.initColorPickers();
        AIChatbotAdmin.initFileUploads();
        AIChatbotAdmin.initSortables();
    });
    
    /**
     * Window load
     */
    $(window).on('load', function() {
        // Any additional initialization after full page load
    });
    
    /**
     * Make AIChatbotAdmin globally available
     */
    window.AIChatbotAdmin = AIChatbotAdmin;
    
})(jQuery);