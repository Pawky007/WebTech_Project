<?php
date_default_timezone_set('Asia/Dhaka');

// ----- DATA -----
// Assuming you have different trip data for each truck
$truckData = [
  'truck1' => [
    ["route"=>"Cumilla → Feni", "distance"=>70, "revenue"=>8500, "expense"=>5000, "type"=>"Round", "date"=>"2024-05-05"], 
    ["route"=>"Sylhet → Khulna", "distance"=>410, "revenue"=>21000, "expense"=>14000, "type"=>"Single", "date"=>"2024-06-15"], 
    ["route"=>"Sylhet → Rajshahi", "distance"=>470, "revenue"=>23000, "expense"=>15000, "type"=>"Round", "date"=>"2024-08-01"],
    ["route"=>"Chattogram → Khulna", "distance"=>450, "revenue"=>24000, "expense"=>16000, "type"=>"Single", "date"=>"2024-09-01"],
    ["route"=>"Chattogram → Sylhet", "distance"=>330, "revenue"=>19000, "expense"=>13000, "type"=>"Round", "date"=>"2024-10-05"],
    ["route"=>"Chattogram → Cox’s Bazar", "distance"=>150, "revenue"=>11000, "expense"=>7000, "type"=>"Single", "date"=>"2024-11-15"],
    ["route"=>"Dhaka → Feni", "distance"=>170, "revenue"=>11000, "expense"=>7000, "type"=>"Round", "date"=>"2024-12-10"],
    ["route"=>"Dhaka → Cumilla", "distance"=>100, "revenue"=>9500, "expense"=>6000, "type"=>"Single", "date"=>"2025-01-20"],
    ["route"=>"Dhaka → Rajshahi", "distance"=>255, "revenue"=>14000, "expense"=>9000, "type"=>"Single", "date"=>"2025-02-12"],
    ["route"=>"Dhaka → Khulna", "distance"=>275, "revenue"=>15000, "expense"=>10000, "type"=>"Round", "date"=>"2025-03-01"],
    ["route"=>"Dhaka → Cox’s Bazar", "distance"=>390, "revenue"=>24000, "expense"=>15000, "type"=>"Single", "date"=>"2025-05-05"],
    ["route"=>"Dhaka → Sylhet", "distance"=>240, "revenue"=>16000, "expense"=>11000, "type"=>"Round", "date"=>"2025-08-01"],
    ["route"=>"Dhaka → Cox’s Bazar", "distance"=>390, "revenue"=>24500, "expense"=>15200, "type"=>"Single", "date"=>"2025-08-02"],
    ["route"=>"Dhaka → Khulna", "distance"=>275, "revenue"=>15100, "expense"=>10150, "type"=>"Round", "date"=>"2025-08-23"],
    ["route"=>"Dhaka → Rajshahi", "distance"=>255, "revenue"=>14200, "expense"=>9200, "type"=>"Single", "date"=>"2025-08-31"],
    ["route"=>"Dhaka → Chattogram", "distance"=>245, "revenue"=>17000, "expense"=>12000, "type"=>"Single", "date"=>"2025-09-01"],
  ],
  'truck2' => [
    ["route"=>"Cumilla → Feni", "distance"=>70, "revenue"=>8500, "expense"=>5000, "type"=>"Round", "date"=>"2024-05-05"], 
    ["route"=>"Sylhet → Khulna", "distance"=>410, "revenue"=>21000, "expense"=>14000, "type"=>"Single", "date"=>"2024-06-15"], 
    ["route"=>"Sylhet → Rajshahi", "distance"=>470, "revenue"=>23000, "expense"=>15000, "type"=>"Round", "date"=>"2024-08-01"],
    ["route"=>"Chattogram → Khulna", "distance"=>450, "revenue"=>24000, "expense"=>16000, "type"=>"Single", "date"=>"2024-09-01"],
    ["route"=>"Chattogram → Sylhet", "distance"=>330, "revenue"=>19000, "expense"=>13000, "type"=>"Round", "date"=>"2024-10-05"],
    ["route"=>"Chattogram → Cox’s Bazar", "distance"=>150, "revenue"=>11000, "expense"=>7000, "type"=>"Single", "date"=>"2024-11-15"],
    ["route"=>"Dhaka → Feni", "distance"=>170, "revenue"=>11000, "expense"=>7000, "type"=>"Round", "date"=>"2024-12-10"],
    ["route"=>"Dhaka → Cumilla", "distance"=>100, "revenue"=>9500, "expense"=>6000, "type"=>"Single", "date"=>"2025-01-20"],
    ["route"=>"Dhaka → Rajshahi", "distance"=>255, "revenue"=>14000, "expense"=>9000, "type"=>"Single", "date"=>"2025-02-12"],
    ["route"=>"Dhaka → Khulna", "distance"=>275, "revenue"=>15000, "expense"=>10000, "type"=>"Round", "date"=>"2025-03-01"],
    ["route"=>"Dhaka → Cox’s Bazar", "distance"=>390, "revenue"=>24000, "expense"=>15000, "type"=>"Single", "date"=>"2025-05-05"],
    ["route"=>"Dhaka → Sylhet", "distance"=>240, "revenue"=>16000, "expense"=>11000, "type"=>"Round", "date"=>"2025-08-01"],
    ["route"=>"Dhaka → Cox’s Bazar", "distance"=>390, "revenue"=>24500, "expense"=>15200, "type"=>"Single", "date"=>"2025-08-02"],
    ["route"=>"Dhaka → Khulna", "distance"=>275, "revenue"=>15100, "expense"=>10150, "type"=>"Round", "date"=>"2025-08-23"],
    ["route"=>"Dhaka → Rajshahi", "distance"=>255, "revenue"=>14200, "expense"=>9200, "type"=>"Single", "date"=>"2025-08-31"],
    ["route"=>"Dhaka → Chattogram", "distance"=>245, "revenue"=>17000, "expense"=>12000, "type"=>"Single", "date"=>"2025-09-01"],
  ],
  'truck3' => [
    ["route"=>"Cumilla → Feni", "distance"=>70, "revenue"=>8500, "expense"=>5000, "type"=>"Round", "date"=>"2024-05-05"], 
    ["route"=>"Sylhet → Khulna", "distance"=>410, "revenue"=>21000, "expense"=>14000, "type"=>"Single", "date"=>"2024-06-15"], 
    ["route"=>"Sylhet → Rajshahi", "distance"=>470, "revenue"=>23000, "expense"=>15000, "type"=>"Round", "date"=>"2024-08-01"],
    ["route"=>"Chattogram → Khulna", "distance"=>450, "revenue"=>24000, "expense"=>16000, "type"=>"Single", "date"=>"2024-09-01"],
    ["route"=>"Chattogram → Sylhet", "distance"=>330, "revenue"=>19000, "expense"=>13000, "type"=>"Round", "date"=>"2024-10-05"],
    ["route"=>"Chattogram → Cox’s Bazar", "distance"=>150, "revenue"=>11000, "expense"=>7000, "type"=>"Single", "date"=>"2024-11-15"],
    ["route"=>"Dhaka → Feni", "distance"=>170, "revenue"=>11000, "expense"=>7000, "type"=>"Round", "date"=>"2024-12-10"],
    ["route"=>"Dhaka → Cumilla", "distance"=>100, "revenue"=>9500, "expense"=>6000, "type"=>"Single", "date"=>"2025-01-20"],
    ["route"=>"Dhaka → Rajshahi", "distance"=>255, "revenue"=>14000, "expense"=>9000, "type"=>"Single", "date"=>"2025-02-12"],
    ["route"=>"Dhaka → Khulna", "distance"=>275, "revenue"=>15000, "expense"=>10000, "type"=>"Round", "date"=>"2025-03-01"],
    ["route"=>"Dhaka → Cox’s Bazar", "distance"=>390, "revenue"=>24000, "expense"=>15000, "type"=>"Single", "date"=>"2025-05-05"],
    ["route"=>"Dhaka → Sylhet", "distance"=>240, "revenue"=>16000, "expense"=>11000, "type"=>"Round", "date"=>"2025-08-01"],
    ["route"=>"Dhaka → Cox’s Bazar", "distance"=>390, "revenue"=>24500, "expense"=>15200, "type"=>"Single", "date"=>"2025-08-02"],
    ["route"=>"Dhaka → Khulna", "distance"=>275, "revenue"=>15100, "expense"=>10150, "type"=>"Round", "date"=>"2025-08-23"],
    ["route"=>"Dhaka → Rajshahi", "distance"=>255, "revenue"=>14200, "expense"=>9200, "type"=>"Single", "date"=>"2025-08-31"],
    ["route"=>"Dhaka → Chattogram", "distance"=>245, "revenue"=>17000, "expense"=>12000, "type"=>"Single", "date"=>"2025-09-01"],
  ]
];

// ----- FILTERS -----
$truck = $_GET['truck'] ?? 'truck1';  // Default to Truck 1 if no truck is selected
$range  = $_GET['range'] ?? '30';  // Default to the last 30 days
$anchor = $_GET['anchor'] ?? null;
$rangeLabel = ($range === 'all') ? 'All Trips' : "Last $range Days";

// Retrieve the trips for the selected truck
$trips = $truckData[$truck];

// Filter trips based on the range
if ($anchor && preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
  $end   = $anchor;
  $start = date("Y-m-d", strtotime("$end -30 days"));
  $filtered = array_filter($trips, fn($t) => $t["date"] >= $start && $t["date"] <= $end);
  $rangeLabel = "30 Days ending " . date("d/m/Y", strtotime($end));
  $range = 30;
} else {
  if ($range === 'all') {
    $filtered = $trips;
  } else {
    $cutoff = date("Y-m-d", strtotime("-$range days"));
    $filtered = array_filter($trips, fn($t) => $t["date"] >= $cutoff);
  }
}

// Sort & totals
usort($filtered, fn($a,$b) => strtotime($a["date"]) <=> strtotime($b["date"]));
$totalRevenue = $totalExpense = $totalProfit = 0;
foreach ($filtered as $row) {
  $m = ($row['type'] === 'Round') ? 2 : 1;
  $rev = $row['revenue'] * $m; 
  $exp = $row['expense'] * $m;
  $totalRevenue += $rev; 
  $totalExpense += $exp; 
  $totalProfit += ($rev - $exp);
}

$grandRevenue = $grandExpense = 0;
foreach ($trips as $row) {
  $m = ($row['type'] === 'Round') ? 2 : 1;
  $grandRevenue += $row['revenue'] * $m; 
  $grandExpense += $row['expense'] * $m;
}

$grandProfit = $grandRevenue - $grandExpense;
$anchorIsoForJs = $anchor ?: date('Y-m-d');

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
            <a href="http://localhost/WebTech_Project/Lorry_List.php"
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
              $m = ($t['type'] === 'Round') ? 2 : 1;
              $distance = $t['distance'] * $m; $revenue = $t['revenue'] * $m; $expense = $t['expense'] * $m; $profit = $revenue - $expense;
            ?>
            <tr>
              <td><?= $serial ?></td>
              <td><?= $t["date"] ?></td>
              <td><?= htmlspecialchars($t["route"]) ?></td>
              <td><?= htmlspecialchars($t["type"]) ?></td>
              <td><?= number_format($distance) ?> km</td>
              <td>৳<?= number_format($revenue) ?></td>
              <td>৳<?= number_format($expense) ?></td>
              <td>৳<?= number_format($profit) ?></td>
              <td><button class="receipt-btn" onclick="openReceipt({
                receipt:'<?= $serial ?>', no:'<?= $serial ?>', date:'<?= $t["date"] ?>',
                route:'<?= htmlspecialchars($t["route"], ENT_QUOTES) ?>', type:'<?= $t["type"] ?>',
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
