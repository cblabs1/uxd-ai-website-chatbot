/**
 * AI Chatbot Admin Training JavaScript
 * 
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var AIChatbotTraining = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initFormValidation();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Add training data
            $(document).on('click', '.add-training-data', this.showAddForm);
            $(document).on('submit', '#training-data-form', this.submitTrainingData);
            $(document).on('click', '.cancel-add', this.hideAddForm);
            
            // Edit training data
            $(document).on('click', '.edit-training-data', this.showEditForm);
            $(document).on('click', '.cancel-edit', this.hideEditForm);
            
            // Delete training data
            $(document).on('click', '.delete-training-data', this.deleteTrainingData);
            
            // Import/Export
            $(document).on('click', '.import-training-data', this.showImportModal);
            $(document).on('click', '.export-training-data', this.exportTrainingData);
            $(document).on('change', '#training-import-file', this.handleFileUpload);
            $(document).on('click', '.process-import', this.processImport);
            
            // Train model
            $(document).on('click', '.train-model', this.trainModel);
            
            // Filter and search
            $(document).on('change', '#training-filter', this.filterTrainingData);
            $(document).on('input', '#training-search', this.debounce(this.searchTrainingData, 300));
            
            // Tag management
            $(document).on('click', '.add-tag', this.addTag);
            $(document).on('click', '.remove-tag', this.removeTag);
            
            // Intent suggestions
            $(document).on('input', '#training-intent', this.suggestIntents);
        },
        
        /**
         * Show add training data form
         */
        showAddForm: function(e) {
            e.preventDefault();
            
            $('#training-data-form')[0].reset();
            $('#training-data-form').find('input[name="training_id"]').val('');
            $('.training-form-container').slideDown();
            $('.add-training-data').prop('disabled', true);
            
            // Focus on first field
            $('#training-question').focus();
        },
        
        /**
         * Hide add training data form
         */
        hideAddForm: function(e) {
            e.preventDefault();
            
            $('.training-form-container').slideUp();
            $('.add-training-data').prop('disabled', false);
        },
        
        /**
         * Show edit form
         */
        showEditForm: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var trainingId = $button.data('id');
            
            // Get training data via AJAX
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_training_data',
                    nonce: aiChatbotAdmin.nonce,
                    training_id: trainingId
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        $('#training-question').val(data.question);
                        $('#training-answer').val(data.answer);
                        $('#training-intent').val(data.intent);
                        $('#training-data-form').find('input[name="training_id"]').val(data.id);
                        
                        // Handle tags
                        $('.tag-container').empty();
                        if (data.tags && data.tags.length > 0) {
                            data.tags.forEach(function(tag) {
                                AIChatbotTraining.addTagToForm(tag);
                            });
                        }
                        
                        $('.training-form-container').slideDown();
                        $('.add-training-data').prop('disabled', true);
                        $('#training-question').focus();
                    }
                }
            });
        },
        
        /**
         * Hide edit form
         */
        hideEditForm: function(e) {
            e.preventDefault();
            
            $('.training-form-container').slideUp();
            $('.add-training-data').prop('disabled', false);
            $('#training-data-form')[0].reset();
        },
        
        /**
         * Submit training data
         */
        submitTrainingData: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = $form.serializeArray();
            
            // Add tags to form data
            var tags = [];
            $('.tag-item').each(function() {
                tags.push($(this).text().replace('×', '').trim());
            });
            formData.push({name: 'tags', value: JSON.stringify(tags)});
            
            // Validate form
            if (!AIChatbotTraining.validateTrainingForm(formData)) {
                return;
            }
            
            var $submitButton = $form.find('button[type="submit"]');
            $submitButton.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: $.param(formData) + '&action=ai_chatbot_add_training_data&nonce=' + aiChatbotAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        AIChatbotAdmin.showNotification(response.data, 'success');
                        AIChatbotTraining.hideAddForm({preventDefault: function(){}});
                        location.reload(); // Refresh to show new data
                    } else {
                        AIChatbotAdmin.showNotification(response.data, 'error');
                    }
                },
                error: function() {
                    AIChatbotAdmin.showNotification('Failed to save training data', 'error');
                },
                complete: function() {
                    $submitButton.prop('disabled', false).text('Save Training Data');
                }
            });
        },
        
        /**
         * Delete training data
         */
        deleteTrainingData: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this training data? This action cannot be undone.')) {
                return;
            }
            
            var $button = $(this);
            var trainingId = $button.data('id');
            
            $button.prop('disabled', true);
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_delete_training_data',
                    nonce: aiChatbotAdmin.nonce,
                    id: trainingId
                },
                success: function(response) {
                    if (response.success) {
                        AIChatbotAdmin.showNotification(response.data, 'success');
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        AIChatbotAdmin.showNotification(response.data, 'error');
                    }
                },
                error: function() {
                    AIChatbotAdmin.showNotification('Failed to delete training data', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },
        
        /**
         * Show import modal
         */
        showImportModal: function(e) {
            e.preventDefault();
            
            $('#import-training-modal').addClass('active');
            $('body').addClass('modal-open');
        },
        
        /**
         * Export training data
         */
        exportTrainingData: function(e) {
            e.preventDefault();
            
            var format = $(this).data('format') || 'csv';
            var $button = $(this);
            
            $button.prop('disabled', true).text('Exporting...');
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_export_training_data',
                    nonce: aiChatbotAdmin.nonce,
                    format: format
                },
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(data, textStatus, xhr) {
                    // Create download link
                    var filename = xhr.getResponseHeader('Content-Disposition');
                    if (filename) {
                        filename = filename.split('filename=')[1].replace(/"/g, '');
                    } else {
                        filename = 'training-data.' + format;
                    }
                    
                    var blob = new Blob([data]);
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    AIChatbotAdmin.showNotification('Training data exported successfully!', 'success');
                },
                error: function() {
                    AIChatbotAdmin.showNotification('Export failed', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Export');
                }
            });
        },
        
        /**
         * Handle file upload
         */
        handleFileUpload: function() {
            var file = this.files[0];
            if (!file) return;
            
            var allowedTypes = ['text/csv', 'application/json'];
            var allowedExtensions = ['.csv', '.json'];
            
            var fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
                AIChatbotAdmin.showNotification('Please select a CSV or JSON file', 'error');
                $(this).val('');
                return;
            }
            
            $('.file-info').show();
            $('.file-name').text(file.name);
            $('.file-size').text(AIChatbotTraining.formatFileSize(file.size));
            $('.process-import').prop('disabled', false);
        },
        
        /**
         * Process import
         */
        processImport: function(e) {
            e.preventDefault();
            
            var file = $('#training-import-file')[0].files[0];
            if (!file) {
                AIChatbotAdmin.showNotification('Please select a file first', 'error');
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true).text('Importing...');
            
            var formData = new FormData();
            formData.append('training_file', file);
            formData.append('action', 'ai_chatbot_import_training_data');
            formData.append('nonce', aiChatbotAdmin.nonce);
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        AIChatbotAdmin.showNotification(response.data, 'success');
                        $('#import-training-modal').removeClass('active');
                        $('body').removeClass('modal-open');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        AIChatbotAdmin.showNotification(response.data, 'error');
                    }
                },
                error: function() {
                    AIChatbotAdmin.showNotification('Import failed', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Import');
                }
            });
        },
        
        /**
         * Train model
         */
        trainModel: function(e) {
            e.preventDefault();
            
            if (!confirm('This will train the AI model with your current training data. This process may take a few minutes. Continue?')) {
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true).text('Training...');
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_train_model',
                    nonce: aiChatbotAdmin.nonce
                },
                timeout: 300000, // 5 minutes
                success: function(response) {
                    if (response.success) {
                        AIChatbotAdmin.showNotification(response.data, 'success');
                        $('.last-training-time').text('Just now');
                    } else {
                        AIChatbotAdmin.showNotification(response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'timeout') {
                        AIChatbotAdmin.showNotification('Training is taking longer than expected. Please check back later.', 'warning');
                    } else {
                        AIChatbotAdmin.showNotification('Training failed', 'error');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text('Train Model');
                }
            });
        },
        
        /**
         * Filter training data
         */
        filterTrainingData: function() {
            var filter = $(this).val();
            var $rows = $('.training-data-table tbody tr');
            
            if (filter === 'all') {
                $rows.show();
            } else {
                $rows.hide();
                $rows.filter('[data-status="' + filter + '"]').show();
            }
            
            AIChatbotTraining.updateResultsCount();
        },
        
        /**
         * Search training data
         */
        searchTrainingData: function() {
            var query = $(this).val().toLowerCase();
            var $rows = $('.training-data-table tbody tr');
            
            if (query === '') {
                $rows.show();
            } else {
                $rows.each(function() {
                    var $row = $(this);
                    var question = $row.find('.question-cell').text().toLowerCase();
                    var answer = $row.find('.answer-cell').text().toLowerCase();
                    var intent = $row.find('.intent-cell').text().toLowerCase();
                    
                    if (question.indexOf(query) !== -1 || 
                        answer.indexOf(query) !== -1 || 
                        intent.indexOf(query) !== -1) {
                        $row.show();
                    } else {
                        $row.hide();
                    }
                });
            }
            
            AIChatbotTraining.updateResultsCount();
        },
        
        /**
         * Add tag
         */
        addTag: function(e) {
            e.preventDefault();
            
            var tag = $('#training-tags').val().trim();
            if (tag === '') return;
            
            // Check if tag already exists
            var existingTags = [];
            $('.tag-item').each(function() {
                existingTags.push($(this).text().replace('×', '').trim());
            });
            
            if (existingTags.includes(tag)) {
                AIChatbotAdmin.showNotification('Tag already exists', 'warning');
                return;
            }
            
            AIChatbotTraining.addTagToForm(tag);
            $('#training-tags').val('').focus();
        },
        
        /**
         * Add tag to form
         */
        addTagToForm: function(tag) {
            var $tagContainer = $('.tag-container');
            var $tag = $('<span class="tag-item">' + tag + ' <button type="button" class="remove-tag">×</button></span>');
            $tagContainer.append($tag);
        },
        
        /**
         * Remove tag
         */
        removeTag: function(e) {
            e.preventDefault();
            $(this).parent().remove();
        },
        
        /**
         * Suggest intents
         */
        suggestIntents: function() {
            var query = $(this).val().toLowerCase();
            
            if (query.length < 2) {
                $('.intent-suggestions').hide();
                return;
            }
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_intent_suggestions',
                    nonce: aiChatbotAdmin.nonce,
                    query: query
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var $suggestions = $('.intent-suggestions');
                        $suggestions.empty();
                        
                        response.data.forEach(function(intent) {
                            $suggestions.append('<div class="suggestion-item" data-intent="' + intent + '">' + intent + '</div>');
                        });
                        
                        $suggestions.show();
                    } else {
                        $('.intent-suggestions').hide();
                    }
                }
            });
        },
        
        /**
         * Validate training form
         */
        validateTrainingForm: function(formData) {
            var question = '';
            var answer = '';
            
            formData.forEach(function(field) {
                if (field.name === 'question') question = field.value;
                if (field.name === 'answer') answer = field.value;
            });
            
            if (question.trim() === '') {
                AIChatbotAdmin.showNotification('Question is required', 'error');
                $('#training-question').focus();
                return false;
            }
            
            if (answer.trim() === '') {
                AIChatbotAdmin.showNotification('Answer is required', 'error');
                $('#training-answer').focus();
                return false;
            }
            
            if (question.length < 10) {
                AIChatbotAdmin.showNotification('Question must be at least 10 characters long', 'error');
                $('#training-question').focus();
                return false;
            }
            
            if (answer.length < 10) {
                AIChatbotAdmin.showNotification('Answer must be at least 10 characters long', 'error');
                $('#training-answer').focus();
                return false;
            }
            
            return true;
        },
        
        /**
         * Initialize form validation
         */
        initFormValidation: function() {
            // Real-time validation
            $('#training-question').on('blur', function() {
                var question = $(this).val().trim();
                if (question.length > 0 && question.length < 10) {
                    $(this).addClass('error');
                    $('.question-error').text('Question must be at least 10 characters long').show();
                } else {
                    $(this).removeClass('error');
                    $('.question-error').hide();
                }
            });
            
            $('#training-answer').on('blur', function() {
                var answer = $(this).val().trim();
                if (answer.length > 0 && answer.length < 10) {
                    $(this).addClass('error');
                    $('.answer-error').text('Answer must be at least 10 characters long').show();
                } else {
                    $(this).removeClass('error');
                    $('.answer-error').hide();
                }
            });
            
            // Intent suggestions click handler
            $(document).on('click', '.suggestion-item', function() {
                var intent = $(this).data('intent');
                $('#training-intent').val(intent);
                $('.intent-suggestions').hide();
            });
            
            // Hide suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.intent-input-container').length) {
                    $('.intent-suggestions').hide();
                }
            });
        },
        
        /**
         * Update results count
         */
        updateResultsCount: function() {
            var visible = $('.training-data-table tbody tr:visible').length;
            var total = $('.training-data-table tbody tr').length;
            
            $('.results-count').text('Showing ' + visible + ' of ' + total + ' items');
        },
        
        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
        }
    };
    
    /**
     * Document ready
     */
    $(document).ready(function() {
        AIChatbotTraining.init();
        AIChatbotTraining.updateResultsCount();
    });
    
    /**
     * Make AIChatbotTraining globally available
     */
    window.AIChatbotTraining = AIChatbotTraining;
    
})(jQuery);