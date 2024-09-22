<?php

namespace Ezoic_Namespace;

class Ezoic_AdTester_Native_Inserter extends Ezoic_AdTester_Inserter {
    public function __construct( $config ) {
        parent::__construct( $config );

        if ( !isset( $this->page_type ) || $this->page_type == '' ) {
            $this->page_type = 'post';
        }
    }

    public function insert( $content ) {
        $rules = array();
        $to_insert = array();
        foreach ( $this->config->placeholder_config as $ph_config ) {
            if ( $ph_config->page_type == $this->page_type ) {
                $placeholder = $this->config->placeholders[ $ph_config->placeholder_id ];
                if ( \substr( $placeholder->position_type, 0, 6) === 'native' ) {
                    $rules[ $ph_config->placeholder_id ] = $ph_config;
                    $to_insert[ $ph_config->placeholder_id ] = $placeholder;
                }
            }
        }

        // Exit early if no rules were found
        if ( count( $rules ) === 0 ) {
            return $content;
        }

        foreach ( $rules as $rule ) {
            $placeholder = $to_insert[ $rule->placeholder_id ];
            if ( $rule->display === 'after_content' ) {
                $content = $content . $placeholder->embed_code();
            }

            if ( $rule->display === 'before_content' ) {
                $content = $placeholder->embed_code() . $content;
            }
        }

        return $content;
    }
}

?>