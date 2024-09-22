<?php

namespace Ezoic_Namespace;

class Ezoic_AdPos extends Ezoic_Feature {
    private $has_run = false;
    private $sidebar_id = 'sidebar-1';

    public function __construct() {
        $this->is_public_enabled = true;
        $this->is_admin_enabled = false;
    }

    public function register_admin_hooks( $loader ) {
        // do nothing
    }

    public function register_public_hooks( $loader ) {
        $config = Ezoic_AdTester_Config::load();

        $this->sidebar_id = $config->sidebar_id;

        // If integration is not enabled, exit early
        // if ( $config->enable_adpos_integration === false ) {
        //     return;
        // }

        $loader->add_filter( 'widgets_init', $this, 'set_sidebar_markers' );
    }

    public function set_sidebar_markers() {
        if ( $this->has_run ) {
            return;
        }

        $sidebars = get_option( 'sidebars_widgets' );

   		if ( empty( $sidebars ) ) {
			return;
		}

        // Attempt to find the correct sidebar, based on configurable id
        if ( !array_key_exists( $this->sidebar_id, $sidebars ) || !is_array( $sidebars[ $this->sidebar_id ] ) || count( $sidebars[ $this->sidebar_id ] ) === 0 ) {
            return;
        }

        // Ensure the adpos widget is available
        if ( !class_exists( 'Ezoic_Namespace\Ezoic_AdPos_Widget' ) ) {
            return;
        }

        // No widgets in this sidebar
        if ( count( $sidebars[ $this->sidebar_id ] ) === 0 ) {
            return;
        }

        if ( in_array( 'ezoic_adpos_widget-1', $sidebars[ $this->sidebar_id ] ) ) {
            return;
        }

        // Register widget, if needed
        if ( is_active_widget( false, false, 'ezoic_adpos_widget', true ) === false ) {
            register_widget('Ezoic_Namespace\Ezoic_AdPos_Widget');
        }        

        $insert_counter = 1;
        $widget_options = array();
        $new_widgets = array();
        foreach ( $sidebars[ $this->sidebar_id ] as $widget ) {
            if ( \ez_stripos( $widget, 'ezoic_' ) !== 0 ) {
                $new_widgets[] = 'ezoic_adpos_widget-' . $insert_counter;

                $location = 'middle';
                if ( $insert_counter === 1 ) {
                    $location = 'top';
                }

                $widget_options[ $insert_counter ] = array( 'location' => $location );
                $insert_counter++;
            }
            
            if ( \ez_stripos( $widget, 'ezoic_' ) !== 0 ) {
                $new_widgets[] = $widget;
            }
        }

        $new_widgets[] = 'ezoic_adpos_widget-' . $insert_counter;
        $widget_options[ $insert_counter ] = array( 'location' => 'bottom' );

        $sidebars[ $this->sidebar_id ] = $new_widgets;

        update_option( 'widget_ezoic_adpos_widget', $widget_options );
        update_option( 'sidebars_widgets', $sidebars );

        $this->has_run = true;
    }
}