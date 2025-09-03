<?php
/**
 * Analytics page display
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('AI Chatbot Analytics', 'ai-website-chatbot'); ?></h1>

    <div class="ai-chatbot-analytics">
        <!-- Time period selector -->
        <div class="analytics-controls">
            <div class="period-selector">
                <label for="analytics-period"><?php _e('Time Period:', 'ai-website-chatbot'); ?></label>
                <select id="analytics-period">
                    <option value="24hours"><?php _e('Last 24 Hours', 'ai-website-chatbot'); ?></option>
                    <option value="7days" selected><?php _e('Last 7 Days', 'ai-website-chatbot'); ?></option>
                    <option value="30days"><?php _e('Last 30 Days', 'ai-website-chatbot'); ?></option>
                    <option value="90days"><?php _e('Last 90 Days', 'ai-website-chatbot'); ?></option>
                </select>
            </div>
            <div class="export-controls">
                <button type="button" class="button" id="export-analytics">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Data', 'ai-website-chatbot'); ?>
                </button>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="analytics-summary">
            <div class="summary-card" id="total-conversations">
                <div class="card-content">
                    <div class="card-number">-</div>
                    <div class="card-label"><?php _e('Total Conversations', 'ai-website-chatbot'); ?></div>
                </div>
                <div class="card-icon">
                    <span class="dashicons dashicons-format-chat"></span>
                </div>
            </div>
            <div class="summary-card" id="avg-response-time">
                <div class="card-content">
                    <div class="card-number">-</div>
                    <div class="card-label"><?php _e('Avg Response Time', 'ai-website-chatbot'); ?></div>
                </div>
                <div class="card-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
            </div>
            <div class="summary-card" id="satisfaction-rate">
                <div class="card-content">
                    <div class="card-number">-</div>
                    <div class="card-label"><?php _e('Satisfaction Rate', 'ai-website-chatbot'); ?></div>
                </div>
                <div class="card-icon">
                    <span class="dashicons dashicons-thumbs-up"></span>
                </div>
            </div>
            <div class="summary-card" id="total-cost">
                <div class="card-content">
                    <div class="card-number">-</div>
                    <div class="card-label"><?php _e('Total Cost', 'ai-website-chatbot'); ?></div>
                </div>
                <div class="card-icon">
                    <span class="dashicons dashicons-money"></span>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="analytics-charts">
            <div class="chart-container">
                <h3><?php _e('Conversation Volume', 'ai-website-chatbot'); ?></h3>
                <div class="chart-wrapper">
                    <canvas id="volume-chart"></canvas>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-container half">
                    <h3><?php _e('Popular Topics', 'ai-website-chatbot'); ?></h3>
                    <div class="chart-wrapper">
                        <canvas id="topics-chart"></canvas>
                    </div>
                </div>

                <div class="chart-container half">
                    <h3><?php _e('User Satisfaction', 'ai-website-chatbot'); ?></h3>
                    <div class="chart-wrapper">
                        <canvas id="satisfaction-chart"></canvas>
                    </div>
                </div>
            </div>

            <div class="chart-container">
                <h3><?php _e('Response Time Distribution', 'ai-website-chatbot'); ?></h3>
                <div class="response-times">
                    <div class="time-stats">
                        <div class="time-stat">
                            <span class="stat-label"><?php _e('Average:', 'ai-website-chatbot'); ?></span>
                            <span class="stat-value" id="avg-time">-</span>
                        </div>
                        <div class="time-stat">
                            <span class="stat-label"><?php _e('Median:', 'ai-website-chatbot'); ?></span>
                            <span class="stat-value" id="median-time">-</span>
                        </div>
                        <div class="time-stat">
                            <span class="stat-label"><?php _e('95th Percentile:', 'ai-website-chatbot'); ?></span>
                            <span class="stat-value" id="p95-time">-</span>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="response-time-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data tables -->
        <div class="analytics-tables">
            <div class="table-container">
                <h3><?php _e('Detailed Analytics', 'ai-website-chatbot'); ?></h3>
                <div class="table-tabs">
                    <button class="tab-button active" data-tab="volume"><?php _e('Volume', 'ai-website-chatbot'); ?></button>
                    <button class="tab-button" data-tab="topics"><?php _e('Topics', 'ai-website-chatbot'); ?></button>
                    <button class="tab-button" data-tab="usage"><?php _e('Usage', 'ai-website-chatbot'); ?></button>
                </div>
                
                <div id="volume-table" class="tab-content active">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'ai-website-chatbot'); ?></th>
                                <th><?php _e('Conversations', 'ai-website-chatbot'); ?></th>
                                <th><?php _e('Change', 'ai-website-chatbot'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="volume-table-body">
                            <tr>
                                <td colspan="3"><?php _e('Loading...', 'ai-website-chatbot'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="topics-table" class="tab-content">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Topic', 'ai-website-chatbot'); ?></th>
                                <th><?php _e('Count', 'ai-website-chatbot'); ?></th>
                                <th><?php _e('Percentage', 'ai-website-chatbot'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="topics-table-body">
                            <tr>
                                <td colspan="3"><?php _e('Loading...', 'ai-website-chatbot'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="usage-table" class="tab-content">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Metric', 'ai-website-chatbot'); ?></th>
                                <th><?php _e('Value', 'ai-website-chatbot'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="usage-table-body">
                            <tr>
                                <td colspan="2"><?php _e('Loading...', 'ai-website-chatbot'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading overlay -->
    <div id="analytics-loading" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p><?php _e('Loading analytics data...', 'ai-website-chatbot'); ?></p>
        </div>
    </div>
</div>
