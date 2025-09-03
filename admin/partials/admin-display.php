<?php
/**
 * Admin Settings Display Template
 *
 * @package AI_Website_Chatbot
 * @subpackage Admin/Partials
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wrap ai-chatbot-admin">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'AI Website Chatbot', 'ai-website-chatbot' ); ?>
		<span class="title-version">v<?php echo esc_html( AI_CHATBOT_VERSION ); ?></span>
	</h1>

	<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully!', 'ai-website-chatbot' ); ?></p>
		</div>
	<?php endif; ?>

	<?php settings_errors(); ?>

	<div class="ai-chatbot-admin-header">
		<div class="ai-chatbot-status-card">
			<div class="status-indicator <?php echo $this->is_properly_configured() ? 'status-online' : 'status-offline'; ?>">
				<span class="status-dot"></span>
				<span class="status-text">
					<?php echo $this->is_properly_configured() ? esc_html__( 'Online', 'ai-website-chatbot' ) : esc_html__( 'Offline', 'ai-website-chatbot' ); ?>
				</span>
			</div>
			<div class="status-info">
				<strong><?php esc_html_e( 'Chatbot Status', 'ai-website-chatbot' ); ?></strong>
				<p>
					<?php if ( $this->is_properly_configured() ) : ?>
						<?php esc_html_e( 'Your chatbot is configured and ready to help visitors.', 'ai-website-chatbot' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Please configure your AI provider to activate the chatbot.', 'ai-website-chatbot' ); ?>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<div class="ai-chatbot-quick-stats">
			<?php
			$database = new AI_Chatbot_Database();
			$today_stats = $database->get_conversation_stats( 'day' );
			$total_conversations = $today_stats['total_conversations'] ?? 0;
			$unique_sessions = $today_stats['unique_sessions'] ?? 0;
			?>
			<div class="stat-item">
				<div class="stat-number"><?php echo esc_html( $total_conversations ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Conversations Today', 'ai-website-chatbot' ); ?></div>
			</div>
			<div class="stat-item">
				<div class="stat-number"><?php echo esc_html( $unique_sessions ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Unique Visitors Today', 'ai-website-chatbot' ); ?></div>
			</div>
		</div>
	</div>

	<div class="ai-chatbot-admin-content">
		<div class="ai-chatbot-settings-nav">
			<button class="nav-tab nav-tab-active" data-tab="general">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'General', 'ai-website-chatbot' ); ?>
			</button>
			<button class="nav-tab" data-tab="ai-config">
				<span class="dashicons dashicons-admin-network"></span>
				<?php esc_html_e( 'AI Configuration', 'ai-website-chatbot' ); ?>
			</button>
			<button class="nav-tab" data-tab="display">
				<span class="dashicons dashicons-admin-appearance"></span>
				<?php esc_html_e( 'Display', 'ai-website-chatbot' ); ?>
			</button>
			<button class="nav-tab" data-tab="privacy">
				<span class="dashicons dashicons-privacy"></span>
				<?php esc_html_e( 'Privacy', 'ai-website-chatbot' ); ?>
			</button>
			<button class="nav-tab" data-tab="advanced">
				<span class="dashicons dashicons-admin-tools"></span>
				<?php esc_html_e( 'Advanced', 'ai-website-chatbot' ); ?>
			</button>
		</div>

		<form method="post" action="" class="ai-chatbot-settings-form">
			<?php wp_nonce_field( 'ai_chatbot_settings', 'ai_chatbot_nonce' ); ?>

			<!-- General Settings Tab -->
			<div class="tab-content" id="tab-general">
				<div class="settings-section">
					<h2><?php esc_html_e( 'General Settings', 'ai-website-chatbot' ); ?></h2>
					<p class="section-description">
						<?php esc_html_e( 'Configure the basic settings for your AI chatbot.', 'ai-website-chatbot' ); ?>
					</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="ai_chatbot_enabled">
									<?php esc_html_e( 'Enable Chatbot', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" 
											   name="ai_chatbot_enabled" 
											   id="ai_chatbot_enabled" 
											   value="1" 
											   <?php checked( $current_settings['ai_chatbot_enabled'] ?? false ); ?> />
										<?php esc_html_e( 'Enable the AI chatbot on your website', 'ai-website-chatbot' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_chatbot_widget_title">
									<?php esc_html_e( 'Widget Title', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<input type="text" 
									   name="ai_chatbot_widget_title" 
									   id="ai_chatbot_widget_title" 
									   value="<?php echo esc_attr( $current_settings['ai_chatbot_widget_title'] ?? '' ); ?>" 
									   class="regular-text" />
								<p class="description">
									<?php esc_html_e( 'Title displayed in the chatbot header.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_chatbot_welcome_message">
									<?php esc_html_e( 'Welcome Message', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<textarea name="ai_chatbot_welcome_message" 
										  id="ai_chatbot_welcome_message" 
										  rows="3" 
										  class="large-text"><?php echo esc_textarea( $current_settings['ai_chatbot_welcome_message'] ?? '' ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Initial message shown to users when they open the chatbot.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_chatbot_placeholder_text">
									<?php esc_html_e( 'Input Placeholder', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<input type="text" 
									   name="ai_chatbot_placeholder_text" 
									   id="ai_chatbot_placeholder_text" 
									   value="<?php echo esc_attr( $current_settings['ai_chatbot_placeholder_text'] ?? '' ); ?>" 
									   class="regular-text" />
								<p class="description">
									<?php esc_html_e( 'Placeholder text shown in the message input field.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- AI Configuration Tab -->
			<div class="tab-content" id="tab-ai-config" style="display: none;">
				<div class="settings-section">
					<h2><?php esc_html_e( 'AI Provider Configuration', 'ai-website-chatbot' ); ?></h2>
					<p class="section-description">
						<?php esc_html_e( 'Configure your AI service provider and API credentials.', 'ai-website-chatbot' ); ?>
					</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="ai_chatbot_ai_provider">
									<?php esc_html_e( 'AI Provider', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<select name="ai_chatbot_ai_provider" id="ai_chatbot_ai_provider" class="ai-provider-select">
									<option value="openai" <?php selected( $ai_provider, 'openai' ); ?>>
										<?php esc_html_e( 'OpenAI (ChatGPT)', 'ai-website-chatbot' ); ?>
									</option>
									<option value="claude" <?php selected( $ai_provider, 'claude' ); ?>>
										<?php esc_html_e( 'Anthropic Claude', 'ai-website-chatbot' ); ?>
									</option>
									<option value="gemini" <?php selected( $ai_provider, 'gemini' ); ?>>
										<?php esc_html_e( 'Google Gemini', 'ai-website-chatbot' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select your preferred AI service provider.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<!-- OpenAI Settings -->
					<div class="provider-settings" id="openai-settings" <?php echo $ai_provider !== 'openai' ? 'style="display: none;"' : ''; ?>>
						<h3><?php esc_html_e( 'OpenAI Configuration', 'ai-website-chatbot' ); ?></h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="ai_chatbot_openai_temperature">
										<?php esc_html_e( 'Temperature', 'ai-website-chatbot' ); ?>
									</label>
								</th>
								<td>
									<input type="number" 
										   name="ai_chatbot_openai_temperature" 
										   id="ai_chatbot_openai_temperature" 
										   value="<?php echo esc_attr( $current_settings['ai_chatbot_openai_temperature'] ?? '0.7' ); ?>" 
										   min="0" 
										   max="2" 
										   step="0.1" 
										   class="small-text" />
									<p class="description">
										<?php esc_html_e( 'Controls randomness in responses. Lower values (0.1) make responses more focused, higher values (1.5) more creative.', 'ai-website-chatbot' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="ai_chatbot_openai_max_tokens">
										<?php esc_html_e( 'Max Response Length', 'ai-website-chatbot' ); ?>
									</label>
								</th>
								<td>
									<input type="number" 
										   name="ai_chatbot_openai_max_tokens" 
										   id="ai_chatbot_openai_max_tokens" 
										   value="<?php echo esc_attr( $current_settings['ai_chatbot_openai_max_tokens'] ?? '300' ); ?>" 
										   min="50" 
										   max="4000" 
										   class="small-text" />
									<p class="description">
										<?php esc_html_e( 'Maximum length of AI responses in tokens. 300 tokens ≈ 200-250 words.', 'ai-website-chatbot' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<!-- Claude Settings -->
					<div class="provider-settings" id="claude-settings" <?php echo $ai_provider !== 'claude' ? 'style="display: none;"' : ''; ?>>
						<h3><?php esc_html_e( 'Anthropic Claude Configuration', 'ai-website-chatbot' ); ?></h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="ai_chatbot_claude_api_key">
										<?php esc_html_e( 'API Key', 'ai-website-chatbot' ); ?>
									</label>
								</th>
								<td>
									<input type="password" 
										   name="ai_chatbot_claude_api_key" 
										   id="ai_chatbot_claude_api_key" 
										   value="<?php echo esc_attr( $current_settings['ai_chatbot_claude_api_key'] ?? '' ); ?>" 
										   class="regular-text" 
										   autocomplete="off" />
									<button type="button" class="button test-connection" data-provider="claude">
										<?php esc_html_e( 'Test Connection', 'ai-website-chatbot' ); ?>
									</button>
									<p class="description">
										<?php 
										printf( 
											/* translators: %s: Claude API URL */
											esc_html__( 'Your Anthropic Claude API key. Get one from %s', 'ai-website-chatbot' ),
											'<a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>'
										); 
										?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<!-- Gemini Settings -->
					<div class="provider-settings" id="gemini-settings" <?php echo $ai_provider !== 'gemini' ? 'style="display: none;"' : ''; ?>>
						<h3><?php esc_html_e( 'Google Gemini Configuration', 'ai-website-chatbot' ); ?></h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="ai_chatbot_gemini_api_key">
										<?php esc_html_e( 'API Key', 'ai-website-chatbot' ); ?>
									</label>
								</th>
								<td>
									<input type="password" 
										   name="ai_chatbot_gemini_api_key" 
										   id="ai_chatbot_gemini_api_key" 
										   value="<?php echo esc_attr( $current_settings['ai_chatbot_gemini_api_key'] ?? '' ); ?>" 
										   class="regular-text" 
										   autocomplete="off" />
									<button type="button" class="button test-connection" data-provider="gemini">
										<?php esc_html_e( 'Test Connection', 'ai-website-chatbot' ); ?>
									</button>
									<p class="description">
										<?php 
										printf( 
											/* translators: %s: Google AI Studio URL */
											esc_html__( 'Your Google Gemini API key. Get one from %s', 'ai-website-chatbot' ),
											'<a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>'
										); 
										?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>
			</div>

			<!-- Display Settings Tab -->
			<div class="tab-content" id="tab-display" style="display: none;">
				<div class="settings-section">
					<h2><?php esc_html_e( 'Display & Appearance', 'ai-website-chatbot' ); ?></h2>
					<p class="section-description">
						<?php esc_html_e( 'Customize how the chatbot appears on your website.', 'ai-website-chatbot' ); ?>
					</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="ai_chatbot_position">
									<?php esc_html_e( 'Position', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<select name="ai_chatbot_position" id="ai_chatbot_position">
									<option value="bottom-right" <?php selected( $current_settings['ai_chatbot_position'] ?? '', 'bottom-right' ); ?>>
										<?php esc_html_e( 'Bottom Right', 'ai-website-chatbot' ); ?>
									</option>
									<option value="bottom-left" <?php selected( $current_settings['ai_chatbot_position'] ?? '', 'bottom-left' ); ?>>
										<?php esc_html_e( 'Bottom Left', 'ai-website-chatbot' ); ?>
									</option>
									<option value="top-right" <?php selected( $current_settings['ai_chatbot_position'] ?? '', 'top-right' ); ?>>
										<?php esc_html_e( 'Top Right', 'ai-website-chatbot' ); ?>
									</option>
									<option value="top-left" <?php selected( $current_settings['ai_chatbot_position'] ?? '', 'top-left' ); ?>>
										<?php esc_html_e( 'Top Left', 'ai-website-chatbot' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Choose where to position the chatbot widget on your website.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_chatbot_theme_color">
									<?php esc_html_e( 'Theme Color', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<input type="text" 
									   name="ai_chatbot_theme_color" 
									   id="ai_chatbot_theme_color" 
									   value="<?php echo esc_attr( $current_settings['ai_chatbot_theme_color'] ?? '#0073aa' ); ?>" 
									   class="color-picker" />
								<p class="description">
									<?php esc_html_e( 'Primary color for the chatbot interface.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Visibility Options', 'ai-website-chatbot' ); ?>
							</th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" 
											   name="ai_chatbot_show_on_mobile" 
											   value="1" 
											   <?php checked( $current_settings['ai_chatbot_show_on_mobile'] ?? true ); ?> />
										<?php esc_html_e( 'Show on mobile devices', 'ai-website-chatbot' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" 
											   name="ai_chatbot_show_to_logged_users" 
											   value="1" 
											   <?php checked( $current_settings['ai_chatbot_show_to_logged_users'] ?? true ); ?> />
										<?php esc_html_e( 'Show to logged-in users', 'ai-website-chatbot' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" 
											   name="ai_chatbot_show_to_guests" 
											   value="1" 
											   <?php checked( $current_settings['ai_chatbot_show_to_guests'] ?? true ); ?> />
										<?php esc_html_e( 'Show to guest visitors', 'ai-website-chatbot' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_chatbot_custom_css">
									<?php esc_html_e( 'Custom CSS', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<textarea name="ai_chatbot_custom_css" 
										  id="ai_chatbot_custom_css" 
										  rows="8" 
										  class="large-text code"><?php echo esc_textarea( $current_settings['ai_chatbot_custom_css'] ?? '' ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Add custom CSS to style the chatbot. Use the class prefix .ai-chatbot-widget', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Privacy Settings Tab -->
			<div class="tab-content" id="tab-privacy" style="display: none;">
				<div class="settings-section">
					<h2><?php esc_html_e( 'Privacy & Security', 'ai-website-chatbot' ); ?></h2>
					<p class="section-description">
						<?php esc_html_e( 'Configure privacy settings and data retention policies to comply with GDPR and other privacy regulations.', 'ai-website-chatbot' ); ?>
					</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="ai_chatbot_data_retention_days">
									<?php esc_html_e( 'Data Retention', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<input type="number" 
									   name="ai_chatbot_data_retention_days" 
									   id="ai_chatbot_data_retention_days" 
									   value="<?php echo esc_attr( $current_settings['ai_chatbot_data_retention_days'] ?? '30' ); ?>" 
									   min="0" 
									   max="365" 
									   class="small-text" />
								<span><?php esc_html_e( 'days', 'ai-website-chatbot' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Automatically delete conversation data after this many days. Set to 0 to keep indefinitely.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Data Collection', 'ai-website-chatbot' ); ?>
							</th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" 
											   name="ai_chatbot_collect_ip" 
											   value="1" 
											   <?php checked( $current_settings['ai_chatbot_collect_ip'] ?? false ); ?> />
										<?php esc_html_e( 'Collect anonymized IP addresses', 'ai-website-chatbot' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" 
											   name="ai_chatbot_collect_user_agent" 
											   value="1" 
											   <?php checked( $current_settings['ai_chatbot_collect_user_agent'] ?? false ); ?> />
										<?php esc_html_e( 'Collect browser information', 'ai-website-chatbot' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" 
											   name="ai_chatbot_enable_rating" 
											   value="1" 
											   <?php checked( $current_settings['ai_chatbot_enable_rating'] ?? true ); ?> />
										<?php esc_html_e( 'Allow users to rate responses', 'ai-website-chatbot' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_chatbot_rate_limit_per_minute">
									<?php esc_html_e( 'Rate Limiting', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<input type="number" 
									   name="ai_chatbot_rate_limit_per_minute" 
									   id="ai_chatbot_rate_limit_per_minute" 
									   value="<?php echo esc_attr( $current_settings['ai_chatbot_rate_limit_per_minute'] ?? '10' ); ?>" 
									   min="1" 
									   max="100" 
									   class="small-text" />
								<span><?php esc_html_e( 'messages per minute', 'ai-website-chatbot' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Limit the number of messages per user to prevent abuse.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_chatbot_max_message_length">
									<?php esc_html_e( 'Message Length Limit', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<input type="number" 
									   name="ai_chatbot_max_message_length" 
									   id="ai_chatbot_max_message_length" 
									   value="<?php echo esc_attr( $current_settings['ai_chatbot_max_message_length'] ?? '1000' ); ?>" 
									   min="100" 
									   max="5000" 
									   class="small-text" />
								<span><?php esc_html_e( 'characters', 'ai-website-chatbot' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Maximum length of user messages.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Advanced Settings Tab -->
			<div class="tab-content" id="tab-advanced" style="display: none;">
				<div class="settings-section">
					<h2><?php esc_html_e( 'Advanced Settings', 'ai-website-chatbot' ); ?></h2>
					<p class="section-description">
						<?php esc_html_e( 'Advanced configuration options for power users.', 'ai-website-chatbot' ); ?>
					</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="ai_chatbot_custom_prompt">
									<?php esc_html_e( 'Custom System Prompt', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<textarea name="ai_chatbot_custom_prompt" 
										  id="ai_chatbot_custom_prompt" 
										  rows="6" 
										  class="large-text"><?php echo esc_textarea( $current_settings['ai_chatbot_custom_prompt'] ?? '' ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Override the default system prompt. Leave empty to use the default behavior-defining prompt.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_chatbot_fallback_message">
									<?php esc_html_e( 'Fallback Message', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<textarea name="ai_chatbot_fallback_message" 
										  id="ai_chatbot_fallback_message" 
										  rows="3" 
										  class="large-text"><?php echo esc_textarea( $current_settings['ai_chatbot_fallback_message'] ?? '' ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Message shown when the AI cannot understand the user query.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_chatbot_error_message">
									<?php esc_html_e( 'Error Message', 'ai-website-chatbot' ); ?>
								</label>
							</th>
							<td>
								<textarea name="ai_chatbot_error_message" 
										  id="ai_chatbot_error_message" 
										  rows="3" 
										  class="large-text"><?php echo esc_textarea( $current_settings['ai_chatbot_error_message'] ?? '' ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Message shown when there are technical difficulties.', 'ai-website-chatbot' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Debug Options', 'ai-website-chatbot' ); ?>
							</th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" 
											   name="ai_chatbot_enable_debug" 
											   value="1" 
											   <?php checked( $current_settings['ai_chatbot_enable_debug'] ?? false ); ?> />
										<?php esc_html_e( 'Enable debug logging', 'ai-website-chatbot' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Log detailed information for troubleshooting. Only enable when needed.', 'ai-website-chatbot' ); ?>
									</p>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="ai-chatbot-submit-section">
				<p class="submit">
					<?php submit_button( null, 'primary', 'submit', false ); ?>
					<span class="spinner"></span>
				</p>
			</div>
		</form>
	</div>

	<div class="ai-chatbot-admin-sidebar">
		<div class="sidebar-widget">
			<h3><?php esc_html_e( 'Quick Actions', 'ai-website-chatbot' ); ?></h3>
			<div class="quick-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-chatbot-analytics' ) ); ?>" class="button">
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e( 'View Analytics', 'ai-website-chatbot' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-chatbot-conversations' ) ); ?>" class="button">
					<span class="dashicons dashicons-format-chat"></span>
					<?php esc_html_e( 'View Conversations', 'ai-website-chatbot' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-chatbot-training' ) ); ?>" class="button">
					<span class="dashicons dashicons-welcome-learn-more"></span>
					<?php esc_html_e( 'Manage Training', 'ai-website-chatbot' ); ?>
				</a>
			</div>
		</div>

		<div class="sidebar-widget">
			<h3><?php esc_html_e( 'Support', 'ai-website-chatbot' ); ?></h3>
			<ul>
				<li><a href="#" target="_blank"><?php esc_html_e( 'Documentation', 'ai-website-chatbot' ); ?></a></li>
				<li><a href="#" target="_blank"><?php esc_html_e( 'Support Forum', 'ai-website-chatbot' ); ?></a></li>
				<li><a href="#" target="_blank"><?php esc_html_e( 'Rate Plugin', 'ai-website-chatbot' ); ?></a></li>
			</ul>
		</div>

		<div class="sidebar-widget">
			<h3><?php esc_html_e( 'System Info', 'ai-website-chatbot' ); ?></h3>
			<ul class="system-info">
				<li>
					<strong><?php esc_html_e( 'Plugin Version:', 'ai-website-chatbot' ); ?></strong>
					<?php echo esc_html( AI_CHATBOT_VERSION ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'WordPress:', 'ai-website-chatbot' ); ?></strong>
					<?php echo esc_html( get_bloginfo( 'version' ) ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'PHP:', 'ai-website-chatbot' ); ?></strong>
					<?php echo esc_html( PHP_VERSION ); ?>
				</li>
			</ul>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Tab navigation
	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		const tab = $(this).data('tab');
		
		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		
		$('.tab-content').hide();
		$('#tab-' + tab).show();
	});

	// Provider selection
	$('#ai_chatbot_ai_provider').on('change', function() {
		const provider = $(this).val();
		$('.provider-settings').hide();
		$('#' + provider + '-settings').show();
	});

	// Color picker
	if ($.fn.wpColorPicker) {
		$('.color-picker').wpColorPicker();
	}

	// Test connection
	$('.test-connection').on('click', function() {
		const button = $(this);
		const provider = button.data('provider');
		const statusDiv = button.siblings('.connection-status');
		
		button.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'ai-website-chatbot' ) ); ?>');
		statusDiv.removeClass('success error').empty();
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ai_chatbot_test_connection',
				nonce: '<?php echo esc_js( wp_create_nonce( 'ai_chatbot_admin_nonce' ) ); ?>',
				provider: provider
			},
			success: function(response) {
				if (response.success) {
					statusDiv.addClass('success').text('<?php echo esc_js( __( 'Connection successful!', 'ai-website-chatbot' ) ); ?>');
				} else {
					statusDiv.addClass('error').text(response.data.message || '<?php echo esc_js( __( 'Connection failed', 'ai-website-chatbot' ) ); ?>');
				}
			},
			error: function() {
				statusDiv.addClass('error').text('<?php echo esc_js( __( 'Network error', 'ai-website-chatbot' ) ); ?>');
			},
			complete: function() {
				button.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'ai-website-chatbot' ) ); ?>');
			}
		});
	});

	// Form validation
	$('.ai-chatbot-settings-form').on('submit', function(e) {
		const provider = $('#ai_chatbot_ai_provider').val();
		const apiKey = $('#ai_chatbot_' + provider + '_api_key').val();
		const enabled = $('#ai_chatbot_enabled').is(':checked');
		
		if (enabled && !apiKey) {
			alert('<?php echo esc_js( __( 'Please enter an API key for your selected AI provider.', 'ai-website-chatbot' ) ); ?>');
			e.preventDefault();
			return false;
		}

		// Show loading spinner
		$('.spinner').addClass('is-active');
		$('.submit .button-primary').prop('disabled', true);
	});

	// Auto-save indicator
	let saveTimeout;
	$('input, textarea, select').on('change', function() {
		clearTimeout(saveTimeout);
		$('.settings-form').addClass('unsaved-changes');
		
		saveTimeout = setTimeout(function() {
			$('.settings-form').removeClass('unsaved-changes');
		}, 2000);
	});

	// Warn about unsaved changes
	$(window).on('beforeunload', function() {
		if ($('.settings-form').hasClass('unsaved-changes')) {
			return '<?php echo esc_js( __( 'You have unsaved changes. Are you sure you want to leave?', 'ai-website-chatbot' ) ); ?>';
		}
	});

	// Advanced settings toggle
	$('#show-advanced-settings').on('click', function() {
		$('.advanced-settings').toggle();
		$(this).text(function(i, text) {
			return text === '<?php echo esc_js( __( 'Show Advanced', 'ai-website-chatbot' ) ); ?>' ? 
				   '<?php echo esc_js( __( 'Hide Advanced', 'ai-website-chatbot' ) ); ?>' : 
				   '<?php echo esc_js( __( 'Show Advanced', 'ai-website-chatbot' ) ); ?>';
		});
	});

	// Settings import/export
	$('#export-settings').on('click', function() {
		const settings = {};
		$('input, textarea, select').each(function() {
			const name = $(this).attr('name');
			if (name && name.indexOf('ai_chatbot_') === 0) {
				if ($(this).attr('type') === 'checkbox') {
					settings[name] = $(this).is(':checked');
				} else {
					settings[name] = $(this).val();
				}
			}
		});

		// Remove sensitive data
		delete settings.ai_chatbot_openai_api_key;
		delete settings.ai_chatbot_claude_api_key;
		delete settings.ai_chatbot_gemini_api_key;

		const dataStr = JSON.stringify(settings, null, 2);
		const dataBlob = new Blob([dataStr], {type: 'application/json'});
		const url = URL.createObjectURL(dataBlob);
		
		const link = document.createElement('a');
		link.href = url;
		link.download = 'ai-chatbot-settings-' + new Date().toISOString().split('T')[0] + '.json';
		link.click();
		
		URL.revokeObjectURL(url);
	});

	$('#import-settings').on('change', function(e) {
		const file = e.target.files[0];
		if (!file) return;

		const reader = new FileReader();
		reader.onload = function(e) {
			try {
				const settings = JSON.parse(e.target.result);
				
				if (confirm('<?php echo esc_js( __( 'This will overwrite current settings. Continue?', 'ai-website-chatbot' ) ); ?>')) {
					Object.keys(settings).forEach(function(key) {
						const element = $('[name="' + key + '"]');
						if (element.length) {
							if (element.attr('type') === 'checkbox') {
								element.prop('checked', settings[key]);
							} else {
								element.val(settings[key]);
							}
						}
					});
					
					alert('<?php echo esc_js( __( 'Settings imported successfully!', 'ai-website-chatbot' ) ); ?>');
					$('.settings-form').addClass('unsaved-changes');
				}
			} catch (err) {
				alert('<?php echo esc_js( __( 'Invalid settings file.', 'ai-website-chatbot' ) ); ?>');
			}
		};
		reader.readAsText(file);
	});

	// Real-time validation
	$('#ai_chatbot_openai_api_key').on('input', function() {
		const apiKey = $(this).val();
		const feedback = $(this).siblings('.validation-feedback');
		
		if (apiKey && !apiKey.match(/^sk-[a-zA-Z0-9]{48}$/)) {
			if (!feedback.length) {
				$(this).after('<div class="validation-feedback error"><?php echo esc_js( __( 'Invalid API key format', 'ai-website-chatbot' ) ); ?></div>');
			}
		} else {
			feedback.remove();
		}
	});

	$('#ai_chatbot_claude_api_key').on('input', function() {
		const apiKey = $(this).val();
		const feedback = $(this).siblings('.validation-feedback');
		
		if (apiKey && !apiKey.match(/^sk-ant-[a-zA-Z0-9_-]+$/)) {
			if (!feedback.length) {
				$(this).after('<div class="validation-feedback error"><?php echo esc_js( __( 'Invalid API key format', 'ai-website-chatbot' ) ); ?></div>');
			}
		} else {
			feedback.remove();
		}
	});

	// Temperature slider
	$('#ai_chatbot_openai_temperature').on('input', function() {
		const value = $(this).val();
		const label = $(this).siblings('.temperature-label');
		
		let description = '';
		if (value <= 0.3) description = '<?php echo esc_js( __( 'Very focused', 'ai-website-chatbot' ) ); ?>';
		else if (value <= 0.7) description = '<?php echo esc_js( __( 'Balanced', 'ai-website-chatbot' ) ); ?>';
		else if (value <= 1.2) description = '<?php echo esc_js( __( 'Creative', 'ai-website-chatbot' ) ); ?>';
		else description = '<?php echo esc_js( __( 'Very creative', 'ai-website-chatbot' ) ); ?>';
		
		if (!label.length) {
			$(this).after('<span class="temperature-label"></span>');
		}
		$(this).siblings('.temperature-label').text(' (' + description + ')');
	});

	// Character counter for textareas
	$('textarea[maxlength]').each(function() {
		const textarea = $(this);
		const maxLength = parseInt(textarea.attr('maxlength'));
		const counter = $('<div class="char-counter"><span class="current">0</span>/' + maxLength + '</div>');
		textarea.after(counter);

		textarea.on('input', function() {
			const currentLength = $(this).val().length;
			counter.find('.current').text(currentLength);
			
			if (currentLength > maxLength * 0.9) {
				counter.addClass('warning');
			} else {
				counter.removeClass('warning');
			}
		});
	});

	// Preview chatbot button
	$('#preview-chatbot').on('click', function() {
		const previewWindow = window.open('', 'chatbot-preview', 'width=400,height=600,scrollbars=no,resizable=yes');
		
		// Get current settings
		const settings = {
			enabled: $('#ai_chatbot_enabled').is(':checked'),
			title: $('#ai_chatbot_widget_title').val(),
			welcomeMessage: $('#ai_chatbot_welcome_message').val(),
			themeColor: $('#ai_chatbot_theme_color').val(),
			position: $('#ai_chatbot_position').val()
		};

		// Generate preview HTML
		const previewHtml = `
			<!DOCTYPE html>
			<html>
			<head>
				<title>Chatbot Preview</title>
				<style>
					body { margin: 0; background: #f0f0f1; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
					.preview-container { padding: 20px; position: relative; height: 100vh; }
					.chatbot-widget { position: absolute; ${settings.position.includes('right') ? 'right: 20px;' : 'left: 20px;'} ${settings.position.includes('bottom') ? 'bottom: 20px;' : 'top: 20px;'} }
					.chatbot-toggle { width: 60px; height: 60px; border-radius: 50%; background: ${settings.themeColor}; border: none; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
					.chatbot-container { position: absolute; bottom: 70px; right: 0; width: 350px; height: 400px; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); display: none; flex-direction: column; }
					.chatbot-header { background: ${settings.themeColor}; color: white; padding: 16px 20px; border-radius: 8px 8px 0 0; }
					.chatbot-messages { flex: 1; padding: 20px; background: #f8f9fa; overflow-y: auto; }
					.welcome-message { background: white; padding: 12px 16px; border-radius: 18px; border: 1px solid #ddd; }
					.preview-note { position: absolute; top: 10px; left: 10px; background: #0073aa; color: white; padding: 8px 12px; border-radius: 4px; font-size: 12px; }
				</style>
			</head>
			<body>
				<div class="preview-container">
					<div class="preview-note">Preview Mode</div>
					<div class="chatbot-widget">
						<button class="chatbot-toggle" onclick="toggleChat()">💬</button>
						<div class="chatbot-container" id="chatContainer">
							<div class="chatbot-header">
								<h3 style="margin: 0; font-size: 16px;">${settings.title || 'AI Assistant'}</h3>
							</div>
							<div class="chatbot-messages">
								<div class="welcome-message">
									${settings.welcomeMessage || 'Hello! How can I help you today?'}
								</div>
							</div>
						</div>
					</div>
				</div>
				<script>
					function toggleChat() {
						const container = document.getElementById('chatContainer');
						container.style.display = container.style.display === 'flex' ? 'none' : 'flex';
					}
				</script>
			</body>
			</html>
		`;

		previewWindow.document.write(previewHtml);
		previewWindow.document.close();
	});

	// Reset settings confirmation
	$('#reset-settings').on('click', function() {
		if (confirm('<?php echo esc_js( __( 'Are you sure you want to reset all settings to defaults? This cannot be undone.', 'ai-website-chatbot' ) ); ?>')) {
			if (confirm('<?php echo esc_js( __( 'This will permanently delete your custom settings. Continue?', 'ai-website-chatbot' ) ); ?>')) {
				window.location.href = '<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ai-chatbot-settings&action=reset' ), 'ai_chatbot_reset' ) ); ?>';
			}
		}
	});

	// Help tooltips
	$('.help-tooltip').on('mouseenter', function() {
		const tooltip = $(this);
		const text = tooltip.data('help');
		
		if (!tooltip.find('.tooltip-content').length) {
			tooltip.append('<div class="tooltip-content">' + text + '</div>');
		}
		tooltip.find('.tooltip-content').show();
	}).on('mouseleave', function() {
		$(this).find('.tooltip-content').hide();
	});

	// Keyboard shortcuts
	$(document).on('keydown', function(e) {
		// Ctrl/Cmd + S to save
		if ((e.ctrlKey || e.metaKey) && e.key === 's') {
			e.preventDefault();
			$('.ai-chatbot-settings-form').submit();
		}
		
		// Ctrl/Cmd + T to test connection (if on AI config tab)
		if ((e.ctrlKey || e.metaKey) && e.key === 't' && $('#tab-ai-config').is(':visible')) {
			e.preventDefault();
			$('.test-connection:visible').first().click();
		}
	});

	// Smooth scrolling for anchor links
	$('a[href^="#"]').on('click', function(e) {
		e.preventDefault();
		const target = $($(this).attr('href'));
		if (target.length) {
			$('html, body').animate({
				scrollTop: target.offset().top - 50
			}, 500);
		}
	});

	// Initialize tooltips
	if ($.fn.tooltip) {
		$('[data-tooltip]').tooltip();
	}

	// Form dirty state management
	const originalFormData = $('.ai-chatbot-settings-form').serialize();
	setInterval(function() {
		const currentFormData = $('.ai-chatbot-settings-form').serialize();
		if (currentFormData !== originalFormData) {
			$('.settings-form').addClass('unsaved-changes');
		}
	}, 5000);

	// Auto-expand textareas
	$('textarea').each(function() {
		this.setAttribute('style', 'height:' + (this.scrollHeight) + 'px;overflow-y:hidden;');
	}).on('input', function() {
		this.style.height = 'auto';
		this.style.height = (this.scrollHeight) + 'px';
	});

	// Connection status indicator
	function updateConnectionStatus() {
		const provider = $('#ai_chatbot_ai_provider').val();
		const apiKey = $('#ai_chatbot_' + provider + '_api_key').val();
		const statusIndicator = $('.status-indicator');
		
		if (!apiKey) {
			statusIndicator.removeClass('status-online').addClass('status-offline');
			statusIndicator.find('.status-text').text('<?php echo esc_js( __( 'Not Configured', 'ai-website-chatbot' ) ); ?>');
		}
	}

	// Update status when provider or API key changes
	$('#ai_chatbot_ai_provider, input[id*="api_key"]').on('change input', updateConnectionStatus);

	// Initialize
	updateConnectionStatus();
	
	// Trigger temperature label update on page load
	$('#ai_chatbot_openai_temperature').trigger('input');
});
</script>

<style>
/* Additional admin styles */
.ai-chatbot-admin {
	max-width: 1200px;
}

.ai-chatbot-admin-header {
	display: flex;
	gap: 20px;
	margin-bottom: 20px;
	background: white;
	padding: 20px;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.ai-chatbot-status-card {
	flex: 1;
	display: flex;
	align-items: center;
	gap: 15px;
}

.status-indicator {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	border-radius: 20px;
	font-size: 14px;
	font-weight: 500;
}

.status-online {
	background: #d4edda;
	color: #155724;
}

.status-offline {
	background: #f8d7da;
	color: #721c24;
}

.status-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: currentColor;
}

.ai-chatbot-quick-stats {
	display: flex;
	gap: 20px;
}

.stat-item {
	text-align: center;
}

.stat-number {
	font-size: 24px;
	font-weight: 600;
	color: #0073aa;
}

.stat-label {
	font-size: 12px;
	color: #666;
	margin-top: 4px;
}

.ai-chatbot-admin-content {
	display: grid;
	grid-template-columns: 1fr 300px;
	gap: 20px;
}

.ai-chatbot-settings-nav {
	background: white;
	border-radius: 8px 8px 0 0;
	border-bottom: 1px solid #ddd;
	padding: 0;
	display: flex;
}

.ai-chatbot-settings-nav .nav-tab {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
	border: none;
	border-right: 1px solid #ddd;
}

.ai-chatbot-settings-form {
	background: white;
	border-radius: 0 0 8px 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tab-content {
	padding: 20px 30px;
}

.settings-section h2 {
	margin-top: 0;
	color: #23282d;
	border-bottom: 1px solid #ddd;
	padding-bottom: 10px;
}

.section-description {
	color: #666;
	font-style: italic;
	margin-bottom: 20px;
}

.provider-settings {
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 20px;
	margin-top: 15px;
	background: #f9f9f9;
}

.connection-status {
	margin-top: 8px;
	padding: 8px 12px;
	border-radius: 4px;
	font-size: 13px;
}

.connection-status.success {
	background: #d4edda;
	color: #155724;
}

.connection-status.error {
	background: #f8d7da;
	color: #721c24;
}

.ai-chatbot-admin-sidebar {
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.sidebar-widget {
	background: white;
	border-radius: 8px;
	padding: 20px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.sidebar-widget h3 {
	margin-top: 0;
	color: #23282d;
}

.quick-actions {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.quick-actions .button {
	display: flex;
	align-items: center;
	gap: 8px;
	justify-content: flex-start;
	text-decoration: none;
}

.system-info li {
	display: flex;
	justify-content: space-between;
	padding: 5px 0;
	border-bottom: 1px solid #f0f0f1;
}

.system-info li:last-child {
	border-bottom: none;
}

.validation-feedback {
	font-size: 12px;
	margin-top: 4px;
}

.validation-feedback.error {
	color: #d63638;
}

.char-counter {
	text-align: right;
	font-size: 11px;
	color: #666;
	margin-top: 4px;
}

.char-counter.warning {
	color: #d63638;
}

.title-version {
	font-size: 14px;
	color: #666;
	font-weight: normal;
}

.unsaved-changes {
	position: relative;
}

.unsaved-changes::before {
	content: '';
	position: absolute;
	top: 0;
	left: 0;
	width: 4px;
	height: 100%;
	background: #d63638;
}

@media (max-width: 768px) {
	.ai-chatbot-admin-content {
		grid-template-columns: 1fr;
	}
	
	.ai-chatbot-admin-header {
		flex-direction: column;
	}
	
	.ai-chatbot-settings-nav {
		flex-wrap: wrap;
	}
	
	.ai-chatbot-settings-nav .nav-tab {
		flex: 1;
		min-width: 120px;
		text-align: center;
	}
}
</style>
								

