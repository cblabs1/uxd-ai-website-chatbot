/**
 * AI Chatbot Admin Analytics JavaScript
 * 
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var AIChatbotAnalytics = {
        
        charts: {},
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.initDateRangePicker();
            this.loadAnalyticsData();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Date range change
            $(document).on('change', '#analytics-date-range', this.handleDateRangeChange);
            
            // Export analytics
            $(document).on('click', '.export-analytics', this.exportAnalytics);
            
            // Refresh data
            $(document).on('click', '.refresh-analytics', this.refreshAnalytics);
            
            // Filter changes
            $(document).on('change', '.analytics-filter', this.handleFilterChange);
            
            // Chart type toggle
            $(document).on('click', '.chart-type-toggle', this.toggleChartType);
            
            // Real-time updates toggle
            $(document).on('change', '#realtime-updates', this.toggleRealtimeUpdates);
        },
        
        /**
         * Initialize charts
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded');
                return;
            }
            
            // Set default Chart.js options
            Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            Chart.defaults.color = '#666';
            
            this.initConversationTrendsChart();
            this.initResponseTimeChart();
            this.initSatisfactionChart();
            this.initTopicsChart();
            this.initHourlyDistributionChart();
        },
        
        /**
         * Initialize conversation trends chart
         */
        initConversationTrendsChart: function() {
            var $canvas = $('#conversation-trends-chart');
            if (!$canvas.length) return;
            
            var ctx = $canvas[0].getContext('2d');
            
            this.charts.conversationTrends = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Conversations',
                        data: [],
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Number of Conversations'
                            },
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return 'Date: ' + context[0].label;
                                },
                                label: function(context) {
                                    return 'Conversations: ' + context.parsed.y;
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize response time chart
         */
        initResponseTimeChart: function() {
            var $canvas = $('#response-time-chart');
            if (!$canvas.length) return;
            
            var ctx = $canvas[0].getContext('2d');
            
            this.charts.responseTime = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: [],
                        backgroundColor: 'rgba(40, 167, 69, 0.8)',
                        borderColor: '#28a745',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Response Time (ms)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize satisfaction chart
         */
        initSatisfactionChart: function() {
            var $canvas = $('#satisfaction-chart');
            if (!$canvas.length) return;
            
            var ctx = $canvas[0].getContext('2d');
            
            this.charts.satisfaction = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#28a745',
                            '#6f42c1',
                            '#fd7e14',
                            '#ffc107',
                            '#dc3545'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    var percentage = ((context.parsed * 100) / total).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize topics chart
         */
        initTopicsChart: function() {
            var $canvas = $('#topics-chart');
            if (!$canvas.length) return;
            
            var ctx = $canvas[0].getContext('2d');
            
            this.charts.topics = new Chart(ctx, {
                type: 'horizontalBar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Mentions',
                        data: [],
                        backgroundColor: 'rgba(108, 117, 125, 0.8)',
                        borderColor: '#6c757d',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Mentions'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize hourly distribution chart
         */
        initHourlyDistributionChart: function() {
            var $canvas = $('#hourly-distribution-chart');
            if (!$canvas.length) return;
            
            var ctx = $canvas[0].getContext('2d');
            
            this.charts.hourlyDistribution = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', 
                            '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23'],
                    datasets: [{
                        label: 'Conversations by Hour',
                        data: [],
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Hour of Day'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Conversations'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },
        
        /**
         * Load analytics data
         */
        loadAnalyticsData: function(dateRange) {
            dateRange = dateRange || '30';
            
            $('.analytics-loading').show();
            $('.analytics-content').addClass('loading');
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_analytics_data',
                    nonce: aiChatbotAdmin.nonce,
                    date_range: dateRange
                },
                success: function(response) {
                    if (response.success) {
                        AIChatbotAnalytics.updateAnalyticsDisplay(response.data);
                    } else {
                        AIChatbotAdmin.showNotification('Failed to load analytics data', 'error');
                    }
                },
                error: function() {
                    AIChatbotAdmin.showNotification('Failed to load analytics data', 'error');
                },
                complete: function() {
                    $('.analytics-loading').hide();
                    $('.analytics-content').removeClass('loading');
                }
            });
        },
        
        /**
         * Update analytics display
         */
        updateAnalyticsDisplay: function(data) {
            // Update statistics cards
            $('.stat-total-conversations').text(this.formatNumber(data.total_conversations || 0));
            $('.stat-conversations-today').text(this.formatNumber(data.conversations_today || 0));
            $('.stat-avg-response-time').text((data.avg_response_time || 0) + 'ms');
            $('.stat-user-satisfaction').text((data.user_satisfaction || 0) + '/5');
            
            // Update charts
            this.updateConversationTrendsChart(data.daily_trends || []);
            this.updateResponseTimeChart(data.response_times || []);
            this.updateSatisfactionChart(data.satisfaction_distribution || []);
            this.updateTopicsChart(data.top_topics || []);
            this.updateHourlyDistributionChart(data.hourly_distribution || []);
            
            // Update last updated time
            $('.last-updated-time').text('Last updated: ' + new Date().toLocaleString());
        },
        
        /**
         * Update conversation trends chart
         */
        updateConversationTrendsChart: function(data) {
            if (!this.charts.conversationTrends) return;
            
            var labels = data.map(function(item) {
                return new Date(item.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
            });
            var values = data.map(function(item) {
                return parseInt(item.count);
            });
            
            this.charts.conversationTrends.data.labels = labels;
            this.charts.conversationTrends.data.datasets[0].data = values;
            this.charts.conversationTrends.update();
        },
        
        /**
         * Update response time chart
         */
        updateResponseTimeChart: function(data) {
            if (!this.charts.responseTime) return;
            
            var labels = data.map(function(item) {
                return new Date(item.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
            });
            var values = data.map(function(item) {
                return parseFloat(item.avg_response_time);
            });
            
            this.charts.responseTime.data.labels = labels;
            this.charts.responseTime.data.datasets[0].data = values;
            this.charts.responseTime.update();
        },
        
        /**
         * Update satisfaction chart
         */
        updateSatisfactionChart: function(data) {
            if (!this.charts.satisfaction) return;
            
            var values = [
                data['5'] || 0,
                data['4'] || 0,
                data['3'] || 0,
                data['2'] || 0,
                data['1'] || 0
            ];
            
            this.charts.satisfaction.data.datasets[0].data = values;
            this.charts.satisfaction.update();
        },
        
        /**
         * Update topics chart
         */
        updateTopicsChart: function(data) {
            if (!this.charts.topics) return;
            
            var labels = data.map(function(item) {
                return item.topic;
            });
            var values = data.map(function(item) {
                return parseInt(item.count);
            });
            
            this.charts.topics.data.labels = labels;
            this.charts.topics.data.datasets[0].data = values;
            this.charts.topics.update();
        },
        
        /**
         * Update hourly distribution chart
         */
        updateHourlyDistributionChart: function(data) {
            if (!this.charts.hourlyDistribution) return;
            
            // Initialize with zeros for all 24 hours
            var hourlyData = new Array(24).fill(0);
            
            // Fill in actual data
            data.forEach(function(item) {
                var hour = parseInt(item.hour);
                if (hour >= 0 && hour <= 23) {
                    hourlyData[hour] = parseInt(item.count);
                }
            });
            
            this.charts.hourlyDistribution.data.datasets[0].data = hourlyData;
            this.charts.hourlyDistribution.update();
        },
        
        /**
         * Handle date range change
         */
        handleDateRangeChange: function() {
            var dateRange = $(this).val();
            AIChatbotAnalytics.loadAnalyticsData(dateRange);
        },
        
        /**
         * Handle filter change
         */
        handleFilterChange: function() {
            var filter = $(this).val();
            var dateRange = $('#analytics-date-range').val();
            
            AIChatbotAnalytics.loadAnalyticsData(dateRange, filter);
        },
        
        /**
         * Export analytics
         */
        exportAnalytics: function(e) {
            e.preventDefault();
            
            var format = $(this).data('format') || 'csv';
            var dateRange = $('#analytics-date-range').val();
            var $button = $(this);
            
            $button.prop('disabled', true).text('Exporting...');
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_export_analytics',
                    nonce: aiChatbotAdmin.nonce,
                    format: format,
                    date_range: dateRange
                },
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(data, textStatus, xhr) {
                    // Create download
                    var filename = 'ai-chatbot-analytics-' + dateRange + 'days.' + format;
                    var blob = new Blob([data]);
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    AIChatbotAdmin.showNotification('Analytics exported successfully!', 'success');
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
         * Refresh analytics
         */
        refreshAnalytics: function(e) {
            e.preventDefault();
            
            var dateRange = $('#analytics-date-range').val();
            AIChatbotAnalytics.loadAnalyticsData(dateRange);
        },
        
        /**
         * Toggle chart type
         */
        toggleChartType: function(e) {
            e.preventDefault();
            
            var chartName = $(this).data('chart');
            var newType = $(this).data('type');
            
            if (AIChatbotAnalytics.charts[chartName]) {
                AIChatbotAnalytics.charts[chartName].config.type = newType;
                AIChatbotAnalytics.charts[chartName].update();
            }
        },
        
        /**
         * Toggle realtime updates
         */
        toggleRealtimeUpdates: function() {
            var enabled = $(this).is(':checked');
            
            if (enabled) {
                AIChatbotAnalytics.startRealtimeUpdates();
            } else {
                AIChatbotAnalytics.stopRealtimeUpdates();
            }
        },
        
        /**
         * Start realtime updates
         */
        startRealtimeUpdates: function() {
            if (this.realtimeInterval) {
                clearInterval(this.realtimeInterval);
            }
            
            this.realtimeInterval = setInterval(function() {
                var dateRange = $('#analytics-date-range').val();
                AIChatbotAnalytics.loadAnalyticsData(dateRange);
            }, 30000); // Update every 30 seconds
            
            AIChatbotAdmin.showNotification('Real-time updates enabled', 'success', 2000);
        },
        
        /**
         * Stop realtime updates
         */
        stopRealtimeUpdates: function() {
            if (this.realtimeInterval) {
                clearInterval(this.realtimeInterval);
                this.realtimeInterval = null;
            }
            
            AIChatbotAdmin.showNotification('Real-time updates disabled', 'info', 2000);
        },
        
        /**
         * Initialize date range picker
         */
        initDateRangePicker: function() {
            // Simple date range implementation
            $('#custom-date-range').on('change', function() {
                var customRange = $(this).val();
                if (customRange) {
                    $('#analytics-date-range').val('custom').trigger('change');
                }
            });
        },
        
        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            if (typeof num !== 'number') {
                num = parseInt(num) || 0;
            }
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        
        /**
         * Destroy charts (cleanup)
         */
        destroyCharts: function() {
            Object.keys(this.charts).forEach(function(key) {
                if (AIChatbotAnalytics.charts[key]) {
                    AIChatbotAnalytics.charts[key].destroy();
                }
            });
            this.charts = {};
        }
    };
    
    /**
     * Document ready
     */
    $(document).ready(function() {
        AIChatbotAnalytics.init();
    });
    
    /**
     * Window beforeunload - cleanup
     */
    $(window).on('beforeunload', function() {
        AIChatbotAnalytics.stopRealtimeUpdates();
        AIChatbotAnalytics.destroyCharts();
    });
    
    /**
     * Make AIChatbotAnalytics globally available
     */
    window.AIChatbotAnalytics = AIChatbotAnalytics;
    
})(jQuery);