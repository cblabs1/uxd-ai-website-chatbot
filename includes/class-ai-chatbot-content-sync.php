<?php
/**
 * Content synchronization for AI training
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
 * Content synchronization class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Content_Sync {

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
		// Auto-sync when posts are saved
		add_action( 'save_post', array( $this, 'sync_single_post' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'delete_post_content' ) );
		
		// Scheduled full sync
		add_action( 'ai_chatbot_weekly_sync', array( $this, 'sync_website_content' ) );
	}

	/**
	 * Sync all website content
	 *
	 * @param int $limit Maximum number of posts to sync.
	 * @return array|WP_Error Sync results or error.
	 * @since 1.0.0
	 */
	public function sync_website_content( $limit = 100 ) {
		$allowed_post_types = get_option( 'ai_chatbot_allowed_post_types', array( 'post', 'page' ) );
		
		if ( empty( $allowed_post_types ) ) {
			return new WP_Error( 'no_post_types', __( 'No post types selected for training.', 'ai-website-chatbot' ) );
		}

		$posts = get_posts( array(
			'post_type' => $allowed_post_types,
			'post_status' => 'publish',
			'posts_per_page' => $limit,
			'orderby' => 'modified',
			'order' => 'DESC',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => '_ai_chatbot_synced',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => '_ai_chatbot_synced',
					'value' => get_the_modified_date( 'c' ),
					'compare' => '<',
				),
			),
		) );

		$synced = 0;
		$errors = 0;
		$skipped = 0;

		foreach ( $posts as $post ) {
			$result = $this->sync_single_post( $post->ID, $post );
			
			if ( is_wp_error( $result ) ) {
				$errors++;
				continue;
			}
			
			if ( $result === false ) {
				$skipped++;
				continue;
			}
			
			$synced++;
			
			// Update sync timestamp
			update_post_meta( $post->ID, '_ai_chatbot_synced', current_time( 'c' ) );
		}

		// Update sync statistics
		update_option( 'ai_chatbot_content_sync_stats', array(
			'last_sync' => current_time( 'mysql' ),
			'total_synced' => $synced,
			'total_errors' => $errors,
			'total_skipped' => $skipped,
		) );

		return array(
			'synced' => $synced,
			'errors' => $errors,
			'skipped' => $skipped,
			'total_processed' => count( $posts ),
		);
	}

	/**
	 * Sync single post content
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return bool|WP_Error True if synced, false if skipped, WP_Error if failed.
	 * @since 1.0.0
	 */
	public function sync_single_post( $post_id, $post = null ) {
		if ( ! $post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post || $post->post_status !== 'publish' ) {
			return false;
		}

		// Check if post type is allowed
		$allowed_post_types = get_option( 'ai_chatbot_allowed_post_types', array( 'post', 'page' ) );
		if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
			return false;
		}

		// Skip if content is too short
		$content = $this->extract_post_content( $post );
		if ( strlen( $content ) < 100 ) {
			return false;
		}

		// Prepare content data
		$content_data = array(
			'post_id' => $post_id,
			'content_type' => $post->post_type,
			'title' => $post->post_title,
			'content' => $content,
			'url' => get_permalink( $post_id ),
		);

		// Save to database
		$saved = $this->database->save_content( $content_data );

		if ( ! $saved ) {
			return new WP_Error( 'save_failed', __( 'Failed to save content to database.', 'ai-website-chatbot' ) );
		}

		return true;
	}

	/**
	 * Delete post content from training data
	 *
	 * @param int $post_id Post ID.
	 * @since 1.0.0
	 */
	public function delete_post_content( $post_id ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . 'ai_chatbot_content',
			array( 'post_id' => $post_id ),
			array( '%d' )
		);

		delete_post_meta( $post_id, '_ai_chatbot_synced' );
	}

	/**
	 * Extract clean content from post
	 *
	 * @param WP_Post $post Post object.
	 * @return string Clean content.
	 * @since 1.0.0
	 */
	private function extract_post_content( $post ) {
		// Get post content
		$content = $post->post_content;
		
		// Apply content filters (shortcodes, etc.)
		$content = apply_filters( 'the_content', $content );
		
		// Remove HTML tags
		$content = wp_strip_all_tags( $content );
		
		// Clean up whitespace
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );
		
		// Limit content length
		$max_length = get_option( 'ai_chatbot_max_content_length', 2000 );
		if ( strlen( $content ) > $max_length ) {
			$content = substr( $content, 0, $max_length );
			
			// Try to break at word boundary
			$last_space = strrpos( $content, ' ' );
			if ( $last_space > $max_length * 0.8 ) {
				$content = substr( $content, 0, $last_space );
			}
			
			$content .= '...';
		}
		
		// Add excerpt if available and content is short
		if ( strlen( $content ) < 500 && ! empty( $post->post_excerpt ) ) {
			$excerpt = wp_strip_all_tags( $post->post_excerpt );
			$content = $excerpt . ' ' . $content;
		}

		return $content;
	}

	/**
	 * Sync custom post types
	 *
	 * @param array $post_types Post types to sync.
	 * @return array Sync results.
	 * @since 1.0.0
	 */
	public function sync_custom_post_types( $post_types ) {
		$results = array();

		foreach ( $post_types as $post_type ) {
			$posts = get_posts( array(
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => 50,
			) );

			$synced = 0;
			foreach ( $posts as $post ) {
				if ( $this->sync_single_post( $post->ID, $post ) === true ) {
					$synced++;
				}
			}

			$results[ $post_type ] = $synced;
		}

		return $results;
	}

	/**
	 * Get content sync statistics
	 *
	 * @return array Sync statistics.
	 * @since 1.0.0
	 */
	public function get_sync_stats() {
		return get_option( 'ai_chatbot_content_sync_stats', array(
			'last_sync' => null,
			'total_synced' => 0,
			'total_errors' => 0,
			'total_skipped' => 0,
		) );
	}

	/**
	 * Clear all synced content
	 *
	 * @return bool True if cleared successfully.
	 * @since 1.0.0
	 */
	public function clear_all_content() {
		global $wpdb;

		$deleted = $wpdb->query( "DELETE FROM {$wpdb->prefix}ai_chatbot_content" );
		
		// Clear sync timestamps
		delete_metadata( 'post', 0, '_ai_chatbot_synced', '', true );
		
		// Reset sync stats
		delete_option( 'ai_chatbot_content_sync_stats' );

		return $deleted !== false;
	}

	/**
	 * Sync taxonomy terms
	 *
	 * @param array $taxonomies Taxonomies to sync.
	 * @return array Sync results.
	 * @since 1.0.0
	 */
	public function sync_taxonomy_terms( $taxonomies = array( 'category', 'post_tag' ) ) {
		$synced = 0;

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
			) );

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$content_data = array(
					'post_id' => null,
					'content_type' => $taxonomy,
					'title' => $term->name,
					'content' => $term->description ?: $term->name,
					'url' => get_term_link( $term ),
				);

				if ( $this->database->save_content( $content_data ) ) {
					$synced++;
				}
			}
		}

		return array( 'synced' => $synced );
	}

	/**
	 * Sync WooCommerce products (if available)
	 *
	 * @return array Sync results.
	 * @since 1.0.0
	 */
	public function sync_woocommerce_products() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'woo_not_found', __( 'WooCommerce is not installed.', 'ai-website-chatbot' ) );
		}

		$products = wc_get_products( array(
			'status' => 'publish',
			'limit' => 100,
		) );

		$synced = 0;
		foreach ( $products as $product ) {
			$content = $product->get_name() . ' ' . 
					   $product->get_short_description() . ' ' . 
					   $product->get_description();

			$content_data = array(
				'post_id' => $product->get_id(),
				'content_type' => 'product',
				'title' => $product->get_name(),
				'content' => wp_strip_all_tags( $content ),
				'url' => $product->get_permalink(),
			);

			if ( $this->database->save_content( $content_data ) ) {
				$synced++;
			}
		}

		return array( 'synced' => $synced );
	}
}