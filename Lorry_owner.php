<?php
require __DIR__.'/db.php';
require __DIR__.'/auth.php';
require_login();
$user_id = (int) current_user_id();

/* ---------- Ensure tables (safe) ---------- */
$mysqli->query("
CREATE TABLE IF NOT EXISTS lorry_owners (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  vehicle_no VARCHAR(80) NOT NULL,
  owner_type VARCHAR(40) NOT NULL,
  owner_name VARCHAR(120) DEFAULT NULL,
  truck_type VARCHAR(60) NOT NULL,
  status VARCHAR(40) DEFAULT 'Available',
  driver_id BIGINT DEFAULT NULL,
  contact VARCHAR(50) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  capacity DECIMAL(10,2) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  user_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (driver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$mysqli->query("
CREATE TABLE IF NOT EXISTS drives (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  contact VARCHAR(50) NOT NULL,
  license_no VARCHAR(80) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  status ENUM('Active','Inactive') DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$msg = '';
$err = '';

/* ---------- Handle POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    /* ===== Vehicles ===== */
    if ($action === 'add_vehicle') {
      $veh_no     = trim($_POST['vehicle_no'] ?? '');
      $owner_type = trim($_POST['owner_type'] ?? '');
      $owner_name = trim($_POST['owner_name'] ?? '');
      $truck_type = trim($_POST['truck_type'] ?? '');
      $status     = trim($_POST['status'] ?? 'Available');
      $driver_id  = (int)($_POST['driver_id'] ?? 0);
      $contact    = trim($_POST['contact'] ?? '');
      $address    = trim($_POST['address'] ?? '');
      $capacity   = ($_POST['capacity'] === '' ? null : (float)$_POST['capacity']);
      $notes      = trim($_POST['notes'] ?? '');

      if ($veh_no === '' || $owner_type === '' || $truck_type === '') {
        throw new Exception('Vehicle no, owner type and truck type are required.');
      }
      if ($owner_type === 'Private' && ($owner_name === '' || $contact === '')) {
        throw new Exception('Owner name and contact are required for private lorries.');
      }
      if ($driver_id) {
        $chk = $mysqli->prepare("SELECT id FROM drives WHERE id=? AND user_id=?");
        $chk->bind_param('ii', $driver_id, $user_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
          throw new Exception('Selected driver does not exist or is not yours.');
        }
      }

      $driver_id_param = $driver_id ?: null;
      $stm = $mysqli->prepare("
        INSERT INTO lorry_owners
          (vehicle_no, owner_type, owner_name, truck_type, status, driver_id, contact, address, capacity, notes, user_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
      ");
      $stm->bind_param(
        'sssssissdsi',
        $veh_no, $owner_type, $owner_name, $truck_type, $status,
        $driver_id_param, $contact, $address, $capacity, $notes, $user_id
      );
      $stm->execute();
      $msg = 'Vehicle added successfully.';

    } elseif ($action === 'update_vehicle') {
      $id         = (int)($_POST['id'] ?? 0);
      $veh_no     = trim($_POST['vehicle_no'] ?? '');
      $owner_type = trim($_POST['owner_type'] ?? '');
      $owner_name = trim($_POST['owner_name'] ?? '');
      $truck_type = trim($_POST['truck_type'] ?? '');
      $status     = trim($_POST['status'] ?? 'Available');
      $driver_id  = (int)($_POST['driver_id'] ?? 0);
      $contact    = trim($_POST['contact'] ?? '');
      $address    = trim($_POST['address'] ?? '');
      $capacity   = ($_POST['capacity'] === '' ? null : (float)$_POST['capacity']);
      $notes      = trim($_POST['notes'] ?? '');

      if ($id<=0) throw new Exception('Invalid vehicle.');
      if ($veh_no === '' || $owner_type === '' || $truck_type === '') {
        throw new Exception('Vehicle no, owner type and truck type are required.');
      }
      if ($owner_type === 'Private' && ($owner_name === '' || $contact === '')) {
        throw new Exception('Owner name and contact are required for private lorries.');
      }
      if ($driver_id) {
        $chk = $mysqli->prepare("SELECT id FROM drives WHERE id=? AND user_id=?");
        $chk->bind_param('ii', $driver_id, $user_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
          throw new Exception('Selected driver does not exist or is not yours.');
        }
      }

      $driver_id_param = $driver_id ?: null;
      $stm = $mysqli->prepare("
        UPDATE lorry_owners
           SET vehicle_no=?, owner_type=?, owner_name=?, truck_type=?, status=?,
               driver_id=?, contact=?, address=?, capacity=?, notes=?
         WHERE id=? AND user_id=?
      ");
      $stm->bind_param(
        'sssssissdsii',
        $veh_no, $owner_type, $owner_name, $truck_type, $status,
        $driver_id_param, $contact, $address, $capacity, $notes, $id, $user_id
      );
      $stm->execute();
      if ($stm->affected_rows === 0) throw new Exception('Update failed or not permitted.');
      $msg = 'Vehicle updated.';

    } elseif ($action === 'delete_vehicle') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new Exception('Invalid vehicle.');
      $stm = $mysqli->prepare("DELETE FROM lorry_owners WHERE id=? AND user_id=?");
      $stm->bind_param('ii', $id, $user_id);
      $stm->execute();
      if ($stm->affected_rows === 0) throw new Exception('Delete failed or not permitted.');
      $msg = 'Vehicle deleted.';

    /* ===== Drivers ===== */
    } elseif ($action === 'drv_add') {
      $d_name   = trim($_POST['drv_full_name'] ?? '');
      $d_phone  = trim($_POST['drv_contact'] ?? '');
      $d_lic    = trim($_POST['drv_license_no'] ?? '');
      $d_addr   = trim($_POST['drv_address'] ?? '');
      $d_status = in_array($_POST['drv_status'] ?? 'Active', ['Active','Inactive'], true) ? $_POST['drv_status'] : 'Active';

      if ($d_name === '' || $d_phone === '') throw new Exception('Driver name and contact are required.');

      $stm = $mysqli->prepare("
        INSERT INTO drives (user_id, full_name, contact, license_no, address, status)
        VALUES (?,?,?,?,?,?)
      ");
      $stm->bind_param('isssss', $user_id, $d_name, $d_phone, $d_lic, $d_addr, $d_status);
      $stm->execute();
      $msg = 'Driver added.';

    } elseif ($action === 'drv_update') {
      $did     = (int)($_POST['drv_id'] ?? 0);
      $d_name  = trim($_POST['drv_full_name'] ?? '');
      $d_phone = trim($_POST['drv_contact'] ?? '');
      $d_lic   = trim($_POST['drv_license_no'] ?? '');
      $d_addr  = trim($_POST['drv_address'] ?? '');
      $d_status= in_array($_POST['drv_status'] ?? 'Active', ['Active','Inactive'], true) ? $_POST['drv_status'] : 'Active';

      if ($did<=0) throw new Exception('Invalid driver.');
      if ($d_name === '' || $d_phone === '') throw new Exception('Driver name and contact are required.');

      $stm = $mysqli->prepare("
        UPDATE drives
           SET full_name=?, contact=?, license_no=?, address=?, status=?
         WHERE id=? AND user_id=?
      ");
      $stm->bind_param('sssssii', $d_name, $d_phone, $d_lic, $d_addr, $d_status, $did, $user_id);
      $stm->execute();
      if ($stm->affected_rows === 0) throw new Exception('Update failed or not permitted.');
      $msg = 'Driver updated.';

    } elseif ($action === 'drv_delete') {
      $did = (int)($_POST['drv_id'] ?? 0);
      if ($did<=0) throw new Exception('Invalid driver.');
      $stm = $mysqli->prepare("DELETE FROM drives WHERE id=? AND user_id=?");
      $stm->bind_param('ii', $did, $user_id);
      $stm->execute();
      if ($stm->affected_rows === 0) throw new Exception('Delete failed or not permitted.');
      $msg = 'Driver deleted.';
    }

  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

/* ---------- Data for page ---------- */
// vehicle edit
$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$edit_owner = null;
if ($edit_id) {
  $st = $mysqli->prepare("SELECT * FROM lorry_owners WHERE id=? AND user_id=?");
  $st->bind_param('ii', $edit_id, $user_id);
  $st->execute();
  $edit_owner = $st->get_result()->fetch_assoc();
}

// driver edit
$drv_edit_id = isset($_GET['drv_edit']) ? (int)$_GET['drv_edit'] : 0;
$edit_driver = null;
if ($drv_edit_id) {
  $st = $mysqli->prepare("SELECT * FROM drives WHERE id=? AND user_id=?");
  $st->bind_param('ii', $drv_edit_id, $user_id);
  $st->execute();
  $edit_driver = $st->get_result()->fetch_assoc();
}

// driver list for dropdown
$drvStmt = $mysqli->prepare("SELECT id, full_name, contact, status FROM drives WHERE user_id=? ORDER BY full_name");
$drvStmt->bind_param('i', $user_id);
$drvStmt->execute();
$drivers = $drvStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// vehicles table
$stList = $mysqli->prepare("
  SELECT lo.*, d.full_name AS drv_name, d.contact AS drv_phone
  FROM lorry_owners lo
  LEFT JOIN drives d ON d.id = lo.driver_id
  WHERE lo.user_id=?
  ORDER BY lo.vehicle_no
");
$stList->bind_param('i', $user_id);
$stList->execute();
$vehicles = $stList->get_result();

// drivers table
$drvListStmt = $mysqli->prepare("SELECT * FROM drives WHERE user_id=? ORDER BY full_name");
$drvListStmt->bind_param('i', $user_id);
$drvListStmt->execute();
$driversList = $drvListStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HaulPro — Lorry Owners</title>
<link rel="stylesheet" href="dashboad_style.css">
<style>
/* Keep original theme; polish buttons & add tab animation */
:root {
  --bg:#f9fafb; --surface:#ffffff; --text:#1f2937; --muted:#6b7280;
  --border:#e5e7eb; --primary:#2563eb; --primary-hover:#1d4ed8;
  --danger:#ef4444; --danger-hover:#dc2626; --secondary:#f3f4f6;
  --radius:10px; --shadow:0 2px 6px rgba(0,0,0,0.08);
}
body{font-family:'Segoe UI',Tahoma,sans-serif;background:var(--bg);margin:0;color:var(--text);line-height:1.5;}
.container{display:flex;}
.shell{flex:1;max-width:1700px;margin:32px auto;padding:0 16px;}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
h2{font-size:1.7rem;font-weight:700;text-align:center;flex-grow:1;color:#2563eb;}
h3{margin-bottom:16px;font-size:1.2rem;color:#2563eb;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:24px;margin-bottom:24px;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;}
label{font-size:14px;font-weight:700;color:#334155;margin-bottom:6px;display:block;}
input,select,textarea{padding:10px;border:1px solid var(--border);border-radius:10px;font-size:14px;outline:none;width:100%;}
input:focus,select:focus,textarea:focus{border-color:var(--primary);box-shadow:0 0 0 2px rgba(37,99,235,.12)}
.form-actions{margin-top:16px;display:flex;flex-wrap:wrap;gap:10px;}
.msg{margin:16px 0;padding:12px 16px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:10px;font-size:14px;}
.msg.err{background:#fee2e2;color:#991b1b;border-color:#fecaca}
table{width:100%;border-collapse:collapse;font-size:14px;border-radius:10px;overflow:hidden;}
th,td{padding:14px 16px;border-bottom:1px solid var(--border);}
th{background:#f8fafc;font-weight:700;text-align:left;position:sticky;top:0;}
tr:nth-child(even) td{background:#fdfdfd;}
tr:hover td{background:#f1f5ff;}
.status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600;text-align:center;}
.status-available{background:#dcfce7;color:#166534;}
.status-active{background:#dbeafe;color:#1e40af;}
.status-intransit{background:#ffedd5;color:#9a3412;}
.status-delivered{background:#e5e7eb;color:#374151;}
.status-waiting{background:#ede9fe;color:#5b21b6;}
.status-outofservice{background:#fee2e2;color:#991b1b;}
.status-maintenance{background:#fef9c3;color:#854d0e;}

.small-muted{font-size:12px;color:#64748b}

/* Polished buttons */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:8px 14px;border-radius:999px;border:1px solid var(--border);
  font-size:13px;font-weight:700;cursor:pointer;background:#fff;color:#0f172a;
  transition:transform .12s ease, box-shadow .12s ease, background .12s ease, color .12s ease, border-color .12s ease;
  box-shadow:0 1px 2px rgba(15,23,42,.06);
}
.btn:hover{transform:translateY(-1px); box-shadow:0 4px 14px rgba(2,6,23,.10);}
.btn:active{transform:translateY(0); box-shadow:0 1px 2px rgba(2,6,23,.08);}
.btn.primary{background:var(--primary);color:#fff;border-color:var(--primary);}
.btn.primary:hover{background:var(--primary-hover);}
.btn.danger{background:var(--danger);color:#fff;border-color:var(--danger);}
.btn.danger:hover{background:var(--danger-hover);}
.btn.secondary{background:#eef2ff;color:#1e40af;border-color:#c7d2fe}

/* Action buttons group in tables */
.actions{display:flex;gap:8px;flex-wrap:wrap}
.actions .btn{padding:7px 12px}

/* Tabs with animation */
.tabs{display:flex;gap:8px;margin:6px 0 14px}
.tab-btn{padding:8px 14px;border:1px solid var(--border);border-radius:999px;background:#fff;font-weight:800;color:#334155;cursor:pointer;transition:all .18s ease}
.tab-btn:hover{box-shadow:0 3px 10px rgba(2,6,23,.08); transform:translateY(-1px)}
.tab-btn.active{background:#2563eb;color:#fff;border-color:#2563eb; box-shadow:0 6px 20px rgba(37,99,235,.25)}

/* Animated panels (slide + fade). Using max-height to allow transition without flash. */
.tab-panel{
  overflow:hidden;
  opacity:0;
  transform:translateY(8px);
  max-height:0;
  transition:opacity .22s ease, transform .22s ease, max-height .25s ease;
  will-change:opacity, transform, max-height;
}
.tab-panel.active{
  opacity:1;
  transform:translateY(0);
  max-height:5000px; /* plenty to fit content; no layout jump */
}
</style>
<script>
function confirmDel(formId){
  if(confirm('Delete this record?')){
    document.getElementById(formId).submit();
  }
}

function toggleFields(){
  const typeSel=document.querySelector('select[name="owner_type"]');
  if(!typeSel) return;
  const ownerDiv=document.getElementById('ownerNameDiv');
  const ownerInput=document.querySelector('input[name="owner_name"]');
  const contactDiv=document.getElementById('contactDiv');
  const contactInput=document.querySelector('input[name="contact"]');
  if(typeSel.value==='Private'){
    ownerDiv.style.display='block'; ownerInput.required=true;
    contactDiv.style.display='block'; contactInput.required=true;
  } else {
    ownerDiv.style.display='none'; ownerInput.required=false; ownerInput.value='';
    contactDiv.style.display='none'; contactInput.required=false; contactInput.value='';
  }
}

document.addEventListener('DOMContentLoaded',()=>{
  toggleFields();
  const t=document.querySelector('select[name="owner_type"]');
  if(t) t.addEventListener('change',toggleFields);

  // Tabs + animation
  const btns=[...document.querySelectorAll('.tab-btn')];
  const panels=[...document.querySelectorAll('.tab-panel')];

  function activate(targetId){
    btns.forEach(b=>b.classList.toggle('active', b.dataset.target===targetId));
    panels.forEach(p=>{
      p.classList.toggle('active', p.id===targetId);
    });
  }

  btns.forEach(btn=>{
    btn.addEventListener('click',()=>activate(btn.dataset.target));
  });

  // If editing a driver, open driver tab
  <?php if($edit_driver): ?>
  activate('tab-drivers');
  <?php endif; ?>
});
</script>
</head>
<body>
<div class="container">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <img src="Image/Logo.png" alt="HaulPro Logo" width="160" />
    <h3>HaulPro</h3>
    <ul class="menu">
      <li><a href="dashboard.php"><img src="Image/dashboard.png" alt="" />Dashboard</a></li>
      <li class="has-submenu">
        <a href="#"><img src="Image/chart.png" alt="" />Analysis</a>
        <ul class="submenu">
          <li><a href="delivery_performance.php"><img src="Image/continuous-improvement.png" alt=""/>Delivery Performance</a></li>
          <li><a href="Revenue_analysis.php"><img src="Image/profit-margin.png" alt=""/>Revenue Analysis</a></li>
          <li><a href="fleet_analysis.php"><img src="Image/delivery-truck.png" alt=""/>Fleet Efficiency</a></li>
        </ul>
      </li>
      <li><a href="calculationInput.php"><img src="Image/plus.png" alt="" style="width:40px" />Add Trips</a></li>
      <li><a href="Payment_customer.php"><img src="Image/wallet.png" alt="" style="width:40px" />Payment Method</a></li>
      <li><a class="active" href="Lorry_owner.php"><img src="Image/businessman.png" alt="" style="width:40px" />Lorry Owner List</a></li>
      <li><a href="lorrylist.php"><img src="Image/truck.png" alt="" style="width:40px" />Lorry List</a></li>
      <li><a href="Payment_customer.php"><img src="Image/settings.png" alt="" style="width:40px" />Settings</a></li>
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

  <!-- Main content -->
  <div class="shell">
    <div class="topbar">
      <h2>🚚 Lorry Owner & Driver Management</h2>
      <span></span>
    </div>

    <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="msg err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="tabs">
      <button class="tab-btn active" data-target="tab-vehicles">Add Vehicle</button>
      <button class="tab-btn" data-target="tab-drivers">Manage Drivers</button>
    </div>

    <!-- ================= VEHICLES TAB ================= -->
    <div class="tab-panel active" id="tab-vehicles">
      <div class="card">
        <h3><?= $edit_owner ? '✏️ Edit Lorry Owner' : '➕ Add Lorry Owner' ?></h3>
        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
          <div class="grid">
            <div>
              <label>Vehicle Number</label>
              <input type="text" name="vehicle_no" required value="<?= htmlspecialchars($edit_owner['vehicle_no'] ?? '') ?>">
            </div>
            <div>
              <label>Owner Type</label>
              <select name="owner_type" required>
                <?php
                  $types=['Company','Private'];
                  $cur=$edit_owner['owner_type'] ?? '';
                  foreach($types as $t){
                    $sel=($t===$cur)?'selected':''; echo "<option $sel>".htmlspecialchars($t)."</option>";
                  }
                ?>
              </select>
            </div>
            <div id="ownerNameDiv">
              <label>Owner Name</label>
              <input type="text" name="owner_name" value="<?= htmlspecialchars($edit_owner['owner_name'] ?? '') ?>">
            </div>
            <div>
              <label>Truck Type</label>
              <select name="truck_type" required>
                <?php
                  $opts=['Small Truck','Medium Truck','Large Truck','Covered Van','Open Truck'];
                  $cur=$edit_owner['truck_type'] ?? '';
                  foreach($opts as $o){
                    $sel=($o===$cur)?'selected':''; echo "<option $sel>".htmlspecialchars($o)."</option>";
                  }
                ?>
              </select>
            </div>
            <div>
              <label>Status</label>
              <select name="status">
                <?php
                  $sopts=['Available','Active','In Transit','Delivered','Waiting for Load','Out of Service','Maintenance'];
                  $cur=$edit_owner['status'] ?? 'Available';
                  foreach($sopts as $s){
                    $sel=($s===$cur)?'selected':''; echo "<option $sel>".htmlspecialchars($s)."</option>";
                  }
                ?>
              </select>
            </div>
            <div>
              <label>Driver</label>
              <select name="driver_id">
                <option value="">— No driver —</option>
                <?php
                  $curDrv = (int)($edit_owner['driver_id'] ?? 0);
                  foreach($drivers as $d){
                    $sel = ($curDrv === (int)$d['id']) ? 'selected' : '';
                    $label = $d['full_name'].' — '.$d['contact'].($d['status']==='Inactive'?' (inactive)':'');
                    echo '<option value="'.(int)$d['id'].'" '.$sel.'>'.htmlspecialchars($label).'</option>';
                  }
                ?>
              </select>
              <div class="small-muted">Add drivers in the <b>Manage Drivers</b> tab to see them here.</div>
            </div>
            <div id="contactDiv">
              <label>Owner Contact</label>
              <input type="text" name="contact" value="<?= htmlspecialchars($edit_owner['contact'] ?? '') ?>">
            </div>
            <div>
              <label>Address</label>
              <input type="text" name="address" value="<?= htmlspecialchars($edit_owner['address'] ?? '') ?>">
            </div>
            <div>
              <label>Capacity (tons)</label>
              <input type="number" step="0.1" name="capacity" value="<?= htmlspecialchars($edit_owner['capacity'] ?? '') ?>">
            </div>
            <div style="grid-column:1/-1">
              <label>Notes</label>
              <textarea name="notes" rows="2"><?= htmlspecialchars($edit_owner['notes'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="form-actions">
            <?php if($edit_owner): ?>
              <input type="hidden" name="id" value="<?= (int)$edit_owner['id'] ?>">
              <input type="hidden" name="action" value="update_vehicle">
              <button class="btn primary" type="submit">💾 Save</button>
              <a class="btn" href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">Cancel</a>
            <?php else: ?>
              <input type="hidden" name="action" value="add_vehicle">
              <button class="btn primary" type="submit">✅ Add Owner</button>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr>
              <th>Vehicle No</th>
              <th>Owner</th>
              <th>Truck Type</th>
              <th>Status</th>
              <th>Driver</th>
              <th>Capacity</th>
              <th style="width:200px">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if($vehicles && $vehicles->num_rows): while($row=$vehicles->fetch_assoc()): $id=(int)$row['id']; ?>
            <tr>
              <td><?= htmlspecialchars($row['vehicle_no']) ?></td>
              <td><?= $row['owner_type']==='Company' ? 'Company-Owned' : htmlspecialchars($row['owner_name'] ?: '—') ?></td>
              <td><?= htmlspecialchars($row['truck_type']) ?></td>
              <td>
                <?php
                  $status = $row['status']; $class = '';
                  switch($status) {
                    case 'Available': $class='status-available'; break;
                    case 'Active': $class='status-active'; break;
                    case 'In Transit': $class='status-intransit'; break;
                    case 'Delivered': $class='status-delivered'; break;
                    case 'Waiting for Load': $class='status-waiting'; break;
                    case 'Out of Service': $class='status-outofservice'; break;
                    case 'Maintenance': $class='status-maintenance'; break;
                    default: $class='status-delivered';
                  }
                  echo "<span class='status-badge $class'>".htmlspecialchars($status)."</span>";
                ?>
              </td>
              <td><?= $row['drv_name'] ? htmlspecialchars($row['drv_name'].' — '.$row['drv_phone']) : '—' ?></td>
              <td><?= $row['capacity']!==null ? htmlspecialchars($row['capacity']).' tons' : '—' ?></td>
              <td class="actions">
                <a class="btn secondary" href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?edit_id=<?= $id ?>">Edit</a>
                <form id="del-<?= $id ?>" method="post" style="display:inline">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="action" value="delete_vehicle">
                  <button type="button" class="btn danger" onclick="confirmDel('del-<?= $id ?>')">Delete</button>
                </form>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="7">No lorry owners yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ================= DRIVERS TAB ================= -->
    <div class="tab-panel" id="tab-drivers">
      <div class="card">
        <h3><?= $edit_driver ? '✏️ Edit Driver' : '➕ Add Driver' ?></h3>
        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
          <div class="grid">
            <div>
              <label>Full Name</label>
              <input type="text" name="drv_full_name" required value="<?= htmlspecialchars($edit_driver['full_name'] ?? '') ?>">
            </div>
            <div>
              <label>Contact</label>
              <input type="text" name="drv_contact" required value="<?= htmlspecialchars($edit_driver['contact'] ?? '') ?>">
            </div>
            <div>
              <label>License No</label>
              <input type="text" name="drv_license_no" value="<?= htmlspecialchars($edit_driver['license_no'] ?? '') ?>">
            </div>
            <div>
              <label>Status</label>
              <select name="drv_status">
                <option <?= (($edit_driver['status'] ?? '')==='Active')?'selected':''; ?>>Active</option>
                <option <?= (($edit_driver['status'] ?? '')==='Inactive')?'selected':''; ?>>Inactive</option>
              </select>
            </div>
            <div style="grid-column:1/-1">
              <label>Address</label>
              <input type="text" name="drv_address" value="<?= htmlspecialchars($edit_driver['address'] ?? '') ?>">
            </div>
          </div>
          <div class="form-actions">
            <?php if($edit_driver): ?>
              <input type="hidden" name="drv_id" value="<?= (int)$edit_driver['id'] ?>">
              <input type="hidden" name="action" value="drv_update">
              <button class="btn primary" type="submit">💾 Save Driver</button>
              <a class="btn" href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">Cancel</a>
            <?php else: ?>
              <input type="hidden" name="action" value="drv_add">
              <button class="btn primary" type="submit">Save Driver</button>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="card">
        <h3>Your Drivers</h3>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Contact</th>
              <th>License</th>
              <th>Status</th>
              <th>Address</th>
              <th style="width:200px">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if($driversList && $driversList->num_rows): while($d=$driversList->fetch_assoc()): $did=(int)$d['id']; ?>
            <tr>
              <td><?= htmlspecialchars($d['full_name']) ?></td>
              <td><?= htmlspecialchars($d['contact']) ?></td>
              <td><?= htmlspecialchars($d['license_no']) ?></td>
              <td><?= htmlspecialchars($d['status']) ?></td>
              <td><?= htmlspecialchars($d['address']) ?></td>
              <td class="actions">
                <a class="btn secondary" href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?drv_edit=<?= $did ?>">Edit</a>
                <form id="del-drv-<?= $did ?>" method="post" style="display:inline">
                  <input type="hidden" name="drv_id" value="<?= $did ?>">
                  <input type="hidden" name="action" value="drv_delete">
                  <button type="button" class="btn danger" onclick="confirmDel('del-drv-<?= $did ?>')">Delete</button>
                </form>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="6">No drivers yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        <div class="small-muted" style="margin-top:8px">Tip: add your drivers here first; they appear in the driver dropdown in the <b>Add Vehicle</b> tab.</div>
      </div>
    </div>
    <!-- =============== / DRIVERS TAB =============== -->

  </div><!-- /shell -->
</div><!-- /container -->
</body>
</html>
