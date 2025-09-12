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
		$content = $this->decode_html_entities($content);
		if ( strlen( $content ) < 50 ) {
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

	public function decode_html_entities($text) {
        // First use PHP's built-in decoder
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Comprehensive entity map for additional coverage
        $entity_map = array(
            // CURRENCY SYMBOLS
            '&#8377;' => '₹',     // Indian Rupee
            '&#36;'   => '$',     // Dollar
            '&#8364;' => '€',     // Euro
            '&#163;'  => '£',     // British Pound
            '&#165;'  => '¥',     // Japanese Yen
            '&#162;'  => '¢',     // Cent
            '&#8359;' => '₧',     // Peseta
            '&#8361;' => '₩',     // Korean Won
            '&#8360;' => '₨',     // Rupee
            '&#8362;' => '₪',     // Shekel
            '&#8363;' => '₫',     // Vietnamese Dong
            '&#8364;' => '€',     // Euro
            '&#8365;' => '₭',     // Kip
            '&#8366;' => '₮',     // Tugrik
            '&#8367;' => '₯',     // Drachma
            '&#8368;' => '₰',     // German Penny
            '&#8369;' => '₱',     // Peso
            '&#8370;' => '₲',     // Guarani
            '&#8371;' => '₳',     // Austral
            '&#8372;' => '₴',     // Hryvnia
            '&#8373;' => '₵',     // Cedi
            '&#8374;' => '₶',     // Livre Tournois
            '&#8375;' => '₷',     // Spesmilo
            '&#8376;' => '₸',     // Tenge
            '&#8378;' => '₺',     // Turkish Lira
            '&#8379;' => '₻',     // Nordic Mark
            '&#8380;' => '₼',     // Manat
            '&#8381;' => '₽',     // Russian Ruble
            
            // QUOTATION MARKS & APOSTROPHES
            '&#8216;' => "'",     // Left single quotation mark  
            '&#8217;' => "'",     // Right single quotation mark
            '&#8218;' => '‚',     // Single low-9 quotation mark
            '&#8219;' => '‛',     // Single high-reversed-9 quotation mark
            '&#8220;' => '"',     // Left double quotation mark
            '&#8221;' => '"',     // Right double quotation mark
            '&#8222;' => '„',     // Double low-9 quotation mark
            '&#8223;' => '‟',     // Double high-reversed-9 quotation mark
            '&#171;'  => '«',     // Left-pointing double angle quotation mark
            '&#187;'  => '»',     // Right-pointing double angle quotation mark
            '&#8249;' => '‹',     // Single left-pointing angle quotation mark
            '&#8250;' => '›',     // Single right-pointing angle quotation mark
            
            // DASHES & HYPHENS
            '&#8208;' => '‐',     // Hyphen
            '&#8209;' => '‑',     // Non-breaking hyphen
            '&#8210;' => '‒',     // Figure dash
            '&#8211;' => '–',     // En dash
            '&#8212;' => '—',     // Em dash
            '&#8213;' => '―',     // Horizontal bar
            '&#45;'   => '-',     // Hyphen-minus
            
            // MATHEMATICAL SYMBOLS
            '&#215;'  => '×',     // Multiplication sign
            '&#247;'  => '÷',     // Division sign
            '&#177;'  => '±',     // Plus-minus sign
            '&#8722;' => '−',     // Minus sign
            '&#8804;' => '≤',     // Less-than or equal to
            '&#8805;' => '≥',     // Greater-than or equal to
            '&#8800;' => '≠',     // Not equal to
            '&#8776;' => '≈',     // Almost equal to
            '&#8734;' => '∞',     // Infinity
            '&#8730;' => '√',     // Square root
            '&#8747;' => '∫',     // Integral
            '&#8721;' => '∑',     // N-ary summation
            '&#8719;' => '∏',     // N-ary product
            '&#8706;' => '∂',     // Partial differential
            '&#8710;' => '∆',     // Increment
            '&#8711;' => '∇',     // Nabla
            '&#8712;' => '∈',     // Element of
            '&#8713;' => '∉',     // Not an element of
            '&#8715;' => '∋',     // Contains as member
            '&#8716;' => '∌',     // Does not contain as member
            '&#8745;' => '∩',     // Intersection
            '&#8746;' => '∪',     // Union
            '&#8834;' => '⊂',     // Subset of
            '&#8835;' => '⊃',     // Superset of
            '&#8836;' => '⊄',     // Not a subset of
            '&#8838;' => '⊆',     // Subset of or equal to
            '&#8839;' => '⊇',     // Superset of or equal to
            '&#8853;' => '⊕',     // Circled plus
            '&#8855;' => '⊗',     // Circled times
            '&#8869;' => '⊥',     // Up tack
            '&#8901;' => '⋅',     // Bullet operator
            
            // ARROWS
            '&#8592;' => '←',     // Leftwards arrow
            '&#8593;' => '↑',     // Upwards arrow
            '&#8594;' => '→',     // Rightwards arrow
            '&#8595;' => '↓',     // Downwards arrow
            '&#8596;' => '↔',     // Left right arrow
            '&#8597;' => '↕',     // Up down arrow
            '&#8598;' => '↖',     // North west arrow
            '&#8599;' => '↗',     // North east arrow
            '&#8600;' => '↘',     // South east arrow
            '&#8601;' => '↙',     // South west arrow
            '&#8629;' => '↵',     // Downwards arrow with corner leftwards
            '&#8656;' => '⇐',     // Leftwards double arrow
            '&#8657;' => '⇑',     // Upwards double arrow
            '&#8658;' => '⇒',     // Rightwards double arrow
            '&#8659;' => '⇓',     // Downwards double arrow
            '&#8660;' => '⇔',     // Left right double arrow
            
            // SYMBOLS & PUNCTUATION
            '&#8224;' => '†',     // Dagger
            '&#8225;' => '‡',     // Double dagger
            '&#8226;' => '•',     // Bullet
            '&#8230;' => '…',     // Horizontal ellipsis
            '&#8240;' => '‰',     // Per mille sign
            '&#8242;' => '′',     // Prime
            '&#8243;' => '″',     // Double prime
            '&#8244;' => '‴',     // Triple prime
            '&#8245;' => '‵',     // Reversed prime
            '&#8254;' => '‾',     // Overline
            '&#8260;' => '⁄',     // Fraction slash
            '&#8364;' => '€',     // Euro sign
            '&#8482;' => '™',     // Trade mark sign
            '&#8501;' => 'ℵ',     // Alef symbol
            '&#8476;' => 'ℜ',     // Black-letter capital R
            '&#8472;' => '℘',     // Weierstrass elliptic function
            '&#8465;' => 'ℑ',     // Black-letter capital I
            '&#8450;' => 'ℂ',     // Double-struck capital C
            '&#8461;' => 'ℍ',     // Double-struck capital H
            '&#8469;' => 'ℕ',     // Double-struck capital N
            '&#8473;' => 'ℙ',     // Double-struck capital P
            '&#8474;' => 'ℚ',     // Double-struck capital Q
            '&#8477;' => 'ℝ',     // Double-struck capital R
            '&#8484;' => 'ℤ',     // Double-struck capital Z
            
            // GREEK LETTERS (commonly used)
            '&#913;'  => 'Α',     // Alpha
            '&#914;'  => 'Β',     // Beta
            '&#915;'  => 'Γ',     // Gamma
            '&#916;'  => 'Δ',     // Delta
            '&#917;'  => 'Ε',     // Epsilon
            '&#918;'  => 'Ζ',     // Zeta
            '&#919;'  => 'Η',     // Eta
            '&#920;'  => 'Θ',     // Theta
            '&#921;'  => 'Ι',     // Iota
            '&#922;'  => 'Κ',     // Kappa
            '&#923;'  => 'Λ',     // Lambda
            '&#924;'  => 'Μ',     // Mu
            '&#925;'  => 'Ν',     // Nu
            '&#926;'  => 'Ξ',     // Xi
            '&#927;'  => 'Ο',     // Omicron
            '&#928;'  => 'Π',     // Pi
            '&#929;'  => 'Ρ',     // Rho
            '&#931;'  => 'Σ',     // Sigma
            '&#932;'  => 'Τ',     // Tau
            '&#933;'  => 'Υ',     // Upsilon
            '&#934;'  => 'Φ',     // Phi
            '&#935;'  => 'Χ',     // Chi
            '&#936;'  => 'Ψ',     // Psi
            '&#937;'  => 'Ω',     // Omega
            '&#945;'  => 'α',     // alpha
            '&#946;'  => 'β',     // beta
            '&#947;'  => 'γ',     // gamma
            '&#948;'  => 'δ',     // delta
            '&#949;'  => 'ε',     // epsilon
            '&#950;'  => 'ζ',     // zeta
            '&#951;'  => 'η',     // eta
            '&#952;'  => 'θ',     // theta
            '&#953;'  => 'ι',     // iota
            '&#954;'  => 'κ',     // kappa
            '&#955;'  => 'λ',     // lambda
            '&#956;'  => 'μ',     // mu
            '&#957;'  => 'ν',     // nu
            '&#958;'  => 'ξ',     // xi
            '&#959;'  => 'ο',     // omicron
            '&#960;'  => 'π',     // pi
            '&#961;'  => 'ρ',     // rho
            '&#962;'  => 'ς',     // final sigma
            '&#963;'  => 'σ',     // sigma
            '&#964;'  => 'τ',     // tau
            '&#965;'  => 'υ',     // upsilon
            '&#966;'  => 'φ',     // phi
            '&#967;'  => 'χ',     // chi
            '&#968;'  => 'ψ',     // psi
            '&#969;'  => 'ω',     // omega
            
            // ACCENTED CHARACTERS (common ones)
            '&#192;'  => 'À',     // A with grave
            '&#193;'  => 'Á',     // A with acute
            '&#194;'  => 'Â',     // A with circumflex
            '&#195;'  => 'Ã',     // A with tilde
            '&#196;'  => 'Ä',     // A with diaeresis
            '&#197;'  => 'Å',     // A with ring above
            '&#198;'  => 'Æ',     // AE ligature
            '&#199;'  => 'Ç',     // C with cedilla
            '&#200;'  => 'È',     // E with grave
            '&#201;'  => 'É',     // E with acute
            '&#202;'  => 'Ê',     // E with circumflex
            '&#203;'  => 'Ë',     // E with diaeresis
            '&#204;'  => 'Ì',     // I with grave
            '&#205;'  => 'Í',     // I with acute
            '&#206;'  => 'Î',     // I with circumflex
            '&#207;'  => 'Ï',     // I with diaeresis
            '&#209;'  => 'Ñ',     // N with tilde
            '&#210;'  => 'Ò',     // O with grave
            '&#211;'  => 'Ó',     // O with acute
            '&#212;'  => 'Ô',     // O with circumflex
            '&#213;'  => 'Õ',     // O with tilde
            '&#214;'  => 'Ö',     // O with diaeresis
            '&#216;'  => 'Ø',     // O with stroke
            '&#217;'  => 'Ù',     // U with grave
            '&#218;'  => 'Ú',     // U with acute
            '&#219;'  => 'Û',     // U with circumflex
            '&#220;'  => 'Ü',     // U with diaeresis
            '&#221;'  => 'Ý',     // Y with acute
            '&#224;'  => 'à',     // a with grave
            '&#225;'  => 'á',     // a with acute
            '&#226;'  => 'â',     // a with circumflex
            '&#227;'  => 'ã',     // a with tilde
            '&#228;'  => 'ä',     // a with diaeresis
            '&#229;'  => 'å',     // a with ring above
            '&#230;'  => 'æ',     // ae ligature
            '&#231;'  => 'ç',     // c with cedilla
            '&#232;'  => 'è',     // e with grave
            '&#233;'  => 'é',     // e with acute
            '&#234;'  => 'ê',     // e with circumflex
            '&#235;'  => 'ë',     // e with diaeresis
            '&#236;'  => 'ì',     // i with grave
            '&#237;'  => 'í',     // i with acute
            '&#238;'  => 'î',     // i with circumflex
            '&#239;'  => 'ï',     // i with diaeresis
            '&#241;'  => 'ñ',     // n with tilde
            '&#242;'  => 'ò',     // o with grave
            '&#243;'  => 'ó',     // o with acute
            '&#244;'  => 'ô',     // o with circumflex
            '&#245;'  => 'õ',     // o with tilde
            '&#246;'  => 'ö',     // o with diaeresis
            '&#248;'  => 'ø',     // o with stroke
            '&#249;'  => 'ù',     // u with grave
            '&#250;'  => 'ú',     // u with acute
            '&#251;'  => 'û',     // u with circumflex
            '&#252;'  => 'ü',     // u with diaeresis
            '&#253;'  => 'ý',     // y with acute
            '&#255;'  => 'ÿ',     // y with diaeresis
            
            // BASIC HTML ENTITIES
            '&#38;'   => '&',     // Ampersand
            '&#60;'   => '<',     // Less than
            '&#62;'   => '>',     // Greater than
            '&#34;'   => '"',     // Quotation mark
            '&#39;'   => "'",     // Apostrophe
            '&#160;'  => ' ',     // Non-breaking space
            '&#161;'  => '¡',     // Inverted exclamation mark
            '&#162;'  => '¢',     // Cent sign
            '&#163;'  => '£',     // Pound sign
            '&#164;'  => '¤',     // Currency sign
            '&#165;'  => '¥',     // Yen sign
            '&#166;'  => '¦',     // Broken bar
            '&#167;'  => '§',     // Section sign
            '&#168;'  => '¨',     // Diaeresis
            '&#169;'  => '©',     // Copyright sign
            '&#170;'  => 'ª',     // Feminine ordinal indicator
            '&#172;'  => '¬',     // Not sign
            '&#173;'  => '­',     // Soft hyphen
            '&#174;'  => '®',     // Registered sign
            '&#175;'  => '¯',     // Macron
            '&#176;'  => '°',     // Degree sign
            '&#178;'  => '²',     // Superscript two
            '&#179;'  => '³',     // Superscript three
            '&#180;'  => '´',     // Acute accent
            '&#181;'  => 'µ',     // Micro sign
            '&#182;'  => '¶',     // Pilcrow sign
            '&#183;'  => '·',     // Middle dot
            '&#184;'  => '¸',     // Cedilla
            '&#185;'  => '¹',     // Superscript one
            '&#186;'  => 'º',     // Masculine ordinal indicator
            '&#188;'  => '¼',     // Vulgar fraction one quarter
            '&#189;'  => '½',     // Vulgar fraction one half
            '&#190;'  => '¾',     // Vulgar fraction three quarters
            '&#191;'  => '¿',     // Inverted question mark
            
            // NAMED ENTITIES (common ones)
            '&nbsp;'  => ' ',     // Non-breaking space
            '&amp;'   => '&',     // Ampersand
            '&lt;'    => '<',     // Less than
            '&gt;'    => '>',     // Greater than
            '&quot;'  => '"',     // Quotation mark
            '&apos;'  => "'",     // Apostrophe
            '&copy;'  => '©',     // Copyright
            '&reg;'   => '®',     // Registered trademark
            '&trade;' => '™',     // Trademark
            '&euro;'  => '€',     // Euro
            '&pound;' => '£',     // Pound
            '&yen;'   => '¥',     // Yen
            '&cent;'  => '¢',     // Cent
            '&deg;'   => '°',     // Degree
            '&plusmn;'=> '±',     // Plus-minus
            '&times;' => '×',     // Multiplication
            '&divide;'=> '÷',     // Division
            '&frac12;'=> '½',     // One half
            '&frac14;'=> '¼',     // One quarter
            '&frac34;'=> '¾',     // Three quarters
            '&sup1;'  => '¹',     // Superscript 1
            '&sup2;'  => '²',     // Superscript 2
            '&sup3;'  => '³',     // Superscript 3
            '&micro;' => 'µ',     // Micro
            '&para;'  => '¶',     // Paragraph
            '&sect;'  => '§',     // Section
            '&middot;'=> '·',     // Middle dot
            '&laquo;' => '«',     // Left angle quote
            '&raquo;' => '»',     // Right angle quote
            '&ldquo;' => '"',     // Left double quote
            '&rdquo;' => '"',     // Right double quote
            '&lsquo;' => "'",     // Left single quote
            '&rsquo;' => "'",     // Right single quote
            '&ndash;' => '–',     // En dash
            '&mdash;' => '—',     // Em dash
            '&hellip;'=> '…',     // Horizontal ellipsis
            '&prime;' => '′',     // Prime
            '&Prime;' => '″',     // Double prime
            '&larr;'  => '←',     // Left arrow
            '&uarr;'  => '↑',     // Up arrow
            '&rarr;'  => '→',     // Right arrow
            '&darr;'  => '↓',     // Down arrow
            '&harr;'  => '↔',     // Left right arrow
            '&crarr;' => '↵',     // Carriage return arrow
            '&lArr;'  => '⇐',     // Left double arrow
            '&uArr;'  => '⇑',     // Up double arrow
            '&rArr;'  => '⇒',     // Right double arrow
            '&dArr;'  => '⇓',     // Down double arrow
            '&hArr;'  => '⇔',     // Left right double arrow
        );
        
        // Apply manual replacements for entities that might not be caught by html_entity_decode
        foreach ($entity_map as $entity => $replacement) {
            $decoded = str_replace($entity, $replacement, $decoded);
        }
        
        return trim($decoded);
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
        
        // CRITICAL: Apply WordPress content filters to expand shortcodes
        // This turns [arm_setup id="1"] into actual HTML content
        $content = apply_filters('the_content', $content);
        
        if ($this->is_mostly_shortcodes($content)) {
            // Try to render shortcodes manually
            $content = do_shortcode($post->post_content);
            
            // If still shortcodes, try rendering in context
            if ($this->is_mostly_shortcodes($content)) {
                // Proper way to set post context
                $original_post = $GLOBALS['post'] ?? null;
                $GLOBALS['post'] = $post;
                setup_postdata($post);
                
                $content = apply_filters('the_content', $post->post_content);
                
                // Restore original context
                if ($original_post) {
                    $GLOBALS['post'] = $original_post;
                    setup_postdata($original_post);
                }
                wp_reset_postdata();
            }
        }
        
        // Extract structured information
        $structured_info = $this->extract_structured_information($content, $post);
        
        // Clean HTML but preserve structure
        $content = $this->clean_content_intelligently($content);
        
        // If content is still empty or just shortcodes, get alternative content
        if (strlen(trim($content)) < 50 || $this->is_mostly_shortcodes($content)) {
            $content = $this->get_alternative_content($post);
        }
        
        // Build enhanced content
        $enhanced_content = $this->build_enhanced_content_structure($post, $content, $structured_info);
        
        return $enhanced_content;
    }

    /**
     * Check if content is mostly shortcodes - ADD this method
     */
    private function is_mostly_shortcodes($content) {
        // Count shortcodes vs regular content
        $shortcode_count = preg_match_all('/\[[^\]]+\]/', $content);
        $content_length = strlen(strip_tags($content));
        $text_only = preg_replace('/\[[^\]]+\]/', '', strip_tags($content));
        $meaningful_content = strlen(trim($text_only));
        
        // If less than 100 chars of meaningful content, it's mostly shortcodes
        return $meaningful_content < 100;
    }

    /**
     * Get alternative content when shortcodes don't expand - ADD this method
     */
    private function get_alternative_content($post) {
        $content_parts = array();
        
        // Get post excerpt
        if (!empty($post->post_excerpt)) {
            $content_parts[] = $post->post_excerpt;
        }
        
        // Get custom fields that might contain content
        $meta_keys_to_check = array(
            'description', 'summary', 'content', 'details', 'info',
            'pricing', 'features', 'benefits', 'services'
        );
        
        foreach ($meta_keys_to_check as $key) {
            $meta_value = get_post_meta($post->ID, $key, true);
            if (!empty($meta_value) && is_string($meta_value) && strlen($meta_value) > 20) {
                $content_parts[] = $meta_value;
            }
        }
        
        // Get ACF fields if available
        if (function_exists('get_fields')) {
            $acf_fields = get_fields($post->ID);
            if ($acf_fields) {
                foreach ($acf_fields as $field_name => $field_value) {
                    if (is_string($field_value) && strlen($field_value) > 20) {
                        $content_parts[] = $field_name . ': ' . $field_value;
                    }
                }
            }
        }
        
        // Try to extract content from the page by actually loading it
        if (empty($content_parts)) {
            $alternative_content = $this->scrape_page_content($post);
            if (!empty($alternative_content)) {
                $content_parts[] = $alternative_content;
            }
        }
        
        return implode("\n\n", $content_parts);
    }

    /**
     * Scrape actual page content - ADD this method
     */
    private function scrape_page_content($post) {
        $url = get_permalink($post->ID);
        
        // Only try this for published posts with URLs
        if (empty($url) || $post->post_status !== 'publish') {
            return '';
        }
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Extract content from common content areas
        $content_areas = array(
            '/<main[^>]*>(.*?)<\/main>/si',
            '/<article[^>]*>(.*?)<\/article>/si', 
            '/<div[^>]*class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*entry[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*id="content"[^>]*>(.*?)<\/div>/si'
        );
        
        foreach ($content_areas as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $content = $matches[1];
                
                // Clean the scraped content
                $content = $this->clean_scraped_content($content);
                
                if (strlen($content) > 100) {
                    return substr($content, 0, 1000);
                }
            }
        }
        
        return '';
    }

    /**
     * Clean scraped content - ADD this method
     */
    private function clean_scraped_content($content) {
        // Remove scripts, styles, and other non-content elements
        $content = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $content);
        $content = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $content);
        $content = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $content);
        $content = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $content);
        $content = preg_replace('/<aside[^>]*>.*?<\/aside>/si', '', $content);
        
        // Convert common HTML elements to text
        $content = str_replace(array('<br>', '<br/>', '<br />'), "\n", $content);
        $content = str_replace(array('<p>', '</p>'), array('', "\n"), $content);
        $content = str_replace(array('<li>', '</li>'), array('• ', "\n"), $content);
        
        // Remove all remaining HTML
        $content = wp_strip_all_tags($content);
        
        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\n\s*\n/', "\n", $content);
        
        return trim($content);
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