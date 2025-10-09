<?php
/*******************************
 * HaulPro — Lorry List (per user)
 * - Each row tied to logged-in user_id
 * - CRUD operations scoped to user
 *******************************/
require __DIR__.'/db.php';
require __DIR__.'/auth.php';
require_login();
$user_id = (int) current_user_id();

/* ====== HANDLE CRUD ====== */
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'add') {
      $reg    = trim($_POST['vehicle_no'] ?? '');
      $type   = trim($_POST['truck_type'] ?? '');
      $status = trim($_POST['status'] ?? 'Available');
      if ($reg === '' || $type === '') throw new Exception('Vehicle number and truck type are required.');

      $stm = $mysqli->prepare("INSERT INTO lorry_owners (vehicle_no, truck_type, status, user_id) VALUES (?,?,?,?)");
      $stm->bind_param('sssi', $reg, $type, $status, $user_id);
      $stm->execute();
      $msg = '✅ Lorry added.';

    } elseif ($action === 'update') {
      $id     = (int)($_POST['id'] ?? 0);
      $reg    = trim($_POST['vehicle_no'] ?? '');
      $type   = trim($_POST['truck_type'] ?? '');
      $status = trim($_POST['status'] ?? 'Available');
      if ($id<=0) throw new Exception('Invalid lorry id.');
      if ($reg === '' || $type === '') throw new Exception('Vehicle number and truck type are required.');

      $stm = $mysqli->prepare("UPDATE lorry_owners SET vehicle_no=?, truck_type=?, status=? WHERE id=? AND user_id=?");
      $stm->bind_param('sssii', $reg, $type, $status, $id, $user_id);
      $stm->execute();
      if ($stm->affected_rows === 0) throw new Exception('Update failed or not permitted.');
      $msg = '✅ Lorry updated.';

    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new Exception('Invalid lorry id.');
      $stm = $mysqli->prepare("DELETE FROM lorry_owners WHERE id=? AND user_id=?");
      $stm->bind_param('ii', $id, $user_id);
      $stm->execute();
      if ($stm->affected_rows === 0) throw new Exception('Delete failed or not permitted.');
      $msg = '🗑️ Lorry deleted.';
    }
  } catch(Throwable $e) {
    $msg = 'Error: '.$e->getMessage();
  }
}

/* ====== LIST (only this user’s lorries) ====== */
$st = $mysqli->prepare("SELECT id, vehicle_no, truck_type, status FROM lorry_owners WHERE user_id=? ORDER BY vehicle_no");
$st->bind_param('i', $user_id);
$st->execute();
$res = $st->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HaulPro — Lorry List</title>
<link rel="stylesheet" href="dashboad_style.css">
<style>
:root {
  --bg:#f9fafb; --surface:#ffffff; --text:#1f2937; --muted:#6b7280;
  --border:#e5e7eb; --primary:#2563eb; --primary-hover:#1d4ed8;
  --danger:#ef4444; --danger-hover:#dc2626; --secondary:#f3f4f6;
  --radius:10px; --shadow:0 2px 6px rgba(0,0,0,0.08);
}
body{font-family:'Segoe UI',Tahoma,sans-serif;background:var(--bg);margin:0;color:var(--text);}
.container{display:flex;}
.shell{flex:1;max-width:1700px;margin:32px auto;padding:0 16px;}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
h2{font-size:1.7rem;font-weight:700;text-align:center;flex-grow:1;color:var(--primary);}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 16px;border-radius:50px;border:none;font-size:14px;font-weight:500;cursor:pointer;transition:background 0.2s,transform 0.1s;text-decoration:none;}
.btn:hover{transform:translateY(-1px);}
.btn.primary{background:var(--primary);color:#fff;}
.btn.primary:hover{background:var(--primary-hover);}
.btn.danger{background:var(--danger);color:#fff;}
.btn.danger:hover{background:var(--danger-hover);}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:24px;margin-bottom:24px;}
table{width:100%;border-collapse:collapse;font-size:16px;border-radius:var(--radius);overflow:hidden;}
th,td{padding:14px 16px;border-bottom:1px solid var(--border);}
th{background:var(--secondary);font-weight:600;text-align:left;}
tr:nth-child(even) td{background:#fdfdfd;}
tr:hover td{background:#f1f5ff;}
.actions{display:flex;gap:8px;}
.status-badge{display:inline-block;padding:6px 12px;border-radius:20px;font-size:13px;font-weight:600;text-align:center;}
.status-available{background:#dcfce7;color:#166534;}
.status-active{background:#dbeafe;color:#1e40af;}
.status-intransit{background:#ffedd5;color:#9a3412;}
.status-delivered{background:#e5e7eb;color:#374151;}
.status-waiting{background:#ede9fe;color:#5b21b6;}
.status-outofservice{background:#fee2e2;color:#991b1b;}
.status-maintenance{background:#fef9c3;color:#854d0e;}
.msg{margin:10px 0;padding:10px 16px;border-radius:var(--radius);background:#e0f2fe;color:#0369a1;font-weight:500;}
</style>
<script>
function confirmDel(id){
  if(confirm('Delete this lorry?')) document.getElementById('del-'+id).submit();
}
function goRow(url){ window.location.href = url; }
function stopEvt(e){ e.stopPropagation(); }
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
          <li><a href="delivery_performance.php"><img src="Image/continuous-improvement.png" alt="" />Delivery Performance</a></li>
          <li><a href="Revenue_analysis.php"><img src="Image/profit-margin.png" alt="" />Revenue Analysis</a></li>
          <li><a href="fleet_analysis.php"><img src="Image/delivery-truck.png" alt="" />Fleet Efficiency</a></li>
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
      <img src="https://cdn-icons-png.flaticon.com/512/4712/4712002.png" alt="Help"/>
      <p>Need Help?</p>
      <button>Contact Now</button>
    </div>
  </aside>

  <!-- Main content -->
  <div class="shell">
    <div class="topbar"><h2>🚛 Lorry List</h2><span></span></div>
    <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Vehicle No</th>
            <th>Truck Type</th>
            <th>Status</th>
            <th style="width:240px">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if($res && $res->num_rows): while($row=$res->fetch_assoc()): $id=(int)$row['id']; ?>
          <tr class="row-link" onclick="goRow('calculationInput.php?truck_id=<?= $id ?>')">
            <td><?= htmlspecialchars($row['vehicle_no']) ?></td>
            <td><?= htmlspecialchars($row['truck_type']) ?></td>
            <td>
              <?php
                $status = $row['status'];
                $class = '';
                switch($status) {
                  case 'Available': $class='status-available'; break;
                  case 'Active': $class='status-active'; break;
                  case 'In Transit': $class='status-intransit'; break;
                  case 'Delivered': $class='status-delivered'; break;
                  case 'Waiting for Load': $class='status-waiting'; break;
                  case 'Out of Service': $class='status-outofservice'; break;
                  case 'Maintenance': $class='status-maintenance'; break;
                }
                echo "<span class='status-badge $class'>".htmlspecialchars($status)."</span>";
              ?>
            </td>
            <td class="actions" onclick="stopEvt(event)">
              <form id="del-<?= $id ?>" method="post" style="display:inline">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="action" value="delete">
                <button type="button" class="btn danger" onclick="confirmDel(<?= $id ?>)">Delete</button>
              </form>
              <a class="btn primary" href="calculationShow.php?truck_id=<?= $id ?>">📊 Trips</a>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="4">No lorries yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /shell -->

</div><!-- /container -->
</body>
</html>
