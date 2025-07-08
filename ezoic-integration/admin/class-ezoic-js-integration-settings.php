<?php

namespace Ezoic_Namespace;

/**
 * JavaScript Integration Settings for the Ezoic plugin.
 *
 * @link       https://ezoic.com
 * @since      1.0.0
 *
 * @package    Ezoic_Integration
 * @subpackage Ezoic_Integration/admin
 */
class Ezoic_JS_Integration_Settings
{
	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct()
	{
		// Constructor can be empty for now
	}

	/**
	 * Initialize JavaScript integration settings
	 */
	public function initialize_js_integration_settings()
	{
		// Only initialize if JS integration is enabled
		if (!get_option('ezoic_js_integration_enabled', false)) {
			return;
		}

		if (false === get_option('ezoic_js_integration_options')) {
			$default_array = $this->default_js_integration_options();
			update_option('ezoic_js_integration_options', $default_array);
		}

		add_settings_section(
			'ezoic_js_integration_section',
			__('JavaScript Integration Settings', 'ezoic'),
			array($this, 'js_integration_settings_callback'),
			'ezoic_js_integration_options'
		);

		add_settings_field(
			'js_auto_insert_scripts',
			__('Auto-insert Scripts', 'ezoic'),
			array($this, 'js_auto_insert_scripts_callback'),
			'ezoic_js_integration_options',
			'ezoic_js_integration_section'
		);

		add_settings_field(
			'js_enable_privacy_scripts',
			__('Enable Privacy Scripts', 'ezoic'),
			array($this, 'js_enable_privacy_scripts_callback'),
			'ezoic_js_integration_options',
			'ezoic_js_integration_section'
		);

		add_settings_field(
			'js_use_wp_placeholders',
			__('Use WordPress Placeholders', 'ezoic'),
			array($this, 'js_use_wp_placeholders_callback'),
			'ezoic_js_integration_options',
			'ezoic_js_integration_section'
		);

		register_setting(
			'ezoic_js_integration_options',
			'ezoic_js_integration_options',
			array(
				'default' => $this->default_js_integration_options(),
				'type' => 'array',
				'sanitize_callback' => array($this, 'sanitize_js_integration_options')
			)
		);
	}

	/**
	 * Default JavaScript integration options
	 */
	private function default_js_integration_options()
	{
		return array(
			'js_auto_insert_scripts' => 1,
			'js_enable_privacy_scripts' => 1,
			'js_use_wp_placeholders' => 0
		);
	}

	/**
	 * JavaScript integration settings section callback
	 */
	public function js_integration_settings_callback()
	{
		echo '<p>' . __('Configure how JavaScript integration works on your site.', 'ezoic') . '</p>';
		echo '<hr/>';
	}

	/**
	 * Auto-insert scripts field callback
	 */
	public function js_auto_insert_scripts_callback($args)
	{
		$options = get_option('ezoic_js_integration_options', $this->default_js_integration_options());
		$value = isset($options['js_auto_insert_scripts']) ? $options['js_auto_insert_scripts'] : 1;

		$html = '<input type="checkbox" id="js_auto_insert_scripts" name="ezoic_js_integration_options[js_auto_insert_scripts]" value="1"' . checked(1, $value, false) . '/>';
		$html .= '<label for="js_auto_insert_scripts">' . __('Automatically insert Ezoic scripts into your pages', 'ezoic') . '</label>';
		$html .= '<p class="description">' . __('Essential JavaScript files that initialize the Ezoic ad system.', 'ezoic') . '</p>';

		// Check if sa.min.js is already detected on the site
		if ($value && $this->is_sa_script_detected()) {
			$html .= '<div class="notice notice-warning inline" style="margin: 10px 0; padding: 8px 12px;">';
			$html .= '<p style="margin: 0;"><strong>' . __('Warning:', 'ezoic') . '</strong> ';
			$html .= __('Ezoic scripts are already detected on your site. Please remove existing scripts before enabling auto-insert.', 'ezoic');
			$html .= '</p></div>';
		}

		echo $html;
	}

	/**
	 * Enable privacy scripts field callback
	 */
	public function js_enable_privacy_scripts_callback($args)
	{
		$options = get_option('ezoic_js_integration_options', $this->default_js_integration_options());
		$value = isset($options['js_enable_privacy_scripts']) ? $options['js_enable_privacy_scripts'] : 1;

		$html = '<input type="checkbox" id="js_enable_privacy_scripts" name="ezoic_js_integration_options[js_enable_privacy_scripts]" value="1"' . checked(1, $value, false) . '/>';
		$html .= '<label for="js_enable_privacy_scripts">' . __('Enable privacy compliance scripts', 'ezoic') . '</label>';
		$html .= '<p class="description">' . __('The privacy scripts handle user consent management and ensure compliance with privacy regulations.', 'ezoic') . '</p>';

		echo $html;
	}

	/**
	 * Use WordPress placeholders field callback
	 */
	public function js_use_wp_placeholders_callback($args)
	{
		$options = get_option('ezoic_js_integration_options', $this->default_js_integration_options());
		$value = isset($options['js_use_wp_placeholders']) ? $options['js_use_wp_placeholders'] : 0;

		$html = '<input type="checkbox" id="js_use_wp_placeholders" name="ezoic_js_integration_options[js_use_wp_placeholders]" value="1"' . checked(1, $value, false) . '/>';
		$html .= '<label for="js_use_wp_placeholders">' . __('Use WordPress-generated ad placeholders', 'ezoic') . '</label>';
		$html .= '<p class="description">' . sprintf(
			__('Use ad placeholders that are automatically inserted by <a href="%s">Ad Placements</a>. When disabled, placeholders must be inserted manually.', 'ezoic'),
			admin_url('admin.php?page=' . EZOIC__PLUGIN_SLUG . '&tab=ad_settings')
		) . '</p>';

		echo $html;
	}

	/**
	 * Handle enabling JavaScript integration
	 */
	public function handle_enable_js_integration()
	{
		if (isset($_POST['action']) && $_POST['action'] === 'enable_js_integration') {
			if (!wp_verify_nonce($_POST['js_integration_nonce'], 'enable_js_integration_nonce')) {
				wp_die('Security check failed');
			}
			// Enable JavaScript integration
			update_option('ezoic_js_integration_enabled', true);

			// Set disable_wp_integration to true when enabling JS integration
			$integration_options = get_option('ezoic_integration_options', array());
			$integration_options['disable_wp_integration'] = true;
			update_option('ezoic_integration_options', $integration_options);

			// Disable Placement Service Integration when enabling JS integration
			// Use the proper AdTester class to load and save the configuration
			$adtester = new \Ezoic_Namespace\Ezoic_AdTester();
			if ($adtester->config && isset($adtester->config->enable_adpos_integration)) {
				// Disable Placement Service Integration
				$adtester->config->enable_adpos_integration = false;
				// Save the updated config using the proper method
				$adtester->update_config();
			}

			// Trigger integration recheck by clearing the check time
			$options = get_option('ezoic_integration_status');
			$options['check_time'] = '';
			update_option('ezoic_integration_status', $options);

			// Set default options if they don't exist
			if (false === get_option('ezoic_js_integration_options')) {
				update_option('ezoic_js_integration_options', $this->default_js_integration_options());
			}

			// Determine redirect tab
			$redirect_tab = isset($_POST['from_integration_tab']) ? 'js_integration' : 'js_integration';

			// Redirect to Integration tab
			wp_redirect(admin_url('admin.php?page=' . EZOIC__PLUGIN_SLUG . '&tab=' . $redirect_tab));
			exit;
		}
	}

	/**
	 * Handle disabling JavaScript integration
	 */
	public function handle_disable_js_integration()
	{
		if (isset($_POST['action']) && $_POST['action'] === 'disable_js_integration') {
			if (!wp_verify_nonce($_POST['js_integration_disable_nonce'], 'disable_js_integration_nonce')) {
				wp_die('Security check failed');
			}

			// Disable JavaScript integration
			update_option('ezoic_js_integration_enabled', false);

			// Trigger integration recheck by clearing the check time
			$options = get_option('ezoic_integration_status');
			$options['check_time'] = '';
			update_option('ezoic_integration_status', $options);

			// Optionally clear JavaScript integration options
			// delete_option('ezoic_js_integration_options');

			// Redirect to Integration tab
			wp_redirect(admin_url('admin.php?page=' . EZOIC__PLUGIN_SLUG . '&tab=js_integration&js_integration_disabled=1'));
			exit;
		}
	}

	/**
	 * Sanitize JavaScript integration options
	 */
	public function sanitize_js_integration_options($settings)
	{
		// Get current options to merge with
		$current_options = get_option('ezoic_js_integration_options', $this->default_js_integration_options());

		// Sanitize each setting
		$sanitized = array();
		$sanitized['js_auto_insert_scripts'] = isset($settings['js_auto_insert_scripts']) ? 1 : 0;
		$sanitized['js_enable_privacy_scripts'] = isset($settings['js_enable_privacy_scripts']) ? 1 : 0;
		$sanitized['js_use_wp_placeholders'] = isset($settings['js_use_wp_placeholders']) ? 1 : 0;

		// Trigger integration recheck if any settings changed
		if ($sanitized !== $current_options) {
			$options = get_option('ezoic_integration_status');
			$options['check_time'] = '';
			update_option('ezoic_integration_status', $options);
		}

		return $sanitized;
	}
	/**
	 * Check if sa.min.js script is already loaded on the site
	 */
	public function is_sa_script_detected()
	{
		// Get the site's homepage content
		$url = home_url('/');
		$response = wp_remote_get($url, array(
			'timeout' => 10,
			'user-agent' => 'WordPress/Ezoic Plugin Script Detection'
		));

		if (is_wp_error($response)) {
			return false;
		}

		$contents = wp_remote_retrieve_body($response);
		if (empty($contents)) {
			return false;
		}

		$script_count = substr_count($contents, 'ezojs.com/ezoic/sa.min.js');
		if ($script_count === 0) {
			return false;
		}

		$plugin_script_found = strpos($contents, 'id="ezoic-wp-plugin-js"') !== false;
		if ($plugin_script_found) {
			return $script_count > 1;  // Duplicates detected
		}

		return true;
	}

	/**
	 * Render the JS integration tab content
	 */
	public function render_js_integration_tab()
	{
		// Check if WordPress integration is active and show recommendation
		$wp_integration_active = !get_option('ezoic_js_integration_enabled', false) &&
			\Ezoic_Namespace\Ezoic_Integration_Admin::is_wordpress_integrated();

		// Show recommendation message if WordPress integration is active
		if ($wp_integration_active && !get_option('ezoic_js_integration_enabled', false)) {
			echo '<div class="notice notice-info" style="margin: 20px 0; padding: 12px; background-color: #e7f3ff; border-left: 4px solid #0073aa;">';
			echo '<h4 style="margin-top: 0; color: #0073aa;"><span class="dashicons dashicons-info" style="vertical-align: middle; margin-right: 5px;"></span>' . __('Recommendation: Switch to JavaScript Integration', 'ezoic') . '</h4>';
			echo '<p>' . __('Your site is currently using WordPress Integration. We recommend switching to JavaScript Integration for better performance and more advanced features.', 'ezoic') . '</p>';
			echo '<p><strong>' . __('Benefits of Ezoic JavaScript Integration:', 'ezoic') . '</strong></p>';
			echo '<ul style="margin-left: 20px;">';
			echo '<li>' . __('Quick, simple setup', 'ezoic') . '</li>';
			echo '<li>' . __('No changes to DNS required', 'ezoic') . '</li>';
			echo '<li>' . __('Complete control &amp; customization', 'ezoic') . '</li>';
			echo '<li>' . __('Lightweight scripts', 'ezoic') . '</li>';
			echo '<li>' . __('&#8230; and more!', 'ezoic') . '</li>';
			echo '</ul>';
			echo '</div>';
		}

		// Only show settings if JS integration is enabled
		if (get_option('ezoic_js_integration_enabled', false)) {
			settings_fields('ezoic_js_integration_options');
			do_settings_sections('ezoic_js_integration_options');
			submit_button('Save Settings');
		} else {
			// Check if site is cloud integrated and show warning
			if (\Ezoic_Namespace\Ezoic_Integration_Admin::is_cloud_integrated()) {
				echo '<div class="notice notice-warning" style="margin: 20px 0; padding: 12px; background-color: #fff3cd; border-left: 4px solid #ffc107;">';
				echo '<h4 style="margin-top: 0; color: #856404;"><span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 5px;"></span>' . __('Cloud Integration Active', 'ezoic') . '</h4>';
				echo '<p>' . __('Your site is using Ezoic Cloud Integration, which already handles script delivery. Enabling JavaScript Integration may cause conflicts.', 'ezoic') . '</p>';
				echo '</div>';
			}

			// Just show the turn on button directly, don't call do_settings_sections
			echo '<h3>' . __('JavaScript Integration Settings', 'ezoic') . '</h3>';
			echo '<p>' . __('JavaScript integration is currently disabled. Enable it to configure advanced settings for your Ezoic ads.', 'ezoic') . '</p>';
			echo '<form method="post" action="" style="margin-top: 20px;">';
			echo wp_nonce_field('enable_js_integration_nonce', 'js_integration_nonce', true, false);
			echo '<input type="hidden" name="action" value="enable_js_integration"/>';
			echo '<input type="hidden" name="from_integration_tab" value="1"/>';
			echo '<input type="submit" class="button button-primary" value="Turn On JavaScript Integration" style="background: #0073aa; color: white; border-color: #005a87;"/>';
			echo '</form>';
		}
	}

	/**
	 * Render the help documentation section for JS integration
	 */
	public function render_help_section()
	{
		if (get_option('ezoic_js_integration_enabled', false)) {
?>
			<div style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
				<div style="margin-bottom: 15px;">
					<p style="color: #666; margin-bottom: 10px;"><?php _e('Need help with JavaScript integration?', 'ezoic'); ?></p>
					<a href="https://docs.ezoic.com/docs/ezoicads/" target="_blank" class="button button-secondary" style="margin-right: 10px;">
						<span class="dashicons dashicons-external" style="vertical-align: middle; margin-right: 5px;"></span>
						<?php _e('JavaScript Integration Documentation', 'ezoic'); ?>
					</a>
					<a href="<?php echo esc_url(home_url('?ez_js_debugger=1')); ?>" target="_blank" class="button button-secondary">
						<span class="dashicons dashicons-admin-tools" style="vertical-align: middle; margin-right: 5px;"></span>
						<?php _e('Open Debugger', 'ezoic'); ?>
					</a>
				</div>
				<p style="color: #666;"><?php _e('Turn off automatic JavaScript integration.', 'ezoic'); ?></p>
				<form method="post" action="<?php echo admin_url('admin.php?page=' . EZOIC__PLUGIN_SLUG . '&tab=js_integration'); ?>" style="display: inline;">
					<?php wp_nonce_field('disable_js_integration_nonce', 'js_integration_disable_nonce'); ?>
					<input type="hidden" name="action" value="disable_js_integration" />
					<input type="submit" name="disable_js_integration" class="button button-link-delete" value="<?php _e('Turn Off', 'ezoic'); ?>" />
				</form>
			</div>
<?php
		}
	}
}
