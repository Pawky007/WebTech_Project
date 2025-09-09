<?php
// Set timezone
date_default_timezone_set('Asia/Dhaka');

// ----- DATA -----
// Define available locations (only Dhaka, Chattogram, and Cumilla)
$locations = ["Dhaka", "Chattogram", "Cumilla"];

// Initialize form fields with empty values
$routeFrom = $routeTo = $driverName = $driverFee = $fuelCost = $otherCosts = $revenue = '';
$distance = 0;
$totalCost = 0;
$profit = 0;
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect the posted data
    $routeFrom = $_POST['routeFrom'] ?? '';
    $routeTo = $_POST['routeTo'] ?? '';
    $driverName = $_POST['driverName'] ?? '';
    $driverFee = $_POST['driverFee'] ?? '';
    $fuelCost = $_POST['fuelCost'] ?? '';
    $otherCosts = $_POST['otherCosts'] ?? '';
    $revenue = $_POST['revenue'] ?? '';

    // Prevent negative values for costs
    if ($driverFee < 0 || $fuelCost < 0 || $otherCosts < 0 || $revenue < 0) {
        $errorMessage = "Values cannot be negative.";
    }

    // Calculate the distance if "From" and "To" are selected
    if (!empty($routeFrom) && !empty($routeTo)) {
        $distance = calculateDistance($routeFrom, $routeTo);
    }

    // Ensure that revenue and total costs are numbers (float)
    $revenue = (float) $revenue; // Cast to float
    $driverFee = (float) $driverFee; // Cast to float
    $fuelCost = (float) $fuelCost; // Cast to float
    $otherCosts = (float) $otherCosts; // Cast to float

    // Calculate total cost and profit
    $totalCost = $driverFee + $fuelCost + $otherCosts;
    $profit = $revenue - $totalCost;
}

// Function to calculate the distance based on the trip data
function calculateDistance($routeFrom, $routeTo) {
    // Define distances between locations (in km)
    $distances = [
        "Dhaka" => ["Chattogram" => 253, "Cumilla" => 109],
        "Chattogram" => ["Dhaka" => 253, "Cumilla" => 152],
        "Cumilla" => ["Dhaka" => 109, "Chattogram" => 152]
    ];

    // Return the distance between "From" and "To"
    return $distances[$routeFrom][$routeTo] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>🚚 HaulPro – Truck 1 Trip Calculation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Combined CSS for Light and Dark Mode -->
    <link rel="stylesheet" href="calculation.css"> <!-- Modern CSS for form styling -->

</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="left">
            <button class="btn link" onclick="goHome()">🏠 Home</button>
            <span class="brand">HaulPro</span>
            <span class="truck-badge">Truck 1</span>
        </div>
        <div class="right">
            <button class="btn" onclick="toggleTheme()">🌙 Dark Mode</button>
            <a href="logout.php" class="btn">🚪 Logout</a>
        </div>
    </div>

    <div class="card">
        <h2>🚚 Trip Details Form</h2>

        <!-- Show error message if any -->
        <?php if (isset($errorMessage)): ?>
            <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <form action="calculationInput.php" method="POST">
            <!-- From Dropdown -->
            <label for="routeFrom">From:</label>
            <select id="routeFrom" name="routeFrom" required onchange="updateDistance()">
                <option value="">Select From</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?= htmlspecialchars($location) ?>" <?= ($routeFrom == $location) ? 'selected' : '' ?>><?= htmlspecialchars($location) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- To Dropdown -->
            <label for="routeTo">To:</label>
            <select id="routeTo" name="routeTo" required onchange="updateDistance()">
                <option value="">Select To</option>
                <?php foreach ($locations as $location): ?>
                    <?php if ($routeFrom != $location || $routeTo == $location): ?>
                        <option value="<?= htmlspecialchars($location) ?>" <?= ($routeTo == $location) ? 'selected' : '' ?>><?= htmlspecialchars($location) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <!-- Distance Display -->
            <label for="distance">Distance (km):</label>
            <div id="distanceDisplay"><?= htmlspecialchars($distance) ?: 'Select both locations to see distance' ?></div>

            <!-- Revenue Input -->
            <label for="revenue">Revenue (BDT):</label>
            <input type="number" id="revenue" name="revenue" value="<?= htmlspecialchars($revenue) ?>" required>

            <!-- Driver Info -->
            <label for="driverName">Driver's Name:</label>
            <input type="text" id="driverName" name="driverName" value="<?= htmlspecialchars($driverName) ?>" required>

            <!-- Cost Details -->
            <label for="driverFee">Driver Fee (BDT):</label>
            <input type="number" id="driverFee" name="driverFee" value="<?= htmlspecialchars($driverFee) ?>" required>

            <label for="fuelCost">Fuel Cost (BDT):</label>
            <input type="number" id="fuelCost" name="fuelCost" value="<?= htmlspecialchars($fuelCost) ?>" required>

            <label for="otherCosts">Other Costs (BDT):</label>
            <input type="number" id="otherCosts" name="otherCosts" value="<?= htmlspecialchars($otherCosts) ?>" required>

            <!-- Total Cost Display -->
            <label for="totalCost">Total Cost (BDT):</label>
            <div id="totalCost"><?= number_format($totalCost) ?: '0.00' ?></div>

            <!-- Profit Display -->
            <label for="profit">Profit (BDT):</label>
            <div id="profit"><?= number_format($profit) ?: '0.00' ?></div>

            <button type="submit" class="btn">Submit</button>
        </form>
    </div>
</div>

<script>
    // Update the distance when both From and To are selected
    function updateDistance() {
        var routeFrom = document.getElementById("routeFrom").value;
        var routeTo = document.getElementById("routeTo").value;
        var distanceDisplay = document.getElementById("distanceDisplay");

        if (routeFrom && routeTo && routeFrom !== routeTo) {
            // Calculate the distance using PHP data
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'calculate_distance.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    distanceDisplay.textContent = xhr.responseText;
                }
            };
            xhr.send('routeFrom=' + routeFrom + '&routeTo=' + routeTo);
        } else {
            distanceDisplay.textContent = 'Select both locations to see distance'; // Placeholder
        }
    }

    // Dark Mode Toggle Function
    function toggleTheme() {
        var body = document.body;
        body.classList.toggle("dark-mode"); // Toggle dark mode class for body
    }

    // Display total cost and profit dynamically
    document.getElementById('driverFee').addEventListener('input', updateTotalCost);
    document.getElementById('fuelCost').addEventListener('input', updateTotalCost);
    document.getElementById('otherCosts').addEventListener('input', updateTotalCost);
    document.getElementById('revenue').addEventListener('input', updateProfit);

    function updateTotalCost() {
        var driverFee = parseFloat(document.getElementById('driverFee').value) || 0;
        var fuelCost = parseFloat(document.getElementById('fuelCost').value) || 0;
        var otherCosts = parseFloat(document.getElementById('otherCosts').value) || 0;
        var totalCost = driverFee + fuelCost + otherCosts;
        document.getElementById('totalCost').textContent = totalCost.toFixed(2);
        updateProfit();
    }

    function updateProfit() {
        var revenue = parseFloat(document.getElementById('revenue').value) || 0;
        var totalCost = parseFloat(document.getElementById('totalCost').textContent) || 0;
        var profit = revenue - totalCost;
        document.getElementById('profit').textContent = profit.toFixed(2);
    }
</script>

</body>
</html>
