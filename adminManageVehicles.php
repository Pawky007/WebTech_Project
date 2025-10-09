<?php
/****************************************************
 * HaulPro — Admin » Manage Vehicles
 * - Sidebar matches Show All Clients page (no blue glow)
 * - Clients from `users`
 * - Vehicle details from `lorry_owners`
 * - AJAX endpoints: ?ajax=clients, ?ajax=vehicles&user_id=ID
 ****************************************************/

// ---------- DB CONFIG ----------
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "webtech_project";

// ---------- CONNECT ----------
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die("DB connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

function jout($x){ header("Content-Type: application/json"); echo json_encode($x); exit; }
function fail($m,$c=400){ http_response_code($c); jout(["error"=>$m]); }

// ---------- AJAX: clients list with truck counts ----------
if (isset($_GET["ajax"]) && $_GET["ajax"] === "clients") {
  $sql = "
    SELECT
      u.id,
      COALESCE(u.full_name, '')   AS name,
      COALESCE(u.email, '')       AS email,
      DATE(u.created_at)          AS created_at,
      COUNT(l.id)                 AS total_trucks,
      SUM(
        CASE
          WHEN COALESCE(l.status,'') IN ('Available','Waiting for Load','In Transit')
          THEN 1 ELSE 0
        END
      ) AS active_trucks
    FROM users u
    LEFT JOIN lorry_owners l ON l.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC, u.id DESC
  ";
  $res = $mysqli->query($sql);
  $out=[]; while($r=$res->fetch_assoc()){ $out[]=$r; }
  jout($out);
}

// ---------- AJAX: vehicles for one client ----------
if (isset($_GET["ajax"]) && $_GET["ajax"] === "vehicles") {
  $uid = (int)($_GET["user_id"] ?? 0);
  if ($uid<=0) fail("Invalid user_id");

  $stmt = $mysqli->prepare("
    SELECT
      id,
      COALESCE(vehicle_no,'')  AS vehicle_no,
      COALESCE(truck_type,'')  AS truck_type,
      COALESCE(status,'')      AS status,
      COALESCE(contact,'')     AS contact,
      COALESCE(address,'')     AS address,
      COALESCE(capacity,0)     AS capacity,
      created_at
    FROM lorry_owners
    WHERE user_id=?
    ORDER BY created_at DESC, id DESC
  ");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;

  $c = $mysqli->prepare("
    SELECT id, COALESCE(full_name,'') AS name, COALESCE(email,'') AS email, DATE(created_at) AS created_at
    FROM users WHERE id=?
  ");
  $c->bind_param("i",$uid); $c->execute();
  $cli = $c->get_result()->fetch_assoc();

  jout(["client"=>$cli, "vehicles"=>$rows]);
}

// ---------- Helpers for active nav ----------
$current = basename($_SERVER['PHP_SELF']);
function navActive($f){ global $current; return $current===$f ? 'active' : ''; }

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Vehicles — HaulPro</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="dashboad_style.css"><!-- your global theme -->
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>

<style>
:root{
  --primary:#2563eb; --primary-hover:#1d4ed8;
  --bg:#f6f8fc; --surface:#fff; --border:#e5e7eb; --text:#0f172a;
  --muted:#64748b; --subtle:#334155;
  --radius:14px; --shadow:0 6px 18px rgba(15,23,42,.06);
  --chip:#eef2ff; --chiptext:#3730a3;
}

/* Base layout */
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,"Segoe UI",system-ui,-apple-system,sans-serif}
.layout{display:flex;min-height:100vh}

/* ===== Sidebar (same as Show All Clients, but no blue glow) ===== */
.sidebar{
  width:260px;background:var(--surface);border-right:1px solid var(--border);
  padding:18px;display:flex;flex-direction:column;gap:10px;position:sticky;top:0;height:100vh
}
.brand{display:flex;align-items:center;gap:10px;padding:4px 6px}
.brand img{width:160px;height:auto}
.brand-title{font-weight:800;font-size:18px;color:var(--primary)}
.menu{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-direction:column;gap:6px}
.menu a{
  display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;text-decoration:none;
  color:var(--text);font-weight:600;border:1px solid transparent
}
/* neutral hovers */
.menu a:hover{background:#f3f4f6;border-color:var(--border)}
/* active: keep active state but remove blue glow + blue background */
.menu a.active{
  background:#e5e7eb; color:var(--text); border-color:#e5e7eb; box-shadow:none
}

/* Main content */
.content{flex:1;padding:26px;max-width:1700px;margin:0 auto}
h1{margin:0 0 8px;font-size:24px;color:var(--primary);font-weight:800}
.sub{color:var(--muted);font-size:.92rem;margin-bottom:14px}

/* Card + table */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px}
.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:12px}
table{width:100%;border-collapse:separate;border-spacing:0}
thead th{position:sticky;top:0;background:#f8fafc;color:#334155;font-weight:800;border-bottom:1px solid var(--border);padding:12px}
/* neutral row hover (no blue tint) */
tbody td{border-bottom:1px solid var(--border);padding:12px}
tbody tr:nth-child(even){background:#fcfdff}
tbody tr:hover{background:#f3f4f6;cursor:pointer}

/* Badges */
.badge{display:inline-block;border-radius:999px;padding:4px 10px;font-size:.82rem;font-weight:700}
.badge.gray{background:#eef2f7;color:#334155;border:1px solid #d8deea}
.badge.green{background:#e7fff2;color:#0f7a43;border:1px solid #bff0d1}

/* Modal */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);opacity:0;pointer-events:none;transition:.15s}
.modal-backdrop.show{opacity:1;pointer-events:auto}
.modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none}
.modal .panel{
  width:min(1100px,94vw);max-height:86vh;overflow:auto;background:#fff;border:1px solid var(--border);
  border-radius:14px;box-shadow:0 20px 50px rgba(2,6,23,.18);transform:translateY(8px);opacity:0;transition:.2s
}
.modal.show{pointer-events:auto}
.modal.show .panel{transform:translateY(0);opacity:1}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px;border-bottom:1px solid var(--border)}
.modal-title{font-size:18px;font-weight:800;color:var(--subtle)}
.modal-body{padding:16px}
.modal-footer{padding:14px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px}
.btn{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 16px;font-weight:800;cursor:pointer}
.btn:hover{background:var(--primary-hover)}
.btn.secondary{background:#f1f5f9;color:#0f172a;border:1px solid var(--border)}
.btn.secondary:hover{background:#e2e8f0}

/* Tiny helpers */
.row-meta{display:flex;gap:8px;flex-wrap:wrap}
.meta{color:#64748b;font-size:.84rem}
</style>
</head>
<body>
<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <img src="Image/Logo.png" alt="HaulPro Logo" width="160" />
    <h3>HaulPro</h3>
    <ul class="menu">
      <li><a class="<?= navActive('adminDashboard.php') ?>" href="adminDashboard.php"><img src="Image/dashboard.png" style="width:40px" alt=""/>Dashboard</a></li>
      <li><a class="<?= navActive('adminShowClients.php') ?>" href="adminShowClients.php"><img src="Image/magnifying-glass.png" alt="" style="width:40px" />Show All Clients</a></li>
      <li><a class="<?= navActive('adminManageVehicles.php') ?>" href="adminManageVehicles.php"><img src="Image/car1.png" alt="" style="width:40px" />Manage Vehicles</a></li>
      <li><a class="<?= navActive('adminPayment.php') ?>" href="adminPayment.php"><img src="Image/wallet.png" alt="" style="width:40px" />Payments</a></li>
      <li><a class="<?= navActive('adminSettings.php') ?>" href="adminSettings.php"><img src="Image/settings.png" alt="" style="width:40px" />Settings</a></li>
      <li><a href="login.php"><img src="Image/right-arrow.png" alt="" style="width:40px" />Log Out</a></li>
    </ul>
    <div class="help-card" style="margin-top:auto;padding:14px;border:1px dashed var(--border);border-radius:12px;background:#fafcff;text-align:center;color:var(--subtle)">
      <img src="https://cdn-icons-png.flaticon.com/512/4712/4712002.png" alt="Help" style="width:60px;height:60px;margin-bottom:10px"/>
      <p style="margin-bottom:10px;font-weight:600">Need Help?</p>
      <button style="background:#111827;color:#fff;border:none;padding:10px 15px;border-radius:10px;font-weight:700;cursor:pointer">Contact Now</button>
    </div>
  </aside>

  <!-- Main -->
  <main class="content">
    <h1>Manage Vehicles</h1>
    <div class="sub">Click a client row to view their trucks</div>

    <div class="card">
      <div class="table-wrap">
        <table id="clientsTable">
          <thead>
            <tr>
              <th style="min-width:240px">Client</th>
              <th>Email</th>
              <th>Joined</th>
              <th style="min-width:210px;text-align:right">Trucks</th>
            </tr>
          </thead>
          <tbody><!-- JS injects rows --></tbody>
        </table>
      </div>
      <div class="meta" style="margin-top:8px">Data live from <code>users</code> and <code>lorry_owners</code>.</div>
    </div>
  </main>
</div>

<!-- Modal -->
<div id="vehModal" class="modal" aria-hidden="true">
  <div class="modal-backdrop" data-close></div>
  <div class="panel" role="dialog" aria-modal="true" aria-labelledby="vehTitle">
    <div class="modal-header">
      <div>
        <div id="vehTitle" class="modal-title">Client Vehicles</div>
        <div id="vehSub" class="meta"></div>
      </div>
      <button class="btn secondary" data-close>Close</button>
    </div>
    <div class="modal-body">
      <div class="row-meta" style="margin-bottom:10px">
        <span class="badge gray">Total: <span id="metaTotal">0</span></span>
        <span class="badge green">Active: <span id="metaActive">0</span></span>
      </div>
      <div class="table-wrap">
        <table id="vehTable">
          <thead>
            <tr>
              <th>Vehicle No</th>
              <th>Truck Type</th>
              <th>Status</th>
              <th>Contact</th>
              <th>Address / Yard</th>
              <th>Capacity</th>
            </tr>
          </thead>
          <tbody><!-- JS injects rows --></tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn secondary" data-close>Close</button>
    </div>
  </div>
</div>

<script>
const $ = sel => document.querySelector(sel);

/* Load clients */
async function fetchClients(){
  const r = await fetch('?ajax=clients');
  const list = await r.json();
  const tb = document.querySelector('#clientsTable tbody');
  tb.innerHTML = '';

  if (!Array.isArray(list) || list.length === 0) {
    tb.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#64748b;padding:16px">No users found.</td></tr>`;
    return;
  }

  list.forEach(row=>{
    const tr = document.createElement('tr');
    tr.dataset.userId = row.id;
    tr.innerHTML = `
      <td><strong>${esc(row.name || '(No name)')}</strong></td>
      <td>${esc(row.email || '')}</td>
      <td>${esc(row.created_at || '')}</td>
      <td style="text-align:right">
        <span class="badge gray" style="margin-right:6px">Total: ${Number(row.total_trucks||0)}</span>
        <span class="badge green">Active: ${Number(row.active_trucks||0)}</span>
      </td>
    `;
    tb.appendChild(tr);
  });
}

/* Row click -> modal */
document.addEventListener('click', async (e)=>{
  const tr = e.target.closest('#clientsTable tbody tr');
  if (tr) {
    const uid = tr.dataset.userId;
    if (uid) openVehicles(uid);
  }
});

/* Modal helpers */
const vehModal = document.getElementById('vehModal');
function showModal(){ vehModal.classList.add('show'); vehModal.querySelector('.modal-backdrop').classList.add('show'); }
function hideModal(){ vehModal.classList.remove('show'); vehModal.querySelector('.modal-backdrop').classList.remove('show'); }
vehModal.addEventListener('click', (e)=>{ if (e.target.hasAttribute('data-close')) hideModal(); });

/* Open vehicles for client */
async function openVehicles(userId){
  const r = await fetch(`?ajax=vehicles&user_id=${encodeURIComponent(userId)}`);
  const j = await r.json();
  const client = j.client || {};
  const vehs = j.vehicles || [];

  document.getElementById('vehSub').textContent =
    `${client.name || '(No name)'} • ${client.email || ''} • joined ${client.created_at || ''}`;

  const tb = document.querySelector('#vehTable tbody');
  tb.innerHTML = '';

  let active = 0;
  vehs.forEach(v=>{
    const s = String(v.status||'').toLowerCase();
    if (['available','in transit','waiting for load'].includes(s)) active++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(v.vehicle_no)}</td>
      <td>${esc(v.truck_type)}</td>
      <td>${esc(v.status)}</td>
      <td>${esc(v.contact)}</td>
      <td>${esc(v.address)}</td>
      <td>${esc(v.capacity)}</td>
    `;
    tb.appendChild(tr);
  });

  document.getElementById('metaTotal').textContent = vehs.length;
  document.getElementById('metaActive').textContent = active;

  showModal();
}

/* Utils */
function esc(s){
  return String(s==null?'':s)
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#39;');
}

/* Boot */
fetchClients();
</script>
</body>
</html>
