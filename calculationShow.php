<?php
require __DIR__.'/db.php';

$truck_id = isset($_GET['truck_id']) ? (int)$_GET['truck_id'] : 0;
if ($truck_id<=0) die('Missing truck_id');

// ✅ Select from lorry_owners instead of trucks
$ts = $mysqli->prepare("SELECT vehicle_no, truck_type FROM lorry_owners WHERE id=?");
$ts->bind_param('i',$truck_id);
$ts->execute();
$truck = $ts->get_result()->fetch_assoc();

// If no truck found
if (!$truck) {
    die('<div style="padding:20px; font-family:sans-serif; color:red;">⚠ Lorry not found</div>');
}

$today = date('Y-m-d');
$default_from = date('Y-m-d', strtotime('-30 days'));

$range = $_GET['range'] ?? '30';
if ($range === 'all') {
  $from = '1970-01-01';
  $to   = $today;
} else {
  $from = $_GET['from'] ?? $default_from;
  $to   = $_GET['to']   ?? $today;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from = $default_from;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))   $to   = $today;

// ✅ Trips table query stays same
$sql = "SELECT t.*, d.name AS driver_name, d.phone AS driver_phone
        FROM trips t
        JOIN drivers d ON d.id = t.driver_id
        WHERE t.truck_id = ? AND t.trip_date BETWEEN ? AND ?
        ORDER BY t.trip_date DESC";
$st = $mysqli->prepare($sql);
$st->bind_param('iss',$truck_id,$from,$to);
$st->execute();
$res = $st->get_result();

$totalRevenue=$totalExpense=$totalProfit=0;
$rows=[];
while($r=$res->fetch_assoc()){
  $expense = $r['driver_fee']+$r['fuel_cost']+$r['toll_cost']+$r['labor_cost']+$r['gate_cost']+$r['other_cost'];
  $profit  = $r['revenue_bdt'] - $expense;
  $r['expense']=$expense;
  $r['profit']=$profit;
  $rows[]=$r;
  $totalRevenue += $r['revenue_bdt'];
  $totalExpense += $expense;
  $totalProfit  += $profit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Trips — <?= htmlspecialchars($truck['vehicle_no']) ?></title>
<link rel="stylesheet" href="dashboad_style.css">
<style>
:root {
  --bg: #f9fafb; --surface: #ffffff; --text: #1f2937; --muted: #6b7280;
  --border: #e5e7eb; --primary: #2563eb; --primary-hover: #1d4ed8;
  --danger: #ef4444; --danger-hover: #dc2626; --secondary: #f3f4f6;
  --radius: 10px; --shadow: 0 2px 6px rgba(0,0,0,0.08);
  --pos:#166534; --neg:#991b1b;
}
body{font-family:'Segoe UI',Tahoma,sans-serif; background:var(--bg);margin:0;color:var(--text);}
.container { display:flex; }
main { flex:1; padding:24px; }

/* FIX: make shell full width */
.shell{width:100%; max-width:100%; margin:0; padding:0 16px;}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
h2,h3{color:var(--primary);}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 16px;border-radius:50px;border:none;font-size:16px;font-weight:500;cursor:pointer;transition:background 0.2s,transform 0.1s;text-decoration:none;}
.btn:hover{transform:translateY(-1px);}
.btn.link{background:transparent;border:1px solid var(--border);color:var(--text);}
.btn.primary{background:var(--primary);color:#fff;}
.btn.primary:hover{background:var(--primary-hover);}
.btn.danger{background:var(--danger);color:#fff;}
.btn.danger:hover{background:var(--danger-hover);}
.btn.secondary{background:#eef2ff;color:var(--primary);}

/* FIX: card/table full width */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:24px;margin-bottom:24px;width:100%;}
.filters{display:flex;gap:12px;margin:10px 0;align-items:center;flex-wrap:wrap}
table{width:100%;border-collapse:collapse;font-size:18px;border-radius:var(--radius);overflow:hidden;}
th,td{padding:12px 14px;border-bottom:1px solid var(--border);text-align:center;}
th{background:var(--secondary);font-weight:600;}
tr:nth-child(even) td{background:#fdfdfd;}
tr:hover td{background:#f1f5ff;}
.totals{background:#f3f4f6;font-weight:700;}
.profit-positive{color:var(--pos);font-weight:700}
.profit-negative{color:var(--neg);font-weight:700}
.ranges .btn{background:#eef2ff;color:var(--primary);}
</style>
<script>
function setRange(days){
  const url = new URL(window.location.href);
  url.searchParams.set('range', days);
  if(days==='all'){ url.searchParams.delete('from'); url.searchParams.delete('to'); }
  window.location.href = url.toString();
}
function openReceipt(data){
  const w = window.open('', '_blank', 'width=720,height=900');
  const html = `
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt ${data.receipt}</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#111}
  .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
  .brand{font-weight:800;font-size:20px}
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
    <div class="brand">HaulPro – ${data.truck}</div>
    <div class="meta">Receipt #: ${data.receipt}</div>
  </div>
  <div class="card">
    <h3>Truck</h3>
    <table>
      <tr><th>Vehicle No</th><td>${data.truck}</td></tr>
      <tr><th>Driver</th><td>${data.driver_name} (${data.driver_phone})</td></tr>
    </table>
  </div>
  <div class="card">
    <h3>Trip</h3>
    <table>
      <tr><th>Date</th><td>${data.date}</td></tr>
      <tr><th>Route</th><td>${data.route}</td></tr>
      <tr><th>Trip Type</th><td>${data.type}</td></tr>
      <tr><th>Distance</th><td>${data.distance} km</td></tr>
      <tr><th>Revenue</th><td>৳${data.revenue}</td></tr>
    </table>
  </div>
  <div class="card">
    <h3>Cost Breakdown</h3>
    <table>
      <tr><th>Driver Fee</th><td>৳${data.driver_fee}</td></tr>
      <tr><th>Fuel Cost</th><td>৳${data.fuel_cost}</td></tr>
      <tr><th>Toll Cost</th><td>৳${data.toll_cost}</td></tr>
      <tr><th>Labor Cost</th><td>৳${data.labor_cost}</td></tr>
      <tr><th>Gate Cost</th><td>৳${data.gate_cost}</td></tr>
      <tr><th>Other Cost</th><td>৳${data.other_cost}</td></tr>
      <tr class="tot"><th>Total Expense</th><td>৳${data.expense}</td></tr>
      <tr class="tot"><th>Profit</th><td>৳${data.profit}</td></tr>
    </table>
  </div>
  <div class="actions"><button class="btn" onclick="window.print()">Print</button></div>
</body>
</html>`;
  w.document.open(); w.document.write(html); w.document.close();
}
</script>
</head>
<body>
<div class="container">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <img src="Image/Logo.png" alt="HaulPro Logo" width="160"/>
    <h3>HaulPro</h3>
    <ul class="menu">
      <li><a href="dashboard.php"><img src="Image/dashboard.png" alt=""/>Dashboard</a></li>
      <li class="has-submenu">
        <a href="#"><img src="Image/chart.png" alt=""/>Analysis</a>
        <ul class="submenu">
          <li><a href="delivery_performance.php"><img src="Image/continuous-improvement.png" alt=""/>Delivery Performance</a></li>
          <li><a href="revenue_analysis.html"><img src="Image/profit-margin.png" alt=""/>Revenue Analysis</a></li>
          <li><a href="fleet_efficiency.html"><img src="Image/delivery-truck.png" alt=""/>Fleet Efficiency</a></li>
        </ul>
      </li>
      <li><a href="calculationInput.php?truck_id=<?= (int)$truck_id ?>"><img src="Image/plus.png" alt=""/>Add Trips</a></li>
      <li><a href="Payment_customer.php"><img src="Image/wallet.png" alt=""/>Payment Method</a></li>
      <li><a href="Lorry_owner.php"><img src="Image/businessman.png" alt=""/>Lorry Owner List</a></li>
      <li><a href="lorrylist.php"><img src="Image/truck.png" alt=""/>Lorry List</a></li>
      <li><a href="Customer_settings.php"><img src="Image/settings.png" alt=""/>Settings</a></li>
      <li><a href="faq.html"><img src="Image/faq.png" alt=""/>FAQ</a></li>
      <li><a href="ai_chatbot.php"><img src="Image/robot.png" alt="" style="width:40px" />AI Chat Bot</a></li>
      <li><a href="login.php"><img src="Image/right-arrow.png" alt="" style="width:40px" />Log Out</a></li>
    </ul>
    <div class="help-card">
      <img src="https://cdn-icons-png.flaticon.com/512/4712/4712002.png" alt="Help"/>
      <p>Need Help?</p>
      <button>Contact Now</button>
    </div>
  </aside>

  <!-- Main -->
  <main>
    <div class="shell">
      <div class="topbar">
        <div><strong><?= htmlspecialchars($truck['vehicle_no']) ?></strong> — <?= htmlspecialchars($truck['truck_type']) ?></div>
        <a class="btn primary" href="calculationInput.php?truck_id=<?= (int)$truck_id ?>">+ New Trip</a>
      </div>

      <div class="card">
        <div class="filters">
          <form method="get" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="truck_id" value="<?= (int)$truck_id ?>">
            <label>From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
            <label>To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
            <button class="btn primary" type="submit">Filter</button>
            <a class="btn secondary" href="calculationShow.php?truck_id=<?= (int)$truck_id ?>">Reset</a>
          </form>
          <div class="ranges" style="margin-left:auto;display:flex;gap:6px">
            <button class="btn secondary" type="button" onclick="setRange(10)">10 days</button>
            <button class="btn secondary" type="button" onclick="setRange(30)">1 month</button>
            <button class="btn secondary" type="button" onclick="setRange(180)">6 months</button>
            <button class="btn secondary" type="button" onclick="setRange(365)">1 year</button>
            <button class="btn secondary" type="button" onclick="setRange('all')">All</button>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Route</th>
              <th>Type</th>
              <th>Distance</th>
              <th>Revenue</th>
              <th>Expense</th>
              <th>Profit</th>
              <th>Receipt</th>
            </tr>
          </thead>
          <tbody>
            <?php if($rows): foreach($rows as $r):
              $dateBD = date('d/m/Y', strtotime($r['trip_date']));
              $route  = $r['route_from'].' → '.$r['route_to'];
              $distance = number_format($r['distance_km']);
              $revenue  = number_format($r['revenue_bdt']);
              $expense  = number_format($r['expense']);
              $profit   = number_format($r['profit']);
              $driver_fee = number_format($r['driver_fee']);
              $fuel_cost  = number_format($r['fuel_cost']);
              $toll_cost  = number_format($r['toll_cost']);
              $labor_cost = number_format($r['labor_cost']);
              $gate_cost  = number_format($r['gate_cost']);
              $other_cost = number_format($r['other_cost']);
              $receipt = $r['receipt_no'] ?: ('#'.$r['id']);
            ?>
              <tr>
                <td><?= htmlspecialchars($dateBD) ?></td>
                <td><?= htmlspecialchars($route) ?></td>
                <td><?= htmlspecialchars($r['trip_type']) ?></td>
                <td><?= htmlspecialchars($distance) ?> km</td>
                <td>৳<?= htmlspecialchars($revenue) ?></td>
                <td>৳<?= htmlspecialchars($expense) ?></td>
                <td class="<?= $r['profit']>=0?'profit-positive':'profit-negative' ?>">৳<?= htmlspecialchars($profit) ?></td>
                <td>
                  <button
                    class="btn secondary"
                    onclick='openReceipt({
                      receipt: <?= json_encode($receipt) ?>,
                      truck:   <?= json_encode($truck["vehicle_no"]) ?>,
                      date:    <?= json_encode($dateBD) ?>,
                      route:   <?= json_encode($route) ?>,
                      type:    <?= json_encode($r["trip_type"]) ?>,
                      distance: <?= json_encode($distance) ?>,
                      revenue:  <?= json_encode($revenue) ?>,
                      driver_fee: <?= json_encode($driver_fee) ?>,
                      fuel_cost:  <?= json_encode($fuel_cost) ?>,
                      toll_cost:  <?= json_encode($toll_cost) ?>,
                      labor_cost: <?= json_encode($labor_cost) ?>,
                      gate_cost:  <?= json_encode($gate_cost) ?>,
                      other_cost: <?= json_encode($other_cost) ?>,
                      expense: <?= json_encode($expense) ?>,
                      profit:  <?= json_encode($profit) ?>,
                      driver_name:  <?= json_encode($r["driver_name"]) ?>,
                      driver_phone: <?= json_encode($r["driver_phone"]) ?>
                    })'>
                    Receipt
                  </button>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="8">No trips in this range.</td></tr>
            <?php endif; ?>
            <tr class="totals">
              <td colspan="4">TOTAL (<?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?>)</td>
              <td>৳<?= number_format($totalRevenue) ?></td>
              <td>৳<?= number_format($totalExpense) ?></td>
              <td>৳<?= number_format($totalProfit) ?></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
