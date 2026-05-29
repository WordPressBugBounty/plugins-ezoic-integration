<?php

namespace Ezoic_Namespace;

/**
 * Loads privacy settings that are owned by Publisher Dashboard.
 */
class Ezoic_Integration_Privacy_Config {
	const ENDPOINT = EZOIC_URL . '/pub/v1/wordpressintegration/v1/privacy-config?d=';
	const CACHE_KEY = 'ezoic_privacy_config';
	const CACHE_TTL = 300;
	const ERROR_CACHE_TTL = 300;
	const FETCH_LOCK_TTL = 30;
	const REQUEST_TIMEOUT = 3;

	/**
	 * Returns true when the WordPress plugin should emit the CCPA/GPP script.
	 */
	public static function should_inject_ccpa_script() {
		$config = self::get_config();

		if (!is_array($config) || !array_key_exists('ccpaFooterEnabled', $config)) {
			return true;
		}

		if (!(bool) $config['ccpaFooterEnabled']) {
			return false;
		}

		return !self::matches_disabled_ccpa_gpp_page($config);
	}

	private static function get_config() {
		$domain = Ezoic_Integration_Request_Utils::get_domain();
		if ($domain == '') {
			return array('ccpaFooterEnabled' => true);
		}

		$cached = self::get_cached_config($domain, false);
		if (is_array($cached)) {
			return $cached;
		}

		if (!self::acquire_fetch_lock($domain)) {
			$stale = self::get_cached_config($domain, true);
			if (is_array($stale)) {
				return $stale;
			}

			return array('ccpaFooterEnabled' => true);
		}

		$fetched = self::fetch_config($domain);
		if (is_array($fetched)) {
			self::cache_config($domain, $fetched);
			self::release_fetch_lock($domain);
			return $fetched;
		}

		$stale = self::get_cached_config($domain, true);
		if (is_array($stale)) {
			self::cache_config($domain, $stale, false, self::ERROR_CACHE_TTL);
			return $stale;
		}

		$default = array('ccpaFooterEnabled' => true);
		self::cache_config($domain, $default, false, self::ERROR_CACHE_TTL);
		return $default;
	}

	private static function acquire_fetch_lock($domain) {
		$lock_key = self::fetch_lock_key($domain);
		if (function_exists('add_option') && function_exists('get_option')) {
			$now = time();
			if (\add_option($lock_key, $now, '', false)) {
				return true;
			}

			$lock_time = (int) \get_option($lock_key, 0);
			if ($lock_time > 0 && $now - $lock_time > self::FETCH_LOCK_TTL && function_exists('delete_option')) {
				\delete_option($lock_key);
				return \add_option($lock_key, $now, '', false);
			}

			return false;
		}

		if (!function_exists('get_transient') || !function_exists('set_transient')) {
			return true;
		}

		if (\get_transient($lock_key) !== false) {
			return false;
		}

		return \set_transient($lock_key, 1, self::FETCH_LOCK_TTL);
	}

	private static function release_fetch_lock($domain) {
		if (function_exists('delete_option')) {
			\delete_option(self::fetch_lock_key($domain));
		}

		if (function_exists('delete_transient')) {
			\delete_transient(self::fetch_lock_key($domain));
		}
	}

	private static function fetch_lock_key($domain) {
		return self::cache_key($domain) . '_fetch_lock';
	}

	private static function get_cached_config($domain, $allow_stale) {
		$cache_key = self::cache_key($domain);

		if (!$allow_stale && function_exists('get_transient')) {
			$transient = \get_transient($cache_key);
			if (is_array($transient)) {
				return $transient;
			}
		}

		if (function_exists('get_option')) {
			$option = \get_option($cache_key, false);
			if (is_array($option) && $allow_stale) {
				return $option;
			}
		}

		return null;
	}

	private static function cache_config($domain, $config, $store_option = true, $ttl = null) {
		$cache_key = self::cache_key($domain);
		if ($ttl === null) {
			$ttl = self::CACHE_TTL;
		}

		if (function_exists('set_transient')) {
			\set_transient($cache_key, $config, self::cache_ttl($ttl));
		}

		if ($store_option && function_exists('update_option')) {
			\update_option($cache_key, $config, false);
		}
	}

	private static function cache_key($domain) {
		return self::CACHE_KEY . '_' . preg_replace('/[^a-z0-9_.-]/i', '_', strtolower($domain));
	}

	private static function cache_ttl($base_ttl) {
		$jitter = function_exists('wp_rand') ? \wp_rand(0, 300) : mt_rand(0, 300);
		return $base_ttl + $jitter;
	}

	private static function fetch_config($domain) {
		if (!function_exists('wp_remote_get')) {
			return null;
		}

		$request_url = self::ENDPOINT . rawurlencode($domain);
		$request_args = array(
			'method' => 'GET',
			'timeout' => self::REQUEST_TIMEOUT,
			'headers' => array(),
		);

		if (Ezoic_Cdn::ezoic_cdn_api_key() != null) {
			$request_url .= '&developerKey=' . rawurlencode(Ezoic_Cdn::ezoic_cdn_api_key());
		}

		$response = \wp_remote_get($request_url, $request_args);
		if (\is_wp_error($response)) {
			return null;
		}

		$response_body = \wp_remote_retrieve_body($response);
		$parsed = json_decode($response_body);
		if (!isset($parsed->status) || !$parsed->status || !isset($parsed->data)) {
			return null;
		}

		return self::normalize_config($parsed->data);
	}

	private static function normalize_config($data) {
		if (!is_object($data) || !property_exists($data, 'ccpaFooterEnabled')) {
			return null;
		}

		return array(
			'ccpaFooterEnabled' => (bool) $data->ccpaFooterEnabled,
			'cmpDialogEnabled' => property_exists($data, 'cmpDialogEnabled') ? (bool) $data->cmpDialogEnabled : false,
			'thirdPartyCMPEnabled' => property_exists($data, 'thirdPartyCMPEnabled') ? (bool) $data->thirdPartyCMPEnabled : false,
			'disabledCcpaGppPages' => property_exists($data, 'disabledCcpaGppPages') ? self::normalize_disabled_ccpa_gpp_pages($data->disabledCcpaGppPages) : array(),
		);
	}

	private static function normalize_disabled_ccpa_gpp_pages($pages) {
		if (is_object($pages) && property_exists($pages, 'list')) {
			$pages = $pages->list;
		}

		if (!is_array($pages)) {
			return array();
		}

		$normalized = array();
		foreach ($pages as $page) {
			$target = self::page_rule_value($page, 'target');
			$url = self::page_rule_value($page, 'url');
			if (!is_string($target) || !is_string($url) || $url === '') {
				continue;
			}

			if (!in_array($target, array('single_page', 'directory', 'contains'), true)) {
				continue;
			}

			$normalized[] = array(
				'target' => $target,
				'url' => $url,
			);
		}

		return $normalized;
	}

	private static function matches_disabled_ccpa_gpp_page($config) {
		if (!isset($config['disabledCcpaGppPages']) || !is_array($config['disabledCcpaGppPages'])) {
			return false;
		}

		$current_url = self::current_request_url();
		$current_path = self::normalized_url_path($current_url);

		foreach ($config['disabledCcpaGppPages'] as $page) {
			$target = self::page_rule_value($page, 'target');
			$url = self::page_rule_value($page, 'url');
			if (!is_string($target) || !is_string($url) || $url === '') {
				continue;
			}

			if ($target === 'single_page' && $current_path === self::normalized_url_path($url)) {
				return true;
			}

			if ($target === 'directory' && strpos($current_path, self::normalized_url_path($url)) === 0) {
				return true;
			}

			if ($target === 'contains' && strpos($current_url, $url) !== false) {
				return true;
			}
		}

		return false;
	}

	private static function current_request_url() {
		global $wp;

		$query_string = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
		if (function_exists('home_url') && isset($wp) && is_object($wp) && isset($wp->request)) {
			$current_url = \home_url($wp->request);
			if ($query_string !== '') {
				$current_url .= (strpos($current_url, '?') === false ? '?' : '&') . $query_string;
			}
			return $current_url;
		}

		if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '') {
			if (function_exists('home_url')) {
				return \home_url($_SERVER['REQUEST_URI']);
			}
			return $_SERVER['REQUEST_URI'];
		}

		return '';
	}

	private static function normalized_url_path($url) {
		$path = parse_url($url, PHP_URL_PATH);
		if (!is_string($path) || $path === '') {
			return '/';
		}

		return '/' . ltrim($path, '/');
	}

	private static function page_rule_value($page, $key) {
		if (is_array($page) && array_key_exists($key, $page)) {
			return $page[$key];
		}

		if (is_object($page) && property_exists($page, $key)) {
			return $page->{$key};
		}

		return null;
	}
}
