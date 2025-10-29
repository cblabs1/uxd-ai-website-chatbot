<?php
/**
 * Pro Features Page Display
 *
 * @package AI_Website_Chatbot
 * @subpackage Admin/Partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ai-chatbot-pro-page">
    <h1><?php _e('Pro Features', 'ai-website-chatbot'); ?> ⭐</h1>
    
    <div class="pro-features-hero">
        <div class="hero-content">
            <h2><?php _e('Unlock Advanced AI Capabilities', 'ai-website-chatbot'); ?></h2>
            <p class="hero-description">
                <?php _e('Take your chatbot to the next level with powerful Pro features designed for businesses that demand more.', 'ai-website-chatbot'); ?>
            </p>
            <a href="#" class="button button-primary button-hero"><?php _e('Upgrade to Pro', 'ai-website-chatbot'); ?></a>
        </div>
    </div>
    
    <div class="pro-features-grid">
        
        <!-- Semantic Intelligence -->
        <div class="pro-feature-card">
            <div class="feature-icon">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <h3><?php _e('Semantic Intelligence', 'ai-website-chatbot'); ?></h3>
            <p><?php _e('Advanced content understanding using vector embeddings for more accurate and contextual responses.', 'ai-website-chatbot'); ?></p>
            <ul class="feature-benefits">
                <li><?php _e('Vector-based semantic search', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Deep content understanding', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Contextual similarity matching', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Improved answer accuracy', 'ai-website-chatbot'); ?></li>
            </ul>
        </div>
        
        <!-- Voice Features -->
        <div class="pro-feature-card">
            <div class="feature-icon">
                <span class="dashicons dashicons-microphone"></span>
            </div>
            <h3><?php _e('Voice & Audio Mode', 'ai-website-chatbot'); ?></h3>
            <p><?php _e('Hands-free conversations with voice input, text-to-speech, and full audio mode support.', 'ai-website-chatbot'); ?></p>
            <ul class="feature-benefits">
                <li><?php _e('Voice input recognition', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Text-to-speech responses', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Full audio conversation mode', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Voice commands support', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Select Voices from various male/female voices', 'ai-website-chatbot'); ?></li>
            </ul>
        </div>
        
        <div class="pro-feature-card">
            <div class="feature-icon">
                <span class="dashicons dashicons-megaphone"></span>
            </div>
            <h3><?php _e('Advance Analytics', 'ai-website-chatbot'); ?></h3>
            <p><?php _e('Enhanced training capabilities with csv file uploads and bulk imports.', 'ai-website-chatbot'); ?></p>
            <ul class="feature-benefits">
                <li><?php _e('Advanced data visualization', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Custom date range filtering', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Peak usage hours analysis', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Conversation trends & patterns', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Real-time conversation analytics', 'ai-website-chatbot'); ?></li>
                
            </ul>
        </div>

        <div class="pro-feature-card">
            <div class="feature-icon">
                <span class="dashicons dashicons-welcome-learn-more"></span>
            </div>
            <h3><?php _e('Additional Advancements', 'ai-website-chatbot'); ?></h3>
            <p><?php _e('Enhanced training capabilities with csv file uploads and bulk imports.', 'ai-website-chatbot'); ?></p>
            <ul class="feature-benefits">
                <li><?php _e('CSV imports Q&A', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Training data analytics', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Remove "Powered by" branding', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Full customization freedom', 'ai-website-chatbot'); ?></li>
            </ul>
        </div>
        
        <!-- Priority Support -->
        <div class="pro-feature-card">
            <div class="feature-icon">
                <span class="dashicons dashicons-sos"></span>
            </div>
            <h3><?php _e('Priority Support', 'ai-website-chatbot'); ?></h3>
            <p><?php _e('Get dedicated support with faster response times and direct developer access.', 'ai-website-chatbot'); ?></p>
            <ul class="feature-benefits">
                <li><?php _e('24-hour response time', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Email & live chat support', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Custom feature requests', 'ai-website-chatbot'); ?></li>
                <li><?php _e('Setup assistance', 'ai-website-chatbot'); ?></li>
            </ul>
        </div>
        
    </div>
    
    <div class="pro-cta-section">
        <h2><?php _e('Ready to Upgrade?', 'ai-website-chatbot'); ?></h2>
        <p><?php _e('Join hundreds of businesses using Pro features to deliver exceptional customer experiences.', 'ai-website-chatbot'); ?></p>
        <a href="#" class="button button-primary button-hero"><?php _e('Get Pro Now', 'ai-website-chatbot'); ?></a>
    </div>
</div>

<style>
.ai-chatbot-pro-page {
    max-width: 1400px;
}

.pro-features-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 40px;
    border-radius: 12px;
    margin: 20px 0 40px;
    text-align: center;
}

.hero-content h2 {
    color: white;
    font-size: 32px;
    margin-bottom: 15px;
}

.hero-description {
    font-size: 18px;
    margin-bottom: 30px;
    opacity: 0.95;
}

.button-hero {
    font-size: 18px;
    padding: 12px 40px;
    height: auto;
}

.pro-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin: 40px 0;
}

.pro-feature-card {
    background: white;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.pro-feature-card:hover {
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    transform: translateY(-4px);
}

.feature-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.feature-icon .dashicons {
    font-size: 20px;
    color: white;
}

.pro-feature-card h3 {
    margin: 0 0 15px;
    font-size: 20px;
    color: #1d2327;
}

.pro-feature-card > p {
    color: #50575e;
    margin-bottom: 20px;
    line-height: 1.6;
}

.feature-benefits {
    list-style: none;
    padding: 0;
    margin: 0;
}

.feature-benefits li {
    padding: 8px 0;
    padding-left: 25px;
    position: relative;
    color: #50575e;
    font-size: 15px;
    line-height: 1;
}

.feature-benefits li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #46b450;
    font-weight: bold;
}

.pro-cta-section {
    background: #f6f7f7;
    padding: 60px 40px;
    text-align: center;
    border-radius: 12px;
    margin-top: 40px;
}

.pro-cta-section h2 {
    margin-bottom: 15px;
    font-size: 28px;
}

.pro-cta-section p {
    font-size: 16px;
    color: #50575e;
    margin-bottom: 25px;
}

@media (max-width: 768px) {
    .pro-features-grid {
        grid-template-columns: 1fr;
    }
    
    .pro-features-hero {
        padding: 40px 20px;
    }
}
</style>