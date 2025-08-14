<?php

namespace Ezoic_Namespace;

abstract class Ezoic_AdTester_Inserter
{
	protected $config;
	protected $page_type;

	protected function __construct($config)
	{
		$this->config = $config;

		// Figure out page type using centralized helper
		$this->page_type = Ezoic_AdPos::get_current_page_type();
	}

	/**
	 * Filters placeholder configurations for the current page type, applying Position ID selection logic
	 *
	 * @return array Array of placeholder configurations indexed by placeholder_id
	 */
	protected function get_filtered_placeholder_rules()
	{
		$rules = array();

		// Track which position types we've already processed across ALL posts on this page
		static $global_processed_position_types = array();

		// Only log this once per page load to avoid spam
		static $logged_page_info = false;
		if (!$logged_page_info) {
			$page_type_counts = array();
			foreach ($this->config->placeholder_config as $config) {
				$page_type_counts[$config->page_type] = (isset($page_type_counts[$config->page_type]) ? $page_type_counts[$config->page_type] : 0) + 1;
			}

			$matching_placements = isset($page_type_counts[$this->page_type]) ? $page_type_counts[$this->page_type] : 0;

			Ezoic_Integration_Logger::console_debug(
				"Ad Tester running - Page Type: {$this->page_type}, Active Placements: {$matching_placements}",
				'Ad System',
				'info'
			);
			$logged_page_info = true;
		}

		foreach ($this->config->placeholder_config as $ph_config) {
			if ($ph_config->page_type != $this->page_type) {
				continue;
			}

			$placeholder = isset($this->config->placeholders[$ph_config->placeholder_id]) ? $this->config->placeholders[$ph_config->placeholder_id] : null;
			if (!$placeholder) {
				Ezoic_Integration_Logger::console_debug(
					"Placement skipped - placeholder not found. Placeholder ID: {$ph_config->placeholder_id}",
					'Ad System'
				);
				continue;
			}

			$position_type = $placeholder->position_type;

			// Skip if we've already processed this position type globally (prevents duplicates across all posts)
			if (isset($global_processed_position_types[$position_type])) {
				continue;
			}

			// Check if this placeholder should be included
			$should_include = false;
			if (isset($this->config->enable_placement_id_selection) && $this->config->enable_placement_id_selection === true) {
				// Position ID selection enabled: only include if this is the active placement
				$position_id = $placeholder->position_id;
				$active_position_id = $this->config->get_active_placement($position_type);
				$should_include = ($active_position_id && $active_position_id == $position_id);

				if (!$should_include) {
					Ezoic_Integration_Logger::console_debug(
						"Placement skipped - not the active placement. Position ID: {$position_id}, Active Position ID: {$active_position_id}, Position Type: {$position_type}",
						'Ad System'
					);
				}
			} else {
				// Position ID selection disabled: include all valid placeholders
				$should_include = true;
			}

			if ($should_include) {
				$rules[$ph_config->placeholder_id] = $ph_config;
				$global_processed_position_types[$position_type] = true;
				Ezoic_Integration_Logger::console_debug(
					"Placement {$placeholder->position_id} included for insertion. Position Type: {$position_type}",
					'Ad System',
					'info',
					$placeholder->position_id
				);
			}
		}

		return $rules;
	}
}
