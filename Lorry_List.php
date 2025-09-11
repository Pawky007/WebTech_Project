<?php
date_default_timezone_set('Asia/Dhaka');

// ----- Database Connection -----
$servername = "localhost";  // Database server (usually localhost)
$username = "root";         // Database username
$password = "";             // Database password
$dbname = "webtech_project"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----- FILTERS -----
$truck = $_GET['truck'] ?? 'truck1';  // Default to Truck 1 if no truck is selected
$range  = $_GET['range'] ?? '30';  // Default to the last 30 days
$anchor = $_GET['anchor'] ?? null;
$rangeLabel = ($range === 'all') ? 'All Trips' : "Last $range Days";

// Retrieve trips for the selected truck from the database
$sql = "SELECT `Trip No`, `Date (BD)`, `Route`, `Trip Type`, `Distance (km)`, `Rent / Revenue (BDT)`, `Expense (BDT)`, `Profit (BDT)`
        FROM `trip_history`"; // Modified query to select all trips

// Prepare the query for execution
$stmt = $conn->prepare($sql);

// Execute the query
$stmt->execute();
$result = $stmt->get_result();

// Fetch all rows from the result
$trips = $result->fetch_all(MYSQLI_ASSOC);

// ----- FILTERING BASED ON RANGE -----
if ($anchor && preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
  $end   = $anchor;
  $start = date("Y-m-d", strtotime("$end -30 days"));
  $filtered = array_filter($trips, fn($t) => $t["Date (BD)"] >= $start && $t["Date (BD)"] <= $end);
  $rangeLabel = "30 Days ending " . date("d/m/Y", strtotime($end));
  $range = 30;
} else {
  if ($range === 'all') {
    $filtered = $trips;
  } else {
    $cutoff = date("Y-m-d", strtotime("-$range days"));
    $filtered = array_filter($trips, fn($t) => $t["Date (BD)"] >= $cutoff);
  }
}

// ----- CALCULATE TOTALS -----
usort($filtered, fn($a, $b) => strtotime($a["Date (BD)"]) <=> strtotime($b["Date (BD)"]));
$totalRevenue = $totalExpense = $totalProfit = 0;
foreach ($filtered as $row) {
  $m = ($row['Trip Type'] === 'Round') ? 2 : 1;
  $rev = $row['Rent / Revenue (BDT)'] * $m;
  $exp = $row['Expense (BDT)'] * $m;
  $totalRevenue += $rev;
  $totalExpense += $exp;
  $totalProfit += ($rev - $exp);
}

$grandRevenue = $grandExpense = 0;
foreach ($trips as $row) {
  $m = ($row['Trip Type'] === 'Round') ? 2 : 1;
  $grandRevenue += $row['Rent / Revenue (BDT)'] * $m;
  $grandExpense += $row['Expense (BDT)'] * $m;
}

$grandProfit = $grandRevenue - $grandExpense;
$anchorIsoForJs = $anchor ?: date('Y-m-d');

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard</title>
  <link rel="stylesheet" href="dashboad_style.css" />
  <link rel="stylesheet" href="assets/calculationShow.css">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/barba.js/2.9.7/barba.min.js"></script>
</head>
<body>
  <div class="container">
    <aside class="sidebar">
        <img src="Image/Logo.png" alt="HaulPro Logo" width="200px" />
        <h3>HaulPro</h3>
        <ul class="menu">
          <li>
            <a href="Dashboard.html"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-chart-line"></i> Analytics</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-bullhorn"></i> Banner</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-truck"></i> Vehicle</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-cogs"></i> Load Order</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-credit-card"></i> Payment Method</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-users"></i> Transporter List</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-user-tie"></i> Lorry Owner List</a>
          </li>
          <li>
            <a href="http://localhost/WebTech_Project/Truck_list.php"
              ><i class="fa fa-truck-moving"></i> Lorry List</a
            >
          </li>
          <li>
            <a href="#"><i class="fa fa-cogs"></i> Setting</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-question-circle"></i> FAQ</a>
          </li>
        </ul>
        <div class="help-card">
          <img
            src="https://cdn-icons-png.flaticon.com/512/4712/4712002.png"
            alt="Help"
          />
          <p>Need Help?</p>
          <button>Contact Now</button>
        </div>
      </aside>

    <div class="shell">
      <div class="topbar">
        <div class="left">
            <select id="truckList">
              <option value="truck1" <?php echo $truck === 'truck1' ? 'selected' : ''; ?>>Truck 1</option>
              <option value="truck2" <?php echo $truck === 'truck2' ? 'selected' : ''; ?>>Truck 2</option>
              <option value="truck3" <?php echo $truck === 'truck3' ? 'selected' : ''; ?>>Truck 3</option>
            </select>

        </div>
        <div class="right">
          <div class="calendar-wrap">
            <label class="calendar-pill" title="Pick an end date for a 30-day view">
              📅 <input type="text" id="jumpDate" placeholder="dd-mm-yyyy" />
            </label>
          </div>
          <button id="themeToggle" class="btn" type="button">🌙 Dark Mode</button>
          <a href="logout.php" class="btn">🚪 Logout</a>
        </div>
      </div>

      <div class="card">
        <h2><img src="Image/clock.png" alt="" width="30px" style="margin-right: 15px;">Trip History</h2>
        <div class="subtitle"><?= htmlspecialchars($rangeLabel) ?></div>

        <div class="controls">
          <button class="btn <?= $range == 10 ? 'active' : '' ?>" onclick="setRange(10)">Last 10 Days</button>
          <button class="btn <?= $range == 30 ? 'active' : '' ?>" onclick="setRange(30)">Last 30 Days</button>
          <button class="btn <?= $range == 180 ? 'active' : '' ?>" onclick="setRange(180)">Last 6 Months</button>
          <button class="btn <?= $range == 365 ? 'active' : '' ?>" onclick="setRange(365)">Last 1 Year</button>
          <button class="btn <?= $range === 'all' ? 'active' : '' ?>" onclick="setRange('all')">All List</button>
        </div>

        <table>
          <thead>
            <tr>
              <th>Trip No</th><th>Date (BD)</th><th>Route</th><th>Trip Type</th>
              <th>Distance (km)</th><th>Rent / Revenue (BDT)</th><th>Expense (BDT)</th><th>Profit (BDT)</th><th>Receipt</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($filtered): $serial = 1; foreach($filtered as $t): 
              $m = ($t['Trip Type'] === 'Round') ? 2 : 1;
              $distance = $t['Distance (km)'] * $m; $revenue = $t['Rent / Revenue (BDT)'] * $m; $expense = $t['Expense (BDT)'] * $m; $profit = $revenue - $expense;
            ?>
            <tr>
              <td><?= $serial ?></td>
              <td><?= $t["Date (BD)"] ?></td>
              <td><?= htmlspecialchars($t["Route"]) ?></td>
              <td><?= htmlspecialchars($t["Trip Type"]) ?></td>
              <td><?= number_format($distance) ?> km</td>
              <td>৳<?= number_format($revenue) ?></td>
              <td>৳<?= number_format($expense) ?></td>
              <td>৳<?= number_format($profit) ?></td>
              <td><button class="receipt-btn" onclick="openReceipt({
                receipt:'<?= $serial ?>', no:'<?= $serial ?>', date:'<?= $t["Date (BD)"] ?>',
                route:'<?= htmlspecialchars($t["Route"], ENT_QUOTES) ?>', type:'<?= $t["Trip Type"] ?>',
                distance:'<?= number_format($distance) ?>', revenue:'<?= number_format($revenue) ?>',
                expense:'<?= number_format($expense) ?>', profit:'<?= number_format($profit) ?>'
              })">Receipt</button></td>
            </tr>
            <?php $serial++; endforeach; else: ?>
            <tr><td colspan="9">No trips in this range.</td></tr>
            <?php endif; ?>
            <tr class="totals"><td colspan="5">TOTAL (<?= htmlspecialchars($rangeLabel) ?>)</td>
              <td>৳<?= number_format($totalRevenue) ?></td>
              <td>৳<?= number_format($totalExpense) ?></td>
              <td>৳<?= number_format($totalProfit) ?></td><td></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <script>
  // Pass the truck data to JavaScript
  const truckData = <?php echo json_encode($truckData); ?>;
  
  // The selected truck from the URL
  let selectedTruck = "<?php echo $truck; ?>";
</script>

    <!-- Flatpickr + Your JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="assets/calculationShow.js"></script>
  </body>
</html>
