<?php

namespace Ezoic_Namespace;

abstract class Ezoic_AdTester_Inserter
{
	protected $config;
	protected $page_type;

	protected function __construct($config)
	{
		$this->config = $config;

		// Figure out page type:

		// When the front page of the site is displayed, regardless of whether
		// it is set to show posts or a static page.
		if (\is_front_page()) {
			$this->page_type = 'home';
		}

		// When a Category archive page is being displayed, or when the main
		// blog page is being displayed. If your home page has been set to a
		// Static Page instead, then this will only prove true on the page
		// which you set as the "Posts page" in Settings > Reading.
		elseif (\is_category() || \is_home()) {
			$this->page_type = 'category';
		}

		// When any single Post (or attachment, or custom Post Type) is being
		// displayed, or archive pages which include category, tag, author, date,
		// custom post type, and custom taxonomy based archives is being displayed.
		elseif (\is_single() || \is_archive()) {
			$this->page_type = 'post';
		}

		// When any Page is being displayed. This refers to WordPress Pages,
		// not any generic webpage from your blog
		elseif (\is_page()) {
			$this->page_type = 'page';
		}
	}

	/**
	 * Filters placeholder configurations for the current page type, applying Position ID selection logic
	 *
	 * @return array Array of placeholder configurations indexed by placeholder_id
	 */
	protected function get_filtered_placeholder_rules()
	{
		$rules = array();

		foreach ($this->config->placeholder_config as $ph_config) {
			if ($ph_config->page_type == $this->page_type) {
				// If Position ID selection is enabled, only include placeholders that are active for their position type
				if (isset($this->config->enable_placement_id_selection) && $this->config->enable_placement_id_selection === true) {
					$placeholder = isset($this->config->placeholders[$ph_config->placeholder_id]) ? $this->config->placeholders[$ph_config->placeholder_id] : null;
					if ($placeholder) {
						$position_type = $placeholder->position_type;
						$position_id = $placeholder->position_id;
						$active_position_id = $this->config->get_active_placement($position_type);

						// Only include this placeholder if it's the active one for this position type
						if ($active_position_id && $active_position_id == $position_id) {
							$rules[$ph_config->placeholder_id] = $ph_config;
						}
					}
				} else {
					// Original logic when Position ID selection is disabled
					$rules[$ph_config->placeholder_id] = $ph_config;
				}
			}
		}

		return $rules;
	}
}
