<?php
/*
Plugin Name: Keyword Tracker by WDA
Plugin URI: https://wedevall.com/
Description: A plugin to track keyword rankings from specific locations using SERPAPI.
Version: 1.0
Author: WeDevAll
Author URI: https://wedevall.com/
License: GPL2
*/

// Include necessary files
include_once plugin_dir_path(__FILE__) . 'admin-menu.php';
include_once plugin_dir_path(__FILE__) . 'ajax-handlers.php';
include_once plugin_dir_path(__FILE__) . 'cron-jobs.php';
include_once plugin_dir_path(__FILE__) . 'helpers.php';
include_once plugin_dir_path(__FILE__) . 'enqueue-scripts.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'keyword_tracker_activation');
register_deactivation_hook(__FILE__, 'keyword_tracker_deactivation');
