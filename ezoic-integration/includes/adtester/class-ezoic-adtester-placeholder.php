<?php

namespace Ezoic_Namespace;

class Ezoic_AdTester_Placeholder
{
	const PLACEHOLDER_RESERVATION_ATTRIBUTES = ' style="min-height: 280px; display: block; text-align: center;"';
	const RESERVE_PLACEHOLDER_SPACE_OPTION = 'js_reserve_placeholder_space';
	const RESERVE_PLACEHOLDER_SPACE_QUERY_PARAM = 'ez_reserve_placeholders';
	const EMBED_CODE_TEMPLATE = '<!-- Ezoic - %s - %s -->%s<div id="ezoic-pub-ad-placeholder-%d"%s data-inserter-version="%d"></div><!-- End Ezoic - %s - %s -->';
	const JS_EMBED_CODE_TEMPLATE = '<!-- Ezoic - %s - %s -->%s<div id="ezoic-pub-ad-placeholder-%d"%s data-inserter-version="%d" data-placement-location="%s"></div><script data-ezoic="1"%s>ezstandalone.cmd.push(function () { ezstandalone.showAds(%d); });</script><!-- End Ezoic - %s - %s -->';
	const JS_EMBED_CODE_TEMPLATE_NO_ADS = '<!-- Ezoic - %s - %s (Ads Disabled) -->%s<div id="ezoic-pub-ad-placeholder-%d"%s data-inserter-version="%d"></div><!-- End Ezoic - %s - %s -->';

	// Track if any JS placeholders have been inserted
	private static $js_placeholders_inserted = false;

	public $id;
	public $position_id;
	public $position_type;
	public $name;
	public $is_video_placeholder;
	public $reservation_dimensions = array();

	// Getter for camelCase property name for frontend compatibility
	public function __get($property)
	{
		if ($property === 'positionType') {
			return $this->position_type;
		}
		if ($property === 'positionId') {
			return $this->position_id;
		}
		return null;
	}

	public function __construct($id, $position_id, $name, $position_type, $is_video_placeholder, $reservation_dimensions = array())
	{
		$this->id						= $id;
		$this->position_id				= $position_id;
		$this->position_type			= $position_type;
		$this->name						= $name;
		$this->is_video_placeholder		= $is_video_placeholder;
		$this->set_reservation_dimensions($reservation_dimensions);
	}

	public function set_reservation_dimensions($reservation_dimensions)
	{
		$this->reservation_dimensions = self::normalize_reservation_dimensions($reservation_dimensions);
	}

	public static function is_reserve_placeholder_space_enabled($allow_query_override = true)
	{
		if ($allow_query_override && isset($_GET[self::RESERVE_PLACEHOLDER_SPACE_QUERY_PARAM]) && $_GET[self::RESERVE_PLACEHOLDER_SPACE_QUERY_PARAM] == '1') {
			return true;
		}

		$js_options = get_option('ezoic_js_integration_options', array());
		if (!is_array($js_options) || !isset($js_options[self::RESERVE_PLACEHOLDER_SPACE_OPTION])) {
			return false;
		}

		return (bool) $js_options[self::RESERVE_PLACEHOLDER_SPACE_OPTION];
	}

	public static function set_reserve_placeholder_space_enabled($enabled)
	{
		$js_options = get_option('ezoic_js_integration_options', array());
		if (!is_array($js_options)) {
			$js_options = array();
		}

		$js_options = array_merge(
			array(
				'js_auto_insert_scripts' => 1,
				'js_enable_privacy_scripts' => 1,
				'js_use_wp_placeholders' => 1,
			),
			$js_options
		);
		$js_options[self::RESERVE_PLACEHOLDER_SPACE_OPTION] = $enabled ? 1 : 0;

		update_option('ezoic_js_integration_options', $js_options);
	}

	private function placeholder_attributes()
	{
		$attributes = $this->should_use_fallback_reservation() ? self::PLACEHOLDER_RESERVATION_ATTRIBUTES : '';
		if ($this->is_video_placeholder) {
			$attributes .= ' data-ezhumixplayerlocation="true"';
		}

		return $attributes;
	}

	private function placeholder_reservation_style_block()
	{
		if (!$this->should_reserve_placeholder_space() || !$this->has_reservation_dimensions()) {
			return '';
		}

		$rules = $this->reservation_css_rules();
		if (empty($rules)) {
			return '';
		}

		return '<style data-ezoic-placeholder-reservation="' . intval($this->position_id) . '">' . implode(' ', $rules) . '</style>';
	}

	private function should_use_fallback_reservation()
	{
		return $this->should_reserve_placeholder_space() && !$this->has_reservation_dimensions();
	}

	private function should_reserve_placeholder_space()
	{
		if (!in_array($this->position_type, array('top_of_page', 'under_page_title'), true)) {
			return false;
		}

		return self::is_reserve_placeholder_space_enabled();
	}

	private function has_reservation_dimensions()
	{
		return !empty($this->reservation_dimensions) && is_array($this->reservation_dimensions);
	}

	private function reservation_css_rules()
	{
		$selector = '#ezoic-pub-ad-placeholder-' . intval($this->position_id);
		$rules    = array();

		foreach ($this->reservation_dimensions_for('desktop') as $dimension) {
			$declaration = $this->reservation_css_declaration($dimension, true);
			if ($declaration === '') {
				continue;
			}

			$media_query = $this->desktop_media_query($dimension);
			if ($media_query === '') {
				$rules[] = $selector . ' { ' . $declaration . ' }';
			} else {
				$rules[] = $media_query . ' { ' . $selector . ' { ' . $declaration . ' } }';
			}
		}

		foreach ($this->reservation_dimensions_for('mobile') as $dimension) {
			$declaration = $this->reservation_css_declaration($dimension, false);
			if ($declaration !== '') {
				$rules[] = '@media (max-width: 767px) { ' . $selector . ' { ' . $declaration . ' } }';
			}
		}

		foreach ($this->reservation_dimensions_for('tablet') as $dimension) {
			$declaration = $this->reservation_css_declaration($dimension, false);
			if ($declaration !== '') {
				$rules[] = '@media (min-width: 768px) and (max-width: 1024px) { ' . $selector . ' { ' . $declaration . ' } }';
			}
		}

		return $rules;
	}

	private function reservation_dimensions_for($form_factor)
	{
		if (!isset($this->reservation_dimensions[$form_factor]) || !is_array($this->reservation_dimensions[$form_factor])) {
			return array();
		}

		return $this->reservation_dimensions[$form_factor];
	}

	private function reservation_css_declaration($dimension, $include_display)
	{
		if (!isset($dimension['minHeight']) || intval($dimension['minHeight']) <= 0) {
			return '';
		}

		$declaration = 'min-height: ' . intval($dimension['minHeight']) . 'px;';
		if (isset($dimension['minWidth']) && intval($dimension['minWidth']) > 0) {
			$declaration .= ' min-width: ' . intval($dimension['minWidth']) . 'px;';
		}
		if ($include_display) {
			$declaration .= ' display: block; text-align: center;';
		}

		return $declaration;
	}

	private function desktop_media_query($dimension)
	{
		$min_width = 1025;
		if (isset($dimension['screenMinWidth']) && intval($dimension['screenMinWidth']) > 0) {
			$min_width = max($min_width, intval($dimension['screenMinWidth']));
		}
		$conditions = array('(min-width: ' . $min_width . 'px)');
		if (isset($dimension['screenMinHeight']) && intval($dimension['screenMinHeight']) > 0) {
			$conditions[] = '(min-height: ' . intval($dimension['screenMinHeight']) . 'px)';
		}

		return '@media ' . implode(' and ', $conditions);
	}

	private static function normalize_reservation_dimensions($reservation_dimensions)
	{
		if (is_object($reservation_dimensions)) {
			$reservation_dimensions = get_object_vars($reservation_dimensions);
		}
		if (!is_array($reservation_dimensions)) {
			return array();
		}

		$normalized = array();
		foreach (array('desktop', 'mobile', 'tablet') as $form_factor) {
			if (!isset($reservation_dimensions[$form_factor])) {
				continue;
			}
			$dimensions = is_object($reservation_dimensions[$form_factor]) ? get_object_vars($reservation_dimensions[$form_factor]) : $reservation_dimensions[$form_factor];
			if (!is_array($dimensions)) {
				continue;
			}
			foreach ($dimensions as $dimension) {
				$normalized_dimension = self::normalize_reservation_dimension($dimension);
				if (!empty($normalized_dimension)) {
					$normalized[$form_factor][] = $normalized_dimension;
				}
			}
		}

		return $normalized;
	}

	private static function normalize_reservation_dimension($dimension)
	{
		if (is_object($dimension)) {
			$dimension = get_object_vars($dimension);
		}
		if (!is_array($dimension) || !isset($dimension['minHeight']) || intval($dimension['minHeight']) <= 0) {
			return array();
		}

		$normalized = array(
			'minHeight' => intval($dimension['minHeight']),
		);

		foreach (array('minWidth', 'screenMinWidth', 'screenMinHeight') as $key) {
			if (isset($dimension[$key]) && intval($dimension[$key]) > 0) {
				$normalized[$key] = intval($dimension[$key]);
			}
		}

		return $normalized;
	}

	/**
	 * Calculates the correct embed code to inject into the page
	 */
	public function embed_code($inserter_version = -1)
	{
		// Check if JavaScript integration is enabled and placeholders should be used
		$js_integration_enabled = get_option('ezoic_js_integration_enabled', false);
		$js_options = get_option('ezoic_js_integration_options');
		$use_js_placeholders = $js_integration_enabled && isset($js_options['js_use_wp_placeholders']) && $js_options['js_use_wp_placeholders'];

		if ($use_js_placeholders) {
			// Mark that a JS placeholder was inserted
			self::$js_placeholders_inserted = true;

			// Check if ads are disabled for the current user
			$ads_disabled = isset($_COOKIE['x-ez-wp-noads']) && $_COOKIE['x-ez-wp-noads'] == '1';

			// Return JavaScript ad code for JS integration
			$styleBlock = $this->placeholder_reservation_style_block();
			$dataAttr   = $this->placeholder_attributes();

			// Add LiteSpeed exclusion attributes if LiteSpeed Cache is active
			$litespeed_attr = Ezoic_Integration_Compatibility_Check::is_litespeed_cache_active() ? ' data-no-optimize="1" data-no-defer="1"' : '';

			// If ads are disabled, return placeholder without showAds() call
			if ($ads_disabled) {
				return sprintf(
					self::JS_EMBED_CODE_TEMPLATE_NO_ADS,
					$this->name,
					$this->position_type,
					$styleBlock,
					$this->position_id,
					$dataAttr,
					$inserter_version,
					$this->name,
					$this->position_type
				);
			}

			return sprintf(
				self::JS_EMBED_CODE_TEMPLATE,
				$this->name,
				$this->position_type,
				$styleBlock,
				$this->position_id,
				$dataAttr,
				$inserter_version,
				$this->position_type,
				$litespeed_attr,
				$this->position_id,
				$this->name,
				$this->position_type
			);
		}

		// Default WordPress integration placeholder
		$styleBlock = $this->placeholder_reservation_style_block();
		$dataAttr   = $this->placeholder_attributes();
		return sprintf(self::EMBED_CODE_TEMPLATE, $this->name, $this->position_type, $styleBlock, $this->position_id, $dataAttr, $inserter_version, $this->name, $this->position_type);
	}

	public static function from_pubad($ad)
	{
		$reservation_dimensions = isset($ad->reservationDimensions) ? $ad->reservationDimensions : array();
		$placeholder            = new Ezoic_AdTester_Placeholder($ad->id, $ad->adPositionId, $ad->name, $ad->positionType, $ad->isVideoPlaceholder, $reservation_dimensions);

		return $placeholder;
	}

	/**
	 * Check if any JS placeholders have been inserted on this page
	 */
	public static function js_placeholders_inserted()
	{
		return self::$js_placeholders_inserted;
	}

	/**
	 * Reset the JS placeholders tracking (useful for testing or page resets)
	 */
	public static function reset_js_placeholders_tracking()
	{
		self::$js_placeholders_inserted = false;
	}
}
