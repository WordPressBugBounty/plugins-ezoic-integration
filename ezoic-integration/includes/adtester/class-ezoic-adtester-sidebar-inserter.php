<?php

namespace Ezoic_Namespace;

class Ezoic_AdTester_Sidebar_Inserter extends Ezoic_AdTester_Inserter {
	private $has_run = false;

	public function __construct( $config ) {
		parent::__construct( $config );

		// Default to 'post' as the page is not correctly being detected
		if ( !isset( $this->page_type ) || $this->page_type == "" ) {
			$this->page_type = 'post';
		}
	}

	/**
	 * Insert sidebar placeholders
	 */
	public function insert() {
        if ( $this->has_run ) {
            return;
        }

		$sidebars = get_option( 'sidebars_widgets' );

		if ( empty( $sidebars ) ) {
			return;
		}

		// Attempt to find the correct sidebar, based on configurable id
        if ( !array_key_exists( $this->config->sidebar_id, $sidebars ) || !is_array( $sidebars[ $this->config->sidebar_id ] ) || count( $sidebars[ $this->config->sidebar_id ] ) === 0 ) {
            return;
        }

		// No widgets in this sidebar
        if ( count( $sidebars[ $this->config->sidebar_id ] ) === 0 ) {
            return;
        }

		// If the custom sidebar widget was not defined, do not attempt to add
		if ( !class_exists( 'Ezoic_Namespace\Ezoic_AdTester_Widget' ) ) {
			return;
		}

		// Get insertion rules
		$insertion_rules = $this->get_rules();
		if ( count( $insertion_rules ) === 0 ) {
			// No rules found, return
			return;
		}

        if ( in_array( 'ezoic_adtester_widget-1', $sidebars[ $this->config->sidebar_id ] ) ) {
            return;
        }

        // Register widget, if needed
        if ( is_active_widget( false, false, 'ezoic_adtester_widget', true ) === false ) {
			register_widget( 'Ezoic_Namespace\Ezoic_AdTester_Widget' );
        }        

		$widget_counter = 0;
		$insert_counter = 1;
		$widget_options = array();
		$new_widgets = array();
		foreach ( $sidebars[ $this->config->sidebar_id ] as $widget ) {
			if ( \ez_stripos( $widget, 'ezoic_adtester' ) !== 0 ) {
				if ( isset( $insertion_rules[ $widget_counter ] ) ) {
					$new_widgets[] = 'ezoic_adtester_widget-' . $widget_counter;
					$widget_options[ $insert_counter ] = array(
						'embed_code' => $insertion_rules[ $widget_counter ]->embed_code()
					);
					$insert_counter++;
				}


                $new_widgets[] = $widget;
				$widget_counter++;
			}
		}

		foreach ( $insertion_rules as $rule_idx => $remaining_rule ) {
			if ( $rule_idx >= $widget_counter ) {
				$new_widgets[] = 'ezoic_adtester_widget-' . $insert_counter;
				$widget_options[ $insert_counter ] = array(
					'embed_code' => $remaining_rule->embed_code()
				);
				$insert_counter++;
			}
		}

		// Replace existing widgets with new widgets
		$sidebars[ $this->config->sidebar_id ] = $new_widgets;

		update_option( 'widget_ezoic_adtester_widget', $widget_options );
        update_option( 'sidebars_widgets', $sidebars );

		$this->has_run = true;
	}

	/**
	 * Returns a map of relavent rules
	 */
	private function get_rules() {
		$rules = array();

		foreach ( $this->config->placeholder_config as $ph_config ) {			
			if ( $ph_config->page_type == $this->page_type &&	// Current page type
				 $ph_config->display != 'disabled' &&			// Rule is enabled
				 $ph_config->display == 'after_widget' ) {		// Rule is a sidebar rule
		
				$rules[ (int) $ph_config->display_option ] = $this->config->placeholders[ $ph_config->placeholder_id ];
			}
		}

		\ksort( $rules, SORT_NUMERIC  );

		return $rules;
	}
}
