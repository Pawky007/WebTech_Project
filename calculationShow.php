<?php
date_default_timezone_set('Asia/Dhaka');

// ----- DATA -----
$trips = [
  ["route"=>"Cumilla → Feni","distance"=>70,"revenue"=>8500,"expense"=>5000,"type"=>"Round","date"=>"2024-05-05"],
  ["route"=>"Sylhet → Khulna","distance"=>410,"revenue"=>21000,"expense"=>14000,"type"=>"Single","date"=>"2024-06-15"],
  ["route"=>"Sylhet → Rajshahi","distance"=>470,"revenue"=>23000,"expense"=>15000,"type"=>"Round","date"=>"2024-08-01"],
  ["route"=>"Chattogram → Khulna","distance"=>450,"revenue"=>24000,"expense"=>16000,"type"=>"Single","date"=>"2024-09-01"],
  ["route"=>"Chattogram → Sylhet","distance"=>330,"revenue"=>19000,"expense"=>13000,"type"=>"Round","date"=>"2024-10-05"],
  ["route"=>"Chattogram → Cox’s Bazar","distance"=>150,"revenue"=>11000,"expense"=>7000,"type"=>"Single","date"=>"2024-11-15"],
  ["route"=>"Dhaka → Feni","distance"=>170,"revenue"=>11000,"expense"=>7000,"type"=>"Round","date"=>"2024-12-10"],
  ["route"=>"Dhaka → Cumilla","distance"=>100,"revenue"=>9500,"expense"=>6000,"type"=>"Single","date"=>"2025-01-20"],
  ["route"=>"Dhaka → Rajshahi","distance"=>255,"revenue"=>14000,"expense"=>9000,"type"=>"Single","date"=>"2025-02-12"],
  ["route"=>"Dhaka → Khulna","distance"=>275,"revenue"=>15000,"expense"=>10000,"type"=>"Round","date"=>"2025-03-01"],
  ["route"=>"Dhaka → Cox’s Bazar","distance"=>390,"revenue"=>24000,"expense"=>15000,"type"=>"Single","date"=>"2025-05-05"],
  ["route"=>"Dhaka → Sylhet","distance"=>240,"revenue"=>16000,"expense"=>11000,"type"=>"Round","date"=>"2025-08-01"],
  ["route"=>"Dhaka → Cox’s Bazar","distance"=>390,"revenue"=>24500,"expense"=>15200,"type"=>"Single","date"=>"2025-08-02"],
  ["route"=>"Dhaka → Khulna","distance"=>275,"revenue"=>15100,"expense"=>10150,"type"=>"Round","date"=>"2025-08-23"],
  ["route"=>"Dhaka → Rajshahi","distance"=>255,"revenue"=>14200,"expense"=>9200,"type"=>"Single","date"=>"2025-08-31"],
  ["route"=>"Dhaka → Chattogram","distance"=>245,"revenue"=>17000,"expense"=>12000,"type"=>"Single","date"=>"2025-09-01"],
];

// ----- FILTERS -----
$range  = $_GET['range']  ?? '30';
$anchor = $_GET['anchor'] ?? null;
$rangeLabel = ($range === 'all') ? 'All Trips' : "Last $range Days";

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
$totalRevenue=$totalExpense=$totalProfit=0;
foreach ($filtered as $row) {
  $m = ($row['type']==='Round')?2:1;
  $rev=$row['revenue']*$m; $exp=$row['expense']*$m;
  $totalRevenue+=$rev; $totalExpense+=$exp; $totalProfit+=($rev-$exp);
}
$grandRevenue=$grandExpense=0;
foreach ($trips as $row) {
  $m = ($row['type']==='Round')?2:1;
  $grandRevenue+=$row['revenue']*$m; $grandExpense+=$row['expense']*$m;
}
$grandProfit=$grandRevenue-$grandExpense;
$anchorIsoForJs=$anchor?:date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>🚚 HaulPro – Truck 1 Calculations</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Flatpickr CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <!-- Your CSS -->
  <link rel="stylesheet" href="assets/calculationShow.css">
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
    <h2>🚚 Road Distance & Covered Van Rent (Approx.)</h2>
    <div class="subtitle"><?= htmlspecialchars($rangeLabel) ?></div>

    <div class="controls">
      <button class="btn <?= $range==10?'active':'' ?>" onclick="setRange(10)">Last 10 Days</button>
      <button class="btn <?= $range==30?'active':'' ?>" onclick="setRange(30)">Last 30 Days</button>
      <button class="btn <?= $range==180?'active':'' ?>" onclick="setRange(180)">Last 6 Months</button>
      <button class="btn <?= $range==365?'active':'' ?>" onclick="setRange(365)">Last 1 Year</button>
      <button class="btn <?= $range==='all'?'active':'' ?>" onclick="setRange('all')">All List</button>
    </div>

    <table>
      <thead>
        <tr>
          <th>Trip No</th><th>Date (BD)</th><th>Route</th><th>Trip Type</th>
          <th>Distance (km)</th><th>Rent / Revenue (BDT)</th><th>Expense (BDT)</th><th>Profit (BDT)</th><th>Receipt</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($filtered): $serial=1; $receiptCounters=[]; foreach($filtered as $t): 
          $m=($t['type']==='Round')?2:1;
          $distance=$t['distance']*$m; $revenue=$t['revenue']*$m; $expense=$t['expense']*$m; $profit=$revenue-$expense;
          $dateObj=new DateTime($t["date"]); $dateBD=$dateObj->format("d/m/Y");
          $year=$dateObj->format("y"); $month=$dateObj->format("n"); $ymKey=$dateObj->format("Y-m");
          if(!isset($receiptCounters[$ymKey])) $receiptCounters[$ymKey]=1; else $receiptCounters[$ymKey]++;
          $counter=str_pad($receiptCounters[$ymKey],3,'0',STR_PAD_LEFT);
          $receiptNo="$year-$counter-$month";
        ?>
        <tr>
          <td><?= $serial ?></td>
          <td><?= $dateBD ?></td>
          <td><?= htmlspecialchars($t["route"]) ?></td>
          <td><?= htmlspecialchars($t["type"]) ?></td>
          <td><?= number_format($distance) ?> km</td>
          <td>৳<?= number_format($revenue) ?></td>
          <td>৳<?= number_format($expense) ?></td>
          <td class="<?= $profit>=0?'profit-positive':'profit-negative' ?>">৳<?= number_format($profit) ?></td>
          <td><button class="receipt-btn" onclick="openReceipt({
            receipt:'<?= $receiptNo ?>', no:'<?= $serial ?>', date:'<?= $dateBD ?>',
            route:'<?= htmlspecialchars($t["route"],ENT_QUOTES) ?>', type:'<?= $t["type"] ?>',
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
        <?php if(($anchor===null)&&$range==='all'): ?>
        <tr class="totals"><td colspan="5">GRAND TOTAL (All Time)</td>
          <td>৳<?= number_format($grandRevenue) ?></td>
          <td>৳<?= number_format($grandExpense) ?></td>
          <td>৳<?= number_format($grandProfit) ?></td><td></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Config for JS -->
<script>window.HAULPRO={anchorIso:<?= json_encode($anchorIsoForJs) ?>};</script>

<!-- Flatpickr + Your JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="assets/calculationShow.js"></script>
</body>
</html>
