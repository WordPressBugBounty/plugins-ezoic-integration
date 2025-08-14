<?php

namespace Ezoic_Namespace;

// <ins class="ezoic-adpos-sidebar" data-loc="top" />

// Only create custom widget if WP_Widget is supported
if (class_exists('WP_Widget')) {
	class Ezoic_AdPos_Widget extends \WP_Widget
	{
		public function __construct()
		{
			$widget_options = array(
				'classname' => 'Ezoic_Namespace\\Ezoic_AdPos_Widget',
				'description' => 'Ezoic Placement Service Marker'
			);

			parent::__construct('ezoic_adpos_widget', 'Ezoic AdPos Widget', $widget_options);
		}

		public function widget($args, $instance)
		{
			if (isset($instance['position'])) {
				// JavaScript integration: use AdTester rules
				$sidebar_id = isset($args['id']) ? $args['id'] : 'sidebar';
				$placeholder = $this->get_sidebar_placeholder($sidebar_id, $instance);

				if ($placeholder !== null) {
					$embed_code = $placeholder->embed_code();
					echo $embed_code;

					// Track insertion for verification
					if (preg_match('/id="ezoic-pub-ad-placeholder-(\d+)"/', $embed_code, $matches)) {
						$position_id = $matches[1];
						Ezoic_Integration_Logger::track_insertion($position_id);
					}
				}
			} else {
				// Server-side processing: use location markers
				$location = isset($instance['location']) ? $instance['location'] : 'top';
				echo "<ins class='ezoic-adpos-sidebar' style='display:none !important;visibility:hidden !important;height:0 !important;width:0 !important;' data-loc='" . $location . "'></ins>";

			}
		}

		private function get_sidebar_placeholder($sidebar_id, $instance)
		{
			// Get active placement IDs from AdTester config
			$config = Ezoic_AdTester_Config::load();

			// Get the widget position from the stored instance data
			$widget_position = isset($instance['position']) ? $instance['position'] : 0;

			$page_type = Ezoic_AdPos::get_current_page_type();

			// Find active placement using same logic as AdTester sidebar inserter
			// Build rules array first (same as get_rules() in AdTester)
			$rules = array();
			foreach ($config->placeholder_config as $ph_config) {
				if (
					$ph_config->page_type == $page_type &&        // Current page type
					$ph_config->display != 'disabled' &&          // Rule is enabled
					$ph_config->display == 'after_widget'         // Rule is a sidebar rule
				) {
					$rules[(int) $ph_config->display_option] = $config->placeholders[$ph_config->placeholder_id];
				}
			}

			// Sort rules by position
			ksort($rules, SORT_NUMERIC);

			// Return the placeholder for this widget position
			if (isset($rules[$widget_position])) {
				return $rules[$widget_position];
			}

			return null;
		}

		public function form($instance)
		{
			// Do nothing
		}
	}
}
