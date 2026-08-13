<?php

namespace Ezoic_Namespace;

class Ezoic_Integration_Ad_Settings
{
	private $adtester;

	public function __construct() {}

	private function initialize()
	{
		// Load config
		$this->adtester = new Ezoic_AdTester();

		$this->initialize_ad_settings();
	}

	/**
	 * Fetches ad data from backend and initializes configuraiton if needed
	 */
	public function initialize_ad_settings()
	{
		// Only initialize if on the settings tab
		if (!isset($_GET['tab']) || (isset($_GET['tab']) && $_GET['tab'] !== 'ad_settings')) {
			return;
		}

		$this->adtester->initialize_config();
	}

	/**
	 * Register RESTful endpoint for saving configuration
	 */
	public function register_rest()
	{
		// Save placeholder rule
		\register_rest_route('ezoic/v1', 'save-rule', array(
			'methods'					=> \WP_REST_SERVER::CREATABLE,
			'callback'					=> array($this, 'save_rule'),
			'args'						=> array(),
			'permission_callback'	=> function () {
				return $this->validate_user();
			},
			'show_in_index'			=> false
		));

		// Save general configuration
		\register_rest_route('ezoic/v1', 'save-settings', array(
			'methods'					=> \WP_REST_SERVER::CREATABLE,
			'callback'					=> array($this, 'save_settings'),
			'args'						=> array(),
			'permission_callback'	=> function () {
				return $this->validate_user();
			},
			'show_in_index'			=> false
		));

		// Reset general configuration
		\register_rest_route('ezoic/v1', 'reset-settings', array(
			'methods'					=> \WP_REST_SERVER::CREATABLE,
			'callback'					=> array($this, 'reset_settings'),
			'args'						=> array(),
			'permission_callback'	=> function () {
				return $this->validate_user();
			},
			'show_in_index'			=> false
		));

		// Clear default configuration
		\register_rest_route('ezoic/v1', 'clear-defaults', array(
			'methods'					=> \WP_REST_SERVER::CREATABLE,
			'callback'					=> array($this, 'clear_default_configuration'),
			'args'						=> array(),
			'permission_callback'	=> function () {
				return $this->validate_user();
			},
			'show_in_index'			=> false
		));

		// Get placeholder data
		\register_rest_route('ezoic/v1', 'retrieve-placeholders', array(
			'methods'					=> \WP_REST_SERVER::READABLE,
			'callback'					=> array($this, 'retrieve_placeholders'),
			'args'						=> array(),
			'permission_callback'	=> function () {
				return $this->validate_user();
			},
			'show_in_index'			=> false
		));

		// Domain status
		\register_rest_route('ezoic/v1', 'domain-status', array(
			'methods'					=> \WP_REST_SERVER::READABLE,
			'callback'					=> array($this, 'domain_status'),
			'args'						=> array(),
			'permission_callback'	=> function () {
				return $this->validate_user();
			},
			'show_in_index'			=> false
		));

		// Force Generate
		\register_rest_route('ezoic/v1', 'force-generate', array(
			'methods'					=> \WP_REST_SERVER::CREATABLE,
			'callback'					=> array($this, 'force_generate'),
			'args'						=> array(),
			'permission_callback'	=> function () {
				return $this->validate_user();
			},
			'show_in_index'			=> false
		));

		// Get fresh configuration data
		\register_rest_route('ezoic/v1', 'get-config', array(
			'methods'					=> \WP_REST_SERVER::READABLE,
			'callback'					=> array($this, 'get_config'),
			'args'						=> array(),
			'permission_callback'	=> function () {
				return $this->validate_user();
			},
			'show_in_index'			=> false
		));
	}

	/**
	 * Ensures that the current request is from a user with the administrator role
	 */
	private function validate_user()
	{
		$data = \get_userdata(\get_current_user_id());

		// Verify there is a user and that they have roles
		if (!$data || !$data->roles || !is_array($data->roles)) {
			return false;
		}

		$role = $data->roles;
		return (\is_super_admin() || (in_array('administrator', $role)));
	}

	/**
	 * Force default ad placeholders to be generated
	 */
	public function force_generate()
	{
		$this->adtester = new Ezoic_AdTester();
		$this->adtester->force_generate_placeholders();
	}

	/**
	 * Obtains domain status, including placeholder creation status
	 */
	public function domain_status()
	{
		$status = new Ezoic_AdTester_Domain_Status(true);
		$status->fetch();

		$plugin_initialized = \get_option('ez_ad_initialized');
		if ($plugin_initialized && (intval($plugin_initialized) + 5 * 60) > \time()) {
			$status->placeholders_created = false;
		}

		return $status;
	}


	/**
	 * Retrieves placeholders w/ optional date range filter
	 */
	public function retrieve_placeholders()
	{
		$this->adtester = new Ezoic_AdTester();

		$dateFrom = false;
		$dateTo = false;
		//make sure both of these are set properly, expecting a positive timestamp integer,
		//we don't want to allow willy-nilly vals through which could lead to xss/db inject
		if (
			//date from
			array_key_exists('dateFrom', $_GET) &&
			isset($_GET['dateFrom']) &&
			is_numeric($_GET['dateFrom']) &&
			$_GET['dateFrom'] > 0 &&

			//date to
			array_key_exists('dateTo', $_GET) &&
			isset($_GET['dateTo']) &&
			is_numeric($_GET['dateTo']) &&
			$_GET['dateTo'] > 0
		) {
			$dateFrom = $_GET['dateFrom'];
			$dateTo = $_GET['dateTo'];
		}

		$response = $this->adtester->retrieve_placeholders($dateFrom, $dateTo);

		// Add activePlacements for debugging
		if ($response instanceof \WP_REST_Response && $response->get_status() === 200) {
			$data = $response->get_data();
			if (isset($data['body_response'])) {
				$body = json_decode($data['body_response'], true);
				if ($body && is_array($body)) {
					// Add comprehensive debug information
					$debug_info = array(
						'activePlacements' => $this->adtester->config->active_placements,
						'enablePlacementIdSelection' => $this->adtester->config->enable_placement_id_selection,
						'totalPlaceholders' => count($this->adtester->config->placeholders),
						'wpPlaceholders' => array(),
						'allPositionTypes' => array()
					);

					// Analyze wp_* placeholders
					foreach ($this->adtester->config->placeholders as $placeholder) {
						$debug_info['allPositionTypes'][] = array(
							'id' => $placeholder->id,
							'positionId' => $placeholder->position_id,
							'positionType' => $placeholder->position_type,
							'name' => $placeholder->name
						);

						if (strpos($placeholder->name, 'wp_') === 0) {
							$debug_info['wpPlaceholders'][] = array(
								'id' => $placeholder->id,
								'positionId' => $placeholder->position_id,
								'positionType' => $placeholder->position_type,
								'name' => $placeholder->name
							);
						}
					}

					$body['debugInfo'] = $debug_info;
					$data['body_response'] = json_encode($body);
					$response->set_data($data);
				}
			}
		}

		return $response;
	}

	/**
	 * Clears recommended configuration and regenerates missing wp_ placeholder configs
	 */
	public function clear_default_configuration()
	{
		$this->initialize();

		$configs = $this->adtester->config->placeholder_config;

		$new_config = [];
		foreach ($configs as $config) {
			if (!$config->is_default) {
				$new_config[] = $config;
			}
		}

		$this->adtester->config->placeholder_config = $new_config;

		// Reset active placements to original API data
		$this->reset_active_placements_to_original();

		// After clearing defaults, regenerate missing configurations for wp_ placeholders
		try {
			$generated = $this->adtester->generate_missing_wp_configs();
			if ($generated) {
				//Ezoic_AdTester::log('Generated missing wp_ placeholder configurations after clearing defaults');
			}
		} catch (\Exception $e) {
			Ezoic_AdTester::log('Error generating missing wp_ configs: ' . $e->getMessage());
		}

		$this->adtester->update_config();
	}

	/**
	 * Reset active placements to match original API placeholder data
	 */
	private function reset_active_placements_to_original()
	{
		// Clear existing active placements
		$this->adtester->config->active_placements = [];

		// Get all placeholders from API
		$placeholders = $this->adtester->config->placeholders;

		// If no placeholders available, try to fetch them from API
		if (!$placeholders || !is_array($placeholders)) {
			$this->adtester->initialize_config(); // Force refresh
			$placeholders = $this->adtester->config->placeholders;
		}

		if (!$placeholders || !is_array($placeholders)) {
			Ezoic_AdTester::log('Failed to get placeholders for resetting active placements');
			return;
		}

		// Build a mapping using wp_* placeholders to get the correct position IDs
		// This ensures we use the original WordPress placeholder mappings
		foreach ($placeholders as $placeholder) {
			// Only use wp_* placeholders to establish the correct mappings
			if (strpos($placeholder->name, 'wp_') === 0) {
				$position_type = $placeholder->position_type;
				$this->adtester->config->active_placements[$position_type] = $placeholder->position_id;
			}
		}
	}

	/**
	 * Resets any configuration set for ad placeholders
	 */
	public function reset_settings()
	{
		$this->initialize();

		// Reset configuration (this will clear active_placements and other settings)
		$this->adtester->config->reset();
		Ezoic_AdTester_Placeholder::set_reserve_placeholder_space_enabled(false);
		Ezoic_AdTester_Placeholder::set_reserve_all_placeholder_space_enabled(false);

		// Reset active placements to original API data (this will rebuild active_placements)
		$this->reset_active_placements_to_original();

		// Store config
		$this->adtester->update_config();

		// Regenerate placeholders after reset
		$this->adtester->force_generate_placeholders();
	}

	/**
	 * Save placeholder configuration
	 */
	public function save_rule($request_data)
	{
		$this->initialize();

		// Parse payload
		$payload = \json_decode($request_data->get_body());

		// Load config
		$config = $this->adtester->config;

		// Handle Position ID selection logic
		if (isset($config->enable_placement_id_selection) && $config->enable_placement_id_selection === true) {
			// Get the placeholder that was just updated
			$current_placeholder = isset($config->placeholders[$payload->placeholderId]) ? $config->placeholders[$payload->placeholderId] : null;

			if ($current_placeholder && $payload->display !== 'disabled') {
				// Update active placement for this position type
				$position_type = $current_placeholder->position_type;

				// Use the new position ID from the payload if it was changed
				$position_id = isset($payload->placeholderPositionId) ? $payload->placeholderPositionId : $current_placeholder->position_id;

				$config->set_active_placement($position_type, $position_id);
			} else if ($current_placeholder && $payload->display === 'disabled') {
				// Remove active placement for this position type
				$position_type = $current_placeholder->position_type;
				unset($config->active_placements[$position_type]);
			}
		}

		// Find the specific placeholder configuration to modify
		$edit_config = null;
		foreach ($config->placeholder_config as $ph_config) {
			if ($ph_config->page_type == $payload->pageType && $ph_config->placeholder_id == $payload->placeholderId) {
				$edit_config = $ph_config;
			}
		}

		// If no configuration was found, create a new one
		if (!is_null($edit_config)) {
			$edit_config->display = $payload->display;
			$edit_config->display_option = $payload->displayOption;
		} else {
			// Update existing configuration
			$edit_config = new Ezoic_AdTester_Placeholder_Config($payload->pageType, $payload->placeholderId, $payload->display, $payload->displayOption, false);
			$config->placeholder_config[] = $edit_config;
		}

		//Ezoic_AdTester::log('saving new rule');

		// Save configuration
		$this->adtester->update_config();

		// Flush cache
		$cdn = new Ezoic_Cdn();
		$cdn->ezoic_cdn_purge($cdn->ezoic_cdn_get_domain());
	}

	/**
	 * Saves general (top-level) configuration changes
	 */
	public function save_settings($request_data)
	{
		$this->initialize();

		// Parse payload
		$payload = \json_decode($request_data->get_body());

		// Load config
		$config = $this->adtester->config;

		// Update data
		$config->paragraph_tags					= $payload->paragraphTags;
		$config->excerpt_tags					= $payload->excerptTags;
		$config->parent_filters					= $payload->excludeParents;
		$config->exclude_class_list				= $payload->excludeClasses;
		$config->sidebar_id						= $payload->sidebarId;
		$config->user_roles_with_ads_disabled 	= $payload->userRolesWithAdsDisabled;
		$config->meta_tags 						= $payload->metaTags;
		$config->exclude_urls					= $payload->excludeUrls;

		if (isset($payload->reservePlaceholderSpace)) {
			Ezoic_AdTester_Placeholder::set_reserve_placeholder_space_enabled($payload->reservePlaceholderSpace);
		}
		if (isset($payload->reserveAllPlaceholderSpace)) {
			Ezoic_AdTester_Placeholder::set_reserve_all_placeholder_space_enabled($payload->reserveAllPlaceholderSpace);
		}

		// Check if JS integration with WP placeholders is enabled
		$js_integration_enabled = get_option('ezoic_js_integration_enabled', false);
		$js_options = get_option('ezoic_js_integration_options', array());
		$js_use_wp_placeholders = isset($js_options['js_use_wp_placeholders']) && $js_options['js_use_wp_placeholders'];

		// If JS integration with WP placeholders is enabled, force disable Placement Service Integration
		if ($js_integration_enabled && $js_use_wp_placeholders) {
			$config->enable_adpos_integration = false;
		} else {
			$config->enable_adpos_integration = $payload->enableAdPos;
		}

		// Check if Placement ID Selection is being disabled
		$was_enabled = $config->enable_placement_id_selection;
		$config->enable_placement_id_selection	= $payload->enablePlacementIdSelection;

		// If Placement ID Selection is being turned off, reset active placements to default wp_* placeholders
		if ($was_enabled && !$payload->enablePlacementIdSelection) {
			$config->initialize_active_placements(true);

			// Also clean up any saved configurations for non-wp_* placeholders
			$cleaned_configs = array();
			foreach ($config->placeholder_config as $ph_config) {
				$placeholder = isset($config->placeholders[$ph_config->placeholder_id]) ? $config->placeholders[$ph_config->placeholder_id] : null;
				if ($placeholder && strpos($placeholder->name, 'wp_') === 0) {
					// Keep configurations for wp_* placeholders only
					$cleaned_configs[] = $ph_config;
				}
			}
			$config->placeholder_config = $cleaned_configs;
		}

		// If Placement ID Selection is being turned on, initialize active placements if needed
		if (!$was_enabled && $payload->enablePlacementIdSelection) {
			$config->initialize_active_placements(false);
		}

		if ($payload->excludeWordCount !== '' && \is_numeric($payload->excludeWordCount) && $payload->excludeWordCount >= 0) {
			$config->skip_word_count = $payload->excludeWordCount;
		}

		//Ezoic_AdTester::log( 'saving general settings' );

		// Store configuration
		$this->adtester->update_config();

		// Flush cache
		$cdn = new Ezoic_Cdn();
		$cdn->ezoic_cdn_purge($cdn->ezoic_cdn_get_domain());
	}

	/**
	 * Renders the settings page for ad placeholders
	 */
	public function render_settings_page_content()
	{
		$this->initialize();

		// Fetch domain status
		$domain_status = new Ezoic_AdTester_Domain_Status(true);

		// Load Vue-based interface
		wp_enqueue_script('ezoic_integration', plugin_dir_url(__FILE__) . 'js/ad-settings.js', array(), rand(), true);

		// Remove script which causes vuejs conflicts
		wp_deregister_script('js_files_for_wp_admin');

		// Fetch recent article for element picker, if needed
		$recent_post_url = '/';
		$recent_post_args = array('numberposts' => 1);
		$recent_posts = \wp_get_recent_posts($recent_post_args);
		if (\count($recent_posts) > 0) {
			$recent_post_url = \get_permalink($recent_posts[0]['ID']);
		}

		// Disable ads on page
		if (\strpos($recent_post_url, '?')) {
			$recent_post_url .= '&ez_orig=1&ez_js_disable=1';
		} else {
			$recent_post_url .= '?ez_orig=1&ez_js_disable=1';
		}
		$placeholderArray = array();
		foreach ($this->adtester->config->placeholders as $placeholder) {
			$revenue = null;
			if (isset($this->adtester->revenues[$placeholder->position_id]->revenue_percentage)) {
				$revenue = $this->adtester->revenues[$placeholder->position_id]->revenue_percentage;
			}

			$placeholderArray[] = array(
				'id' => $placeholder->id,
				'positionId' => $placeholder->position_id,
				'positionType' => $placeholder->position_type,
				'name' => $placeholder->name,
				'revenue' => $revenue
			);
		}

		$configArray = array();
		if (!empty($this->adtester->config->placeholder_config)) {
			foreach ($this->adtester->config->placeholder_config as $config) {
				$configArray[] = array(
					'pageType' => $config->page_type,
					'placeholderId' => $config->placeholder_id,
					'display' => $config->display,
					'displayOption' => $config->display_option
				);
			}
		}

		$plugin_initialized = \get_option('ez_ad_initialized');
		$placeholders_generated = $domain_status->placeholders_created;
		if ($plugin_initialized && (intval($plugin_initialized) + 5 * 60) > \time()) {
			$placeholders_generated = false;
		}

		$exclude_word_count = isset($this->adtester->config->skip_word_count) && $this->adtester->config->skip_word_count !== null
			? $this->adtester->config->skip_word_count
			: 10;

		$payload = array(
			'status' => array(
				'hasError' => (bool) $domain_status->has_error,
				'errorMessage' => $domain_status->error_message,
				'monetizationEligible' => $domain_status->monetization_eligible === true,
				'placeholdersCreated' => $placeholders_generated === true,
				'placeholderCountOther' => $domain_status->placeholder_count_other,
				'placeholderCountWp' => $domain_status->placeholder_count_wp,
			),
			'endpoints' => array(
				'saveRule' => \get_rest_url(null, 'ezoic/v1/save-rule'),
				'saveSettings' => \get_rest_url(null, 'ezoic/v1/save-settings'),
				'resetSettings' => \get_rest_url(null, 'ezoic/v1/reset-settings'),
				'retrievePlaceholders' => \get_rest_url(null, 'ezoic/v1/retrieve-placeholders'),
				'clearDefaults' => \get_rest_url(null, 'ezoic/v1/clear-defaults'),
				'domainStatus' => \get_rest_url(null, 'ezoic/v1/domain-status'),
				'forceGenerate' => \get_rest_url(null, 'ezoic/v1/force-generate'),
				'getConfig' => \get_rest_url(null, 'ezoic/v1/get-config'),
			),
			'nonce' => \wp_create_nonce('wp_rest'),
			'baseURL' => \plugin_dir_url(__FILE__),
			'recentPostUrl' => $recent_post_url,
			'pageTypes' => $this->placeholder_page_types(),
			'placeholderConfig' => $configArray,
			'placeholders' => $placeholderArray,
			'revenues' => $this->adtester->revenues,
			'general' => array(
				'activePlacements' => $this->adtester->config->active_placements,
				'adminUrl' => \admin_url(),
				'availableSidebars' => $this->get_wordpress_sidebars(),
				'enableAdPos' => (bool) $this->adtester->config->enable_adpos_integration,
				'enablePlacementIdSelection' => (bool) $this->adtester->config->enable_placement_id_selection,
				'excerptTags' => (isset($this->adtester->config->excerpt_tags) && is_array($this->adtester->config->excerpt_tags))
					? $this->adtester->config->excerpt_tags
					: array(),
				'excludeClasses' => (isset($this->adtester->config->exclude_class_list) && is_array($this->adtester->config->exclude_class_list))
					? $this->adtester->config->exclude_class_list
					: array(),
				'excludeParents' => (isset($this->adtester->config->parent_filters) && is_array($this->adtester->config->parent_filters))
					? $this->adtester->config->parent_filters
					: array(),
				'excludeUrls' => isset($this->adtester->config->exclude_urls)
					? $this->adtester->config->exclude_urls
					: array(),
				'excludeWordCount' => (string) $exclude_word_count,
				'jsIntegrationEnabled' => (bool) get_option('ezoic_js_integration_enabled', false),
				'metaTags' => isset($this->adtester->config->meta_tags)
					? $this->adtester->config->meta_tags
					: array(),
				'paragraphTags' => (isset($this->adtester->config->paragraph_tags) && is_array($this->adtester->config->paragraph_tags))
					? $this->adtester->config->paragraph_tags
					: array(),
				'reservePlaceholderSpace' => Ezoic_AdTester_Placeholder::is_reserve_placeholder_space_enabled(false),
				'reserveAllPlaceholderSpace' => Ezoic_AdTester_Placeholder::is_reserve_all_placeholder_space_enabled(),
				'sidebarId' => (string) $this->adtester->config->sidebar_id,
				'userRoles' => $this->get_user_roles(),
				'userRolesWithAdsDisabled' => isset($this->adtester->config->user_roles_with_ads_disabled)
					? $this->adtester->config->user_roles_with_ads_disabled
					: array(),
			),
		);

		$payload_json = \json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($payload_json === false) {
			error_log('Ezoic Ad Settings: failed to encode admin payload: ' . json_last_error_msg());
			$payload_json = '{}';
		}
		?>
		<link href="//fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
		<link href="https://cdn.jsdelivr.net/npm/@mdi/font@5.x/css/materialdesignicons.min.css" rel="stylesheet">
		<script id="placeholder-payload" type="application/json"><?php echo $payload_json; ?></script>
		<div id="app"></div>
		<?php
	}

	private function placeholder_page_types()
	{
		$content_options = array(
			array('id' => 'disabled', 'name' => 'Disabled'),
			array('id' => 'before_content', 'name' => 'Before Content', 'hasOption' => false),
			array('id' => 'after_content', 'name' => 'After Content', 'hasOption' => false),
			array('id' => 'before_paragraph', 'name' => 'Before Paragraph', 'hasOption' => true),
			array('id' => 'after_paragraph', 'name' => 'After Paragraph', 'hasOption' => true),
			array('id' => 'after_widget', 'name' => 'After Widget', 'hasOption' => true),
			array('id' => 'before_element', 'name' => 'Before HTML Element', 'hasOption' => true),
			array('id' => 'after_element', 'name' => 'After HTML Element', 'hasOption' => true),
		);
		$excerpt_options = array(
			array('id' => 'disabled', 'name' => 'Disabled'),
			array('id' => 'before_paragraph', 'name' => 'Before Paragraph', 'hasOption' => true),
			array('id' => 'after_paragraph', 'name' => 'After Paragraph', 'hasOption' => true),
			array('id' => 'before_excerpt', 'name' => 'Before Excerpt', 'hasOption' => true),
			array('id' => 'after_excerpt', 'name' => 'After Excerpt', 'hasOption' => true),
			array('id' => 'after_widget', 'name' => 'After Widget', 'hasOption' => true),
			array('id' => 'before_element', 'name' => 'Before HTML Element', 'hasOption' => true),
			array('id' => 'after_element', 'name' => 'After HTML Element', 'hasOption' => true),
		);

		return array(
			array('type' => 'post', 'name' => 'Post', 'displayOptions' => $content_options),
			array('type' => 'page', 'name' => 'Page', 'displayOptions' => $content_options),
			array('type' => 'home', 'name' => 'Home Page', 'displayOptions' => $excerpt_options),
			array('type' => 'category', 'name' => 'Category', 'displayOptions' => $excerpt_options),
		);
	}

	/**
	 * Returns fresh configuration data for frontend refresh
	 */
	public function get_config()
	{
		$this->initialize();

		// Build placeholder config array
		$configArray = array();
		if (!empty($this->adtester->config->placeholder_config)) {
			foreach ($this->adtester->config->placeholder_config as $config) {
				$configArray[] = array(
					'pageType' => $config->page_type,
					'placeholderId' => $config->placeholder_id,
					'display' => $config->display,
					'displayOption' => $config->display_option
				);
			}
		}

		// Build placeholders array
		$placeholderArray = array();
		foreach ($this->adtester->config->placeholders as $placeholder) {
			$revenue = null;
			if (isset($this->adtester->revenues[$placeholder->position_id]->revenue_percentage)) {
				$revenue = $this->adtester->revenues[$placeholder->position_id]->revenue_percentage;
			}

			// Always send the original Position ID to frontend - let frontend handle filtering
			$placeholderArray[] = array(
				'id' => $placeholder->id,
				'positionId' => $placeholder->position_id,
				'positionType' => $placeholder->position_type,
				'name' => $placeholder->name,
				'revenue' => $revenue
			);
		}

		return array(
			'placeholderConfig' => $configArray,
			'placeholders' => $placeholderArray,
			'revenues' => $this->adtester->revenues,
			'activePlacements' => $this->adtester->config->active_placements,
			'reservePlaceholderSpace' => Ezoic_AdTester_Placeholder::is_reserve_placeholder_space_enabled(false),
			'reserveAllPlaceholderSpace' => Ezoic_AdTester_Placeholder::is_reserve_all_placeholder_space_enabled()
		);
	}

	/**
	 * Fetches a list of role names from the global variable
	 * $wp_roles and returns them sorted alphabetically.
	 *
	 * @return array
	 */
	public function get_user_roles()
	{
		global $wp_roles;

		if (
			!isset($wp_roles)
			|| !isset($wp_roles->role_names)
		) {
			return array();
		}

		$userRoles = array();
		foreach ($wp_roles->role_names as $slug => $displayName) {
			$userRoles[] = array(
				'value' => $slug,
				'text'  => $displayName,
			);
		}

		\usort($userRoles, function ($a, $b) {
			return \strcmp($a['text'], $b['text']);
		});

		return $userRoles;
	}

	public function get_wordpress_sidebars()
	{
		global $wp_registered_sidebars;

		$sidebars = array();

		if (isset($wp_registered_sidebars) && is_array($wp_registered_sidebars)) {
			foreach ($wp_registered_sidebars as $id => $sidebar) {
				$sidebars[] = array(
					'id' => $id,
					'name' => $sidebar['name']
				);
			}
		}

		return $sidebars;
	}
}
