<?php

add_filter( 'wp_theme_json_data_theme', function( $theme_json ) {
    $custom_css_file = get_stylesheet_directory() . '/assets/css/custom-styles.css';

    if ( file_exists( $custom_css_file ) ) {
        $custom_css = file_get_contents( $custom_css_file );

        if ( ! empty( $custom_css ) ) {
             $new_data = [
                'version'  => 3, 
                'styles'   => [
                    'css' => $custom_css
                ],
            ];

            return $theme_json->update_with( $new_data );
        }
    }

    return $theme_json;
});