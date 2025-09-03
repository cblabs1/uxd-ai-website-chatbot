<?php
/**
 * Conversations page display
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get pagination parameters
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;

// Get filters
$filters = array(
    'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
    'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
    'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
);

// Get conversations
$conversations_data = $this->get_conversations($current_page, $per_page, $filters);
$conversations = $conversations_data['conversations'];
$total_items = $conversations_data['total_items'];
$total_pages = $conversations_data['total_pages'];

// Get statistics
$stats = $this->get_conversation_stats();
?>

<div class="wrap">
    <h1><?php _e('AI Chatbot Conversations', 'ai-website-chatbot'); ?></h1>

    <div class="ai-chatbot-conversations">
        <!-- Statistics cards -->
        <div class="conversation-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label"><?php _e('Total Conversations', 'ai-website-chatbot'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['today'] ?? 0); ?></div>
                <div class="stat-label"><?php _e('Today', 'ai-website-chatbot'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['this_week'] ?? 0); ?></div>
                <div class="stat-label"><?php _e('This Week', 'ai-website-chatbot'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['avg_response_time'] ?? 0); ?>ms</div>
                <div class="stat-label"><?php _e('Avg Response Time', 'ai-website-chatbot'); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="conversation-filters">
            <form method="get" class="filters-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
                
                <div class="filter-group">
                    <input type="search" name="s" value="<?php echo esc_attr($filters['search']); ?>" 
                           placeholder="<?php _e('Search conversations...', 'ai-website-chatbot'); ?>">
                </div>

                <div class="filter-group">
                    <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>" 
                           placeholder="<?php _e('From date', 'ai-website-chatbot'); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>" 
                           placeholder="<?php _e('To date', 'ai-website-chatbot'); ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="button"><?php _e('Filter', 'ai-website-chatbot'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=' . esc_attr($_GET['page'])); ?>" class="button">
                        <?php _e('Clear', 'ai-website-chatbot'); ?>
                    </a>
                </div>

                <div class="bulk-actions">
                    <button type="button" class="button" id="export-conversations">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export', 'ai-website-chatbot'); ?>
                    </button>
                    <button type="button" class="button" id="delete-selected">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Delete Selected', 'ai-website-chatbot'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Conversations list -->
        <div class="conversations-list">
            <?php if (!empty($conversations)): ?>
                <div class="list-header">
                    <div class="select-all">
                        <input type="checkbox" id="select-all-conversations">
                        <label for="select-all-conversations"><?php _e('Select All', 'ai-website-chatbot'); ?></label>
                    </div>
                    <div class="results-info">
                        <?php printf(
                            _n('%s conversation', '%s conversations', $total_items, 'ai-website-chatbot'),
                            number_format($total_items)
                        ); ?>
                    </div>
                </div>

                <div class="conversations-table">
                    <?php foreach ($conversations as $conversation): ?>
                        <div class="conversation-row" data-id="<?php echo esc_attr($conversation->id); ?>">
                            <div class="conversation-select">
                                <input type="checkbox" class="conversation-checkbox" value="<?php echo esc_attr($conversation->id); ?>">
                            </div>
                            
                            <div class="conversation-content">
                                <div class="conversation-messages">
                                    <div class="user-message">
                                        <span class="message-label"><?php _e('User:', 'ai-website-chatbot'); ?></span>
                                        <span class="message-text"><?php echo esc_html(wp_trim_words($conversation->user_message, 20)); ?></span>
                                    </div>
                                    <div class="bot-response">
                                        <span class="message-label"><?php _e('Bot:', 'ai-website-chatbot'); ?></span>
                                        <span class="message-text"><?php echo esc_html(wp_trim_words($conversation->bot_response, 20)); ?></span>
                                    </div>
                                </div>
                                
                                <div class="conversation-meta">
                                    <div class="meta-item">
                                        <span class="dashicons dashicons-clock"></span>
                                        <span><?php echo human_time_diff(strtotime($conversation->created_at)); ?> <?php _e('ago', 'ai-website-chatbot'); ?></span>
                                    </div>
                                    <?php if (!empty($conversation->response_time)): ?>
                                        <div class="meta-item">
                                            <span class="dashicons dashicons-performance"></span>
                                            <span><?php echo esc_html($conversation->response_time); ?>ms</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($conversation->tokens_used)): ?>
                                        <div class="meta-item">
                                            <span class="dashicons dashicons-editor-code"></span>
                                            <span><?php echo esc_html($conversation->tokens_used); ?> tokens</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="conversation-actions">
                                <button type="button" class="button-link view-conversation" data-id="<?php echo esc_attr($conversation->id); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('View', 'ai-website-chatbot'); ?>
                                </button>
                                <button type="button" class="button-link delete-conversation" data-id="<?php echo esc_attr($conversation->id); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php _e('Delete', 'ai-website-chatbot'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="conversations-pagination">
                        <?php
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo; ' . __('Previous', 'ai-website-chatbot'),
                            'next_text' => __('Next', 'ai-website-chatbot') . ' &raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                        );
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-conversations">
                    <div class="empty-state">
                        <span class="dashicons dashicons-format-chat"></span>
                        <h3><?php _e('No Conversations Found', 'ai-website-chatbot'); ?></h3>
                        <p>
                            <?php if (!empty(array_filter($filters))): ?>
                                <?php _e('No conversations match your current filters.', 'ai-website-chatbot'); ?>
                            <?php else: ?>
                                <?php _e('No conversations have been recorded yet.', 'ai-website-chatbot'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Conversation details modal -->
    <div id="conversation-modal" class="ai-chatbot-modal" style="display: none;">
        <div class="modal-content large">
            <div class="modal-header">
                <h3><?php _e('Conversation Details', 'ai-website-chatbot'); ?></h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="conversation-details">
                    <div class="loading">
                        <span class="spinner is-active"></span>
                        <p><?php _e('Loading conversation...', 'ai-website-chatbot'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export modal -->
    <div id="export-modal" class="ai-chatbot-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Export Conversations', 'ai-website-chatbot'); ?></h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="export-form">
                    <div class="form-field">
                        <label><?php _e('Date Range', 'ai-website-chatbot'); ?></label>
                        <div class="date-range">
                            <input type="date" id="export-date-from" name="date_from">
                            <span><?php _e('to', 'ai-website-chatbot'); ?></span>
                            <input type="date" id="export-date-to" name="date_to">
                        </div>
                    </div>

                    <div class="form-field">
                        <label><?php _e('Format', 'ai-website-chatbot'); ?></label>
                        <div class="format-options">
                            <label>
                                <input type="radio" name="format" value="csv" checked>
                                <?php _e('CSV', 'ai-website-chatbot'); ?>
                            </label>
                            <label>
                                <input type="radio" name="format" value="json">
                                <?php _e('JSON', 'ai-website-chatbot'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export', 'ai-website-chatbot'); ?>
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
