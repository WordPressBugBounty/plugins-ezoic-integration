<?php

namespace Ezoic_Namespace;

require_once( dirname( __FILE__ ) . '/interface-ezoic-integration-content-collector.php' );
require_once( dirname( __FILE__ ) . '/include-functions.php' );
require_once( dirname( __FILE__ ) . '/class-ezoic-integration-path-sanitizer.php' );

class Ezoic_Integration_File_Content_Collector implements iEzoic_Integration_Content_Collector {
	private $config_path;

	public function __construct() {
		$this->config_path = dirname( __FILE__ ) . "/config/ezoic_config.json";
	}

	public function get_orig_content() {
		$content = "";

		if ( file_exists( $this->config_path ) && is_readable( $this->config_path ) ) {
			$cache_content  = file_get_contents( $this->config_path );
			$config_content = json_decode( $cache_content, true );
			$file_name      = "";
			if ( $config_content["cache_identity"] == Ezoic_Cache_Identity::W3_TOTAL_CACHE ) {
				$file_name = "_index.html";
			} elseif ( $config_content["cache_identity"] == Ezoic_Cache_Identity::WP_SUPER_CACHE ||
			           $config_content["cache_identity"] == Ezoic_Cache_Identity::WP_ROCKET_CACHE ) {
				$file_name = "index.html";
			}

			$content = $this->get_cached_file_contents( $config_content, $file_name );
		}

		return $content;
	}

	private function get_cached_file_contents( $config_content, $file_name ) {

		$cache_path = isset( $config_content['cache_path'] ) ? $config_content['cache_path'] : '';
		if ( $cache_path === '' ) {
			return '';
		}

		// SERVER_NAME comes from the Host header on most server configs and is
		// attacker-controlled; reject traversal/separator characters before it
		// is ever concatenated into a filesystem path.
		$server_name = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : '';
		if ( preg_match( '#[/\\\\]|\.\.#', $server_name ) ) {
			return '';
		}

		// REQUEST_URI is the primary attacker-controlled input; reject '..'
		// segments (including URL-encoded forms) before path construction
		// rather than relying solely on is_path_safe()'s realpath() check.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( strpos( rawurldecode( $request_uri ), '..' ) !== false ) {
			return '';
		}

		$cached_file = $cache_path . $server_name . $request_uri . $file_name;

		// Validate before any filesystem call so an unsafe path is never
		// stat'd (avoids turning file_exists()/is_readable() into a
		// file-existence oracle for attacker-controlled paths).
		if ( ! Ezoic_Integration_Path_Sanitizer::is_path_safe( $cached_file, $cache_path ) ) {
			return '';
		}

		if ( ! file_exists( $cached_file ) || ! is_readable( $cached_file ) ) {

			// recheck with SSL filename
			$file_name   = "_index_slash_ssl.html"; // W3_TOTAL_CACHE cache file
			$cached_file = $cache_path . $server_name . $request_uri . $file_name;

			if ( ! Ezoic_Integration_Path_Sanitizer::is_path_safe( $cached_file, $cache_path ) ) {
				return '';
			}

			if ( ! file_exists( $cached_file ) || ! is_readable( $cached_file ) ) {
				return '';
			}

		}

		$content = file_get_contents( $cached_file, true );
		$content .= "<!-- grabbed from cache file -->";

		return $content;
	}
}
