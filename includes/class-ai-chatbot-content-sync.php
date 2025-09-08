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
	 * Train from synced content data
	 */
	public function train_from_synced_content($limit = 100) {
		global $wpdb;
		
		$content_table = $wpdb->prefix . 'ai_chatbot_content';
		$training_table = $wpdb->prefix . 'ai_chatbot_training_data';
		
		// Get synced content
		$content = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$content_table} ORDER BY updated_at DESC LIMIT %d",
			$limit
		));
		
		if (empty($content)) {
			return new WP_Error('no_content', __('No synced content found. Please sync content first.', 'ai-website-chatbot'));
		}
		
		$trained = 0;
		
		foreach ($content as $item) {
			// Create single training entry per content
			$question = $item->title;
			$answer = $item->content . "\n\nSource: " . $item->url;
			
			// Check if already exists
			$exists = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$training_table} WHERE source = 'website_content' AND source_id = %d",
				$item->id
			));
			
			if ($exists) {
				// Update existing
				$wpdb->update($training_table, [
					'question' => $question,
					'answer' => $answer,
					'intent' => 'website-information',
					'updated_at' => current_time('mysql')
				], ['id' => $exists]);
			} else {
				// Insert new
				$wpdb->insert($training_table, [
					'question' => $question,
					'answer' => $answer,
					'source' => 'website_content',
					'source_id' => $item->id,
					'status' => 'active',
					'created_at' => current_time('mysql'),
					'updated_at' => current_time('mysql')
				]);
			}
			$trained++;
		}
		
		return ['trained' => $trained, 'total' => count($content)];
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
	private function extract_post_content($post) {
        // Get post content
        $content = $post->post_content;
        
        // Apply content filters (shortcodes, etc.)
        $content = apply_filters('the_content', $content);
        
        // Extract structured information
        $structured_info = $this->extract_structured_information($content, $post);
        
        // Clean HTML but preserve structure
        $content = $this->clean_content_intelligently($content);
        
        // Build enhanced content
        $enhanced_content = $this->build_enhanced_content_structure($post, $content, $structured_info);
        
        return $enhanced_content;
    }

	/**
     * Extract structured information from content - ADD this NEW method
     */
    private function extract_structured_information($content, $post) {
        $info = array();
        
        // Extract pricing information
        $info['pricing'] = $this->extract_pricing_info($content);
        
        // Extract contact information
        $info['contact'] = $this->extract_contact_info($content);
        
        // Extract features/benefits
        $info['features'] = $this->extract_features($content);
        
        return $info;
    }

    /**
     * Extract pricing information - ADD this NEW method
     */
    private function extract_pricing_info($content) {
        $pricing_patterns = array(
            '/\$[\d,]+\.?\d*/i',  // Dollar amounts
            '/price[:\s]*\$?[\d,]+/i',
            '/cost[:\s]*\$?[\d,]+/i',
            '/starting\s+at[:\s]*\$?[\d,]+/i',
            '/from[:\s]*\$?[\d,]+/i',
        );
        
        $pricing_info = array();
        foreach ($pricing_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                $pricing_info = array_merge($pricing_info, $matches[0]);
            }
        }
        
        return array_unique($pricing_info);
    }

    /**
     * Extract contact information - ADD this NEW method
     */
    private function extract_contact_info($content) {
        $contact_info = array();
        
        // Email addresses
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $emails)) {
            $contact_info['emails'] = $emails[0];
        }
        
        // Phone numbers
        if (preg_match_all('/(?:\+?1[-.\s]?)?\(?[0-9]{3}\)?[-.\s]?[0-9]{3}[-.\s]?[0-9]{4}/', $content, $phones)) {
            $contact_info['phones'] = $phones[0];
        }
        
        return $contact_info;
    }

    /**
     * Extract features from content - ADD this NEW method
     */
    private function extract_features($content) {
        $features = array();
        
        // Look for bullet points and lists
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/s', $content, $list_items)) {
            foreach ($list_items[1] as $item) {
                $clean_item = strip_tags($item);
                if (strlen($clean_item) > 10 && strlen($clean_item) < 200) {
                    $features[] = trim($clean_item);
                }
            }
        }
        
        return array_slice(array_unique($features), 0, 5);
    }

    /**
     * Clean content intelligently - ADD this NEW method
     */
    private function clean_content_intelligently($content) {
        // Remove script and style tags
        $content = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $content);
        
        // Convert HTML elements to readable text
        $content = str_replace(array('<br>', '<br/>', '<br />'), "\n", $content);
        $content = str_replace(array('<p>', '</p>'), array('', "\n\n"), $content);
        
        // Strip remaining HTML
        $content = wp_strip_all_tags($content);
        
        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        return trim($content);
    }

    /**
     * Build enhanced content structure - ADD this NEW method
     */
    private function build_enhanced_content_structure($post, $content, $structured_info) {
        $enhanced = array();
        
        // Add title and type
        $enhanced[] = "TITLE: " . $post->post_title;
        $enhanced[] = "TYPE: " . ucfirst($post->post_type);
        $enhanced[] = "";
        
        // Add main content
        $enhanced[] = "CONTENT:";
        $enhanced[] = substr($content, 0, 1000) . (strlen($content) > 1000 ? '...' : '');
        $enhanced[] = "";
        
        // Add pricing if found
        if (!empty($structured_info['pricing'])) {
            $enhanced[] = "PRICING:";
            foreach (array_slice($structured_info['pricing'], 0, 3) as $price) {
                $enhanced[] = "- " . $price;
            }
            $enhanced[] = "";
        }
        
        // Add contact info if found
        if (!empty($structured_info['contact'])) {
            $enhanced[] = "CONTACT:";
            if (isset($structured_info['contact']['emails'])) {
                $enhanced[] = "Email: " . implode(', ', $structured_info['contact']['emails']);
            }
            if (isset($structured_info['contact']['phones'])) {
                $enhanced[] = "Phone: " . implode(', ', $structured_info['contact']['phones']);
            }
            $enhanced[] = "";
        }
        
        // Add features if found
        if (!empty($structured_info['features'])) {
            $enhanced[] = "FEATURES:";
            foreach ($structured_info['features'] as $feature) {
                $enhanced[] = "- " . $feature;
            }
            $enhanced[] = "";
        }
        
        return implode("\n", $enhanced);
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