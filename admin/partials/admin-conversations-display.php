<?php
/**
 * Provide admin conversations view for the plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get conversation statistics
$conversation_stats = $this->get_conversation_statistics();
?>

<div class="wrap ai-chatbot-conversations-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Conversation Statistics -->
    <div class="conversations-stats-overview">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-format-chat"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($conversation_stats['total_conversations']); ?></div>
                    <div class="stat-label"><?php _e('Total Conversations', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($conversation_stats['active_conversations']); ?></div>
                    <div class="stat-label"><?php _e('Active Conversations', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($conversation_stats['resolved_conversations']); ?></div>
                    <div class="stat-label"><?php _e('Resolved Conversations', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($conversation_stats['avg_rating']); ?>/5</div>
                    <div class="stat-label"><?php _e('Average Rating', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($conversation_stats['conversations_today']); ?></div>
                    <div class="stat-label"><?php _e('Today\'s Conversations', 'ai-website-chatbot'); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Conversation Filters and Actions -->
    <div class="conversations-controls">
        <div class="controls-left">
            <label for="status-filter"><?php _e('Filter by Status:', 'ai-website-chatbot'); ?></label>
            <select id="status-filter" name="status">
                <option value=""><?php _e('All Statuses', 'ai-website-chatbot'); ?></option>
                <option value="active" <?php selected($status_filter, 'active'); ?>><?php _e('Active', 'ai-website-chatbot'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'ai-website-chatbot'); ?></option>
                <option value="resolved" <?php selected($status_filter, 'resolved'); ?>><?php _e('Resolved', 'ai-website-chatbot'); ?></option>
                <option value="closed" <?php selected($status_filter, 'closed'); ?>><?php _e('Closed', 'ai-website-chatbot'); ?></option>
            </select>
            
            <label for="date-filter"><?php _e('Date Range:', 'ai-website-chatbot'); ?></label>
            <select id="date-filter" name="date_range">
                <option value=""><?php _e('All Time', 'ai-website-chatbot'); ?></option>
                <option value="today" <?php selected($date_filter, 'today'); ?>><?php _e('Today', 'ai-website-chatbot'); ?></option>
                <option value="week" <?php selected($date_filter, 'week'); ?>><?php _e('This Week', 'ai-website-chatbot'); ?></option>
                <option value="month" <?php selected($date_filter, 'month'); ?>><?php _e('This Month', 'ai-website-chatbot'); ?></option>
            </select>
            
            <button type="button" class="button" onclick="location.reload();">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'ai-website-chatbot'); ?>
            </button>
        </div>
        
        <div class="controls-right">
            <button type="button" class="button export-conversations" data-format="csv">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export CSV', 'ai-website-chatbot'); ?>
            </button>
            
            <button type="button" class="button export-conversations" data-format="json">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export JSON', 'ai-website-chatbot'); ?>
            </button>
        </div>
    </div>
    
    <!-- Conversations Table -->
    <div class="conversations-table-container">
        <form class="bulk-action-form" method="post">
            <?php wp_nonce_field('ai_chatbot_conversations_bulk_action', '_wpnonce'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option value="-1"><?php _e('Bulk Actions', 'ai-website-chatbot'); ?></option>
                        <option value="delete"><?php _e('Delete', 'ai-website-chatbot'); ?></option>
                        <option value="mark_resolved"><?php _e('Mark as Resolved', 'ai-website-chatbot'); ?></option>
                        <option value="mark_pending"><?php _e('Mark as Pending', 'ai-website-chatbot'); ?></option>
                        <option value="mark_active"><?php _e('Mark as Active', 'ai-website-chatbot'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'ai-website-chatbot'); ?>">
                </div>
                
                <div class="alignright">
                    <span class="displaying-num">
                        <?php printf(__('%d conversations', 'ai-website-chatbot'), $total_items); ?>
                    </span>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped conversations-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All', 'ai-website-chatbot'); ?></label>
                            <input id="cb-select-all-1" type="checkbox" class="select-all">
                        </td>
                        <th scope="col" class="manage-column column-user"><?php _e('User', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-message"><?php _e('Message', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-response"><?php _e('AI Response', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-status"><?php _e('Status', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-rating"><?php _e('Rating', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-date"><?php _e('Date', 'ai-website-chatbot'); ?></th>
                        <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'ai-website-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($conversations)): ?>
                        <?php foreach ($conversations as $conversation): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="conversation_ids[]" value="<?php echo esc_attr($conversation['id']); ?>">
                                </th>
                                <td class="user-cell">
                                    <div class="user-info">
                                        <strong class="user-name">
                                            <?php echo esc_html($conversation['user_name'] ?: __('Anonymous', 'ai-website-chatbot')); ?>
                                        </strong>
                                        <?php if (!empty($conversation['user_email'])): ?>
                                            <div class="user-email"><?php echo esc_html($conversation['user_email']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($conversation['user_ip'])): ?>
                                            <div class="user-ip"><?php echo esc_html($conversation['user_ip']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="message-cell">
                                    <div class="message-content">
                                        <?php 
                                        $user_msg = $conversation['user_message'] ?? '';
                                        echo esc_html(wp_trim_words($user_msg, 15)); 
                                        ?>
                                        <?php if (!empty($user_msg) && strlen($user_msg) > 75): ?>
                                            <div class="message-preview" style="display: none;">
                                                <?php echo esc_html($user_msg); ?>
                                            </div>
                                            <button type="button" class="button-link toggle-message"><?php _e('Show more', 'ai-website-chatbot'); ?></button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($conversation['intent'])): ?>
                                        <div class="message-intent">
                                            <span class="intent-badge"><?php echo esc_html($conversation['intent']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="response-cell">
                                    <div class="response-content">
                                        <?php 
                                        $ai_resp = $conversation['ai_response'] ?? '';
                                        echo esc_html(wp_trim_words($ai_resp, 12)); 
                                        ?>
                                        <?php if (!empty($ai_resp) && strlen($ai_resp) > 60): ?>
                                            <div class="response-preview" style="display: none;">
                                                <?php echo esc_html($ai_resp); ?>
                                            </div>
                                            <button type="button" class="button-link toggle-response"><?php _e('Show more', 'ai-website-chatbot'); ?></button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($conversation['response_time'])): ?>
                                        <div class="response-time">
                                            <small><?php printf(__('Response time: %dms', 'ai-website-chatbot'), intval($conversation['response_time'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="status-cell">
                                    <select class="conversation-status-select" data-conversation-id="<?php echo esc_attr($conversation['id']); ?>">
                                        <option value="active" <?php selected($conversation['status'], 'active'); ?>><?php _e('Active', 'ai-website-chatbot'); ?></option>
                                        <option value="pending" <?php selected($conversation['status'], 'pending'); ?>><?php _e('Pending', 'ai-website-chatbot'); ?></option>
                                        <option value="resolved" <?php selected($conversation['status'], 'resolved'); ?>><?php _e('Resolved', 'ai-website-chatbot'); ?></option>
                                        <option value="closed" <?php selected($conversation['status'], 'closed'); ?>><?php _e('Closed', 'ai-website-chatbot'); ?></option>
                                    </select>
                                </td>
                                <td class="rating-cell">
                                    <?php if (!empty($conversation['rating'])): ?>
                                        <div class="rating-display">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star <?php echo $i <= $conversation['rating'] ? 'filled' : 'empty'; ?>">★</span>
                                            <?php endfor; ?>
                                            <span class="rating-value">(<?php echo esc_html($conversation['rating']); ?>/5)</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="no-rating"><?php _e('No rating', 'ai-website-chatbot'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="date-cell">
                                    <div class="conversation-date">
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($conversation['created_at']))); ?>
                                    </div>
                                    <div class="time-ago">
                                        <?php echo human_time_diff(strtotime($conversation['created_at'])); ?> <?php _e('ago', 'ai-website-chatbot'); ?>
                                    </div>
                                </td>
                                <td class="actions-cell">
                                    <button type="button" class="button button-small view-conversation" data-conversation-id="<?php echo esc_attr($conversation['id']); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                        <?php _e('View', 'ai-website-chatbot'); ?>
                                    </button>
                                    <button type="button" class="button button-small add-note" data-conversation-id="<?php echo esc_attr($conversation['id']); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php _e('Note', 'ai-website-chatbot'); ?>
                                    </button>
                                    <button type="button" class="button button-small button-link-delete delete-conversation" data-conversation-id="<?php echo esc_attr($conversation['id']); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php _e('Delete', 'ai-website-chatbot'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-items">
                            <td colspan="8" class="no-conversations">
                                <div class="no-data-message">
                                    <span class="dashicons dashicons-format-chat"></span>
                                    <h3><?php _e('No Conversations Yet', 'ai-website-chatbot'); ?></h3>
                                    <p><?php _e('When users start chatting with your AI chatbot, their conversations will appear here.', 'ai-website-chatbot'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if (!empty($conversations) && $total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(__('%d conversations', 'ai-website-chatbot'), $total_items); ?>
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

<!-- Conversation Details Modal -->
<div id="conversation-details-modal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content large-modal">
        <div class="modal-header">
            <h3><?php _e('Conversation Details', 'ai-website-chatbot'); ?></h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <div class="conversation-details-content">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button" data-modal-close><?php _e('Close', 'ai-website-chatbot'); ?></button>
        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div id="add-note-modal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Add Note', 'ai-website-chatbot'); ?></h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <form id="add-note-form">
                <input type="hidden" id="note-conversation-id" value="">
                <div class="form-row">
                    <label for="conversation-note"><?php _e('Note:', 'ai-website-chatbot'); ?></label>
                    <textarea id="conversation-note" rows="4" placeholder="<?php _e('Add your note about this conversation...', 'ai-website-chatbot'); ?>" required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="button" data-modal-close><?php _e('Cancel', 'ai-website-chatbot'); ?></button>
            <button type="button" class="button button-primary save-note"><?php _e('Save Note', 'ai-website-chatbot'); ?></button>
        </div>
    </div>
</div>

<style>
.ai-chatbot-conversations-wrap {
    max-width: 1600px;
}

.conversations-stats-overview {
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

.stat-label {
    color: #666;
    font-size: 13px;
}

.conversations-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    flex-wrap: wrap;
    gap: 15px;
}

.controls-left, .controls-right {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.conversations-table-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    overflow: hidden;
}

.user-info {
    min-width: 120px;
}

.user-name {
    display: block;
    margin-bottom: 3px;
}

.user-email, .user-ip {
    font-size: 11px;
    color: #666;
}

.message-cell, .response-cell {
    max-width: 250px;
}

.message-content, .response-content {
    margin-bottom: 5px;
}

.message-preview, .response-preview {
    display: block;
    margin-top: 8px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 12px;
    color: #555;
    max-height: 150px;
    overflow-y: auto;
}

.toggle-message, .toggle-response {
    color: #0073aa;
    text-decoration: none;
    font-size: 11px;
    cursor: pointer;
}

.message-intent {
    margin-top: 5px;
}

.intent-badge {
    background: #e7f3ff;
    color: #0073aa;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: bold;
}

.response-time {
    margin-top: 5px;
}

.response-time small {
    color: #666;
    font-size: 10px;
}

.conversation-status-select {
    width: 100%;
    font-size: 12px;
}

.rating-display {
    display: flex;
    align-items: center;
    gap: 2px;
}

.star {
    color: #ddd;
    font-size: 14px;
}

.star.filled {
    color: #ffc107;
}

.rating-value {
    margin-left: 5px;
    font-size: 11px;
    color: #666;
}

.no-rating {
    color: #999;
    font-style: italic;
    font-size: 12px;
}

.date-cell {
    min-width: 140px;
}

.conversation-date {
    font-weight: bold;
    margin-bottom: 3px;
}

.time-ago {
    font-size: 11px;
    color: #666;
}

.actions-cell {
    white-space: nowrap;
}

.actions-cell .button {
    margin-right: 3px;
    margin-bottom: 3px;
    padding: 2px 6px;
    font-size: 11px;
    line-height: 1.4;
}

.actions-cell .dashicons {
    font-size: 12px;
    margin-right: 2px;
}

.no-conversations {
    padding: 60px 40px;
    text-align: center;
}

.no-data-message {
    color: #666;
}

.no-data-message .dashicons {
    font-size: 64px;
    color: #ccc;
    margin-bottom: 20px;
}

.no-data-message h3 {
    margin-bottom: 10px;
    color: #333;
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

.modal-content.large-modal {
    max-width: 800px;
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
    max-height: 500px;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-row {
    margin-bottom: 15px;
}

.form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-row textarea {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .conversations-table .column-response {
        display: none;
    }
}

@media (max-width: 768px) {
    .conversations-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .controls-left, .controls-right {
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
    
    .conversations-table {
        font-size: 12px;
    }
    
    .conversations-table .column-user,
    .conversations-table .column-response {
        display: none;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .actions-cell .button {
        display: block;
        margin-bottom: 5px;
        text-align: center;
    }
}

@media (max-width: 600px) {
    .conversations-table .column-rating,
    .conversations-table .column-date {
        display: none;
    }
    
    .no-conversations {
        padding: 40px 20px;
    }
}

/* Loading states */
.conversation-status-select:disabled {
    opacity: 0.6;
}

.loading-row {
    opacity: 0.6;
    pointer-events: none;
}

/* Success/Error indicators */
.status-updated {
    background: #d4edda !important;
    transition: background-color 2s ease;
}

.status-error {
    background: #f8d7da !important;
    transition: background-color 2s ease;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle status changes
    $('.conversation-status-select').on('change', function() {
        var $select = $(this);
        var conversationId = $select.data('conversation-id');
        var newStatus = $select.val();
        var $row = $select.closest('tr');
        
        $select.prop('disabled', true);
        $row.addClass('loading-row');
        
        $.ajax({
            url: aiChatbotAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_update_conversation_status',
                nonce: aiChatbotAdmin.nonce,
                conversation_id: conversationId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    $row.addClass('status-updated');
                    setTimeout(function() {
                        $row.removeClass('status-updated');
                    }, 2000);
                } else {
                    $row.addClass('status-error');
                    setTimeout(function() {
                        $row.removeClass('status-error');
                    }, 2000);
                }
            },
            error: function() {
                $row.addClass('status-error');
                setTimeout(function() {
                    $row.removeClass('status-error');
                }, 2000);
            },
            complete: function() {
                $select.prop('disabled', false);
                $row.removeClass('loading-row');
            }
        });
    });
    
    // Toggle message/response previews
    $(document).on('click', '.toggle-message, .toggle-response', function() {
        var $button = $(this);
        var $preview = $button.siblings('.message-preview, .response-preview');
        
        if ($preview.is(':visible')) {
            $preview.hide();
            $button.text('Show more');
        } else {
            $preview.show();
            $button.text('Show less');
        }
    });
    
    // View conversation details
    $(document).on('click', '.view-conversation', function() {
        var conversationId = $(this).data('conversation-id');
        
        $.ajax({
            url: aiChatbotAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_get_conversation_details',
                nonce: aiChatbotAdmin.nonce,
                conversation_id: conversationId
            },
            success: function(response) {
                if (response.success) {
                    $('.conversation-details-content').html(response.data);
                    $('#conversation-details-modal').addClass('active');
                    $('body').addClass('modal-open');
                }
            }
        });
    });
    
    // Add note functionality
    $(document).on('click', '.add-note', function() {
        var conversationId = $(this).data('conversation-id');
        $('#note-conversation-id').val(conversationId);
        $('#conversation-note').val('');
        $('#add-note-modal').addClass('active');
        $('body').addClass('modal-open');
    });
    
    $('.save-note').on('click', function() {
        var conversationId = $('#note-conversation-id').val();
        var note = $('#conversation-note').val();
        
        if (!note.trim()) {
            alert('Please enter a note.');
            return;
        }
        
        $.ajax({
            url: aiChatbotAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_add_conversation_note',
                nonce: aiChatbotAdmin.nonce,
                conversation_id: conversationId,
                note: note
            },
            success: function(response) {
                if (response.success) {
                    $('#add-note-modal').removeClass('active');
                    $('body').removeClass('modal-open');
                    AIChatbotAdmin.showNotification(response.data, 'success');
                } else {
                    AIChatbotAdmin.showNotification(response.data, 'error');
                }
            }
        });
    });
    
    // Delete conversation
    $(document).on('click', '.delete-conversation', function() {
        if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
            return;
        }
        
        var conversationId = $(this).data('conversation-id');
        var $row = $(this).closest('tr');
        
        $.ajax({
            url: aiChatbotAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_delete_conversation',
                nonce: aiChatbotAdmin.nonce,
                conversation_id: conversationId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                    });
                    AIChatbotAdmin.showNotification(response.data, 'success');
                } else {
                    AIChatbotAdmin.showNotification(response.data, 'error');
                }
            }
        });
    });
    
    // Export conversations
    $('.export-conversations').on('click', function() {
        var format = $(this).data('format');
        var statusFilter = $('#status-filter').val();
        var dateFilter = $('#date-filter').val();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Exporting...');
        
        $.ajax({
            url: aiChatbotAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_chatbot_export_conversations',
                nonce: aiChatbotAdmin.nonce,
                format: format,
                status_filter: statusFilter,
                date_filter: dateFilter
            },
            xhrFields: {
                responseType: 'blob'
            },
            success: function(data) {
                // Create download
                var filename = 'conversations-export-' + new Date().toISOString().slice(0, 10) + '.' + format;
                var blob = new Blob([data]);
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                AIChatbotAdmin.showNotification('Conversations exported successfully!', 'success');
            },
            error: function() {
                AIChatbotAdmin.showNotification('Export failed', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Export ' + format.toUpperCase());
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
});
</script>