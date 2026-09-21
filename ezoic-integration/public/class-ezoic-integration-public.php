<?php

namespace Ezoic_Namespace;

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @link       https://ezoic.com
 * @since      1.0.0
 *
 * @package    Ezoic_Integration
 * @subpackage Ezoic_Integration/public
 * @author     Ezoic Inc. <support@ezoic.com>
 */

require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ezoic-integration-factory.php';

class Ezoic_Integration_Public
{

	protected $loader;
	private $plugin_name;
	private $version;
	private $ads_disabled_for_user = null;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->loader      = null;
	}

	public function register_hooks($loader)
	{
		$this->loader = $loader;

		// Do not run any hooks/filters if disabled
		if (defined('EZOIC__DISABLE') && EZOIC__DISABLE) {
			return;
		}

		$this->bypass_cache_filters();

		$preview_mode = $this->is_js_preview_mode();

		// Handle preview mode cookie setting on init hook (WordPress standard for URL parameters)
		$this->loader->add_action('init', $this, 'handle_preview_mode_cookie');

		// Add JavaScript integration hooks if enabled OR if ez_js_preview parameter is present
		// Preview mode should work regardless of settings or cloud integration status
		$js_enabled = get_option('ezoic_js_integration_enabled', false);
		if ($js_enabled || $preview_mode) {
			$this->register_js_integration_hooks();
		}

		if (\defined('EZOIC_DEBUG') && EZOIC_DEBUG) {
			$this->loader->add_action('shutdown', $this, 'ez_debug_output');
		}
	}

	public function ez_debug_output()
	{
		$debuggers = [];

		// Output debugging information
		$debuggers[] = new Ezoic_Integration_WP_Debug(Ezoic_Cache_Type::NO_CACHE);

		foreach ($debuggers as $debugger) {
			echo $debugger->get_debug_information();
		}

		\do_action('ez_debug_output');
	}

	/**
	 * Check if JavaScript preview mode is active via URL parameter or cookie
	 */
	private function is_js_preview_mode()
	{
		return Ezoic_Integration::is_js_preview_mode();
	}

	/**
	 * Handle preview mode cookie setting during init (before headers are sent)
	 */
	public function handle_preview_mode_cookie()
	{
		if (isset($_GET['ez_js_preview'])) {
			if ($_GET['ez_js_preview'] == '1') {
				// Enable preview mode - set cookie to expire in 1 hour
				setcookie('ez_js_preview', '1', time() + 3600, '/');
			} elseif ($_GET['ez_js_preview'] == '0') {
				// Disable preview mode - clear cookie
				setcookie('ez_js_preview', '', time() - 3600, '/');
			}
		}
	}

	/**
	 * Register JavaScript integration hooks
	 */
	private function register_js_integration_hooks()
	{
		// Do not run JavaScript integration if disabled
		if (defined('EZOIC__DISABLE_JS') && EZOIC__DISABLE_JS) {
			return;
		}

		// Do not run JavaScript integration in admin contexts
		if ($this->is_admin_context()) {
			return;
		}

		$js_options = get_option('ezoic_js_integration_options');
		$is_preview_mode = $this->is_js_preview_mode();

		// Use default options if in preview mode
		if ($is_preview_mode) {
			$js_options = array(
				'js_auto_insert_scripts' => 1,
				'js_enable_privacy_scripts' => 1,
				'js_use_wp_placeholders' => 1
			);
		}

		// All three run at wp_head priority 0, ahead of core's own output at priority 1
		// (the title tag) so sa.min.js starts as early as the head allows. Same-priority
		// callbacks fire in registration order, so the order of these three calls is the
		// document order: keep privacy first. sa.min.js detects Gatekeeper by scanning
		// document.scripts when it initializes, and a cached async sa.min.js can run before
		// later <head> tags are parsed; missing that detection makes it wait on a publisher
		// CMP and then inject a duplicate Gatekeeper pair. All three tags are async, so
		// emitting the privacy pair first does not block the parser ahead of sa.min.js.
		if ((isset($js_options['js_enable_privacy_scripts']) && $js_options['js_enable_privacy_scripts']) || $is_preview_mode) {
			$this->loader->add_action('wp_head', $this, 'inject_privacy_scripts', 0);
		}

		if ((isset($js_options['js_auto_insert_scripts']) && $js_options['js_auto_insert_scripts']) || $is_preview_mode) {
			$this->loader->add_action('wp_head', $this, 'inject_ezoic_js_scripts', 0);
		}

		// Always emit when JS integration is enabled, independent of auto-insert.
		$this->loader->add_action('wp_head', $this, 'inject_ezoic_analytics_script', 0);

		// Add fallback showAds() call in footer if no placeholders were inserted
		if ((isset($js_options['js_auto_insert_scripts']) && $js_options['js_auto_insert_scripts']) || $is_preview_mode) {
			$this->loader->add_action('wp_footer', $this, 'inject_fallback_showads', 15);
		}

		// Add scroll rail initialization when enabled with a non-empty selector list
		// Preview mode does not force-enable scroll rails (selectors are publisher data)
		$scroll_rails_enabled = isset($js_options['js_enable_scroll_rails']) && $js_options['js_enable_scroll_rails'];
		$scroll_rail_selectors = array();
		if ($scroll_rails_enabled && isset($js_options['js_scroll_rail_selectors'])) {
			$scroll_rail_selectors = Ezoic_JS_Integration_Settings::parse_scroll_rail_selectors($js_options['js_scroll_rail_selectors']);
		}
		if (
			((isset($js_options['js_auto_insert_scripts']) && $js_options['js_auto_insert_scripts']) || $is_preview_mode)
			&& $scroll_rails_enabled
			&& !empty($scroll_rail_selectors)
		) {
			$this->loader->add_action('wp_footer', $this, 'inject_scroll_rail_script', 15);
		}

		// Exclude Ezoic scripts from LiteSpeed Cache optimization if plugin is active
		if (Ezoic_Integration_Compatibility_Check::is_litespeed_cache_active()) {
			$this->loader->add_filter('litespeed_optimize_js_excludes', $this, 'exclude_ezoic_scripts_from_litespeed', 10);
			$this->loader->add_filter('litespeed_optm_js_defer_exc', $this, 'exclude_ezoic_scripts_from_litespeed', 10);
		}

		// Opt Ezoic scripts out of WP Rocket JS optimizers (filters no-op when feature off)
		if (Ezoic_Integration_Compatibility_Check::is_wp_rocket_active()) {
			$this->loader->add_filter('rocket_delay_js_exclusions', $this, 'exclude_ezoic_scripts_from_wp_rocket_delay_js', 10);
			$this->loader->add_filter('rocket_exclude_defer_js', $this, 'exclude_ezoic_scripts_from_wp_rocket_defer_js', 10);
			$this->loader->add_filter('rocket_exclude_js', $this, 'exclude_ezoic_scripts_from_wp_rocket_minify_js', 10);
			$this->loader->add_filter('rocket_minify_excluded_external_js', $this, 'exclude_ezoic_scripts_from_wp_rocket_minify_js', 10);
			$this->loader->add_filter('rocket_excluded_inline_js_content', $this, 'exclude_ezoic_inline_from_wp_rocket_combine_js', 10);
		}
	}

	/**
	 * Attributes that opt injected scripts out of known cache-plugin optimizers.
	 *
	 * @return string
	 */
	private function get_cache_plugin_script_attrs()
	{
		return Ezoic_Integration_Compatibility_Check::get_cache_plugin_script_attrs();
	}

	/**
	 * Inject Ezoic JavaScript scripts
	 */
	public function inject_ezoic_js_scripts()
	{
		if ($this->is_admin_context()) {
			return;
		}

		if ($this->should_disable_ads_for_user()) {
			return;
		}

		$is_preview = $this->is_js_preview_mode();
		if ($is_preview) {
			echo '<!-- Ezoic JS Preview Mode Active -->' . "\n";
		}

		// Add LiteSpeed exclusion attributes if LiteSpeed Cache is active
		$litespeed_attr = $this->get_cache_plugin_script_attrs();

		// Main Ezoic script
		echo '<script id="ezoic-wp-plugin-js" async src="' . esc_url(EZOIC_SA_SCRIPT_URL) . '"' . $litespeed_attr . '></script>' . "\n";

		// Initialize ezstandalone
		echo '<script data-ezoic="1"' . $litespeed_attr . '>window.ezstandalone = window.ezstandalone || {};';
		echo 'ezstandalone.cmd = ezstandalone.cmd || [];</script>' . "\n";
	}

	/**
	 * Inject Ezoic analytics script unconditionally when JS integration is enabled
	 */
	public function inject_ezoic_analytics_script()
	{
		if ($this->is_admin_context()) {
			return;
		}

		$js_enabled = get_option('ezoic_js_integration_enabled', false);
		if (!$js_enabled && !$this->is_js_preview_mode()) {
			return;
		}

		$litespeed_attr = $this->get_cache_plugin_script_attrs();

		echo '<script async src="' . esc_url(EZOIC_ANALYTICS_SCRIPT_URL) . '"' . $litespeed_attr . '></script>' . "\n";
	}

	/**
	 * Inject privacy scripts (CMP - Consent Management Platform)
	 */
	public function inject_privacy_scripts()
	{
		// Do not inject scripts in admin contexts
		if ($this->is_admin_context()) {
			return;
		}

		// Add LiteSpeed exclusion attributes if LiteSpeed Cache is active
		$litespeed_attr = $this->get_cache_plugin_script_attrs();
		$gpp_suppress_attr = Ezoic_Integration_Privacy_Config::should_suppress_ccpa_gpp_banner() ? ' data-ez-gpp-suppress-banner="true"' : '';

		// CCPA/GPP can be suppressed independently of the CMP/GDPR gatekeeper script.
		if (Ezoic_Integration_Privacy_Config::should_inject_ccpa_script()) {
			echo '<script id="ezoic-wp-plugin-cmp" async src="' . esc_url(EZOIC_CMP_SCRIPT_URL) . '" data-cfasync="false"' . $litespeed_attr . '></script>' . "\n";
		}
		echo '<script id="ezoic-wp-plugin-gatekeeper" async src="' . esc_url(EZOIC_GATEKEEPER_SCRIPT_URL) . '" data-cfasync="false"' . $gpp_suppress_attr . $litespeed_attr . '></script>' . "\n";
	}

	/**
	 * Inject fallback showAds() call if no placeholders were inserted on the page
	 */
	public function inject_fallback_showads()
	{
		// Do not inject scripts in admin contexts
		if ($this->is_admin_context()) {
			return;
		}

		// Don't call showAds if user has ads disabled based on their role
		if ($this->should_disable_ads_for_user()) {
			echo '<!-- Ezoic showAds() skipped - ads disabled for user role -->' . "\n";
			return;
		}

		// Check if any Ezoic JS placeholders were inserted using the class method
		if (!Ezoic_AdTester_Placeholder::js_placeholders_inserted()) {
			// Add LiteSpeed exclusion attributes if LiteSpeed Cache is active
			$litespeed_attr = $this->get_cache_plugin_script_attrs();

			// No JS placeholders were inserted, add fallback showAds() call
			echo '<script data-ezoic="1"' . $litespeed_attr . '>ezstandalone.cmd.push(function () { ezstandalone.showAds(); });</script>' . "\n";
		}
	}

	/**
	 * Inject scroll rail initialization for publisher-configured selectors
	 */
	public function inject_scroll_rail_script()
	{
		if ($this->is_admin_context()) {
			return;
		}

		if ($this->should_disable_ads_for_user()) {
			return;
		}

		$js_options = get_option('ezoic_js_integration_options', array());
		if (!isset($js_options['js_enable_scroll_rails']) || !$js_options['js_enable_scroll_rails']) {
			return;
		}

		$selector_raw = isset($js_options['js_scroll_rail_selectors']) ? $js_options['js_scroll_rail_selectors'] : '';
		$selectors = Ezoic_JS_Integration_Settings::parse_scroll_rail_selectors($selector_raw);
		if (empty($selectors)) {
			return;
		}

		$litespeed_attr = $this->get_cache_plugin_script_attrs();
		$selectors_json = wp_json_encode(array_values($selectors));

		echo '<script data-ezoic="1"' . $litespeed_attr . '>';
		echo 'window.ezstandalone = window.ezstandalone || {};';
		echo 'ezstandalone.cmd = ezstandalone.cmd || [];';
		echo 'ezstandalone.cmd.push(function () {';
		echo 'if (typeof ezstandalone.showScrollRail !== "function") { return; }';
		echo 'var selectors = ' . $selectors_json . ';';
		echo 'var elements = [];';
		echo 'var i, j, k, sel, matches, el, id, n, already;';
		echo 'for (i = 0; i < selectors.length; i++) {';
		// getElementById/getElementsByClassName avoid CSS selector parsing, which
		// would throw on digit-leading names (e.g. #2023-header) that are valid
		// HTML ids/classes but invalid CSS identifiers.
		echo 'sel = selectors[i];';
		echo 'if (sel.charAt(0) === "#") {';
		echo 'el = document.getElementById(sel.slice(1));';
		echo 'matches = el ? [el] : [];';
		echo '} else {';
		echo 'matches = document.getElementsByClassName(sel.slice(1));';
		echo '}';
		echo 'for (j = 0; j < matches.length; j++) {';
		echo 'el = matches[j];';
		echo 'already = false;';
		echo 'for (k = 0; k < elements.length; k++) { if (elements[k] === el) { already = true; break; } }';
		echo 'if (already) { continue; }';
		echo 'elements.push(el);';
		echo 'if (elements.length >= 10) { break; }';
		echo '}';
		echo 'if (elements.length >= 10) { break; }';
		echo '}';
		echo 'n = 1;';
		echo 'for (i = 0; i < elements.length; i++) {';
		echo 'el = elements[i];';
		echo 'id = el.id;';
		echo 'if (!id) {';
		echo 'while (document.getElementById("ez-scroll-rail-" + n)) { n++; }';
		echo 'id = "ez-scroll-rail-" + n;';
		echo 'el.id = id;';
		echo 'n++;';
		echo '}';
		echo 'ezstandalone.showScrollRail(id);';
		echo '}';
		echo '});';
		echo '</script>' . "\n";
	}

	private function bypass_cache_filters()
	{
		// Prevent WP-Touch Cache(s)
		$this->loader->add_filter('wptouch_addon_cache_current_page', '__return_false', 99);
	}

	/**
	 * Check if we're in an admin context where JS integration should be disabled
	 */
	private function is_admin_context()
	{
		// Check for admin pages
		if (is_admin()) {
			return true;
		}

		// Check for customizer preview
		if (is_customize_preview()) {
			return true;
		}

		// Check for block editor context (including widget editor)
		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if ($screen && method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if ads should be disabled for the current user based on their role.
	 * Checks the cookie first (fast path), then falls back to a direct role check
	 * in case the cookie hasn't been set yet (e.g. first request after login).
	 *
	 * @return bool True if ads should be disabled, false otherwise
	 */
	private function should_disable_ads_for_user()
	{
		if ($this->ads_disabled_for_user !== null) {
			return $this->ads_disabled_for_user;
		}

		if (isset($_COOKIE['x-ez-wp-noads']) && $_COOKIE['x-ez-wp-noads'] == '1') {
			return $this->ads_disabled_for_user = true;
		}

		if (!is_user_logged_in()) {
			return $this->ads_disabled_for_user = false;
		}

		if (class_exists('Ezoic_Namespace\Ezoic_AdTester_Config')) {
			$config = Ezoic_AdTester_Config::load();
			if (
				isset($config->user_roles_with_ads_disabled)
				&& !empty($config->user_roles_with_ads_disabled)
			) {
				$currentUserRoles = array_map('strtolower', wp_get_current_user()->roles);
				$disabledRoles    = array_map('strtolower', $config->user_roles_with_ads_disabled);
				$diff             = array_diff($currentUserRoles, $disabledRoles);

				if (count($currentUserRoles) !== count($diff)) {
					return $this->ads_disabled_for_user = true;
				}
			}
		}

		return $this->ads_disabled_for_user = false;
	}

	/**
	 * Exclude Ezoic scripts from LiteSpeed Cache JS optimization and deferring
	 * This prevents LiteSpeed from minifying/combining/deferring our sa.min.js file
	 *
	 * Used for both:
	 * - litespeed_optimize_js_excludes (prevent minification/combination)
	 * - litespeed_optm_js_defer_exc (prevent defer/delay)
	 *
	 * @param array $excludes Array of JS files/patterns to exclude from optimization
	 * @return array Modified array with Ezoic scripts added
	 */
	public function exclude_ezoic_scripts_from_litespeed($excludes)
	{
		if (!is_array($excludes)) {
			$excludes = array();
		}

		// Add Ezoic script URLs to exclusion list
		$ezoic_scripts = array(
			'ezojs.com/ezoic/sa.min.js',
			'ezoicanalytics.com/analytics.js',
			'cmp.gatekeeperconsent.com/min.js',
			'the.gatekeeperconsent.com/cmp.min.js'
		);

		foreach ($ezoic_scripts as $script) {
			if (!in_array($script, $excludes, true)) {
				$excludes[] = $script;
			}
		}

		return $excludes;
	}

	/**
	 * Exclude Ezoic scripts from WP Rocket Delay JavaScript Execution.
	 *
	 * @param mixed $excludes Existing exclusion patterns.
	 * @return array
	 */
	public function exclude_ezoic_scripts_from_wp_rocket_delay_js($excludes)
	{
		return Ezoic_Integration_Compatibility_Check::exclude_ezoic_scripts_from_wp_rocket_delay_js($excludes);
	}

	/**
	 * Exclude Ezoic scripts from WP Rocket Load JavaScript deferred.
	 *
	 * @param mixed $excludes Existing exclusion patterns.
	 * @return array
	 */
	public function exclude_ezoic_scripts_from_wp_rocket_defer_js($excludes)
	{
		return Ezoic_Integration_Compatibility_Check::exclude_ezoic_scripts_from_wp_rocket_defer_js($excludes);
	}

	/**
	 * Exclude Ezoic scripts from WP Rocket Minify / Combine JS.
	 *
	 * @param mixed $excludes Existing exclusion patterns.
	 * @return array
	 */
	public function exclude_ezoic_scripts_from_wp_rocket_minify_js($excludes)
	{
		return Ezoic_Integration_Compatibility_Check::exclude_ezoic_scripts_from_wp_rocket_minify_js($excludes);
	}

	/**
	 * Exclude Ezoic inline bootstrap from WP Rocket Combine JS.
	 *
	 * @param mixed $excludes Existing inline exclusion patterns.
	 * @return array
	 */
	public function exclude_ezoic_inline_from_wp_rocket_combine_js($excludes)
	{
		return Ezoic_Integration_Compatibility_Check::exclude_ezoic_inline_from_wp_rocket_combine_js($excludes);
	}
}
