<?php
require __DIR__.'/db.php';
$trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
if ($trip_id<=0) die('Missing trip_id');

$sql = "SELECT t.*, tr.reg_number, tr.truck_type, d.name AS driver_name, d.phone AS driver_phone
        FROM trips t
        JOIN trucks tr ON tr.id=t.truck_id
        JOIN drivers d ON d.id=t.driver_id
        WHERE t.id=?";
$st = $mysqli->prepare($sql);
$st->bind_param('i',$trip_id);
$st->execute();
$r = $st->get_result()->fetch_assoc();
if(!$r) die('Trip not found');

$expense = $r['driver_fee']+$r['fuel_cost']+$r['toll_cost']+$r['labor_cost']+$r['gate_cost']+$r['other_cost'];
$profit  = $r['revenue_bdt'] - $expense;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?= htmlspecialchars($r['receipt_no'] ?? $r['id']) ?></title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#111}
  .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
  .brand{font-weight:800;font-size:22px}
  .meta{color:#555}
  .card{border:1px solid #e3e6eb;border-radius:10px;padding:16px;margin-top:10px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:10px;border:1px solid #e3e6eb;text-align:left}
  th{background:#f3f6fb}
  .tot{font-weight:800}
  .actions{margin-top:16px}
  .btn{padding:8px 12px;border:1px solid #ddd;border-radius:8px;background:#0d6efd;color:#fff;cursor:pointer}
</style>
</head>
<body>
  <div class="head">
    <div class="brand">HaulPro – <?= htmlspecialchars($r['reg_number']) ?></div>
    <div class="meta">Receipt #: <?= htmlspecialchars($r['receipt_no'] ?? $r['id']) ?></div>
  </div>

  <div class="card">
    <h3>Truck</h3>
    <table>
      <tr><th>Reg No</th><td><?= htmlspecialchars($r['reg_number']) ?></td></tr>
      <tr><th>Type</th><td><?= htmlspecialchars($r['truck_type']) ?></td></tr>
      <tr><th>Driver</th><td><?= htmlspecialchars($r['driver_name']) ?> (<?= htmlspecialchars($r['driver_phone']) ?>)</td></tr>
    </table>
  </div>

  <div class="card">
    <h3>Trip</h3>
    <table>
      <tr><th>Date</th><td><?= htmlspecialchars(date('d/m/Y',strtotime($r['trip_date']))) ?></td></tr>
      <tr><th>Route</th><td><?= htmlspecialchars($r['route_from'].' → '.$r['route_to']) ?></td></tr>
      <tr><th>Trip Type</th><td><?= htmlspecialchars($r['trip_type']) ?></td></tr>
      <tr><th>Distance</th><td><?= number_format($r['distance_km']) ?> km</td></tr>
      <tr><th>Revenue</th><td>৳<?= number_format($r['revenue_bdt']) ?></td></tr>
    </table>
  </div>

  <div class="card">
    <h3>Cost Breakdown</h3>
    <table>
      <tr><th>Driver Fee</th><td>৳<?= number_format($r['driver_fee']) ?></td></tr>
      <tr><th>Fuel Cost</th><td>৳<?= number_format($r['fuel_cost']) ?></td></tr>
      <tr><th>Toll Cost</th><td>৳<?= number_format($r['toll_cost']) ?></td></tr>
      <tr><th>Labor Cost</th><td>৳<?= number_format($r['labor_cost']) ?></td></tr>
      <tr><th>Gate Cost</th><td>৳<?= number_format($r['gate_cost']) ?></td></tr>
      <tr><th>Other Cost</th><td>৳<?= number_format($r['other_cost']) ?></td></tr>
      <tr class="tot"><th>Total Expense</th><td>৳<?= number_format($expense) ?></td></tr>
      <tr class="tot"><th>Profit</th><td>৳<?= number_format($profit) ?></td></tr>
    </table>
  </div>

  <div class="actions">
    <button class="btn" onclick="window.print()">Print</button>
  </div>
</body>
</html>
