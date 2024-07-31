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

    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';
    $history_table_name = $wpdb->prefix . 'keyword_tracker_history';
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql_history);

    keyword_tracker_debug("Keyword Tracker Table Created: $sql");
    keyword_tracker_debug("Keyword Tracker History Table Created: $sql_history");
}
register_activation_hook(__FILE__, 'keyword_tracker_activation');


function keyword_tracker_deactivation() {
    wp_clear_scheduled_hook('keyword_tracker_cron_event_minute');
    wp_clear_scheduled_hook('keyword_tracker_cron_event_daily');
}
register_deactivation_hook(__FILE__, 'keyword_tracker_deactivation');
