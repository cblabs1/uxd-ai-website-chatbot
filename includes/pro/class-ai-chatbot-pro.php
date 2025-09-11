<?php
/**
 * AI Chatbot Pro AJAX Handler
 * 
 * File: includes/pro/class-ai-chatbot-pro-ajax.php
 * 
 * Handles all Pro-specific AJAX requests and enhanced response processing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pro AJAX Handler Class
 * 
 * Manages all Pro-specific AJAX functionality including enhanced message processing,
 * semantic search, embedding generation, and analytics
 */
class AI_Chatbot_Pro_Ajax {
    
    /**
     * Response start time for performance tracking
     */
    private $response_start_time;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->response_start_time = microtime(true);
        $this->init_hooks();
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_hooks() {
        // Enhanced message processing (replaces basic handler when Pro is active)
        add_action('wp_ajax_ai_chatbot_message_pro', array($this, 'handle_pro_chat_message'));
        add_action('wp_ajax_nopriv_ai_chatbot_message_pro', array($this, 'handle_pro_chat_message'));
        
        // Pro-specific AJAX endpoints
        add_action('wp_ajax_ai_chatbot_test_semantic', array($this, 'handle_test_semantic_search'));
        add_action('wp_ajax_ai_chatbot_generate_embeddings', array($this, 'handle_generate_embeddings'));
        add_action('wp_ajax_ai_chatbot_conversation_feedback', array($this, 'handle_conversation_feedback'));
        add_action('wp_ajax_ai_chatbot_get_suggestions', array($this, 'handle_get_suggestions'));
        add_action('wp_ajax_ai_chatbot_analyze_intent', array($this, 'handle_analyze_intent'));
        add_action('wp_ajax_ai_chatbot_track_event', array($this, 'handle_track_event'));
        
        // Admin-only Pro endpoints
        add_action('wp_ajax_ai_chatbot_embedding_status', array($this, 'handle_embedding_status'));
        add_action('wp_ajax_ai_chatbot_pro_analytics', array($this, 'handle_pro_analytics'));
        add_action('wp_ajax_ai_chatbot_clear_embedding_cache', array($this, 'handle_clear_embedding_cache'));
        
        // Filter to replace basic message handler when Pro is active
        add_filter('ai_chatbot_ajax_action', array($this, 'replace_basic_handler'), 10, 2);
    }
    
    /**
     * Replace basic message handler with Pro version
     */
    public function replace_basic_handler($action, $request_data) {
        if ($action === 'ai_chatbot_message' && ai_chatbot_has_feature('intelligence_engine')) {
            return 'ai_chatbot_message_pro';
        }
        return $action;
    }
    
    /**
     * Enhanced Pro message handler
     */
    public function handle_pro_chat_message() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'ai-website-chatbot'),
                'code' => 'NONCE_FAILED'
            ));
            return;
        }
        
        // Get and sanitize input
        $message = sanitize_textarea_field($_POST['message']);
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $context_data = $_POST['context'] ?? array();
        
        if (empty($message)) {
            wp_send_json_error(array(
                'message' => __('Please enter a message.', 'ai-website-chatbot'),
                'code' => 'EMPTY_MESSAGE'
            ));
            return;
        }
        
        // Rate limiting check
        if (!$this->check_rate_limits()) {
            wp_send_json_error(array(
                'message' => __('Too many requests. Please wait a moment.', 'ai-website-chatbot'),
                'code' => 'RATE_LIMITED'
            ));
            return;
        }
        
        try {
            // Process message with Pro intelligence
            $response = $this->process_message_with_intelligence($message, $session_id, $conversation_id, $context_data);
            
            if (is_wp_error($response)) {
                wp_send_json_error(array(
                    'message' => $response->get_error_message(),
                    'code' => $response->get_error_code()
                ));
                return;
            }
            
            // Store conversation with Pro analytics
            $conversation_data = $this->store_conversation_with_analytics(
                $session_id,
                $conversation_id,
                $message,
                $response,
                $context_data
            );
            
            // Build enhanced response
            $ajax_response = $this->build_pro_response($response, $conversation_data);
            
            wp_send_json_success($ajax_response);
            
        } catch (Exception $e) {
            error_log('AI Chatbot Pro Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Something went wrong. Please try again.', 'ai-website-chatbot'),
                'code' => 'PROCESSING_ERROR'
            ));
        }
    }
    
    /**
     * Process message with Pro intelligence features
     */
    private function process_message_with_intelligence($message, $session_id, $conversation_id, $context_data) {
        // Initialize intelligence modules
        $context_builder = $this->get_context_builder();
        $intent_recognizer = $this->get_intent_recognizer();
        $embedding_engine = $this->get_embedding_engine();
        
        // Build enhanced context
        $enhanced_context = $context_builder->build_enhanced_context($message, $this->build_base_context($session_id, $context_data));
        
        // Analyze user intent
        $intent_analysis = $intent_recognizer->analyze_intent($message, $enhanced_context);
        
        // Try semantic search first
        $semantic_response = $this->try_semantic_response($message, $enhanced_context, $intent_analysis);
        
        if (!is_wp_error($semantic_response)) {
            return $semantic_response;
        }
        
        // Fallback to AI provider with enhanced context
        return $this->process_with_ai_provider($message, $enhanced_context, $intent_analysis);
    }
    
    /**
     * Try semantic response using embeddings
     */
    private function try_semantic_response($message, $context, $intent_analysis) {
        $embedding_engine = $this->get_embedding_engine();
        
        if (!$embedding_engine) {
            return new WP_Error('no_embedding_engine', 'Embedding engine not available');
        }
        
        // Try semantic training match first
        $training_match = $embedding_engine->semantic_training_match($message, 0.75);
        
        if (!is_wp_error($training_match)) {
            // Enhance training response with reasoning
            $reasoning_engine = $this->get_reasoning_engine();
            if ($reasoning_engine) {
                $enhanced_response = $reasoning_engine->enhance_response(
                    $training_match['response'], 
                    $message, 
                    $context
                );
                $training_match['response'] = $enhanced_response;
            }
            
            return array_merge($training_match, array(
                'source' => 'semantic_training',
                'intent' => $intent_analysis['primary_intent'] ?? null,
                'context_used' => !empty($context)
            ));
        }
        
        // Try semantic content search
        $content_results = $embedding_engine->semantic_content_search($message, 3, $context);
        
        if (!empty($content_results)) {
            // Build response from content with AI provider
            $content_context = $this->build_content_context($content_results, $context);
            return $this->process_with_ai_provider($message, $content_context, $intent_analysis, $content_results);
        }
        
        return new WP_Error('no_semantic_results', 'No semantic results found');
    }
    
    /**
     * Process with AI provider using enhanced context
     */
    private function process_with_ai_provider($message, $context, $intent_analysis, $semantic_results = null) {
        // Get AI provider
        $provider = $this->get_ai_provider();
        
        if (!$provider) {
            return new WP_Error('no_provider', 'AI provider not available');
        }
        
        // Generate response
        $ai_response = $provider->generate_response($message, $context);
        
        if (is_wp_error($ai_response)) {
            return $ai_response;
        }
        
        // Enhance with Pro reasoning
        $reasoning_engine = $this->get_reasoning_engine();
        if ($reasoning_engine) {
            $ai_response = $reasoning_engine->enhance_response($ai_response, $message, $context);
        }
        
        return array(
            'text' => $ai_response,
            'source' => 'ai_provider_enhanced',
            'provider' => get_option('ai_chatbot_provider', 'openai'),
            'intent' => $intent_analysis['primary_intent'] ?? null,
            'confidence' => $intent_analysis['confidence'] ?? null,
            'semantic_results' => $semantic_results,
            'context_used' => !empty($context),
            'reasoning_applied' => $reasoning_engine !== null
        );
    }
    
    /**
     * Store conversation with Pro analytics
     */
    private function store_conversation_with_analytics($session_id, $conversation_id, $message, $response, $context_data) {
        global $wpdb;
        
        // Generate conversation ID if not provided
        if (empty($conversation_id)) {
            $conversation_id = $session_id . '_' . time();
        }
        
        // Base conversation data
        $conversation_data = array(
            'session_id' => $session_id,
            'conversation_id' => $conversation_id,
            'message' => $message,
            'response' => $response['text'],
            'intent' => $response['intent'] ?? null,
            'confidence_score' => $response['confidence'] ?? null,
            'provider' => $response['provider'] ?? null,
            'response_time_ms' => $this->get_response_time(),
            'tokens_used' => $response['tokens_used'] ?? null,
            'cost' => $response['cost'] ?? null,
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => current_time('mysql')
        );
        
        // Store in conversations table
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        $wpdb->insert($table_name, $conversation_data);
        $conversation_data['id'] = $wpdb->insert_id;
        
        // Store Pro insights
        $this->store_conversation_insights($conversation_data, $response, $context_data);
        
        // Track semantic analytics
        if (!empty($response['semantic_results'])) {
            $this->track_semantic_analytics($message, $response['semantic_results']);
        }
        
        return $conversation_data;
    }
    
    /**
     * Store conversation insights (Pro analytics)
     */
    private function store_conversation_insights($conversation_data, $response, $context_data) {
        global $wpdb;
        
        $insights_data = array(
            'conversation_id' => $conversation_data['id'],
            'primary_intent' => $response['intent'],
            'intent_confidence' => $response['confidence'],
            'journey_stage' => $this->determine_journey_stage($context_data),
            'engagement_level' => $this->calculate_engagement_level($context_data),
            'lead_score' => $this->calculate_lead_score($response['intent'], $context_data),
            'insights_data' => wp_json_encode(array(
                'source' => $response['source'],
                'semantic_results_count' => count($response['semantic_results'] ?? array()),
                'context_used' => $response['context_used'] ?? false,
                'reasoning_applied' => $response['reasoning_applied'] ?? false
            )),
            'created_at' => current_time('mysql')
        );
        
        $insights_table = $wpdb->prefix . 'ai_chatbot_conversation_insights';
        $wpdb->insert($insights_table, $insights_data);
    }
    
    /**
     * Track semantic search analytics
     */
    private function track_semantic_analytics($query, $semantic_results) {
        global $wpdb;
        
        $similarities = array_column($semantic_results, 'similarity');
        
        $analytics_data = array(
            'query' => $query,
            'query_embedding_hash' => md5($query),
            'results_found' => count($semantic_results),
            'avg_similarity' => !empty($similarities) ? array_sum($similarities) / count($similarities) : 0,
            'top_similarity' => !empty($similarities) ? max($similarities) : 0,
            'search_type' => 'content',
            'processing_time_ms' => $this->get_response_time(),
            'created_at' => current_time('mysql')
        );
        
        $analytics_table = $wpdb->prefix . 'ai_chatbot_semantic_analytics';
        $wpdb->insert($analytics_table, $analytics_data);
    }
    
    /**
     * Build Pro response for frontend
     */
    private function build_pro_response($response_data, $conversation_data) {
        $ajax_response = array(
            'response' => $response_data['text'],
            'conversation_id' => $conversation_data['conversation_id'],
            'timestamp' => current_time('mysql'),
            'session_id' => $conversation_data['session_id'],
            'source' => $response_data['source'],
            'response_time' => $this->get_response_time()
        );
        
        // Add Pro-specific data
        if (isset($response_data['confidence'])) {
            $ajax_response['confidence'] = $response_data['confidence'];
        }
        
        if (isset($response_data['intent'])) {
            $ajax_response['intent'] = $response_data['intent'];
        }
        
        if (isset($response_data['semantic_results'])) {
            $ajax_response['semantic_results_count'] = count($response_data['semantic_results']);
        }
        
        // Generate follow-up suggestions
        $ajax_response['suggestions'] = $this->generate_followup_suggestions($response_data);
        
        // Add debug information if enabled
        if (WP_DEBUG && current_user_can('manage_options')) {
            $ajax_response['debug'] = array(
                'reasoning_applied' => $response_data['reasoning_applied'] ?? false,
                'context_used' => $response_data['context_used'] ?? false,
                'provider' => $response_data['provider'] ?? null
            );
        }
        
        return $ajax_response;
    }
    
    /**
     * Generate follow-up suggestions
     */
    private function generate_followup_suggestions($response_data) {
        $suggestions = array();
        
        // Intent-based suggestions
        $intent = $response_data['intent'] ?? null;
        
        switch ($intent) {
            case 'purchase':
                $suggestions[] = __('How do I place an order?', 'ai-website-chatbot');
                $suggestions[] = __('What payment methods do you accept?', 'ai-website-chatbot');
                $suggestions[] = __('Do you offer any discounts?', 'ai-website-chatbot');
                break;
                
            case 'support':
                $suggestions[] = __('Can you help me with something else?', 'ai-website-chatbot');
                $suggestions[] = __('How do I contact support?', 'ai-website-chatbot');
                $suggestions[] = __('Where can I find more help?', 'ai-website-chatbot');
                break;
                
            case 'information':
                $suggestions[] = __('Tell me more about this', 'ai-website-chatbot');
                $suggestions[] = __('What are the key features?', 'ai-website-chatbot');
                $suggestions[] = __('How does it work?', 'ai-website-chatbot');
                break;
                
            default:
                if (!empty($response_data['semantic_results'])) {
                    $suggestions[] = __('Show me more details', 'ai-website-chatbot');
                    $suggestions[] = __('Are there related topics?', 'ai-website-chatbot');
                }
                break;
        }
        
        return array_slice($suggestions, 0, 3);
    }
    
    /**
     * Handle conversation feedback
     */
    public function handle_conversation_feedback() {
        check_ajax_referer('ai_chatbot_nonce', 'nonce');
        
        $conversation_id = intval($_POST['conversation_id']);
        $rating = intval($_POST['rating']);
        $feedback = sanitize_textarea_field($_POST['feedback'] ?? '');
        
        if ($conversation_id && $rating >= 1 && $rating <= 5) {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
            $updated = $wpdb->update(
                $table_name,
                array(
                    'satisfaction_rating' => $rating,
                    'feedback' => $feedback
                ),
                array('id' => $conversation_id),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($updated) {
                // Update insights with satisfaction data
                $this->update_conversation_satisfaction($conversation_id, $rating);
                
                wp_send_json_success(array(
                    'message' => __('Thank you for your feedback!', 'ai-website-chatbot')
                ));
            } else {
                wp_send_json_error('Failed to save feedback');
            }
        } else {
            wp_send_json_error('Invalid data');
        }
    }
    
    /**
     * Handle test semantic search (Admin only)
     */
    public function handle_test_semantic_search() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('ai_chatbot_test_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query']);
        
        $embedding_engine = $this->get_embedding_engine();
        if ($embedding_engine) {
            $results = $embedding_engine->semantic_content_search($query, 5);
            wp_send_json_success($results);
        } else {
            wp_send_json_error('Embedding engine not available');
        }
    }
    
    /**
     * Handle generate embeddings (Admin only)
     */
    public function handle_generate_embeddings() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('ai_chatbot_embedding_nonce', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $batch_size = intval($_POST['batch_size'] ?? 10);
        
        $embedding_engine = $this->get_embedding_engine();
        if ($embedding_engine) {
            if ($type === 'all') {
                $this->reset_all_embeddings();
            }
            
            $result = $embedding_engine->batch_generate_embeddings($batch_size);
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Embedding engine not available');
        }
    }
    
    /**
     * Handle track event (Analytics)
     */
    public function handle_track_event() {
        check_ajax_referer('ai_chatbot_nonce', 'nonce');
        
        $event_data = json_decode(stripslashes($_POST['event_data']), true);
        
        if ($event_data) {
            // Store event in analytics table
            global $wpdb;
            
            $analytics_table = $wpdb->prefix . 'ai_chatbot_semantic_analytics';
            $wpdb->insert($analytics_table, array(
                'query' => $event_data['event_type'],
                'search_type' => 'event',
                'created_at' => current_time('mysql')
            ));
            
            wp_send_json_success('Event tracked');
        } else {
            wp_send_json_error('Invalid event data');
        }
    }
    
    /**
     * Helper methods
     */
    
    private function check_rate_limits() {
        $rate_limiter = new AI_Chatbot_Rate_Limiter();
        return $rate_limiter->check_rate_limit(get_current_user_id() ?: $_SERVER['REMOTE_ADDR']);
    }
    
    private function get_context_builder() {
        if (class_exists('AI_Chatbot_Context_Builder')) {
            return new AI_Chatbot_Context_Builder();
        }
        return null;
    }
    
    private function get_intent_recognizer() {
        if (class_exists('AI_Chatbot_Intent_Recognition')) {
            return new AI_Chatbot_Intent_Recognition();
        }
        return null;
    }
    
    private function get_embedding_engine() {
        if (class_exists('AI_Chatbot_Embedding_Reasoning')) {
            return new AI_Chatbot_Embedding_Reasoning();
        }
        return null;
    }
    
    private function get_reasoning_engine() {
        if (class_exists('AI_Chatbot_Response_Reasoning')) {
            return new AI_Chatbot_Response_Reasoning();
        }
        return null;
    }
    
    private function get_ai_provider() {
        $provider_name = get_option('ai_chatbot_provider', 'openai');
        
        switch ($provider_name) {
            case 'openai':
                return new AI_Chatbot_OpenAI();
            case 'claude':
                return new AI_Chatbot_Claude();
            case 'gemini':
                return new AI_Chatbot_Gemini();
            case 'custom':
                return new AI_Chatbot_Custom();
            default:
                return null;
        }
    }
    
    private function build_base_context($session_id, $context_data) {
        $context_parts = array();
        
        // Website information
        $context_parts[] = "Website: " . get_bloginfo('name');
        $context_parts[] = "Description: " . get_bloginfo('description');
        
        // Add context data from frontend
        if (!empty($context_data)) {
            if (isset($context_data['page_url'])) {
                $context_parts[] = "Current page: " . $context_data['page_url'];
            }
            if (isset($context_data['page_title'])) {
                $context_parts[] = "Page title: " . $context_data['page_title'];
            }
        }
        
        // Recent conversation history
        if (!empty($session_id)) {
            $recent_messages = $this->get_recent_conversation($session_id);
            if (!empty($recent_messages)) {
                $context_parts[] = "\nRecent conversation:";
                foreach ($recent_messages as $msg) {
                    $context_parts[] = "User: " . substr($msg['message'], 0, 100);
                    $context_parts[] = "AI: " . substr($msg['response'], 0, 100);
                }
            }
        }
        
        return implode("\n", $context_parts);
    }
    
    private function build_content_context($content_results, $base_context) {
        $context_parts = array($base_context);
        
        $context_parts[] = "\n=== RELEVANT CONTENT (SEMANTIC SEARCH) ===";
        
        foreach ($content_results as $result) {
            $context_parts[] = sprintf(
                "Content: %s (Relevance: %s%%)",
                $result['title'],
                $result['relevance_score']
            );
            
            $excerpt = substr(strip_tags($result['content']), 0, 200);
            if (strlen($result['content']) > 200) {
                $excerpt .= '...';
            }
            $context_parts[] = $excerpt;
            $context_parts[] = "";
        }
        
        return implode("\n", $context_parts);
    }
    
    private function get_recent_conversation($session_id, $limit = 3) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT message, response, created_at 
             FROM $table_name 
             WHERE session_id = %s 
             ORDER BY created_at DESC 
             LIMIT %d",
            $session_id,
            $limit
        ), ARRAY_A);
    }
    
    private function get_response_time() {
        return round((microtime(true) - $this->response_start_time) * 1000);
    }
    
    private function determine_journey_stage($context_data) {
        // Analyze context to determine user journey stage
        // This could be enhanced with more sophisticated analysis
        return 'information'; // Default stage
    }
    
    private function calculate_engagement_level($context_data) {
        // Calculate engagement based on context data
        $time_on_page = $context_data['time_on_page'] ?? 0;
        
        if ($time_on_page > 300) return 'high';
        if ($time_on_page > 60) return 'medium';
        return 'low';
    }
    
    private function calculate_lead_score($intent, $context_data) {
        // Calculate lead score based on intent and context
        $score = 0;
        
        if ($intent === 'purchase') $score += 50;
        if ($intent === 'information') $score += 20;
        if ($intent === 'support') $score += 10;
        
        // Add context-based scoring
        $time_on_page = $context_data['time_on_page'] ?? 0;
        if ($time_on_page > 300) $score += 20;
        if ($time_on_page > 120) $score += 10;
        
        return min($score, 100); // Cap at 100
    }
    
    private function update_conversation_satisfaction($conversation_id, $rating) {
        global $wpdb;
        
        $insights_table = $wpdb->prefix . 'ai_chatbot_conversation_insights';
        $wpdb->update(
            $insights_table,
            array('satisfaction_score' => $rating / 5.0), // Convert to 0-1 scale
            array('conversation_id' => $conversation_id),
            array('%f'),
            array('%d')
        );
    }
    
    private function reset_all_embeddings() {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ai_chatbot_content',
            array('embedding_status' => 'pending', 'embedding_vector' => null),
            array(),
            array('%s', '%s'),
            array()
        );
        
        $wpdb->update(
            $wpdb->prefix . 'ai_chatbot_training_data',
            array('embedding_status' => 'pending', 'question_embedding' => null, 'answer_embedding' => null),
            array(),
            array('%s', '%s', '%s'),
            array()
        );
    }
}