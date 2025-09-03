/**
 * AI Chatbot Training JavaScript
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initTrainingInterface();
        initSearch();
        initDragDrop();
        
        // Add/Edit training data
        $('#add-training-pair, #add-first-pair').on('click', function() {
            openTrainingModal();
        });

        $('.edit-item').on('click', function() {
            const id = $(this).data('id');
            editTrainingItem(id);
        });

        // Delete training data
        $('.delete-item').on('click', function() {
            const id = $(this).data('id');
            deleteTrainingItem(id);
        });

        // Bulk actions
        $('#select-all-training').on('change', function() {
            $('.training-checkbox').prop('checked', $(this).prop('checked'));
        });

        $('#delete-selected').on('click', function() {
            deleteSelectedItems();
        });

        // Import/Export
        $('#import-training-data').on('click', function() {
            $('#import-modal').show();
        });

        $('#export-training-data').on('click', function() {
            exportTrainingData();
        });

        // Form submissions
        $('#training-form').on('submit', function(e) {
            e.preventDefault();
            saveTrainingData();
        });

        $('#import-form').on('submit', function(e) {
            e.preventDefault();
            importTrainingData();
        });

        // File preview
        $('#training-file').on('change', function() {
            previewImportFile(this.files[0]);
        });
    });

    // Initialize training interface
    function initTrainingInterface() {
        // Make training items sortable
        if ($.fn.sortable) {
            $('#training-items').sortable({
                handle: '.item-header',
                update: function(event, ui) {
                    updateTrainingOrder();
                }
            });
        }

        // Initialize category autocomplete
        if ($.fn.autocomplete) {
            $('#training-category').autocomplete({
                source: getExistingCategories()
            });
        }
    }

    // Initialize search functionality
    function initSearch() {
        let searchTimeout;
        
        $('#training-search').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().toLowerCase();
            
            searchTimeout = setTimeout(function() {
                filterTrainingItems(query);
            }, 300);
        });

        $('#category-filter').on('change', function() {
            const category = $(this).val();
            filterByCategory(category);
        });
    }

    // Filter training items by search query
    function filterTrainingItems(query) {
        $('.training-item').each(function() {
            const item = $(this);
            const question = item.find('.question p').text().toLowerCase();
            const answer = item.find('.answer p').text().toLowerCase();
            const category = item.data('category').toLowerCase();
            
            if (question.includes(query) || answer.includes(query) || category.includes(query)) {
                item.show();
            } else {
                item.hide();
            }
        });
        
        updateResultsCount();
    }

    // Filter training items by category
    function filterByCategory(category) {
        if (category === '') {
            $('.training-item').show();
        } else {
            $('.training-item').each(function() {
                if ($(this).data('category') === category) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
        
        updateResultsCount();
    }

    // Update visible results count
    function updateResultsCount() {
        const visibleItems = $('.training-item:visible').length;
        const totalItems = $('.training-item').length;
        
        $('#results-count').text(visibleItems + ' of ' + totalItems + ' items shown');
    }

    // Open training modal for new item
    function openTrainingModal(data) {
        $('#modal-title').text(data ? 'Edit Training Data' : 'Add Training Data');
        $('#training-id').val(data ? data.id : '');
        $('#training-question').val(data ? data.question : '');
        $('#training-answer').val(data ? data.answer : '');
        $('#training-category').val(data ? data.category : '');
        
        $('#training-modal').show();
        $('#training-question').focus();
    }

    // Edit existing training item
    function editTrainingItem(id) {
        // Get data from the item
        const item = $('.training-item[data-id="' + id + '"]');
        const data = {
            id: id,
            question: item.find('.question p').text(),
            answer: item.find('.answer p').text(),
            category: item.data('category')
        };
        
        openTrainingModal(data);
    }

    // Delete training item
    function deleteTrainingItem(id) {
        if (!confirm('Are you sure you want to delete this training data? This cannot be undone.')) {
            return;
        }

        $.ajax({
            url: ai_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_chatbot_delete_training_data',
                id: id,
                nonce: ai_chatbot_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.training-item[data-id="' + id + '"]').fadeOut(function() {
                        $(this).remove();
                        updateTrainingStats();
                    });
                    window.AIChatbotAdmin.showNotification('Training data deleted successfully', 'success');
                } else {
                    window.AIChatbotAdmin.showNotification('Failed to delete training data: ' + response.data, 'error');
                }
            },
            error: function() {
                window.AIChatbotAdmin.showNotification('Failed to delete training data', 'error');
            }
        });
    }

    // Delete selected items
    function deleteSelectedItems() {
        const selectedIds = [];
        $('.training-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            window.AIChatbotAdmin.showNotification('Please select items to delete', 'warning');
            return;
        }

        if (!confirm('Are you sure you want to delete ' + selectedIds.length + ' training items? This cannot be undone.')) {
            return;
        }

        // Delete items one by one
        let deletedCount = 0;
        selectedIds.forEach(function(id) {
            $.ajax({
                url: ai_chatbot_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_delete_training_data',
                    id: id,
                    nonce: ai_chatbot_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.training-item[data-id="' + id + '"]').fadeOut(function() {
                            $(this).remove();
                        });
                        deletedCount++;
                        
                        if (deletedCount === selectedIds.length) {
                            updateTrainingStats();
                            window.AIChatbotAdmin.showNotification(deletedCount + ' training items deleted successfully', 'success');
                        }
                    }
                }
            });
        });
    }

    // Save training data
    function saveTrainingData() {
        const formData = {
            action: 'ai_chatbot_save_training_data',
            id: $('#training-id').val(),
            question: $('#training-question').val().trim(),
            answer: $('#training-answer').val().trim(),
            category: $('#training-category').val().trim(),
            nonce: ai_chatbot_admin.nonce
        };

        // Validate required fields
        if (!formData.question || !formData.answer) {
            window.AIChatbotAdmin.showNotification('Question and answer are required', 'error');
            return;
        }

        const submitButton = $('#training-form button[type="submit"]');
        submitButton.prop('disabled', true).text('Saving...');

        $.ajax({
            url: ai_chatbot_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#training-modal').hide();
                    
                    if (formData.id) {
                        // Update existing item
                        updateTrainingItemDisplay(formData);
                    } else {
                        // Add new item (reload page for simplicity)
                        location.reload();
                    }
                    
                    window.AIChatbotAdmin.showNotification('Training data saved successfully', 'success');
                } else {
                    window.AIChatbotAdmin.showNotification('Failed to save training data: ' + response.data, 'error');
                }
            },
            error: function() {
                window.AIChatbotAdmin.showNotification('Failed to save training data', 'error');
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Save Training Data');
            }
        });
    }

    // Update training item display after edit
    function updateTrainingItemDisplay(data) {
        const item = $('.training-item[data-id="' + data.id + '"]');
        
        item.find('.question p').text(data.question);
        item.find('.answer p').text(data.answer);
        item.data('category', data.category);
        
        // Update category tag
        const categoryTag = item.find('.category-tag');
        if (data.category) {
            if (categoryTag.length) {
                categoryTag.text(data.category);
            } else {
                item.find('.item-meta').prepend('<span class="category-tag">' + data.category + '</span>');
            }
        } else {
            categoryTag.remove();
        }
    }

    // Initialize drag and drop for file import
    function initDragDrop() {
        const dropZone = $('#import-modal .modal-body');
        
        dropZone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });
        
        dropZone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });
        
        dropZone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $('#training-file')[0].files = files;
                previewImportFile(files[0]);
            }
        });
    }

    // Preview import file content
    function previewImportFile(file) {
        if (!file) return;
        
        const reader = new FileReader();
        const preview = $('#import-preview');
        
        reader.onload = function(e) {
            const content = e.target.result;
            let previewHtml = '';
            
            if (file.name.endsWith('.csv')) {
                previewHtml = previewCSV(content);
            } else if (file.name.endsWith('.json')) {
                previewHtml = previewJSON(content);
            }
            
            if (previewHtml) {
                $('#preview-content').html(previewHtml);
                preview.show();
            }
        };
        
        reader.readAsText(file);
    }

    // Preview CSV content
    function previewCSV(content) {
        const lines = content.split('\n').slice(0, 5); // Show first 5 lines
        let html = '<table class="preview-table"><thead><tr><th>Question</th><th>Answer</th><th>Category</th></tr></thead><tbody>';
        
        lines.forEach(function(line, index) {
            if (line.trim()) {
                const columns = line.split(',');
                html += '<tr>';
                html += '<td>' + (columns[0] || '').replace(/"/g, '') + '</td>';
                html += '<td>' + (columns[1] || '').replace(/"/g, '') + '</td>';
                html += '<td>' + (columns[2] || '').replace(/"/g, '') + '</td>';
                html += '</tr>';
            }
        });
        
        html += '</tbody></table>';
        html += '<p class="preview-note">Showing first 5 rows...</p>';
        
        return html;
    }

    // Preview JSON content
    function previewJSON(content) {
        try {
            const data = JSON.parse(content);
            const items = Array.isArray(data) ? data.slice(0, 5) : [data];
            
            let html = '<div class="json-preview">';
            items.forEach(function(item) {
                html += '<div class="json-item">';
                html += '<strong>Question:</strong> ' + (item.question || '') + '<br>';
                html += '<strong>Answer:</strong> ' + (item.answer || '') + '<br>';
                html += '<strong>Category:</strong> ' + (item.category || '') + '<br>';
                html += '</div>';
            });
            html += '</div>';
            html += '<p class="preview-note">Showing first 5 items...</p>';
            
            return html;
        } catch (e) {
            return '<div class="error">Invalid JSON format</div>';
        }
    }

    // Import training data
    function importTrainingData() {
        const fileInput = $('#training-file')[0];
        
        if (!fileInput.files.length) {
            window.AIChatbotAdmin.showNotification('Please select a file to import', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'ai_chatbot_import_training_data');
        formData.append('training_file', fileInput.files[0]);
        formData.append('nonce', ai_chatbot_admin.nonce);

        const submitButton = $('#import-form button[type="submit"]');
        submitButton.prop('disabled', true).text('Importing...');

        $.ajax({
            url: ai_chatbot_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#import-modal').hide();
                    window.AIChatbotAdmin.showNotification('Training data imported successfully', 'success');
                    location.reload(); // Reload to show imported data
                } else {
                    window.AIChatbotAdmin.showNotification('Import failed: ' + response.data, 'error');
                }
            },
            error: function() {
                window.AIChatbotAdmin.showNotification('Import failed', 'error');
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Import Data');
            }
        });
    }

    // Export training data
    function exportTrainingData() {
        const format = 'csv'; // Default to CSV
        const exportUrl = ai_chatbot_admin.ajax_url + 
            '?action=ai_chatbot_export_training_data' +
            '&format=' + format +
            '&nonce=' + ai_chatbot_admin.nonce;
        
        // Create temporary download link
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = 'chatbot-training-data.' + format;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        window.AIChatbotAdmin.showNotification('Training data exported successfully', 'success');
    }

    // Update training statistics
    function updateTrainingStats() {
        const totalItems = $('.training-item').length;
        const categories = new Set();
        
        $('.training-item').each(function() {
            const category = $(this).data('category');
            if (category) categories.add(category);
        });

        $('.stat-card:first .stat-number').text(totalItems);
        $('.stat-card:nth-child(2) .stat-number').text(categories.size);
    }

    // Get existing categories for autocomplete
    function getExistingCategories() {
        const categories = [];
        $('.training-item').each(function() {
            const category = $(this).data('category');
            if (category && categories.indexOf(category) === -1) {
                categories.push(category);
            }
        });
        return categories;
    }

    // Update training order after sorting
    function updateTrainingOrder() {
        const order = [];
        $('.training-item').each(function(index) {
            order.push({
                id: $(this).data('id'),
                order: index
            });
        });
        
        // Save new order to server
        $.ajax({
            url: ai_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_chatbot_update_training_order',
                order: order,
                nonce: ai_chatbot_admin.nonce
            }
        });
    }

})(jQuery);
