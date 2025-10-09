<?php
/*******************************
 * HaulPro — Dashboard (PHP, 1-file, user-scoped)
 * Fully dynamic KPI tiles with robust fallbacks
 *******************************/

require __DIR__.'/db.php';
require __DIR__.'/auth.php';
require_login();
$user_id = (int) current_user_id();

/* ---------- Helpers ---------- */
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = $db->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");
  return $res && $res->num_rows > 0;
}
function has_col(mysqli $db, string $t, string $c): bool {
  $t = $db->real_escape_string($t);
  $c = $db->real_escape_string($c);
  $res = $db->query("
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}'
  ");
  return $res && $res->num_rows > 0;
}
function q1(mysqli $db, string $sql, array $params = [], string $types = "") {
  $stmt = $db->prepare($sql);
  if (!$stmt) return null;
  if ($params) {
    if ($types === "") {
      foreach ($params as $p) $types .= (is_int($p)?'i':(is_float($p)?'d':'s'));
    }
    $stmt->bind_param($types, ...$params);
  }
  if (!$stmt->execute()) return null;
  $res = $stmt->get_result();
  return $res ? $res->fetch_assoc() : null;
}
function qcol(mysqli $db, string $sql, array $params = [], string $types = "", $fallback = 0) {
  $row = q1($db, $sql, $params, $types);
  if (!$row) return $fallback;
  $v = array_values($row)[0] ?? $fallback;
  return ($v === null ? $fallback : $v);
}

/* ---------- Default KPI container ---------- */
$kpi = [
  "total_due_bdt"                         => 0.0,
  "overdue_invoices"                      => 0,   // (overdue trips; key kept for UI)
  "active_vehicles"                       => 0,
  "total_pending_load"                    => 0,
  "total_accepted_load"                   => 0,
  "total_pickup_load"                     => 0,
  "total_completed_load"                  => 0,
  "total_cancelled_load"                  => 0,
  "total_earning_this_week_bdt"           => 0.0,
  "total_pending_payout_this_week_bdt"    => 0.0,
  "total_completed_payout_this_week_bdt"  => 0.0,
  "total_lorry"                           => 0
];

/* ---------- COST SUM (edit here if column names differ) ---------- */
$TRIP_COST_SQL = "
  COALESCE(t.driver_fee,0) +
  COALESCE(t.fuel_cost,0)  +
  COALESCE(t.toll_cost,0)  +
  COALESCE(t.labor_cost,0) +
  COALESCE(t.gate_cost,0)  +
  COALESCE(t.other_cost,0)
";
/* ---------------------------------------------------------------- */

/* ---------- Strategy 1: single view if available ---------- */
if (has_table($mysqli, 'v_dashboard_kpis')) {
  $sql = has_col($mysqli,'v_dashboard_kpis','user_id')
    ? "SELECT * FROM v_dashboard_kpis WHERE user_id=? LIMIT 1"
    : "SELECT * FROM v_dashboard_kpis LIMIT 1";
  $row = has_col($mysqli,'v_dashboard_kpis','user_id') ? q1($mysqli,$sql,[$user_id],'i') : q1($mysqli,$sql);
  if ($row) $kpi = array_merge($kpi, array_change_key_case($row, CASE_LOWER));
}

/* ---------- Strategy 2: compute missing metrics ---------- */
$compute_any = true;

/* --- Lorry counts & Active vehicles --- */
if ($compute_any && has_table($mysqli,'lorry_owners')) {
  $scope = has_col($mysqli,'lorry_owners','user_id');
  $where = $scope ? "WHERE user_id=?" : "";
  $par   = $scope ? [$user_id] : [];

  if ((int)$kpi['total_lorry'] === 0) {
    $kpi['total_lorry'] = (int) qcol($mysqli,"SELECT COUNT(*) FROM lorry_owners {$where}", $par);
  }

  if ((int)$kpi['active_vehicles'] === 0) {
    if (has_col($mysqli,'lorry_owners','status')) {
      $kpi['active_vehicles'] = (int) qcol(
        $mysqli,
        "SELECT COUNT(*) FROM lorry_owners {$where} ".($where?'AND':'WHERE')." COALESCE(status,'') NOT IN ('Inactive','Out of Service')",
        $par
      );
    } else if (has_table($mysqli,'trips')) {
      $joinScope = has_col($mysqli,'trips','user_id');
      $kpi['active_vehicles'] = (int) qcol(
        $mysqli,
        "SELECT COUNT(DISTINCT l.id)
           FROM lorry_owners l
           JOIN trips t ON t.truck_id = l.id
          ".($scope ? "WHERE l.user_id=?" : "WHERE 1=1")."
            AND t.trip_date >= (CURDATE() - INTERVAL 14 DAY)
          ".($joinScope ? " AND t.user_id=?" : ""),
        $scope && $joinScope ? [$user_id,$user_id] : ($scope ? [$user_id] : [])
      );
    }
  }
}

/* --- Due & Overdue — now powered by TRIPS --- */
if ($compute_any && has_table($mysqli,'trips')) {

  // Filter by user if present on trips
  $flt = has_col($mysqli,'trips','user_id') ? "WHERE t.user_id=?" : "WHERE 1=1";
  $par = has_col($mysqli,'trips','user_id') ? [$user_id] : [];

  // Subquery: payments mapped to trips when payments has entity_type/entity_id
  $pay_join_possible = has_table($mysqli,'payments') && has_col($mysqli,'payments','entity_type') && has_col($mysqli,'payments','entity_id');

  // TOTAL DUE
  if ((float)$kpi['total_due_bdt'] === 0.0) {
    $total_due_sql = "
      SELECT COALESCE(SUM(GREATEST( ($TRIP_COST_SQL) - COALESCE(p.paid,0), 0 )),0) AS total_due
      FROM trips t
      LEFT JOIN (
        SELECT entity_id, SUM(amount_bdt) paid
        FROM payments
        WHERE entity_type='TRIP'
        GROUP BY entity_id
      ) p ON p.entity_id = t.id
      {$flt}
    ";
    $kpi['total_due_bdt'] = $pay_join_possible ? (float) qcol($mysqli, $total_due_sql, $par) : 0.0;
  }

  // OVERDUE COUNT (overdue trips, 7-day terms)
  if ((int)$kpi['overdue_invoices'] === 0) {
    $overdue_sql = "
      SELECT COUNT(*) AS c
      FROM (
        SELECT
          GREATEST( ($TRIP_COST_SQL) - COALESCE(p.paid,0), 0 ) AS due_bdt,
          DATE_ADD(t.trip_date, INTERVAL 7 DAY) AS due_date
        FROM trips t
        LEFT JOIN (
          SELECT entity_id, SUM(amount_bdt) paid
          FROM payments
          WHERE entity_type='TRIP'
          GROUP BY entity_id
        ) p ON p.entity_id = t.id
        {$flt}
      ) x
      WHERE x.due_bdt > 0 AND x.due_date IS NOT NULL AND x.due_date < CURDATE()
    ";
    $kpi['overdue_invoices'] = $pay_join_possible ? (int) qcol($mysqli, $overdue_sql, $par) : 0;
  }
}

/* --- Trip status tiles & weekly earnings --- */
if ($compute_any && has_table($mysqli,'trips')) {
  $flt = has_col($mysqli,'trips','user_id') ? "WHERE user_id=?" : ""; $par = $flt ? [$user_id] : [];

  if (has_col($mysqli,'trips','trip_status')) {
    if ((int)$kpi['total_pending_load'] === 0)   $kpi['total_pending_load']   = (int) qcol($mysqli,"SELECT COUNT(*) FROM trips {$flt} ".($flt?"AND":"WHERE")." trip_status IN ('Pending')", $par);
    if ((int)$kpi['total_accepted_load'] === 0)  $kpi['total_accepted_load']  = (int) qcol($mysqli,"SELECT COUNT(*) FROM trips {$flt} ".($flt?"AND":"WHERE")." trip_status IN ('Accepted')", $par);
    if ((int)$kpi['total_pickup_load'] === 0)    $kpi['total_pickup_load']    = (int) qcol($mysqli,"SELECT COUNT(*) FROM trips {$flt} ".($flt?"AND":"WHERE")." trip_status IN ('Pickup','Picked Up')", $par);
    if ((int)$kpi['total_completed_load'] === 0) $kpi['total_completed_load'] = (int) qcol($mysqli,"SELECT COUNT(*) FROM trips {$flt} ".($flt?"AND":"WHERE")." trip_status IN ('Completed','Delivered')", $par);
    if ((int)$kpi['total_cancelled_load'] === 0) $kpi['total_cancelled_load'] = (int) qcol($mysqli,"SELECT COUNT(*) FROM trips {$flt} ".($flt?"AND":"WHERE")." trip_status IN ('Cancelled','Canceled')", $par);
  }

  if ((float)$kpi['total_earning_this_week_bdt'] === 0.0) {
    $statusFilter = has_col($mysqli,'trips','trip_status') ? " AND trip_status IN ('Completed','Delivered')" : "";
    $kpi['total_earning_this_week_bdt'] = (float) qcol(
      $mysqli,
      "SELECT COALESCE(SUM(revenue_bdt),0) FROM trips {$flt} ".($flt?"AND":"WHERE")." YEARWEEK(trip_date,1)=YEARWEEK(CURDATE(),1) {$statusFilter}",
      $par
    );
  }
}

/* --- Weekly payouts (TRIP-scope) --- */
if ($compute_any && has_table($mysqli,'payments') && has_table($mysqli,'trips')) {

  $hasTripScope = has_col($mysqli,'payments','entity_type') && has_col($mysqli,'payments','entity_id');

  // Completed payout this week (payments made this week against trips)
  if ((float)$kpi['total_completed_payout_this_week_bdt'] === 0.0 && $hasTripScope) {
    $fltTrips = has_col($mysqli,'trips','user_id') ? " AND t.user_id=?" : "";
    $par = has_col($mysqli,'trips','user_id') ? [$user_id] : [];
    $kpi['total_completed_payout_this_week_bdt'] = (float) qcol(
      $mysqli,
      "SELECT COALESCE(SUM(p.amount_bdt),0)
         FROM payments p
         JOIN trips t ON t.id = p.entity_id
        WHERE p.entity_type='TRIP'
          AND YEARWEEK(p.paid_date,1)=YEARWEEK(CURDATE(),1)
          {$fltTrips}",
      $par
    );
  }

  // Pending payout this week (due with due_date in this week)
  if ((float)$kpi['total_pending_payout_this_week_bdt'] === 0.0 && $hasTripScope) {
    $flt = has_col($mysqli,'trips','user_id') ? "WHERE t.user_id=?" : "WHERE 1=1";
    $par = has_col($mysqli,'trips','user_id') ? [$user_id] : [];
    $kpi['total_pending_payout_this_week_bdt'] = (float) qcol(
      $mysqli,
      "SELECT COALESCE(SUM(GREATEST( ($TRIP_COST_SQL) - COALESCE(p.paid,0), 0 )),0) AS pending_week
         FROM trips t
         LEFT JOIN (
           SELECT entity_id, SUM(amount_bdt) paid
           FROM payments
           WHERE entity_type='TRIP'
           GROUP BY entity_id
         ) p ON p.entity_id=t.id
        {$flt}
          AND YEARWEEK(DATE_ADD(t.trip_date, INTERVAL 7 DAY),1)=YEARWEEK(CURDATE(),1)",
      $par
    );
  }
}

/* ---------- Render helpers ---------- */
function bd_amount($n) {
  if ($n === null || $n === "") return "0৳";
  if (is_numeric($n)) return number_format((float)$n, 2, '.', ',') . "৳";
  return htmlspecialchars((string)$n) . "৳";
}
function bd_int($n) {
  if ($n === null || $n === "") return "0";
  return number_format((int)$n);
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HaulPro — Dashboard</title>
    <link rel="stylesheet" href="dashboad_style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="analysis_css.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>
    <style>
      #map { width:100%; height:600px; border:0; }
      .map-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; }
      .traffic-toggle { margin:10px 0; padding:8px 15px; border:none; background:#007bff; color:#fff; font-weight:bold; cursor:pointer; border-radius:5px; }
      .traffic-toggle:hover { background:#0056b3; }
    </style>
  </head>
  <body>
    <div class="container">
      <aside class="sidebar" id="sidebar">
        <img src="Image/Logo.png" alt="HaulPro Logo" width="160" />
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
          <li><a href="calculationInput.php"><img src="Image/plus.png" alt="" style="width:40px" />Add Trips</a></li>
          <li><a href="Payment_customer.php"><img src="Image/wallet.png" alt="" style="width:40px" />Payment Method</a></li>
          <li><a href="Lorry_owner.php"><img src="Image/businessman.png" alt="" style="width:40px" />Lorry Owner List</a></li>
          <li><a href="lorrylist.php"><img src="Image/truck.png" alt="" style="width:40px" />Lorry List</a></li>
          <li><a href="Customer_settings.php"><img src="Image/settings.png" alt="" style="width:40px" />Settings</a></li>
          <li><a href="faq.html"><img src="Image/faq.png" alt="" style="width:40px" />FAQ</a></li>
          <li><a href="ai_chatbot.php"><img src="Image/robot.png" alt="" style="width:40px" />AI Chat Bot</a></li>
          <li><a href="login.php"><img src="Image/right-arrow.png" alt="" style="width:40px" />Log Out</a></li>
        </ul>
<div class="help-card">
<img src="https://cdn-icons-png.flaticon.com/512/4712/4712002.png" alt="Help" />
<p>Need Help?</p>
<!-- Contact Now button: opens Gmail Compose in a NEW TAB -->
<button
   type="button"
   onclick="openGmailCompose('haulpro2025@gmail.com','Help Request','I need help with...')"
>
   Contact Now
</button>
<script>
   function openGmailCompose(to, subject = '', body = '') {
     const url =
       'https://mail.google.com/mail/?view=cm&fs=1' +
       '&to=' + encodeURIComponent(to) +
       (subject ? '&su=' + encodeURIComponent(subject) : '') +
       (body ? '&body=' + encodeURIComponent(body) : '');
     // open in a new tab
     window.open(url, '_blank', 'noopener');
   }
</script>
</div>
      </aside>

      <main class="dashboard" id="dashboard">
        <div class="header">
          <h1>Dashboard</h1>
        </div>

        <!-- KPI Cards (now driven by TRIPS) -->
        <div class="cards-grid">
          <div class="card"><p>Total Due</p><h2><?= bd_amount($kpi['total_due_bdt']); ?></h2></div>
          <div class="card"><p>Overdue</p><h2><?= bd_int($kpi['overdue_invoices']); ?></h2></div>
          <div class="card"><p>Active Vehicles</p><h2><?= bd_int($kpi['active_vehicles']); ?></h2></div>
          <div class="card"><p>Total Pending Load</p><h2><?= bd_int($kpi['total_pending_load']); ?></h2></div>
          <div class="card"><p>Total Accepted Load</p><h2><?= bd_int($kpi['total_accepted_load']); ?></h2></div>
          <div class="card"><p>Total Pick Up Load</p><h2><?= bd_int($kpi['total_pickup_load']); ?></h2></div>
          <div class="card"><p>Total Completed Load</p><h2><?= bd_int($kpi['total_completed_load']); ?></h2></div>
          <div class="card"><p>Total Cancelled Load</p><h2><?= bd_int($kpi['total_cancelled_load']); ?></h2></div>
          <div class="card"><p>Total Earning (This Week)</p><h2><?= bd_amount($kpi['total_earning_this_week_bdt']); ?></h2></div>
          <div class="card"><p>Pending Payout (This Week)</p><h2><?= bd_amount($kpi['total_pending_payout_this_week_bdt']); ?></h2></div>
          <div class="card"><p>Completed Payout (This Week)</p><h2><?= bd_amount($kpi['total_completed_payout_this_week_bdt']); ?></h2></div>
          <div class="card"><p>Total Lorry</p><h2><?= bd_int($kpi['total_lorry']); ?></h2></div>
        </div>

        <div class="main-content">
          <div class="map-container">
            <h3 class="map-heading">
              <span>Live GPS Tracking</span>
              <button class="traffic-toggle" onclick="toggleTraffic()">Toggle Traffic Layer</button>
            </h3>
            <div id="map"></div>
          </div>
        </div>
      </main>
    </div>

    <footer>
      <div class="footer-content">
        <p>&copy; 2025 HaulPro. All Rights Reserved.</p>
        <div class="footer-links">
          <a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a>
        </div>
      </div>
    </footer>

    <script>
      let map, directionsService, trafficLayer, trafficVisible = true;
      let myLocation = null, activeRenderer = null, activeVehicle = null;
      const randomOffset = (r=0.005)=> (Math.random()-0.5)*r*2;

      function initMap() {
        const defaultLocation = { lat: 23.8103, lng: 90.4125 }; // Dhaka
        map = new google.maps.Map(document.getElementById("map"), { zoom:12, center:defaultLocation });
        directionsService = new google.maps.DirectionsService();
        trafficLayer = new google.maps.TrafficLayer(); trafficLayer.setMap(map);

        const baseVehicles = [
          { id:"V1", lat:23.8201, lng:90.4152 },
          { id:"V2", lat:23.7955, lng:90.3537 },
          { id:"V3", lat:23.8324, lng:90.4228 },
          { id:"V4", lat:23.8124, lng:90.4280 },
          { id:"V5", lat:23.8050, lng:90.3900 },
          { id:"V6", lat:23.8255, lng:90.4005 },
        ].map(v=>({ id:v.id, position:{ lat:v.lat+randomOffset(), lng:v.lng+randomOffset() } }));
        addVehicleMarkers(baseVehicles);

        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(
            (pos) => {
              myLocation = { lat: pos.coords.latitude, lng: pos.coords.longitude };
              new google.maps.Marker({
                position: myLocation, map, title:"You (Position A)",
                icon:"http://maps.google.com/mapfiles/ms/icons/blue-dot.png",
              });
              map.setCenter(myLocation);
            },
            () => console.warn("Geolocation failed. Vehicles still showing.")
          );
        }
      }

      function addVehicleMarkers(vehicles){
        vehicles.forEach(v=>{
          const m = new google.maps.Marker({
            position: v.position, map, title: v.id,
            icon: "http://maps.google.com/mapfiles/ms/icons/red-dot.png",
          });
          m.addListener("click", ()=> toggleRoute(v.id, v.position));
        });
      }

      function toggleRoute(vehicleId, vehiclePos){
        if (!myLocation) { alert("Your location is not available yet."); return; }
        if (activeVehicle === vehicleId) {
          if (activeRenderer) { activeRenderer.setMap(null); activeRenderer=null; activeVehicle=null; }
        } else {
          if (activeRenderer) activeRenderer.setMap(null);
          const dr = new google.maps.DirectionsRenderer({
            map, suppressMarkers:false,
            polylineOptions:{ strokeColor:"#800080", strokeOpacity:1.0, strokeWeight:7 },
          });
          directionsService.route(
            { origin: myLocation, destination: vehiclePos, travelMode: google.maps.TravelMode.DRIVING },
            (resp, status)=>{
              if (status === "OK") { dr.setDirections(resp); activeRenderer=dr; activeVehicle=vehicleId; }
              else alert("Directions request failed: " + status);
            }
          );
        }
      }

      function toggleTraffic(){ trafficLayer.setMap(trafficVisible ? null : map); trafficVisible = !trafficVisible; }
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=API&callback=initMap" async defer></script>
    <script>
(function(){if(!window.chatbase||window.chatbase("getState")!=="initialized"){window.chatbase=(...arguments)=>{if(!window.chatbase.q){window.chatbase.q=[]}window.chatbase.q.push(arguments)};window.chatbase=new Proxy(window.chatbase,{get(target,prop){if(prop==="q"){return target.q}return(...args)=>target(prop,...args)}})}const onLoad=function(){const script=document.createElement("script");script.src="https://www.chatbase.co/embed.min.js";script.id="nTG_Qdo5baK6-SvuWzFtz";script.domain="www.chatbase.co";document.body.appendChild(script)};if(document.readyState==="complete"){onLoad()}else{window.addEventListener("load",onLoad)}})();
</script>
  </body>
</html>
