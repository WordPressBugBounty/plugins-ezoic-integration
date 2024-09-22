<?php

namespace Ezoic_Namespace;

/**
 * AdTester Configuration
 */
class Ezoic_AdTester_Config {
	const VERSION = '3';

	// const config_sync_endpoint = 'http://wordpress-service.ezoic.com:5460/api/v1/wp/config';
	const config_sync_endpoint = 'https://ad-api.ezoic.com/api/v1/wp/config';

	// IMPORTANT: The constructor doesn't get called when we build a new Ezoic_AdTester_Config object
	// by deserializing the user's saved data in load(), so new variables in this class should be
	// given an initial value here as well as the reset() function or else they won't be set.
	public $version;
	public $last_updated = 0;
	public $paragraph_tags;
	public $excerpt_tags;
	public $placeholders;
	public $parent_filters;
	public $exclude_pages;
	public $exclude_parent_tags;
	public $exclude_class_list;
	public $placeholder_config;
	public $last_placeholder_fetch;
	public $skip_word_count = 10;
	public $sidebar_id = 'sidebar-1';
	public $user_roles_with_ads_disabled = array();
	public $meta_tags = array();
	public $exclude_urls = array();
	public $enable_adpos_integration = false;

	public function __construct() {
		$this->version	= Ezoic_AdTester_Config::VERSION;

		$this->reset();
	}

	/**
	 * Completely resets the configuration
	 */
	public function reset() {
		$this->placeholders						= array();
		$this->placeholder_config				= array();
		$this->parent_filters					= array( 'blockquote', 'table', '#toc_container', '#ez-toc-container' );
		$this->paragraph_tags					= array( 'p', 'li' );
		$this->excerpt_tags						= array( 'p' );
		$this->sidebar_id						= 'sidebar-1';
		$this->skip_word_count					= -1;
		$this->user_roles_with_ads_disabled 	= array();
		$this->exclude_pages					= array();
		$this->last_updated						= 0;
		$this->enable_adpos_integration			= false;
	}

	/**
	 * Clears the placeholder configuration
	 */
	public function resetPlaceholderConfigs() {
		$this->placeholders			= array();
		$this->placeholder_config	= array();
	}

	/**
	 * Load configuration from Wordpress options
	 */
	public static function load() {
		// Fetch configuration from storage
		$encoded = \get_option( 'ez_adtester_config' );

		// If no configuration found, return empty configuration
		if ( $encoded == '' ) {
			return new Ezoic_AdTester_Config();
		}

		// Decode configuration
		$decoded = \base64_decode( $encoded );

		// Deserialize configuration
		$config = \unserialize( $decoded );

		// Upgrade if needed
		Ezoic_AdTester_Config::upgrade( $config );

		return $config;
	}

	/**
	 * Store configuration in Wordpress options
	 */
	public static function store( $config ) {
		$config->last_updated = time();

		// Serialize configuration
		$serialized = \serialize($config);

		// Encode configuration
		$encoded = base64_encode( $serialized );

		// Store configuration
		\update_option( 'ez_adtester_config', $encoded );
	}

	/**
	 * Synchronizes the configuration with the backend server (if needed)
	 */
	public function sync() {
		$requestURL = Ezoic_AdTester_Config::config_sync_endpoint . '?d=' . Ezoic_Integration_Request_Utils::get_domain();

		// This fixes a bug with the consistency of the data-type of skip_word_count
		if ( \is_string( $this->skip_word_count ) ) {
			$this->skip_word_count = \intval( $this->skip_word_count );
		}

		$payload = json_encode( $this );
		$requestArgs = array(
			'method' => 'POST',
			'timeout' => 45,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Content-Length' => strlen( $payload )
			),
			'body' => $payload
		);

		if ( Ezoic_Cdn::ezoic_cdn_api_key() != null ) {
			$token = Ezoic_Cdn::ezoic_cdn_api_key();
			$requestURL .= '&developerKey=' . $token;
		} else {
			$token = Ezoic_Integration_Authentication::get_token();
		}

		// If there is no token (failed authentication), then exit early
		if ( $token == '' ) {
			return;
		}

		// Set auth token value
		$requestArgs['headers']['Authorization'] = 'Bearer ' . $token;
		
		$response = wp_remote_post( $requestURL, $requestArgs );

		if ( is_wp_error( $response ) ) {
			error_log( 'Unable to sync configuration: ' . $response->get_error_message() );
			//throw new \Exception( $response->get_error_message() );
		}

		$responseBody = wp_remote_retrieve_body( $response );
		$parsed_config = json_decode( $responseBody );

		if ( !isset( $parsed_config ) ) {
			return;
		}

		if ( intval( $parsed_config->last_updated ) > intval( $this->last_updated ) ) {
			$this->copy_config_from_array( $parsed_config );

			Ezoic_AdTester_Config::store( $this );
		}
	}

	/**
	 * Merges an object containing updated configuration with existing configuration
	 */
	private function copy_config_from_array( $config ) {
		$this->version = $config->version;
		$this->last_updated = $config->last_updated;
		$this->paragraph_tags = $config->paragraph_tags;
		$this->excerpt_tags = $config->excerpt_tags;
		$this->parent_filters = $config->parent_filters;
		$this->skip_word_count = $config->skip_word_count;
		$this->sidebar_id = $config->sidebar_id;

		// $phConfigs = array();

		// // Index incoming placeholder configurations
		// foreach ( $config->placeholder_config as $phConfig ) {
		// 	$key = $phConfig->page_type . '_' . $this->placeholders[ $phConfig->placeholder_id ]->position_type;

		// 	$phConfigs[ $key ] = $phConfig;
		// }

		// // Update any placeholder configuration changes
		// foreach ( $this->placeholder_config as $oldConfig ) {
		// 	$key = $oldConfig->page_type . '_' . $this->placeholders[ $oldConfig->placeholder_id ]->position_type;
		// 	$newConfig = $phConfigs[ $key ];

		// 	if ( $oldConfig->display !== $newConfig->display || $oldConfig->display_option !== $newConfig->display_option ) {
		// 		$oldConfig->display = $newConfig->display;
		// 		$oldConfig->display_option = $newConfig->display_option;
		// 	}
		// }
	}

	/**
	 * Upgrade configuration object
	 */
	private static function upgrade( $config ) {

		$version = \intval( $config->version );

		// Upgrade from version 1 to 2
		if ( $version === 1 ) {

			// Backup config
			// Serialize configuration
			$serialized = \serialize($config);

			// Encode configuration
			$encoded = base64_encode( $serialized );

			// Store configuration
			\update_option( 'ez_adtester_config_bak', $encoded );

			$config->version = '2';

			if ( isset( $config->placeholder_config ) && \is_array( $config->placeholder_config ) ) {
				// Attempt to convert XPath's to CSS selectors
				foreach ( $config->placeholder_config as $ph_config ) {
					if ( $ph_config->display === 'before_element' || $ph_config->display === 'after_element' ) {
						// Trim leading '/'
						$ph_config->display_option = \substr( $ph_config->display_option, 1 );

						// Replace '/' with '>'
						$ph_config->display_option = \str_ireplace( '/', ' > ', $ph_config->display_option );

						// Remove [1]
						$ph_config->display_option = \str_ireplace( '[1]', '', $ph_config->display_option );

						// Replace [\d] with :eq(\d)
						$ph_config->display_option = \preg_replace( '/\[\d+\]/i', ':eq($0)', $ph_config->display_option );
					}
				}
			}

			Ezoic_AdTester_Config::store( $config );
		}
	}
}
