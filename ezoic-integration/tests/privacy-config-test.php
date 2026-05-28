<?php

namespace {
	$GLOBALS['ezoic_test_transients'] = array();
	$GLOBALS['ezoic_test_options'] = array();
	$GLOBALS['ezoic_test_remote_response'] = null;
	$GLOBALS['ezoic_test_remote_calls'] = 0;
	$GLOBALS['ezoic_test_token'] = 'test-token';
	$GLOBALS['ezoic_test_api_key'] = null;
	$GLOBALS['ezoic_test_transient_ttls'] = array();

	if (!defined('EZOIC_URL')) {
		define('EZOIC_URL', 'https://publisherbe.ezoic.com');
	}

	function get_transient($key) {
		return array_key_exists($key, $GLOBALS['ezoic_test_transients']) ? $GLOBALS['ezoic_test_transients'][$key] : false;
	}

	function set_transient($key, $value, $ttl) {
		$GLOBALS['ezoic_test_transients'][$key] = $value;
		$GLOBALS['ezoic_test_transient_ttls'][$key] = $ttl;
		return true;
	}

	function get_option($key, $default = false) {
		return array_key_exists($key, $GLOBALS['ezoic_test_options']) ? $GLOBALS['ezoic_test_options'][$key] : $default;
	}

	function update_option($key, $value, $autoload = null) {
		$GLOBALS['ezoic_test_options'][$key] = $value;
		return true;
	}

	function is_wp_error($value) {
		return $value instanceof WP_Error;
	}

	function wp_remote_get($url, $args) {
		$GLOBALS['ezoic_test_remote_calls']++;
		$GLOBALS['ezoic_test_last_request'] = array('url' => $url, 'args' => $args);
		return $GLOBALS['ezoic_test_remote_response'];
	}

	function wp_remote_retrieve_body($response) {
		return isset($response['body']) ? $response['body'] : '';
	}

	function home_url($path = '') {
		$path = '/' . ltrim($path, '/');
		return 'https://example.com' . ($path === '/' ? '' : $path);
	}

	function wp_rand($min = 0, $max = 0) {
		return $min;
	}

	class WP_Error {}
}

namespace Ezoic_Namespace {

class Ezoic_Integration_Request_Utils {
	public static function get_domain() {
		return 'example.com';
	}
}

class Ezoic_Integration_Authentication {
	public static function get_token($requestURL = '') {
		return $GLOBALS['ezoic_test_token'];
	}
}

class Ezoic_Cdn {
	public static function ezoic_cdn_api_key() {
		return $GLOBALS['ezoic_test_api_key'];
	}
}

require_once __DIR__ . '/../includes/class-ezoic-integration-privacy-config.php';

function reset_privacy_config_test_state() {
	$GLOBALS['ezoic_test_transients'] = array();
	$GLOBALS['ezoic_test_options'] = array();
	$GLOBALS['ezoic_test_remote_response'] = null;
	$GLOBALS['ezoic_test_remote_calls'] = 0;
	$GLOBALS['ezoic_test_token'] = 'test-token';
	$GLOBALS['ezoic_test_api_key'] = null;
	$GLOBALS['ezoic_test_transient_ttls'] = array();
	$GLOBALS['wp'] = (object) array('request' => '');
	$_SERVER['QUERY_STRING'] = '';
	$_SERVER['REQUEST_URI'] = '/';
}

function set_privacy_config_request($request_path, $query_string = '') {
	$GLOBALS['wp'] = (object) array('request' => ltrim($request_path, '/'));
	$_SERVER['QUERY_STRING'] = $query_string;
	$_SERVER['REQUEST_URI'] = $request_path . ($query_string === '' ? '' : '?' . $query_string);
}

function assert_same($want, $got, $message) {
	if ($want !== $got) {
		echo "FAIL: {$message}. want ";
		var_export($want);
		echo ', got ';
		var_export($got);
		echo "\n";
		exit(1);
	}
}

reset_privacy_config_test_state();
\set_transient('ezoic_privacy_config_example.com', array('ccpaFooterEnabled' => false), 3600);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'cached disabled CCPA suppresses script');
assert_same(0, $GLOBALS['ezoic_test_remote_calls'], 'cached config does not call backend');

reset_privacy_config_test_state();
set_privacy_config_request('/privacy-policy', 'ignored=1');
\set_transient('ezoic_privacy_config_example.com', array(
	'ccpaFooterEnabled' => true,
	'disabledCcpaGppPages' => array(
		array('target' => 'single_page', 'url' => '/privacy-policy'),
	),
), 3600);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'single_page CCPA/GPP rule matches path and ignores query string');

reset_privacy_config_test_state();
set_privacy_config_request('/privacy-policy/subpage', 'ignored=1');
\set_transient('ezoic_privacy_config_example.com', array(
	'ccpaFooterEnabled' => true,
	'disabledCcpaGppPages' => array(
		array('target' => 'directory', 'url' => '/privacy-policy'),
	),
), 3600);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'directory CCPA/GPP rule matches path prefix and ignores query string');

reset_privacy_config_test_state();
set_privacy_config_request('/article', 'source=newsletter');
\set_transient('ezoic_privacy_config_example.com', array(
	'ccpaFooterEnabled' => true,
	'disabledCcpaGppPages' => array(
		array('target' => 'contains', 'url' => 'source=newsletter'),
	),
), 3600);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'contains CCPA/GPP rule matches full URL including query string');

reset_privacy_config_test_state();
set_privacy_config_request('/article', 'source=organic');
\set_transient('ezoic_privacy_config_example.com', array(
	'ccpaFooterEnabled' => true,
	'disabledCcpaGppPages' => array(
		array('target' => 'contains', 'url' => 'source=newsletter'),
	),
), 3600);
assert_same(true, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'non-matching CCPA/GPP rule does not suppress enabled CCPA script');

reset_privacy_config_test_state();
unset($GLOBALS['wp']);
$_SERVER['REQUEST_URI'] = '/fallback-page?ignored=1';
\set_transient('ezoic_privacy_config_example.com', array(
	'ccpaFooterEnabled' => true,
	'disabledCcpaGppPages' => array(
		array('target' => 'single_page', 'url' => '/fallback-page'),
	),
), 3600);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'request URI fallback matches path when WordPress request is unavailable');

reset_privacy_config_test_state();
$GLOBALS['ezoic_test_remote_response'] = array(
	'body' => json_encode(array(
		'status' => true,
		'data' => array(
			'ccpaFooterEnabled' => false,
			'cmpDialogEnabled' => true,
			'thirdPartyCMPEnabled' => false,
			'disabledCcpaGppPages' => array(
				array('target' => 'single_page', 'url' => '/privacy-policy'),
				array('target' => 'directory', 'url' => '/privacy'),
				array('target' => 'contains', 'url' => 'source=newsletter'),
				array('target' => 'contains'),
				array('url' => '/missing-target'),
				'invalid-row',
			),
		),
	)),
);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'backend disabled CCPA suppresses script');
assert_same(false, \get_option('ezoic_privacy_config_example.com', array())['ccpaFooterEnabled'], 'successful backend config is cached');
assert_same(array(
	array('target' => 'single_page', 'url' => '/privacy-policy'),
	array('target' => 'directory', 'url' => '/privacy'),
	array('target' => 'contains', 'url' => 'source=newsletter'),
), \get_option('ezoic_privacy_config_example.com', array())['disabledCcpaGppPages'], 'backend CCPA/GPP page rules are normalized into cache');
assert_same(300, $GLOBALS['ezoic_test_transient_ttls']['ezoic_privacy_config_example.com'], 'successful backend config uses five-minute cache TTL before jitter');

reset_privacy_config_test_state();
$GLOBALS['ezoic_test_remote_response'] = array(
	'body' => json_encode(array(
		'status' => true,
		'data' => array(
			'ccpaFooterEnabled' => true,
			'cmpDialogEnabled' => true,
			'thirdPartyCMPEnabled' => false,
			'disabledCcpaGppPages' => array(
				'list' => array(
					array('target' => 'single_page', 'url' => '/wrapper-page'),
					array('target' => 'directory', 'url' => '/wrapper-directory'),
				),
			),
		),
	)),
);
assert_same(true, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'wrapper object config preserves enabled CCPA when no rule matches');
assert_same(array(
	array('target' => 'single_page', 'url' => '/wrapper-page'),
	array('target' => 'directory', 'url' => '/wrapper-directory'),
), \get_option('ezoic_privacy_config_example.com', array())['disabledCcpaGppPages'], 'backend CCPA/GPP wrapper object list is normalized into cache');

reset_privacy_config_test_state();
\update_option('ezoic_privacy_config_example.com', array('ccpaFooterEnabled' => false));
$GLOBALS['ezoic_test_remote_response'] = new \WP_Error();
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'stale disabled cache is used on backend failure');

reset_privacy_config_test_state();
$GLOBALS['ezoic_test_token'] = '';
assert_same(true, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'missing auth and no cache preserves existing script behavior');

echo "privacy-config tests passed\n";
}
