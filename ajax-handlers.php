<?php

function keyword_tracker_ajax_handler() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('User does not have permissions');
    }

    $keyword = sanitize_text_field($_POST['keyword']);
    $location = sanitize_text_field($_POST['location']);
    $search_type = sanitize_text_field($_POST['search_type']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';

    $existing_keyword = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE keyword = %s AND location = %s AND search_type = %s",
        strtolower($keyword),
        strtolower($location),
        strtolower($search_type)
    ));

    if ($existing_keyword) {
        wp_send_json_error('This keyword already exists for the specified location and search type.');
    }

    $result = $wpdb->insert(
        $table_name,
        [
            'keyword' => strtolower($keyword),
            'location' => $location,
            'search_type' => $search_type,
            'title' => 'Updating...', // Placeholder, will be updated later
            'link' => '', // Placeholder, will be updated later
            'rank' => -1  // Placeholder, will be updated later
        ]
    );

    if ($result === false) {
        wp_send_json_error('Failed to add keyword');
    }

    $insert_id = $wpdb->insert_id;

    // Return initial success response with updating placeholders
    wp_send_json_success([
        'id' => $insert_id,
        'keyword' => strtolower($keyword),
        'location' => $location,
        'search_type' => $search_type,
        'title' => 'Updating...',
        'link' => '',
        'rank' => -1,
        'delete_link' => esc_url(admin_url('admin-post.php?action=delete_keyword&id=' . $insert_id))
    ]);

    // Fetch keyword data and update the database asynchronously
    $domain = parse_url(home_url(), PHP_URL_HOST);
    $results = fetch_keyword_data($keyword, $location, $domain, $search_type);

    if ($results) {
        foreach ($results as $result) {
            $wpdb->update(
                $table_name,
                [
                    'title' => $result['title'],
                    'link' => $result['link'],
                    'rank' => $result['position']
                ],
                ['id' => $insert_id]
            );
        }
    } 
    else {
        $wpdb->update(
            $table_name,
            [
                
                'rank' => 101
            ],
            ['id' => $insert_id]
        );
    }
}
add_action('wp_ajax_add_and_fetch_keyword', 'keyword_tracker_ajax_handler');

function keyword_tracker_get_keyword_data() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('User does not have permissions');
    }

    $id = intval($_POST['id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';
    $history_table_name = $wpdb->prefix . 'keyword_tracker_history';

    $keyword = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

    if ($keyword && $keyword->rank !== '-1') {
        // Calculate changes
        $change_1d = get_keyword_changes($id, 1);
        $change_7d = get_keyword_changes($id, 7);
        $change_30d = get_keyword_changes($id, 30);
        $change_life = get_life_change($id);

        wp_send_json_success([
            'rank' => $keyword->rank,
            'link' => $keyword->link,
            'change_1d' => $change_1d,
            'change_7d' => $change_7d,
            'change_30d' => $change_30d,
            'change_life' => $change_life
        ]);
    } else {
        wp_send_json_error('Still updating');
    }
}
add_action('wp_ajax_get_keyword_data', 'keyword_tracker_get_keyword_data');


function keyword_tracker_update_selected_keywords() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('User does not have permissions');
    }

    if (!isset($_POST['keyword_ids']) || !is_array($_POST['keyword_ids'])) {
        wp_send_json_error('Invalid request');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';
    $keyword_ids = array_map('intval', $_POST['keyword_ids']);

    foreach ($keyword_ids as $id) {
        $wpdb->update(
            $table_name,
            [
                'title' => 'Updating...',
                'rank' => -1
            ],
            ['id' => $id]
        );
    }

    wp_send_json_success();
}
add_action('wp_ajax_update_selected_keywords', 'keyword_tracker_update_selected_keywords');

function keyword_tracker_ajax_handler_update_keyword() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('User does not have permissions');
    }

    $keyword_id = intval($_POST['keyword_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';

    // Update the keyword to set it to updating state
    $wpdb->update(
        $table_name,
        [
            'rank' => -1,
            'title' => 'Updating...'
        ],
        ['id' => $keyword_id]
    );

    // The cron job will take care of fetching the actual ranking
    wp_send_json_success('Keyword set to updating state');
}
add_action('wp_ajax_update_keyword', 'keyword_tracker_ajax_handler_update_keyword');


function handle_update_keyword() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('User does not have permissions');
    }

    $id = isset($_POST['keyword_id']) ? intval($_POST['keyword_id']) : 0;

    if (!$id) {
        wp_send_json_error('Invalid keyword ID');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';

    // Update the keyword status to indicate it's being updated
    $wpdb->update(
        $table_name,
        [
            'title' => 'Updating...',
            'rank' => -1
        ],
        ['id' => $id]
    );

    wp_send_json_success(['id' => $id]);
}
add_action('wp_ajax_update_keyword', 'handle_update_keyword');


function handle_delete_keyword() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('User does not have permissions');
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!$id) {
        wp_send_json_error('Invalid keyword ID');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';

    $wpdb->delete($table_name, ['id' => $id]);

    wp_send_json_success();
}
add_action('wp_ajax_delete_keyword', 'handle_delete_keyword');


function keyword_tracker_update_keyword() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('User does not have permissions');
    }

    $keyword_id = intval($_POST['keyword_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';

    $updated = $wpdb->update(
        $table_name,
        // ['rank' => '-1'], // Set rank to -1 (Updating...)
        ['id' => $keyword_id]
    );

    if ($updated === false) {
        wp_send_json_error('Failed to update keyword rank');
    }

    wp_send_json_success(['id' => $keyword_id]);
}
add_action('wp_ajax_update_keyword', 'keyword_tracker_update_keyword');


function keyword_tracker_location_suggestions() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('User does not have permissions');
    }

    if (!isset($_POST['query'])) {
        wp_send_json_error('Invalid request');
    }

    $query = sanitize_text_field($_POST['query']);
    $api_key = get_option('serpapi_key');
    $url = "https://serpapi.com/locations.json?q=" . urlencode($query) . "&limit=5&api_key=" . $api_key;

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error('Failed to fetch location suggestions');
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    wp_send_json_success($data);
}
add_action('wp_ajax_location_suggestions', 'keyword_tracker_location_suggestions');

function handle_get_keyword_chart_data() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $keyword_id = intval($_POST['keyword_id']);

    // Fetch keyword data from the database
    $keyword_data = get_keyword_data($keyword_id);

    if (!$keyword_data) {
        wp_send_json_error('No data found');
    }

    // Format data for the chart
    $keyword = '';
    $dates = [];
    $ranks = [];
    $current_rank = '';

    foreach ($keyword_data as $data_point) {
        if (empty($keyword)) {
            $keyword = $data_point->keyword; // Set keyword text
            
        }

        if (empty($current_rank)) {
            $current_rank = $data_point->rank; // Set keyword text
            
        }
        
        $dates[] = $data_point->date;
        $ranks[] = $data_point->rank;
    }

    wp_send_json_success([
        'keyword' => $keyword.' | Current Position: '.$current_rank,
        'dates' => $dates,
        'ranks' => $ranks
    ]);
}
add_action('wp_ajax_get_keyword_chart_data', 'handle_get_keyword_chart_data');


add_action('wp_ajax_get_gmb_data', 'get_gmb_data');
function get_gmb_data() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    // Example: Fetch GMB data logic here
    $gmb_data = [
        // Sample data
        ['business' => 'Business 1', 'rank' => 1, 'location' => 'Location 1'],
        ['business' => 'Business 2', 'rank' => 2, 'location' => 'Location 2'],
    ];

    wp_send_json_success($gmb_data);
}


add_action('wp_ajax_save_gmb_data', 'save_gmb_data');

function save_gmb_data() {
    check_ajax_referer('keyword_tracker_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized.');
    }

    global $wpdb;

    $place_id = get_option('place_id');

    $keyword = sanitize_text_field($_POST['keyword']);
    $location = sanitize_text_field($_POST['location']);
    $grid_radius = sanitize_text_field($_POST['gridRadius']);
    $grid_points = sanitize_text_field($_POST['gridPoints']);
    $place_id = sanitize_text_field($place_id);
    $gridRadius = floatval($_POST['gridRadius']);
    $gridPoints = intval($_POST['gridPoints']);
    $center = $_POST['center'];
    $center_lat = sanitize_text_field($_POST['centerLat']);
    $center_lng = sanitize_text_field($_POST['centerLng']);
    $gmb_table_name = $wpdb->prefix . 'gmb_keyword_tracker';
    $grid_points_table_name = $wpdb->prefix . 'gmb_grid_points';

    $current_time = current_time('mysql');
    $avg_ranking = 0; // Placeholder for average ranking calculation
    $last_ranking_check = null;


    
    // Insert data into gmb_keyword_tracker table
    $wpdb->insert(
        $gmb_table_name,
        [
            'keyword' => $keyword,
            'location' => $location,
            'place_id' => $place_id,
            'grid_points' => $grid_points,
            'grid_radius' => $grid_radius,
            'center_lat' => $center_lat,
            'center_lng' => $center_lng,
            'created_date' => $current_time,
            'avg_ranking' => $avg_ranking,
            'last_ranking_check' => $last_ranking_check
        ]
    );

    $gmb_id = $wpdb->insert_id;

    // Calculate the grid points and insert them into the grid_points table
    $milesToDegrees = 1 / 69.0; // 1 mile in degrees
    $gridSize = $gridRadius * 2 * $milesToDegrees; // Distance between centers of circles

    $startLat = $center['lat'] - ($gridSize * ($gridPoints / 2));
    $startLng = $center['lng'] - ($gridSize * ($gridPoints / 2));

    for ($i = 0; $i < $gridPoints; $i++) {
        for ($j = 0; $j < $gridPoints; $j++) {
            $lat = $startLat + ($i * $gridSize);
            $lng = $startLng + ($j * $gridSize);

            // Placeholder for ranking data; you can replace this with actual ranking data fetching logic
            $ranking = null; 

            $wpdb->insert(
                $grid_points_table_name,
                [
                    'gmb_id' => $gmb_id,
                    'lat' => $lat,
                    'lng' => $lng,
                    'ranking' => $ranking
                ]
            );
        }
    }

    // $results = fetch_gmb_by_keyword_data('air duct cleaning charlotte nc', '@35.11840434782609,-80.80686811594204,10z','ChIJezXR6PyhVogRobBGDKtccTI');

    wp_send_json_success($results);
    // wp_send_json_success('Data saved successfully.');
}