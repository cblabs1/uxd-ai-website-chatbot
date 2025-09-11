<?php
/**
 * AI Chatbot Admin Analytics Class
 * 
 * Handles analytics and reporting functionality
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AI_Chatbot_Admin_Analytics
 */
class AI_Chatbot_Admin_Analytics {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_ai_chatbot_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_ai_chatbot_export_analytics', array($this, 'ajax_export_analytics'));
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ai-website-chatbot'));
        }
        
        // Get analytics data
        $analytics_data = $this->get_analytics_data();
        
        // Include analytics template
        include AI_CHATBOT_PLUGIN_DIR . 'admin/partials/admin-analytics-display.php';
    }
    
    /**
     * Get analytics data - FINAL VERSION WITH CORRECT JAVASCRIPT FIELD NAMES
     */
    public function get_analytics_data($date_range = 30) {
        global $wpdb;
        
        $conversations_table = $wpdb->prefix . 'ai_chatbot_conversations';
        $current_date = current_time('Y-m-d');
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$conversations_table'") != $conversations_table) {
            return $this->get_empty_analytics_data();
        }
        
        $data = array();
        $date_filter = "AND created_at >= DATE_SUB(NOW(), INTERVAL {$date_range} DAY)";
        
        // ===== BASIC STATISTICS =====
        
        $data['total_conversations'] = intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT conversation_id) FROM $conversations_table 
            WHERE conversation_id != '' AND conversation_id IS NOT NULL"
        ) ?: 0);
        
        $data['total_messages'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $conversations_table"
        ) ?: 0);
        
        $data['conversations_today'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT conversation_id) FROM $conversations_table 
            WHERE DATE(created_at) = %s AND conversation_id != ''", 
            $current_date
        )) ?: 0);
        
        $data['conversations_this_month'] = intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT conversation_id) FROM $conversations_table 
            WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND conversation_id != ''"
        ) ?: 0);
        
        // ===== PERFORMANCE METRICS =====
        
        $avg_response_time = $wpdb->get_var(
            "SELECT AVG(response_time) FROM $conversations_table WHERE response_time > 0 $date_filter"
        );
        $data['avg_response_time'] = $avg_response_time ? round($avg_response_time * 1000, 0) : 0;
        
        $data['total_tokens'] = intval($wpdb->get_var(
            "SELECT SUM(tokens_used) FROM $conversations_table WHERE tokens_used > 0 $date_filter"
        ) ?: 0);
        
        // ===== USER METRICS =====
        
        $data['unique_users'] = intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE 
                WHEN user_email != '' AND user_email IS NOT NULL THEN user_email 
                ELSE session_id 
            END) FROM $conversations_table WHERE 1=1 $date_filter"
        ) ?: 0);
        
        $data['registered_users'] = intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT user_email) FROM $conversations_table 
            WHERE user_email != '' AND user_email IS NOT NULL $date_filter"
        ) ?: 0);
        
        $data['anonymous_users'] = $data['unique_users'] - $data['registered_users'];
        
        // ===== SATISFACTION METRICS =====
        
        $ratings = $wpdb->get_results(
            "SELECT rating, COUNT(*) as count FROM $conversations_table 
            WHERE rating IS NOT NULL AND rating > 0 $date_filter 
            GROUP BY rating"
        );
        
        $data['satisfaction'] = array(
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
            'total_rated' => 0,
            'satisfaction_rate' => 0
        );
        
        // FIXED: JavaScript expects satisfaction_distribution with string keys
        $data['satisfaction_distribution'] = array(
            '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0
        );
        
        $total_rating_sum = 0;
        foreach ($ratings as $rating) {
            $rating_val = intval($rating->rating);
            $count = intval($rating->count);
            
            if ($rating_val >= 4) {
                $data['satisfaction']['positive'] += $count;
            } elseif ($rating_val <= 2) {
                $data['satisfaction']['negative'] += $count;
            } else {
                $data['satisfaction']['neutral'] += $count;
            }
            
            $data['satisfaction']['total_rated'] += $count;
            $total_rating_sum += ($rating_val * $count);
            
            // FIXED: Ensure keys are strings for JavaScript
            $data['satisfaction_distribution'][(string)$rating_val] = $count;
        }
        
        if ($data['satisfaction']['total_rated'] > 0) {
            $data['satisfaction']['satisfaction_rate'] = round(
                ($data['satisfaction']['positive'] / $data['satisfaction']['total_rated']) * 100, 1
            );
            $data['user_satisfaction'] = round($total_rating_sum / $data['satisfaction']['total_rated'], 1);
        } else {
            $data['satisfaction']['satisfaction_rate'] = 0;
            $data['user_satisfaction'] = 0;
        }
        
        // ===== CONVERSATION TRENDS =====
        
        $conversations_trend = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(DISTINCT conversation_id) as count 
            FROM $conversations_table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
            AND conversation_id != ''
            GROUP BY DATE(created_at) 
            ORDER BY date ASC",
            $date_range
        ));
        
        $data['conversations_trend'] = array();
        
        // Create last 7 days of data
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $count = 0;
            
            foreach ($conversations_trend as $trend) {
                if ($trend->date === $date) {
                    $count = intval($trend->count);
                    break;
                }
            }
            
            $data['conversations_trend'][] = array(
                'date' => $date,
                'count' => $count
            );
        }
        
        // ===== RESPONSE TIME TRENDS =====
        
        $response_time_trend = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, AVG(response_time) as avg_response_time 
            FROM $conversations_table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
            AND response_time > 0
            GROUP BY DATE(created_at) 
            ORDER BY date ASC",
            $date_range
        ));
        
        $data['response_time_trend'] = array();
        
        // Create last 7 days of response time data
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $avg_time = 0;
            
            foreach ($response_time_trend as $trend) {
                if ($trend->date === $date) {
                    $avg_time = round(floatval($trend->avg_response_time) * 1000, 0);
                    break;
                }
            }
            
            $data['response_time_trend'][] = array(
                'date' => $date,
                'avg_response_time' => $avg_time
            );
        }
        
        // ===== TOP TOPICS =====
        
        $top_queries = $wpdb->get_results($wpdb->prepare(
            "SELECT user_message, COUNT(*) as count 
            FROM $conversations_table 
            WHERE user_message IS NOT NULL 
            AND user_message != '' 
            AND LENGTH(user_message) > 3
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY user_message 
            ORDER BY count DESC 
            LIMIT 10",
            $date_range
        ));
        
        $data['top_topics'] = array();
        foreach ($top_queries as $query) {
            $intent = $this->extract_intent_from_message($query->user_message);
            
            // FIXED: JavaScript expects 'topic' field, not 'intent'
            $data['top_topics'][] = array(
                'topic' => $intent,
                'intent' => $intent, // Keep both for backward compatibility
                'query' => wp_trim_words($query->user_message, 8),
                'count' => intval($query->count)
            );
        }
        
        // ===== HOURLY DISTRIBUTION =====
        
        $hourly_data = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count 
            FROM $conversations_table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY HOUR(created_at) 
            ORDER BY hour ASC",
            $date_range
        ));
        
        // FIXED: Initialize proper array structure for JavaScript
        $data['hourly_distribution'] = array();
        for ($i = 0; $i < 24; $i++) {
            $data['hourly_distribution'][] = array(
                'hour' => $i,
                'count' => 0
            );
        }
        
        // Fill in actual data
        foreach ($hourly_data as $hour_data) {
            $hour = intval($hour_data->hour);
            if ($hour >= 0 && $hour <= 23) {
                $data['hourly_distribution'][$hour]['count'] = intval($hour_data->count);
            }
        }
        
        // ===== PROVIDER STATISTICS =====
        
        $providers = $wpdb->get_results($wpdb->prepare(
            "SELECT provider, model, COUNT(*) as count, AVG(response_time) as avg_time, SUM(tokens_used) as total_tokens
            FROM $conversations_table 
            WHERE provider IS NOT NULL AND provider != ''
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY provider, model 
            ORDER BY count DESC",
            $date_range
        ));
        
        $data['providers'] = array();
        foreach ($providers as $provider) {
            $data['providers'][] = array(
                'name' => $provider->provider,
                'model' => $provider->model,
                'count' => intval($provider->count),
                'avg_response_time' => round(floatval($provider->avg_time) * 1000, 0),
                'total_tokens' => intval($provider->total_tokens)
            );
        }
        
        // ===== GROWTH METRICS =====
        
        $yesterday_conversations = intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT conversation_id) FROM $conversations_table 
            WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND conversation_id != ''"
        ) ?: 0);
        
        if ($yesterday_conversations > 0 && $data['conversations_today'] > 0) {
            $data['growth_rate'] = round(
                (($data['conversations_today'] - $yesterday_conversations) / $yesterday_conversations) * 100, 1
            );
        } else {
            $data['growth_rate'] = $data['conversations_today'] > 0 ? 100 : 0;
        }
        
        // ===== CALCULATED METRICS =====
        
        if ($data['total_conversations'] > 0) {
            $data['avg_messages_per_conversation'] = round($data['total_messages'] / $data['total_conversations'], 1);
        } else {
            $data['avg_messages_per_conversation'] = 0;
        }
        
        if ($data['unique_users'] > 0) {
            $data['avg_messages_per_user'] = round($data['total_messages'] / $data['unique_users'], 1);
        } else {
            $data['avg_messages_per_user'] = 0;
        }
		
		
		// ===== MOST ACTIVE HOURS (for table) =====

		$hourly_data = $wpdb->get_results($wpdb->prepare(
			"SELECT HOUR(created_at) as hour, COUNT(*) as count 
			 FROM $conversations_table 
			 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
			 GROUP BY HOUR(created_at) 
			 ORDER BY count DESC 
			 LIMIT 10",
			$date_range
		));

		$data['active_hours'] = array();
		foreach ($hourly_data as $hour_data) {
			$data['active_hours'][] = (object) array(
				'hour' => intval($hour_data->hour),
				'count' => intval($hour_data->count)
			);
		}

		// ===== CONVERSATION STATUS DISTRIBUTION (for table) =====

		$status_data = $wpdb->get_results($wpdb->prepare(
			"SELECT status, COUNT(*) as count 
			 FROM $conversations_table 
			 WHERE status IS NOT NULL AND status != ''
			 AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
			 GROUP BY status 
			 ORDER BY count DESC",
			$date_range
		));

		$data['status_distribution'] = array();
		foreach ($status_data as $status_item) {
			$data['status_distribution'][] = (object) array(
				'status' => $status_item->status,
				'count' => intval($status_item->count)
			);
		}

        $data['insights'] = $this->generate_key_insights($data);
        
        return $data;
    }

    /**
     * Extract intent from user message (simple keyword analysis)
     * 
     * @param string $message User message
     * @return string Extracted intent/topic
     */
    private function extract_intent_from_message($message) {
        $message = strtolower(trim($message));
        
        $intents = array(
            'greeting' => array('hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'howdy', 'greetings', 'salutations', 'how are you', 'how\'s it going', 'nice to meet you', 'pleased to meet you'),

            'information' => array('what', 'how', 'when', 'where', 'who', 'why', 'information', 'about', 'context', 'paragraph', 'details', 'explain', 'describe', 'define', 'inquire', 'provide', 'tell me', 'give me', 'data', 'facts', 'knowledge', 'background', 'report', 'summary'),

            'goodbye' => array('bye', 'goodbye', 'see you', 'farewell', 'ok bye', 'later', 'talk to you later', 'take care', 'talk to you soon', 'have a nice day', 'until next time', 'so long', 'cheers'),

            'website' => array('website', 'site', 'page', 'about', 'home page', 'contact us', 'webpage', 'online', 'portal', 'link', 'url', 'directory', 'blog', 'forum', 'section', 'visit'),

            'help' => array('help', 'support', 'assist', 'can you', 'aid', 'guidance', 'troubleshoot', 'fix', 'problem', 'issue', 'need help', 'do you know', 'assist me', 'guidance', 'assistance', 'can you help'),

            'content' => array('content', 'paragraph', 'text', 'writing', 'article', 'summary', 'blog post', 'document', 'report', 'script', 'story', 'essay', 'compose', 'create', 'write', 'generate', 'draft', 'publish', 'topic', 'subject')
        );
        
        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    return ucfirst($intent);
                }
            }
        }
        
        $words = explode(' ', $message);
        $first_word = !empty($words) ? ucfirst($words[0]) : 'General';
        
        return $first_word;
    }
    
    /**
     * Get empty analytics data structure
     */
    private function get_empty_analytics_data() {
        return array(
            'total_conversations' => 0,
            'total_messages' => 0,
            'conversations_today' => 0,
            'conversations_this_month' => 0,
            'avg_response_time' => 0,
            'total_tokens' => 0,
            'unique_users' => 0,
            'registered_users' => 0,
            'anonymous_users' => 0,
            'user_satisfaction' => 0,
            'satisfaction' => array(
                'positive' => 0,
                'negative' => 0,
                'total_rated' => 0,
                'satisfaction_rate' => 0
            ),
            'satisfaction_distribution' => array(),
            'conversations_trend' => array(),
            'response_time_trend' => array(),
            'top_topics' => array(),
            'hourly_distribution' => array(),
            'providers' => array(),
            'growth_rate' => 0,
            'avg_messages_per_conversation' => 0,
            'avg_messages_per_user' => 0
        );
    }
    
    /**
     * Calculate bounce rate (conversations with only 1 user message)
     */
    private function calculate_bounce_rate($since_date) {
        global $wpdb;
        
        $total_conversations = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ai_chatbot_conversations 
            WHERE DATE(created_at) >= %s
        ", $since_date));
        
        if ($total_conversations == 0) {
            return 0;
        }
        
        $bounce_conversations = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM (
                SELECT conversation_id, COUNT(*) as user_messages 
                FROM {$wpdb->prefix}ai_chatbot_messages 
                WHERE sender_type = 'user' AND DATE(created_at) >= %s
                GROUP BY conversation_id 
                HAVING user_messages = 1
            ) as bounced
        ", $since_date));
        
        return round(($bounce_conversations / $total_conversations) * 100, 1);
    }
    
    /**
     * AJAX: Get analytics data
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $date_range = intval($_POST['date_range'] ?? '30');
        $data = $this->get_analytics_data($date_range);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get analytics data by date range
     */
    private function get_analytics_data_by_range($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        $data = array();
        
        // Conversations in range
        $data['conversations_in_range'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) >= %s",
            $start_date
        ));
        
        // Daily trends for the range
        $data['daily_trends'] = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM $table_name 
            WHERE DATE(created_at) >= %s 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC
        ", $start_date));
        
        // Hourly distribution
        $data['hourly_distribution'] = $wpdb->get_results($wpdb->prepare("
            SELECT HOUR(created_at) as hour, COUNT(*) as count 
            FROM $table_name 
            WHERE DATE(created_at) >= %s
            GROUP BY HOUR(created_at) 
            ORDER BY hour ASC
        ", $start_date));
        
        return $data;
    }
    
    /**
     * AJAX: Export analytics
     */
    public function ajax_export_analytics() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-website-chatbot'));
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $date_range = sanitize_text_field($_POST['date_range'] ?? '30');
        
        $data = $this->get_export_data($date_range);
        
        if ($format === 'json') {
            $this->export_json($data);
        } else {
            $this->export_csv($data);
        }
    }
    
    /**
     * Get data for export
     */
    private function get_export_data($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                id,
                user_name,
                user_message,
                ai_response,
                intent,
                status,
                rating,
                response_time,
                created_at
            FROM $table_name 
            WHERE DATE(created_at) >= %s 
            ORDER BY created_at DESC
        ", $start_date), ARRAY_A);
    }
    
    /**
     * Export data as CSV
     */
    private function export_csv($data) {
        $filename = 'ai-chatbot-analytics-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        if (!empty($data)) {
            // Write headers
            fputcsv($output, array_keys($data[0]));
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data as JSON
     */
    private function export_json($data) {
        $filename = 'ai-chatbot-analytics-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Get chart data for dashboard
     */
    public function get_chart_data($type = 'conversations', $days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        $labels = array();
        $data = array();
        
        // Generate date labels
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));
            
            // Get count for this date
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
                $date
            ));
            
            $data[] = intval($count);
        }
        
        return array(
            'labels' => $labels,
            'data' => $data
        );
    }

    private function generate_key_insights($analytics_data) {
        $insights = array();
        
        // ===== CONVERSATION VOLUME INSIGHTS =====
        
        if ($analytics_data['total_conversations'] > 0) {
            if ($analytics_data['conversations_today'] > 0) {
                $insights[] = array(
                    'type' => 'positive',
                    'icon' => 'dashicons-chart-line',
                    'title' => __('Active Engagement Today', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('You have %d conversations today out of %d total conversations. Great user engagement!', 'ai-website-chatbot'),
                        $analytics_data['conversations_today'],
                        $analytics_data['total_conversations']
                    )
                );
            }
            
            // Growth insight
            if ($analytics_data['growth_rate'] > 0) {
                $insights[] = array(
                    'type' => 'positive',
                    'icon' => 'dashicons-arrow-up-alt',
                    'title' => __('Growing Usage', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Conversations have grown by %s%% compared to the previous period. Your chatbot is gaining traction!', 'ai-website-chatbot'),
                        number_format($analytics_data['growth_rate'], 1)
                    )
                );
            } elseif ($analytics_data['growth_rate'] < -10) {
                $insights[] = array(
                    'type' => 'warning',
                    'icon' => 'dashicons-arrow-down-alt',
                    'title' => __('Declining Usage', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Conversations have decreased by %s%%. Consider promoting your chatbot or improving visibility.', 'ai-website-chatbot'),
                        number_format(abs($analytics_data['growth_rate']), 1)
                    )
                );
            }
        } else {
            $insights[] = array(
                'type' => 'info',
                'icon' => 'dashicons-info',
                'title' => __('Getting Started', 'ai-website-chatbot'),
                'message' => __('No conversations yet. Make sure your chatbot is enabled and visible on your website. Test it yourself to get started!', 'ai-website-chatbot')
            );
        }
        
        // ===== PERFORMANCE INSIGHTS =====
        
        if ($analytics_data['avg_response_time'] > 0) {
            if ($analytics_data['avg_response_time'] < 2000) { // Less than 2 seconds
                $insights[] = array(
                    'type' => 'positive',
                    'icon' => 'dashicons-performance',
                    'title' => __('Excellent Response Time', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Average response time is %dms - that\'s excellent! Users are getting quick responses.', 'ai-website-chatbot'),
                        $analytics_data['avg_response_time']
                    )
                );
            } elseif ($analytics_data['avg_response_time'] > 5000) { // More than 5 seconds
                $insights[] = array(
                    'type' => 'warning',
                    'icon' => 'dashicons-clock',
                    'title' => __('Slow Response Times', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Average response time is %dms. Consider optimizing your AI provider settings or checking your internet connection.', 'ai-website-chatbot'),
                        $analytics_data['avg_response_time']
                    )
                );
            }
        }
        
        // ===== USER SATISFACTION INSIGHTS =====
        
        if ($analytics_data['user_satisfaction'] > 0) {
            if ($analytics_data['user_satisfaction'] >= 4) {
                $insights[] = array(
                    'type' => 'positive',
                    'icon' => 'dashicons-star-filled',
                    'title' => __('High User Satisfaction', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Users rate your chatbot %s/5 stars on average. Excellent job on providing helpful responses!', 'ai-website-chatbot'),
                        number_format($analytics_data['user_satisfaction'], 1)
                    )
                );
            } elseif ($analytics_data['user_satisfaction'] < 3) {
                $insights[] = array(
                    'type' => 'warning',
                    'icon' => 'dashicons-star-empty',
                    'title' => __('Satisfaction Needs Improvement', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Average rating is %s/5. Consider adding more training data or improving your AI prompts to better help users.', 'ai-website-chatbot'),
                        number_format($analytics_data['user_satisfaction'], 1)
                    )
                );
            }
        }
        
        // ===== USAGE PATTERN INSIGHTS =====
        
        if (!empty($analytics_data['active_hours'])) {
            $peak_hour = $analytics_data['active_hours'][0]; // First item has highest count
            
            if ($peak_hour->count > 1) {
                $hour_display = $peak_hour->hour . ':00';
                $insights[] = array(
                    'type' => 'info',
                    'icon' => 'dashicons-clock',
                    'title' => __('Peak Usage Hours', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Most conversations happen around %s with %d conversations. Consider this timing for announcements or support availability.', 'ai-website-chatbot'),
                        $hour_display,
                        $peak_hour->count
                    )
                );
            }
        }
        
        // ===== CONTENT INSIGHTS =====
        
        if (!empty($analytics_data['top_topics'])) {
            $top_topic = $analytics_data['top_topics'][0];
            $insights[] = array(
                'type' => 'info',
                'icon' => 'dashicons-format-chat',
                'title' => __('Popular Discussion Topic', 'ai-website-chatbot'),
                'message' => sprintf(
                    __('"%s" is your most discussed topic with %d mentions. Consider creating more content around this topic.', 'ai-website-chatbot'),
                    $top_topic['topic'],
                    $top_topic['count']
                )
            );
        }
        
        // ===== USER ENGAGEMENT INSIGHTS =====
        
        if ($analytics_data['avg_messages_per_conversation'] > 0) {
            if ($analytics_data['avg_messages_per_conversation'] >= 3) {
                $insights[] = array(
                    'type' => 'positive',
                    'icon' => 'dashicons-format-chat',
                    'title' => __('High Engagement', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Users send an average of %s messages per conversation. This shows good engagement and multi-turn conversations!', 'ai-website-chatbot'),
                        number_format($analytics_data['avg_messages_per_conversation'], 1)
                    )
                );
            } elseif ($analytics_data['avg_messages_per_conversation'] < 2) {
                $insights[] = array(
                    'type' => 'warning',
                    'icon' => 'dashicons-format-chat',
                    'title' => __('Short Conversations', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Average conversation length is %s messages. Users might not be getting complete answers. Consider improving response depth.', 'ai-website-chatbot'),
                        number_format($analytics_data['avg_messages_per_conversation'], 1)
                    )
                );
            }
        }
        
        // ===== PROVIDER INSIGHTS =====
        
        if (!empty($analytics_data['providers'])) {
            $main_provider = $analytics_data['providers'][0];
            if ($main_provider['total_tokens'] > 0) {
                $insights[] = array(
                    'type' => 'info',
                    'icon' => 'dashicons-admin-tools',
                    'title' => __('AI Usage Summary', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('Using %s with %s model. Total tokens consumed: %s. Monitor usage to control costs.', 'ai-website-chatbot'),
                        ucfirst($main_provider['name']),
                        $main_provider['model'],
                        number_format($main_provider['total_tokens'])
                    )
                );
            }
        }
        
        // ===== STATUS INSIGHTS =====
        
        if (!empty($analytics_data['status_distribution'])) {
            $completed_count = 0;
            $active_count = 0;
            
            foreach ($analytics_data['status_distribution'] as $status) {
                if ($status->status === 'completed') {
                    $completed_count = $status->count;
                } elseif ($status->status === 'active') {
                    $active_count = $status->count;
                }
            }
            
            if ($active_count > $completed_count) {
                $insights[] = array(
                    'type' => 'warning',
                    'icon' => 'dashicons-warning',
                    'title' => __('Unresolved Conversations', 'ai-website-chatbot'),
                    'message' => sprintf(
                        __('You have %d active conversations vs %d completed ones. Some users might need follow-up assistance.', 'ai-website-chatbot'),
                        $active_count,
                        $completed_count
                    )
                );
            }
        }
        
        // ===== ACTIONABLE RECOMMENDATIONS =====
        
        // If no insights generated (edge case), provide general advice
        if (empty($insights)) {
            $insights[] = array(
                'type' => 'info',
                'icon' => 'dashicons-lightbulb',
                'title' => __('Getting Started', 'ai-website-chatbot'),
                'message' => __('Your chatbot is set up! Encourage visitors to try it out, and check back here for insights as usage grows.', 'ai-website-chatbot')
            );
        }
        
        // Limit to max 4 insights to avoid overwhelming
        return array_slice($insights, 0, 4);
    }
}