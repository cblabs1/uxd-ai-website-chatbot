/**
 * AI Chatbot Admin Analytics JavaScript - FIXED VERSION
 * 
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var AIChatbotAnalytics = {
        
        charts: {},
        initialized: false,
        
        /**
         * Initialize
         */
        init: function() {
            if (this.initialized) {
                return; // Prevent double initialization
            }
            
            this.bindEvents();
            this.initCharts();
            this.initDateRangePicker();
            this.initialized = true;
            
            console.log('AIChatbotAnalytics initialized');
        },
        
        /**
         * Update analytics data and refresh charts
         */
        updateAnalyticsData: function(data) {
            console.log('Updating analytics data:', data);
            
            // Update statistics
            this.updateStatistics(data);
            
            // Update charts
            this.updateConversationTrendsChart(data.conversations_trend || []);
            this.updateResponseTimeChart(data.response_time_trend || []);
            this.updateSatisfactionChart(data.satisfaction_distribution || {});
            this.updateTopicsChart(data.top_topics || []);
            this.updateHourlyDistributionChart(data.hourly_distribution || []);
            
            // Update last updated time
            $('.last-updated-time').text('Last updated: ' + new Date().toLocaleString());
        },
        
        /**
         * Update statistics cards
         */
        updateStatistics: function(data) {
            $('.stat-total-conversations').text(data.total_conversations || 0);
            $('.stat-total-messages').text(data.total_messages || 0);
            $('.stat-conversations-today').text(data.conversations_today || 0);
            $('.stat-avg-response-time').text((data.avg_response_time || 0) + 'ms');
            $('.stat-user-satisfaction').text((data.user_satisfaction || 0) + '/5');
            $('.stat-unique-users').text(data.unique_users || 0);
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Date range change
            $(document).on('change', '#analytics-date-range', this.handleDateRangeChange.bind(this));
            
            // Export analytics
            $(document).on('click', '.export-analytics', this.exportAnalytics.bind(this));
            
            // Refresh data
            $(document).on('click', '.refresh-analytics', this.refreshAnalytics.bind(this));
            
            // Filter changes
            $(document).on('change', '.analytics-filter', this.handleFilterChange.bind(this));
            
            // Chart type toggle
            $(document).on('click', '.chart-type-toggle', this.toggleChartType.bind(this));
            
            // Real-time updates toggle
            $(document).on('change', '#realtime-updates', this.toggleRealtimeUpdates.bind(this));
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
            
            // Destroy existing charts first
            this.destroyExistingCharts();
            
            // Initialize new charts
            this.initConversationTrendsChart();
            this.initResponseTimeChart();
            this.initSatisfactionChart();
            this.initTopicsChart();
            this.initHourlyDistributionChart();
        },
        
        /**
         * Destroy existing charts to prevent conflicts
         */
        destroyExistingCharts: function() {
            Object.keys(this.charts).forEach(function(key) {
                if (this.charts[key] && typeof this.charts[key].destroy === 'function') {
                    this.charts[key].destroy();
                }
            }.bind(this));
            this.charts = {};
        },
        
        /**
         * Initialize conversation trends chart
         */
        initConversationTrendsChart: function() {
            var $canvas = $('#conversation-trends-chart');
            if (!$canvas.length) {
                console.warn('Conversation trends canvas not found');
                return;
            }
            
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
            
            console.log('Conversation trends chart initialized');
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
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
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
                            '#ffc107',
                            '#fd7e14',
                            '#dc3545'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize topics chart - FIXED: Changed horizontalBar to bar with indexAxis
         */
        initTopicsChart: function() {
            var $canvas = $('#topics-chart');
            if (!$canvas.length) return;
            
            var ctx = $canvas[0].getContext('2d');
            
            this.charts.topics = new Chart(ctx, {
                type: 'bar', // FIXED: Changed from 'horizontalBar'
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
                    indexAxis: 'y', // FIXED: This makes it horizontal
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
         * Update conversation trends chart - FIXED
         */
        updateConversationTrendsChart: function(data) {
            console.log('Updating conversation trends chart with:', data);
            
            if (!this.charts.conversationTrends) {
                console.warn('Conversation trends chart not initialized');
                return;
            }
            
            var labels = data.map(function(item) {
                return new Date(item.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
            });
            var values = data.map(function(item) {
                return parseInt(item.count);
            });
            
            console.log('Chart labels:', labels);
            console.log('Chart values:', values);
            
            this.charts.conversationTrends.data.labels = labels;
            this.charts.conversationTrends.data.datasets[0].data = values;
            this.charts.conversationTrends.update();
            
            console.log('Conversation trends chart updated');
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
                return item.topic || item.intent || 'Unknown';
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
            var dateRange = $('#analytics-date-range').val();
            this.loadAnalyticsData(dateRange);
        },
        
        /**
         * Load analytics data via AJAX
         */
        loadAnalyticsData: function(dateRange) {
            var self = this;
            
            $.ajax({
                url: aiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chatbot_get_analytics_data',
                    nonce: aiChatbotAdmin.nonce,
                    date_range: dateRange || 30
                },
                beforeSend: function() {
                    $('.analytics-loading').show();
                },
                success: function(response) {
                    if (response.success) {
                        self.updateAnalyticsData(response.data);
                    } else {
                        console.error('Failed to load analytics data:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                },
                complete: function() {
                    $('.analytics-loading').hide();
                }
            });
        },
        
        /**
         * Refresh analytics
         */
        refreshAnalytics: function() {
            var dateRange = $('#analytics-date-range').val();
            this.loadAnalyticsData(dateRange);
        },
        
        /**
         * Handle filter change
         */
        handleFilterChange: function() {
            var filter = $('.analytics-filter').val();
            var dateRange = $('#analytics-date-range').val();
            this.loadAnalyticsData(dateRange, filter);
        },
        
        /**
         * Export analytics
         */
        exportAnalytics: function(e) {
            e.preventDefault();
            
            var format = $(e.currentTarget).data('format') || 'csv';
            var dateRange = $('#analytics-date-range').val();
            var $button = $(e.currentTarget);
            
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
                success: function(data) {
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
                },
                error: function() {
                    console.error('Export failed');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Export ' + format.toUpperCase());
                }
            });
        },
        
        /**
         * Toggle chart type
         */
        toggleChartType: function(e) {
            // Chart type toggle functionality
            var chartName = $(e.currentTarget).data('chart');
            var chartType = $(e.currentTarget).data('type');
            
            if (this.charts[chartName]) {
                this.charts[chartName].config.type = chartType;
                this.charts[chartName].update();
            }
        },
        
        /**
         * Toggle real-time updates
         */
        toggleRealtimeUpdates: function() {
            // Real-time updates functionality
            var enabled = $('#realtime-updates').is(':checked');
            
            if (enabled) {
                // Set up periodic refresh
                this.realtimeInterval = setInterval(this.refreshAnalytics.bind(this), 30000); // 30 seconds
            } else {
                // Clear interval
                if (this.realtimeInterval) {
                    clearInterval(this.realtimeInterval);
                }
            }
        },
        
        /**
         * Initialize date range picker
         */
        initDateRangePicker: function() {
            // Basic date range picker setup
            $('#analytics-date-range').on('change', this.handleDateRangeChange.bind(this));
        }
    };

    // Make available globally
    window.AIChatbotAnalytics = AIChatbotAnalytics;

})(jQuery);