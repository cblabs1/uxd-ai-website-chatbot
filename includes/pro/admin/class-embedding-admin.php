<?php
/**
 * Pro Admin Interface for Embedding Management
 * 
 * File: includes/pro/admin/class-embedding-admin.php
 */

class AI_Chatbot_Embedding_Admin {
    
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_embedding_menu' ) );
        add_action( 'wp_ajax_ai_chatbot_generate_embeddings', array( $this, 'ajax_generate_embeddings' ) );
        add_action( 'wp_ajax_ai_chatbot_embedding_status', array( $this, 'ajax_embedding_status' ) );
        add_action( 'wp_ajax_ai_chatbot_test_semantic_search', array( $this, 'ajax_test_semantic_search' ) );
    }
    
    /**
     * Add embedding management menu
     */
    public function add_embedding_menu() {
        add_submenu_page(
            'ai-chatbot',
            __( 'Semantic Intelligence', 'ai-website-chatbot' ),
            __( 'Semantic Intelligence', 'ai-website-chatbot' ),
            'manage_options',
            'ai-chatbot-embeddings',
            array( $this, 'render_embedding_page' )
        );
    }
    
    /**
     * Render embedding management page
     */
    public function render_embedding_page() {
        $embedding_engine = new AI_Chatbot_Embedding_Reasoning();
        $status = $this->get_embedding_status();
        
        ?>
        <div class="wrap ai-chatbot-embedding-admin">
            <h1><?php _e( 'Semantic Intelligence Dashboard', 'ai-website-chatbot' ); ?></h1>
            
            <?php if ( ! ai_chatbot_has_feature( 'intelligence_engine' ) ): ?>
                <div class="notice notice-warning">
                    <p><?php _e( 'Semantic Intelligence requires Pro license.', 'ai-website-chatbot' ); ?></p>
                    <p><a href="<?php echo ai_chatbot_get_upgrade_url( 'intelligence_engine' ); ?>" class="button button-primary">
                        <?php _e( 'Upgrade to Pro', 'ai-website-chatbot' ); ?>
                    </a></p>
                </div>
                <?php return; ?>
            <?php endif; ?>
            
            <!-- Status Overview -->
            <div class="embedding-status-grid">
                <div class="status-card">
                    <h3><?php _e( 'Content Embeddings', 'ai-website-chatbot' ); ?></h3>
                    <div class="status-info">
                        <span class="status-number"><?php echo $status['content']['completed']; ?></span>
                        <span class="status-label">/ <?php echo $status['content']['total']; ?> <?php _e( 'completed', 'ai-website-chatbot' ); ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $status['content']['percentage']; ?>%"></div>
                    </div>
                </div>
                
                <div class="status-card">
                    <h3><?php _e( 'Training Data', 'ai-website-chatbot' ); ?></h3>
                    <div class="status-info">
                        <span class="status-number"><?php echo $status['training']['completed']; ?></span>
                        <span class="status-label">/ <?php echo $status['training']['total']; ?> <?php _e( 'completed', 'ai-website-chatbot' ); ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $status['training']['percentage']; ?>%"></div>
                    </div>
                </div>
                
                <div class="status-card">
                    <h3><?php _e( 'Search Quality', 'ai-website-chatbot' ); ?></h3>
                    <div class="status-info">
                        <span class="status-number"><?php echo number_format( $this->get_average_search_quality(), 1 ); ?></span>
                        <span class="status-label">/ 5.0 <?php _e( 'avg score', 'ai-website-chatbot' ); ?></span>
                    </div>
                    <div class="quality-indicator <?php echo $this->get_quality_class(); ?>">
                        <?php echo $this->get_quality_label(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Configuration Section -->
            <div class="embedding-config-section">
                <h2><?php _e( 'Semantic Search Configuration', 'ai-website-chatbot' ); ?></h2>
                
                <form method="post" action="options.php" class="embedding-settings-form">
                    <?php settings_fields( 'ai_chatbot_embedding_settings' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e( 'Embedding Provider', 'ai-website-chatbot' ); ?></th>
                            <td>
                                <select name="ai_chatbot_embedding_provider">
                                    <option value="openai" <?php selected( get_option( 'ai_chatbot_embedding_provider' ), 'openai' ); ?>>
                                        OpenAI (text-embedding-ada-002)
                                    </option>
                                    <option value="local" <?php selected( get_option( 'ai_chatbot_embedding_provider' ), 'local' ); ?>>
                                        Local Model (Coming Soon)
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e( 'Choose your embedding provider. OpenAI provides the best quality.', 'ai-website-chatbot' ); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e( 'Similarity Threshold', 'ai-website-chatbot' ); ?></th>
                            <td>
                                <input type="range" 
                                       name="ai_chatbot_embedding_similarity_threshold" 
                                       min="0.5" 
                                       max="0.95" 
                                       step="0.05" 
                                       value="<?php echo esc_attr( get_option( 'ai_chatbot_embedding_similarity_threshold', '0.75' ) ); ?>"
                                       oninput="this.nextElementSibling.value = this.value" />
                                <output><?php echo get_option( 'ai_chatbot_embedding_similarity_threshold', '0.75' ); ?></output>
                                <p class="description">
                                    <?php _e( 'Higher values = more precise matches. Lower values = broader results.', 'ai-website-chatbot' ); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e( 'Batch Processing Size', 'ai-website-chatbot' ); ?></th>
                            <td>
                                <select name="ai_chatbot_embedding_batch_size">
                                    <option value="5" <?php selected( get_option( 'ai_chatbot_embedding_batch_size' ), '5' ); ?>>5 items</option>
                                    <option value="10" <?php selected( get_option( 'ai_chatbot_embedding_batch_size' ), '10' ); ?>>10 items</option>
                                    <option value="20" <?php selected( get_option( 'ai_chatbot_embedding_batch_size' ), '20' ); ?>>20 items</option>
                                </select>
                                <p class="description">
                                    <?php _e( 'Number of items to process at once. Smaller batches are safer for API limits.', 'ai-website-chatbot' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
            
            <!-- Actions Section -->
            <div class="embedding-actions-section">
                <h2><?php _e( 'Embedding Actions', 'ai-website-chatbot' ); ?></h2>
                
                <div class="action-buttons">
                    <button type="button" 
                            id="generate-embeddings-btn" 
                            class="button button-primary">
                        <?php _e( 'Generate Missing Embeddings', 'ai-website-chatbot' ); ?>
                    </button>
                    
                    <button type="button" 
                            id="regenerate-all-btn" 
                            class="button button-secondary">
                        <?php _e( 'Regenerate All Embeddings', 'ai-website-chatbot' ); ?>
                    </button>
                    
                    <button type="button" 
                            id="clear-cache-btn" 
                            class="button button-secondary">
                        <?php _e( 'Clear Embedding Cache', 'ai-website-chatbot' ); ?>
                    </button>
                </div>
                
                <div id="embedding-progress" class="embedding-progress" style="display: none;">
                    <h4><?php _e( 'Processing Embeddings...', 'ai-website-chatbot' ); ?></h4>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-text">0 / 0 items processed</div>
                </div>
            </div>
            
            <!-- Test Section -->
            <div class="embedding-test-section">
                <h2><?php _e( 'Semantic Search Testing', 'ai-website-chatbot' ); ?></h2>
                
                <div class="test-interface">
                    <div class="test-input">
                        <label for="test-query"><?php _e( 'Test Query:', 'ai-website-chatbot' ); ?></label>
                        <input type="text" 
                               id="test-query" 
                               placeholder="<?php _e( 'Enter a test question...', 'ai-website-chatbot' ); ?>" 
                               style="width: 100%; padding: 10px;" />
                        <button type="button" 
                                id="test-search-btn" 
                                class="button button-primary">
                            <?php _e( 'Test Semantic Search', 'ai-website-chatbot' ); ?>
                        </button>
                    </div>
                    
                    <div id="test-results" class="test-results" style="display: none;">
                        <h4><?php _e( 'Search Results:', 'ai-website-chatbot' ); ?></h4>
                        <div class="results-container"></div>
                    </div>
                </div>
            </div>
            
            <!-- Analytics Section -->
            <div class="embedding-analytics-section">
                <h2><?php _e( 'Semantic Search Analytics', 'ai-website-chatbot' ); ?></h2>
                
                <?php $this->render_analytics_charts(); ?>
            </div>
        </div>
        
        <style>
        .embedding-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .status-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .status-info {
            margin: 10px 0;
        }
        
        .status-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #00a0d2);
            transition: width 0.3s ease;
        }
        
        .quality-indicator {
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        
        .quality-excellent { background: #46b450; color: white; }
        .quality-good { background: #ffb900; color: white; }
        .quality-fair { background: #f56e28; color: white; }
        .quality-poor { background: #dc3232; color: white; }
        
        .embedding-progress {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
        }
        
        .test-interface {
            background: white;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .test-results {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .result-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-left: 4px solid #0073aa;
        }
        
        .result-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .result-similarity {
            float: right;
            background: #0073aa;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Generate embeddings button
            $('#generate-embeddings-btn').on('click', function() {
                generateEmbeddings('missing');
            });
            
            $('#regenerate-all-btn').on('click', function() {
                if (confirm('<?php _e( 'This will regenerate ALL embeddings. Continue?', 'ai-website-chatbot' ); ?>')) {
                    generateEmbeddings('all');
                }
            });
            
            $('#clear-cache-btn').on('click', function() {
                if (confirm('<?php _e( 'Clear embedding cache?', 'ai-website-chatbot' ); ?>')) {
                    clearEmbeddingCache();
                }
            });
            
            $('#test-search-btn').on('click', function() {
                testSemanticSearch();
            });
            
            function generateEmbeddings(type) {
                $('#embedding-progress').show();
                
                $.post(ajaxurl, {
                    action: 'ai_chatbot_generate_embeddings',
                    type: type,
                    nonce: '<?php echo wp_create_nonce( 'ai_chatbot_embedding_nonce' ); ?>'
                }, function(response) {
                    if (response.success) {
                        updateProgress(response.data);
                        if (response.data.remaining > 0) {
                            // Continue processing
                            setTimeout(function() {
                                generateEmbeddings(type);
                            }, 1000);
                        } else {
                            $('#embedding-progress').hide();
                            location.reload();
                        }
                    } else {
                        alert('Error: ' + response.data);
                        $('#embedding-progress').hide();
                    }
                });
            }
            
            function updateProgress(data) {
                var total = data.processed + data.remaining;
                var percentage = total > 0 ? (data.processed / total) * 100 : 0;
                
                $('#embedding-progress .progress-fill').css('width', percentage + '%');
                $('#embedding-progress .progress-text').text(
                    data.processed + ' / ' + total + ' items processed'
                );
            }
            
            function testSemanticSearch() {
                var query = $('#test-query').val();
                if (!query) return;
                
                $('#test-results').show().find('.results-container').html('Loading...');
                
                $.post(ajaxurl, {
                    action: 'ai_chatbot_test_semantic_search',
                    query: query,
                    nonce: '<?php echo wp_create_nonce( 'ai_chatbot_test_nonce' ); ?>'
                }, function(response) {
                    if (response.success) {
                        displayTestResults(response.data);
                    } else {
                        $('#test-results .results-container').html('Error: ' + response.data);
                    }
                });
            }
            
            function displayTestResults(results) {
                var html = '';
                if (results.length === 0) {
                    html = '<p>No results found.</p>';
                } else {
                    results.forEach(function(result) {
                        html += '<div class="result-item">';
                        html += '<div class="result-similarity">' + result.relevance_score + '%</div>';
                        html += '<div class="result-title">' + result.title + '</div>';
                        html += '<div class="result-content">' + result.content.substring(0, 200) + '...</div>';
                        html += '</div>';
                    });
                }
                $('#test-results .results-container').html(html);
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for generating embeddings
     */
    public function ajax_generate_embeddings() {
        check_ajax_referer( 'ai_chatbot_embedding_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        
        $type = sanitize_text_field( $_POST['type'] );
        $embedding_engine = new AI_Chatbot_Embedding_Reasoning();
        
        if ( $type === 'all' ) {
            // Reset all embedding statuses first
            $this->reset_all_embeddings();
        }
        
        $result = $embedding_engine->batch_generate_embeddings( 
            get_option( 'ai_chatbot_embedding_batch_size', 10 ) 
        );
        
        wp_send_json_success( $result );
    }
    
    /**
     * AJAX handler for testing semantic search
     */
    public function ajax_test_semantic_search() {
        check_ajax_referer( 'ai_chatbot_test_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        
        $query = sanitize_text_field( $_POST['query'] );
        $embedding_engine = new AI_Chatbot_Embedding_Reasoning();
        
        $results = $embedding_engine->semantic_content_search( $query, 5 );
        
        wp_send_json_success( $results );
    }
    
    /**
     * Get embedding status for dashboard
     */
    private function get_embedding_status() {
        global $wpdb;
        
        // Content status
        $content_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN embedding_status = 'completed' THEN 1 ELSE 0 END) as completed
             FROM {$wpdb->prefix}ai_chatbot_content"
        );
        
        // Training status  
        $training_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN embedding_status = 'completed' THEN 1 ELSE 0 END) as completed
             FROM {$wpdb->prefix}ai_chatbot_training_data"
        );
        
        return array(
            'content' => array(
                'total' => $content_stats->total,
                'completed' => $content_stats->completed,
                'percentage' => $content_stats->total > 0 ? 
                    round( ( $content_stats->completed / $content_stats->total ) * 100, 1 ) : 0
            ),
            'training' => array(
                'total' => $training_stats->total,
                'completed' => $training_stats->completed,
                'percentage' => $training_stats->total > 0 ? 
                    round( ( $training_stats->completed / $training_stats->total ) * 100, 1 ) : 0
            )
        );
    }
    
    /**
     * Additional helper methods for analytics, etc.
     */
    private function get_average_search_quality() {
        global $wpdb;
        
        $result = $wpdb->get_var(
            "SELECT AVG(top_similarity) 
             FROM {$wpdb->prefix}ai_chatbot_semantic_analytics 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        return $result ? $result * 5 : 0; // Convert to 5-point scale
    }
    
    private function get_quality_class() {
        $quality = $this->get_average_search_quality();
        
        if ( $quality >= 4.0 ) return 'quality-excellent';
        if ( $quality >= 3.0 ) return 'quality-good';
        if ( $quality >= 2.0 ) return 'quality-fair';
        return 'quality-poor';
    }
    
    private function get_quality_label() {
        $quality = $this->get_average_search_quality();
        
        if ( $quality >= 4.0 ) return __( 'Excellent', 'ai-website-chatbot' );
        if ( $quality >= 3.0 ) return __( 'Good', 'ai-website-chatbot' );
        if ( $quality >= 2.0 ) return __( 'Fair', 'ai-website-chatbot' );
        return __( 'Needs Improvement', 'ai-website-chatbot' );
    }
    
    private function render_analytics_charts() {
        // Implementation for analytics charts
        echo '<div class="analytics-placeholder">';
        echo '<p>' . __( 'Analytics charts will show search performance, similarity scores, and usage patterns.', 'ai-website-chatbot' ) . '</p>';
        echo '</div>';
    }
    
    private function reset_all_embeddings() {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ai_chatbot_content',
            array( 'embedding_status' => 'pending', 'embedding_vector' => null ),
            array(),
            array( '%s', '%s' ),
            array()
        );
        
        $wpdb->update(
            $wpdb->prefix . 'ai_chatbot_training_data',
            array( 'embedding_status' => 'pending', 'embedding_vector' => null ),
            array(),
            array( '%s', '%s' ),
            array()
        );
    }
}