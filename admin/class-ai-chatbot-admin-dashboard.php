<?php

/**
 * AI Chatbot Admin Dashboard
 *
 * @since 1.0.0
 */
class AI_Chatbot_Admin_Dashboard {

    /**
     * Plugin name
     *
     * @var string
     * @since 1.0.0
     */
    private $plugin_name;

    /**
     * Plugin version
     *
     * @var string
     * @since 1.0.0
     */
    private $version;

    /**
     * Constructor
     *
     * @param string $plugin_name Plugin name.
     * @param string $version Plugin version.
     * @since 1.0.0
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Add dashboard widgets - MUST be hooked to wp_dashboard_setup
     *
     * @since 1.0.0
     */
    public function add_dashboard_widgets() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'ai_chatbot_dashboard_widget',
                __('AI Chatbot Overview', 'ai-website-chatbot'),
                array($this, 'dashboard_widget_content')
            );
        }
    }

    /**
     * Dashboard widget content
     *
     * @since 1.0.0
     */
    public function dashboard_widget_content() {
        $stats = $this->get_dashboard_stats();
        
        // Display widget content
        echo '<div class="ai-chatbot-dashboard-widget">';
        echo '<div class="chatbot-stats">';
        echo '<h4>' . __('Quick Stats', 'ai-website-chatbot') . '</h4>';
        echo '<p>' . sprintf(__('Total Conversations: %d', 'ai-website-chatbot'), $stats['total_conversations'] ?? 0) . '</p>';
        echo '<p>' . sprintf(__('Today: %d', 'ai-website-chatbot'), $stats['today_conversations'] ?? 0) . '</p>';
        echo '<p>' . sprintf(__('This Week: %d', 'ai-website-chatbot'), $stats['week_conversations'] ?? 0) . '</p>';
        echo '</div>';
        
        echo '<div class="chatbot-status">';
        $status = $this->get_chatbot_status();
        $status_class = $status['online'] ? 'online' : 'offline';
        echo '<h4>' . __('Chatbot Status', 'ai-website-chatbot') . '</h4>';
        echo '<p class="status-indicator ' . $status_class . '">';
        echo $status['online'] ? __('Online', 'ai-website-chatbot') : __('Offline', 'ai-website-chatbot');
        echo '</p>';
        echo '</div>';
        
        echo '<div class="chatbot-actions">';
        echo '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-settings') . '" class="button">' . __('Settings', 'ai-website-chatbot') . '</a>';
        echo ' <a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-analytics') . '" class="button">' . __('Analytics', 'ai-website-chatbot') . '</a>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Get dashboard statistics
     *
     * @return array Statistics data.
     * @since 1.0.0
     */
    private function get_dashboard_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        $stats = array();
        
        // Total conversations
        $stats['total_conversations'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Today's conversations
        $stats['today_conversations'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) = %s",
            current_time('Y-m-d')
        ));
        
        // This week's conversations
        $stats['week_conversations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)"
        );
        
        return $stats;
    }

    /**
     * Get chatbot status
     *
     * @return array Status information.
     * @since 1.0.0
     */
    private function get_chatbot_status() {
        $is_enabled = get_option('ai_chatbot_enabled', false);
        $provider = get_option('ai_chatbot_provider', 'openai');
        
        return array(
            'online' => $is_enabled,
            'provider' => $provider,
            'message' => $is_enabled ? __('Chatbot is online', 'ai-website-chatbot') : __('Chatbot is disabled', 'ai-website-chatbot')
        );
    }
}
