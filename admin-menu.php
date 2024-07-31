<?php

function keyword_tracker_menu() {
    add_menu_page('Keyword Tracker', 'Keyword Tracker', 'manage_options', 'keyword-tracker', 'keyword_tracker_page', 'dashicons-chart-line', 100);
    add_submenu_page('keyword-tracker', 'Settings', 'Settings', 'manage_options', 'keyword-tracker-settings', 'keyword_tracker_settings_page');
}
add_action('admin_menu', 'keyword_tracker_menu');

function keyword_tracker_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'keyword_tracker';
    $results = $wpdb->get_results("SELECT * FROM $table_name");
    $order = isset($_GET['order']) ? $_GET['order'] : 'asc'; // Default to ascending order if not set

    $next_order = ($order === 'asc') ? 'desc' : 'asc';

    ?>
    <div class="wrap">
        <h1>Keyword Tracker</h1>
        <?php settings_errors(); ?>
        
        <form id="add-keyword-form" method="post">
            <input type="text" name="keyword" placeholder="Keyword" required style="margin-right: 10px;" />
            <div class="suggestion-list-container" style="position:relative; display:inline-flex;">
                <input type="text" name="location" placeholder="Location" required style="margin-right: 10px;" id="location-input" autocomplete="off" />
                <div id="location-suggestions" style="display: none;"></div>
                <select name="search_type" required style="margin-right: 10px;">
                    <option value="desktop">Desktop</option>
                    <option value="mobile">Mobile</option>
                </select>
            </div>
            
            <input type="submit" value="Add Keyword" class="button button-primary" />
        </form>
        
       
        
        <div id="loading-indicator" style="display: none;">Loading...</div>

        <div id="keyword-results-container" style="display: none; width:100%;">
            <h2>Keyword Tracking Results</h2>
            <button id="update-selected" style="margin-bottom: 10px;" class="button button-primary">Update Selected Keywords</button>
            <table id="keyword-results" class="widefat" >
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Keyword</th>
                    <th><a href="javascript:void(0);" id="rank-header">Rank</a></th>
                    <th>1d</th>
                    <th>7d</th>
                    <th>30d</th>
                    <th>Life</th>
                    <th>Link</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result) : 
                    $link_path = parse_url($result->link, PHP_URL_PATH);
                ?>
                <tr data-id="<?php echo esc_attr($result->id); ?>">
                    <td>
                        <input type="checkbox" class="select-row">
                        <span class="icon">
                            <?php if ($result->search_type === 'mobile') : ?>
                                <span class="dashicons dashicons-smartphone" title="Search Type: Mobile"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-desktop" title="Search Type: Desktop"></span>
                            <?php endif; ?>
                            <?php if ($result->location) : ?>
                                <span class="dashicons dashicons-location" title="Location: <?php echo esc_html($result->location); ?>"></span>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(strtolower($result->keyword)); ?></td>
                    <td class="rank" data-rank="<?php echo ($result->rank === '-1' || $result->rank === '101') ? '100' : esc_attr($result->rank); ?>">
                        <?php if ($result->rank === '-1') : ?>
                        Updating...
                        <?php elseif ($result->rank === '101') : ?>
                        +100
                        <?php else : ?>
                        <?php echo esc_html($result->rank); ?>
                        <?php endif; ?> 
                    </td>
                    <td><?php echo get_keyword_changes($result->id, 1); ?></td>
                    <td><?php echo get_keyword_changes($result->id, 7); ?></td>
                    <td><?php echo get_keyword_changes($result->id, 30); ?></td>
                    <td><?php echo get_life_change($result->id); ?></td>
                    <td class="link"><a href="<?php echo esc_url($result->link); ?>" target="_blank"><?php echo esc_html($link_path); ?></a></td>
                    <td>
                        <a href="#" class="button update-keyword" data-id="<?php echo esc_attr($result->id); ?>"><span class="dashicons dashicons-update" style="vertical-align:middle"></span></a>
                        <a href="#" class="button delete-keyword" data-id="<?php echo esc_attr($result->id); ?>"><span class="dashicons dashicons-trash" style="vertical-align:middle"></span></a>
                        <a href="#" class="button show-chart" data-id="<?php echo esc_attr($result->id); ?>"><span class="dashicons dashicons-chart-area" style="vertical-align:middle"></span></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
        <div id="chart-popup" style="display:none;">
            <div id="chart-container">
                <h3 id="keyword-title"></h3> <!-- Element to display the keyword -->
                <canvas id="rank-chart"></canvas>
            </div>
        </div>
    </div>
    <?php
}

function keyword_tracker_settings_page() {
    ?>
    <div class="wrap">
        <h1>Keyword Tracker Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('keyword_tracker_settings_group');
            do_settings_sections('keyword-tracker-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function keyword_tracker_settings() {
    register_setting('keyword_tracker_settings_group', 'serpapi_key');

    add_settings_section('keyword_tracker_settings_section', 'API Settings', null, 'keyword-tracker-settings');

    add_settings_field('serpapi_key', 'SERPAPI Key', 'keyword_tracker_serpapi_key_callback', 'keyword-tracker-settings', 'keyword_tracker_settings_section');
}
add_action('admin_init', 'keyword_tracker_settings');

function keyword_tracker_serpapi_key_callback() {
    $serpapi_key = get_option('serpapi_key');
    echo '<input type="text" name="serpapi_key" value="' . esc_attr($serpapi_key) . '" />';
}

