<?php

namespace Ezoic_Namespace;

/**
 * Utility class for requests to the Ezoic content backend (Emote export).
 */
class Ezoic_Content_Request {
	// Returns domain name set on server w/o protocol
	public static function find_host( ) {
		if ( isset( $_SERVER['SERVER_NAME'] ) && $_SERVER['SERVER_NAME'] ) {
			return sanitize_text_field( $_SERVER['SERVER_NAME'] );
		}
		return "";
	}

	public static function send_backend_request( $url_path, $request ) {
		$backend_url = "https://content-backend.ezoic.com" . $url_path;

		$ezoic_auth = new Ezoic_Auth();
		$token = $ezoic_auth->get_token();
		if ( ! $token ) {
			\error_log( "Unable to get authorization token for content-backend request. Not sending request" );
			return "";
		}

		$request["headers"]["Authorization"] = "Bearer " . $token;

		$response = wp_remote_post( $backend_url, $request );
		if ( is_wp_error( $response ) ) {
			\error_log( '[CMS] ' . $backend_url . " - " . $response->get_error_message() );
			return "";
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code > 299 ) {
			\error_log( "[CMS] " . $status_code . ": " . $backend_url );
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Export status requests
	 */
	public static function send_export_status( $status, $module ) {
		// Send alert to CMS
		$payload = array(
			'domain' 	=> self::find_host(),
			'status' 	=> $status,
			'type'		=> $module . " Import",
			'source' 	=> 'wordpress'
		);

		$request = array(
			'timeout'	=> 30,
			'method'	=> 'POST',
			'body'		=> json_encode( $payload ),
			'headers'	=> array(
				'Content-Type' 		=> 'application/json',
				'X-From-Req'		=> 'wp',
				'X-Ezoic-Import' 	=> 'true',
				'X-Ez-CMS-API'		=> 'true',
			),
		);

		return self::send_backend_request( '/api/v1/import/status', $request );
	}

	public static function send_export_alert( $message, $module ) {
		// Send alert to CMS
		$payload = array(
			'domain' 	=> self::find_host(),
			'message' 	=> $message,
			'source'	=> 'wordpress',
			'type' 		=> $module . " Import",
			'status'	=> 'FAILED'
		);

		$request = array(
			'timeout'	=> 30,
			'method'	=> 'POST',
			'body'		=> json_encode( $payload ),
			'headers'	=> array(
				'Content-Type' 		=> 'application/json',
				'X-From-Req'		=> 'wp',
				'X-Ezoic-Import' 	=> 'true',
				'X-Ez-CMS-API'		=> 'true',
			),
		);

		return self::send_backend_request( '/api/v1/import/alert', $request );
	}

	public static function notify_asset_upload_complete( $tenant) {
		$request = array(
			'timeout'	=> 30,
			'method'	=> 'POST',
			'headers'	=> array(
				'X-From-Req'		=> 'wp',
				'X-Ezoic-Import' 	=> 'true',
			),
		);

		return self::send_backend_request( "/api/v1/tenants/$tenant/workers/site/assets/startimport", $request );
	}

	public static function send_file_upload( $upload_url, $file_path, $filename, $auth_token ) {
		if ( !$auth_token ) {
			\error_log("Empty auth token");
		}

		$curl = curl_init();
		if ( !$curl ) {
			return "cURL req to import server could not be initialized";
		}

		$options_set = curl_setopt_array( $curl, array(
			CURLOPT_URL => $upload_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			// Only allow HTTPS - Must specify https as protocol in URL
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
			// Use HTTP2 over TLS
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
			CURLOPT_POST => 1,
			// Sets content-type as multi-part form data
			CURLOPT_POSTFIELDS => array(
				'file' => new \CURLFILE(
					$file_path,
					"application/zip",
					$filename
				),
			),
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . $auth_token,
				),
		));

		if ( !$options_set ) {
			return "cURL options could not be properly set";
		}

		$response = curl_exec( $curl );
		$resp_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		if ( !$response || $resp_code < 200 || $resp_code > 299 ) {
			if ( $resp_code && $response) {
				return "cURL execution to " . curl_getinfo( $curl, CURLINFO_EFFECTIVE_URL ) . " failed with code $resp_code: $response";
			}
			return "cURL execution failed: " . curl_error( $curl );
		}

		curl_close( $curl );
		return true;
	}
}
