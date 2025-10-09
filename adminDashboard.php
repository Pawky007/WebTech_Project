<?php
/* ===== DEMO DATA (replace with live DB later) ===== */
$clients = [
  ["id"=>1,"name"=>"Rahim Uddin","phone"=>"01711111111","email"=>"rahim@example.com","address"=>"Dhaka","created_at"=>date('Y-m-d', strtotime('-1 day'))],
  ["id"=>2,"name"=>"Karim Ali","phone"=>"01822222222","email"=>"karim@example.com","address"=>"Chattogram","created_at"=>date('Y-m-d', strtotime('-8 days'))],
  ["id"=>3,"name"=>"Shila Akter","phone"=>"01933333333","email"=>"shila@example.com","address"=>"Khulna","created_at"=>date('Y-m-d', strtotime('-3 days'))],
  ["id"=>4,"name"=>"Hasan Mahmud","phone"=>"01644444444","email"=>"hasan@example.com","address"=>"Sylhet","created_at"=>date('Y-m-d', strtotime('-2 days'))],
  ["id"=>5,"name"=>"Nasrin Jahan","phone"=>"01555555555","email"=>"nasrin@example.com","address"=>"Rajshahi","created_at"=>date('Y-m-d', strtotime('-12 days'))],
  ["id"=>6,"name"=>"Siam Hossain","phone"=>"01366666666","email"=>"siam@example.com","address"=>"Barishal","created_at"=>date('Y-m-d', strtotime('-5 days'))],
  ["id"=>7,"name"=>"Tuhin Sarker","phone"=>"01777777777","email"=>"tuhin@example.com","address"=>"Cumilla","created_at"=>date('Y-m-d', strtotime('-7 days'))],
  ["id"=>8,"name"=>"Jui Rahman","phone"=>"01888888888","email"=>"jui@example.com","address"=>"Mymensingh","created_at"=>date('Y-m-d', strtotime('-15 days'))],
  ["id"=>9,"name"=>"Rafiul Islam","phone"=>"01999999999","email"=>"rafi@example.com","address"=>"Gazipur","created_at"=>date('Y-m-d', strtotime('-1 day'))],
  ["id"=>10,"name"=>"Meem Akter","phone"=>"01600000000","email"=>"meem@example.com","address"=>"Tangail","created_at"=>date('Y-m-d', strtotime('-6 days'))],
];
$payments = [
  ["client_id"=>1,"amount"=>3000,"date"=>date('Y-m-d', strtotime('-1 day'))],
  ["client_id"=>3,"amount"=>2500,"date"=>date('Y-m-d', strtotime('-3 days'))],
  ["client_id"=>4,"amount"=>1200,"date"=>date('Y-m-d', strtotime('-2 days'))],
  ["client_id"=>6,"amount"=>1800,"date"=>date('Y-m-d', strtotime('-5 days'))],
  ["client_id"=>7,"amount"=>900,"date"=>date('Y-m-d', strtotime('-7 days'))],
  ["client_id"=>9,"amount"=>2200,"date"=>date('Y-m-d', strtotime('-1 day'))],
  ["client_id"=>10,"amount"=>1500,"date"=>date('Y-m-d', strtotime('-6 days'))],
  ["client_id"=>2,"amount"=>2700,"date"=>date('Y-m-d', strtotime('-10 days'))],
  ["client_id"=>8,"amount"=>3100,"date"=>date('Y-m-d', strtotime('-16 days'))],
];

/* ===== KPI CALCS (7-day window) ===== */
$today = new DateTime('today'); $sevenAgo = new DateTime('-7 days');
$totalClients = count($clients);
$newLast7 = 0; foreach ($clients as $c) { $d=new DateTime($c['created_at']); if($d>=$sevenAgo&&$d<=$today)$newLast7++; }
$totalPayments7=0;$txCount7=0;$uniquePayers7=[];
foreach($payments as $p){$d=new DateTime($p['date']);if($d>=$sevenAgo&&$d<=$today){$totalPayments7+=$p['amount'];$txCount7++;$uniquePayers7[$p['client_id']]=true;}}
$uniquePayers7=count($uniquePayers7); $avgPerPayer7=$uniquePayers7?round($totalPayments7/$uniquePayers7,2):0; $avgPerTx7=$txCount7?round($totalPayments7/$txCount7,2):0;
$activeClientsDemo=6; $pendingVerifyDemo=2; $overdueInvoicesDemo=3;

/* ===== MAP: 10 client city coordinates (example) ===== */
$clientLocations = [
  ["name"=>"Dhaka","lat"=>23.8103,"lng"=>90.4125],
  ["name"=>"Chattogram","lat"=>22.3569,"lng"=>91.7832],
  ["name"=>"Khulna","lat"=>22.8456,"lng"=>89.5403],
  ["name"=>"Sylhet","lat"=>24.8949,"lng"=>91.8687],
  ["name"=>"Rajshahi","lat"=>24.3636,"lng"=>88.6241],
  ["name"=>"Barishal","lat"=>22.7010,"lng"=>90.3535],
  ["name"=>"Cumilla","lat"=>23.4607,"lng"=>91.1809],
  ["name"=>"Mymensingh","lat"=>24.7471,"lng"=>90.4203],
  ["name"=>"Gazipur","lat"=>23.9999,"lng"=>90.4203],
  ["name"=>"Tangail","lat"=>24.2513,"lng"=>89.9167],
];

/* ===== nav active ===== */
$current = basename($_SERVER['PHP_SELF']);
function navActive($f){global $current; return $current===$f?'active':'';}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard — HaulPro</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="dashboad_style.css">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --primary:#2563eb; --primary-hover:#1d4ed8;
  --bg:#f6f8fc; --surface:#ffffff; --border:#e6e8ef; --text:#0f172a;
  --muted:#64748b; --subtle:#334155;
  --ok:#16a34a; --warn:#f59e0b; --bad:#ef4444;
  --radius:14px; --shadow:0 10px 28px rgba(15,23,42,0.08);
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,"Segoe UI",system-ui,-apple-system,sans-serif;line-height:1.55}

/* Layout */
.layout{display:flex;min-height:100vh}
aside.sidebar{
  width:260px;background:var(--surface);border-right:1px solid var(--border);
  padding:22px;position:sticky;top:0;height:100vh;display:flex;flex-direction:column;gap:14px
}
.sidebar .brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px;color:var(--primary)}
.sidebar .menu{list-style:none;margin:6px 0 0;padding:0;display:flex;flex-direction:column;gap:6px}
.sidebar .menu a{
  display:flex;align-items:center;gap:10px;padding:10px 12px;text-decoration:none;
  color:var(--text);border-radius:10px;border:1px solid transparent;font-weight:600
}
.sidebar .menu a:hover{background:#f1f5f9;border-color:var(--border)}
.sidebar .menu a.active{background:var(--primary);color:#fff;box-shadow:0 6px 16px rgba(37,99,235,.25)}
.sidebar .help-card{
  margin-top:auto;padding:14px;border:1px dashed var(--border);border-radius:12px;background:#fafcff;text-align:center;color:var(--subtle)
}
.sidebar .help-card button{
  margin-top:10px;padding:8px 14px;border:none;border-radius:10px;background:var(--primary);color:#fff;font-weight:700;cursor:pointer
}
.sidebar .help-card button:hover{background:var(--primary-hover)}

main.content{flex:1;padding:28px;max-width:1700px;margin:0 auto}

/* Header */
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:12px}
.header h1{margin:0;font-size:28px;color:var(--primary);font-weight:800}
.header .sub{color:var(--muted);font-weight:600;font-size:.92rem}

/* KPI cards */
.cards{
  display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:22px
}
@media(max-width:1200px){.cards{grid-template-columns:repeat(2,1fr)}}
@media(max-width:780px){.cards{grid-template-columns:1fr}}

.card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);
  padding:16px;transition:transform .18s ease, box-shadow .18s ease
}
.card:hover{transform:translateY(-2px)}
.kpi{display:flex;align-items:center;justify-content:space-between}
.kpi .value{font-size:26px;font-weight:800;color:var(--subtle)}
.kpi .icon{
  width:42px;height:42px;border-radius:12px;background:#eef2ff;display:flex;align-items:center;justify-content:center;
  color:#3730a3;font-weight:800;font-size:18px
}
.card .label{margin-top:6px;color:var(--muted);font-weight:700;font-size:.9rem}
.card .meta{margin-top:6px;color:#97a0af;font-size:.8rem}

/* Map */
.map-wrap{margin-top:10px}
.map-card .map-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.map-card .map-title{font-weight:800;color:var(--primary)}
#clientMap{width:100%;height:540px;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow)}

/* Buttons */
.btn{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 16px;font-weight:700;cursor:pointer}
.btn:hover{background:var(--primary-hover)}
.btn.secondary{background:#f1f5f9;color:var(--subtle);border:1px solid var(--border)}
.btn.secondary:hover{background:#e2e8f0}

/* Footer spacer */
.footer-space{height:12px}
</style>
</head>
<body>
<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
        <img src="Image/Logo.png" alt="HaulPro Logo" width="160" />
        <h3>HaulPro</h3>
        <ul class="menu">
          <li><a href="adminDashboard.php"><img src="Image/dashboard.png" style="width:40px" alt=""/>Dashboard</a></li>
          <li><a href="adminShowClients.php"><img src="Image/magnifying-glass.png" alt="" style="width:40px" />Show All Clients</a></li>
          <li><a href="adminManageVehicles.php"><img src="Image/car1.png" alt="" style="width:40px" />Manage Vehicles</a></li>
          <li><a href="adminPayment.php"><img src="Image/wallet.png" alt="" style="width:40px" />Payments</a></li>
          <li><a href="adminSettings.php"><img src="Image/settings.png" alt="" style="width:40px" />Settings</a></li>
          <li><a href="login.php"><img src="Image/right-arrow.png" alt="" style="width:40px" />Log Out</a></li>
        </ul>
        <div class="help-card">
          <img src="https://cdn-icons-png.flaticon.com/512/4712/4712002.png" alt="Help"/>
          <p>Need Help?</p>
          <button>Contact Now</button>
        </div>
      </aside>

  <!-- Main -->
  <main class="content">
    <div class="header">
      <h1>Admin Dashboard</h1>
      <div class="sub">last 7 days (<?php echo $sevenAgo->format('d M'); ?> – <?php echo $today->format('d M, Y'); ?>)</div>
    </div>

    <!-- KPI Cards -->
    <section class="cards">
      <div class="card">
        <div class="kpi"><div class="value"><?php echo number_format($totalClients); ?></div><div class="icon">👥</div></div>
        <div class="label">Total Registered Clients</div>
      </div>
      <div class="card">
        <div class="kpi"><div class="value"><?php echo number_format($newLast7); ?></div><div class="icon">➕</div></div>
        <div class="label">New Clients (7 days)</div>
      </div>
      <div class="card">
        <div class="kpi"><div class="value"><?php echo number_format($activeClientsDemo); ?></div><div class="icon">⚡</div></div>
        <div class="label">Active Clients (demo)</div>
      </div>
      <div class="card">
        <div class="kpi"><div class="value"><?php echo number_format($pendingVerifyDemo); ?></div><div class="icon">🛡️</div></div>
        <div class="label">Pending Verifications</div>
      </div>
      <div class="card">
        <div class="kpi"><div class="value"><?php echo number_format($totalPayments7,2); ?> ৳</div><div class="icon">৳</div></div>
        <div class="label">Total Payments (7 days)</div>
        <div class="meta"><?php echo $txCount7; ?> tx • <?php echo $uniquePayers7; ?> payers</div>
      </div>
      <div class="card">
        <div class="kpi"><div class="value"><?php echo number_format($avgPerPayer7,2); ?> ৳</div><div class="icon">👤</div></div>
        <div class="label">Avg per Payer (7 days)</div>
      </div>
      <div class="card">
        <div class="kpi"><div class="value"><?php echo number_format($avgPerTx7,2); ?> ৳</div><div class="icon">🧾</div></div>
        <div class="label">Avg per Transaction (7 days)</div>
      </div>
      <div class="card">
        <div class="kpi"><div class="value"><?php echo number_format($overdueInvoicesDemo); ?></div><div class="icon">⚠️</div></div>
        <div class="label">Overdue Invoices (demo)</div>
      </div>
    </section>

    <!-- Client Locations Map -->
    <section class="card map-card">
      <div class="map-header">
        <div class="map-title">Client Operating Locations</div>
        <div>
          <button class="btn secondary" id="toggleTrafficBtn">Toggle Traffic</button>
        </div>
      </div>
      <div id="clientMap"></div>
    </section>

    <div class="footer-space"></div>
  </main>
</div>

<script>
/* Map + markers */
const CLIENT_POINTS = <?php echo json_encode($clientLocations, JSON_UNESCAPED_UNICODE); ?>;
let map, trafficLayer, trafficVisible = true;

function initClientMap(){
  const center = {lat:23.8, lng:90.41}; // Dhaka-ish center
  map = new google.maps.Map(document.getElementById('clientMap'), {
    zoom: 7, center, mapTypeControl:false, streetViewControl:false, fullscreenControl:true
  });

  trafficLayer = new google.maps.TrafficLayer();
  trafficLayer.setMap(map);

  // Markers
  const bounds = new google.maps.LatLngBounds();
  CLIENT_POINTS.forEach(c=>{
    const pos = {lat: c.lat, lng: c.lng};
    const m = new google.maps.Marker({
      position: pos, map, title: c.name,
      icon: "http://maps.google.com/mapfiles/ms/icons/red-dot.png"
    });
    const inf = new google.maps.InfoWindow({
      content: `<div style="font-family:Inter,sans-serif">
                  <div style="font-weight:800;color:#2563eb">${c.name}</div>
                  <div style="color:#64748b;font-size:.9rem">Active client location</div>
                </div>`
    });
    m.addListener('click', ()=> inf.open({anchor:m, map, shouldFocus:false}));
    bounds.extend(pos);
  });
  if (CLIENT_POINTS.length > 1) map.fitBounds(bounds);

  document.getElementById('toggleTrafficBtn')?.addEventListener('click', ()=>{
    trafficLayer.setMap(trafficVisible ? null : map);
    trafficVisible = !trafficVisible;
  });
}
</script>
<!-- Replace key if needed -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB9OwltS3fiIj-fEbmKAPcYH39lj-mOZmM&callback=initClientMap" async defer></script>
</body>
</html>
