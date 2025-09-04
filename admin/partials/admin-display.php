<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get plugin settings
$settings = get_option('ai_chatbot_settings', array());
$is_enabled = isset($settings['enabled']) ? $settings['enabled'] : false;
$api_configured = !empty($settings['api_key']);

// Get statistics
$stats = array(
    'total_conversations' => 0,
    'conversations_today' => 0,
    'avg_response_time' => 0,
    'user_satisfaction' => 0
);

global $wpdb;
$conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
if ($wpdb->get_var("SHOW TABLES LIKE '$conversations_table'") == $conversations_table) {
    $stats['total_conversations'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $conversations_table"));
    $stats['conversations_today'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $conversations_table WHERE DATE(created_at) = CURDATE()"));
    
    $avg_time = $wpdb->get_var("SELECT AVG(response_time) FROM $conversations_table WHERE response_time > 0");
    $stats['avg_response_time'] = round($avg_time ?: 0);
    
    $satisfaction = $wpdb->get_var("SELECT AVG(rating) FROM $conversations_table WHERE rating > 0");
    $stats['user_satisfaction'] = round($satisfaction ?: 0, 1);
}
?>

<div class="wrap ai-chatbot-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Status Banner -->
    <div class="ai-chatbot-status-banner <?php echo $is_enabled && $api_configured ? 'status-active' : 'status-inactive'; ?>">
        <div class="status-indicator">
            <span class="status-dot"></span>
            <strong>
                <?php if ($is_enabled && $api_configured): ?>
                    <?php _e('Chatbot Active', 'ai-website-chatbot'); ?>
                <?php elseif (!$api_configured): ?>
                    <?php _e('API Not Configured', 'ai-website-chatbot'); ?>
                <?php else: ?>
                    <?php _e('Chatbot Disabled', 'ai-website-chatbot'); ?>
                <?php endif; ?>
            </strong>
        </div>
        <div class="status-actions">
            <?php if (!$api_configured): ?>
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-settings'); ?>" class="button button-primary">
                    <?php _e('Configure API', 'ai-website-chatbot'); ?>
                </a>
            <?php elseif (!$is_enabled): ?>
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-settings'); ?>" class="button button-primary">
                    <?php _e('Enable Chatbot', 'ai-website-chatbot'); ?>
                </a>
            <?php else: ?>
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-settings'); ?>" class="button">
                    <?php _e('Settings', 'ai-website-chatbot'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="ai-chatbot-stats-overview">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($stats['total_conversations']); ?></div>
                <div class="stat-label"><?php _e('Total Conversations', 'ai-website-chatbot'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($stats['conversations_today']); ?></div>
                <div class="stat-label"><?php _e('Conversations Today', 'ai-website-chatbot'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($stats['avg_response_time']); ?>ms</div>
                <div class="stat-label"><?php _e('Avg Response Time', 'ai-website-chatbot'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($stats['user_satisfaction']); ?>/5</div>
                <div class="stat-label"><?php _e('User Satisfaction', 'ai-website-chatbot'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="ai-chatbot-quick-actions">
        <h2><?php _e('Quick Actions', 'ai-website-chatbot'); ?></h2>
        <div class="actions-grid">
            <div class="action-card">
                <div class="action-icon">
                    <span class="dashicons dashicons-admin-settings"></span>
                </div>
                <h3><?php _e('Settings', 'ai-website-chatbot'); ?></h3>
                <p><?php _e('Configure your AI provider, customize the chatbot appearance, and manage general settings.', 'ai-website-chatbot'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-settings'); ?>" class="button button-primary">
                    <?php _e('Go to Settings', 'ai-website-chatbot'); ?>
                </a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <span class="dashicons dashicons-format-chat"></span>
                </div>
                <h3><?php _e('Conversations', 'ai-website-chatbot'); ?></h3>
                <p><?php _e('View and manage all conversations between users and your AI chatbot.', 'ai-website-chatbot'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-conversations'); ?>" class="button">
                    <?php _e('View Conversations', 'ai-website-chatbot'); ?>
                </a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                </div>
                <h3><?php _e('Training', 'ai-website-chatbot'); ?></h3>
                <p><?php _e('Add custom training data to improve your chatbot\'s responses.', 'ai-website-chatbot'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-training'); ?>" class="button">
                    <?php _e('Manage Training', 'ai-website-chatbot'); ?>
                </a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <span class="dashicons dashicons-chart-area"></span>
                </div>
                <h3><?php _e('Analytics', 'ai-website-chatbot'); ?></h3>
                <p><?php _e('View detailed analytics and insights about your chatbot\'s performance.', 'ai-website-chatbot'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-analytics'); ?>" class="button">
                    <?php _e('View Analytics', 'ai-website-chatbot'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <?php
    $recent_conversations = array();
    if ($wpdb->get_var("SHOW TABLES LIKE '$conversations_table'") == $conversations_table) {
        $recent_conversations = $wpdb->get_results(
            "SELECT id, user_name, user_message, created_at, status FROM $conversations_table ORDER BY created_at DESC LIMIT 5",
            ARRAY_A
        );
    }
    ?>
    
    <?php if (!empty($recent_conversations)): ?>
    <div class="ai-chatbot-recent-activity">
        <h2><?php _e('Recent Conversations', 'ai-website-chatbot'); ?></h2>
        <div class="activity-table-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'ai-website-chatbot'); ?></th>
                        <th><?php _e('Message', 'ai-website-chatbot'); ?></th>
                        <th><?php _e('Status', 'ai-website-chatbot'); ?></th>
                        <th><?php _e('Date', 'ai-website-chatbot'); ?></th>
                        <th><?php _e('Actions', 'ai-website-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_conversations as $conversation): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($conversation['user_name'] ?: __('Anonymous', 'ai-website-chatbot')); ?></strong>
                        </td>
                        <td>
                            <div class="message-preview">
                                <?php echo esc_html(wp_trim_words($conversation['user_message'], 10)); ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($conversation['status']); ?>">
                                <?php echo esc_html(ucfirst($conversation['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html(human_time_diff(strtotime($conversation['created_at']))); ?> <?php _e('ago', 'ai-website-chatbot'); ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=ai-chatbot-conversations&conversation_id=' . $conversation['id']); ?>" class="button button-small">
                                <?php _e('View', 'ai-website-chatbot'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="activity-footer">
            <a href="<?php echo admin_url('admin.php?page=ai-chatbot-conversations'); ?>" class="button">
                <?php _e('View All Conversations', 'ai-website-chatbot'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- System Status -->
    <div class="ai-chatbot-system-status">
        <h2><?php _e('System Status', 'ai-website-chatbot'); ?></h2>
        <div class="status-grid">
            <div class="status-item">
                <div class="status-indicator <?php echo $api_configured ? 'status-ok' : 'status-error'; ?>">
                    <span class="status-dot"></span>
                </div>
                <div class="status-info">
                    <strong><?php _e('API Configuration', 'ai-website-chatbot'); ?></strong>
                    <p><?php echo $api_configured ? __('API key configured', 'ai-website-chatbot') : __('API key not set', 'ai-website-chatbot'); ?></p>
                </div>
            </div>
            
            <div class="status-item">
                <div class="status-indicator <?php echo $wpdb->get_var("SHOW TABLES LIKE '$conversations_table'") == $conversations_table ? 'status-ok' : 'status-error'; ?>">
                    <span class="status-dot"></span>
                </div>
                <div class="status-info">
                    <strong><?php _e('Database Tables', 'ai-website-chatbot'); ?></strong>
                    <p><?php echo $wpdb->get_var("SHOW TABLES LIKE '$conversations_table'") == $conversations_table ? __('Tables created', 'ai-website-chatbot') : __('Tables missing', 'ai-website-chatbot'); ?></p>
                </div>
            </div>
            
            <div class="status-item">
                <div class="status-indicator <?php echo $is_enabled ? 'status-ok' : 'status-warning'; ?>">
                    <span class="status-dot"></span>
                </div>
                <div class="status-info">
                    <strong><?php _e('Chatbot Status', 'ai-website-chatbot'); ?></strong>
                    <p><?php echo $is_enabled ? __('Enabled', 'ai-website-chatbot') : __('Disabled', 'ai-website-chatbot'); ?></p>
                </div>
            </div>
            
            <?php
            $rate_limiting = isset($settings['rate_limiting']['enabled']) ? $settings['rate_limiting']['enabled'] : false;
            ?>
            <div class="status-item">
                <div class="status-indicator <?php echo $rate_limiting ? 'status-ok' : 'status-warning'; ?>">
                    <span class="status-dot"></span>
                </div>
                <div class="status-info">
                    <strong><?php _e('Rate Limiting', 'ai-website-chatbot'); ?></strong>
                    <p><?php echo $rate_limiting ? __('Enabled', 'ai-website-chatbot') : __('Disabled', 'ai-website-chatbot'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ai-chatbot-admin-wrap {
    margin: 20px 20px 0 0;
}

.ai-chatbot-status-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border-left: 5px solid;
}

.status-active {
    background: #d4edda;
    border-left-color: #28a745;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    border-left-color: #dc3545;
    color: #721c24;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.status-active .status-dot {
    background: #28a745;
}

.status-inactive .status-dot {
    background: #dc3545;
}

.ai-chatbot-stats-overview {
    margin-bottom: 30px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #e1e1e1;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 8px;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

.ai-chatbot-quick-actions {
    margin-bottom: 30px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.action-card {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #e1e1e1;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.action-icon {
    margin-bottom: 15px;
}

.action-icon .dashicons {
    font-size: 32px;
    color: #0073aa;
}

.action-card h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.action-card p {
    color: #666;
    margin-bottom: 20px;
}

.ai-chatbot-recent-activity,
.ai-chatbot-system-status {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #e1e1e1;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.activity-table-wrapper {
    overflow-x: auto;
    margin: 20px 0;
}

.message-preview {
    max-width: 300px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-resolved {
    background: #cce5ff;
    color: #004085;
}

.activity-footer {
    text-align: center;
    margin-top: 20px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.status-item .status-indicator {
    flex-shrink: 0;
}

.status-ok .status-dot {
    background: #28a745;
}

.status-warning .status-dot {
    background: #ffc107;
}

.status-error .status-dot {
    background: #dc3545;
}

.status-info strong {
    display: block;
    margin-bottom: 5px;
}

.status-info p {
    margin: 0;
    color: #666;
    font-size: 13px;
}

@media (max-width: 768px) {
    .ai-chatbot-status-banner {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .stats-grid,
    .actions-grid,
    .status-grid {
        grid-template-columns: 1fr;
    }
}
</style>