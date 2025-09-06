<?php
/**
 * Database operations for AI Chatbot
 *
 * @package AI_Website_Chatbot
 * @subpackage Includes
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database operations class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Database {

	/**
	 * Save conversation to database
	 *
	 * @param array $conversation_data Conversation data.
	 * @return int|false The number of rows inserted, or false on error.
	 * @since 1.0.0
	 */
	public function save_conversation( $conversation_data ) {
		global $wpdb;

		// Sanitize data
		$sanitized_data = array(
			'session_id'    => sanitize_text_field( $conversation_data['session_id'] ),
			'conversation_id' => sanitize_text_field( $conversation_data['conversation_id'] ?? '' ),
			'user_message'  => wp_kses_post( $conversation_data['user_message'] ),
			'ai_response'   => wp_kses_post( $conversation_data['ai_response'] ?? '' ),
			'user_name'     => sanitize_text_field( $conversation_data['user_name'] ?? '' ),
			'user_email'    => sanitize_email( $conversation_data['user_email'] ?? '' ),
			'user_ip'       => $this->sanitize_ip( $conversation_data['user_ip'] ?? '' ),
			'page_url'      => esc_url_raw( $conversation_data['page_url'] ?? '' ),
			'user_agent'    => sanitize_text_field( $conversation_data['user_agent'] ?? '' ),
			'status'        => sanitize_text_field( $conversation_data['status'] ?? 'completed' ),
			'provider'      => sanitize_text_field( $conversation_data['provider'] ?? '' ),
			'model'         => sanitize_text_field( $conversation_data['model'] ?? '' ),
			'response_time' => floatval( $conversation_data['response_time'] ?? 0 ),
			'tokens_used'   => intval( $conversation_data['tokens_used'] ?? 0 ),
			'created_at'    => current_time( 'mysql' ),
		);


		// Insert into database
		return $wpdb->insert(
			$wpdb->prefix . 'ai_chatbot_conversations',
			$sanitized_data,
			array(
				'%s', // session_id
				'%s', // conversation_id
				'%s', // user_message
				'%s', // ai_response
				'%s', // user_name
				'%s', // user_email
				'%s', // user_ip
				'%s', // page_url
				'%s', // user_agent
				'%s', // status
				'%s', // provider
				'%s', // model
				'%f', // response_time
				'%d', // tokens_used
				'%s', // created_at
			)
		);
	}

	/**
	 * Get conversations by session ID
	 *
	 * @param string $session_id Session ID.
	 * @param int    $limit Number of conversations to retrieve.
	 * @return array|null Array of conversations or null on failure.
	 * @since 1.0.0
	 */
	public function get_conversations_by_session( $session_id, $limit = 10 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE session_id = %s ORDER BY created_at DESC LIMIT %d",
				sanitize_text_field( $session_id ),
				intval( $limit )
			)
		);
	}

	/**
	 * Update conversation rating
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $rating Rating (1 for positive, -1 for negative).
	 * @return int|false The number of rows updated, or false on error.
	 * @since 1.0.0
	 */
	public function update_conversation_rating( $conversation_id, $rating ) {
		global $wpdb;

		// Validate rating
		if ( ! in_array( $rating, array( 1, -1, 0 ), true ) ) {
			return false;
		}

		return $wpdb->update(
			$wpdb->prefix . 'ai_chatbot_conversations',
			array( 'rating' => $rating ),
			array( 'id' => intval( $conversation_id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Save website content for training
	 *
	 * @param array $content_data Content data.
	 * @return int|false The number of rows inserted, or false on error.
	 * @since 1.0.0
	 */
	public function save_content( $content_data ) {
		global $wpdb;

		// Generate content hash for duplicate detection
		$content_hash = md5( $content_data['title'] . $content_data['content'] );

		// Check if content already exists
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ai_chatbot_content WHERE content_hash = %s",
				$content_hash
			)
		);

		$sanitized_data = array(
			'post_id'      => isset( $content_data['post_id'] ) ? intval( $content_data['post_id'] ) : null,
			'content_type' => sanitize_text_field( $content_data['content_type'] ),
			'title'        => sanitize_text_field( $content_data['title'] ),
			'content'      => wp_kses_post( $content_data['content'] ),
			'url'          => esc_url_raw( $content_data['url'] ?? '' ),
			'content_hash' => $content_hash,
		);

		if ( $existing ) {
			// Update existing content
			$sanitized_data['updated_at'] = current_time( 'mysql' );
			return $wpdb->update(
				$wpdb->prefix . 'ai_chatbot_content',
				$sanitized_data,
				array( 'id' => $existing ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new content
			$sanitized_data['created_at'] = current_time( 'mysql' );
			return $wpdb->insert(
				$wpdb->prefix . 'ai_chatbot_content',
				$sanitized_data,
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Get content for training
	 *
	 * @param string $status Embedding status filter.
	 * @param int    $limit Number of content items to retrieve.
	 * @return array|null Array of content items or null on failure.
	 * @since 1.0.0
	 */
	public function get_content_for_training( $status = 'pending', $limit = 50 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_chatbot_content';

		if ( $status === 'all' ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} ORDER BY updated_at DESC LIMIT %d",
					intval( $limit )
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE embedding_status = %s ORDER BY updated_at DESC LIMIT %d",
				sanitize_text_field( $status ),
				intval( $limit )
			)
		);
	}

	/**
	 * Update content embedding status
	 *
	 * @param int    $content_id Content ID.
	 * @param string $status New status.
	 * @return int|false The number of rows updated, or false on error.
	 * @since 1.0.0
	 */
	public function update_content_status( $content_id, $status ) {
		global $wpdb;

		$valid_statuses = array( 'pending', 'processing', 'completed', 'error' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		$update_data = array( 'embedding_status' => $status );
		
		if ( $status === 'completed' ) {
			$update_data['last_trained'] = current_time( 'mysql' );
		}

		return $wpdb->update(
			$wpdb->prefix . 'ai_chatbot_content',
			$update_data,
			array( 'id' => intval( $content_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Search relevant content based on user query
	 *
	 * @param string $query User query.
	 * @param int    $limit Number of results to return.
	 * @return array|null Array of relevant content or null on failure.
	 * @since 1.0.0
	 */
	public function search_relevant_content( $query, $limit = 5 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_chatbot_content';
		
		// Simple keyword matching (in production, this should use vector similarity)
		$search_terms = explode( ' ', strtolower( trim( $query ) ) );
		$search_terms = array_filter( $search_terms, function( $term ) {
			return strlen( $term ) > 2; // Only search terms longer than 2 characters
		});

		if ( empty( $search_terms ) ) {
			return null;
		}

		// Build LIKE conditions for each search term
		$like_conditions = array();
		$prepare_values = array();

		foreach ( $search_terms as $term ) {
			$like_conditions[] = '(title LIKE %s OR content LIKE %s)';
			$prepare_values[] = '%' . $wpdb->esc_like( $term ) . '%';
			$prepare_values[] = '%' . $wpdb->esc_like( $term ) . '%';
		}

		$where_clause = implode( ' OR ', $like_conditions );
		$prepare_values[] = intval( $limit );

		$sql = "SELECT id, title, content, url FROM {$table_name} 
				WHERE embedding_status = 'completed' 
				AND ({$where_clause}) 
				ORDER BY updated_at DESC 
				LIMIT %d";

		return $wpdb->get_results(
			$wpdb->prepare( $sql, $prepare_values )
		);
	}

	/**
	 * Clean up old conversations based on retention policy
	 *
	 * @return int|false Number of deleted rows or false on error.
	 * @since 1.0.0
	 */
	public function cleanup_old_conversations() {
		global $wpdb;

		$retention_days = get_option( 'ai_chatbot_data_retention_days', 30 );
		
		if ( $retention_days <= 0 ) {
			return 0; // Don't delete if retention is disabled
		}

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ai_chatbot_conversations 
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				intval( $retention_days )
			)
		);
	}

	/**
	 * Get conversation statistics
	 *
	 * @param string $period Time period (day, week, month).
	 * @return array|null Statistics array or null on failure.
	 * @since 1.0.0
	 */
	public function get_conversation_stats( $period = 'month' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';

		// Determine the date condition based on period
		switch ( $period ) {
			case 'day':
				$date_condition = 'DATE(created_at) = CURDATE()';
				break;
			case 'week':
				$date_condition = 'created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)';
				break;
			case 'month':
			default:
				$date_condition = 'created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)';
				break;
		}

		$stats = $wpdb->get_row(
			"SELECT 
				COUNT(*) as total_conversations,
				COUNT(DISTINCT session_id) as unique_sessions,
				AVG(CASE WHEN rating IS NOT NULL THEN rating END) as avg_rating,
				COUNT(CASE WHEN rating = 1 THEN 1 END) as positive_ratings,
				COUNT(CASE WHEN rating = -1 THEN 1 END) as negative_ratings
			FROM {$table_name} 
			WHERE {$date_condition}",
			ARRAY_A
		);

		return $stats;
	}

	/**
	 * Sanitize IP address for storage
	 *
	 * @param string $ip IP address.
	 * @return string Sanitized IP address.
	 * @since 1.0.0
	 */
	private function sanitize_ip( $ip ) {
		// Check if IP collection is enabled
		if ( ! get_option( 'ai_chatbot_collect_ip', false ) ) {
			return '';
		}

		// Validate and sanitize IP
		$sanitized_ip = filter_var( $ip, FILTER_VALIDATE_IP );
		
		if ( $sanitized_ip === false ) {
			return '';
		}

		// Anonymize IP for privacy (remove last octet for IPv4, last 80 bits for IPv6)
		if ( filter_var( $sanitized_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $sanitized_ip );
			$parts[3] = '0';
			return implode( '.', $parts );
		} elseif ( filter_var( $sanitized_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $sanitized_ip );
			// Keep first 48 bits (3 groups), zero out the rest
			for ( $i = 3; $i < count( $parts ); $i++ ) {
				$parts[$i] = '0';
			}
			return implode( ':', $parts );
		}

		return '';
	}

	/**
	 * Get content count by status
	 *
	 * @param string $status Content status filter.
	 * @return int Content count.
	 * @since 1.0.0
	 */
	public function get_content_count( $status = 'all' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ai_chatbot_content';

		if ( $status === 'all' ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE embedding_status = %s",
				sanitize_text_field( $status )
			)
		);
	}

	/**
	 * Get last insert ID
	 *
	 * @return int Last insert ID.
	 * @since 1.0.0
	 */
	public function get_last_insert_id() {
		global $wpdb;
		return $wpdb->insert_id;
	}
}