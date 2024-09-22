<?php

namespace Ezoic_Namespace;

// <ins class="ezoic-adpos-sidebar" data-loc="top" />

// Only create custom widget if WP_Widget is supported
if ( class_exists( 'WP_Widget' ) ) {
	class Ezoic_AdPos_Widget extends \WP_Widget {
		public function __construct() {
			$widget_options = array(
				'classname' => 'Ezoic_Namespace\Ezoic_AdPos_Widget',
				'description' => 'Ezoic Placement Service Marker'
			);

			parent::__construct( 'ezoic_adpos_widget', 'Ezoic AdPos Widget', $widget_options );
		}

		public function widget( $args, $instance ) {
			$location = 'top';

			if ( isset( $instance[ 'location' ] ) ) {
				$location = $instance[ 'location' ];
			}

			echo "<ins class='ezoic-adpos-sidebar' style='display:none !important;visibility:hidden !important;height:0 !important;width:0 !important;' data-loc='" . $location . "'></ins>";
		}

		public function form( $instance ) {
			// Do nothing
		}
	}
}
