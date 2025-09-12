<?php
/**
 * Embedding-Enhanced Reasoning Module for AI Chatbot Pro
 * 
 * File: includes/pro/intelligence/class-embedding-reasoning.php
 * 
 * This module uses embeddings to dramatically improve semantic understanding
 * and reasoning capabilities beyond simple keyword matching.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Embedding-Enhanced Reasoning Engine
 * 
 * Uses vector embeddings for semantic search and context understanding
 */
class AI_Chatbot_Embedding_Reasoning {
    
    /**
     * Embedding provider (OpenAI, local, etc.)
     */
    private $embedding_provider;
    
    /**
     * Vector dimension (1536 for OpenAI text-embedding-ada-002)
     */
    private $vector_dimension = 1536;
    
    /**
     * Similarity threshold for matches
     */
    private $similarity_threshold = 0.75;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->embedding_provider = $this->get_embedding_provider();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Replace basic content search with semantic search
        add_filter( 'ai_chatbot_content_search', array( $this, 'semantic_content_search' ), 10, 3 );
        
        // Enhance training data matching
        add_filter( 'ai_chatbot_training_match', array( $this, 'semantic_training_match' ), 10, 2 );
        
        // Add context clustering for better understanding
        add_filter( 'ai_chatbot_context_analysis', array( $this, 'cluster_context_semantically' ), 10, 2 );
        
        // Enhance intent recognition with semantic similarity
        add_filter( 'ai_chatbot_intent_analysis', array( $this, 'semantic_intent_recognition' ), 10, 2 );
    }
    
    /**
     * Semantic content search using embeddings
     * 
     * This replaces keyword matching with true semantic understanding
     */
    public function semantic_content_search( $query, $limit = 5, $context = '' ) {
        // 1. Generate embedding for user query
        $query_embedding = $this->generate_embedding( $query );
        
        if ( is_wp_error( $query_embedding ) ) {
            // Fallback to keyword search if embedding fails
            return $this->fallback_keyword_search( $query, $limit );
        }
        
        // 2. Find semantically similar content
        $similar_content = $this->find_similar_content_by_vector( $query_embedding, $limit );
        
        // 3. Re-rank results based on additional context
        if ( ! empty( $context ) ) {
            $similar_content = $this->rerank_with_context( $similar_content, $context );
        }
        
        return $this->format_search_results( $similar_content );
    }
    
    /**
     * Semantic training data matching
     * 
     * Finds training responses that are semantically similar, not just keyword matches
     */
    public function semantic_training_match( $message, $similarity_threshold = null ) {
        if ( $similarity_threshold === null ) {
            $similarity_threshold = $this->similarity_threshold;
        }
        
        // Generate embedding for user message
        $message_embedding = $this->generate_embedding( $message );
        
        if ( is_wp_error( $message_embedding ) ) {
            return new WP_Error( 'embedding_failed', 'Could not generate embedding for message' );
        }
        
        // Find semantically similar training questions
        $similar_training = $this->find_similar_training_by_vector( $message_embedding, $similarity_threshold );
        
        if ( empty( $similar_training ) ) {
            return new WP_Error( 'no_semantic_match', 'No semantically similar training found' );
        }
        
        // Return best match with confidence score
        return array(
            'response' => $similar_training[0]['answer'],
            'confidence' => $similar_training[0]['similarity'],
            'reasoning' => $this->explain_semantic_match( $message, $similar_training[0] )
        );
    }
    
    /**
     * Cluster context semantically for better understanding
     */
    public function cluster_context_semantically( $context_parts, $message ) {
        if ( empty( $context_parts ) ) {
            return $context_parts;
        }
        
        // Generate embeddings for each context part
        $context_embeddings = array();
        foreach ( $context_parts as $index => $part ) {
            $embedding = $this->generate_embedding( $part );
            if ( ! is_wp_error( $embedding ) ) {
                $context_embeddings[ $index ] = array(
                    'text' => $part,
                    'embedding' => $embedding,
                    'relevance' => $this->calculate_relevance_to_query( $embedding, $message )
                );
            }
        }
        
        // Sort by relevance and return most relevant context
        usort( $context_embeddings, function( $a, $b ) {
            return $b['relevance'] <=> $a['relevance'];
        });
        
        // Return top relevant context parts
        $relevant_context = array_slice( $context_embeddings, 0, 5 );
        return array_column( $relevant_context, 'text' );
    }
    
    /**
     * Enhanced intent recognition using semantic similarity
     */
    public function semantic_intent_recognition( $message, $existing_intents ) {
        // Define intent examples with embeddings
        $intent_examples = $this->get_intent_examples_with_embeddings();
        
        $message_embedding = $this->generate_embedding( $message );
        if ( is_wp_error( $message_embedding ) ) {
            return $existing_intents;
        }
        
        $semantic_intents = array();
        
        foreach ( $intent_examples as $intent => $examples ) {
            $max_similarity = 0;
            
            foreach ( $examples['embeddings'] as $example_embedding ) {
                $similarity = $this->cosine_similarity( $message_embedding, $example_embedding );
                $max_similarity = max( $max_similarity, $similarity );
            }
            
            if ( $max_similarity > 0.7 ) {
                $semantic_intents[ $intent ] = $max_similarity;
            }
        }
        
        // Merge with existing intents, giving semantic results higher weight
        foreach ( $semantic_intents as $intent => $confidence ) {
            if ( isset( $existing_intents[ $intent ] ) ) {
                $existing_intents[ $intent ] = max( $existing_intents[ $intent ], $confidence * 1.2 );
            } else {
                $existing_intents[ $intent ] = $confidence;
            }
        }
        
        arsort( $existing_intents );
        return $existing_intents;
    }
    
    /**
     * Generate embedding for text
     */
    private function generate_embedding( $text ) {
        // Clean and prepare text
        $clean_text = $this->prepare_text_for_embedding( $text );
        
        if ( empty( $clean_text ) ) {
            return new WP_Error( 'empty_text', 'Text is empty after cleaning' );
        }
        
        // Check cache first
        $cache_key = 'ai_chatbot_embedding_' . md5( $clean_text );
        $cached_embedding = get_transient( $cache_key );
        
        if ( $cached_embedding !== false ) {
            return $cached_embedding;
        }
        
        // Generate new embedding
        $embedding = $this->call_embedding_api( $clean_text );
        
        if ( ! is_wp_error( $embedding ) ) {
            // Cache for 24 hours
            set_transient( $cache_key, $embedding, DAY_IN_SECONDS );
        }
        
        return $embedding;
    }
    
    /**
     * Call embedding API (OpenAI, local model, etc.)
     */
    private function call_embedding_api( $text ) {
        $provider = get_option( 'ai_chatbot_embedding_provider', 'openai' );
        
        switch ( $provider ) {
            case 'openai':
                return $this->get_openai_embedding( $text );
                
            case 'local':
                return $this->get_local_embedding( $text );
                
            case 'huggingface':
                return $this->get_huggingface_embedding( $text );
                
            default:
                return new WP_Error( 'unsupported_provider', 'Embedding provider not supported' );
        }
    }
    
    /**
     * Get OpenAI embedding
     */
    private function get_openai_embedding( $text ) {
        // Use the SAME logic as the provider classes to get the API key
        $api_key = $this->get_provider_api_key('openai');
        
        if ( empty( $api_key ) ) {
            error_log('AI Chatbot: No OpenAI API key found using provider logic');
            return new WP_Error( 'no_api_key', 'OpenAI API key not configured' );
        }
        
        // Validate API key format
        if ( strlen($api_key) < 40 || strpos($api_key, 'sk-') !== 0 ) {
            error_log('AI Chatbot: Invalid API key format');
            return new WP_Error( 'invalid_api_key', 'Invalid OpenAI API key format' );
        }
        
        $response = wp_remote_post( 'https://api.openai.com/v1/embeddings', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'input' => $text,
                'model' => 'text-embedding-ada-002'
            ) ),
            'timeout' => 30,
        ) );
        
        if ( is_wp_error( $response ) ) {
            error_log('AI Chatbot: HTTP request failed: ' . $response->get_error_message());
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $status_code !== 200 ) {
            $error_message = isset( $body['error']['message'] ) 
                ? $body['error']['message'] 
                : 'HTTP ' . $status_code . ' error';
            error_log('AI Chatbot: API returned error: ' . $error_message);
            return new WP_Error( 'api_error', $error_message );
        }
        
        if ( isset( $body['error'] ) ) {
            error_log('AI Chatbot: API error: ' . $body['error']['message']);
            return new WP_Error( 'api_error', $body['error']['message'] );
        }
        
        if ( ! isset( $body['data'][0]['embedding'] ) ) {
            error_log('AI Chatbot: Invalid response structure');
            return new WP_Error( 'invalid_response', 'Invalid embedding response' );
        }
        
        return $body['data'][0]['embedding'];
    }
    
    /**
     * Find similar content by vector similarity
     */
    private function find_similar_content_by_vector( $query_embedding, $limit = 5 ) {
        global $wpdb;
        
        $content_table = $wpdb->prefix . 'ai_chatbot_content';
        
        // Get all content with embeddings
        $content_with_embeddings = $wpdb->get_results(
            "SELECT id, title, content, url, embedding_vector 
             FROM $content_table 
             WHERE embedding_vector IS NOT NULL 
             AND embedding_status = 'completed'",
            ARRAY_A
        );
        
        if ( empty( $content_with_embeddings ) ) {
            return array();
        }
        
        $similarities = array();
        
        foreach ( $content_with_embeddings as $content ) {
            $content_embedding = json_decode( $content['embedding_vector'], true );
            
            if ( $content_embedding ) {
                $similarity = $this->cosine_similarity( $query_embedding, $content_embedding );
                
                if ( $similarity > $this->similarity_threshold ) {
                    $similarities[] = array(
                        'content' => $content,
                        'similarity' => $similarity
                    );
                }
            }
        }
        
        // Sort by similarity (highest first)
        usort( $similarities, function( $a, $b ) {
            return $b['similarity'] <=> $a['similarity'];
        } );
        
        return array_slice( $similarities, 0, $limit );
    }
    
    /**
     * Find similar training data by vector similarity
     */
    private function find_similar_training_by_vector( $query_embedding, $threshold ) {
        global $wpdb;
        
        $training_table = $wpdb->prefix . 'ai_chatbot_training_data';
        
        // Get all training data with embeddings
        $training_with_embeddings = $wpdb->get_results(
            "SELECT id, question, answer, intent, embedding_vector 
             FROM $training_table 
             WHERE embedding_vector IS NOT NULL 
             AND status = 'active'",
            ARRAY_A
        );
        
        if ( empty( $training_with_embeddings ) ) {
            return array();
        }
        
        $similarities = array();
        
        foreach ( $training_with_embeddings as $training ) {
            $training_embedding = json_decode( $training['embedding_vector'], true );
            
            if ( $training_embedding ) {
                $similarity = $this->cosine_similarity( $query_embedding, $training_embedding );
                
                if ( $similarity > $threshold ) {
                    $similarities[] = array(
                        'question' => $training['question'],
                        'answer' => $training['answer'],
                        'intent' => $training['intent'],
                        'similarity' => $similarity
                    );
                }
            }
        }
        
        // Sort by similarity (highest first)
        usort( $similarities, function( $a, $b ) {
            return $b['similarity'] <=> $a['similarity'];
        } );
        
        return $similarities;
    }
    
    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosine_similarity( $vector1, $vector2 ) {
        if ( count( $vector1 ) !== count( $vector2 ) ) {
            return 0;
        }
        
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ( $i = 0; $i < count( $vector1 ); $i++ ) {
            $dot_product += $vector1[ $i ] * $vector2[ $i ];
            $norm1 += $vector1[ $i ] * $vector1[ $i ];
            $norm2 += $vector2[ $i ] * $vector2[ $i ];
        }
        
        $norm1 = sqrt( $norm1 );
        $norm2 = sqrt( $norm2 );
        
        if ( $norm1 == 0 || $norm2 == 0 ) {
            return 0;
        }
        
        return $dot_product / ( $norm1 * $norm2 );
    }
    
    /**
     * Prepare text for embedding generation
     */
    private function prepare_text_for_embedding( $text ) {
        // Remove HTML tags
        $clean_text = wp_strip_all_tags( $text );
        
        // Normalize whitespace
        $clean_text = preg_replace( '/\s+/', ' ', $clean_text );
        
        // Trim and limit length
        $clean_text = trim( $clean_text );
        
        // Limit to reasonable length for embeddings (8192 tokens for OpenAI)
        if ( strlen( $clean_text ) > 30000 ) {
            $clean_text = substr( $clean_text, 0, 30000 );
        }
        
        return $clean_text;
    }
    
    /**
     * Get intent examples with pre-computed embeddings
     */
    private function get_intent_examples_with_embeddings() {
        $cache_key = 'ai_chatbot_intent_embeddings';
        $cached = get_transient( $cache_key );
        
        if ( $cached !== false ) {
            return $cached;
        }
        
        $intent_examples = array(
            'purchase' => array(
                'examples' => array(
                    'How much does this cost?',
                    'I want to buy your product',
                    'What are your prices?',
                    'Can I purchase this online?',
                    'How do I place an order?'
                )
            ),
            'support' => array(
                'examples' => array(
                    'I need help with my account',
                    'This feature is not working',
                    'How do I fix this problem?',
                    'I am having trouble with...',
                    'Can you help me troubleshoot?'
                )
            ),
            'information' => array(
                'examples' => array(
                    'Tell me more about your service',
                    'What features do you offer?',
                    'How does this work?',
                    'What is included in your plan?',
                    'Can you explain your process?'
                )
            )
        );
        
        // Generate embeddings for examples
        foreach ( $intent_examples as $intent => &$data ) {
            $data['embeddings'] = array();
            
            foreach ( $data['examples'] as $example ) {
                $embedding = $this->generate_embedding( $example );
                if ( ! is_wp_error( $embedding ) ) {
                    $data['embeddings'][] = $embedding;
                }
            }
        }
        
        // Cache for 1 hour
        set_transient( $cache_key, $intent_examples, HOUR_IN_SECONDS );
        
        return $intent_examples;
    }
    
    /**
     * Calculate relevance of context to query
     */
    private function calculate_relevance_to_query( $context_embedding, $query ) {
        $query_embedding = $this->generate_embedding( $query );
        
        if ( is_wp_error( $query_embedding ) ) {
            return 0;
        }
        
        return $this->cosine_similarity( $context_embedding, $query_embedding );
    }
    
    /**
     * Explain why a semantic match was made
     */
    private function explain_semantic_match( $user_message, $matched_training ) {
        return sprintf(
            'Found semantic match with confidence %.2f for question: "%s"',
            $matched_training['similarity'],
            substr( $matched_training['question'], 0, 100 )
        );
    }
    
    /**
     * Re-rank results based on additional context
     */
    private function rerank_with_context( $results, $context ) {
        $context_embedding = $this->generate_embedding( $context );
        
        if ( is_wp_error( $context_embedding ) ) {
            return $results;
        }
        
        foreach ( $results as &$result ) {
            $content_embedding = json_decode( $result['content']['embedding_vector'], true );
            
            if ( $content_embedding ) {
                $context_relevance = $this->cosine_similarity( $content_embedding, $context_embedding );
                
                // Boost similarity score based on context relevance
                $result['similarity'] = ( $result['similarity'] * 0.7 ) + ( $context_relevance * 0.3 );
            }
        }
        
        // Re-sort by new similarity scores
        usort( $results, function( $a, $b ) {
            return $b['similarity'] <=> $a['similarity'];
        } );
        
        return $results;
    }
    
    /**
     * Format search results for consumption
     */
    private function format_search_results( $similar_content ) {
        $formatted = array();
        
        foreach ( $similar_content as $item ) {
            $formatted[] = array(
                'title' => $item['content']['title'],
                'content' => $item['content']['content'],
                'url' => $item['content']['url'],
                'similarity' => $item['similarity'],
                'relevance_score' => round( $item['similarity'] * 100, 1 )
            );
        }
        
        return $formatted;
    }
    
    /**
     * Fallback keyword search when embeddings fail
     */
    private function fallback_keyword_search( $query, $limit ) {
        global $wpdb;
        
        $content_table = $wpdb->prefix . 'ai_chatbot_content';
        $keywords = explode( ' ', strtolower( $query ) );
        $search_conditions = array();
        $search_values = array();
        
        foreach ( $keywords as $keyword ) {
            if ( strlen( $keyword ) > 2 ) {
                $search_conditions[] = '(title LIKE %s OR content LIKE %s)';
                $search_values[] = '%' . $wpdb->esc_like( $keyword ) . '%';
                $search_values[] = '%' . $wpdb->esc_like( $keyword ) . '%';
            }
        }
        
        if ( empty( $search_conditions ) ) {
            return array();
        }
        
        $search_query = "SELECT title, content, url 
                         FROM $content_table 
                         WHERE " . implode( ' OR ', $search_conditions ) . "
                         ORDER BY updated_at DESC 
                         LIMIT %d";
        
        $search_values[] = $limit;
        
        $results = $wpdb->get_results( $wpdb->prepare( $search_query, ...$search_values ), ARRAY_A );
        
        return array_map( function( $result ) {
            return array(
                'title' => $result['title'],
                'content' => $result['content'],
                'url' => $result['url'],
                'similarity' => 0.5, // Default similarity for keyword matches
                'relevance_score' => 50
            );
        }, $results );
    }
    
    /**
     * Batch generate embeddings for content
     * 
     * Use this to populate embeddings for existing content
     */
    public function batch_generate_embeddings( $batch_size = 10 ) {
        global $wpdb;
        
        error_log('AI Chatbot Debug: Starting batch_generate_embeddings with batch_size: ' . $batch_size);
        
        $content_table = $wpdb->prefix . 'ai_chatbot_content';
        
        // Get content without embeddings
        $content_items = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, content 
            FROM $content_table 
            WHERE embedding_vector IS NULL 
            OR embedding_status = 'pending'
            LIMIT %d",
            $batch_size
        ), ARRAY_A );
        
        error_log('AI Chatbot Debug: Found ' . count($content_items) . ' items to process');
        
        $processed = 0;
        $errors = 0;
        
        foreach ( $content_items as $item ) {
            error_log('AI Chatbot Debug: Processing item ID: ' . $item['id'] . ', Title: ' . substr($item['title'], 0, 50));
            
            // Update status to processing
            $wpdb->update(
                $content_table,
                array( 'embedding_status' => 'processing' ),
                array( 'id' => $item['id'] )
            );
            
            // Generate embedding for title + content
            $text = $item['title'] . ' ' . $item['content'];
            $text = trim($text);
            
            error_log('AI Chatbot Debug: Text length: ' . strlen($text) . ' chars');
            
            $embedding = $this->generate_embedding( $text );
            
            if ( is_wp_error( $embedding ) ) {
                error_log('AI Chatbot Debug: Embedding generation failed: ' . $embedding->get_error_message());
                
                // Mark as error
                $wpdb->update(
                    $content_table,
                    array( 'embedding_status' => 'error' ),
                    array( 'id' => $item['id'] )
                );
                $errors++;
            } else {
                error_log('AI Chatbot Debug: Embedding generated successfully, vector length: ' . count($embedding));
                
                // Save embedding
                $wpdb->update(
                    $content_table,
                    array(
                        'embedding_vector' => wp_json_encode( $embedding ),
                        'embedding_status' => 'completed'
                    ),
                    array( 'id' => $item['id'] )
                );
                $processed++;
            }
            
            // Small delay to avoid rate limits
            usleep( 100000 ); // 100ms
        }
        
        error_log('AI Chatbot Debug: Batch complete. Processed: ' . $processed . ', Errors: ' . $errors);
        
        return array(
            'processed' => $processed,
            'errors' => $errors,
            'remaining' => $this->get_pending_embedding_count(),
        );
    }
    
    /**
     * Get count of content items needing embeddings
     */
    public function get_pending_embedding_count() {
        global $wpdb;
        
        $content_table = $wpdb->prefix . 'ai_chatbot_content';
        
        return $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM $content_table 
             WHERE embedding_vector IS NULL 
             OR embedding_status IN ('pending', 'error')"
        );
    }
    
    /**
     * Get embedding provider instance
     */
    private function get_embedding_provider() {
        $provider = get_option( 'ai_chatbot_embedding_provider', 'openai' );
        
        // Future: Return specific provider instance
        return $provider;
    }

    private function get_provider_api_key($provider_name) {
        $main_settings = get_option('ai_chatbot_settings', array());
        
        // Check if this provider is selected and API key exists in main settings
        if (!empty($main_settings['api_key']) && 
            isset($main_settings['ai_provider']) && 
            $main_settings['ai_provider'] === $provider_name) {
            
            error_log('AI Chatbot: Found API key in main settings for provider: ' . $provider_name);
            return $main_settings['api_key'];
        }
        
        // Fallback to old structure: individual options
        $fallback_key = get_option('ai_chatbot_' . $provider_name . '_api_key', '');
        if (!empty($fallback_key)) {
            error_log('AI Chatbot: Found API key in fallback option: ai_chatbot_' . $provider_name . '_api_key');
            return $fallback_key;
        }
        
        error_log('AI Chatbot: No API key found for provider: ' . $provider_name);
        error_log('AI Chatbot: Main settings provider: ' . ($main_settings['ai_provider'] ?? 'not set'));
        error_log('AI Chatbot: Main settings has api_key: ' . (empty($main_settings['api_key']) ? 'NO' : 'YES'));
        
        return null;
    }
}