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

add_action('save_post_page', function ($post_id, $post, $update) {
    // Tallennetaan vain jos kyseessä on julkaistu sivu
    if ($post->post_status !== 'publish') {
        return;
    }

    // Polku teeman kansioon
    $theme_dir = get_stylesheet_directory();
    $txt_file   = $theme_dir . "/playground/postcontent.txt";
    file_put_contents($txt_file, $post->post_content);
}, 10, 3);

add_filter('show_admin_bar', function () {
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === 'playground.wordpress.net') {
        return true;
    }

    return false;
});


/**
 * Lue postcontent.txt, parsii lohkot ja päivitä kuvien osoitteet.
 */
function process_postcontent_with_local_images() {
    // $file = get_stylesheet_directory() . '/playground/postcontent.txt';

    // if ( ! file_exists( $file ) ) {
    //     // return new WP_Error( 'file_missing', 'postcontent.txt ei löytynyt teemakansiosta.' );
    // }

    // $content = file_get_contents( $file );

    // if ( empty( $content ) ) {
    //     // return new WP_Error( 'file_empty', 'postcontent.txt on tyhjä.' );
    // }

    // // Parsitaan sisällöstä lohkot
    // $blocks = parse_blocks( $content );

    // // Käydään lohkot rekursiivisesti läpi
    // $blocks = update_image_blocks( $blocks );

    // // Palautetaan uusi sisältö lohkoista
    // $updated_content = serialize_blocks( $blocks );

    // return $updated_content;
}

/**
 * Käy lohkot läpi rekursiivisesti ja päivittää core/image -lohkot.
 */
function update_image_blocks( $blocks ) {
    foreach ( $blocks as &$block ) {
        // Jos on kuva
        if ( $block['blockName'] === 'core/image' && ! empty( $block['attrs']['url'] ) ) {
            $old_url = $block['attrs']['url'];
            $filename = basename( parse_url( $old_url, PHP_URL_PATH ) );

            // Hae attachment tiedostonimen perusteella
            $attachment_id = attachment_url_to_postid( wp_get_upload_dir()['baseurl'] . '/' . $filename );

            if ( ! $attachment_id ) {
                // Jos ei löytynyt, kokeillaan etsiä mediatiedostoa suoraan nimen perusteella
                $attachment_id = mytheme_find_attachment_by_filename( $filename );
            }

            if ( $attachment_id ) {
                $new_url = wp_get_attachment_url( $attachment_id );
                if ( $new_url ) {
                    $block['attrs']['url'] = $new_url;
                }
            }
        }

        // Jos on sisäkkäisiä lohkoja → käsitellään nekin
        if ( ! empty( $block['innerBlocks'] ) ) {
            $block['innerBlocks'] = update_image_blocks( $block['innerBlocks'] );
        }
    }

    return $blocks;
}

/**
 * Etsi attachment filename perusteella (jos attachment_url_to_postid ei riitä).
 */
function mytheme_find_attachment_by_filename( $filename ) {
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
        WHERE post_type = 'attachment'
        AND post_status = 'inherit'
        AND guid LIKE %s
        LIMIT 1",
        '%' . $wpdb->esc_like( $filename )
    );

    return $wpdb->get_var( $query );
}
