<?php
/**
 * Privacy and GDPR compliance for AI Chatbot
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
 * Privacy and GDPR compliance class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Privacy {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize privacy functionality
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Add privacy policy content
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		
		// Register cleanup cron job
		if ( ! wp_next_scheduled( 'ai_chatbot_privacy_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'ai_chatbot_privacy_cleanup' );
		}
		add_action( 'ai_chatbot_privacy_cleanup', array( $this, 'cleanup_expired_data' ) );
	}

	/**
	 * Register privacy data exporter
	 *
	 * @param array $exporters Existing exporters.
	 * @return array Modified exporters array.
	 * @since 1.0.0
	 */
	public function register_exporter( $exporters ) {
		$exporters['ai-chatbot-conversations'] = array(
			'exporter_friendly_name' => __( 'AI Chatbot Conversations', 'ai-website-chatbot' ),
			'callback'               => array( $this, 'export_user_conversations' ),
		);

		return $exporters;
	}

	/**
	 * Register privacy data eraser
	 *
	 * @param array $erasers Existing erasers.
	 * @return array Modified erasers array.
	 * @since 1.0.0
	 */
	public function register_eraser( $erasers ) {
		$erasers['ai-chatbot-conversations'] = array(
			'eraser_friendly_name' => __( 'AI Chatbot Conversations', 'ai-website-chatbot' ),
			'callback'             => array( $this, 'erase_user_conversations' ),
		);

		return $erasers;
	}

	/**
	 * Export user conversation data
	 *
	 * @param string $email_address User email address.
	 * @param int    $page Page number.
	 * @return array Export data.
	 * @since 1.0.0
	 */
	public function export_user_conversations( $email_address, $page = 1 ) {
		global $wpdb;

		$export_items = array();
		$per_page = 100;
		$offset = ( $page - 1 ) * $per_page;

		// Get user ID from email
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		// Get conversations associated with this user
		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} 
				WHERE user_ip IN (
					SELECT DISTINCT user_ip FROM {$table_name} 
					WHERE session_id IN (
						SELECT DISTINCT session_id 
						FROM {$wpdb->usermeta} 
						WHERE user_id = %d 
						AND meta_key = 'ai_chatbot_session_id'
					)
				)
				ORDER BY created_at ASC 
				LIMIT %d OFFSET %d",
				$user->ID,
				$per_page,
				$offset
			)
		);

		foreach ( $conversations as $conversation ) {
			$export_items[] = array(
				'group_id'    => 'ai-chatbot-conversations',
				'group_label' => __( 'AI Chatbot Conversations', 'ai-website-chatbot' ),
				'item_id'     => 'conversation-' . $conversation->id,
				'data'        => array(
					array(
						'name'  => __( 'Session ID', 'ai-website-chatbot' ),
						'value' => $conversation->session_id,
					),
					array(
						'name'  => __( 'User Message', 'ai-website-chatbot' ),
						'value' => $conversation->user_message,
					),
					array(
						'name'  => __( 'Bot Response', 'ai-website-chatbot' ),
						'value' => $conversation->bot_response,
					),
					array(
						'name'  => __( 'Page URL', 'ai-website-chatbot' ),
						'value' => $conversation->page_url,
					),
					array(
						'name'  => __( 'Timestamp', 'ai-website-chatbot' ),
						'value' => $conversation->created_at,
					),
					array(
						'name'  => __( 'Rating', 'ai-website-chatbot' ),
						'value' => $conversation->rating ? 
							( $conversation->rating > 0 ? __( 'Positive', 'ai-website-chatbot' ) : __( 'Negative', 'ai-website-chatbot' ) ) : 
							__( 'No rating', 'ai-website-chatbot' ),
					),
				),
			);
		}

		$done = count( $conversations ) < $per_page;

		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

	/**
	 * Erase user conversation data
	 *
	 * @param string $email_address User email address.
	 * @param int    $page Page number.
	 * @return array Erasure results.
	 * @since 1.0.0
	 */
	public function erase_user_conversations( $email_address, $page = 1 ) {
		global $wpdb;

		$per_page = 100;
		$offset = ( $page - 1 ) * $per_page;
		$items_removed = 0;
		$items_retained = 0;
		$messages = array();

		// Get user ID from email
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		// Get session IDs associated with this user
		$session_ids = get_user_meta( $user->ID, 'ai_chatbot_session_id' );
		
		if ( empty( $session_ids ) ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );
		
		// Get conversations to delete
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} 
				WHERE session_id IN ({$placeholders}) 
				LIMIT %d OFFSET %d",
				array_merge( $session_ids, array( $per_page, $offset ) )
			)
		);

		foreach ( $conversations as $conversation ) {
			$deleted = $wpdb->delete(
				$table_name,
				array( 'id' => $conversation->id ),
				array( '%d' )
			);

			if ( $deleted ) {
				$items_removed++;
			} else {
				$items_retained++;
				$messages[] = sprintf( 
					/* translators: %d: conversation ID */
					__( 'Failed to delete conversation %d', 'ai-website-chatbot' ), 
					$conversation->id 
				);
			}
		}

		// Remove user session metadata
		if ( $page === 1 ) {
			delete_user_meta( $user->ID, 'ai_chatbot_session_id' );
		}

		$done = count( $conversations ) < $per_page;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	/**
	 * Add privacy policy content
	 *
	 * @since 1.0.0
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = $this->get_privacy_policy_content();
		
		wp_add_privacy_policy_content(
			__( 'AI Website Chatbot', 'ai-website-chatbot' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	/**
	 * Get privacy policy content
	 *
	 * @return string Privacy policy content.
	 * @since 1.0.0
	 */
	private function get_privacy_policy_content() {
		$content = '';

		// Data Collection section
		$content .= '<h2>' . __( 'What data we collect', 'ai-website-chatbot' ) . '</h2>';
		$content .= '<p>' . __( 'When you use our AI chatbot, we may collect the following information:', 'ai-website-chatbot' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . __( 'Messages you send to the chatbot', 'ai-website-chatbot' ) . '</li>';
		$content .= '<li>' . __( 'Responses generated by the AI system', 'ai-website-chatbot' ) . '</li>';
		$content .= '<li>' . __( 'Timestamp of conversations', 'ai-website-chatbot' ) . '</li>';
		$content .= '<li>' . __( 'Page URL where the chatbot was used', 'ai-website-chatbot' ) . '</li>';

		if ( get_option( 'ai_chatbot_collect_ip', false ) ) {
			$content .= '<li>' . __( 'Anonymized IP address (last octet removed for privacy)', 'ai-website-chatbot' ) . '</li>';
		}

		if ( get_option( 'ai_chatbot_collect_user_agent', false ) ) {
			$content .= '<li>' . __( 'Browser information (User Agent)', 'ai-website-chatbot' ) . '</li>';
		}

		$content .= '</ul>';

		// Purpose section
		$content .= '<h2>' . __( 'Why we collect this data', 'ai-website-chatbot' ) . '</h2>';
		$content .= '<p>' . __( 'We collect this information to:', 'ai-website-chatbot' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . __( 'Provide AI-powered assistance and support', 'ai-website-chatbot' ) . '</li>';
		$content .= '<li>' . __( 'Improve the chatbot\'s responses over time', 'ai-website-chatbot' ) . '</li>';
		$content .= '<li>' . __( 'Analyze usage patterns to enhance user experience', 'ai-website-chatbot' ) . '</li>';
		$content .= '<li>' . __( 'Prevent abuse and maintain system security', 'ai-website-chatbot' ) . '</li>';
		$content .= '</ul>';

		// Storage and retention
		$retention_days = get_option( 'ai_chatbot_data_retention_days', 30 );
		$content .= '<h2>' . __( 'How long we keep your data', 'ai-website-chatbot' ) . '</h2>';
		
		if ( $retention_days > 0 ) {
			$content .= '<p>' . sprintf(
				/* translators: %d: number of days */
				_n(
					'Conversation data is automatically deleted after %d day.',
					'Conversation data is automatically deleted after %d days.',
					$retention_days,
					'ai-website-chatbot'
				),
				$retention_days
			) . '</p>';
		} else {
			$content .= '<p>' . __( 'Conversation data is stored indefinitely unless you request deletion.', 'ai-website-chatbot' ) . '</p>';
		}

		// Third-party sharing
		$ai_provider = get_option( 'ai_chatbot_ai_provider', 'openai' );
		$content .= '<h2>' . __( 'Third-party services', 'ai-website-chatbot' ) . '</h2>';
		$content .= '<p>' . __( 'To provide AI responses, your messages are sent to our AI service provider:', 'ai-website-chatbot' ) . '</p>';
		
		switch ( $ai_provider ) {
			case 'openai':
				$content .= '<p>' . __( 'OpenAI (ChatGPT) - Please review OpenAI\'s privacy policy at https://openai.com/privacy/', 'ai-website-chatbot' ) . '</p>';
				break;
			case 'claude':
				$content .= '<p>' . __( 'Anthropic (Claude) - Please review Anthropic\'s privacy policy at https://www.anthropic.com/privacy', 'ai-website-chatbot' ) . '</p>';
				break;
			case 'gemini':
				$content .= '<p>' . __( 'Google (Gemini) - Please review Google\'s privacy policy at https://policies.google.com/privacy', 'ai-website-chatbot' ) . '</p>';
				break;
		}

		// User rights
		$content .= '<h2>' . __( 'Your rights', 'ai-website-chatbot' ) . '</h2>';
		$content .= '<p>' . __( 'You have the right to:', 'ai-website-chatbot' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . __( 'Request a copy of your conversation data', 'ai-website-chatbot' ) . '</li>';
		$content .= '<li>' . __( 'Request deletion of your conversation data', 'ai-website-chatbot' ) . '</li>';
		$content .= '<li>' . __( 'Opt out of data collection by not using the chatbot', 'ai-website-chatbot' ) . '</li>';
		$content .= '</ul>';

		// Contact information
		$content .= '<h2>' . __( 'Contact us', 'ai-website-chatbot' ) . '</h2>';
		$content .= '<p>' . sprintf(
			/* translators: %s: admin email */
			__( 'If you have any questions about how we handle your chatbot data, please contact us at %s', 'ai-website-chatbot' ),
			get_option( 'admin_email' )
		) . '</p>';

		return $content;
	}

	/**
	 * Clean up expired data based on retention policy
	 *
	 * @since 1.0.0
	 */
	public function cleanup_expired_data() {
		$retention_days = get_option( 'ai_chatbot_data_retention_days', 30 );
		
		if ( $retention_days <= 0 ) {
			return; // No cleanup if retention is disabled
		}

		global $wpdb;

		// Clean up conversations
		$conversations_deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ai_chatbot_conversations 
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);

		// Clean up orphaned content training data
		$content_deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ai_chatbot_content 
				WHERE last_trained IS NOT NULL 
				AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)
				AND post_id NOT IN (SELECT ID FROM {$wpdb->posts})",
				$retention_days * 2 // Keep training data longer
			)
		);

		// Log cleanup activity
		$this->log_cleanup_activity( $conversations_deleted, $content_deleted );
	}

	/**
	 * Log data cleanup activity
	 *
	 * @param int $conversations_deleted Number of conversations deleted.
	 * @param int $content_deleted Number of content items deleted.
	 * @since 1.0.0
	 */
	private function log_cleanup_activity( $conversations_deleted, $content_deleted ) {
		$log_entry = array(
			'timestamp'            => current_time( 'mysql' ),
			'conversations_deleted' => $conversations_deleted,
			'content_deleted'      => $content_deleted,
		);

		$cleanup_log = get_option( 'ai_chatbot_cleanup_log', array() );
		$cleanup_log[] = $log_entry;

		// Keep only last 30 cleanup entries
		if ( count( $cleanup_log ) > 30 ) {
			$cleanup_log = array_slice( $cleanup_log, -30 );
		}

		update_option( 'ai_chatbot_cleanup_log', $cleanup_log );
	}

	/**
	 * Get privacy compliance status
	 *
	 * @return array Privacy compliance information.
	 * @since 1.0.0
	 */
	public function get_compliance_status() {
		$status = array(
			'data_retention_enabled' => get_option( 'ai_chatbot_data_retention_days', 30 ) > 0,
			'ip_collection_disabled' => ! get_option( 'ai_chatbot_collect_ip', false ),
			'user_agent_collection_disabled' => ! get_option( 'ai_chatbot_collect_user_agent', false ),
			'privacy_policy_added' => $this->is_privacy_policy_content_added(),
			'exporters_registered' => true, // Always true if class is loaded
			'erasers_registered' => true,   // Always true if class is loaded
			'cleanup_scheduled' => wp_next_scheduled( 'ai_chatbot_privacy_cleanup' ) !== false,
		);

		$status['overall_compliance'] = ! in_array( false, $status, true );
		
		return $status;
	}

	/**
	 * Check if privacy policy content has been added
	 *
	 * @return bool True if privacy policy content exists.
	 * @since 1.0.0
	 */
	private function is_privacy_policy_content_added() {
		$privacy_policy_page_id = get_option( 'wp_page_for_privacy_policy' );
		
		if ( ! $privacy_policy_page_id ) {
			return false;
		}

		$page = get_post( $privacy_policy_page_id );
		if ( ! $page ) {
			return false;
		}

		// Check if our content is in the privacy policy
		return strpos( $page->post_content, 'AI Website Chatbot' ) !== false;
	}

	/**
	 * Generate privacy summary for admin
	 *
	 * @return array Privacy summary.
	 * @since 1.0.0
	 */
	public function get_privacy_summary() {
		global $wpdb;

		// Get data counts
		$total_conversations = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ai_chatbot_conversations"
		);

		$total_users_with_data = $wpdb->get_var(
			"SELECT COUNT(DISTINCT session_id) FROM {$wpdb->prefix}ai_chatbot_conversations"
		);

		$retention_days = get_option( 'ai_chatbot_data_retention_days', 30 );
		$oldest_conversation = $wpdb->get_var(
			"SELECT created_at FROM {$wpdb->prefix}ai_chatbot_conversations ORDER BY created_at ASC LIMIT 1"
		);

		$cleanup_log = get_option( 'ai_chatbot_cleanup_log', array() );
		$last_cleanup = ! empty( $cleanup_log ) ? end( $cleanup_log ) : null;

		return array(
			'total_conversations' => intval( $total_conversations ),
			'unique_sessions' => intval( $total_users_with_data ),
			'retention_days' => intval( $retention_days ),
			'oldest_data' => $oldest_conversation,
			'last_cleanup' => $last_cleanup,
			'compliance_status' => $this->get_compliance_status(),
			'data_types_collected' => $this->get_collected_data_types(),
		);
	}

	/**
	 * Get list of data types being collected
	 *
	 * @return array List of collected data types.
	 * @since 1.0.0
	 */
	private function get_collected_data_types() {
		$data_types = array(
			'user_messages' => true, // Always collected
			'bot_responses' => true, // Always collected
			'timestamps' => true,    // Always collected
			'page_urls' => true,     // Always collected
		);

		if ( get_option( 'ai_chatbot_collect_ip', false ) ) {
			$data_types['ip_addresses'] = true;
		}

		if ( get_option( 'ai_chatbot_collect_user_agent', false ) ) {
			$data_types['user_agents'] = true;
		}

		if ( get_option( 'ai_chatbot_enable_rating', true ) ) {
			$data_types['ratings'] = true;
		}

		return array_keys( array_filter( $data_types ) );
	}

	/**
	 * Generate consent form HTML
	 *
	 * @return string HTML for consent form.
	 * @since 1.0.0
	 */
	public function get_consent_form_html() {
		$privacy_policy_url = get_option( 'ai_chatbot_privacy_policy_url' );
		$site_privacy_policy = get_privacy_policy_url();
		
		$privacy_url = ! empty( $privacy_policy_url ) ? $privacy_policy_url : $site_privacy_policy;

		ob_start();
		?>
		<div class="ai-chatbot-consent" style="padding: 15px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
			<label style="display: flex; align-items: flex-start; gap: 8px;">
				<input type="checkbox" id="ai-chatbot-consent" required style="margin-top: 2px;">
				<span>
					<?php
					if ( $privacy_url ) {
						printf(
							/* translators: %s: privacy policy URL */
							esc_html__( 'I agree to the collection and processing of my messages as described in the %s.', 'ai-website-chatbot' ),
							sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $privacy_url ), esc_html__( 'privacy policy', 'ai-website-chatbot' ) )
						);
					} else {
						esc_html_e( 'I agree to the collection and processing of my messages for providing AI assistance.', 'ai-website-chatbot' );
					}
					?>
				</span>
			</label>
			<div style="margin-top: 8px; font-size: 11px; opacity: 0.8;">
				<?php esc_html_e( 'Your conversations may be processed by third-party AI services to generate responses.', 'ai-website-chatbot' ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if consent is required
	 *
	 * @return bool True if consent form should be shown.
	 * @since 1.0.0
	 */
	public function is_consent_required() {
		// Show consent form if:
		// 1. Data retention is enabled, OR
		// 2. IP collection is enabled, OR
		// 3. User agent collection is enabled, OR
		// 4. Analytics/tracking is enabled
		
		return get_option( 'ai_chatbot_data_retention_days', 30 ) > 0 ||
			   get_option( 'ai_chatbot_collect_ip', false ) ||
			   get_option( 'ai_chatbot_collect_user_agent', false ) ||
			   get_option( 'ai_chatbot_enable_analytics', true );
	}

	/**
	 * Handle data export request
	 *
	 * @param string $session_id Session ID to export data for.
	 * @return array|WP_Error Export data or error.
	 * @since 1.0.0
	 */
	public function export_session_data( $session_id ) {
		global $wpdb;

		if ( empty( $session_id ) ) {
			return new WP_Error( 'invalid_session', __( 'Invalid session ID provided.', 'ai-website-chatbot' ) );
		}

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_message, bot_response, page_url, created_at, rating 
				FROM {$table_name} 
				WHERE session_id = %s 
				ORDER BY created_at ASC",
				$session_id
			)
		);

		if ( empty( $conversations ) ) {
			return new WP_Error( 'no_data', __( 'No conversation data found for this session.', 'ai-website-chatbot' ) );
		}

		$export_data = array(
			'session_id' => $session_id,
			'export_date' => current_time( 'mysql' ),
			'total_conversations' => count( $conversations ),
			'conversations' => array(),
		);

		foreach ( $conversations as $conversation ) {
			$export_data['conversations'][] = array(
				'timestamp' => $conversation->created_at,
				'user_message' => $conversation->user_message,
				'bot_response' => $conversation->bot_response,
				'page_url' => $conversation->page_url,
				'rating' => $conversation->rating,
			);
		}

		return $export_data;
	}

	/**
	 * Handle data deletion request
	 *
	 * @param string $session_id Session ID to delete data for.
	 * @return bool|WP_Error True if successful, WP_Error if failed.
	 * @since 1.0.0
	 */
	public function delete_session_data( $session_id ) {
		global $wpdb;

		if ( empty( $session_id ) ) {
			return new WP_Error( 'invalid_session', __( 'Invalid session ID provided.', 'ai-website-chatbot' ) );
		}

		$table_name = $wpdb->prefix . 'ai_chatbot_conversations';
		$deleted = $wpdb->delete(
			$table_name,
			array( 'session_id' => $session_id ),
			array( '%s' )
		);

		if ( $deleted === false ) {
			return new WP_Error( 'deletion_failed', __( 'Failed to delete conversation data.', 'ai-website-chatbot' ) );
		}

		// Log the deletion
		$this->log_data_deletion( $session_id, $deleted );

		return true;
	}

	/**
	 * Log data deletion activity
	 *
	 * @param string $session_id Session ID that was deleted.
	 * @param int    $records_deleted Number of records deleted.
	 * @since 1.0.0
	 */
	private function log_data_deletion( $session_id, $records_deleted ) {
		$deletion_log = get_option( 'ai_chatbot_deletion_log', array() );
		
		$deletion_log[] = array(
			'timestamp' => current_time( 'mysql' ),
			'session_id' => $session_id,
			'records_deleted' => $records_deleted,
			'requested_by' => get_current_user_id() ?: 'anonymous',
			'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
		);

		// Keep only last 100 deletion logs
		if ( count( $deletion_log ) > 100 ) {
			$deletion_log = array_slice( $deletion_log, -100 );
		}

		update_option( 'ai_chatbot_deletion_log', $deletion_log );
	}

	/**
	 * Get data retention policy text
	 *
	 * @return string Data retention policy.
	 * @since 1.0.0
	 */
	public function get_retention_policy_text() {
		$retention_days = get_option( 'ai_chatbot_data_retention_days', 30 );
		
		if ( $retention_days <= 0 ) {
			return __( 'Conversation data is stored indefinitely unless you request deletion.', 'ai-website-chatbot' );
		}

		return sprintf(
			/* translators: %d: number of days */
			_n(
				'Conversation data is automatically deleted after %d day.',
				'Conversation data is automatically deleted after %d days.',
				$retention_days,
				'ai-website-chatbot'
			),
			$retention_days
		);
	}

	/**
	 * Check if we need to show cookie/privacy notice
	 *
	 * @return bool True if notice should be shown.
	 * @since 1.0.0
	 */
	public function should_show_privacy_notice() {
		// Show notice if we collect any personal data
		return get_option( 'ai_chatbot_collect_ip', false ) ||
			   get_option( 'ai_chatbot_collect_user_agent', false ) ||
			   get_option( 'ai_chatbot_data_retention_days', 30 ) > 0;
	}
}