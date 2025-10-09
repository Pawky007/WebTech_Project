<?php
require __DIR__.'/db.php';
require __DIR__.'/auth.php';
require_login();
$user_id = (int) current_user_id();

/* ---------------------------
   Locations + distance map
----------------------------*/
$locations = ["Dhaka","Chattogram","Cumilla","Sylhet","Rajshahi","Khulna","Barisal","Rangpur"];

function calculateDistance($from, $to) {
  $dist = [
    "Dhaka" => ["Chattogram"=>253,"Cumilla"=>109,"Sylhet"=>241,"Rajshahi"=>256,"Khulna"=>271,"Barisal"=>169,"Rangpur"=>330],
    "Chattogram" => ["Dhaka"=>253,"Cumilla"=>152,"Sylhet"=>359,"Rajshahi"=>560,"Khulna"=>464,"Barisal"=>380,"Rangpur"=>640],
    "Cumilla" => ["Dhaka"=>109,"Chattogram"=>152,"Sylhet"=>270,"Rajshahi"=>365,"Khulna"=>362,"Barisal"=>260,"Rangpur"=>450],
    "Sylhet" => ["Dhaka"=>241,"Chattogram"=>359,"Cumilla"=>270,"Rajshahi"=>482,"Khulna"=>500,"Barisal"=>410,"Rangpur"=>450],
    "Rajshahi" => ["Dhaka"=>256,"Chattogram"=>560,"Cumilla"=>365,"Sylhet"=>482,"Khulna"=>264,"Barisal"=>340,"Rangpur"=>185],
    "Khulna" => ["Dhaka"=>271,"Chattogram"=>464,"Cumilla"=>362,"Sylhet"=>500,"Rajshahi"=>264,"Barisal"=>160,"Rangpur"=>460],
    "Barisal" => ["Dhaka"=>169,"Chattogram"=>380,"Cumilla"=>260,"Sylhet"=>410,"Rajshahi"=>340,"Khulna"=>160,"Rangpur"=>480],
    "Rangpur" => ["Dhaka"=>330,"Chattogram"=>640,"Cumilla"=>450,"Sylhet"=>450,"Rajshahi"=>185,"Khulna"=>460,"Barisal"=>480]
  ];
  return $dist[$from][$to] ?? 0;
}

/* ---------------------------
   Read ONLY THIS USER'S trucks for dropdown
----------------------------*/
$trucks = [];
$truckMap = [];
if ($stmt = $mysqli->prepare("
  SELECT l.id, l.vehicle_no, l.truck_type, l.driver_id,
         d.name AS driver_name, d.phone AS driver_phone
  FROM lorry_owners l
  LEFT JOIN drivers d ON d.id = l.driver_id
  WHERE l.user_id = ?
  ORDER BY l.vehicle_no
")) {
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $trucks[] = $row;
    $truckMap[(int)$row['id']] = $row;
  }
  $stmt->close();
}

/* ----------------------------------------------------
   Entry modes:
   - From "New Trip" button:   calculationInput.php?truck_id=123  (prefill & after save go back to calculationShow.php)
   - From sidebar "Add Trips": calculationInput.php                (choose truck from dropdown & after save go dashboard)
   Note: prefill truck is only honored if it belongs to this user.
-----------------------------------------------------*/
$prefill_truck_id = (int)($_GET['truck_id'] ?? 0);
if ($prefill_truck_id && !isset($truckMap[$prefill_truck_id])) {
  // If someone tries to prefill with a truck not owned by this user, ignore it.
  $prefill_truck_id = 0;
}

$errorMessage = '';
$selected_truck_id = isset($_POST['truck_id'])
  ? (int)$_POST['truck_id']
  : ($prefill_truck_id ?: 0); // preselect if coming from "New Trip"

$driverName   = '';
$driverNumber = '';
if ($selected_truck_id && isset($truckMap[$selected_truck_id])) {
  $driverName   = $truckMap[$selected_truck_id]['driver_name']  ?? '';
  $driverNumber = $truckMap[$selected_truck_id]['driver_phone'] ?? '';
}

/* ---------------------------
   Handle submit
----------------------------*/
if ($_SERVER['REQUEST_METHOD']==='POST') {
  // Allow overrides from form:
  $driverName   = trim($_POST['driverName']   ?? $driverName);
  $driverNumber = trim($_POST['driverNumber'] ?? $driverNumber);

  $tripDate  = $_POST['tripDate']  ?? date('Y-m-d');
  $tripType  = $_POST['tripType']  ?? 'Single';
  $routeFrom = $_POST['routeFrom'] ?? '';
  $routeTo   = $_POST['routeTo']   ?? '';
  $revenue   = (float)($_POST['revenue'] ?? 0);

  $driverFee = (float)($_POST['driverFee'] ?? 0);
  $fuelCost  = (float)($_POST['fuelCost']  ?? 0);
  $tollCost  = (float)($_POST['tollCost']  ?? 0);
  $laborCost = (float)($_POST['laborCost'] ?? 0);
  $gateCost  = (float)($_POST['gateCost']  ?? 0);
  $miscCost  = (float)($_POST['miscCost']  ?? 0);

  // Validation (enforce truck belongs to current user)
  if ($selected_truck_id<=0 || !isset($truckMap[$selected_truck_id])) {
    $errorMessage = 'Please select a valid Truck (from your own list).';
  } elseif ($revenue<0 || $driverFee<0 || $fuelCost<0 || $tollCost<0 || $laborCost<0 || $gateCost<0 || $miscCost<0) {
    $errorMessage = 'Values cannot be negative.';
  } elseif ($routeFrom==='' || $routeTo==='' || $routeFrom===$routeTo) {
    $errorMessage = 'Choose valid From and To.';
  } elseif ($driverNumber==='') {
    $errorMessage = 'Driver number is required.';
  } else {
    $distance = calculateDistance($routeFrom,$routeTo);

    $mysqli->begin_transaction();
    try {
      // upsert driver by phone
      $q = $mysqli->prepare("SELECT id FROM drivers WHERE phone=?");
      $q->bind_param('s',$driverNumber);
      $q->execute();
      $row = $q->get_result()->fetch_assoc();
      $driver_id = $row['id'] ?? null;

      if ($driver_id) {
        $upd = $mysqli->prepare("UPDATE drivers SET name=? WHERE id=?");
        $upd->bind_param('si',$driverName,$driver_id);
        $upd->execute();
      } else {
        $ins = $mysqli->prepare("INSERT INTO drivers(name,phone) VALUES(?,?)");
        $ins->bind_param('ss',$driverName,$driverNumber);
        $ins->execute();
        $driver_id = $ins->insert_id;
      }

      // attach driver to the truck (safe: it's the user's truck already)
      $att = $mysqli->prepare("UPDATE lorry_owners SET driver_id=? WHERE id=? AND user_id=?");
      $att->bind_param('iii',$driver_id,$selected_truck_id,$user_id);
      $att->execute();

      // monthly sequence per truck for receipt no
      $seqS = $mysqli->prepare("SELECT COUNT(*)+1 FROM trips
                                WHERE truck_id=? AND YEAR(trip_date)=YEAR(?) AND MONTH(trip_date)=MONTH(?)");
      $seqS->bind_param('iss',$selected_truck_id,$tripDate,$tripDate);
      $seqS->execute();
      $seq = (int)$seqS->get_result()->fetch_row()[0];
      $receipt_no = sprintf('%02d-%03d-%d', (int)date('y',strtotime($tripDate)), $seq, (int)date('n',strtotime($tripDate)));

      // insert trip
      // NOTE: If your trips table has a user_id column, you can extend this insert to store it:
      //   ... , user_id) VALUES ( ... , ?)
      // and bind $user_id accordingly.
      $insT = $mysqli->prepare("INSERT INTO trips
        (truck_id, trip_date, route_from, route_to, trip_type, distance_km, revenue_bdt,
         driver_id, driver_fee, fuel_cost, toll_cost, labor_cost, gate_cost, other_cost, receipt_no)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      // i s s s s d d i d d d d d d s
      $insT->bind_param("issssddidddddds",
        $selected_truck_id, $tripDate, $routeFrom, $routeTo, $tripType,
        $distance, $revenue, $driver_id, $driverFee, $fuelCost, $tollCost,
        $laborCost, $gateCost, $miscCost, $receipt_no
      );
      $insT->execute();

      $mysqli->commit();

      // If we came from "New Trip" with ?truck_id=..., go back to that truck's list.
      if ($prefill_truck_id > 0) {
        header('Location: calculationShow.php?truck_id='.(int)$selected_truck_id);
      } else {
        // Otherwise, from sidebar Add Trips → dashboard
        header('Location: dashboard.php');
      }
      exit;
    } catch(Throwable $e) {
      $mysqli->rollback();
      $errorMessage = 'Save failed: '.$e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Trip</title>
  <link rel="stylesheet" href="dashboad_style.css">
  <style>
    :root {
      --primary:#2563eb; --primary-hover:#1d4ed8; --bg:#f3f6fb; --surface:#fff;
      --border:#e5e7eb; --text:#111827; --muted:#6b7280; --radius:12px;
      --shadow:0 6px 16px rgba(0,0,0,0.08); --success:#16a34a; --danger:#dc2626;
    }
    body { margin:0; font-family:'Segoe UI',sans-serif; background:var(--bg); color:var(--text); }
    .container { display:flex; } main { flex:1; padding:32px; }

    .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
    .header h1 { margin:0; font-size:24px; font-weight:700; color:var(--primary); }
    .header .meta { font-size:14px; color:var(--muted); background:#eef2ff; padding:6px 12px; border-radius:var(--radius); }

    .card { background:var(--surface); border-radius:var(--radius); border:1px solid var(--border); box-shadow:var(--shadow); padding:28px; animation: fadeIn .4s ease; }
    @keyframes fadeIn { from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }

    form label { display:block; font-weight:600; margin-bottom:6px; font-size:14px; color:var(--muted); }
    form input, form select { width:100%; padding:12px 14px; font-size:14px; border:1px solid var(--border); border-radius:var(--radius); margin-bottom:18px; transition:border .2s, box-shadow .2s, transform .1s; background:#fff; }
    form input:focus, form select:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,0.15); transform:scale(1.01); }

    .row2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    @media(max-width:800px){ .row2{grid-template-columns:1fr;} }

    .metrics { background:#f9fafb; border:1px solid var(--border); border-radius:var(--radius); padding:14px 18px; font-weight:700; font-size:15px; margin-bottom:16px; transition:.3s; }
    #profit.positive { background:#ecfdf5; color:var(--success); border-color:#bbf7d0; }
    #profit.negative { background:#fef2f2; color:var(--danger); border-color:#fecaca; }

    .btn { background:var(--primary); color:#fff; padding:12px 22px; border:none; border-radius:var(--radius); font-size:15px; font-weight:600; cursor:pointer; transition:.25s; box-shadow:0 3px 6px rgba(0,0,0,0.1); }
    .btn:hover { background:var(--primary-hover); transform:translateY(-1px);}
    .btn:active { transform:translateY(1px); }

    .error { background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:12px; border-radius:var(--radius); margin-bottom:16px; font-weight:500; }
    .hint { color:var(--muted); font-size:12px; margin-top:-12px; margin-bottom:18px; }
  </style>
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
          <li><a href="Revenue_analysis.php"><img src="Image/profit-margin.png" alt=""/>Revenue Analysis</a></li>
          <li><a href="fleet_analysis.php"><img src="Image/delivery-truck.png" alt=""/>Fleet Efficiency</a></li>
        </ul>
      </li>
      <li><a href="calculationInput.php"><img src="Image/plus.png" alt=""/>Add Trips</a></li>
      <li><a href="Payment_Customer.php"><img src="Image/wallet.png" alt=""/>Payment Method</a></li>
      <li><a href="Lorry_owner.php"><img src="Image/businessman.png" alt=""/>Lorry Owner List</a></li>
      <li><a href="lorrylist.php"><img src="Image/truck.png" alt=""/>Lorry List</a></li>
      <li><a href="Admin_settings.php"><img src="Image/settings.png" alt=""/>Settings</a></li>
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
    <div class="header">
      <h1>➕ Add Trip</h1>
      <div class="meta" id="truckMeta">
        <?= $selected_truck_id && isset($truckMap[$selected_truck_id])
            ? 'Truck: '.htmlspecialchars($truckMap[$selected_truck_id]['vehicle_no']).' — '.htmlspecialchars($truckMap[$selected_truck_id]['truck_type'])
            : 'Select a truck to start' ?>
      </div>
    </div>

    <div class="card">
      <?php if ($errorMessage): ?><div class="error"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

      <form method="POST" action="calculationInput.php" id="tripForm">
        <!-- Truck selection (only this user's trucks) -->
        <div class="row2">
          <div>
            <label>Truck</label>
            <select name="truck_id" id="truck_id" required>
              <option value="">— Select Truck —</option>
              <?php foreach($trucks as $t): ?>
                <option
                  value="<?= (int)$t['id'] ?>"
                  data-type="<?= htmlspecialchars($t['truck_type']) ?>"
                  data-driver-name="<?= htmlspecialchars($t['driver_name'] ?? '') ?>"
                  data-driver-phone="<?= htmlspecialchars($t['driver_phone'] ?? '') ?>"
                  <?= $selected_truck_id===(int)$t['id'] ? 'selected' : '' ?>
                >
                  <?= htmlspecialchars($t['vehicle_no']) ?> (<?= htmlspecialchars($t['truck_type']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Only your lorries are listed here.</div>
          </div>
          <div>
            <label>Trip Type</label>
            <select name="tripType">
              <option value="Single" <?= (($_POST['tripType']??'Single')==='Single')?'selected':''; ?>>Single</option>
              <option value="Round"  <?= (($_POST['tripType']??'')==='Round')?'selected':''; ?>>Round</option>
            </select>
          </div>
        </div>

        <div class="row2">
          <div>
            <label>Trip Date</label>
            <input type="date" name="tripDate" value="<?= htmlspecialchars($_POST['tripDate'] ?? date('Y-m-d')) ?>">
          </div>
          <div>
            <label>Driver Number</label>
            <input type="text" id="driverNumber" name="driverNumber" value="<?= htmlspecialchars($driverNumber) ?>">
          </div>
        </div>

        <div class="row2">
          <div>
            <label>Driver Name</label>
            <input type="text" id="driverName" name="driverName" value="<?= htmlspecialchars($driverName) ?>">
          </div>
          <div>
            <label>From</label>
            <select id="routeFrom" name="routeFrom" onchange="updateDistance()">
              <option value="">Select From</option>
              <?php foreach($locations as $l): ?>
                <option <?= (($_POST['routeFrom']??'')===$l)?'selected':''; ?>><?= htmlspecialchars($l) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row2">
          <div>
            <label>To</label>
            <select id="routeTo" name="routeTo" onchange="updateDistance()">
              <option value="">Select To</option>
              <?php foreach($locations as $l): ?>
                <option <?= (($_POST['routeTo']??'')===$l)?'selected':''; ?>><?= htmlspecialchars($l) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="metrics" id="distanceDisplay" style="align-self:end">Select both locations to see distance</div>
        </div>

        <div class="row2">
          <div>
            <label>Revenue (BDT)</label>
            <input type="number" id="revenue" name="revenue" min="0" step="0.01" value="<?= htmlspecialchars($_POST['revenue'] ?? '') ?>">
          </div>
          <div>
            <label>Driver Fee (BDT)</label>
            <input type="number" id="driverFee" name="driverFee" min="0" step="0.01" value="<?= htmlspecialchars($_POST['driverFee'] ?? '') ?>">
          </div>
        </div>

        <div class="row2">
          <div>
            <label>Fuel Cost (BDT)</label>
            <input type="number" id="fuelCost" name="fuelCost" min="0" step="0.01" value="<?= htmlspecialchars($_POST['fuelCost'] ?? '') ?>">
          </div>
          <div>
            <label>Toll Cost (BDT)</label>
            <input type="number" id="tollCost" name="tollCost" min="0" step="0.01" value="<?= htmlspecialchars($_POST['tollCost'] ?? '') ?>">
          </div>
        </div>

        <div class="row2">
          <div>
            <label>Labor Cost (BDT)</label>
            <input type="number" id="laborCost" name="laborCost" min="0" step="0.01" value="<?= htmlspecialchars($_POST['laborCost'] ?? '') ?>">
          </div>
          <div>
            <label>Gate Cost (BDT)</label>
            <input type="number" id="gateCost" name="gateCost" min="0" step="0.01" value="<?= htmlspecialchars($_POST['gateCost'] ?? '') ?>">
          </div>
        </div>

        <div class="row2">
          <div>
            <label>Other Cost (BDT)</label>
            <input type="number" id="miscCost" name="miscCost" min="0" step="0.01" value="<?= htmlspecialchars($_POST['miscCost'] ?? '') ?>">
          </div>
          <div></div>
        </div>

        <div class="metrics" id="totalCost">Total Cost: 0.00</div>
        <div class="metrics" id="profit">Profit: 0.00</div>

        <button class="btn" type="submit">💾 Save Trip</button>
      </form>
    </div>
  </main>
</div>

<script>
function updateDistance(){
  var f=document.getElementById('routeFrom').value;
  var t=document.getElementById('routeTo').value;
  var el=document.getElementById('distanceDisplay');
  const map={
    "Dhaka":{"Chattogram":253,"Cumilla":109,"Sylhet":241,"Rajshahi":256,"Khulna":271,"Barisal":169,"Rangpur":330},
    "Chattogram":{"Dhaka":253,"Cumilla":152,"Sylhet":359,"Rajshahi":560,"Khulna":464,"Barisal":380,"Rangpur":640},
    "Cumilla":{"Dhaka":109,"Chattogram":152,"Sylhet":270,"Rajshahi":365,"Khulna":362,"Barisal":260,"Rangpur":450},
    "Sylhet":{"Dhaka":241,"Chattogram":359,"Cumilla":270,"Rajshahi":482,"Khulna":500,"Barisal":410,"Rangpur":450},
    "Rajshahi":{"Dhaka":256,"Chattogram":560,"Cumilla":365,"Sylhet":482,"Khulna":264,"Barisal":340,"Rangpur":185},
    "Khulna":{"Dhaka":271,"Chattogram":464,"Cumilla":362,"Sylhet":500,"Rajshahi":264,"Barisal":160,"Rangpur":460},
    "Barisal":{"Dhaka":169,"Chattogram":380,"Cumilla":260,"Sylhet":410,"Rajshahi":340,"Khulna":160,"Rangpur":480},
    "Rangpur":{"Dhaka":330,"Chattogram":640,"Cumilla":450,"Sylhet":450,"Rajshahi":185,"Khulna":460,"Barisal":480}
  };
  if(f && t && f!==t && map[f] && map[f][t]) el.textContent="Distance: "+map[f][t]+" km";
  else el.textContent="Select both locations to see distance";
}

['driverFee','fuelCost','tollCost','laborCost','gateCost','miscCost','revenue'].forEach(id=>{
  const el=document.getElementById(id);
  if(el) el.addEventListener('input',calc);
});

function calc(){
  let df=+document.getElementById('driverFee').value||0;
  let fu=+document.getElementById('fuelCost').value||0;
  let to=+document.getElementById('tollCost').value||0;
  let la=+document.getElementById('laborCost').value||0;
  let ga=+document.getElementById('gateCost').value||0;
  let ot=+document.getElementById('miscCost').value||0;
  let rv=+document.getElementById('revenue').value||0;
  let tc=df+fu+to+la+ga+ot;
  document.getElementById('totalCost').textContent="Total Cost: "+tc.toFixed(2);
  const p=rv-tc; const pf=document.getElementById('profit');
  pf.textContent="Profit: "+p.toFixed(2);
  pf.classList.remove('positive','negative');
  if(p>0) pf.classList.add('positive'); else if(p<0) pf.classList.add('negative');
}

// When truck changes, update meta + prefill driver if empty
const truckSel = document.getElementById('truck_id');
const driverName = document.getElementById('driverName');
const driverNumber = document.getElementById('driverNumber');
const truckMeta = document.getElementById('truckMeta');

truckSel && truckSel.addEventListener('change',()=>{
  const opt = truckSel.options[truckSel.selectedIndex];
  if(!opt || !opt.value){ truckMeta.textContent = "Select a truck to start"; return; }
  const vtext = opt.textContent;
  const dname = opt.getAttribute('data-driver-name') || '';
  const dphone= opt.getAttribute('data-driver-phone') || '';
  truckMeta.textContent = "Truck: " + vtext;
  if(!driverName.value)  driverName.value  = dname;
  if(!driverNumber.value)driverNumber.value = dphone;
});

// Initialize helpers
calc();
updateDistance();
</script>
</body>
</html>
