<?php
/**
 * Abstract AI Provider Base Class
 * Contains all common functionality shared between providers
 *
 * @package AI_Website_Chatbot
 * @subpackage AI_Providers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for AI providers
 * Implements common functionality to eliminate code duplication
 */
abstract class AI_Chatbot_Provider_Base implements AI_Chatbot_Provider_Interface {

    /**
     * API key
     *
     * @var string
     */
    protected $api_key;

    /**
     * API base URL
     *
     * @var string
     */
    protected $api_base;

    /**
     * Provider name
     *
     * @var string
     */
    protected $provider_name;

    /**
     * Constructor - loads API key
     */
    public function __construct() {
        $this->load_api_key();
    }

    // ==========================================
    // ABSTRACT METHODS - Must be implemented by child classes
    // ==========================================

    /**
     * Make API request - provider-specific implementation
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|WP_Error API response or error
     */
    abstract protected function make_api_request($endpoint, $data);

    /**
     * Build system message - provider-specific format
     *
     * @param string $context Website context
     * @return string System message
     */
    abstract protected function build_system_message($context);

    /**
     * Get cost per 1K tokens for a model
     *
     * @param string $model Model name
     * @return float Cost per 1K tokens
     */
    abstract protected function get_model_cost($model);

    // ==========================================
    // COMMON SESSION & ID MANAGEMENT
    // ==========================================

    /**
     * Get or generate session ID
     *
     * @return string Session ID
     */
    protected function get_or_generate_session_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['ai_chatbot_session_id'])) {
            $_SESSION['ai_chatbot_session_id'] = wp_generate_uuid4();
        }
        
        return $_SESSION['ai_chatbot_session_id'];
    }

    /**
     * Generate conversation ID
     *
     * @param string $session_id Session ID
     * @return string Conversation ID
     */
    protected function generate_conversation_id($session_id) {
        return wp_generate_uuid4();
    }

    // ==========================================
    // COMMON SETTINGS RETRIEVAL
    // ==========================================

    /**
     * Load API key from settings
     */
    protected function load_api_key() {
        $main_settings = get_option('ai_chatbot_settings', array());
        $provider_name = $this->get_name();
    
        if (!empty($main_settings['api_key']) && $main_settings['ai_provider'] === $provider_name) {
            // New structure: settings stored in main array
            $this->api_key = $main_settings['api_key'];
        } else {
            // Fallback to old structure: individual options
            $this->api_key = get_option('ai_chatbot_' . $provider_name . '_api_key', '');
        }
    }

    /**
     * Get model setting
     *
     * @return string Model name
     */
    protected function get_model() {
        $main_settings = get_option('ai_chatbot_settings', array());
        $fallback_option = 'ai_chatbot_' . $this->get_name() . '_model';
        return $main_settings['model'] ?? get_option($fallback_option, $this->get_default_model());
    }

    /**
     * Get max tokens setting
     *
     * @return int Max tokens
     */
    protected function get_max_tokens() {
        $main_settings = get_option('ai_chatbot_settings', array());
        $fallback_option = 'ai_chatbot_' . $this->get_name() . '_max_tokens';
        return intval($main_settings['max_tokens'] ?? get_option($fallback_option, 300));
    }

    /**
     * Get temperature setting
     *
     * @return float Temperature
     */
    protected function get_temperature() {
        $main_settings = get_option('ai_chatbot_settings', array());
        $fallback_option = 'ai_chatbot_' . $this->get_name() . '_temperature';
        return floatval($main_settings['temperature'] ?? get_option($fallback_option, 0.7));
    }

    // ==========================================
    // COMMON VALIDATION
    // ==========================================

    /**
     * Validate message
     *
     * @param string $message Message to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    protected function validate_message($message) {
        if (empty(trim($message))) {
            return new WP_Error('empty_message', __('Message cannot be empty.', 'ai-website-chatbot'));
        }

        $max_length = get_option('ai_chatbot_max_message_length', 1000);
        if (strlen($message) > $max_length) {
            return new WP_Error('message_too_long', sprintf(
                __('Message is too long. Maximum length is %d characters.', 'ai-website-chatbot'), 
                $max_length 
            ));
        }

        return true;
    }

    // ==========================================
    // COMMON TRAINING DATA OPERATIONS
    // ==========================================

    /**
     * Check training data for exact match
     *
     * @param string $message User message
     * @return string|WP_Error Training response or error if not found
     */
    protected function check_training_data($message) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        // Decode HTML entities in the user message
        $decoded_message = $this->decode_html_entities($message);
        
        // First check exact match with decoded message
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT answer FROM $table_name WHERE LOWER(TRIM(question)) = LOWER(TRIM(%s)) AND status = 'active' LIMIT 1",
            $decoded_message
        ));
        
        if ($result) {
            error_log('Found exact training match for decoded message: ' . $decoded_message);
            // Decode HTML entities in the response too
            return $this->decode_html_entities($result);
        }
        
        // Also try with original message (in case training data doesn't have entities)
        if ($decoded_message !== $message) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT answer FROM $table_name WHERE LOWER(TRIM(question)) = LOWER(TRIM(%s)) AND status = 'active' LIMIT 1",
                $message
            ));
            
            if ($result) {
                error_log('Found exact training match for original message: ' . $message);
                return $this->decode_html_entities($result);
            }
        }
        
        return new WP_Error('no_training_match', 'No exact training match found');
    }
    /**
     * Find similar training data
     *
     * @param string $message User message
     * @return array|WP_Error Similar training data or error
     */
    protected function find_similar_training($message, $similarity_threshold = 0.6) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_training_data';
        
        // Decode HTML entities in the user message
        $decoded_message = $this->decode_html_entities($message);
        
        $training_data = $wpdb->get_results(
            "SELECT question, answer, intent FROM $table_name WHERE status = 'active'",
            ARRAY_A
        );
        
        $best_match = null;
        $best_similarity = 0;
        
        foreach ($training_data as $training_item) {
            // Decode HTML entities in training question
            $decoded_question = $this->decode_html_entities($training_item['question']);
            
            // Calculate similarity with decoded text
            $similarity = $this->calculate_semantic_similarity($decoded_message, $decoded_question);
            
            if ($similarity > $best_similarity && $similarity >= $similarity_threshold) {
                $best_similarity = $similarity;
                $best_match = array(
                    'response' => $this->decode_html_entities($training_item['answer']),
                    'similarity' => $similarity,
                    'original_question' => $decoded_question
                );
            }
        }
        
        return $best_match ? $best_match : new WP_Error('no_similar_match', 'No similar training match found');
    }

    /**
	 * Calculate similarity between two strings
	 */
    private function calculate_semantic_similarity($str1, $str2) {
        // Extract keywords from both strings
        $keywords1 = $this->extract_keywords($str1);
        $keywords2 = $this->extract_keywords($str2);
        
        if (empty($keywords1) || empty($keywords2)) {
            // Fallback to simple Levenshtein if no keywords
            $distance = levenshtein(strtolower($str1), strtolower($str2));
            $max_length = max(strlen($str1), strlen($str2));
            return $max_length > 0 ? 1 - ($distance / $max_length) : 0;
        }
        
        // Calculate keyword overlap
        $common_keywords = array_intersect($keywords1, $keywords2);
        $keyword_score = count($common_keywords) / max(count($keywords1), count($keywords2));
        
        // Check for synonyms
        $synonym_score = $this->calculate_synonym_overlap($keywords1, $keywords2);
        
        // Combine scores
        return max($keyword_score, $synonym_score);
    }

    /**
     * Extract keywords from text - ADD this NEW method
     */
    private function extract_keywords($text) {
        // Convert to lowercase and remove punctuation
        $text = strtolower(preg_replace('/[^\w\s]/', ' ', $text));
        
        // Split into words
        $words = array_filter(explode(' ', $text));
        
        // Remove common stop words
        $stop_words = array('the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'but', 'in', 'with', 'to', 'for', 'of', 'as', 'by', 'that', 'this', 'it', 'from', 'are', 'was', 'will', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'can', 'could', 'would', 'should', 'what', 'where', 'when', 'how', 'why', 'who');
        
        $keywords = array_filter($words, function($word) use ($stop_words) {
            return strlen($word) > 2 && !in_array($word, $stop_words);
        });
        
        return array_values($keywords);
    }

    /**
     * Calculate synonym overlap - ADD this NEW method
     */
    private function calculate_synonym_overlap($keywords1, $keywords2) {
        $synonym_map = array(
            'price' => array('cost', 'pricing', 'fee', 'rate', 'charge', 'amount', 'money'),
            'cost' => array('price', 'pricing', 'fee', 'rate', 'charge', 'amount', 'money'),
            'buy' => array('purchase', 'order', 'get', 'acquire'),
            'help' => array('support', 'assistance', 'aid'),
            'contact' => array('reach', 'connect', 'touch', 'call'),
            'service' => array('offering', 'solution', 'product'),
            'company' => array('business', 'organization', 'firm'),
        );
        
        $synonym_matches = 0;
        $total_comparisons = 0;
        
        foreach ($keywords1 as $keyword1) {
            foreach ($keywords2 as $keyword2) {
                $total_comparisons++;
                
                // Direct match
                if ($keyword1 === $keyword2) {
                    $synonym_matches++;
                    continue;
                }
                
                // Check synonyms
                if (isset($synonym_map[$keyword1]) && in_array($keyword2, $synonym_map[$keyword1])) {
                    $synonym_matches++;
                } elseif (isset($synonym_map[$keyword2]) && in_array($keyword1, $synonym_map[$keyword2])) {
                    $synonym_matches++;
                }
            }
        }
        
        return $total_comparisons > 0 ? $synonym_matches / $total_comparisons : 0;
    }

    /**
     * Get relevant website content - ADD this NEW method
     */
    protected function get_relevant_website_content($message, $limit = 3) {
        global $wpdb;
        
        $content_table = $wpdb->prefix . 'ai_chatbot_content';
        $keywords = $this->extract_keywords($message);
        
        if (empty($keywords)) {
            return array();
        }
        
        // Build search conditions
        $search_conditions = array();
        $search_values = array();
        
        foreach ($keywords as $keyword) {
            $search_conditions[] = "(title LIKE %s OR content LIKE %s)";
            $search_values[] = '%' . $wpdb->esc_like($keyword) . '%';
            $search_values[] = '%' . $wpdb->esc_like($keyword) . '%';
        }
        
        $search_query = "SELECT title, content, url 
                         FROM $content_table 
                         WHERE " . implode(' OR ', $search_conditions) . "
                         ORDER BY updated_at DESC 
                         LIMIT %d";
        
        $search_values[] = $limit;
        
        $relevant_content = $wpdb->get_results($wpdb->prepare($search_query, ...$search_values), ARRAY_A);
        
        return $relevant_content;
    }

    /**
     * Build enhanced context for AI - ADD this NEW method
     */
    protected function build_enhanced_context($message, $additional_context = '') {
        $context_parts = array();
        
        // Add website basic info
        $context_parts[] = "WEBSITE: " . get_bloginfo('name');
        $context_parts[] = "URL: " . home_url();
        $context_parts[] = "DESCRIPTION: " . get_bloginfo('description');
        $context_parts[] = "";
        
        // Get relevant website content
        $relevant_content = $this->get_relevant_website_content($message, 2);
        
        if (!empty($relevant_content)) {
            $context_parts[] = "RELEVANT WEBSITE CONTENT:";
            foreach ($relevant_content as $content) {
                $context_parts[] = "- " . $content['title'];
                // Limit content to first 200 characters
                $short_content = substr(strip_tags($content['content']), 0, 200);
                if (strlen($content['content']) > 200) {
                    $short_content .= '...';
                }
                $context_parts[] = $short_content;
                $context_parts[] = "";
            }
        }
        
        // Add instructions
        $context_parts[] = "INSTRUCTIONS:";
        $context_parts[] = "- You are a helpful assistant for " . get_bloginfo('name');
        $context_parts[] = "- Use the website content above to provide specific, accurate answers";
        $context_parts[] = "- If asked about pricing/cost/price, refer to the website content for specific details";
        $context_parts[] = "- Be friendly and professional";
        $context_parts[] = "";
        
        if (!empty($additional_context)) {
            $context_parts[] = $additional_context;
        }
        
        return implode("\n", $context_parts);
    }

    /**
     * Adapt training response to current message
     *
     * @param string $training_response Training response
     * @param string $current_message Current message
     * @return string Adapted response
     */
    protected function adapt_training_response($training_response, $current_message) {
        // Simple adaptations for common scenarios
        $adaptations = array(
            'our product' => get_bloginfo('name') . "'s product",
            'our service' => get_bloginfo('name') . "'s service", 
            'our company' => get_bloginfo('name'),
            'we offer' => get_bloginfo('name') . ' offers',
            'contact us' => 'contact ' . get_bloginfo('name'),
        );
        
        $adapted_response = $training_response;
        foreach ($adaptations as $generic => $specific) {
            $adapted_response = str_ireplace($generic, $specific, $adapted_response);
        }
        
        return $adapted_response;
    }

    // ==========================================
    // COMMON CACHING OPERATIONS
    // ==========================================

    /**
     * Check cached response
     *
     * @param string $message User message
     * @return array Cache result
     */
    protected function check_cached_response($message) {
        $cache_key = 'ai_chatbot_response_' . md5(strtolower(trim($message)));
        $cached_response = get_transient($cache_key);
        
        if ($cached_response !== false) {
            return array(
                'cached' => true,
                'response' => $cached_response
            );
        }
        
        return array('cached' => false);
    }

    /**
     * Cache response
     *
     * @param string $message User message
     * @param string $response AI response
     */
    protected function cache_response($message, $response) {
        $cache_key = 'ai_chatbot_response_' . md5(strtolower(trim($message)));
        $cache_duration = 12 * HOUR_IN_SECONDS; // Cache for 12 hours
        
        set_transient($cache_key, $response, $cache_duration);
    }

    // ==========================================
    // COMMON DATABASE OPERATIONS
    // ==========================================

    /**
     * Get conversation history
     *
     * @param string $conversation_id Conversation ID
     * @param int $limit Number of messages to retrieve
     * @return array Conversation history
     */
    protected function get_chat_conversation_history($conversation_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT user_message, ai_response 
             FROM {$table_name} 
             WHERE conversation_id = %s 
             AND status = 'completed'
             AND ai_response IS NOT NULL
             AND ai_response != ''
             ORDER BY created_at DESC 
             LIMIT %d",
            $conversation_id, $limit
        ), ARRAY_A);
        
        return $history ?: array();
    }

    /**
     * Log conversation
     *
     * @param string $conversation_id Conversation ID
     * @param string $message User message
     * @param string $response AI response
     * @param float $response_time Response time
     * @param string $source Response source
     */
    protected function log_conversation($conversation_id, $message, $response, $response_time, $source) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $wpdb->insert(
            $table_name,
            array(
                'conversation_id' => $conversation_id,
                'user_message' => $message,
                'ai_response' => $response,
                'response_time' => $response_time,
                'source' => $source,
                'provider' => $this->get_name(),
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
        );
    }

    // ==========================================
    // COMMON USAGE TRACKING
    // ==========================================

    /**
     * Log API usage statistics
     *
     * @param array $response API response
     */
    protected function log_usage($response) {
        $provider_name = $this->get_name();
        $stats_option = 'ai_chatbot_' . $provider_name . '_usage_stats';
        
        $stats = get_option($stats_option, array(
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'last_request' => null,
        ));

        $stats['total_requests']++;
        $stats['last_request'] = current_time('mysql');

        // Extract tokens based on provider
        $tokens_used = $this->extract_tokens_from_response($response);
        if ($tokens_used > 0) {
            $stats['total_tokens'] += $tokens_used;

            // Calculate cost
            $model = $this->extract_model_from_response($response);
            $cost_per_1k = $this->get_model_cost($model);
            $stats['total_cost'] += ($tokens_used / 1000) * $cost_per_1k;
        }

        update_option($stats_option, $stats);
    }

    /**
     * Comprehensive HTML entity decoder
     */
    protected function decode_html_entities($text) {
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
     * Extract tokens from API response - provider-specific
     *
     * @param array $response API response
     * @return int Tokens used
     */
    protected function extract_tokens_from_response($response) {
        $provider_name = $this->get_name();
        
        switch ($provider_name) {
            case 'openai':
                return $response['usage']['total_tokens'] ?? 0;
            case 'claude':
                return $response['usage']['output_tokens'] ?? 0;
            case 'gemini':
                // Gemini doesn't provide token count, estimate
                if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $response['candidates'][0]['content']['parts'][0]['text'];
                    return ceil(strlen($text) / 4); // Rough estimate
                }
                return 0;
            default:
                return 0;
        }
    }

    /**
     * Extract model from API response
     *
     * @param array $response API response
     * @return string Model name
     */
    protected function extract_model_from_response($response) {
        return $response['model'] ?? $this->get_default_model();
    }

    // ==========================================
    // COMMON INTERFACE IMPLEMENTATIONS
    // ==========================================

    /**
     * Test API connection - common implementation
     *
     * @return bool|WP_Error True if connection successful, WP_Error if failed
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', sprintf(
                __('%s API key is not configured.', 'ai-website-chatbot'),
                $this->get_display_name()
            ));
        }

        // Test with a simple message
        $test_response = $this->generate_response('Hello', '', array('max_tokens' => 10));

        if (is_wp_error($test_response)) {
            return $test_response;
        }

        return true;
    }

    /**
     * Check if provider is configured - common implementation
     *
     * @return bool True if configured
     */
    public function is_configured() {
        $api_key = $this->api_key;
        error_log($this->get_display_name() . ' Provider - API Key check: ' . (empty($api_key) ? 'EMPTY' : 'Present (' . strlen($api_key) . ' chars)'));
        return !empty($api_key) && strlen($api_key) >= 20;
    }

    /**
     * Get usage statistics - common implementation
     *
     * @return array Usage statistics
     */
    public function get_usage_stats() {
        $stats_option = 'ai_chatbot_' . $this->get_name() . '_usage_stats';
        return get_option($stats_option, array(
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'last_request' => null,
        ));
    }

    /**
     * Validate configuration - common implementation
     *
     * @param array $config Configuration values
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_config($config) {
        if (empty($config['api_key'])) {
            return new WP_Error('missing_api_key', sprintf(
                __('%s API key is required.', 'ai-website-chatbot'),
                $this->get_display_name()
            ));
        }

        if (strlen($config['api_key']) < 20) {
            return new WP_Error('invalid_api_key', sprintf(
                __('Invalid %s API key format.', 'ai-website-chatbot'),
                $this->get_display_name()
            ));
        }

        return true;
    }

    /**
     * Get rate limits - default implementation
     *
     * @return array Rate limit information
     */
    public function get_rate_limits() {
        return array(
            'requests_per_minute' => 60,
            'tokens_per_minute' => 10000,
            'requests_per_day' => 1000,
            'current_usage' => 0
        );
    }

    // ==========================================
    // COMMON RESPONSE PROCESSING
    // ==========================================

    /**
     * Process training and cache checks before API call
     *
     * @param string $message User message
     * @param string $conversation_id Conversation ID
     * @return array|null Returns response array if found, null if should proceed to API
     */
    protected function process_pre_api_checks($message, $conversation_id) {
        // Check training data first (exact match)
        $training_response = $this->check_training_data($message);
        if (!is_wp_error($training_response) && !empty($training_response)) {
            error_log($this->get_display_name() . ' Provider: Found exact training match for: ' . $message);
            
            return array(
                'response' => $training_response,
                'tokens_used' => 0,
                'model' => 'training',
                'source' => 'training'
            );
        }

        // Check for partial training matches (similarity-based)
        $partial_match = $this->find_similar_training($message);
        if (!is_wp_error($partial_match) && !empty($partial_match['response'])) {
            error_log($this->get_display_name() . ' Provider: Found similar training match for: ' . $message . ' (similarity: ' . $partial_match['similarity'] . ')');
            
            // Use similar response with slight modification
            $modified_response = $this->adapt_training_response($partial_match['response'], $message);
                        
            return array(
                'response' => $modified_response,
                'tokens_used' => 0,
                'model' => 'training_similar',
                'source' => 'training'
            );
        }

        // Check cache for API responses
        $cached = $this->check_cached_response($message);
        if ($cached['cached']) {
            error_log($this->get_display_name() . ' Provider: Using cached response for: ' . $message);
            
            return array(
                'response' => $cached['response'],
                'tokens_used' => 0,
                'model' => 'cached',
                'source' => 'cache'
            );
        }

        // No matches found, proceed with API call
        return null;
    }
}