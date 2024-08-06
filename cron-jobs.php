<?php

function keyword_tracker_cron_job_minute() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';
    $history_table_name = $wpdb->prefix . 'keyword_tracker_history';
    $keywords = $wpdb->get_results("SELECT * FROM $table_name WHERE rank = '-1'");
    if (empty($keywords)) {
        return;
    }
    
    $domain = parse_url(home_url(), PHP_URL_HOST);

    foreach ($keywords as $keyword) {
        $keyword_text = $keyword->keyword;
        $location = $keyword->location;
        $search_type = $keyword->search_type;
        $results = fetch_keyword_data($keyword_text, $location, $domain, $search_type);
        if ($results) {
            foreach ($results as $result) {
                $wpdb->update(
                    $table_name,
                    [
                        'title' => $result['title'],
                        'link' => $result['link'],
                        'rank' => $result['position']
                    ],
                    ['id' => $keyword->id]
                );

                // Update daily rank history
                $current_date = current_time('Y-m-d');
                $existing_history = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $history_table_name WHERE keyword_id = %d AND date = %s",
                    $keyword->id,
                    $current_date
                ));

                if ($existing_history) {
                    $wpdb->update(
                        $history_table_name,
                        ['rank' => $result['position']],
                        ['id' => $existing_history->id]
                    );
                } else {
                    $wpdb->insert(
                        $history_table_name,
                        [
                            'keyword_id' => $keyword->id,
                            'date' => $current_date,
                            'rank' => $result['position']
                        ]
                    );
                }
            }
        } else {
            $current_date = current_time('Y-m-d');
            $existing_history = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $history_table_name WHERE keyword_id = %d AND date = %s",
                $keyword->id,
                $current_date
            ));

            if ($existing_history) {
                $wpdb->update(
                    $history_table_name,
                    ['rank' => 101],
                    ['id' => $existing_history->id]
                );
            } else {
                $wpdb->insert(
                    $history_table_name,
                    [
                        'keyword_id' => $keyword->id,
                        'date' => $current_date,
                        'rank' => 101,
                    ]
                );
            }

            $wpdb->update(
                $table_name,
                [
                    'rank' => 101
                ],
                ['id' => $keyword->id]
            );
        }
    }
}
add_action('keyword_tracker_cron_event_minute', 'keyword_tracker_cron_job_minute');

function gmb_tracker_cron_job() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gmb_keyword_tracker';
    $grid_point_table_name = $wpdb->prefix . 'gmb_grid_points';
    $keywords = $wpdb->get_results("SELECT * FROM $table_name WHERE last_ranking_check IS NULL");

    if (empty($keywords)) {
        return;
    }
    
    $place_id = 'ChIJ9e2aSY2fVogRPEAhJKPq1OQ';

    foreach ($keywords as $keyword) {
        $keyword_text = $keyword->keyword;
        $place_id = $keyword->place_id;
        $location = $keyword->location;
        $gmb_id = $keyword->id;

        $grid_points = $wpdb->get_results($wpdb->prepare("SELECT * FROM $grid_point_table_name WHERE gmb_id = %d", $gmb_id));

        if (empty($grid_points)) {
            continue;
        }

        $total_ranking = 0;
        $ranked_points = 0;

        foreach ($grid_points as $point) {
            $location_coordinates = '@'.$point->lat . ',' . $point->lng.',10z';
            $results = fetch_gmb_by_keyword_data($keyword_text, $location_coordinates, $place_id);

            if ($results) {
                foreach ($results as $result) {
                    $ranking =  $result['position']; // Assuming the place is found at rank 1. Adjust this based on actual result.
                    $total_ranking += $ranking;
                    $ranked_points++;
    
                    $wpdb->update(
                        $grid_point_table_name,
                        ['ranking' => $ranking],
                        ['id' => $point->id]
                    );
                }
                
            } else {
                $wpdb->update(
                    $grid_point_table_name,
                    ['ranking' => null],
                    ['id' => $point->id]
                );
            }
        }

        $avg_ranking = $ranked_points > 0 ? $total_ranking / $ranked_points : null;
        $current_time = current_time('mysql');

        $wpdb->update(
            $table_name,
            [
                'avg_ranking' => $avg_ranking,
                'last_ranking_check' => $current_time
            ],
            ['id' => $gmb_id]
        );
    }
}
add_action('gmb_tracker_cron_event_minute', 'gmb_tracker_cron_job');


function keyword_tracker_cron_job_daily() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';
    $history_table_name = $wpdb->prefix . 'keyword_tracker_history';
    $keywords = $wpdb->get_results("SELECT * FROM $table_name");
    if (empty($keywords)) {
        return;
    }
    $domain = parse_url(home_url(), PHP_URL_HOST);

    foreach ($keywords as $keyword) {
        $keyword_text = $keyword->keyword;
        $location = $keyword->location;
        $search_type = $keyword->search_type;
        $results = fetch_keyword_data($keyword_text, $location, $domain, $search_type);
        if ($results) {
            foreach ($results as $result) {
                $wpdb->update(
                    $table_name,
                    [
                        'title' => $result['title'],
                        'link' => $result['link'],
                        'rank' => $result['position']
                    ],
                    ['id' => $keyword->id]
                );

                // Update daily rank history
                $current_date = current_time('Y-m-d');
                $existing_history = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $history_table_name WHERE keyword_id = %d AND date = %s",
                    $keyword->id,
                    $current_date
                ));

                if ($existing_history) {
                    $wpdb->update(
                        $history_table_name,
                        ['rank' => $result['position']],
                        ['id' => $existing_history->id]
                    );
                } else {
                    $wpdb->insert(
                        $history_table_name,
                        [
                            'keyword_id' => $keyword->id,
                            'date' => $current_date,
                            'rank' => $result['position']
                        ]
                    );
                }
            }
        } else {
            $wpdb->update(
                $table_name,
                [
                    'rank' => 101
                ],
                ['id' => $keyword->id]
            );
        }
    }
}
add_action('keyword_tracker_cron_event_daily', 'keyword_tracker_cron_job_daily');



function keyword_tracker_activation() {
    if (!wp_next_scheduled('keyword_tracker_cron_event_minute')) {
        wp_schedule_event(time(), 'every_minute', 'keyword_tracker_cron_event_minute');
    }
    if (!wp_next_scheduled('keyword_tracker_cron_event_daily')) {
        wp_schedule_event(time(), 'daily', 'keyword_tracker_cron_event_daily');
    }
	
	if (!wp_next_scheduled('gmb_tracker_cron_event_minute')) {
        wp_schedule_event(time(), 'every_minute', 'gmb_tracker_cron_event_minute');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';
    $history_table_name = $wpdb->prefix . 'keyword_tracker_history';
    $gmb_table_name = $wpdb->prefix . 'gmb_keyword_tracker';
    $grid_points_table_name = $wpdb->prefix . 'gmb_grid_points';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        keyword varchar(255) NOT NULL,
        location varchar(255) NOT NULL,
        title text NOT NULL,
        link text NOT NULL,
        rank smallint NOT NULL,
        search_type varchar(10) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql_history = "CREATE TABLE IF NOT EXISTS $history_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        keyword_id mediumint(9) NOT NULL,
        date date NOT NULL,
        rank smallint NOT NULL,
        PRIMARY KEY  (id),
        FOREIGN KEY (keyword_id) REFERENCES $table_name(id) ON DELETE CASCADE
    ) $charset_collate;";

    $sql_gmb = "CREATE TABLE IF NOT EXISTS $gmb_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        keyword varchar(255) NOT NULL,
        place_id varchar(255) NOT NULL,
        location varchar(255) NOT NULL,
        grid_radius smallint NOT NULL,
        grid_points smallint NOT NULL,
        center_lat varchar(255) NOT NULL
        center_lon varchar(255) NOT NULL
        created_date datetime NOT NULL,
        avg_ranking float NOT NULL,
        last_ranking_check datetime DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql_grid_points = "CREATE TABLE IF NOT EXISTS $grid_points_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        gmb_id mediumint(9) NOT NULL,
        lat float NOT NULL,
        lng float NOT NULL,
        ranking smallint DEFAULT NULL,
        PRIMARY KEY  (id),
        FOREIGN KEY (gmb_id) REFERENCES $gmb_table_name(id) ON DELETE CASCADE
    ) $charset_collate;";


    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql_history);
    dbDelta($sql_gmb);
    dbDelta($sql_grid_points);

    keyword_tracker_debug("Keyword Tracker Table Created: $sql");
    keyword_tracker_debug("Keyword Tracker History Table Created: $sql_history");
    keyword_tracker_debug("GMB Keyword Tracker Table Created: $sql_gmb");
    keyword_tracker_debug("GMB Grid Points Table Created: $sql_grid_points");
}
register_activation_hook(__FILE__, 'keyword_tracker_activation');


function keyword_tracker_deactivation() {
    wp_clear_scheduled_hook('keyword_tracker_cron_event_minute');
    wp_clear_scheduled_hook('keyword_tracker_cron_event_daily');
    wp_clear_scheduled_hook('gmb_tracker_cron_event_minute');
}
register_deactivation_hook(__FILE__, 'keyword_tracker_deactivation');
