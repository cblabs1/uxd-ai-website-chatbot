<?php
/**
 * Help & Support Page Display 
 *
 * @package AI_Website_Chatbot
 * @subpackage Admin/Partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<style>
/* Modern Help Page Styles */
.ai-chatbot-help-page {
    background: #f8f9fa;
    padding: 0;
}

.ai-chatbot-help-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 40px;
    margin: -10px -20px 40px -20px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.help-hero-content {
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
}

.help-hero-content .notice{
    display: none!important;
}

.help-hero-content h1 {
    font-size: 42px;
    font-weight: 700;
    margin: 0 0 15px 0;
    color: white;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.help-hero-content p {
    font-size: 18px;
    opacity: 0.95;
    margin: 0;
    line-height: 1.6;
}

.ai-chatbot-help-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px 40px;
}

.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.help-card {
    background: white;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
}

.help-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.help-card-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 25px;
}

.help-card-icon .dashicons {
    font-size: 35px;
    width: 35px;
    height: 35px;
    color: white;
}

.help-card h2 {
    font-size: 24px;
    font-weight: 600;
    margin: 0 0 15px 0;
    color: #1f2937;
}

.help-card p {
    font-size: 15px;
    color: #6b7280;
    line-height: 1.7;
    margin-bottom: 25px;
}

.help-steps {
    list-style: none;
    padding: 0;
    margin: 0;
}

.help-steps li {
    padding: 20px;
    margin-bottom: 15px;
    background: #f9fafb;
    border-radius: 12px;
    border-left: 4px solid #667eea;
    transition: all 0.2s ease;
}

.help-steps li:hover {
    background: #f3f4f6;
    transform: translateX(5px);
}

.help-steps li strong {
    display: block;
    color: #1f2937;
    font-size: 16px;
    margin-bottom: 8px;
}

.help-steps li p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.6;
}

.help-steps li .step-number {
    display: inline-block;
    width: 28px;
    height: 28px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 28px;
    font-weight: 600;
    font-size: 14px;
    margin-right: 12px;
}

.faq-section {
    margin-top: 30px;
}

.faq-item {
    background: #f9fafb;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 15px;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
}

.faq-item:hover {
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.faq-item h3 {
    color: #1f2937;
    font-size: 17px;
    font-weight: 600;
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
}

.faq-item h3:before {
    content: "Q:";
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 13px;
    margin-right: 12px;
    font-weight: 700;
}

.faq-item p {
    color: #6b7280;
    font-size: 15px;
    line-height: 1.7;
    margin: 0;
    padding-left: 35px;
}

.support-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 50px;
    text-align: center;
    color: white;
    margin-top: 40px;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.support-card h2 {
    font-size: 32px;
    font-weight: 700;
    margin: 0 0 15px 0;
    color: white;
}

.support-card p {
    font-size: 18px;
    margin-bottom: 35px;
    opacity: 0.95;
}

.support-email {
    display: inline-flex;
    align-items: center;
    background: white;
    color: #667eea;
    padding: 18px 40px;
    border-radius: 50px;
    font-size: 18px;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.support-email:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    color: #764ba2;
    text-decoration: none;
}

.support-email .dashicons {
    margin-right: 12px;
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.feature-badge {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 20px;
    backdrop-filter: blur(10px);
}

@media (max-width: 768px) {
    .help-grid {
        grid-template-columns: 1fr;
    }
    
    .ai-chatbot-help-hero {
        padding: 40px 20px;
    }
    
    .help-hero-content h1 {
        font-size: 32px;
    }
    
    .support-card {
        padding: 30px 20px;
    }
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.help-card, .faq-item {
    animation: fadeInUp 0.6s ease;
}

.help-card:nth-child(2) {
    animation-delay: 0.1s;
}

.help-card:nth-child(3) {
    animation-delay: 0.2s;
}
</style>

<div class="wrap ai-chatbot-help-page">
    
    <!-- Hero Section -->
    <div class="ai-chatbot-help-hero">
        <div class="help-hero-content">
            <div class="feature-badge">✨ Help Center</div>
            <h1><?php _e('How Can We Help You?', 'ai-website-chatbot'); ?></h1>
            <p><?php _e('Get started quickly with our comprehensive guide and support resources', 'ai-website-chatbot'); ?></p>
        </div>
    </div>
    
    <div class="ai-chatbot-help-container">
        
        <!-- Main Help Cards -->
        <div class="help-grid">
            
            <!-- Quick Start Guide -->
            <div class="help-card">
                <div class="help-card-icon">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                </div>
                <h2><?php _e('Quick Start Guide', 'ai-website-chatbot'); ?></h2>
                <p><?php _e('Follow these simple steps to get your AI chatbot up and running in minutes', 'ai-website-chatbot'); ?></p>
                
                <ul class="help-steps">
                    <li>
                        <span class="step-number">1</span>
                        <strong><?php _e('Configure AI Provider', 'ai-website-chatbot'); ?></strong>
                        <p><?php _e('Select your preferred AI provider (OpenAI, Claude, or Gemini) and add your API key in Settings.', 'ai-website-chatbot'); ?></p>
                    </li>
                    <li>
                        <span class="step-number">2</span>
                        <strong><?php _e('Train Your Chatbot', 'ai-website-chatbot'); ?></strong>
                        <p><?php _e('Add custom Q&A pairs and sync your website content to help your chatbot provide accurate responses.', 'ai-website-chatbot'); ?></p>
                    </li>
                    <li>
                        <span class="step-number">3</span>
                        <strong><?php _e('Customize Appearance', 'ai-website-chatbot'); ?></strong>
                        <p><?php _e('Adjust colors, position, and welcome messages to match your brand perfectly.', 'ai-website-chatbot'); ?></p>
                    </li>
                    <li>
                        <span class="step-number">4</span>
                        <strong><?php _e('Go Live', 'ai-website-chatbot'); ?></strong>
                        <p><?php _e('Enable the chatbot in General Settings and watch it engage with your visitors!', 'ai-website-chatbot'); ?></p>
                    </li>
                </ul>
            </div>
            
            <!-- Common Issues -->
            <div class="help-card">
                <div class="help-card-icon">
                    <span class="dashicons dashicons-sos"></span>
                </div>
                <h2><?php _e('Common Issues & Solutions', 'ai-website-chatbot'); ?></h2>
                <p><?php _e('Quick answers to frequently encountered problems', 'ai-website-chatbot'); ?></p>
                
                <div class="faq-section">
                    <div class="faq-item">
                        <h3><?php _e('Chatbot not appearing on website?', 'ai-website-chatbot'); ?></h3>
                        <p><?php _e('Check that "Enable Chatbot" is ON in Settings, your API key is configured correctly, and "Show on Pages" settings are properly configured.', 'ai-website-chatbot'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h3><?php _e('API key not working?', 'ai-website-chatbot'); ?></h3>
                        <p><?php _e('Verify your API key is correct and has not expired. Make sure you have sufficient credits with your AI provider. Try testing the connection in Settings.', 'ai-website-chatbot'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h3><?php _e('Slow response times?', 'ai-website-chatbot'); ?></h3>
                        <p><?php _e('Response speed depends on your AI provider and model selection. Try switching to a faster model like GPT-3.5 Turbo or Gemini Flash for quicker responses.', 'ai-website-chatbot'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h3><?php _e('Conversations not being saved?', 'ai-website-chatbot'); ?></h3>
                        <p><?php _e('Ensure the plugin has proper database permissions. Check if your hosting provider allows WordPress to create and modify database tables.', 'ai-website-chatbot'); ?></p>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Support Contact Card -->
        <div class="support-card">
            <h2><?php _e('Need More Help?', 'ai-website-chatbot'); ?></h2>
            <p><?php _e('Our support team is ready to assist you with any questions or issues', 'ai-website-chatbot'); ?></p>
            
            <a href="mailto:help@uxdesignexperts.com" class="support-email">
                <span class="dashicons dashicons-email-alt"></span>
                help@uxdesignexperts.com
            </a>
            
            <p style="margin-top: 25px; font-size: 15px; opacity: 0.9;">
                <?php _e('We typically respond within 24 hours on business days', 'ai-website-chatbot'); ?>
            </p>
        </div>
        
    </div>
</div>