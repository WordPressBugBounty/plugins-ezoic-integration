<?php

namespace Ezoic_Namespace;

/**
 * Compatibility check for Ezoic plugin against other plugins.
 *
 * @link       https://ezoic.com
 * @since      1.0.0
 *
 * @package    Ezoic_Integration
 * @subpackage Ezoic_Integration/includes
 */

/**
 * Class Ezoic_Integration_Compatibility_Check
 * @package Ezoic_Namespace
 */
class Ezoic_Integration_Compatibility_Check
{

	// incompatible plugins regardless of integration type
	static $all_incompatible_plugins = array(
		'Ezoic CDN Manager' => array(
			'versions' => 'all',
			'message' => 'Ezoic CDN Manager functionality has been implemented into this main Ezoic plugin. Please deactivate and remove the Ezoic CDN Manager plugin.'
		),
	);

	// list of know incompatible plugins for wordpress integration
	static $known_incompatible_plugins = array(
		'Accelerated Mobile Pages' => array(
			'versions' => 'all',
			'message' => 'Must disable this plugin and can use another AMP plugin for conflict-free mobile site and monetization.',
			'allow_install' => true
		),
		'Ads.txt Manager' => array(
			'versions' => 'all',
			'message'  => 'For WordPress integration, we recommend disabling this plugin and using our <a href="?page=' . EZOIC__PLUGIN_SLUG . '&tab=adstxtmanager_settings">Ads.txt Manager</a> setup.',
			'allow_install' => true
		),
		'AMP' => array(
			'versions' => 'all',
			'message' => 'Must disable this plugin and can use another AMP plugin for conflict-free mobile site and monetization.',
			'allow_install' => true
		),
		/*'Wordfence Security' => array(
			'versions' => 'all',
			'message' => 'Please disable this plugin or contact Wordfence to whitelist Ezoic IPs to avoid Origin Error (if Ezoic IPs are already whitelisted, you can ignore this message). For more information on Origin Error, please visit <a target="_blank" rel="noopener noreferrer" href="https://support.ezoic.com/kb/article/how-to-fix-origin-errors">support.ezoic.com/kb/article/how-to-fix-origin-errors</a>.',
			'allow_install' => true
		),
		'Wordfence Login Security' => array(
			'versions' => 'all',
			'message' => 'Please disable this plugin or contact Wordfence to whitelist Ezoic IPs to avoid Origin Error (if Ezoic IPs are already whitelisted, you can ignore this message). For more information on Origin Error, please visit <a target="_blank" rel="noopener noreferrer" href="https://support.ezoic.com/kb/article/how-to-fix-origin-errors">support.ezoic.com/kb/article/how-to-fix-origin-errors</a>.',
			'allow_install' => true
		),
		'Wordfence Assistant' => array(
			'versions' => 'all',
			'message' => 'Please disable this plugin or contact Wordfence to whitelist Ezoic IPs to avoid Origin Error (if Ezoic IPs are already whitelisted, you can ignore this message). For more information on Origin Error, please visit <a target="_blank" rel="noopener noreferrer" href="https://support.ezoic.com/kb/article/how-to-fix-origin-errors">support.ezoic.com/kb/article/how-to-fix-origin-errors</a>.',
			'allow_install' => true
		),*/
		'Swift Performance Lite' => array(
			'versions' => 'all',
			'message' => 'Plugin must be disabled to utilize Ezoic without issues or conflicts. Sites can elect to use a whitelisted WP caching plugin.'
		),
		'LiteSpeed Cache' => array(
			'versions' => 'all',
			'message' => 'Plugin must be disabled to utilize Ezoic without issues or conflicts. Sites can elect to use a whitelisted WP caching plugin.'
		),
		'WP Fastest Cache' => array(
			'versions' => 'all',
			'message' => 'Plugin must be disabled to utilize Ezoic without issues or conflicts.'
		),
		'Autoptimize' => array(
			'versions' => 'all',
			'message' => 'Plugin must be disabled to utilize Ezoic without issues or conflicts.'
		),
		'WP-Optimize - Clean, Compress, Cache' => array(
			'versions' => 'all',
			'message' => 'Plugin must be disabled to utilize Ezoic without issues or conflicts. Sites can elect to use a whitelisted WP caching plugin.'
		),
		'SG Optimizer' => array(
			'versions' => 'all',
			'message' => 'Plugin must be disabled to utilize Ezoic without issues or conflicts. Sites can elect to use a whitelisted WP caching plugin.'
		),
	);

	// list of known compatible plugins
	static $whitelisted_plugins = array(
		// 'W3 Total Cache' => array(
		// 	'versions' => 'all',
		// 	'message' => 'Ezoic\'s Leap optimization features may require that these plugins be turned off or that all minification, caching, or "speed" optimizations are disabled to prevent conflicts. Leap optimally replaces the functionality of these plugins as it relates to site speed.'
		// ),
		// 'WP Super Cache' => array(
		// 	'versions' => 'all',
		// 	'message' => 'Ezoic\'s Leap optimization features may require that these plugins be turned off or that all minification, caching, or "speed" optimizations are disabled to prevent conflicts. Leap optimally replaces the functionality of these plugins as it relates to site speed.'
		// ),
		// 'WP Rocket' => array(
		// 	'versions' => 'all',
		// 	'message' => 'Ezoic\'s Leap optimization features may require that these plugins be turned off or that all minification, caching, or "speed" optimizations are disabled to prevent conflicts. Leap optimally replaces the functionality of these plugins as it relates to site speed.'
		// ),
	);

	/**
	 * Get plugins that are known to be NOT compatible with Ezoic.
	 *
	 * @param $activation
	 *
	 * @return array
	 */
	public static function get_active_incompatible_plugins($activation = false)
	{
		$active_plugins       = self::get_active_plugins();
		$incompatible_plugins = array();

		// incompatible with wordpress integration
		if (Ezoic_Integration_Admin::is_wordpress_integrated()) {
			foreach ($active_plugins as $filename => $plugin) {
				if (self::is_in_plugins_list($plugin, self::$known_incompatible_plugins)) {
					if (
						$activation
						&& isset(self::$known_incompatible_plugins[$plugin['name']]['allow_install'])
						&& self::$known_incompatible_plugins[$plugin['name']]['allow_install'] == true
					) {
						// skip activation wp_die()
						continue;
					}
					$plugin['message']  = self::$known_incompatible_plugins[$plugin['name']]['message'];
					$plugin['filename'] = $filename;
					array_push($incompatible_plugins, $plugin);
				}
			}
		}

		// incompatible with any integration type
		foreach ($active_plugins as $filename => $plugin) {
			if (self::is_in_plugins_list($plugin, self::$all_incompatible_plugins)) {
				$plugin['message']  = self::$all_incompatible_plugins[$plugin['name']]['message'];
				$plugin['filename'] = $filename;
				array_push($incompatible_plugins, $plugin);
			}
		}
		return $incompatible_plugins;
	}

	/**
	 * Get plugins that are known to be compatible with Ezoic but can be replaced by another Ezoic product.
	 * @return array
	 */
	public static function get_compatible_plugins_with_recommendations()
	{
		$active_plugins = self::get_active_plugins();
		$plugins = array();
		foreach ($active_plugins as $filename => $plugin) {
			if ($plugin['name'] == EZOIC__PLUGIN_NAME || self::is_in_plugins_list($plugin, self::$known_incompatible_plugins)) {
				continue;
			}
			if (self::is_in_plugins_list($plugin, self::$whitelisted_plugins)) {
				$plugin['message'] = self::$whitelisted_plugins[$plugin['name']]['message'];
				array_push($plugins, $plugin);
			}
		}
		return $plugins;
	}

	public static function get_active_plugins()
	{
		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins = get_plugins();
		$active_plugins = get_option('active_plugins');
		$plugins = array();
		foreach ($all_plugins as $key => $value) {
			if (in_array($key, $active_plugins)) {
				$plugins[$key] = array(
					'name'    => $value['Name'],
					'version' => $value['Version'],
				);
			}
		}

		return $plugins;
	}

	private static function is_in_plugins_list($plugin, $plugins_list)
	{
		foreach ($plugins_list as $name => $info) {
			$versions = $info['versions'];
			if ($plugin['name'] == $name) {
				if ($versions == 'all' || in_array($plugin['version'], $versions)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Get activation or deactivation link of a plugin
	 *
	 * @param $plugin
	 * @param string $action
	 *
	 * @return string
	 */
	public static function plugin_action_url($plugin, $action = 'deactivate')
	{
		if (strpos($plugin, '/')) {
			$plugin = str_replace('\/', '%2F', $plugin);
		}
		$url = sprintf(admin_url('plugins.php?action=' . $action . '&plugin=%s&plugin_status=all&paged=1&s'), $plugin);
		$_REQUEST['plugin'] = $plugin;
		$url = wp_nonce_url($url, $action . '-plugin_' . $plugin);
		return $url;
	}

	/**
	 * Check if LiteSpeed Cache plugin is active
	 *
	 * @return bool True if LiteSpeed Cache is active, false otherwise
	 */
	public static function is_litespeed_cache_active()
	{
		static $is_active = null;

		// Return cached result if already checked
		if ($is_active !== null) {
			return $is_active;
		}

		// Check if LiteSpeed Cache plugin is active
		if (!function_exists('is_plugin_active')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$is_active = is_plugin_active('litespeed-cache/litespeed-cache.php');
		return $is_active;
	}

	/**
	 * Remove Ezoic from WP Rocket's conflicting-plugins notice.
	 *
	 * WP Rocket still lists this plugin as incompatible based on the old
	 * HTML/proxy integration. Current JS integration does not block Rocket
	 * page caching, so the admin notice is incorrect.
	 *
	 * @param mixed $plugins Plugins WP Rocket recommends deactivating.
	 * @return mixed
	 */
	public static function suppress_wp_rocket_conflict_notice($plugins)
	{
		if (!is_array($plugins)) {
			return $plugins;
		}

		unset($plugins['ezoic']);

		return $plugins;
	}

	/**
	 * Remove the matching WP Rocket conflict explanation for Ezoic.
	 *
	 * @param mixed $explanations Conflict notice explanations keyed by plugin.
	 * @return mixed
	 */
	public static function suppress_wp_rocket_conflict_explanations($explanations)
	{
		if (!is_array($explanations)) {
			return $explanations;
		}

		unset($explanations['ezoic']);

		return $explanations;
	}

	/**
	 * Check if WP Rocket is active.
	 *
	 * @return bool
	 */
	public static function is_wp_rocket_active()
	{
		static $is_active = null;

		if ($is_active !== null) {
			return $is_active;
		}

		if (!function_exists('is_plugin_active')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$is_active = is_plugin_active('wp-rocket/wp-rocket.php');
		return $is_active;
	}

	/**
	 * Check if WP Rocket Delay JavaScript Execution is enabled.
	 *
	 * @return bool
	 */
	public static function is_wp_rocket_delay_js_enabled()
	{
		if (!self::is_wp_rocket_active()) {
			return false;
		}

		if (function_exists('get_rocket_option')) {
			return (bool) get_rocket_option('delay_js', false);
		}

		$settings = get_option('wp_rocket_settings', array());
		return !empty($settings['delay_js']);
	}

	/**
	 * Host patterns for Ezoic / consent / analytics script URLs.
	 *
	 * Used by Delay, Defer, Minify, and Combine external exclusions.
	 * Matches support's manual exclusion list plus analytics.
	 *
	 * @return string[]
	 */
	public static function get_wp_rocket_host_exclusion_patterns()
	{
		return array(
			'ezojs.com',
			'ezoic.net',
			'ezodn.com',
			'gatekeeperconsent.com',
			'ezoicanalytics.com',
		);
	}

	/**
	 * Patterns matched against full script tags by Delay JS exclusions.
	 *
	 * Hosts cover external scripts. Inline markers cover bootstrap / opt-out attrs.
	 * Defer JS only matches script src URLs (hosts still apply; markers are no-ops there).
	 *
	 * @return string[]
	 */
	public static function get_wp_rocket_script_exclusion_patterns()
	{
		return array_merge(
			self::get_wp_rocket_host_exclusion_patterns(),
			array(
				'ezstandalone',
				'data-nowprocket',
			)
		);
	}

	/**
	 * Merge patterns into a WP Rocket exclusion list without duplicates.
	 *
	 * @param mixed    $excludes Existing exclusion patterns.
	 * @param string[] $patterns Patterns to add.
	 * @return array
	 */
	public static function merge_wp_rocket_exclusion_patterns($excludes, $patterns)
	{
		if (!is_array($excludes)) {
			$excludes = array();
		}

		foreach ($patterns as $pattern) {
			if (!in_array($pattern, $excludes, true)) {
				$excludes[] = $pattern;
			}
		}

		return $excludes;
	}

	/**
	 * Exclude Ezoic scripts from WP Rocket Delay / Defer JS lists.
	 *
	 * @param mixed $excludes Existing exclusion patterns.
	 * @return array
	 */
	public static function exclude_ezoic_scripts_from_wp_rocket($excludes)
	{
		return self::merge_wp_rocket_exclusion_patterns(
			$excludes,
			self::get_wp_rocket_script_exclusion_patterns()
		);
	}

	/**
	 * Exclude Ezoic scripts from WP Rocket Delay JavaScript Execution.
	 *
	 * @param mixed $excludes Existing Delay JS exclusion patterns.
	 * @return array
	 */
	public static function exclude_ezoic_scripts_from_wp_rocket_delay_js($excludes)
	{
		return self::exclude_ezoic_scripts_from_wp_rocket($excludes);
	}

	/**
	 * Exclude Ezoic scripts from WP Rocket Load JavaScript deferred.
	 *
	 * @param mixed $excludes Existing defer exclusion patterns.
	 * @return array
	 */
	public static function exclude_ezoic_scripts_from_wp_rocket_defer_js($excludes)
	{
		return self::exclude_ezoic_scripts_from_wp_rocket($excludes);
	}

	/**
	 * Exclude Ezoic script hosts from WP Rocket Minify / Combine JS.
	 *
	 * rocket_exclude_js matches local file paths; rocket_minify_excluded_external_js
	 * matches full external URLs (strpos). Host patterns cover the external path
	 * Rocket uses when Minify JS is on.
	 *
	 * @param mixed $excludes Existing exclusion patterns.
	 * @return array
	 */
	public static function exclude_ezoic_scripts_from_wp_rocket_minify_js($excludes)
	{
		return self::merge_wp_rocket_exclusion_patterns(
			$excludes,
			self::get_wp_rocket_host_exclusion_patterns()
		);
	}

	/**
	 * Keep Ezoic inline bootstrap out of WP Rocket Combine JS.
	 *
	 * @param mixed $excludes Existing inline content exclusion patterns.
	 * @return array
	 */
	public static function exclude_ezoic_inline_from_wp_rocket_combine_js($excludes)
	{
		return self::merge_wp_rocket_exclusion_patterns($excludes, array('ezstandalone'));
	}

	/**
	 * Attributes that opt injected scripts out of known cache-plugin optimizers.
	 *
	 * @return string
	 */
	public static function get_cache_plugin_script_attrs()
	{
		$attrs = '';

		if (self::is_litespeed_cache_active()) {
			$attrs .= ' data-no-optimize="1" data-no-defer="1"';
		}

		if (self::is_wp_rocket_delay_js_enabled()) {
			$attrs .= ' data-nowprocket';
		}

		return $attrs;
	}
}
