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

add_filter(
	'the_seo_framework_meta_render_data',
	function ( $tags_render_data ) {

        $playground_url = $tags_render_data['canonical']['attributes']['href'];
		$tags_render_data['canonical']['attributes']['href'] = 'https://kontentti.sauli.pro';
		
		$tags_render_data['og:url']['attributes']['content'] = str_replace($playground_url, 'https://kontentti.sauli.pro/', $tags_render_data['og:url']['attributes']['content']);
		$tags_render_data['og:image:0']['attributes']['content'] = str_replace($playground_url, 'https://kontentti.sauli.pro/', $tags_render_data['og:image:0']['attributes']['content']);
		$tags_render_data['twitter:image']['attributes']['content'] = str_replace($playground_url, 'https://kontentti.sauli.pro/', $tags_render_data['twitter:image']['attributes']['content']);
		$tags_render_data['schema:graph']['content']['content'] = str_replace($playground_url, 'https://kontentti.sauli.pro/', $tags_render_data['schema:graph']['content']['content']);

		return $tags_render_data;
	},
);

add_action( 'save_post_page', function ( $post_id, $post, $update ) {
	if ( $post->post_status !== 'publish' ) {
		return;
	}

	$theme_dir = get_stylesheet_directory();
	$txt_file  = $theme_dir . "/playground/postcontent.txt";

	$current_site_url = get_site_url();
	$post_content     = $post->post_content;
	$post_content     = str_replace( $current_site_url, '', $post_content );
	file_put_contents( $txt_file, $post_content );
}, 10, 3 );

add_filter('show_admin_bar', function () {
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === 'playground.wordpress.net') {
        return true;
    }

    return false;
});
