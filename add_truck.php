<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "webtech_project";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  echo json_encode(["success" => false, "error" => "DB connection failed"]);
  exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg_number   = $_POST['reg_number'] ?? '';
    $driver       = $_POST['driver'] ?? '';
    $driver_phone = $_POST['driver_phone'] ?? '';
    $status       = $_POST['status'] ?? '';
    $truck_type   = $_POST['truck_type'] ?? '';
    $current_location = $_POST['current_location'] ?? '';

    $sql = "INSERT INTO `truck_data`
           (`Reg Number`, `Driver`, `Driver Phone`, `Status`, `Truck Type`, `Current Location`)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $reg_number, $driver, $driver_phone, $status, $truck_type, $current_location);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "data" => [
                "reg_number" => $reg_number,
                "driver" => $driver,
                "driver_phone" => $driver_phone,
                "status" => $status,
                "truck_type" => $truck_type,
                "current_location" => $current_location
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
}
$conn->close();
?>
