<?php

// Set response type to JSON
header('Content-Type: application/json');

// Receive JSON data
$json = file_get_contents('php://input');

// Convert JSON into PHP object
$data = json_decode($json);

// Get the name
$name = $data->name ?? "";

// Process the received name
if (!empty(trim($name))) {

    $response = array(
        "status" => "success",
        "message" => "Welcome, " . trim($name) . "!"
    );

} else {

    $response = array(
        "status" => "error",
        "message" => "Please enter your name."
    );

}

// Return JSON response
echo json_encode($response);

?>
