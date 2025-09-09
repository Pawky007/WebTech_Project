<?php
// ----- DATA -----
// The corrected distances between the selected cities
$distances = [
    "Dhaka" => [
        "Chattogram" => 253, "Cumilla" => 109
    ],
    "Chattogram" => [
        "Dhaka" => 253, "Cumilla" => 152
    ],
    "Cumilla" => [
        "Dhaka" => 109, "Chattogram" => 152
    ]
];

// Get the values from the POST request
$routeFrom = $_POST['routeFrom'] ?? '';
$routeTo = $_POST['routeTo'] ?? '';

// Check if both "From" and "To" are provided and not the same location
if ($routeFrom && $routeTo && $routeFrom != $routeTo) {
    // Return the distance between the "From" and "To" locations
    echo $distances[$routeFrom][$routeTo] ?? '0';
} else {
    // Return an error message if invalid or same location selected
    echo 'Invalid selection or the same location for both "From" and "To"';
}
?>
