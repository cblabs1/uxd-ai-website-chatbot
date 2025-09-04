<?php
/**
 * Provide admin analytics view for the plugin
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get analytics data
$analytics_data = isset($analytics_data) ? $analytics_data : $this->get_analytics_data();
?>

<div class="wrap ai-chatbot-analytics-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Analytics Controls -->
    <div class="analytics-controls">
        <div class="controls-left">
            <label for="analytics-date-range"><?php _e('Date Range:', 'ai-website-chatbot'); ?></label>
            <select id="analytics-date-range">
                <option value="7"><?php _e('Last 7 days', 'ai-website-chatbot'); ?></option>
                <option value="30" selected><?php _e('Last 30 days', 'ai-website-chatbot'); ?></option>
                <option value="90"><?php _e('Last 90 days', 'ai-website-chatbot'); ?></option>
                <option value="365"><?php _e('Last year', 'ai-website-chatbot'); ?></option>
            </select>
            
            <button type="button" class="button refresh-analytics">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'ai-website-chatbot'); ?>
            </button>
        </div>
        
        <div class="controls-right">
            <label>
                <input type="checkbox" id="realtime-updates"> <?php _e('Real-time Updates', 'ai-website-chatbot'); ?>
            </label>
            
            <button type="button" class="button export-analytics" data-format="csv">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export CSV', 'ai-website-chatbot'); ?>
            </button>
            
            <button type="button" class="button export-analytics" data-format="json">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export JSON', 'ai-website-chatbot'); ?>
            </button>
        </div>
    </div>
    
    <!-- Analytics Overview -->
    <div class="analytics-overview">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-format-chat"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number stat-avg-response-time"><?php echo esc_html($analytics_data['avg_response_time']); ?>ms</div>
                    <div class="stat-label"><?php _e('Avg Response Time', 'ai-website-chatbot'); ?></div>
                    <div class="stat-change">
                        <span class="change-indicator negative">-50ms</span>
                        <span class="change-period"><?php _e('vs last period', 'ai-website-chatbot'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number stat-user-satisfaction"><?php echo esc_html($analytics_data['user_satisfaction']); ?>/5</div>
                    <div class="stat-label"><?php _e('User Satisfaction', 'ai-website-chatbot'); ?></div>
                    <div class="stat-change">
                        <span class="change-indicator positive">+0.2</span>
                        <span class="change-period"><?php _e('vs last period', 'ai-website-chatbot'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="analytics-charts">
        <div class="charts-grid">
            <!-- Conversation Trends Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('Conversation Trends', 'ai-website-chatbot'); ?></h3>
                    <div class="chart-controls">
                        <button type="button" class="chart-type-toggle" data-chart="conversationTrends" data-type="line">
                            <span class="dashicons dashicons-chart-line"></span>
                        </button>
                        <button type="button" class="chart-type-toggle" data-chart="conversationTrends" data-type="bar">
                            <span class="dashicons dashicons-chart-bar"></span>
                        </button>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="conversation-trends-chart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <!-- Response Time Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('Response Times', 'ai-website-chatbot'); ?></h3>
                </div>
                <div class="chart-wrapper">
                    <canvas id="response-time-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="charts-grid">
            <!-- User Satisfaction Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('User Satisfaction Distribution', 'ai-website-chatbot'); ?></h3>
                </div>
                <div class="chart-wrapper">
                    <canvas id="satisfaction-chart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <!-- Top Topics Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('Top Conversation Topics', 'ai-website-chatbot'); ?></h3>
                </div>
                <div class="chart-wrapper">
                    <canvas id="topics-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Hourly Distribution Chart -->
        <div class="chart-container full-width">
            <div class="chart-header">
                <h3><?php _e('Conversations by Hour of Day', 'ai-website-chatbot'); ?></h3>
            </div>
            <div class="chart-wrapper">
                <canvas id="hourly-distribution-chart" width="800" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Analytics Tables -->
    <div class="analytics-tables">
        <div class="tables-grid">
            <!-- Most Active Hours -->
            <div class="table-container">
                <h3><?php _e('Most Active Hours', 'ai-website-chatbot'); ?></h3>
                <?php if (!empty($analytics_data['active_hours'])): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Hour', 'ai-website-chatbot'); ?></th>
                                <th><?php _e('Conversations', 'ai-website-chatbot'); ?></th>
                                <th><?php _e('Percentage', 'ai-website-chatbot'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_conversations = array_sum(array_column($analytics_data['active_hours'], 'count'));
                            foreach ($analytics_data['active_hours'] as $hour_data): 
                                $percentage = $total_conversations > 0 ? round(($hour_data->count / $total_conversations) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo esc_html($hour_data->hour . ':00'); ?></td>
                                <td><?php echo esc_html($hour_data->count); ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                        <span class="progress-text"><?php echo esc_html($percentage); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data"><?php _e('No data available for the selected period.', 'ai-website-chatbot'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Top Topics Table -->
            <div class="table-container">
                <h3><?php _e('Popular Topics', 'ai-website-chatbot'); ?></h3>
                <?php if (!empty($analytics_data['top_topics'])): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Topic/Intent', 'ai-website-chatbot'); ?></th>
                                <th><?php _e('Mentions', 'ai-website-chatbot'); ?></th>
                                <th><?php _e('Trend', 'ai-website-chatbot'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics_data['top_topics'] as $topic): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($topic->intent); ?></strong>
                                </td>
                                <td><?php echo esc_html($topic->count); ?></td>
                                <td>
                                    <span class="trend-indicator positive">
                                        <span class="dashicons dashicons-arrow-up-alt"></span>
                                        <?php _e('Trending', 'ai-website-chatbot'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data"><?php _e('No topic data available.', 'ai-website-chatbot'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Conversation Status Distribution -->
        <div class="table-container full-width">
            <h3><?php _e('Conversation Status Distribution', 'ai-website-chatbot'); ?></h3>
            <?php if (!empty($analytics_data['status_distribution'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Status', 'ai-website-chatbot'); ?></th>
                            <th><?php _e('Count', 'ai-website-chatbot'); ?></th>
                            <th><?php _e('Percentage', 'ai-website-chatbot'); ?></th>
                            <th><?php _e('Visual', 'ai-website-chatbot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_status = array_sum(array_column($analytics_data['status_distribution'], 'count'));
                        foreach ($analytics_data['status_distribution'] as $status): 
                            $percentage = $total_status > 0 ? round(($status->count / $total_status) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($status->status); ?>">
                                    <?php echo esc_html(ucfirst($status->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($status->count); ?></td>
                            <td><?php echo esc_html($percentage); ?>%</td>
                            <td>
                                <div class="status-bar">
                                    <div class="status-fill status-<?php echo esc_attr($status->status); ?>" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-data"><?php _e('No status data available.', 'ai-website-chatbot'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Analytics Insights -->
    <div class="analytics-insights">
        <h3><?php _e('Key Insights', 'ai-website-chatbot'); ?></h3>
        <div class="insights-grid">
            <div class="insight-card">
                <div class="insight-icon positive">
                    <span class="dashicons dashicons-arrow-up-alt"></span>
                </div>
                <div class="insight-content">
                    <h4><?php _e('Performance Improvement', 'ai-website-chatbot'); ?></h4>
                    <p><?php _e('Response times have improved by 15% compared to last month.', 'ai-website-chatbot'); ?></p>
                </div>
            </div>
            
            <div class="insight-card">
                <div class="insight-icon info">
                    <span class="dashicons dashicons-info"></span>
                </div>
                <div class="insight-content">
                    <h4><?php _e('Peak Usage Hours', 'ai-website-chatbot'); ?></h4>
                    <p><?php _e('Most conversations happen between 2PM and 4PM. Consider staffing adjustments.', 'ai-website-chatbot'); ?></p>
                </div>
            </div>
            
            <div class="insight-card">
                <div class="insight-icon warning">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="insight-content">
                    <h4><?php _e('Training Opportunity', 'ai-website-chatbot'); ?></h4>
                    <p><?php _e('Some topics show lower satisfaction rates. Consider adding more training data.', 'ai-website-chatbot'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="analytics-footer">
        <p class="last-updated-time"><?php printf(__('Last updated: %s', 'ai-website-chatbot'), current_time('M j, Y g:i A')); ?></p>
    </div>
    
    <!-- Loading Indicator -->
    <div class="analytics-loading" style="display: none;">
        <div class="loading-spinner"></div>
        <p><?php _e('Loading analytics data...', 'ai-website-chatbot'); ?></p>
    </div>
</div>

<style>
.ai-chatbot-analytics-wrap {
    max-width: 1400px;
}

.analytics-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 5px;
}

.controls-left, .controls-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.analytics-overview {
    margin-bottom: 30px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.stat-card {
    background: #fff;
    padding: 25px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #0073aa;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon .dashicons {
    font-size: 24px;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
    margin-bottom: 5px;
}

.stat-change {
    font-size: 12px;
    color: #666;
}

.change-indicator.positive {
    color: #46b450;
}

.change-indicator.negative {
    color: #dc3232;
}

.analytics-charts {
    margin-bottom: 40px;
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 25px;
    margin-bottom: 25px;
}

.chart-container {
    background: #fff;
    padding: 25px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.chart-container.full-width {
    grid-column: 1 / -1;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-header h3 {
    margin: 0;
    color: #333;
}

.chart-controls {
    display: flex;
    gap: 5px;
}

.chart-type-toggle {
    border: none;
    background: #f0f0f0;
    padding: 5px 8px;
    border-radius: 3px;
    cursor: pointer;
}

.chart-type-toggle:hover {
    background: #e0e0e0;
}

.chart-wrapper {
    position: relative;
    height: 300px;
}

.analytics-tables {
    margin-bottom: 40px;
}

.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
    margin-bottom: 25px;
}

.table-container {
    background: #fff;
    padding: 25px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.table-container.full-width {
    grid-column: 1 / -1;
}

.table-container h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.progress-bar {
    position: relative;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #0073aa;
    border-radius: 10px;
    transition: width 0.3s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 12px;
    font-weight: bold;
    color: #333;
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

.status-bar {
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.status-fill {
    height: 100%;
    transition: width 0.3s ease;
}

.status-fill.status-active {
    background: #28a745;
}

.status-fill.status-pending {
    background: #ffc107;
}

.status-fill.status-resolved {
    background: #007bff;
}

.trend-indicator {
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 3px;
}

.trend-indicator.positive {
    color: #46b450;
}

.analytics-insights {
    margin-bottom: 40px;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.insight-card {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.insight-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.insight-icon.positive {
    background: #d4edda;
    color: #155724;
}

.insight-icon.info {
    background: #cce5ff;
    color: #004085;
}

.insight-icon.warning {
    background: #fff3cd;
    color: #856404;
}

.insight-content h4 {
    margin: 0 0 8px 0;
    color: #333;
}

.insight-content p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.analytics-footer {
    text-align: center;
    padding: 20px;
    color: #666;
    font-size: 14px;
}

.analytics-loading {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.95);
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    z-index: 9999;
}

.loading-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #0073aa;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.no-data {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
}

@media (max-width: 768px) {
    .analytics-controls {
        flex-direction: column;
        gap: 15px;
    }
    
    .controls-left, .controls-right {
        width: 100%;
        justify-content: center;
    }
    
    .charts-grid, .tables-grid {
        grid-template-columns: 1fr;
    }
    
    .chart-wrapper {
        height: 250px;
    }
}
</style>