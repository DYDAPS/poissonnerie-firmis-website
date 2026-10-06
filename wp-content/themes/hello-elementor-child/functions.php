<?php

/**
 * Remove Yoast SEO's automatic output for rel, canonical, and hreflang tags.
 */
add_filter( 'wpseo_next_rel_link', '__return_false' );
add_filter( 'wpseo_prev_rel_link', '__return_false' );
add_filter( 'wpseo_canonical', '__return_false' );
add_filter( 'wpseo_hreflang', '__return_false' );

/**
 * Add rel="prev" and rel="next" tags on paginated pages.
 * This function uses output buffering to scan the HTML and insert the tags
 * if they are missing. It first attempts to extract pagination data from an
 * element with the class "e-load-more-anchor" and falls back to WP_Query values.
 */
function my_rel_links_output_callback( $buffer ) {
    // Process only paginated pages.
    if ( ! is_paged() ) {
        return $buffer;
    }
    
    // Check if rel="prev" or rel="next" tags already exist.
    $has_prev = ( strpos( $buffer, 'rel="prev"' ) !== false || strpos( $buffer, "rel='prev'" ) !== false );
    $has_next = ( strpos( $buffer, 'rel="next"' ) !== false || strpos( $buffer, "rel='next'" ) !== false );
    $tags     = '';
    
    // Get current page number (defaults to 1).
    $current_page = max( 1, get_query_var( 'paged' ) );
    
    // Determine maximum pages.
    $max_pages = 1;
    // Try to extract max pages from an element with class "e-load-more-anchor".
    if ( preg_match( '/class=["\'][^"\']*e-load-more-anchor[^"\']*["\'][^>]+data-max-page=["\'](\d+)["\']/', $buffer, $match_max ) ) {
        $max_pages = intval( $match_max[1] );
    } else {
        // Fallback to WP_Query values.
        global $wp_query;
        $max_pages = ! empty( $wp_query->max_num_pages ) ? intval( $wp_query->max_num_pages ) : 1;
    }
    
    // Add the previous page link if missing and current page > 1.
    if ( ! $has_prev && $current_page > 1 ) {
        $prev_page = $current_page - 1;
        $prev_link = get_pagenum_link( $prev_page );
        $tags .= '<link rel="prev" href="' . esc_url( $prev_link ) . '">' . "\n";
    }
    
    // Add the next page link if missing and current page is less than max pages.
    if ( ! $has_next && $current_page < $max_pages ) {
        $next_link = '';
        // Try to extract the next page URL from the "e-load-more-anchor" element.
        if ( preg_match( '/class=["\'][^"\']*e-load-more-anchor[^"\']*["\'][^>]+data-next-page=["\']([^"\']+)["\']/', $buffer, $match_next ) ) {
            $next_link = $match_next[1];
        } else {
            $next_link = get_pagenum_link( $current_page + 1 );
        }
        $tags .= '<link rel="next" href="' . esc_url( $next_link ) . '">' . "\n";
    }
    
    // Insert generated tags before the closing </head> tag.
    if ( $tags ) {
        $buffer = str_replace( '</head>', $tags . '</head>', $buffer );
    }
    return $buffer;
}

// Start output buffering in the head section.
function my_rel_links_buffer_start() {
    ob_start( 'my_rel_links_output_callback' );
}
add_action( 'wp_head', 'my_rel_links_buffer_start', 0 );

// Flush output buffering in the footer.
function my_rel_links_buffer_end() {
    if ( ob_get_length() ) {
        ob_end_flush();
    }
}
add_action( 'wp_footer', 'my_rel_links_buffer_end', 9999 );

/**
 * Output canonical tags.
 * For singular pages, this uses the permalink.
 * For blog index, archives, categories, and tags, it uses the paginated URL.
 */
function add_canonical_tags() {
    if ( is_singular() ) {
        $canonical_url = get_permalink();
    } elseif ( is_home() || is_archive() || is_category() || is_tag() ) {
        $canonical_url = get_pagenum_link( get_query_var( 'paged' ) );
    } else {
        return;
    }
    echo '<link rel="canonical" href="' . esc_url( $canonical_url ) . '">' . "\n";
}
add_action( 'wp_head', 'add_canonical_tags' );

/**
 * Dynamically add hreflang tags using the current page URL.
 */
function add_hreflang_tags() {
    $current_url = home_url( add_query_arg( [], $_SERVER['REQUEST_URI'] ) );
    echo '<link rel="alternate" hreflang="fr" href="' . esc_url( $current_url ) . '">' . "\n";
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $current_url ) . '">' . "\n";
}
add_action( 'wp_head', 'add_hreflang_tags' );

/**
 * Enqueue custom CSS and JS in the child theme.
 */
function enqueue_custom_scripts() {
    $theme_directory = get_stylesheet_directory_uri();

    // List of custom styles to enqueue.
    $styles = [
        'custom-wavy-line'              => 'css/wavy-line.css',
    ];

    foreach ( $styles as $handle => $file ) {
        wp_enqueue_style( $handle, $theme_directory . '/' . $file, [], '1.0.0', 'all' );
    }

    // List of custom scripts to enqueue.
    $scripts = [
    ];

    foreach ( $scripts as $handle => $file ) {
        wp_enqueue_script( $handle, $theme_directory . '/' . $file, ['jquery'], '1.0.0', true );
    }
}
add_action( 'wp_enqueue_scripts', 'enqueue_custom_scripts' );
