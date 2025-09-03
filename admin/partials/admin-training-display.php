<?php
/**
 * Training page display
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$training_data = $this->get_training_data();
$categories = $this->get_categories();
?>

<div class="wrap">
    <h1><?php _e('AI Chatbot Training', 'ai-website-chatbot'); ?></h1>

    <div class="ai-chatbot-training">
        <!-- Training controls -->
        <div class="training-controls">
            <div class="control-group">
                <button type="button" class="button button-primary" id="add-training-pair">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Training Data', 'ai-website-chatbot'); ?>
                </button>
                <button type="button" class="button" id="import-training-data">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Import Data', 'ai-website-chatbot'); ?>
                </button>
                <button type="button" class="button" id="export-training-data">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Data', 'ai-website-chatbot'); ?>
                </button>
            </div>

            <div class="filter-group">
                <select id="category-filter">
                    <option value=""><?php _e('All Categories', 'ai-website-chatbot'); ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" id="training-search" placeholder="<?php _e('Search training data...', 'ai-website-chatbot'); ?>">
            </div>
        </div>

        <!-- Training statistics -->
        <div class="training-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($training_data); ?></div>
                <div class="stat-label"><?php _e('Training Pairs', 'ai-website-chatbot'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($categories); ?></div>
                <div class="stat-label"><?php _e('Categories', 'ai-website-chatbot'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_map('str_word_count', wp_list_pluck($training_data, 'answer'))); ?></div>
                <div class="stat-label"><?php _e('Total Words', 'ai-website-chatbot'); ?></div>
            </div>
        </div>

        <!-- Training data list -->
        <div class="training-list">
            <div class="training-header">
                <h3><?php _e('Training Data', 'ai-website-chatbot'); ?></h3>
            </div>

            <div class="training-items" id="training-items">
                <?php if (!empty($training_data)): ?>
                    <?php foreach ($training_data as $item): ?>
                        <div class="training-item" data-id="<?php echo esc_attr($item->id); ?>" data-category="<?php echo esc_attr($item->category); ?>">
                            <div class="item-header">
                                <div class="item-meta">
                                    <?php if (!empty($item->category)): ?>
                                        <span class="category-tag"><?php echo esc_html($item->category); ?></span>
                                    <?php endif; ?>
                                    <span class="item-date"><?php echo date('M j, Y', strtotime($item->created_at)); ?></span>
                                </div>
                                <div class="item-actions">
                                    <button type="button" class="button-link edit-item" data-id="<?php echo esc_attr($item->id); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php _e('Edit', 'ai-website-chatbot'); ?>
                                    </button>
                                    <button type="button" class="button-link delete-item" data-id="<?php echo esc_attr($item->id); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php _e('Delete', 'ai-website-chatbot'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="item-content">
                                <div class="question">
                                    <strong><?php _e('Question:', 'ai-website-chatbot'); ?></strong>
                                    <p><?php echo esc_html($item->question); ?></p>
                                </div>
                                <div class="answer">
                                    <strong><?php _e('Answer:', 'ai-website-chatbot'); ?></strong>
                                    <p><?php echo esc_html($item->answer); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-training-data">
                        <div class="empty-state">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                            <h3><?php _e('No Training Data', 'ai-website-chatbot'); ?></h3>
                            <p><?php _e('Add some training data to help your AI chatbot provide better responses.', 'ai-website-chatbot'); ?></p>
                            <button type="button" class="button button-primary" id="add-first-pair">
                                <?php _e('Add Your First Training Pair', 'ai-website-chatbot'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit training data modal -->
    <div id="training-modal" class="ai-chatbot-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title"><?php _e('Add Training Data', 'ai-website-chatbot'); ?></h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="training-form">
                    <input type="hidden" id="training-id" value="">
                    
                    <div class="form-field">
                        <label for="training-question"><?php _e('Question', 'ai-website-chatbot'); ?></label>
                        <textarea id="training-question" rows="3" placeholder="<?php _e('Enter the question users might ask...', 'ai-website-chatbot'); ?>"></textarea>
                    </div>

                    <div class="form-field">
                        <label for="training-answer"><?php _e('Answer', 'ai-website-chatbot'); ?></label>
                        <textarea id="training-answer" rows="5" placeholder="<?php _e('Enter the response the chatbot should give...', 'ai-website-chatbot'); ?>"></textarea>
                    </div>

                    <div class="form-field">
                        <label for="training-category"><?php _e('Category', 'ai-website-chatbot'); ?></label>
                        <input type="text" id="training-category" list="category-list" placeholder="<?php _e('Optional category for organization', 'ai-website-chatbot'); ?>">
                        <datalist id="category-list">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Save Training Data', 'ai-website-chatbot'); ?>
                        </button>
                        <button type="button" class="button modal-close">
                            <?php _e('Cancel', 'ai-website-chatbot'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import modal -->
    <div id="import-modal" class="ai-chatbot-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Import Training Data', 'ai-website-chatbot'); ?></h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="import-form" enctype="multipart/form-data">
                    <div class="form-field">
                        <label for="training-file"><?php _e('Select File', 'ai-website-chatbot'); ?></label>
                        <input type="file" id="training-file" accept=".csv,.json">
                        <p class="description">
                            <?php _e('Supported formats: CSV, JSON. CSV should have columns: Question, Answer, Category (optional)', 'ai-website-chatbot'); ?>
                        </p>
                    </div>

                    <div class="import-preview" id="import-preview" style="display: none;">
                        <h4><?php _e('Preview', 'ai-website-chatbot'); ?></h4>
                        <div id="preview-content"></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Import Data', 'ai-website-chatbot'); ?>
                        </button>
                        <button type="button" class="button modal-close">
                            <?php _e('Cancel', 'ai-website-chatbot'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
