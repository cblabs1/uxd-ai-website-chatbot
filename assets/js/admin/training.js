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
            this.initDragAndDrop();
            this.updateResultsCount();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            console.log('AIChatbotTraining: Binding events...');
    
            // Remove any existing bindings first
            $(document).off('.aichatbot-training');
            
            // Add training data
            $(document).on('click.aichatbot-training', '.add-training-data', this.showAddForm.bind(this));
            $(document).on('submit.aichatbot-training', '#training-data-form', this.submitTrainingData.bind(this));
            $(document).on('click.aichatbot-training', '.cancel-add', this.hideAddForm.bind(this));
            
            // Edit training data
            $(document).on('click.aichatbot-training', '.edit-training-data', this.showEditForm.bind(this));
            $(document).on('click.aichatbot-training', '.cancel-edit', this.hideEditForm.bind(this));
            
            // Delete training data
            $(document).on('click.aichatbot-training', '.delete-training-data', this.deleteTrainingData.bind(this));
            
            // Import/Export - Fix the file upload binding
            $(document).on('click.aichatbot-training', '.import-training-data', this.showImportModal.bind(this));
            $(document).on('click.aichatbot-training', '.export-training-data', this.exportTrainingData.bind(this));
            $(document).on('change.aichatbot-training', '#training-import-file', this.handleFileUpload.bind(this));
            $(document).on('click.aichatbot-training', '.process-import', this.processImport.bind(this));
            
            // Modal close events
            $(document).on('click.aichatbot-training', '[data-modal-close]', function() {
                AIChatbotTraining.closeModal();
            });
            
            // Click on dropzone to trigger file input
            $(document).on('click.aichatbot-training', '.upload-dropzone', function() {
                $('#training-import-file').click();
            });
            
            // Train model
            $(document).on('click.aichatbot-training', '.train-model', this.trainModel.bind(this));
            
            // Filter and search
            $(document).on('change.aichatbot-training', '#training-filter', this.filterTrainingData.bind(this));
            $(document).on('input.aichatbot-training', '#training-search', this.debounce(this.searchTrainingData.bind(this), 300));
            
            // Tag management
            $(document).on('click.aichatbot-training', '.add-tag', this.addTag.bind(this));
            $(document).on('click.aichatbot-training', '.remove-tag', this.removeTag.bind(this));
            
            // Intent suggestions
            $(document).on('input.aichatbot-training', '#training-intent', this.suggestIntents.bind(this));
            
            console.log('AIChatbotTraining: All events bound');
        },

        closeModal: function() {
            console.log('AIChatbotTraining: Closing modal');
            $('.modal').removeClass('active');
            $('body').removeClass('modal-open');
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
            console.log('AIChatbotTraining: Edit training data clicked');
            
            var $button = $(this);
            var trainingId = $button.data('id');
            
            console.log('Editing training ID:', trainingId);
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_training_data',
                    nonce: aiChatbotAdmin.nonce,
                    training_id: trainingId
                },
                success: function(response) {
                    console.log('Get training data response:', response);
                    if (response.success) {
                        var data = response.data;
                        
                        $('#training-question').val(data.question);
                        $('#training-answer').val(data.answer);
                        $('#training-intent').val(data.intent || '');
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
                    } else {
                        console.error('Failed to get training data:', response.data);
                        if (typeof AIChatbotAdmin !== 'undefined' && AIChatbotAdmin.showNotification) {
                            AIChatbotAdmin.showNotification(response.data, 'error');
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Get training data error:', error);
                    console.error('Response:', xhr.responseText);
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
         * Show import modal
         */
        showImportModal: function(e) {
            e.preventDefault();
            console.log('AIChatbotTraining: Show import modal clicked');
            
            $('#import-training-modal').addClass('active');
            $('body').addClass('modal-open');
            
            // Reset form
            $('#training-import-file').val('');
            $('.file-info').hide();
            $('.process-import').prop('disabled', true);
            
            // Ensure drag and drop is initialized for the modal
            this.initDragAndDrop();
        },
        
        /**
         * Handle file upload with proper validation
         */
        handleFileUpload: function(e) {
            console.log('AIChatbotTraining: File upload changed');
            console.log('Event target:', e.target);
            console.log('Files:', e.target.files);
            
            var file = e.target.files[0];
            if (!file) {
                console.log('No file selected');
                $('.file-info').hide();
                $('.process-import').prop('disabled', true);
                return;
            }
            
            console.log('File selected:', file.name, file.type, file.size);
            
            // File size validation (10MB max)
            var maxSize = 10 * 1024 * 1024; // 10MB
            if (file.size > maxSize) {
                console.log('File too large:', file.size);
                AIChatbotAdmin.showNotification('File size must be less than 10MB', 'error');
                $(e.target).val('');
                $('.file-info').hide();
                $('.process-import').prop('disabled', true);
                return;
            }
            
            // File type validation
            var allowedTypes = ['text/csv', 'application/json', 'text/plain', ''];
            var allowedExtensions = ['csv', 'json'];
            var fileName = file.name.toLowerCase();
            var fileExtension = fileName.split('.').pop();
            
            console.log('File type:', file.type, 'Extension:', fileExtension);
            
            // Check both MIME type and extension
            var isValidType = allowedTypes.includes(file.type);
            var isValidExtension = allowedExtensions.includes(fileExtension);
            
            if (!isValidType && !isValidExtension) {
                console.log('Invalid file type');
                AIChatbotAdmin.showNotification('Please select a valid CSV or JSON file', 'error');
                $(e.target).val('');
                $('.file-info').hide();
                $('.process-import').prop('disabled', true);
                return;
            }
            
            // Show file info and enable import button
            console.log('File validation passed, showing file info');
            $('.file-info').show();
            $('.file-name').text(file.name);
            $('.file-size').text(AIChatbotTraining.formatFileSize(file.size));
            $('.process-import').prop('disabled', false);
        },

        /**
         * Process file import with better error handling
         */
        processImport: function(e) {
            e.preventDefault();
            console.log('AIChatbotTraining: Process import clicked');
            
            var fileInput = $('#training-import-file')[0];
            var file = fileInput.files[0];
            
            if (!file) {
                if (typeof AIChatbotAdmin !== 'undefined' && AIChatbotAdmin.showNotification) {
                    AIChatbotAdmin.showNotification('Please select a file first', 'error');
                } else {
                    alert('Please select a file first');
                }
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true).text('Importing...');
            
            // Create FormData object
            var formData = new FormData();
            formData.append('training_file', file);
            formData.append('action', 'ai_chatbot_import_training_data');
            formData.append('nonce', aiChatbotAdmin.nonce);
            
            console.log('Starting import for file:', file.name);
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 60000, // 60 second timeout
                success: function(response) {
                    console.log('Import response:', response);
                    
                    if (response.success) {
                        if (typeof AIChatbotAdmin !== 'undefined' && AIChatbotAdmin.showNotification) {
                            AIChatbotAdmin.showNotification(response.data, 'success');
                        } else {
                            alert('Success: ' + response.data);
                        }
                        $('#import-training-modal').removeClass('active');
                        $('body').removeClass('modal-open');
                        
                        // Reset form
                        $('#training-import-file').val('');
                        $('.file-info').hide();
                        
                        // Refresh the page after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        var errorMsg = response.data || 'Import failed';
                        if (typeof AIChatbotAdmin !== 'undefined' && AIChatbotAdmin.showNotification) {
                            AIChatbotAdmin.showNotification(errorMsg, 'error');
                        } else {
                            alert('Error: ' + errorMsg);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Import error:', status, error);
                    console.error('Response:', xhr.responseText);
                    
                    var errorMessage = 'Import failed';
                    if (xhr.responseText) {
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            errorMessage = errorResponse.data || errorMessage;
                        } catch (e) {
                            errorMessage = 'Import failed: ' + error;
                        }
                    }
                    
                    if (typeof AIChatbotAdmin !== 'undefined' && AIChatbotAdmin.showNotification) {
                        AIChatbotAdmin.showNotification(errorMessage, 'error');
                    } else {
                        alert('Error: ' + errorMessage);
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text('Import');
                }
            });
        },
        
        /**
         * Initialize drag and drop functionality
         */
        initDragAndDrop: function() {
            var $dropzone = $('.upload-dropzone');
            var $fileInput = $('#training-import-file');
            
            console.log('Initializing drag and drop...');
            console.log('Dropzone elements found:', $dropzone.length);
            console.log('File input elements found:', $fileInput.length);
            
            if ($dropzone.length === 0) {
                console.log('No dropzone found, skipping drag and drop initialization');
                return;
            }
            
            // Remove any existing drag event handlers
            $dropzone.off('dragenter dragover dragleave drop click');
            $(document).off('dragenter.dragdrop dragover.dragdrop drop.dragdrop');
            
            // Prevent default drag behaviors on document
            $(document).on('dragenter.dragdrop dragover.dragdrop drop.dragdrop', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
            
            // Dropzone hover effects
            $dropzone.on('dragenter dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Drag enter/over dropzone');
                $(this).addClass('drag-hover');
            });
            
            $dropzone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Only remove hover if we're leaving the dropzone itself
                if (!$.contains(this, e.relatedTarget)) {
                    console.log('Drag leave dropzone');
                    $(this).removeClass('drag-hover');
                }
            });
            
            // Handle file drop
            $dropzone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('File dropped on dropzone');
                $(this).removeClass('drag-hover');
                
                var files = e.originalEvent.dataTransfer.files;
                console.log('Dropped files:', files.length);
                
                if (files.length > 0) {
                    // Manually set the files to the input and trigger change
                    $fileInput[0].files = files;
                    $fileInput.trigger('change');
                }
            });
            
            // Handle click on dropzone
            $dropzone.on('click', function(e) {
                e.preventDefault();
                console.log('Dropzone clicked, opening file dialog');
                $fileInput.click();
            });
            
            console.log('Drag and drop initialization complete');
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