<?php


require __DIR__.'/db.php';
require __DIR__.'/auth.php';
require_login();
$user_id = (int) current_user_id();

/* ---------- DB CONNECT (if not already) ---------- */
$mysqli->set_charset("utf8mb4");

/* ---------- TABLES (auto create) ---------- */
$mysqli->query("
CREATE TABLE IF NOT EXISTS billing_cycles (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  year SMALLINT NOT NULL,
  month TINYINT NOT NULL,     -- 1..12
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
  method VARCHAR(40) NOT NULL,          -- bkash/nagad/bank/cash/etc.
  method_ref VARCHAR(64) DEFAULT NULL,  -- number or bank name/acc
  txn_no VARCHAR(80) NOT NULL,          -- user-entered transaction id/reference
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

$mysqli->query("
CREATE TABLE IF NOT EXISTS billing_prefs (
  user_id BIGINT PRIMARY KEY,
  currency VARCHAR(8) DEFAULT 'BDT',
  email VARCHAR(160) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ---------- HELPERS ---------- */
function jout($x){ header("Content-Type: application/json"); echo json_encode($x); exit; }
function fail($m,$c=400){ http_response_code($c); jout(["error"=>$m]); }
function first_row($res){ return $res ? $res->fetch_assoc() : null; }
function ym_now(){ $y=(int)date('Y'); $m=(int)date('n'); return [$y,$m]; }

/* ---------- DEMO SEED (adds ONE older due month only) ---------- */
function ensure_cycle(mysqli $db,int $uid,int $y,int $m,float $amt){
  $q=$db->prepare("INSERT INTO billing_cycles(user_id,year,month,amount_due,amount_paid)
                   VALUES(?,?,?,?,0)
                   ON DUPLICATE KEY UPDATE amount_due=VALUES(amount_due)");
  $q->bind_param("iiid",$uid,$y,$m,$amt);
  $q->execute();
}

/* Each call:
   - Finds the oldest existing month for this user in billing_cycles
   - Inserts ONE more older month with due=1200, paid=0
   - If no months exist, start from current month
*/
function seed_for_user(mysqli $db,int $uid){
  $demoDue = 1200.00;

  $row = first_row($db->query("
    SELECT MIN(CONCAT(year,'-',LPAD(month,2,'0'))) AS oldest
    FROM billing_cycles
    WHERE user_id={$uid}
  "));

  if ($row && $row['oldest']) {
    $d = DateTime::createFromFormat('Y-m', $row['oldest']);
    $d->modify('-1 month'); // push one more older month
  } else {
    // nothing yet — start at current month
    $d = new DateTime('first day of this month');
  }

  $y = (int)$d->format('Y');
  $m = (int)$d->format('n');

  // Insert month with due; no payment rows created
  $stmt=$db->prepare("INSERT IGNORE INTO billing_cycles(user_id,year,month,amount_due,amount_paid)
                      VALUES(?,?,?,?,0)");
  $stmt->bind_param("iiid",$uid,$y,$m,$demoDue);
  $stmt->execute();

  return ['year'=>$y,'month'=>$m,'label'=>$d->format('M Y')];
}

/* ---------- JSON ENDPOINTS ---------- */
if (isset($_GET['ajax'])) {
  $ajax = $_GET['ajax'];

  // Summary
  if ($ajax === 'summary') {
    [$cy,$cm] = ym_now();

    $cur = first_row($mysqli->query("
      SELECT amount_due, amount_paid, (amount_due - amount_paid) AS remaining
      FROM billing_cycles
      WHERE user_id={$user_id} AND year={$cy} AND month={$cm}
      LIMIT 1
    ")) ?: ["amount_due"=>0,"amount_paid"=>0,"remaining"=>0];

    $ov = first_row($mysqli->query("
      SELECT
        COUNT(*) AS over_count,
        COALESCE(SUM(amount_due - amount_paid),0) AS over_amount
      FROM billing_cycles
      WHERE user_id={$user_id}
        AND (year < {$cy} OR (year = {$cy} AND month < {$cm}))
        AND (amount_due - amount_paid) > 0
    ")) ?: ["over_count"=>0,"over_amount"=>0];

    $oldest = first_row($mysqli->query("
      SELECT year, month
      FROM billing_cycles
      WHERE user_id={$user_id}
        AND (year < {$cy} OR (year = {$cy} AND month < {$cm}))
        AND (amount_due - amount_paid) > 0
      ORDER BY year, month
      LIMIT 1
    "));
    $oldest_label = $oldest ? date('M Y', strtotime($oldest['year'].'-'.$oldest['month'].'-01')) : null;

    $prefs = first_row($mysqli->query("SELECT currency,email FROM billing_prefs WHERE user_id={$user_id}"))
          ?: ["currency"=>"BDT","email"=>null];

    jout([
      "current" => [
        "year" => $cy, "month" => $cm,
        "label" => date('M Y'),
        "due" => (float)$cur["amount_due"],
        "paid" => (float)$cur["amount_paid"],
        "remaining" => max(0,(float)$cur["remaining"]),
      ],
      "overdue" => [
        "count" => (int)$ov["over_count"],
        "amount" => (float)$ov["over_amount"],
        "oldest_label" => $oldest_label
      ],
      "prefs" => $prefs
    ]);
  }

  // Months list
  if ($ajax === 'months') {
    [$cy,$cm] = ym_now();
    $rows = $mysqli->query("
      SELECT year, month, amount_due, amount_paid, (amount_due - amount_paid) AS remaining
      FROM billing_cycles
      WHERE user_id={$user_id}
      ORDER BY year DESC, month DESC
    ");
    $out = [];
    while($r=$rows->fetch_assoc()){
      $rem = max(0,(float)$r['remaining']);
      $isCurrent = ((int)$r['year']===$cy && (int)$r['month']===$cm);
      $status = $rem<=0 ? 'Cleared' : ($isCurrent ? 'Current' : 'Overdue');
      $out[] = [
        "year"=>(int)$r['year'],
        "month"=>(int)$r['month'],
        "label"=>date('M Y', strtotime($r['year'].'-'.$r['month'].'-01')),
        "due"=>(float)$r['amount_due'],
        "paid"=>(float)$r['amount_paid'],
        "remaining"=>$rem,
        "status"=>$status
      ];
    }
    jout($out);
  }

  // Activity (shows real status)
  if ($ajax === 'tx') {
    $rows = $mysqli->query("
      SELECT id, paid_date, method, method_ref, txn_no, amount_bdt, year, month, status
      FROM billing_payments
      WHERE user_id={$user_id}
      ORDER BY paid_date DESC, id DESC
    ");
    $out=[];
    while($r=$rows->fetch_assoc()){
      $out[]=[
        "t"=>$r["paid_date"],
        "act"=>"bill.pay ".date('M Y', strtotime($r['year'].'-'.$r['month'].'-01')),
        "amt"=>number_format((float)$r["amount_bdt"],2,'.',''),
        "status"=>$r["status"], // pending/approved/rejected
        "method"=>$r["method"].($r["method_ref"]?(" (" . $r["method_ref"] . ")") : ""),
        "txn"=>$r["txn_no"],
      ];
    }
    jout($out);
  }

  // Make a payment (insert PENDING only)
  if ($ajax === 'pay' && $_SERVER['REQUEST_METHOD']==='POST') {
    $year  = (int)($_POST['year'] ?? 0);
    $month = (int)($_POST['month'] ?? 0);
    $amount= (float)($_POST['amount'] ?? 0);
    $method= trim($_POST['method'] ?? '');
    $mref  = trim($_POST['method_ref'] ?? '');
    $txn   = trim($_POST['txn_no'] ?? '');

    if ($year<2000 || $month<1 || $month>12) fail("Invalid billing month");
    if ($amount<=0) fail("Invalid amount");
    if ($method==='') fail("Select a payment method");
    if ($txn==='') fail("Transaction number is required");

    // Ensure cycle exists
    $cyc = first_row($mysqli->query("SELECT amount_due, amount_paid FROM billing_cycles WHERE user_id={$user_id} AND year={$year} AND month={$month}"));
    if (!$cyc) fail("No bill found for the selected month");

    $remaining = max(0, (float)$cyc['amount_due'] - (float)$cyc['amount_paid']);
    if ($remaining<=0) fail("This month is already cleared");
    if ($amount > $remaining) $amount = $remaining;

    $pd = date('Y-m-d');
    $ins = $mysqli->prepare("INSERT INTO billing_payments(user_id,year,month,paid_date,method,method_ref,txn_no,amount_bdt,status)
                             VALUES(?,?,?,?,?,?,?,?, 'pending')");
    $ins->bind_param("iiissssd",$user_id,$year,$month,$pd,$method,$mref,$txn,$amount);
    $ins->execute();

    jout(["ok"=>1,"submitted"=>$amount,"status"=>"pending","msg"=>"Payment submitted for admin approval"]);
  }

  // Save preferences
  if ($ajax === 'savePrefs' && $_SERVER['REQUEST_METHOD']==='POST') {
    $cur = in_array($_POST['currency'] ?? 'BDT',['BDT','USD']) ? $_POST['currency'] : 'BDT';
    $email = trim($_POST['email'] ?? '') ?: null;
    $row = first_row($mysqli->query("SELECT user_id FROM billing_prefs WHERE user_id={$user_id}"));
    if ($row) {
      $q=$mysqli->prepare("UPDATE billing_prefs SET currency=?, email=? WHERE user_id=?");
      $q->bind_param("ssi",$cur,$email,$user_id);
    } else {
      $q=$mysqli->prepare("INSERT INTO billing_prefs (currency,email,user_id) VALUES (?,?,?)");
      $q->bind_param("ssi",$cur,$email,$user_id);
    }
    $q->execute(); jout(["ok"=>1]);
  }

  // Seed demo: add ONE older due month (scroll shows it in Monthly Dues)
  if ($ajax === 'seedDemo') {
    $added = seed_for_user($mysqli,$user_id);
    jout(["ok"=>1,"added"=>$added]);
  }

  // CSV export
  if ($ajax === 'exportTx') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payment_activity.csv"');
    $rows = $mysqli->query("
      SELECT paid_date, method, method_ref, txn_no, amount_bdt, year, month, status
      FROM billing_payments
      WHERE user_id={$user_id}
      ORDER BY paid_date DESC, id DESC
    ");
    echo "date,method,method_ref,txn_no,amount,month,status\n";
    while($r=$rows->fetch_assoc()){
      $m = date('M Y', strtotime($r['year'].'-'.$r['month'].'-01'));
      $line = [
        $r['paid_date'],
        str_replace('\"','\"\"',$r['method']),
        str_replace('\"','\"\"', (string)$r['method_ref']),
        str_replace('\"','\"\"',$r['txn_no']),
        number_format((float)$r['amount_bdt'],2,'.',''),
        $m,
        $r['status']
      ];
      echo '"'.implode('","',$line).'"'."\n";
    }
    exit;
  }

  fail("Unknown ajax endpoint",404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HaulPro — Payment Center</title>
  <link rel="stylesheet" href="dashboad_style.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --primary:#2563eb;--primary-hover:#1d4ed8;--bg:#f6f8fc;--surface:#fff;--border:#e6e8ef;--text:#0f172a;
      --muted:#64748b;--subtle:#334155;--radius:14px;--shadow:0 6px 18px rgba(0,0,0,.06);
      --ok:#16a34a;--warn:#f59e0b;--bad:#ef4444;--chip:#eef2ff;--chiptext:#3730a3
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter,"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text)}
    .container{display:flex;min-height:100vh}
    main{flex:1;padding:24px;max-width:1700px;margin:0 auto}
    .header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
    .header h1{margin:0;font-size:26px;color:var(--primary)}
    .btn-row{display:flex;gap:10px;flex-wrap:wrap}
    .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px}
    .grid{display:grid;gap:14px}
    .cols-3{grid-template-columns:repeat(3,minmax(0,1fr))} .cols-2{grid-template-columns:1fr 1fr}
    @media(max-width:1000px){.cols-3{grid-template-columns:1fr} .cols-2{grid-template-columns:1fr}}
    .stat{display:flex;align-items:center;justify-content:space-between}
    .big{font-size:28px;font-weight:800}
    .chip{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;background:var(--chip);color:var(--chiptext)}
    .btn{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer}
    .btn:hover{background:var(--primary-hover)} .btn.secondary{background:#f1f5f9;color:#0f172a;border:1px solid var(--border)} .btn.danger{background:var(--bad)}
    label{display:block;font-weight:700;margin:6px 0 4px}
    input,select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:#fff}
    .help{font-size:12px;color:var(--muted)}

    /* -------- Monthly Dues table + scroll -------- */
    #monthsWrap{max-height: 300px; overflow-y: auto; border:1px solid var(--border); border-radius:12px}
    table{width:100%;border-collapse:separate;border-spacing:0;background:#fff}
    thead th{background:#f8fafc;font-weight:700;color:#334155;position:sticky;top:0}
    th,td{padding:10px 12px;border-bottom:1px solid var(--border);font-size:14px;text-align:left}
    tbody tr:nth-child(odd){background:#fcfdff} tbody tr:hover{background:#f9fbff}

    /* Status pills */
    .tag{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;background:#eef2ff;color:#3730a3}
    .tag.green{background:#ecfdf5;color:#065f46}     /* Cleared */
    .tag.yellow{background:#fffbeb;color:#92400e}    /* Current */
    .tag.red{background:#fee2e2;color:#b91c1c}       /* Overdue */
    .tag.gray{background:#f3f4f6;color:#374151}

    .empty{padding:18px;border:1px dashed var(--border);border-radius:12px;background:#fff;color:#475569}
    .toast{position:fixed;right:24px;bottom:24px;padding:12px 16px;background:#111827;color:#fff;border-radius:10px;box-shadow:var(--shadow);opacity:0;transform:translateY(10px);transition:.2s}
    .toast.show{opacity:1;transform:translateY(0)}
  </style>
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
      <li><a href="#" class="active"><img src="Image/wallet.png" alt="" style="width:40px" />Payment Center</a></li>
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

  <!-- Main -->
  <main>
    <div class="header">
      <h1>💳 Payment Center</h1>
      <div class="btn-row">
        <button class="btn secondary" id="seedDemoBtn">🌱 Seed Demo Months</button>
        <button class="btn secondary" id="exportTx">⬇️ Export CSV</button>
      </div>
    </div>

    <!-- Summary -->
    <div class="grid cols-3" id="summary">
      <div class="card stat">
        <div>
          <div class="chip" id="cur_label">Current — —</div>
          <div class="big" id="cur_remaining">৳0.00</div>
          <div class="help">Current month due (remaining)</div>
        </div>
        <div>
          <div><span class="chip" id="cur_due">Due: ৳0.00</span></div>
          <div style="margin-top:6px"><span class="chip" id="cur_paid">Paid: ৳0.00</span></div>
        </div>
      </div>
      <div class="card stat">
        <div>
          <div class="chip">Overdue</div>
          <div class="big" id="ov_amount">৳0.00</div>
          <div class="help">Older months not fully paid</div>
        </div>
        <div>
          <div><span class="chip" id="ov_count">0 months</span></div>
          <div style="margin-top:6px"><span class="chip" id="ov_oldest">Oldest: —</span></div>
        </div>
      </div>
      <div class="card">
        <h3>Preferences</h3>
        <div class="grid cols-2" style="align-items:end">
          <div><label>Currency</label><select id="p_currency"><option>BDT</option><option>USD</option></select></div>
          <div><label>Receipt Email</label><input id="p_email" type="email" placeholder="billing@company.com"/></div>
        </div>
        <div class="btn-row" style="margin-top:8px"><button class="btn" id="savePrefs">💾 Save</button></div>
      </div>
    </div>

    <!-- Months & Pay -->
    <div class="grid cols-2" style="margin-top:14px">
      <div class="card">
        <h2>Monthly Dues</h2>
        <div id="monthsEmpty" class="empty" style="display:none">No months yet. Use “Seed Demo Months”.</div>

        <!-- Scroll wrapper -->
        <div id="monthsWrap">
          <table id="monthsTable">
            <thead><tr><th>Month</th><th>Due</th><th>Paid</th><th>Remaining</th><th>Status</th><th style="width:160px">Action</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <h2>Make a Payment</h2>
        <label>Month</label>
        <select id="pay_month"></select>
        <div class="help" id="pay_month_help">Select a month with remaining balance</div>

        <label style="margin-top:8px">Amount (BDT)</label>
        <input id="pay_amount" inputmode="decimal" placeholder="e.g., 1200.00"/>

        <label style="margin-top:8px">Method</label>
        <select id="pay_method">
          <option value="">— Select —</option>
          <option value="bkash">bKash</option>
          <option value="nagad">Nagad</option>
          <option value="rocket">Rocket</option>
          <option value="bank">Bank</option>
          <option value="cash">Cash</option>
          <option value="other">Other</option>
        </select>

        <label style="margin-top:8px">Method Number / Account</label>
        <input id="pay_method_ref" placeholder="+8801XXXXXXX / Bank & A/C"/>

        <label style="margin-top:8px">Transaction Number</label>
        <input id="pay_txn" placeholder="e.g., bKash TXN #"/>

        <div class="btn-row" style="margin-top:10px">
          <button class="btn" id="btnPay" disabled>💸 Submit Payment</button>
        </div>
        <div class="help" style="margin-top:8px">Your payment will show as <b>Pending</b> until an admin approves it.</div>
      </div>
    </div>

    <!-- Activity -->
    <div class="card" style="margin-top:14px">
      <h2>Payment Activity</h2>
      <div class="grid cols-2" style="align-items:end">
        <div><label>Date Contains</label><input id="f_date" placeholder="e.g., 2025-09"/></div>
        <div class="btn-row"><button class="btn secondary" id="applyTxFilter">Apply</button><button class="btn" id="clearTxFilter">Clear</button></div>
      </div>
      <table id="txTable"><thead><tr><th>Date</th><th>Action</th><th>Amount</th><th>Status</th><th>Method</th><th>TXN</th><th style="width:160px">Receipt</th></tr></thead><tbody></tbody></table>
    </div>

    <div class="toast" id="toast">Saved.</div>
  </main>
</div>

<script>
'use strict';
const CURRENCY = { BDT:'৳', USD:'$' };
const money = (n,cur) => (CURRENCY[cur]||'') + Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2});
const toast=(m='Saved.')=>{const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),1600)};

let PREF_CURRENCY = 'BDT';
let MONTHS_CACHE = []; // for month dropdown

async function loadSummary(){
  const r = await fetch('?ajax=summary'); const j = await r.json();
  PREF_CURRENCY = j?.prefs?.currency || 'BDT';

  document.getElementById('cur_label').textContent = 'Current — ' + (j?.current?.label||'—');
  document.getElementById('cur_remaining').textContent = money(j?.current?.remaining||0, PREF_CURRENCY);
  document.getElementById('cur_due').textContent  = 'Due: ' + money(j?.current?.due||0, PREF_CURRENCY);
  document.getElementById('cur_paid').textContent = 'Paid: ' + money(j?.current?.paid||0, PREF_CURRENCY);

  document.getElementById('ov_amount').textContent = money(j?.overdue?.amount||0, PREF_CURRENCY);
  document.getElementById('ov_count').textContent  = (j?.overdue?.count||0) + ' months';
  document.getElementById('ov_oldest').textContent = 'Oldest: ' + (j?.overdue?.oldest_label || '—');

  document.getElementById('p_currency').value = PREF_CURRENCY;
  document.getElementById('p_email').value = (j?.prefs?.email||'');
}

async function loadMonths(){
  const r = await fetch('?ajax=months'); const list = await r.json();
  MONTHS_CACHE = list;
  const tb=document.querySelector('#monthsTable tbody'); tb.innerHTML='';
  document.getElementById('monthsEmpty').style.display = list.length? 'none' : 'block';

  list.forEach(m=>{
    const tr=document.createElement('tr');
    const tagClass = m.status==='Cleared' ? 'green' : (m.status==='Current' ? 'yellow' : 'red');
    tr.innerHTML = `
      <td>${m.label}</td>
      <td>${money(m.due, PREF_CURRENCY)}</td>
      <td>${money(m.paid, PREF_CURRENCY)}</td>
      <td>${money(m.remaining, PREF_CURRENCY)}</td>
      <td><span class="tag ${tagClass}">${m.status}</span></td>
      <td>${m.remaining>0
        ? `<button class="btn secondary prefill" data-y="${m.year}" data-m="${m.month}" data-amt="${m.remaining}">Pay</button>`
        : '—'
      }</td>`;
    tb.appendChild(tr);
  });

  // Payment month dropdown: only months with remaining > 0
  const sel=document.getElementById('pay_month'); sel.innerHTML='';
  const unpaid = list.filter(x=>x.remaining>0);
  if(!unpaid.length){
    const o=document.createElement('option'); o.value=''; o.textContent='— No unpaid months —'; sel.appendChild(o);
  } else {
    unpaid.forEach(m=>{
      const opt=document.createElement('option');
      opt.value = m.year+'-'+String(m.month).padStart(2,'0');
      opt.textContent = `${m.label} — remaining ${money(m.remaining, PREF_CURRENCY)}`;
      sel.appendChild(opt);
    });
  }
  refreshPayBtn();

  // prefill
  tb.querySelectorAll('.prefill').forEach(b=>{
    b.addEventListener('click', ()=>{
      const ym = b.dataset.y + '-' + String(b.dataset.m).padStart(2,'0');
      document.getElementById('pay_month').value = ym;
      document.getElementById('pay_amount').value = Number(b.dataset.amt).toFixed(2);
      toast('Filled from month '+ym);
      refreshPayBtn();
    });
  });
}

function refreshPayBtn(){
  const sel = document.getElementById('pay_month').value;
  const amt = parseFloat((document.getElementById('pay_amount').value||'').replace(/,/g,''))||0;
  const method = document.getElementById('pay_method').value;
  const txn = (document.getElementById('pay_txn').value||'').trim();
  document.getElementById('btnPay').disabled = !(sel && amt>0 && method && txn);
}

['pay_month','pay_amount','pay_method','pay_method_ref','pay_txn'].forEach(id=>{
  const el=document.getElementById(id);
  el && el.addEventListener((id==='pay_method' || id==='pay_month')?'change':'input', refreshPayBtn);
});

// Submit payment (PENDING)
document.getElementById('btnPay').addEventListener('click', async ()=>{
  const sel = document.getElementById('pay_month').value;
  const [year,month] = sel.split('-').map(x=>parseInt(x,10));
  let amount = parseFloat((document.getElementById('pay_amount').value||'').replace(/,/g,''))||0;
  const method = document.getElementById('pay_method').value;
  const method_ref = document.getElementById('pay_method_ref').value;
  const txn_no = document.getElementById('pay_txn').value;

  if(!(year && month)){ alert('Select a month'); return; }
  if(!(amount>0)){ alert('Enter a valid amount'); return; }
  if(!method){ alert('Select a method'); return; }
  if(!txn_no.trim()){ alert('Enter a transaction number'); return; }

  const fd=new URLSearchParams({year,month,amount,method,method_ref,txn_no});
  const r=await fetch('?ajax=pay',{method:'POST',body:fd});
  const j=await r.json();
  if(j.error){ alert(j.error); return; }

  document.getElementById('pay_amount').value='';
  document.getElementById('pay_txn').value='';
  toast('Payment submitted for admin approval');
  await loadSummary(); await loadMonths(); await loadTx({});
});

// Activity/Receipts
function rcptHTML(entry,email){ return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Receipt</title>
<style>body{font-family:Inter,Segoe UI,sans-serif;padding:24px;color:#0f172a}.box{border:1px solid #e5e7eb;border-radius:12px;padding:16px;max-width:720px}
h1{margin:0 0 8px;font-size:22px;color:#2563eb}.row{display:flex;gap:20px;flex-wrap:wrap}.row>div{flex:1 1 240px}.muted{color:#64748b}
table{width:100%;border-collapse:collapse;margin-top:12px}th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f8fafc}</style></head><body>
<div class="box"><h1>Payment Receipt</h1><div class="row">
<div><div class="muted">Date</div><div>${entry.t}</div></div>
<div><div class="muted">Amount</div><div>${entry.amt}</div></div>
<div><div class="muted">Method</div><div>${entry.method||'—'}</div></div>
<div><div class="muted">TXN</div><div>${entry.txn||'—'}</div></div>
<div><div class="muted">Email</div><div>${email||'—'}</div></div>
</div><table><thead><tr><th>Description</th><th>Status</th></tr></thead><tbody><tr><td>${entry.act}</td><td>${entry.status}</td></tr></tbody></table></div></body></html>`; }

async function loadTx(filter={}){
  const r = await fetch('?ajax=tx'); let list = await r.json();
  const tb=document.querySelector('#txTable tbody'); tb.innerHTML='';
  if(filter.date) list=list.filter(x=>(x.t||'').includes(filter.date));

  if (!list.length){
    const tr=document.createElement('tr'); tr.innerHTML = `<td colspan="7"><div class="empty">No transactions yet.</div></td>`;
    tb.appendChild(tr);
    return;
  }

  list.forEach((row,i)=>{
    const cls = row.status==='approved' ? 'green' : (row.status==='pending' ? 'yellow' : 'gray');
    const label = row.status || '—';
    const tr=document.createElement('tr');
    tr.innerHTML = `<td>${row.t}</td>
                    <td>${row.act}</td>
                    <td>${row.amt}</td>
                    <td><span class="tag ${cls}">${label}</span></td>
                    <td>${row.method||'—'}</td>
                    <td>${row.txn||'—'}</td>
                    <td><button type="button" class="btn secondary rcpt" data-idx="${i}" ${row.status!=='approved'?'disabled':''}>Download</button></td>`;
    tb.appendChild(tr);
  });
  const prefs = await (await fetch('?ajax=summary')).json();
  tb.querySelectorAll('.rcpt').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const entry=list[+btn.dataset.idx];
      const blob=new Blob([rcptHTML(entry,prefs?.prefs?.email)],{type:'text/html'});
      const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=`receipt_${Date.now()}.html`; a.click();
    });
  });
}

document.getElementById('applyTxFilter').addEventListener('click',()=> loadTx({date:document.getElementById('f_date').value}));
document.getElementById('clearTxFilter').addEventListener('click',()=>{ document.getElementById('f_date').value=''; loadTx({}) });

// Export CSV
document.getElementById('exportTx').addEventListener('click', ()=>{ window.location='?ajax=exportTx'; });

// Seed demo (adds ONE older month)
document.getElementById('seedDemoBtn').addEventListener('click', async ()=>{
  const r = await fetch('?ajax=seedDemo'); const j=await r.json();
  if(j.error){ alert(j.error); return; }
  await loadSummary(); await loadMonths();
  toast('Added demo month: '+(j?.added?.label||''));
});

// Prefs save
document.getElementById('savePrefs').addEventListener('click', async ()=>{
  const fd=new URLSearchParams({currency:document.getElementById('p_currency').value, email:document.getElementById('p_email').value});
  await fetch('?ajax=savePrefs',{method:'POST',body:fd});
  await loadSummary(); toast('Preferences saved');
});

// Init
(async function init(){ await loadSummary(); await loadMonths(); await loadTx({}); })();
</script>
</body>
</html>
