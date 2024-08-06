<?php

function keyword_tracker_enqueue_scripts($hook_suffix) {
    wp_enqueue_style('keyword-tracker-styles', plugins_url('/keyword-tracker.css', __FILE__)); // Enqueue the custom stylesheet
    
    wp_enqueue_script('jquery'); // Ensure jQuery is loaded first

    // Enqueue Moment.js
    wp_enqueue_script('moment-js', 'https://cdn.jsdelivr.net/npm/moment@2.29.1/min/moment.min.js', array(), null, true);

    // Enqueue Chart.js
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array('jquery', 'moment-js'), null, true);

    // Enqueue Chart.js Moment.js adapter
    wp_enqueue_script('chartjs-adapter-moment', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.0', array('chart-js', 'moment-js'), null, true);

    // Enqueue Google Maps API (only on specific admin page)
    if ($hook_suffix === 'keyword-tracker_page_gmb-ranking-grid') {
        $google_api_key = get_option('google_api_key');
        if ($google_api_key) {
            wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_api_key) . '&libraries=places', [], null, true);
        }
    }

    // Enqueue your custom script
    wp_enqueue_script('keyword-tracker', plugins_url('/keyword-tracker.js', __FILE__), array('jquery', 'chart-js', 'chartjs-adapter-moment'), null, true);
    wp_localize_script('keyword-tracker', 'keywordTrackerAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('keyword_tracker_nonce'),
        'serpapi_key' => get_option('serpapi_key'), // Include the SERPAPI key
        'place_id' => get_option('place_id') // Include the place_id
    ));
}
add_action('admin_enqueue_scripts', 'keyword_tracker_enqueue_scripts');

