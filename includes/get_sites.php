<?php
header('Content-Type: application/json');
echo json_encode([
    [
        'name' => 'Site 1',
        'lat' => 48.8566,
        'lng' => 2.3522,
        'address' => 'Paris, France'
    ],
    [
        'name' => 'Site 2', 
        'lat' => 48.8584,
        'lng' => 2.2945,
        'address' => 'Tour Eiffel, Paris'
    ]
]);
?>