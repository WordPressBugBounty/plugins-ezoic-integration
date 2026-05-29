<?php

namespace {
	$GLOBALS['ezoic_test_transients'] = array();
	$GLOBALS['ezoic_test_options'] = array();
	$GLOBALS['ezoic_test_remote_response'] = null;
	$GLOBALS['ezoic_test_remote_calls'] = 0;
	$GLOBALS['ezoic_test_token'] = 'test-token';
	$GLOBALS['ezoic_test_token_calls'] = 0;
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

	function delete_transient($key) {
		unset($GLOBALS['ezoic_test_transients'][$key]);
		unset($GLOBALS['ezoic_test_transient_ttls'][$key]);
		return true;
	}

	function get_option($key, $default = false) {
		return array_key_exists($key, $GLOBALS['ezoic_test_options']) ? $GLOBALS['ezoic_test_options'][$key] : $default;
	}

	function add_option($key, $value = '', $deprecated = '', $autoload = 'yes') {
		if (array_key_exists($key, $GLOBALS['ezoic_test_options'])) {
			return false;
		}

		$GLOBALS['ezoic_test_options'][$key] = $value;
		return true;
	}

	function update_option($key, $value, $autoload = null) {
		$GLOBALS['ezoic_test_options'][$key] = $value;
		return true;
	}

	function delete_option($key) {
		unset($GLOBALS['ezoic_test_options'][$key]);
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

	function plugin_dir_path($path) {
		return dirname($path) . '/';
	}

	function is_admin() {
		return false;
	}

	function is_customize_preview() {
		return false;
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
		$GLOBALS['ezoic_test_token_calls']++;
		return $GLOBALS['ezoic_test_token'];
	}
}

class Ezoic_Cdn {
	public static function ezoic_cdn_api_key() {
		return $GLOBALS['ezoic_test_api_key'];
	}
}

if (!defined(__NAMESPACE__ . '\\EZOIC_CMP_SCRIPT_URL')) {
	define(__NAMESPACE__ . '\\EZOIC_CMP_SCRIPT_URL', 'https://cmp.gatekeeperconsent.com/min.js');
}
if (!defined(__NAMESPACE__ . '\\EZOIC_GATEKEEPER_SCRIPT_URL')) {
	define(__NAMESPACE__ . '\\EZOIC_GATEKEEPER_SCRIPT_URL', 'https://the.gatekeeperconsent.com/cmp.min.js');
}

require_once __DIR__ . '/../includes/class-ezoic-integration-privacy-config.php';
require_once __DIR__ . '/../public/class-ezoic-integration-public.php';

function reset_privacy_config_test_state() {
	$GLOBALS['ezoic_test_transients'] = array();
	$GLOBALS['ezoic_test_options'] = array();
	$GLOBALS['ezoic_test_remote_response'] = null;
	$GLOBALS['ezoic_test_remote_calls'] = 0;
	$GLOBALS['ezoic_test_token'] = 'test-token';
	$GLOBALS['ezoic_test_token_calls'] = 0;
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
assert_same(true, Ezoic_Integration_Privacy_Config::should_suppress_ccpa_gpp_banner(), 'single_page CCPA/GPP rule suppresses Gatekeeper GPP banner');
$public = new Ezoic_Integration_Public('ezoic-integration', 'test');
\ob_start();
$public->inject_privacy_scripts();
$privacy_scripts = \ob_get_clean();
assert_same(false, strpos($privacy_scripts, EZOIC_CMP_SCRIPT_URL) !== false, 'suppressed page omits plugin CCPA/GPP script');
assert_same(true, strpos($privacy_scripts, EZOIC_GATEKEEPER_SCRIPT_URL) !== false, 'suppressed page keeps Gatekeeper script');
assert_same(true, strpos($privacy_scripts, 'data-ez-gpp-suppress-banner="true"') !== false, 'suppressed page marks Gatekeeper script to suppress GPP banner');

reset_privacy_config_test_state();
set_privacy_config_request('/whitelisting-guide', 'sldkfjsdlkfj');
\set_transient('ezoic_privacy_config_example.com', array(
	'ccpaFooterEnabled' => true,
	'disabledCcpaGppPages' => array(
		array('target' => 'single_page', 'url' => '/whitelisting-guide/'),
	),
), 3600);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'single_page CCPA/GPP rule ignores query strings and insignificant trailing slashes');
assert_same(true, Ezoic_Integration_Privacy_Config::should_suppress_ccpa_gpp_banner(), 'trailing-slash single_page rule suppresses Gatekeeper GPP banner');

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
set_privacy_config_request('/whitelisting-guide/subpage', 'ignored=1');
\set_transient('ezoic_privacy_config_example.com', array(
	'ccpaFooterEnabled' => true,
	'disabledCcpaGppPages' => array(
		array('target' => 'directory', 'url' => '/whitelisting-guide/'),
	),
), 3600);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'directory CCPA/GPP rule ignores insignificant trailing slash for child paths');

reset_privacy_config_test_state();
set_privacy_config_request('/whitelisting-guide-extra', 'ignored=1');
\set_transient('ezoic_privacy_config_example.com', array(
	'ccpaFooterEnabled' => true,
	'disabledCcpaGppPages' => array(
		array('target' => 'directory', 'url' => '/whitelisting-guide/'),
	),
), 3600);
assert_same(true, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'directory CCPA/GPP rule does not match sibling path with shared prefix');

reset_privacy_config_test_state();
set_privacy_config_request('/other-page', 'ignored=1');
\set_transient('ezoic_privacy_config_example.com', array(
	'ccpaFooterEnabled' => true,
	'disabledCcpaGppPages' => array(
		array('target' => 'single_page', 'url' => '/'),
	),
), 3600);
assert_same(true, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'single_page root CCPA/GPP rule does not match non-root paths');

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
assert_same(0, $GLOBALS['ezoic_test_token_calls'], 'privacy config fetch does not request auth token');
assert_same(array(), $GLOBALS['ezoic_test_last_request']['args']['headers'], 'privacy config fetch does not send auth headers');
assert_same(false, \get_option('ezoic_privacy_config_example.com', array())['ccpaFooterEnabled'], 'successful backend config is cached');
assert_same(array(
	array('target' => 'single_page', 'url' => '/privacy-policy'),
	array('target' => 'directory', 'url' => '/privacy'),
	array('target' => 'contains', 'url' => 'source=newsletter'),
), \get_option('ezoic_privacy_config_example.com', array())['disabledCcpaGppPages'], 'backend CCPA/GPP page rules are normalized into cache');
assert_same(300, $GLOBALS['ezoic_test_transient_ttls']['ezoic_privacy_config_example.com'], 'successful backend config uses five-minute cache TTL before jitter');
assert_same(false, \get_option('ezoic_privacy_config_example.com_fetch_lock', false), 'successful backend config releases fetch lock');

reset_privacy_config_test_state();
$GLOBALS['ezoic_test_api_key'] = 'api-key-123';
$GLOBALS['ezoic_test_remote_response'] = array(
	'body' => json_encode(array(
		'status' => true,
		'data' => array(
			'ccpaFooterEnabled' => true,
			'cmpDialogEnabled' => true,
			'thirdPartyCMPEnabled' => false,
			'disabledCcpaGppPages' => array(),
		),
	)),
);
assert_same(true, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'api-key privacy config preserves enabled CCPA');
assert_same(true, strpos($GLOBALS['ezoic_test_last_request']['url'], 'developerKey=api-key-123') !== false, 'api-key privacy config appends developer key');
assert_same(0, $GLOBALS['ezoic_test_token_calls'], 'api-key privacy config does not request auth token');

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
assert_same(false, \get_transient('ezoic_privacy_config_example.com')['ccpaFooterEnabled'], 'stale config is cached briefly after backend failure');
assert_same(300, $GLOBALS['ezoic_test_transient_ttls']['ezoic_privacy_config_example.com'], 'stale config after backend failure uses error cache TTL');

reset_privacy_config_test_state();
\add_option('ezoic_privacy_config_example.com_fetch_lock', time(), '', false);
assert_same(true, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'active fetch lock uses safe default without backend request');
assert_same(0, $GLOBALS['ezoic_test_remote_calls'], 'active fetch lock does not call backend');
assert_same(0, $GLOBALS['ezoic_test_token_calls'], 'active fetch lock does not request auth token');

reset_privacy_config_test_state();
\update_option('ezoic_privacy_config_example.com', array('ccpaFooterEnabled' => false));
\add_option('ezoic_privacy_config_example.com_fetch_lock', time(), '', false);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'active fetch lock uses stale config when available');
assert_same(0, $GLOBALS['ezoic_test_remote_calls'], 'active fetch lock with stale config does not call backend');

reset_privacy_config_test_state();
\add_option('ezoic_privacy_config_example.com_fetch_lock', time() - 60, '', false);
$GLOBALS['ezoic_test_remote_response'] = array(
	'body' => json_encode(array(
		'status' => true,
		'data' => array(
			'ccpaFooterEnabled' => false,
			'cmpDialogEnabled' => true,
			'thirdPartyCMPEnabled' => false,
			'disabledCcpaGppPages' => array(),
		),
	)),
);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'expired fetch lock allows backend refresh');
assert_same(1, $GLOBALS['ezoic_test_remote_calls'], 'expired fetch lock calls backend once');
assert_same(false, \get_option('ezoic_privacy_config_example.com_fetch_lock', false), 'expired fetch lock is released after successful refresh');

reset_privacy_config_test_state();
$GLOBALS['ezoic_test_remote_response'] = array(
	'body' => json_encode(array(
		'status' => true,
		'data' => array(
			'ccpaFooterEnabled' => false,
			'cmpDialogEnabled' => true,
			'thirdPartyCMPEnabled' => false,
			'disabledCcpaGppPages' => array(),
		),
	)),
);
assert_same(false, Ezoic_Integration_Privacy_Config::should_inject_ccpa_script(), 'privacy config fetch succeeds without auth token');
assert_same(0, $GLOBALS['ezoic_test_token_calls'], 'missing auth token is not needed for privacy config');

echo "privacy-config tests passed\n";
}
