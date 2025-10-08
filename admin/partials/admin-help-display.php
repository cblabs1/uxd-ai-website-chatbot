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

$settings = get_option('ai_chatbot_settings', array());
?>

<div class="wrap ai-chatbot-help-page">
    <h1><?php _e('Help & Support', 'ai-website-chatbot'); ?></h1>
    
    <div class="ai-chatbot-help-container">
        
        <!-- Quick Start Guide -->
        <div class="help-section">
            <div class="help-card">
                <div class="help-card-header">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                    <h2><?php _e('Quick Start Guide', 'ai-website-chatbot'); ?></h2>
                </div>
                <div class="help-card-body">
                    <ol>
                        <li>
                            <strong><?php _e('Configure AI Provider', 'ai-website-chatbot'); ?></strong>
                            <p><?php _e('Go to Settings and select your AI provider (OpenAI, Claude, or Gemini). Add your API key.', 'ai-website-chatbot'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Train Your Chatbot', 'ai-website-chatbot'); ?></strong>
                            <p><?php _e('Visit the Training page to add custom Q&A pairs and sync your website content.', 'ai-website-chatbot'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Customize Appearance', 'ai-website-chatbot'); ?></strong>
                            <p><?php _e('Adjust colors, position, and welcome message in the Display Settings.', 'ai-website-chatbot'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Enable the Widget', 'ai-website-chatbot'); ?></strong>
                            <p><?php _e('Make sure "Enable Chatbot" is turned on in General Settings.', 'ai-website-chatbot'); ?></p>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
        
        <!-- Documentation -->
        <div class="help-section">
            <div class="help-card">
                <div class="help-card-header">
                    <span class="dashicons dashicons-book"></span>
                    <h2><?php _e('Documentation', 'ai-website-chatbot'); ?></h2>
                </div>
                <div class="help-card-body">
                    <div class="help-links">
                        <a href="#" class="help-link" target="_blank">
                            <span class="dashicons dashicons-media-document"></span>
                            <?php _e('Full Documentation', 'ai-website-chatbot'); ?>
                        </a>
                        <a href="#" class="help-link" target="_blank">
                            <span class="dashicons dashicons-video-alt3"></span>
                            <?php _e('Video Tutorials', 'ai-website-chatbot'); ?>
                        </a>
                        <a href="#" class="help-link" target="_blank">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php _e('API Integration Guide', 'ai-website-chatbot'); ?>
                        </a>
                        <a href="#" class="help-link" target="_blank">
                            <span class="dashicons dashicons-art"></span>
                            <?php _e('Customization Guide', 'ai-website-chatbot'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Common Issues -->
        <div class="help-section">
            <div class="help-card">
                <div class="help-card-header">
                    <span class="dashicons dashicons-sos"></span>
                    <h2><?php _e('Common Issues & Solutions', 'ai-website-chatbot'); ?></h2>
                </div>
                <div class="help-card-body">
                    <div class="faq-item">
                        <h3><?php _e('Chatbot not appearing on the website?', 'ai-website-chatbot'); ?></h3>
                        <p><?php _e('Check: 1) Enable Chatbot is ON in Settings, 2) API key is configured, 3) "Show on Pages" is set correctly.', 'ai-website-chatbot'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h3><?php _e('API connection failed?', 'ai-website-chatbot'); ?></h3>
                        <p><?php _e('Verify your API key is correct and has sufficient credits. Check the system status in Dashboard.', 'ai-website-chatbot'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h3><?php _e('Chatbot responses are slow?', 'ai-website-chatbot'); ?></h3>
                        <p><?php _e('Enable response caching in Advanced Settings. Consider reducing max_tokens if responses are too long.', 'ai-website-chatbot'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h3><?php _e('Training data not working?', 'ai-website-chatbot'); ?></h3>
                        <p><?php _e('Make sure your Q&A pairs are set to "Active" status. Try clearing the cache if changes are not reflecting.', 'ai-website-chatbot'); ?></p>
                    </div>
                    
                    <div class="faq-item">
                        <h3><?php _e('Conversation history not loading?', 'ai-website-chatbot'); ?></h3>
                        <p><?php _e('Check browser console for JavaScript errors. Ensure session cookies are enabled in user browsers.', 'ai-website-chatbot'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="help-section">
            <div class="help-card">
                <div class="help-card-header">
                    <span class="dashicons dashicons-info"></span>
                    <h2><?php _e('System Information', 'ai-website-chatbot'); ?></h2>
                </div>
                <div class="help-card-body">
                    <table class="system-info-table">
                        <tr>
                            <td><strong><?php _e('Plugin Version:', 'ai-website-chatbot'); ?></strong></td>
                            <td><?php echo AI_CHATBOT_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('WordPress Version:', 'ai-website-chatbot'); ?></strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('PHP Version:', 'ai-website-chatbot'); ?></strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Active AI Provider:', 'ai-website-chatbot'); ?></strong></td>
                            <td><?php echo ucfirst($settings['ai_provider'] ?? 'Not Configured'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Database Tables:', 'ai-website-chatbot'); ?></strong></td>
                            <td>
                                <?php
                                global $wpdb;
                                $tables = array(
                                    $wpdb->prefix . 'ai_chatbot_conversations',
                                    $wpdb->prefix . 'ai_chatbot_users',
                                    $wpdb->prefix . 'ai_chatbot_training'
                                );
                                $existing = 0;
                                foreach ($tables as $table) {
                                    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                                        $existing++;
                                    }
                                }
                                echo $existing . ' / ' . count($tables) . ' ' . __('installed', 'ai-website-chatbot');
                                ?>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="button" class="button" onclick="copySystemInfo()">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php _e('Copy System Info', 'ai-website-chatbot'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Support Contact -->
        <div class="help-section">
            <div class="help-card">
                <div class="help-card-header">
                    <span class="dashicons dashicons-email"></span>
                    <h2><?php _e('Contact Support', 'ai-website-chatbot'); ?></h2>
                </div>
                <div class="help-card-body">
                    <p><?php _e('Need additional help? Our support team is here to assist you.', 'ai-website-chatbot'); ?></p>
                    
                    <div class="support-options">
                        <a href="mailto:support@example.com" class="button button-primary">
                            <span class="dashicons dashicons-email"></span>
                            <?php _e('Email Support', 'ai-website-chatbot'); ?>
                        </a>
                        
                        <a href="https://wordpress.org/support/plugin/ai-website-chatbot/" class="button" target="_blank">
                            <span class="dashicons dashicons-wordpress"></span>
                            <?php _e('WordPress Forum', 'ai-website-chatbot'); ?>
                        </a>
                        
                        <a href="https://github.com/yourname/ai-website-chatbot/issues" class="button" target="_blank">
                            <span class="dashicons dashicons-editor-code"></span>
                            <?php _e('Report Bug on GitHub', 'ai-website-chatbot'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
function copySystemInfo() {
    const systemInfo = document.querySelector('.system-info-table').innerText;
    navigator.clipboard.writeText(systemInfo).then(() => {
        alert('<?php _e('System information copied to clipboard!', 'ai-website-chatbot'); ?>');
    });
}
</script>