<?php
/****************************************************
 * HaulPro — Admin » Client Payments (live refresh)
 * - Clients list (top)
 * - Pending user-submitted payments with Approve/Reject (filter by user)
 * - Per-user dynamic History (summary + all transactions)
 * - Scoped print => prints only the History modal
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
  die("DB connection failed: ".$mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// ---------- Helpers ----------
function jout($x){ header("Content-Type: application/json"); echo json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function fail($m,$c=400){ http_response_code($c); jout(["error"=>$m]); }
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = $db->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");
  return $res && $res->num_rows > 0;
}
function has_col(mysqli $db, string $t, string $c): bool {
  $t = $db->real_escape_string($t);
  $c = $db->real_escape_string($c);
  $sql = "
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{$t}'
      AND COLUMN_NAME = '{$c}'
    LIMIT 1";
  $res = $db->query($sql);
  return $res && $res->num_rows > 0;
}
function ym_span(DateTime $start, DateTime $end, int $max=36): array {
  if ($start > $end) [$start,$end] = [$end,$start];
  $start = new DateTime($start->format('Y-m-01 00:00:00'));
  $end   = new DateTime($end->format('Y-m-01 00:00:00'));
  $out = []; $i=0;
  while ($start <= $end && $i < $max){
    $out[] = [
      'ym'    => $start->format('Y-m'),
      'year'  => (int)$start->format('Y'),
      'month' => $start->format('M'),
      'y'     => (int)$start->format('Y'),
      'm'     => (int)$start->format('n'),
      'start' => $start->format('Y-m-01'),
      'end'   => $start->format('Y-m-t'),
    ];
    $start->modify('+1 month'); $i++;
  }
  return $out;
}
function first_of_month($dateStr){ $d=new DateTime($dateStr??'now'); return new DateTime($d->format('Y-m-01 00:00:00')); }
function now_first(){ return new DateTime(date('Y-m-01 00:00:00')); }

// ---------- Ensure billing_* tables exist (safety) ----------
$mysqli->query("
CREATE TABLE IF NOT EXISTS billing_cycles (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  year SMALLINT NOT NULL,
  month TINYINT NOT NULL,
  amount_due DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_month (user_id, year, month),
  INDEX (user_id, year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$mysqli->query("
CREATE TABLE IF NOT EXISTS billing_payments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  year SMALLINT NOT NULL,
  month TINYINT NOT NULL,
  paid_date DATE NOT NULL,
  method VARCHAR(40) NOT NULL,
  method_ref VARCHAR(64) DEFAULT NULL,
  txn_no VARCHAR(80) NOT NULL,
  amount_bdt DECIMAL(12,2) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT NULL,
  reviewed_at TIMESTAMP NULL,
  review_note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id, year, month),
  INDEX (paid_date),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ---------- SAFE monthly fee lookup ----------
function monthly_fee_for_user(mysqli $db, int $uid): float {
  $DEFAULT = 1200.00;

  try {
    if (has_table($db,'billing_prefs') && has_col($db,'billing_prefs','monthly_fee_bdt')) {
      if ($st = $db->prepare("SELECT monthly_fee_bdt FROM billing_prefs WHERE user_id=? LIMIT 1")) {
        $st->bind_param("i",$uid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row && is_numeric($row['monthly_fee_bdt'])) return (float)$row['monthly_fee_bdt'];
      }
    }
    if (has_table($db,'users') && has_col($db,'users','monthly_fee_bdt')) {
      if ($st = $db->prepare("SELECT monthly_fee_bdt FROM users WHERE id=? LIMIT 1")) {
        $st->bind_param("i",$uid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row && is_numeric($row['monthly_fee_bdt'])) return (float)$row['monthly_fee_bdt'];
      }
    }
  } catch (Throwable $e) {}
  return $DEFAULT;
}

// ---------- AJAX: clients list ----------
if (isset($_GET['ajax']) && $_GET['ajax']==='clients') {
  if (!has_table($mysqli,'users')) jout([]);
  $res = $mysqli->query("
    SELECT id, COALESCE(full_name,'') AS name, COALESCE(email,'') AS email, DATE(created_at) AS created_at
    FROM users
    ORDER BY created_at DESC, id DESC
  ");
  $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
  jout($out);
}

// ---------- AJAX: dynamic history for a user (summary + all transactions) ----------
if (isset($_GET['ajax']) && $_GET['ajax']==='history') {
  $uid = (int)($_GET['user_id'] ?? 0);
  if ($uid<=0) fail("Invalid user_id");

  $fee = monthly_fee_for_user($mysqli, $uid);

  $cli = ["id"=>$uid,"name"=>"","email"=>"","created_at"=>null];
  if (has_table($mysqli,'users')) {
    $c = $mysqli->prepare("SELECT id, COALESCE(full_name,'') AS name, COALESCE(email,'') AS email, DATE(created_at) AS created_at FROM users WHERE id=?");
    $c->bind_param("i",$uid); $c->execute(); $row = $c->get_result()->fetch_assoc();
    if ($row) $cli = $row;
  }

  // Range = first payment date (if any) else user.created_at else now; capped to 36 months
  $firstPay = null;
  if (has_table($mysqli,'billing_payments')) {
    $st = $mysqli->prepare("SELECT MIN(paid_date) AS d FROM billing_payments WHERE user_id=?");
    $st->bind_param("i",$uid); $st->execute();
    $firstPay = $st->get_result()->fetch_column();
  }
  $start = $firstPay ? first_of_month($firstPay) : ($cli['created_at'] ? first_of_month($cli['created_at']) : now_first());
  $end   = now_first();
  $months = ym_span($start, $end, 36);

  // Aggregate map
  $map = [];
  $payments = [];
  if (has_table($mysqli,'billing_payments') && $months) {
    $from = $months[0]['start'];
    $to   = end($months)['end'];
    $sql = "SELECT id, user_id, paid_date, year, month, method, method_ref, txn_no, amount_bdt, status, reviewed_at, review_note
            FROM billing_payments
            WHERE user_id=? AND paid_date BETWEEN ? AND ?
            ORDER BY paid_date DESC, id DESC";
    $st = $mysqli->prepare($sql);
    $st->bind_param("iss",$uid,$from,$to);
    $st->execute();
    $rs = $st->get_result();
    while($r = $rs->fetch_assoc()){
      $payments[] = [
        'id' => (int)$r['id'],
        'paid_date' => $r['paid_date'],
        'ym' => sprintf('%04d-%02d',(int)$r['year'], (int)$r['month']),
        'method' => $r['method'],
        'method_ref' => $r['method_ref'],
        'txn_no' => $r['txn_no'],
        'amount_bdt' => (float)$r['amount_bdt'],
        'status' => $r['status'],
        'reviewed_at' => $r['reviewed_at'],
        'review_note' => $r['review_note'],
      ];
      $key = sprintf('%04d-%02d',(int)$r['year'], (int)$r['month']);
      if (!isset($map[$key])) $map[$key] = [
        'approved_total'=>0.0, 'approved_count'=>0,
        'pending_count'=>0, 'rejected_count'=>0,
        'last_approved_date'=>null, 'last_approved_txn'=>null
      ];
      if ($r['status']==='approved'){
        $map[$key]['approved_total'] += (float)$r['amount_bdt'];
        $map[$key]['approved_count'] += 1;
        if (!$map[$key]['last_approved_date'] || $r['paid_date'] > $map[$key]['last_approved_date']){
          $map[$key]['last_approved_date'] = $r['paid_date'];
          $map[$key]['last_approved_txn']  = $r['txn_no'];
        }
      } elseif ($r['status']==='pending'){
        $map[$key]['pending_count'] += 1;
      } elseif ($r['status']==='rejected'){
        $map[$key]['rejected_count'] += 1;
      }
    }
  }

  // Build summary rows
  $hist = [];
  foreach($months as $m){
    $key = $m['ym'];
    $info = $map[$key] ?? ['approved_total'=>0.0,'approved_count'=>0,'pending_count'=>0,'rejected_count'=>0,'last_approved_date'=>null,'last_approved_txn'=>null];
    $approved = (float)$info['approved_total'];
    $pendingC = (int)$info['pending_count'];

    $status = 'UNPAID';
    if ($approved >= $fee - 0.0001)      $status = 'PAID';
    elseif ($approved > 0)               $status = 'PARTIAL';
    elseif ($pendingC > 0)               $status = 'PENDING';

    $hist[] = [
      "year"   => $m["year"],
      "month"  => $m["month"],
      "ym"     => $m["ym"],
      "status" => $status,
      "fee"    => round($fee,2),
      "approved_total" => round($approved,2),
      "approved_count" => (int)$info['approved_count'],
      "pending_count"  => (int)$info['pending_count'],
      "rejected_count" => (int)$info['rejected_count'],
      "paid_on" => $info['last_approved_date'],
      "txn"     => $info['last_approved_txn'],
    ];
  }

  jout([
    "client"=>$cli,
    "history"=>$hist,
    "payments"=>$payments, // <-- all transactions for that user in range
    "monthly_fee"=>$fee,
    "range"=>["from"=>$months ? $months[0]['ym'] : null, "to"=>$months ? end($months)['ym'] : null]
  ]);
}

// ---------- AJAX: list pending payments (optional filter by user_id) ----------
if (isset($_GET['ajax']) && $_GET['ajax']==='pending') {
  $uid = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;

  if ($uid) {
    $sql = "
      SELECT p.id, p.user_id,
             COALESCE(u.full_name,'') AS user_name,
             COALESCE(u.email,'') AS user_email,
             p.year, p.month, p.paid_date, p.method, p.method_ref, p.txn_no,
             p.amount_bdt, p.status, p.created_at
      FROM billing_payments p
      LEFT JOIN users u ON u.id = p.user_id
      WHERE p.status='pending' AND p.user_id=?
      ORDER BY p.created_at ASC, p.id ASC";
    $st = $mysqli->prepare($sql);
    $st->bind_param("i",$uid);
    $st->execute();
    $res = $st->get_result();
  } else {
    $res = $mysqli->query("
      SELECT p.id, p.user_id,
             COALESCE(u.full_name,'') AS user_name,
             COALESCE(u.email,'') AS user_email,
             p.year, p.month, p.paid_date, p.method, p.method_ref, p.txn_no,
             p.amount_bdt, p.status, p.created_at
      FROM billing_payments p
      LEFT JOIN users u ON u.id = p.user_id
      WHERE p.status='pending'
      ORDER BY p.created_at ASC, p.id ASC
    ");
  }

  $out=[];
  while($r=$res->fetch_assoc()){
    $r['amount_bdt'] = (float)$r['amount_bdt'];
    $out[]=$r;
  }
  jout($out);
}

// ---------- AJAX: approve / reject ----------
if (isset($_GET['ajax']) && $_GET['ajax']==='decide' && $_SERVER['REQUEST_METHOD']==='POST') {
  $pid      = (int)($_POST['payment_id'] ?? 0);
  $decision = trim($_POST['decision'] ?? '');
  $note     = trim($_POST['note'] ?? '');

  if (!$pid || !in_array($decision,['approve','reject'],true)) fail("Invalid input");

  $admin_id = 0; // replace with real admin id if you have auth

  $mysqli->begin_transaction();
  try {
    $pRes = $mysqli->query("SELECT * FROM billing_payments WHERE id={$pid} FOR UPDATE");
    $p = $pRes ? $pRes->fetch_assoc() : null;
    if (!$p) throw new Exception("Payment not found");
    if ($p['status'] !== 'pending') throw new Exception("Already reviewed");

    if ($decision === 'approve') {
      $stmt=$mysqli->prepare("
        UPDATE billing_payments
        SET status='approved', reviewed_by=?, reviewed_at=NOW(), review_note=?
        WHERE id=?
      ");
      $stmt->bind_param("isi",$admin_id,$note,$pid);
      $stmt->execute();

      $amt=(float)$p['amount_bdt']; $uid=(int)$p['user_id']; $yr=(int)$p['year']; $mo=(int)$p['month'];
      $mysqli->query("
        INSERT INTO billing_cycles(user_id,year,month,amount_due,amount_paid)
        VALUES ($uid,$yr,$mo,0,0)
        ON DUPLICATE KEY UPDATE amount_due=amount_due
      ");
      $up=$mysqli->prepare("UPDATE billing_cycles SET amount_paid = amount_paid + ? WHERE user_id=? AND year=? AND month=?");
      $up->bind_param("diii",$amt,$uid,$yr,$mo);
      $up->execute();

    } else {
      $stmt=$mysqli->prepare("
        UPDATE billing_payments
        SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_note=?
        WHERE id=?
      ");
      $stmt->bind_param("isi",$admin_id,$note,$pid);
      $stmt->execute();
    }

    $mysqli->commit();
    jout(["ok"=>1,"payment_id"=>$pid,"decision"=>$decision]);
  } catch(Throwable $e){
    $mysqli->rollback();
    fail("Review failed: ".$e->getMessage(), 500);
  }
}

// ---------- Standard page ----------
$current = basename($_SERVER['PHP_SELF']);
function navActive($f){ global $current; return $current===$f?'active':''; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Payments — HaulPro</title>
<link rel="stylesheet" href="dashboad_style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --primary:#2563eb; --primary-hover:#1d4ed8;
  --bg:#f6f8fc; --surface:#ffffff; --border:#e6e8ef; --text:#0f172a;
  --muted:#64748b; --subtle:#334155;
  --radius:14px; --shadow:0 10px 26px rgba(15,23,42,.06);
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,"Segoe UI",system-ui,-apple-system,sans-serif}
.layout{display:flex;min-height:100vh}

.sidebar{width:260px;background:var(--surface);border-right:1px solid var(--border);
  padding:18px;display:flex;flex-direction:column;gap:10px;position:sticky;top:0;height:100vh}
.sidebar img[alt="HaulPro Logo"]{width:160px;height:auto;margin-top:6px}
.sidebar h3{margin:6px 0 10px;font-size:20px;color:#3c4b64;font-weight:800}
.sidebar .menu{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-direction:column;gap:6px}
.sidebar .menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;text-decoration:none;
  color:#0f172a;font-weight:600;border:1px solid transparent}
.sidebar .menu a img{width:40px;object-fit:contain}
.sidebar .menu a:hover{background:#f3f4f6;border-color:var(--border)}
.sidebar .menu a.active{ background:#e5e7eb; color:#0f172a; border-color:#e5e7eb; box-shadow:none }

.help-card{ margin-top:auto;padding:14px;border:1px dashed var(--border);border-radius:12px;background:#fafcff;text-align:center;color:#334155 }
.help-card img{width:60px;height:60px;margin-bottom:8px}
.help-card p{margin:0 0 8px;font-weight:700}
.help-card button{background:#ffc107;border:none;padding:8px 14px;font-weight:800;color:#000;border-radius:8px;cursor:pointer}

.content{flex:1; padding:28px; max-width:1600px; margin:0 auto}
h4{margin:0 0 14px; font-size:24px; font-weight:800; color:#3c4b64}
.card{background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow)}
.table thead th{ background:#f8fafc; color:#334155; font-weight:800; border-bottom:1px solid var(--border) }
.table tbody td{ border-bottom:1px solid var(--border) }
.table-hover tbody tr:hover{ background:#f3f4f6 }

.badge-paid{ background:#e7fff2; color:#0f7a43; border:1px solid #bff0d1; font-weight:700 }
.badge-unpaid{ background:#fff3f0; color:#c3422f; border:1px solid #ffd7cf; font-weight:700 }
.badge-pending{ background:#fff7e6; color:#9a6700; border:1px solid #ffe1aa; font-weight:700 }
.badge-rejected{ background:#f5f5f5; color:#6b7280; border:1px solid #e5e7eb; font-weight:700 }
.badge-partial{ background:#e9f7ff; color:#0369a1; border:1px solid #cfefff; font-weight:700 }

/* ===== Print only the history modal ===== */
@media print {
  body * { visibility: hidden !important; }
  #historyModal, #historyModal * { visibility: visible !important; }
  #historyModal { position: static !important; }
  #historyModal .modal-dialog { max-width: 100% !important; margin: 0 !important; }
  #historyModal .modal-content { border: none !important; box-shadow: none !important; }
  #historyModal .modal-header .btn-close,
  #historyModal .modal-footer,
  .sidebar,
  .content > *:not(#historyModal) { display: none !important; }
}
</style>
</head>
<body>
<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <img src="Image/Logo.png" alt="HaulPro Logo" />
    <h3>HaulPro</h3>
    <ul class="menu">
      <li><a class="<?= navActive('adminDashboard.php') ?>" href="adminDashboard.php"><img src="Image/dashboard.png" alt=""/>Dashboard</a></li>
      <li><a class="<?= navActive('adminShowClients.php') ?>" href="adminShowClients.php"><img src="Image/magnifying-glass.png" alt=""/>Show All Clients</a></li>
      <li><a class="<?= navActive('adminManageVehicles.php') ?>" href="adminManageVehicles.php"><img src="Image/car1.png" alt=""/>Manage Vehicles</a></li>
      <li><a class="<?= navActive('adminPayment.php') ?>" href="adminPayment.php"><img src="Image/wallet.png" alt=""/>Payments</a></li>
      <li><a class="<?= navActive('adminSettings.php') ?>" href="adminSettings.php"><img src="Image/settings.png" alt=""/>Settings</a></li>
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
    <h4>Client Payments</h4>

    <!-- Clients list -->
    <div class="card p-3 mb-4">
      <div class="table-responsive">
        <table class="table table-hover align-middle" id="clientsTable">
          <thead>
            <tr>
              <th style="min-width:240px">Client</th>
              <th>Email</th>
              <th>Joined</th>
              <th class="text-end" style="min-width:120px">Actions</th>
            </tr>
          </thead>
          <tbody><!-- filled by JS --></tbody>
        </table>
      </div>
      <div class="small text-secondary">Click a row or the <strong>History</strong> button to view that client’s dynamic history.</div>
    </div>

    <!-- Pending user-submitted payments -->
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="m-0 fw-bold">User-Submitted Payments (Pending Review)</h5>
        <div class="d-flex align-items-center gap-2">
          <select id="filterUser" class="form-select form-select-sm" style="min-width:240px">
            <option value="">All users</option>
          </select>
          <button class="btn btn-outline-secondary btn-sm" id="applyFilter">Apply</button>
          <button class="btn btn-link btn-sm" id="clearFilter">Clear</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle" id="pendingTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Month</th>
              <th>Date</th>
              <th>Amount</th>
              <th>Method</th>
              <th>TXN</th>
              <th>Status</th>
              <th class="text-end" style="min-width:180px">Action</th>
            </tr>
          </thead>
          <tbody><!-- JS --></tbody>
        </table>
      </div>
      <div class="small text-secondary">Approving adds the amount to the cycle and marks it Approved. Rejecting marks it Rejected.</div>
    </div>

  </main>
</div>

<!-- History Modal (SUMMARY + ALL TRANSACTIONS) -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold">Payment History</h5>
          <div class="text-secondary small" id="modalClientInfo"></div>
          <div class="text-secondary small" id="modalRangeInfo"></div>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
      </div>

      <div class="modal-body">

        <!-- Summary table -->
        <div class="p-3 border rounded mb-4">
          <div class="d-flex justify-content-between mb-3">
            <div class="fw-bold">HaulPro — Monthly Summary</div>
            <div class="text-secondary small">Generated: <?= date('d M Y, h:i A') ?></div>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered align-middle" id="historyTable">
              <thead class="table-light">
                <tr>
                  <th>Month</th>
                  <th>Status</th>
                  <th>Monthly Fee</th>
                  <th>Approved Total</th>
                  <th>Paid On</th>
                  <th>Last TXN</th>
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot>
                <tr>
                  <th colspan="3" class="text-end">Total Approved (range):</th>
                  <th id="totalPaidCell">0 ৳</th>
                  <th colspan="2"></th>
                </tr>
              </tfoot>
            </table>
          </div>
          <div class="mt-2 small text-secondary">
            Status uses <strong>approved</strong> payments only. PARTIAL = approved amount &lt; monthly fee. If there are submitted but not yet approved rows, month shows PENDING.
          </div>
        </div>

        <!-- All transactions -->
        <div class="p-3 border rounded">
          <div class="fw-bold mb-3">All Transactions (this range)</div>
          <div class="table-responsive">
            <table class="table table-bordered align-middle" id="txTable">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Date</th>
                  <th>Month</th>
                  <th>Amount</th>
                  <th>Method</th>
                  <th>TXN</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" onclick="window.print()">Print</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ---------- Utils ----------
let REFRESH_LOCK = { clients:false, pending:false };
let clientsCache = [];
let PENDING_UID = ""; // current filter ('' = all)
const moneyBDT = n => new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n) + ' ৳';
const toast = (m='Saved.') => { const t=document.getElementById('toast'); if(!t){const d=document.createElement('div'); d.id='toast'; d.className='toast position-fixed bottom-0 end-0 m-3 p-2 bg-dark text-white rounded'; d.style.zIndex=2000; document.body.appendChild(d);} const el=document.getElementById('toast'); el.textContent=m; el.style.opacity=1; setTimeout(()=>el.style.opacity=0,1400); };
function escapeHtml(s){return String(s==null?'':s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#39;");}
function guarded(name, fn){ return async (...args)=>{ if(REFRESH_LOCK[name]) return; REFRESH_LOCK[name]=true; try{ await fn(...args);} finally{REFRESH_LOCK[name]=false;} }; }

// ---------- Clients (top) ----------
async function _loadClients(){
  const r = await fetch('?ajax=clients');
  const list = await r.json();
  clientsCache = Array.isArray(list) ? list : [];
  const tb = document.querySelector('#clientsTable tbody');
  tb.innerHTML = '';

  if(clientsCache.length===0){
    tb.innerHTML = `<tr><td colspan="4" class="text-center text-secondary py-3">No users found.</td></tr>`;
  } else {
    clientsCache.forEach(u=>{
      const tr = document.createElement('tr');
      tr.dataset.userId = u.id;
      tr.innerHTML = `
        <td><strong>${escapeHtml(u.name||'(No name)')}</strong></td>
        <td>${escapeHtml(u.email||'')}</td>
        <td>${escapeHtml(u.created_at||'')}</td>
        <td class="text-end">
          <button type="button" class="btn btn-outline-secondary btn-sm history-btn" data-user-id="${u.id}">History</button>
        </td>`;
      tb.appendChild(tr);
    });
  }

  // Fill filter dropdown
  const sel = document.getElementById('filterUser');
  const cur = sel.value;
  sel.innerHTML = `<option value="">All users</option>` + clientsCache.map(u=>`<option value="${u.id}">${escapeHtml(u.name||'(User #'+u.id+')')} — #${u.id}</option>`).join('');
  if (cur) sel.value = cur;
}
const loadClients = guarded('clients', _loadClients);

// ---------- History (per user) ----------
const modal = new bootstrap.Modal(document.getElementById('historyModal'));

async function openHistory(userId){
  const r = await fetch(`?ajax=history&user_id=${encodeURIComponent(userId)}`);
  if(!r.ok){ toast('Failed to fetch history'); return; }
  let j; try{ j = await r.json(); } catch(e){ toast('History parse error'); return; }

  const client = j.client || {};
  const fee    = Number(j.monthly_fee || 0);

  document.getElementById('modalClientInfo').textContent =
    `${client.name||'(No name)'} • ${client.email||''} • joined ${client.created_at||''}`;

  const range = j.range || {};
  document.getElementById('modalRangeInfo').textContent =
    (range.from && range.to) ? `Range: ${range.from} → ${range.to}` : '';

  // Summary rows
  const tb = document.querySelector('#historyTable tbody');
  tb.innerHTML = '';
  let total = 0;
  (j.history||[]).forEach(row=>{
    total += Number(row.approved_total||0);
    const badge = (st=>{
      if (st==='PAID') return '<span class="badge rounded-pill px-3 badge-paid">PAID</span>';
      if (st==='PARTIAL') return '<span class="badge rounded-pill px-3 badge-partial">PARTIAL</span>';
      if (st==='PENDING') return '<span class="badge rounded-pill px-3 badge-pending">PENDING</span>';
      return '<span class="badge rounded-pill px-3 badge-unpaid">UNPAID</span>';
    })(row.status);

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${row.month} ${row.year}</td>
      <td>${badge}</td>
      <td>${moneyBDT(fee)}</td>
      <td>${moneyBDT(row.approved_total||0)}</td>
      <td>${escapeHtml(row.paid_on||'')}</td>
      <td>${escapeHtml(row.txn||'')}</td>`;
    tb.appendChild(tr);
  });
  document.getElementById('totalPaidCell').textContent = moneyBDT(total);

  // Transactions table
  const txb = document.querySelector('#txTable tbody');
  txb.innerHTML = '';
  (j.payments||[]).forEach(p=>{
    const ym = p.ym || '';
    const badge = p.status==='approved' ? 'badge-paid'
               : p.status==='pending'  ? 'badge-pending'
               : 'badge-rejected';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${p.id}</td>
      <td>${escapeHtml(p.paid_date||'')}</td>
      <td>${ym}</td>
      <td>${moneyBDT(p.amount_bdt||0)}</td>
      <td>${escapeHtml(p.method||'')}${p.method_ref?(' ('+escapeHtml(p.method_ref)+')'):''}</td>
      <td>${escapeHtml(p.txn_no||'')}</td>
      <td><span class="badge rounded-pill px-3 ${badge}">${escapeHtml(p.status)}</span></td>`;
    txb.appendChild(tr);
  });

  modal.show();
}

// Click handlers: History button + row click
document.addEventListener('click', e=>{
  const btn = e.target.closest('.history-btn');
  if (btn){ openHistory(btn.dataset.userId); return; }
  const row = e.target.closest('#clientsTable tbody tr');
  if (row && row.dataset.userId){ openHistory(row.dataset.userId); }
});

// ---------- Pending payments (with user filter) ----------
async function _loadPending(){
  const url = PENDING_UID ? `?ajax=pending&user_id=${encodeURIComponent(PENDING_UID)}` : `?ajax=pending`;
  const r = await fetch(url);
  const list = await r.json();
  const tb = document.querySelector('#pendingTable tbody');
  tb.innerHTML = '';

  if(!Array.isArray(list) || list.length===0){
    tb.innerHTML = `<tr><td colspan="9" class="text-center text-secondary py-3">No pending payments.</td></tr>`;
    return;
  }

  list.forEach(p=>{
    const ym = `${p.year}-${String(p.month).padStart(2,'0')}`;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${p.id}</td>
      <td>${escapeHtml(p.user_name||'(User #'+p.user_id+')')}<div class="small text-secondary">#${p.user_id} • ${escapeHtml(p.user_email||'')}</div></td>
      <td>${ym}</td>
      <td>${escapeHtml(p.paid_date||'')}</td>
      <td>${moneyBDT(p.amount_bdt||0)}</td>
      <td>${escapeHtml(p.method||'')}${p.method_ref?(' ('+escapeHtml(p.method_ref)+')'):''}</td>
      <td>${escapeHtml(p.txn_no||'')}</td>
      <td><span class="badge rounded-pill px-3 badge-pending">pending</span></td>
      <td class="text-end">
        <div class="btn-group">
          <button type="button" class="btn btn-sm btn-primary approve-btn" data-id="${p.id}">Approve</button>
          <button type="button" class="btn btn-sm btn-outline-secondary reject-btn" data-id="${p.id}">Reject</button>
        </div>
      </td>`;
    tb.appendChild(tr);
  });
}
const loadPending = guarded('pending', _loadPending);

// Approve/Reject handlers
async function decidePayment(payment_id, decision){
  const btnSel = `#pendingTable tbody .approve-btn[data-id="${payment_id}"]`;
  const row = document.querySelector(btnSel)?.closest('tr') || null;
  if (row){ row.querySelectorAll('button').forEach(b=> b.disabled = true); row.style.opacity=.5; }

  const note = (decision==='reject') ? (prompt('Reason (optional):','')||'') : '';
  try{
    const fd = new URLSearchParams({payment_id, decision, note});
    const r = await fetch('?ajax=decide', {method:'POST', body:fd});
    const j = await r.json();
    if (j.error) throw new Error(j.error);
    await loadPending();
    await loadClients();
    toast(`Payment #${payment_id} ${decision}d`);
  }catch(err){
    alert(err.message || 'Network error');
    if (row){ row.querySelectorAll('button').forEach(b=> b.disabled = false); row.style.opacity=1; }
  }
}
document.addEventListener('click', e=>{
  const ap = e.target.closest('.approve-btn');
  if (ap){ decidePayment(ap.dataset.id, 'approve'); return; }
  const rj = e.target.closest('.reject-btn');
  if (rj){ decidePayment(rj.dataset.id, 'reject'); return; }
});

// Filter controls
document.getElementById('applyFilter').addEventListener('click', ()=>{
  PENDING_UID = document.getElementById('filterUser').value || "";
  loadPending();
});
document.getElementById('clearFilter').addEventListener('click', ()=>{
  document.getElementById('filterUser').value = "";
  PENDING_UID = "";
  loadPending();
});

// Boot
(async function init(){
  await loadClients();
  await loadPending();
})();
</script>
</body>
</html>
