# WordPress Keyword Tracker Plugin

## Description
The WordPress Keyword Tracker Plugin helps you track keyword rankings over time. It allows you to add keywords, view ranking history, and update rankings. This plugin also provides an interactive chart to visualize ranking changes.

## Features
- Add and manage keywords.
- View and update keyword rankings.
- Interactive chart to visualize ranking changes.
- AJAX-powered updates for smooth user experience.
- Select and update multiple keywords at once.
- Automatically disable "Update Selected Keywords" button if no rows are selected.

## Installation
1. Download the plugin files and upload them to your WordPress site's `wp-content/plugins` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure you have the required database tables (`keyword_tracker` and `keyword_tracker_history`) set up.

## Usage
1. Navigate to the 'Keyword Tracker' menu in the WordPress admin dashboard.
2. Use the form to add new keywords.
3. View the keyword table to see the list of added keywords and their current rankings.
4. Click on the chart icon in the action column to view the ranking history chart for a keyword.
5. Select multiple keywords and click "Update Selected Keywords" to refresh their rankings.

## Dependencies
- jQuery
- Moment.js
- Chart.js
- Chart.js Adapter for Moment.js

## JavaScript Functions

### Chart Display
- **Event Handler**: `.show-chart`
  - Fetches keyword ranking data and displays an interactive chart in a popup.

### Checkbox Handling
- **Event Handlers**: `.select-row`, `#select-all`
  - Enables or disables the "Update Selected Keywords" button based on checkbox selection.

## PHP Functions

### get_keyword_data
Fetches the keyword ranking history and keyword text from the database.

```php
function get_keyword_data($keyword_id) {
    global $wpdb;

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
            h.date ASC", 
        $keyword_id
    );

    return $wpdb->get_results($query);
}
