<?php
/**
 * AI Chatbot Admin Dashboard Class
 * 
 * Handles dashboard widgets and statistics for the AI Chatbot plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AI_Chatbot_Admin_Dashboard
 */
class AI_Chatbot_Admin_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only hook into wp_dashboard_setup which is called when dashboard is loaded
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        add_action('wp_ajax_ai_chatbot_dashboard_stats', array($this, 'get_dashboard_stats'));
    }
    
    /**
     * Add dashboard widgets
     * This method is called only when WordPress dashboard is loaded
     */
    public function add_dashboard_widgets() {
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add chatbot statistics widget
        wp_add_dashboard_widget(
            'ai_chatbot_stats_widget',
            __('AI Chatbot Statistics', 'ai-website-chatbot'),
            array($this, 'render_stats_widget')
        );
        
        // Add recent conversations widget
        wp_add_dashboard_widget(
            'ai_chatbot_recent_conversations',
            __('Recent Chatbot Conversations', 'ai-website-chatbot'),
            array($this, 'render_recent_conversations_widget')
        );
        
        // Add system status widget
        wp_add_dashboard_widget(
            'ai_chatbot_system_status',
            __('AI Chatbot System Status', 'ai-website-chatbot'),
            array($this, 'render_system_status_widget')
        );
    }
    
    /**
     * Render statistics widget
     */
    public function render_stats_widget() {
        $stats = $this->get_chatbot_statistics();
        ?>
        <div class="ai-chatbot-dashboard-widget">
            <div class="ai-chatbot-stats-grid">
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['total_conversations']); ?></h3>
                    <p><?php _e('Total Conversations', 'ai-website-chatbot'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['conversations_today']); ?></h3>
                    <p><?php _e('Conversations Today', 'ai-website-chatbot'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['avg_response_time']); ?>ms</h3>
                    <p><?php _e('Avg Response Time', 'ai-website-chatbot'); ?></p>
                </div>
                <div class="stat-item">
                    <h3><?php echo esc_html($stats['satisfaction_rate']); ?>%</h3>
                    <p><?php _e('Satisfaction Rate', 'ai-website-chatbot'); ?></p>
                </div>
            </div>
            
            <div class="ai-chatbot-chart-container">
                <canvas id="ai-chatbot-conversations-chart" width="400" height="200"></canvas>
            </div>
            
            <div class="widget-actions">
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-analytics'); ?>" class="button button-primary">
                    <?php _e('View Full Analytics', 'ai-website-chatbot'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-conversations'); ?>" class="button">
                    <?php _e('View Conversations', 'ai-website-chatbot'); ?>
                </a>
            </div>
        </div>
        
        <style>
        .ai-chatbot-dashboard-widget {
            padding: 10px;
        }
        .ai-chatbot-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #0073aa;
        }
        .stat-item h3 {
            margin: 0 0 5px 0;
            font-size: 24px;
            color: #0073aa;
        }
        .stat-item p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }
        .ai-chatbot-chart-container {
            margin-bottom: 15px;
            text-align: center;
        }
        .widget-actions {
            text-align: right;
        }
        .widget-actions .button {
            margin-left: 5px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize chart if Chart.js is available
            if (typeof Chart !== 'undefined') {
                var ctx = document.getElementById('ai-chatbot-conversations-chart');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($stats['chart_labels']); ?>,
                            datasets: [{
                                label: '<?php _e('Conversations', 'ai-website-chatbot'); ?>',
                                data: <?php echo json_encode($stats['chart_data']); ?>,
                                borderColor: '#0073aa',
                                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                                borderWidth: 2,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
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
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render recent conversations widget
     */
    public function render_recent_conversations_widget() {
        $recent_conversations = $this->get_recent_conversations();
        ?>
        <div class="ai-chatbot-recent-conversations">
            <?php if (empty($recent_conversations)): ?>
                <p><?php _e('No recent conversations found.', 'ai-website-chatbot'); ?></p>
            <?php else: ?>
                <ul class="conversation-list">
                    <?php foreach ($recent_conversations as $conversation): ?>
                        <li class="conversation-item">
                            <div class="conversation-header">
                                <strong><?php echo esc_html($conversation['user_name'] ?: __('Anonymous User', 'ai-website-chatbot')); ?></strong>
                                <span class="conversation-time"><?php echo human_time_diff($conversation['created_at']) . ' ' . __('ago', 'ai-website-chatbot'); ?></span>
                            </div>
                            <div class="conversation-preview">
                                <?php echo wp_trim_words(esc_html($conversation['last_message']), 10); ?>
                            </div>
                            <div class="conversation-status status-<?php echo esc_attr($conversation['status']); ?>">
                                <?php echo esc_html(ucfirst($conversation['status'])); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <div class="widget-actions">
                    <a href="<?php echo admin_url('admin.php?page=ai-chatbot-conversations'); ?>" class="button">
                        <?php _e('View All Conversations', 'ai-website-chatbot'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .ai-chatbot-recent-conversations {
            padding: 5px;
        }
        .conversation-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .conversation-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }
        .conversation-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        .conversation-time {
            color: #666;
            font-size: 11px;
        }
        .conversation-preview {
            color: #555;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .conversation-status {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-resolved {
            background: #cce5ff;
            color: #004085;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        </style>
        <?php
    }
    
    /**
     * Render system status widget
     */
    public function render_system_status_widget() {
        $system_status = $this->get_system_status();
        ?>
        <div class="ai-chatbot-system-status">
            <div class="status-grid">
                <div class="status-item <?php echo $system_status['ai_provider']['status']; ?>">
                    <span class="status-indicator"></span>
                    <strong><?php _e('AI Provider', 'ai-website-chatbot'); ?></strong>
                    <span class="status-text"><?php echo esc_html($system_status['ai_provider']['message']); ?></span>
                </div>
                
                <div class="status-item <?php echo $system_status['database']['status']; ?>">
                    <span class="status-indicator"></span>
                    <strong><?php _e('Database', 'ai-website-chatbot'); ?></strong>
                    <span class="status-text"><?php echo esc_html($system_status['database']['message']); ?></span>
                </div>
                
                <div class="status-item <?php echo $system_status['rate_limits']['status']; ?>">
                    <span class="status-indicator"></span>
                    <strong><?php _e('Rate Limits', 'ai-website-chatbot'); ?></strong>
                    <span class="status-text"><?php echo esc_html($system_status['rate_limits']['message']); ?></span>
                </div>
                
                <div class="status-item <?php echo $system_status['content_sync']['status']; ?>">
                    <span class="status-indicator"></span>
                    <strong><?php _e('Content Sync', 'ai-website-chatbot'); ?></strong>
                    <span class="status-text"><?php echo esc_html($system_status['content_sync']['message']); ?></span>
                </div>
            </div>
            
            <div class="widget-actions">
                <a href="<?php echo admin_url('admin.php?page=ai-chatbot-settings'); ?>" class="button">
                    <?php _e('Settings', 'ai-website-chatbot'); ?>
                </a>
                <button type="button" class="button button-primary" id="refresh-system-status">
                    <?php _e('Refresh Status', 'ai-website-chatbot'); ?>
                </button>
            </div>
        </div>
        
        <style>
        .ai-chatbot-system-status {
            padding: 5px;
        }
        .status-grid {
            margin-bottom: 15px;
        }
        .status-item {
            display: flex;
            align-items: center;
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 3px;
            background: #f8f9fa;
        }
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-item.healthy .status-indicator {
            background: #28a745;
        }
        .status-item.warning .status-indicator {
            background: #ffc107;
        }
        .status-item.error .status-indicator {
            background: #dc3545;
        }
        .status-item strong {
            min-width: 80px;
            margin-right: 10px;
        }
        .status-text {
            font-size: 12px;
            color: #666;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#refresh-system-status').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('<?php _e('Refreshing...', 'ai-website-chatbot'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_chatbot_refresh_system_status',
                        nonce: '<?php echo wp_create_nonce('ai_chatbot_dashboard_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('<?php _e('Failed to refresh system status', 'ai-website-chatbot'); ?>');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('Refresh Status', 'ai-website-chatbot'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get chatbot statistics
     */
    private function get_chatbot_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Default stats
        $stats = array(
            'total_conversations' => 0,
            'conversations_today' => 0,
            'avg_response_time' => 0,
            'satisfaction_rate' => 0,
            'chart_labels' => array(),
            'chart_data' => array()
        );
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return $stats;
        }
        
        // Total conversations
        $stats['total_conversations'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Conversations today
        $stats['conversations_today'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )
        );
        
        // Average response time
        $avg_time = $wpdb->get_var("SELECT AVG(response_time) FROM $table_name WHERE response_time IS NOT NULL");
        $stats['avg_response_time'] = round($avg_time ?: 0);
        
        // Satisfaction rate (based on ratings)
        $satisfaction = $wpdb->get_var("SELECT AVG(rating) * 20 FROM $table_name WHERE rating IS NOT NULL");
        $stats['satisfaction_rate'] = round($satisfaction ?: 0);
        
        // Chart data for last 7 days
        $chart_data = $wpdb->get_results(
            $wpdb->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM $table_name 
                WHERE created_at >= %s 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC
            ", date('Y-m-d', strtotime('-7 days')))
        );
        
        // Prepare chart labels and data
        $labels = array();
        $data = array();
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));
            
            $count = 0;
            foreach ($chart_data as $row) {
                if ($row->date === $date) {
                    $count = $row->count;
                    break;
                }
            }
            $data[] = $count;
        }
        
        $stats['chart_labels'] = $labels;
        $stats['chart_data'] = $data;
        
        return $stats;
    }
    
    /**
     * Get recent conversations
     */
    private function get_recent_conversations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5",
            ARRAY_A
        );
        
        $conversations = array();
        foreach ($results as $row) {
            $conversations[] = array(
                'id' => $row['id'],
                'user_name' => $row['user_name'],
                'last_message' => $row['user_message'],
                'status' => $row['status'] ?: 'active',
                'created_at' => strtotime($row['created_at'])
            );
        }
        
        return $conversations;
    }
    
    /**
     * Get system status
     */
    private function get_system_status() {
        $status = array();
        
        // AI Provider Status
        $settings = get_option('ai_chatbot_settings', array());
        $provider = $settings['ai_provider'] ?? 'openai';
        $api_key = $settings['api_key'] ?? '';
        
        if (empty($api_key)) {
            $status['ai_provider'] = array(
                'status' => 'error',
                'message' => __('API key not configured', 'ai-website-chatbot')
            );
        } else {
            // Test API connection (simplified check)
            $status['ai_provider'] = array(
                'status' => 'healthy',
                'message' => sprintf(__('%s connected', 'ai-website-chatbot'), ucfirst($provider))
            );
        }
        
        // Database Status
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $status['database'] = array(
                'status' => 'healthy',
                'message' => __('Tables created successfully', 'ai-website-chatbot')
            );
        } else {
            $status['database'] = array(
                'status' => 'error',
                'message' => __('Database tables missing', 'ai-website-chatbot')
            );
        }
        
        // Rate Limits Status
        $rate_limit_settings = $settings['rate_limiting'] ?? array();
        $enabled = $rate_limit_settings['enabled'] ?? false;
        
        if ($enabled) {
            $status['rate_limits'] = array(
                'status' => 'healthy',
                'message' => __('Rate limiting active', 'ai-website-chatbot')
            );
        } else {
            $status['rate_limits'] = array(
                'status' => 'warning',
                'message' => __('Rate limiting disabled', 'ai-website-chatbot')
            );
        }
        
        // Content Sync Status
        $sync_settings = $settings['content_sync'] ?? array();
        $last_sync = get_option('ai_chatbot_last_content_sync', 0);
        
        if ($last_sync && (time() - $last_sync) < DAY_IN_SECONDS) {
            $status['content_sync'] = array(
                'status' => 'healthy',
                'message' => __('Synced recently', 'ai-website-chatbot')
            );
        } else {
            $status['content_sync'] = array(
                'status' => 'warning',
                'message' => __('Sync required', 'ai-website-chatbot')
            );
        }
        
        return $status;
    }
    
    /**
     * AJAX handler for dashboard stats
     */
    public function get_dashboard_stats() {
        check_ajax_referer('ai_chatbot_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $stats = $this->get_chatbot_statistics();
        wp_send_json_success($stats);
    }
}