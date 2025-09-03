/**
 * AI Chatbot Analytics JavaScript
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    let charts = {};
    let currentPeriod = '7days';

    $(document).ready(function() {
        initCharts();
        loadAnalyticsData(currentPeriod);
        
        // Period selector
        $('#analytics-period').on('change', function() {
            currentPeriod = $(this).val();
            loadAnalyticsData(currentPeriod);
        });

        // Export functionality
        $('#export-analytics').on('click', function() {
            exportAnalytics();
        });

        // Tab switching
        $('.tab-button').on('click', function() {
            const tab = $(this).data('tab');
            switchTab(tab);
        });

        // Auto-refresh every 5 minutes
        setInterval(function() {
            loadAnalyticsData(currentPeriod);
        }, 300000);
    });

    // Initialize Chart.js charts
    function initCharts() {
        // Volume chart (line chart)
        const volumeCtx = document.getElementById('volume-chart');
        if (volumeCtx) {
            charts.volume = new Chart(volumeCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Conversations',
                        data: [],
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Topics chart (doughnut chart)
        const topicsCtx = document.getElementById('topics-chart');
        if (topicsCtx) {
            charts.topics = new Chart(topicsCtx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#0073aa', '#00a0d2', '#0085ba',
                            '#005177', '#003f5c', '#2c3e50'
                        ]
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
        }

        // Satisfaction chart (bar chart)
        const satisfactionCtx = document.getElementById('satisfaction-chart');
        if (satisfactionCtx) {
            charts.satisfaction = new Chart(satisfactionCtx, {
                type: 'bar',
                data: {
                    labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                    datasets: [{
                        label: 'Ratings',
                        data: [],
                        backgroundColor: [
                            '#e74c3c', '#f39c12', '#f1c40f', '#2ecc71', '#27ae60'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Response time chart (histogram)
        const responseTimeCtx = document.getElementById('response-time-chart');
        if (responseTimeCtx) {
            charts.responseTime = new Chart(responseTimeCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Responses',
                        data: [],
                        backgroundColor: '#0073aa'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }

    // Load analytics data from server
    function loadAnalyticsData(period) {
        $('#analytics-loading').show();
        
        $.ajax({
            url: ai_chatbot_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_chatbot_get_analytics_data',
                period: period,
                nonce: ai_chatbot_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateAnalytics(response.data);
                } else {
                    window.AIChatbotAdmin.showNotification('Failed to load analytics data', 'error');
                }
            },
            error: function() {
                window.AIChatbotAdmin.showNotification('Failed to load analytics data', 'error');
            },
            complete: function() {
                $('#analytics-loading').hide();
            }
        });
    }

    // Update analytics display with new data
    function updateAnalytics(data) {
        // Update summary cards
        updateSummaryCards(data);
        
        // Update charts
        updateCharts(data);
        
        // Update tables
        updateTables(data);
    }

    // Update summary cards
    function updateSummaryCards(data) {
        // Calculate totals
        const totalConversations = data.volume.reduce((sum, item) => sum + parseInt(item.count), 0);
        const avgResponseTime = data.response_times.average + 'ms';
        const satisfactionRate = calculateSatisfactionRate(data.satisfaction) + '%';
        const totalCost = '$' + (data.usage.total_cost || 0).toFixed(4);

        $('#total-conversations .card-number').text(window.AIChatbotAdmin.formatNumber(totalConversations));
        $('#avg-response-time .card-number').text(avgResponseTime);
        $('#satisfaction-rate .card-number').text(satisfactionRate);
        $('#total-cost .card-number').text(totalCost);
    }

    // Calculate satisfaction rate from ratings
    function calculateSatisfactionRate(satisfactionData) {
        if (!satisfactionData || satisfactionData.length === 0) return 0;
        
        const totalRatings = satisfactionData.reduce((sum, item) => sum + item.count, 0);
        const positiveRatings = satisfactionData.filter(item => item.rating >= 4)
                                                .reduce((sum, item) => sum + item.count, 0);
        
        return totalRatings > 0 ? Math.round((positiveRatings / totalRatings) * 100) : 0;
    }

    // Update all charts
    function updateCharts(data) {
        // Volume chart
        if (charts.volume && data.volume) {
            charts.volume.data.labels = data.volume.map(item => item.date);
            charts.volume.data.datasets[0].data = data.volume.map(item => item.count);
            charts.volume.update();
        }

        // Topics chart
        if (charts.topics && data.topics) {
            charts.topics.data.labels = data.topics.map(item => item.topic);
            charts.topics.data.datasets[0].data = data.topics.map(item => item.count);
            charts.topics.update();
        }

        // Satisfaction chart
        if (charts.satisfaction && data.satisfaction) {
            charts.satisfaction.data.datasets[0].data = data.satisfaction.map(item => item.count);
            charts.satisfaction.update();
        }

        // Response time chart
        if (charts.responseTime && data.response_times.distribution) {
            charts.responseTime.data.labels = data.response_times.distribution.map(item => item.range);
            charts.responseTime.data.datasets[0].data = data.response_times.distribution.map(item => item.count);
            charts.responseTime.update();
        }

        // Update response time stats
        if (data.response_times) {
            $('#avg-time').text(data.response_times.average + 'ms');
            $('#median-time').text(data.response_times.median + 'ms');
            $('#p95-time').text(data.response_times.p95 + 'ms');
        }
    }

    // Update data tables
    function updateTables(data) {
        // Volume table
        const volumeTableBody = $('#volume-table-body');
        volumeTableBody.empty();
        
        if (data.volume && data.volume.length > 0) {
            data.volume.forEach(function(item, index) {
                const prevCount = index > 0 ? data.volume[index - 1].count : item.count;
                const change = ((item.count - prevCount) / prevCount * 100).toFixed(1);
                const changeClass = change >= 0 ? 'positive' : 'negative';
                
                volumeTableBody.append(`
                    <tr>
                        <td>${item.date}</td>
                        <td>${item.count}</td>
                        <td class="${changeClass}">${change}%</td>
                    </tr>
                `);
            });
        } else {
            volumeTableBody.append('<tr><td colspan="3">No data available</td></tr>');
        }

        // Topics table
        const topicsTableBody = $('#topics-table-body');
        topicsTableBody.empty();
        
        if (data.topics && data.topics.length > 0) {
            const totalTopicCount = data.topics.reduce((sum, item) => sum + item.count, 0);
            
            data.topics.forEach(function(item) {
                const percentage = ((item.count / totalTopicCount) * 100).toFixed(1);
                
                topicsTableBody.append(`
                    <tr>
                        <td>${item.topic}</td>
                        <td>${item.count}</td>
                        <td>${percentage}%</td>
                    </tr>
                `);
            });
        } else {
            topicsTableBody.append('<tr><td colspan="3">No data available</td></tr>');
        }

        // Usage table
        const usageTableBody = $('#usage-table-body');
        usageTableBody.empty();
        
        if (data.usage) {
            usageTableBody.append(`
                <tr><td>Total Requests</td><td>${data.usage.total_requests || 0}</td></tr>
                <tr><td>Total Tokens</td><td>${window.AIChatbotAdmin.formatNumber(data.usage.total_tokens || 0)}</td></tr>
                <tr><td>Total Cost</td><td>$${(data.usage.total_cost || 0).toFixed(4)}</td></tr>
                <tr><td>Last Request</td><td>${data.usage.last_request || 'Never'}</td></tr>
            `);
        } else {
            usageTableBody.append('<tr><td colspan="2">No usage data available</td></tr>');
        }
    }

    // Switch between data table tabs
    function switchTab(tab) {
        $('.tab-button').removeClass('active');
        $('.tab-button[data-tab="' + tab + '"]').addClass('active');
        
        $('.tab-content').removeClass('active');
        $('#' + tab + '-table').addClass('active');
    }

    // Export analytics data
    function exportAnalytics() {
        const exportUrl = ai_chatbot_admin.ajax_url + 
            '?action=ai_chatbot_export_analytics' +
            '&period=' + currentPeriod +
            '&format=csv' +
            '&nonce=' + ai_chatbot_admin.nonce;
        
        // Create temporary download link
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = 'chatbot-analytics-' + currentPeriod + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        window.AIChatbotAdmin.showNotification('Analytics data exported successfully', 'success');
    }

})(jQuery);
