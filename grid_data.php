<?php
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$locations = get_location_data_from_serpapi(); // Implement this function to fetch data from SerpApi
$grid_size = 0.1; // Define the size of each grid cell (latitude/longitude degrees)
$grid = [];

foreach ($locations as $location) {
    $lat = $location['latitude'];
    $lng = $location['longitude'];
    
    $grid_lat = round($lat / $grid_size) * $grid_size;
    $grid_lng = round($lng / $grid_size) * $grid_size;
    
    $grid_key = "$grid_lat,$grid_lng";
    if (!isset($grid[$grid_key])) {
        $grid[$grid_key] = 0;
    }
    $grid[$grid_key]++;
}

echo json_encode($grid);

function get_location_data_from_serpapi() {
    $api_key = get_option('serpapi_key');
    $location = 'Charlotte, NC';
    $search = 'locksmith';

    $url = "https://serpapi.com/search.json?engine=google_maps&q=$search&location=$location&hl=en&api_key=$api_key";

    $response = file_get_contents($url);
    if ($response === FALSE) {
        die(json_encode(['error' => 'Error fetching data from SerpApi']));
    }
    
    $data = json_decode($response, true);
    if ($data === NULL) {
        die(json_encode(['error' => 'Error decoding JSON from SerpApi']));
    }

    $locations = [];
    if (isset($data['local_results'])) {
        foreach ($data['local_results'] as $result) {
            $locations[] = [
                'latitude' => $result['gps_coordinates']['latitude'],
                'longitude' => $result['gps_coordinates']['longitude']
            ];
        }
    }

    return $locations;
}
