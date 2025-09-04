<?php
/**
 * Provide admin training view for the plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get training statistics
$training_stats = $this->get_training_statistics();
$available_intents = $this->get_available_intents();
$available_tags = $this->get_available_tags();
?>

<div class="wrap ai-chatbot-training-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Training Statistics -->
    <div class="training-stats-overview">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-book"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($training_stats['total_items']); ?></div>
                    <div class="stat-label"><?php _e('Total Training Items', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($training_stats['active_items']); ?></div>
                    <div class="stat-label"><?php _e('Active Items', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-category"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($training_stats['intents_count']); ?></div>
                    <div class="stat-label"><?php _e('Unique Intents', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-text last-training-time">
                        <?php 
                        if ($training_stats['last_training'] && $training_stats['last_training'] !== 'Never') {
                            echo human_time_diff(strtotime($training_stats['last_training'])) . ' ' . __('ago', 'ai-website-chatbot');
                        } else {
                            echo esc_html($training_stats['last_training']);
                        }
                        ?>
                    </div>
                    <div class="stat-label"><?php _e('Last Training', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Training Controls -->
    <div class="training-controls">
        <div class="controls-left">
            <button type="button" class="button button-primary add-training-data">
                <span class="dashicons dashicons-plus"></span>
                <?php _e('Add Training Data', 'ai-website-chatbot'); ?>
            </button>
            
            <button type="button" class="button train-model">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Train Model', 'ai-website-chatbot'); ?>
            </button>
        </div>
        
        <div class="controls-right">
            <button type="button" class="button import-training-data">
                <span class="dashicons dashicons-upload"></span>
                <?php _e('Import', 'ai-website-chatbot'); ?>
            </button>
            
            <button type="button" class="button export-training-data" data-format="csv">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export CSV', 'ai-website-chatbot'); ?>
            </button>
            
            <button type="button" class="button export-training-data" data-format="json">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export JSON', 'ai-website-chatbot'); ?>
            </button>
        </div>
    </div>
    
    <!-- Training Form (Hidden by default) -->
    <div class="training-form-container" style="display: none;">
        <div class="training-form-card">
            <h3><?php _e('Add/Edit Training Data', 'ai-website-chatbot'); ?></h3>
            <form id="training-data-form">
                <input type="hidden" name="training_id" value="">
                
                <div class="form-row">
                    <label for="training-question"><?php _e('Question', 'ai-website-chatbot'); ?> <span class="required">*</span></label>
                    <textarea id="training-question" name="question" rows="3" required placeholder="<?php _e('Enter the user question or input...', 'ai-website-chatbot'); ?>"></textarea>
                    <div class="error-message question-error" style="display: none;"></div>
                </div>
                
                <div class="form-row">
                    <label for="training-answer"><?php _e('Answer', 'ai-website-chatbot'); ?> <span class="required">*</span></label>
                    <textarea id="training-answer" name="answer" rows="4" required placeholder="<?php _e('Enter the AI response...', 'ai-website-chatbot'); ?>"></textarea>
                    <div class="error-message answer-error" style="display: none;"></div>
                </div>
                
                <div class="form-row">
                    <label for="training-intent"><?php _e('Intent/Category', 'ai-website-chatbot'); ?></label>
                    <div class="intent-input-container">
                        <input type="text" id="training-intent" name="intent" placeholder="<?php _e('e.g., greeting, support, product_info', 'ai-website-chatbot'); ?>" list="intent-suggestions">
                        <datalist id="intent-suggestions">
                            <?php foreach ($available_intents as $intent): ?>
                                <option value="<?php echo esc_attr($intent); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <div class="intent-suggestions" style="display: none;"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <label for="training-tags"><?php _e('Tags', 'ai-website-chatbot'); ?></label>
                    <div class="tags-input-container">
                        <input type="text" id="training-tags" placeholder="<?php _e('Add a tag and press Enter', 'ai-website-chatbot'); ?>">
                        <button type="button" class="button button-small add-tag"><?php _e('Add', 'ai-website-chatbot'); ?></button>
                        <div class="tag-container"></div>
                    </div>
                    <p class="description"><?php _e('Tags help organize and categorize training data.', 'ai-website-chatbot'); ?></p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Save Training Data', 'ai-website-chatbot'); ?>
                    </button>
                    <button type="button" class="button cancel-add">
                        <?php _e('Cancel', 'ai-website-chatbot'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Filter and Search -->
    <div class="training-filters">
        <div class="filters-left">
            <label for="training-filter"><?php _e('Filter by Status:', 'ai-website-chatbot'); ?></label>
            <select id="training-filter">
                <option value="all"><?php _e('All Items', 'ai-website-chatbot'); ?></option>
                <option value="active"><?php _e('Active', 'ai-website-chatbot'); ?></option>
                <option value="inactive"><?php _e('Inactive', 'ai-website-chatbot'); ?></option>
            </select>
        </div>
        
        <div class="filters-right">
            <label for="training-search"><?php _e('Search:', 'ai-website-chatbot'); ?></label>
            <input type="text" id="training-search" placeholder="<?php _e('Search questions, answers, or intents...', 'ai-website-chatbot'); ?>">
        </div>
    </div>
    
    <!-- Training Data Table -->
    <div class="training-data-table-container">
        <form class="bulk-action-form" method="post">
            <?php wp_nonce_field('ai_chatbot_training_bulk_action', '_wpnonce'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option value="-1"><?php _e('Bulk Actions', 'ai-website-chatbot'); ?></option>
                        <option value="delete"><?php _e('Delete', 'ai-website-chatbot'); ?></option>
                        <option value="activate"><?php _e('Activate', 'ai-website-chatbot'); ?></option>
                        <option value="deactivate"><?php _e('Deactivate', 'ai-website-chatbot'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'ai-website-chatbot'); ?>">
                </div>
                
                <div class="alignright">
                    <span class="results-count"></span>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped training-data-table">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All', 'ai-website-chatbot'); ?></label>
                            <input id="cb-select-all-1" type="checkbox" class="select-all">
                        </td>
                        <th scope="col" class="manage-column column-question"><?php _e('Question', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-answer"><?php _e('Answer', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-intent"><?php _e('Intent', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-status"><?php _e('Status', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-date"><?php _e('Created', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'ai-website-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($training_data)): ?>
                        <?php foreach ($training_data as $item): ?>
                            <tr data-status="<?php echo esc_attr($item['status']); ?>">
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="training_ids[]" value="<?php echo esc_attr($item['id']); ?>">
                                </th>
                                <td class="question-cell">
                                    <strong><?php echo esc_html(wp_trim_words($item['question'], 10)); ?></strong>
                                    <?php if (strlen($item['question']) > 50): ?>
                                        <div class="question-preview" style="display: none;">
                                            <?php echo esc_html($item['question']); ?>
                                        </div>
                                        <button type="button" class="button-link toggle-preview"><?php _e('Show more', 'ai-website-chatbot'); ?></button>
                                    <?php endif; ?>
                                </td>
                                <td class="answer-cell">
                                    <?php echo esc_html(wp_trim_words($item['answer'], 8)); ?>
                                    <?php if (strlen($item['answer']) > 40): ?>
                                        <div class="answer-preview" style="display: none;">
                                            <?php echo esc_html($item['answer']); ?>
                                        </div>
                                        <button type="button" class="button-link toggle-preview"><?php _e('Show more', 'ai-website-chatbot'); ?></button>
                                    <?php endif; ?>
                                </td>
                                <td class="intent-cell">
                                    <?php if (!empty($item['intent'])): ?>
                                        <span class="intent-badge"><?php echo esc_html($item['intent']); ?></span>
                                    <?php else: ?>
                                        <span class="no-intent"><?php _e('No intent', 'ai-website-chatbot'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($item['status']); ?>">
                                        <?php echo esc_html(ucfirst($item['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item['created_at']))); ?>
                                </td>
                                <td class="actions-cell">
                                    <button type="button" class="button button-small edit-training-data" data-id="<?php echo esc_attr($item['id']); ?>">
                                        <?php _e('Edit', 'ai-website-chatbot'); ?>
                                    </button>
                                    <button type="button" class="button button-small button-link-delete delete-training-data" data-id="<?php echo esc_attr($item['id']); ?>">
                                        <?php _e('Delete', 'ai-website-chatbot'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-items">
                            <td colspan="7" class="no-training-data">
                                <div class="no-data-message">
                                    <span class="dashicons dashicons-book-alt"></span>
                                    <h3><?php _e('No Training Data Yet', 'ai-website-chatbot'); ?></h3>
                                    <p><?php _e('Start by adding some training data to improve your chatbot\'s responses.', 'ai-website-chatbot'); ?></p>
                                    <button type="button" class="button button-primary add-training-data">
                                        <?php _e('Add First Training Data', 'ai-website-chatbot'); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if (!empty($training_data) && $total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(__('%d items', 'ai-website-chatbot'), $total_items); ?>
                        </span>
                        
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        
                        if ($page_links) {
                            echo '<span class="pagination-links">' . $page_links . '</span>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="import-training-modal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Import Training Data', 'ai-website-chatbot'); ?></h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <div class="import-instructions">
                <h4><?php _e('Supported Formats', 'ai-website-chatbot'); ?></h4>
                <ul>
                    <li><strong>CSV:</strong> <?php _e('Columns: question, answer, intent, tags', 'ai-website-chatbot'); ?></li>
                    <li><strong>JSON:</strong> <?php _e('Array of objects with question, answer, intent, tags fields', 'ai-website-chatbot'); ?></li>
                </ul>
                <p class="description"><?php _e('The system will automatically detect the file format and import the data.', 'ai-website-chatbot'); ?></p>
            </div>
            
            <div class="file-upload-area">
                <input type="file" id="training-import-file" accept=".csv,.json" style="display: none;">
                <div class="upload-dropzone" onclick="$('#training-import-file').click();">
                    <span class="dashicons dashicons-upload"></span>
                    <p><?php _e('Click to select a file or drag and drop', 'ai-website-chatbot'); ?></p>
                    <p class="supported-formats"><?php _e('Supported: .csv, .json (max 10MB)', 'ai-website-chatbot'); ?></p>
                </div>
            </div>
            
            <div class="file-info" style="display: none;">
                <div class="file-details">
                    <span class="dashicons dashicons-media-document"></span>
                    <div class="file-meta">
                        <strong class="file-name"></strong>
                        <span class="file-size"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button" data-modal-close><?php _e('Cancel', 'ai-website-chatbot'); ?></button>
            <button type="button" class="button button-primary process-import" disabled>
                <?php _e('Import', 'ai-website-chatbot'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.ai-chatbot-training-wrap {
    max-width: 1400px;
}

.training-stats-overview {
    margin-bottom: 30px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #0073aa;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon .dashicons {
    font-size: 20px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.stat-text {
    font-size: 16px;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    color: #666;
    font-size: 13px;
}

.training-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
}

.controls-left, .controls-right {
    display: flex;
    gap: 10px;
}

.training-form-container {
    margin: 20px 0;
}

.training-form-card {
    background: #fff;
    padding: 25px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.training-form-card h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.form-row {
    margin-bottom: 20px;
}

.form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.required {
    color: #dc3232;
}

.form-row input, .form-row textarea, .form-row select {
    width: 100%;
    max-width: 600px;
}

.intent-input-container, .tags-input-container {
    position: relative;
}

.intent-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
}

.suggestion-item {
    padding: 8px 12px;
    cursor: pointer;
}

.suggestion-item:hover {
    background: #f0f0f0;
}

.tags-input-container {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    flex-wrap: wrap;
}

.tags-input-container input {
    flex: 1;
    min-width: 200px;
}

.tag-container {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 10px;
    width: 100%;
}

.tag-item {
    background: #0073aa;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.remove-tag {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 14px;
    padding: 0;
    line-height: 1;
}

.form-actions {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.training-filters {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    padding: 10px 0;
}

.training-data-table-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    overflow: hidden;
}

.question-preview, .answer-preview {
    display: block;
    margin-top: 5px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 12px;
    color: #555;
}

.toggle-preview {
    color: #0073aa;
    text-decoration: none;
    font-size: 11px;
    margin-left: 5px;
}

.intent-badge {
    background: #e7f3ff;
    color: #0073aa;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
}

.no-intent {
    color: #999;
    font-style: italic;
    font-size: 12px;
}

.status-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.actions-cell {
    white-space: nowrap;
}

.actions-cell .button {
    margin-right: 5px;
}

.no-training-data {
    padding: 40px;
    text-align: center;
}

.no-data-message {
    color: #666;
}

.no-data-message .dashicons {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 15px;
}

.no-data-message h3 {
    margin-bottom: 10px;
    color: #333;
}

.results-count {
    color: #666;
    font-size: 13px;
}

.error-message {
    color: #dc3232;
    font-size: 12px;
    margin-top: 5px;
}

.error {
    border-color: #dc3232 !important;
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
    display: none;
}

.modal.active {
    display: block;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    max-width: 500px;
    width: 90%;
    max-height: 80%;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #ddd;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.modal-close:hover {
    background: #f0f0f0;
}

.modal-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.upload-dropzone {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.upload-dropzone:hover {
    border-color: #0073aa;
    background: #f8f9ff;
}

.upload-dropzone .dashicons {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 10px;
}

.supported-formats {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.file-info {
    margin-top: 15px;
}

.file-details {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f0f0f0;
    border-radius: 5px;
}

.file-meta strong {
    display: block;
}

.file-meta span {
    font-size: 12px;
    color: #666;
}

@media (max-width: 768px) {
    .training-controls, .training-filters {
        flex-direction: column;
        gap: 15px;
    }
    
    .controls-left, .controls-right, .filters-left, .filters-right {
        width: 100%;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .training-data-table {
        font-size: 14px;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add training data button
    $('.add-training-data').on('click', function(e) {
        e.preventDefault();
        
        $('#training-data-form')[0].reset();
        $('#training-data-form').find('input[name="training_id"]').val('');
        $('.training-form-container').slideDown();
        $('.add-training-data').prop('disabled', true);
        
        // Focus on first field
        $('#training-question').focus();
    });
    
    // Cancel add/edit
    $('.cancel-add').on('click', function(e) {
        e.preventDefault();
        
        $('.training-form-container').slideUp();
        $('.add-training-data').prop('disabled', false);
    });
    
    // Submit training data form
    $('#training-data-form').on('submit', function(e) {
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
        var question = $('#training-question').val().trim();
        var answer = $('#training-answer').val().trim();
        
        if (question.length < 10) {
            $('#training-question').addClass('error');
            $('.question-error').text('Question must be at least 10 characters long').show();
            return;
        }
        
        if (answer.length < 10) {
            $('#training-answer').addClass('error');
            $('.answer-error').text('Answer must be at least 10 characters long').show();
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
                    $('.training-form-container').slideUp();
                    $('.add-training-data').prop('disabled', false);
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
    });
    
    // Edit training data
    $(document).on('click', '.edit-training-data', function(e) {
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
                            addTagToForm(tag);
                        });
                    }
                    
                    $('.training-form-container').slideDown();
                    $('.add-training-data').prop('disabled', true);
                    $('#training-question').focus();
                }
            }
        });
    });
    
    // Delete training data
    $(document).on('click', '.delete-training-data', function(e) {
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
                        updateResultsCount();
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
    });
    
    // Toggle preview
    $(document).on('click', '.toggle-preview', function() {
        var $button = $(this);
        var $preview = $button.siblings('.question-preview, .answer-preview');
        
        if ($preview.is(':visible')) {
            $preview.hide();
            $button.text('Show more');
        } else {
            $preview.show();
            $button.text('Show less');
        }
    });
    
    // Filter training data
    $('#training-filter').on('change', function() {
        var filter = $(this).val();
        var $rows = $('.training-data-table tbody tr');
        
        if (filter === 'all') {
            $rows.show();
        } else {
            $rows.hide();
            $rows.filter('[data-status="' + filter + '"]').show();
        }
        
        updateResultsCount();
    });
    
    // Search training data
    $('#training-search').on('input', function() {
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
        
        updateResultsCount();
    });
    
    // Add tag functionality
    $('.add-tag').on('click', function(e) {
        e.preventDefault();
        
        var tag = $('#training-tags').val().trim();
        if (tag === '') return;
        
        // Check if tag already exists
        var existingTags = [];
        $('.tag-item').each(function() {
            existingTags.push($(this).text().replace('×', '').trim());
        });
        
        if (existingTags.includes(tag)) {
            AIChatbotAdmin.showNotification('Tag already exists', 'warning', 2000);
            return;
        }
        
        addTagToForm(tag);
        $('#training-tags').val('').focus();
    });
    
    // Remove tag
    $(document).on('click', '.remove-tag', function(e) {
        e.preventDefault();
        $(this).parent().remove();
    });
    
    // Enter key on tags input
    $('#training-tags').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('.add-tag').click();
        }
    });
    
    // Import training data modal
    $('.import-training-data').on('click', function(e) {
        e.preventDefault();
        $('#import-training-modal').addClass('active');
        $('body').addClass('modal-open');
    });
    
    // File upload handling
    $('#training-import-file').on('change', function() {
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
        $('.file-size').text(formatFileSize(file.size));
        $('.process-import').prop('disabled', false);
    });
    
    // Process import
    $('.process-import').on('click', function(e) {
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
    });
    
    // Export training data
    $('.export-training-data').on('click', function(e) {
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
                var filename = 'training-data-' + new Date().toISOString().slice(0, 10) + '.' + format;
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
                $button.prop('disabled', false).text('Export ' + format.toUpperCase());
            }
        });
    });
    
    // Train model
    $('.train-model').on('click', function(e) {
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
    });
    
    // Modal controls
    $(document).on('click', '[data-modal-close], .modal-overlay', function(e) {
        if (e.target === e.currentTarget) {
            $('.modal.active').removeClass('active');
            $('body').removeClass('modal-open');
        }
    });
    
    // Form validation
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
    
    // Helper functions
    function addTagToForm(tag) {
        var $tagContainer = $('.tag-container');
        var $tag = $('<span class="tag-item">' + tag + ' <button type="button" class="remove-tag">×</button></span>');
        $tagContainer.append($tag);
    }
    
    function updateResultsCount() {
        var visible = $('.training-data-table tbody tr:visible').length;
        var total = $('.training-data-table tbody tr').length;
        
        $('.results-count').text('Showing ' + visible + ' of ' + total + ' items');
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Initialize results count
    updateResultsCount();
});
</script>