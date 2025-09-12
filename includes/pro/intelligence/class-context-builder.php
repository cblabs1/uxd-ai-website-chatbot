<?php
/**
 * AI Chatbot Context Builder
 * 
 * File: includes/pro/intelligence/class-context-builder.php
 * 
 * Builds enhanced context for AI responses using multiple data sources
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Context Builder Class - Enhances context for better AI responses
 */
class AI_Chatbot_Context_Builder {
    
    /**
     * Context cache duration
     */
    private $cache_duration = 3600; // 1 hour
    
    /**
     * Maximum context length
     */
    private $max_context_length = 4000;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_duration = get_option('ai_chatbot_features_cache_duration', 3600);
    }
    
    /**
     * Build enhanced context from multiple sources
     * 
     * @param string $message User message
     * @param string $base_context Basic context
     * @return string Enhanced context
     */
    public function build_enhanced_context($message, $base_context = '') {
        // Start with base context
        $context_parts = array($base_context);
        
        // Add website context
        $context_parts[] = $this->build_website_context();
        
        // Add semantic context from content
        $semantic_context = $this->build_semantic_content_context($message);
        if (!empty($semantic_context)) {
            $context_parts[] = $semantic_context;
        }
        
        // Add user journey context
        $journey_context = $this->build_user_journey_context($message);
        if (!empty($journey_context)) {
            $context_parts[] = $journey_context;
        }
        
        // Add business context
        $business_context = $this->build_business_context();
        if (!empty($business_context)) {
            $context_parts[] = $business_context;
        }
        
        // Add temporal context
        $temporal_context = $this->build_temporal_context();
        if (!empty($temporal_context)) {
            $context_parts[] = $temporal_context;
        }
        
        // Combine and optimize context
        $enhanced_context = implode("\n\n", array_filter($context_parts));
        
        // Truncate if too long
        if (strlen($enhanced_context) > $this->max_context_length) {
            $enhanced_context = $this->intelligently_truncate_context($enhanced_context);
        }
        
        return $enhanced_context;
    }
    
    /**
     * Build website context
     * 
     * @return string Website context
     */
    private function build_website_context() {
        $context_parts = array();
        
        $context_parts[] = "WEBSITE INFORMATION:";
        $context_parts[] = "Name: " . get_bloginfo('name');
        $context_parts[] = "URL: " . home_url();
        $context_parts[] = "Description: " . get_bloginfo('description');
        
        // Add admin email for contact info
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $context_parts[] = "Contact: " . $admin_email;
        }
        
        // Add site language
        $context_parts[] = "Language: " . get_bloginfo('language');
        
        // Add WordPress version info
        $context_parts[] = "Platform: WordPress " . get_bloginfo('version');
        
        return implode("\n", $context_parts);
    }
    
    /**
     * Build semantic content context based on message
     * 
     * @param string $message User message
     * @return string Semantic context
     */
    private function build_semantic_content_context($message) {
        // Get relevant content based on keywords
        $relevant_content = $this->get_relevant_content_by_keywords($message, 3);
        
        if (empty($relevant_content)) {
            return '';
        }
        
        $context_parts = array();
        $context_parts[] = "RELEVANT WEBSITE CONTENT:";
        
        foreach ($relevant_content as $content) {
            $context_parts[] = "- Title: " . $content['title'];
            
            // Get content excerpt
            $excerpt = $this->create_smart_excerpt($content['content'], $message, 150);
            $context_parts[] = "  Content: " . $excerpt;
            
            if (!empty($content['url'])) {
                $context_parts[] = "  URL: " . $content['url'];
            }
            
            $context_parts[] = ""; // Empty line between items
        }
        
        return implode("\n", $context_parts);
    }
    
    /**
     * Build user journey context
     * 
     * @param string $message Current message
     * @return string Journey context
     */
    private function build_user_journey_context($message) {
        $context_parts = array();
        
        // Detect journey stage based on message patterns
        $journey_stage = $this->detect_journey_stage($message);
        
        if ($journey_stage) {
            $context_parts[] = "USER JOURNEY:";
            $context_parts[] = "Current Stage: " . $journey_stage['stage'];
            $context_parts[] = "Intent: " . $journey_stage['intent'];
            
            // Add stage-specific context
            $stage_context = $this->get_stage_specific_context($journey_stage['stage']);
            if ($stage_context) {
                $context_parts[] = "Stage Context: " . $stage_context;
            }
        }
        
        return implode("\n", $context_parts);
    }
    
    /**
     * Build business context
     * 
     * @return string Business context
     */
    private function build_business_context() {
        $business_options = array(
            'ai_chatbot_business_hours' => 'Business Hours',
            'ai_chatbot_contact_phone' => 'Phone',
            'ai_chatbot_contact_email' => 'Email',
            'ai_chatbot_location_info' => 'Location',
            'ai_chatbot_industry_keywords' => 'Industry',
            'ai_chatbot_current_promotions' => 'Current Promotions'
        );
        
        $context_parts = array();
        $has_business_info = false;
        
        foreach ($business_options as $option => $label) {
            $value = get_option($option, '');
            if (!empty($value)) {
                if (!$has_business_info) {
                    $context_parts[] = "BUSINESS INFORMATION:";
                    $has_business_info = true;
                }
                $context_parts[] = $label . ": " . $value;
            }
        }
        
        return implode("\n", $context_parts);
    }
    
    /**
     * Build temporal context
     * 
     * @return string Temporal context
     */
    private function build_temporal_context() {
        $context_parts = array();
        $context_parts[] = "CURRENT CONTEXT:";
        $context_parts[] = "Date: " . current_time('Y-m-d');
        $context_parts[] = "Time: " . current_time('H:i T');
        $context_parts[] = "Day: " . current_time('l');
        
        // Add seasonal context
        $month = current_time('n');
        $seasons = array(
            'Winter' => array(12, 1, 2),
            'Spring' => array(3, 4, 5),
            'Summer' => array(6, 7, 8),
            'Fall' => array(9, 10, 11)
        );
        
        foreach ($seasons as $season => $months) {
            if (in_array($month, $months)) {
                $context_parts[] = "Season: " . $season;
                break;
            }
        }
        
        return implode("\n", $context_parts);
    }
    
    /**
     * Get relevant content by keywords
     * 
     * @param string $message User message
     * @param int $limit Number of results
     * @return array Relevant content
     */
    private function get_relevant_content_by_keywords($message, $limit = 3) {
        global $wpdb;
        
        $content_table = $wpdb->prefix . 'ai_chatbot_content';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$content_table'") != $content_table) {
            return array();
        }
        
        // Extract keywords from message
        $keywords = $this->extract_meaningful_keywords($message);
        
        if (empty($keywords)) {
            return array();
        }
        
        // Build search query
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
        
        $results = $wpdb->get_results(
            $wpdb->prepare($search_query, ...$search_values),
            ARRAY_A
        );
        
        return $results ? $results : array();
    }
    
    /**
     * Extract meaningful keywords from message
     * 
     * @param string $message User message
     * @return array Keywords
     */
    private function extract_meaningful_keywords($message) {
        // Convert to lowercase and remove punctuation
        $clean_message = preg_replace('/[^\w\s]/', '', strtolower($message));
        
        // Split into words
        $words = array_filter(explode(' ', $clean_message));
        
        // Remove common stop words
        $stop_words = array(
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have',
            'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'can', 'what', 'how', 'when', 'where', 'why', 'who', 'i', 'you', 'we',
            'they', 'it', 'this', 'that', 'these', 'those'
        );
        
        $keywords = array_filter($words, function($word) use ($stop_words) {
            return strlen($word) > 2 && !in_array($word, $stop_words);
        });
        
        return array_unique($keywords);
    }
    
    /**
     * Create smart excerpt based on message relevance
     * 
     * @param string $content Full content
     * @param string $message User message
     * @param int $length Excerpt length
     * @return string Smart excerpt
     */
    private function create_smart_excerpt($content, $message, $length = 150) {
        $keywords = $this->extract_meaningful_keywords($message);
        
        if (empty($keywords)) {
            return substr(strip_tags($content), 0, $length) . '...';
        }
        
        // Find the best position to start excerpt based on keyword density
        $content_words = explode(' ', strip_tags($content));
        $best_position = 0;
        $best_score = 0;
        
        for ($i = 0; $i < count($content_words) - 20; $i++) {
            $excerpt_words = array_slice($content_words, $i, 30);
            $excerpt_text = implode(' ', $excerpt_words);
            
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count(strtolower($excerpt_text), $keyword);
            }
            
            if ($score > $best_score) {
                $best_score = $score;
                $best_position = $i;
            }
        }
        
        // Extract excerpt from best position
        $excerpt_words = array_slice($content_words, $best_position, 25);
        $excerpt = implode(' ', $excerpt_words);
        
        if (strlen($excerpt) > $length) {
            $excerpt = substr($excerpt, 0, $length) . '...';
        }
        
        return $excerpt;
    }
    
    /**
     * Detect user journey stage
     * 
     * @param string $message User message
     * @return array|null Journey stage info
     */
    private function detect_journey_stage($message) {
        $message_lower = strtolower($message);
        
        $stages = array(
            'awareness' => array(
                'keywords' => array('what is', 'tell me about', 'explain', 'learn', 'information'),
                'intent' => 'Information seeking'
            ),
            'consideration' => array(
                'keywords' => array('compare', 'difference', 'vs', 'better', 'options', 'features'),
                'intent' => 'Comparison and evaluation'
            ),
            'decision' => array(
                'keywords' => array('price', 'cost', 'buy', 'purchase', 'order', 'sign up'),
                'intent' => 'Ready to purchase'
            ),
            'support' => array(
                'keywords' => array('help', 'problem', 'issue', 'error', 'not working', 'fix'),
                'intent' => 'Needs assistance'
            ),
            'retention' => array(
                'keywords' => array('upgrade', 'more features', 'additional', 'expand', 'cancel'),
                'intent' => 'Account management'
            )
        );
        
        foreach ($stages as $stage => $data) {
            foreach ($data['keywords'] as $keyword) {
                if (strpos($message_lower, $keyword) !== false) {
                    return array(
                        'stage' => $stage,
                        'intent' => $data['intent']
                    );
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get stage-specific context
     * 
     * @param string $stage Journey stage
     * @return string|null Stage context
     */
    private function get_stage_specific_context($stage) {
        $stage_contexts = array(
            'awareness' => 'Focus on educational content and building understanding',
            'consideration' => 'Provide comparisons, benefits, and detailed information',
            'decision' => 'Offer clear next steps, pricing, and purchase assistance',
            'support' => 'Provide helpful troubleshooting and problem resolution',
            'retention' => 'Focus on account features and upgrade opportunities'
        );
        
        return isset($stage_contexts[$stage]) ? $stage_contexts[$stage] : null;
    }
    
    /**
     * Intelligently truncate context while preserving important information
     * 
     * @param string $context Full context
     * @return string Truncated context
     */
    private function intelligently_truncate_context($context) {
        $sections = explode("\n\n", $context);
        
        // Priority order for sections
        $priority_sections = array();
        $other_sections = array();
        
        foreach ($sections as $section) {
            if (strpos($section, 'WEBSITE INFORMATION:') !== false ||
                strpos($section, 'RELEVANT WEBSITE CONTENT:') !== false) {
                $priority_sections[] = $section;
            } else {
                $other_sections[] = $section;
            }
        }
        
        // Start with priority sections
        $truncated = implode("\n\n", $priority_sections);
        
        // Add other sections if space allows
        foreach ($other_sections as $section) {
            $potential = $truncated . "\n\n" . $section;
            if (strlen($potential) <= $this->max_context_length) {
                $truncated = $potential;
            } else {
                break;
            }
        }
        
        return $truncated;
    }
}