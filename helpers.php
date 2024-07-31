<?php


function fetch_keyword_data($keyword, $location, $domain, $search_type) {
    $api_key = get_option('serpapi_key');
    $device = ($search_type === 'mobile') ? 'mobile' : 'desktop';
    $url = "https://serpapi.com/search.json?engine=google&q=" . urlencode($keyword) . "&location=" . urlencode($location) . "&device=" . $device . "&num=100&api_key=" . $api_key . "&no_cache=true&async=true";
    
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['search_metadata']['id'])) {
        return false;
    }

    $search_id = $data['search_metadata']['id'];
    $result_url = "https://serpapi.com/searches/$search_id.json?api_key=$api_key";

    // Polling for the result
    $polling_attempts = 0;
    $polling_max_attempts = 10;
    $polling_interval = 5; 

    while ($polling_attempts < $polling_max_attempts) {
        $polling_attempts++;
        sleep($polling_interval);

        $result_response = wp_remote_get($result_url);

        if (is_wp_error($result_response)) {
            continue;
        }

        $result_body = wp_remote_retrieve_body($result_response);
        $result_data = json_decode($result_body, true);

        if (isset($result_data['organic_results'])) {
            $filtered_results = array_filter($result_data['organic_results'], function($result) use ($domain) {
                return strpos($result['link'], $domain) !== false;
            });

            if (empty($filtered_results)) {
                return false;
            }

            return $filtered_results;
        }
    }

    return false;
}


function get_keywords_from_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';
    return $wpdb->get_results("SELECT * FROM $table_name");
}

function keyword_tracker_debug($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        error_log($message);
    }
}

function keyword_tracker_custom_cron_schedules($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60, // 1 minute in seconds
        'display' => __('Every Minute')
    );
    $schedules['daily'] = array(
        'interval' => 86400, // 1 day in seconds
        'display' => __('Daily')
    );
    return $schedules;
}
add_filter('cron_schedules', 'keyword_tracker_custom_cron_schedules');



function get_keyword_changes($keyword_id, $days) {
    global $wpdb;
    $history_table_name = $wpdb->prefix . 'keyword_tracker_history';
    $current_date = current_time('Y-m-d');

    $current_rank = $wpdb->get_var($wpdb->prepare(
        "SELECT rank FROM $history_table_name WHERE keyword_id = %d AND date = %s",
        $keyword_id, $current_date
    ));

    $past_date = date('Y-m-d', strtotime("-$days days"));

    $past_rank = $wpdb->get_var($wpdb->prepare(
        "SELECT rank FROM $history_table_name WHERE keyword_id = %d AND date = %s",
        $keyword_id, $past_date
    ));

    if ($past_rank !== null && $current_rank !== null) {
        $change = $current_rank - $past_rank;
        if(abs($change) === 0){
            return '—';
        }
        $indicator = $change > 0 ?  '▼' : '▲';
        $color = $change > 0 ? 'red' : 'green';
        return "<span style='color: $color;'>$indicator " . abs($change) . "</span>";
    }

    return '—';
}



function get_life_change($keyword_id) {
    global $wpdb;
    $history_table_name = $wpdb->prefix . 'keyword_tracker_history';

    $first_rank = $wpdb->get_var($wpdb->prepare(
        "SELECT rank FROM $history_table_name WHERE keyword_id = %d ORDER BY date ASC LIMIT 1",
        $keyword_id
    ));

    $current_rank = $wpdb->get_var($wpdb->prepare(
        "SELECT rank FROM $history_table_name WHERE keyword_id = %d ORDER BY date DESC LIMIT 1",
        $keyword_id
    ));

    if ($first_rank !== null && $current_rank !== null) {
        $change = $current_rank - $first_rank;
        if(abs($change) === 0){
            return '—';
        }
        $indicator = $change > 0 ? '▼' : '▲';
        $color = $change > 0 ? 'red' : 'green';
        return "<span style='color: $color;'>$indicator " . abs($change) . "</span>";
    }

    return '—';
}

function get_keyword_data($keyword_id) {
    global $wpdb;

    // Replace 'your_table_name' with the actual names of your tables
    $history_table = $wpdb->prefix . 'keyword_tracker_history';
    $keyword_table = $wpdb->prefix . 'keyword_tracker';

    $query = $wpdb->prepare("
        SELECT 
            k.keyword,
            h.date, 
            h.rank 
        FROM 
            $history_table h 
        JOIN 
            $keyword_table k 
        ON 
            h.keyword_id = k.id 
        WHERE 
            h.keyword_id = %d 
        ORDER BY 
            h.date DESC", 
        $keyword_id
    );

    return $wpdb->get_results($query);
}