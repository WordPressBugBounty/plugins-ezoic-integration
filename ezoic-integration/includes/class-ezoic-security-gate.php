<?php

namespace Ezoic_Namespace;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shared authorization gate for plugin-owned state changes
 *
 * Admin REST permission is capability-only. Cookie REST already requires
 * WordPress core's X-WP-Nonce (`wp_rest`); this callback does not add a
 * second nonce. Form POSTs use admin_post (capability plus nonce).
 *
 * @link       https://ezoic.com
 * @since      1.0.0
 *
 * @package    Ezoic_Integration
 * @subpackage Ezoic_Integration/includes
 */
class Ezoic_Security_Gate
{
	const CAPABILITY = 'manage_options';

	/**
	 * Permission callback for admin REST routes
	 *
	 * @param \WP_REST_Request|null $request Unused; kept for the permission_callback signature
	 * @return bool
	 */
	public static function rest_admin($request = null)
	{
		return \current_user_can(self::CAPABILITY);
	}

	/**
	 * Capability plus nonce check for admin form POSTs
	 *
	 * @param string $nonce Nonce value supplied by the request
	 * @param string $action Nonce action the value must match
	 * @return bool
	 */
	public static function admin_post($nonce, $action)
	{
		if (!\current_user_can(self::CAPABILITY)) {
			return false;
		}

		if (!is_string($nonce) || $nonce === '') {
			return false;
		}

		return (bool) \wp_verify_nonce($nonce, $action);
	}

	/**
	 * HMAC check for backend-called content routes
	 *
	 * @param \WP_REST_Request $request Request carrying the content auth headers
	 * @return bool
	 */
	public static function content_hmac($request)
	{
		return Ezoic_Content_Export::verify_content_signature(
			$request->get_header('x-ezoic-content-auth'),
			(int) $request->get_header('x-ezoic-content-ts')
		);
	}
}
