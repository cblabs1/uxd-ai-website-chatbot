<?php
/**
 * Analytics functionality for AI Chatbot Admin
 *
 * @package AI_Website_Chatbot
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin analytics class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Admin_Analytics {

	/**
	 * Database instance
	 *
	 * @var AI_Chatbot_Database
	 * @since 1.0.0
	 */
	private $database;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->database = new AI_Chatbot_Database();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_ai_chatbot_get_analytics_data', array( $this, 'get_analytics_data' ) );
		add_action( 'wp_ajax_ai_chatbot_export_analytics', array( $this, 'export_analytics' ) );
	}

	/**
	 * Get analytics dashboard data
	 *
	 * @param string $period Time period (day, week, month, year).
	 * @return array Analytics data.
	 * @since 1.0.0
	 */
	public function get_dashboard_data( $period = 'month' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		
		// Date condition based on period
		$date_conditions = array(
			'day'   => 'DATE(created_at) = CURDATE()',
			'week'  => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)',
			'month' => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
			'year'  => 'created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)',
		);

		$date_condition = $date_conditions[ $period ] ?? $date_conditions['month'];

		// Basic statistics
		$stats = $wpdb->get_row(
			"SELECT 
				COUNT(*) as total_conversations,
				COUNT(DISTINCT session_id) as unique_sessions,
				AVG(CASE WHEN rating IS NOT NULL THEN rating END) as avg_rating,
				COUNT(CASE WHEN rating = 1 THEN 1 END) as positive_ratings,
				COUNT(CASE WHEN rating = -1 THEN 1 END) as negative_ratings,
				COUNT(CASE WHEN rating IS NOT NULL THEN 1 END) as total_ratings
			FROM {$table_name} 
			WHERE {$date_condition}",
			ARRAY_A
		);

		// Popular hours
		$hourly_data = $wpdb->get_results(
			"SELECT 
				HOUR(created_at) as hour,
				COUNT(*) as conversations
			FROM {$table_name} 
			WHERE {$date_condition}
			GROUP BY HOUR(created_at)
			ORDER BY hour",
			ARRAY_A
		);

		// Daily trend (last 30 days)
		$daily_trend = $wpdb->get_results(
			"SELECT 
				DATE(created_at) as date,
				COUNT(*) as conversations,
				COUNT(DISTINCT session_id) as sessions
			FROM {$table_name} 
			WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			GROUP BY DATE(created_at)
			ORDER BY date",
			ARRAY_A
		);

		// Top pages
		$top_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					page_url,
					COUNT(*) as conversations
				FROM {$table_name} 
				WHERE {$date_condition} AND page_url != ''
				GROUP BY page_url
				ORDER BY conversations DESC
				LIMIT %d",
				10
			),
			ARRAY_A
		);

		return array(
			'stats' => $stats,
			'hourly_data' => $hourly_data,
			'daily_trend' => $daily_trend,
			'top_pages' => $top_pages,
			'period' => $period,
		);
	}

	/**
	 * Get conversation analytics
	 *
	 * @return array Conversation analytics.
	 * @since 1.0.0
	 */
	public function get_conversation_analytics() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';

		// Average conversation length
		$avg_length = $wpdb->get_results(
			"SELECT 
				session_id,
				COUNT(*) as message_count
			FROM {$table_name}
			GROUP BY session_id
			HAVING message_count > 1
			ORDER BY message_count DESC
			LIMIT 100",
			ARRAY_A
		);

		$conversation_lengths = array();
		foreach ( $avg_length as $conv ) {
			$length_range = $this->get_length_range( $conv['message_count'] );
			if ( ! isset( $conversation_lengths[ $length_range ] ) ) {
				$conversation_lengths[ $length_range ] = 0;
			}
			$conversation_lengths[ $length_range ]++;
		}

		// Most common user queries
		$common_queries = $wpdb->get_results(
			"SELECT 
				LOWER(SUBSTRING(user_message, 1, 50)) as query_start,
				COUNT(*) as frequency
			FROM {$table_name}
			WHERE LENGTH(user_message) > 10
			GROUP BY query_start
			HAVING frequency > 1
			ORDER BY frequency DESC
			LIMIT 20",
			ARRAY_A
		);

		return array(
			'conversation_lengths' => $conversation_lengths,
			'common_queries' => $common_queries,
		);
	}

	/**
	 * Get performance metrics
	 *
	 * @return array Performance metrics.
	 * @since 1.0.0
	 */
	public function get_performance_metrics() {
		$ai_provider = get_option( 'ai_chatbot_ai_provider', 'openai' );
		$usage_stats = get_option( "ai_chatbot_{$ai_provider}_usage_stats", array() );

		// Calculate satisfaction rate
		$satisfaction_data = $this->database->get_conversation_stats( 'month' );
		$satisfaction_rate = 0;
		
		if ( $satisfaction_data['total_ratings'] > 0 ) {
			$satisfaction_rate = ( $satisfaction_data['positive_ratings'] / $satisfaction_data['total_ratings'] ) * 100;
		}

		// Response time estimation (simplified)
		$avg_response_time = 2.5; // Average seconds for AI response

		// Cost analysis
		$monthly_cost = $usage_stats['total_cost'] ?? 0;
		$cost_per_conversation = 0;
		if ( $satisfaction_data['total_conversations'] > 0 ) {
			$cost_per_conversation = $monthly_cost / $satisfaction_data['total_conversations'];
		}

		return array(
			'satisfaction_rate' => round( $satisfaction_rate, 1 ),
			'avg_response_time' => $avg_response_time,
			'monthly_cost' => $monthly_cost,
			'cost_per_conversation' => round( $cost_per_conversation, 4 ),
			'total_api_requests' => $usage_stats['total_requests'] ?? 0,
			'total_tokens' => $usage_stats['total_tokens'] ?? 0,
		);
	}

	/**
	 * AJAX handler for analytics data
	 *
	 * @since 1.0.0
	 */
	public function get_analytics_data() {
		check_ajax_referer( 'ai_chatbot_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-website-chatbot' ) ) );
		}

		$period = sanitize_text_field( $_POST['period'] ?? 'month' );
		$type = sanitize_text_field( $_POST['type'] ?? 'dashboard' );

		try {
			switch ( $type ) {
				case 'dashboard':
					$data = $this->get_dashboard_data( $period );
					break;
				case 'conversations':
					$data = $this->get_conversation_analytics();
					break;
				case 'performance':
					$data = $this->get_performance_metrics();
					break;
				default:
					$data = $this->get_dashboard_data( $period );
			}

			wp_send_json_success( $data );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Export analytics data
	 *
	 * @since 1.0.0
	 */
	public function export_analytics() {
		check_ajax_referer( 'ai_chatbot_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-website-chatbot' ) ) );
		}

		$period = sanitize_text_field( $_POST['period'] ?? 'month' );
		$format = sanitize_text_field( $_POST['format'] ?? 'csv' );

		try {
			$data = $this->get_dashboard_data( $period );
			
			if ( $format === 'csv' ) {
				$csv_data = $this->export_to_csv( $data );
				wp_send_json_success( array(
					'data' => $csv_data,
					'filename' => 'chatbot-analytics-' . $period . '-' . date( 'Y-m-d' ) . '.csv',
				) );
			} else {
				wp_send_json_success( array(
					'data' => $data,
					'filename' => 'chatbot-analytics-' . $period . '-' . date( 'Y-m-d' ) . '.json',
				) );
			}

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Convert analytics data to CSV format
	 *
	 * @param array $data Analytics data.
	 * @return string CSV data.
	 * @since 1.0.0
	 */
	private function export_to_csv( $data ) {
		$output = fopen( 'php://temp', 'w' );

		// Write headers
		fputcsv( $output, array(
			'Metric',
			'Value',
			'Period',
			'Generated',
		) );

		// Write basic stats
		if ( isset( $data['stats'] ) ) {
			$stats = $data['stats'];
			$rows = array(
				array( 'Total Conversations', $stats['total_conversations'], $data['period'], current_time( 'mysql' ) ),
				array( 'Unique Sessions', $stats['unique_sessions'], $data['period'], current_time( 'mysql' ) ),
				array( 'Average Rating', round( $stats['avg_rating'], 2 ), $data['period'], current_time( 'mysql' ) ),
				array( 'Positive Ratings', $stats['positive_ratings'], $data['period'], current_time( 'mysql' ) ),
				array( 'Negative Ratings', $stats['negative_ratings'], $data['period'], current_time( 'mysql' ) ),
			);

			foreach ( $rows as $row ) {
				fputcsv( $output, $row );
			}
		}

		// Add daily trend data
		if ( isset( $data['daily_trend'] ) ) {
			fputcsv( $output, array() ); // Empty row
			fputcsv( $output, array( 'Date', 'Conversations', 'Sessions', 'Period' ) );
			
			foreach ( $data['daily_trend'] as $day ) {
				fputcsv( $output, array(
					$day['date'],
					$day['conversations'],
					$day['sessions'],
					$data['period'],
				) );
			}
		}

		rewind( $output );
		$csv_data = stream_get_contents( $output );
		fclose( $output );

		return $csv_data;
	}

	/**
	 * Get conversation length range
	 *
	 * @param int $length Message count.
	 * @return string Length range.
	 * @since 1.0.0
	 */
	private function get_length_range( $length ) {
		if ( $length <= 2 ) {
			return '1-2 messages';
		} elseif ( $length <= 5 ) {
			return '3-5 messages';
		} elseif ( $length <= 10 ) {
			return '6-10 messages';
		} else {
			return '11+ messages';
		}
	}

	/**
	 * Get user engagement metrics
	 *
	 * @return array Engagement metrics.
	 * @since 1.0.0
	 */
	public function get_engagement_metrics() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';

		// Return user rate (users who come back)
		$return_users = $wpdb->get_var(
			"SELECT COUNT(DISTINCT session_id) 
			FROM {$table_name} 
			WHERE session_id IN (
				SELECT session_id 
				FROM {$table_name} 
				GROUP BY session_id 
				HAVING COUNT(*) > 1
			)"
		);

		$total_users = $wpdb->get_var(
			"SELECT COUNT(DISTINCT session_id) FROM {$table_name}"
		);

		$return_rate = $total_users > 0 ? ( $return_users / $total_users ) * 100 : 0;

		// Bounce rate (single message conversations)
		$single_message_sessions = $wpdb->get_var(
			"SELECT COUNT(DISTINCT session_id) 
			FROM {$table_name} 
			WHERE session_id IN (
				SELECT session_id 
				FROM {$table_name} 
				GROUP BY session_id 
				HAVING COUNT(*) = 1
			)"
		);

		$bounce_rate = $total_users > 0 ? ( $single_message_sessions / $total_users ) * 100 : 0;

		return array(
			'return_rate' => round( $return_rate, 1 ),
			'bounce_rate' => round( $bounce_rate, 1 ),
			'total_users' => $total_users,
		);
	}

	/**
	 * Get popular time slots
	 *
	 * @return array Time slot data.
	 * @since 1.0.0
	 */
	public function get_time_analytics() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';

		// Day of week analysis
		$day_of_week = $wpdb->get_results(
			"SELECT 
				DAYNAME(created_at) as day_name,
				DAYOFWEEK(created_at) as day_number,
				COUNT(*) as conversations
			FROM {$table_name}
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY day_name, day_number
			ORDER BY day_number",
			ARRAY_A
		);

		// Hour of day analysis
		$hour_of_day = $wpdb->get_results(
			"SELECT 
				HOUR(created_at) as hour,
				COUNT(*) as conversations
			FROM {$table_name}
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY HOUR(created_at)
			ORDER BY hour",
			ARRAY_A
		);

		return array(
			'day_of_week' => $day_of_week,
			'hour_of_day' => $hour_of_day,
		);
	}
}