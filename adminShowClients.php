<?php
/******************************
 * HaulPro Admin — Users (Clients)
 * - Lists users from `users` table
 * - Add & Delete via AJAX
 * - Same theme + sidebar as adminDashboard
 ******************************/

// ---------- DB CONFIG ----------
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "webtech_project";

// ---------- DB CONNECT ----------
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) { http_response_code(500); die("DB connection failed: ".$mysqli->connect_error); }
$mysqli->set_charset("utf8mb4");

// ---------- HELPERS ----------
function jout($x){ header("Content-Type: application/json"); echo json_encode($x); exit; }
function clean($s){ return trim($s ?? ""); }
function now(){ return date("Y-m-d H:i:s"); }

// (Optional) ensure table exists (won’t overwrite your schema)
$mysqli->query("CREATE TABLE IF NOT EXISTS users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL UNIQUE,
  full_name VARCHAR(140) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ---------- AJAX ----------
if (isset($_GET['ajax'])) {
  $ajax = $_GET['ajax'];

  // List users
  if ($ajax === 'list') {
    $res = $mysqli->query("SELECT id, email, full_name, created_at, updated_at FROM users ORDER BY id DESC");
    $out=[]; while($row=$res->fetch_assoc()) $out[]=$row;
    jout($out);
  }

  // Add user
  if ($ajax === 'add' && $_SERVER['REQUEST_METHOD']==='POST') {
    $email = clean($_POST['email'] ?? "");
    $name  = clean($_POST['full_name'] ?? "");
    $pass  = $_POST['password'] ?? "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jout(["ok"=>0,"error"=>"Invalid email"]);
    if ($name==="") jout(["ok"=>0,"error"=>"Name is required"]);
    if (strlen($pass) < 6) jout(["ok"=>0,"error"=>"Password must be at least 6 characters"]);

    // check duplicate
    $chk = $mysqli->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) jout(["ok"=>0,"error"=>"Email already exists"]);

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare("INSERT INTO users (email, full_name, password_hash, created_at, updated_at) VALUES (?,?,?,?,?)");
    $ts = now();
    $stmt->bind_param("sssss", $email, $name, $hash, $ts, $ts);
    if (!$stmt->execute()) jout(["ok"=>0,"error"=>"Insert failed: ".$mysqli->error]);

    jout(["ok"=>1,"id"=>$stmt->insert_id]);
  }

  // Delete user
  if ($ajax === 'delete' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jout(["ok"=>0,"error"=>"Invalid ID"]);

    $stmt = $mysqli->prepare("DELETE FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) jout(["ok"=>0,"error"=>"Delete failed: ".$mysqli->error]);

    jout(["ok"=>1]);
  }

  // Unknown
  http_response_code(404); jout(["error"=>"Unknown ajax"]);
}

// ---------- INITIAL (for SSR fallback) ----------
$users_rs = $mysqli->query("SELECT id, email, full_name, created_at, updated_at FROM users ORDER BY id DESC");
$users = []; while($r=$users_rs->fetch_assoc()) $users[]=$r;

// nav active
$current = basename($_SERVER['PHP_SELF']);
function navActive($f){ global $current; return $current===$f ? 'active' : ''; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Show All Clients — HaulPro Admin</title>
<link rel="stylesheet" href="dashboad_style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --primary:#2563eb; --primary-hover:#1d4ed8;
  --bg:#f6f8fc; --surface:#fff; --border:#e5e7eb; --text:#0f172a;
  --muted:#64748b; --radius:14px; --shadow:0 6px 18px rgba(15,23,42,.06);
  --chip:#eef2ff; --chiptext:#3730a3; --danger:#ef4444;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:Inter,"Segoe UI",system-ui,-apple-system,sans-serif;color:var(--text)}
.layout{display:flex;min-height:100vh}

/* Sidebar (same look as our project) */
.sidebar{width:260px;background:var(--surface);border-right:1px solid var(--border);padding:18px;display:flex;flex-direction:column;gap:10px;position:sticky;top:0;height:100vh}
.brand{display:flex;align-items:center;gap:10px;padding:4px 6px}
.brand img{width:160px;height:auto}
.brand-title{font-weight:800;font-size:18px;color:var(--primary)}
.menu{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-direction:column;gap:6px}
.menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;text-decoration:none;color:var(--text);font-weight:600;border:1px solid transparent}
.menu a:hover{background:#f1f5f9;border-color:var(--border)}
.menu a.active{background:var(--primary);color:#fff;box-shadow:0 6px 16px rgba(37,99,235,.25);border-color:var(--primary)}

/* Content */
.content{flex:1;padding:26px;max-width:1700px;margin:0 auto}
.page-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
.page-head h1{margin:0;font-size:24px;font-weight:800;color:var(--primary)}

/* Card + toolbar */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px}
.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.toolbar input,.toolbar select{padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:#fff;font-size:14px}
.btn{background:var(--primary);color:#fff;border:none;padding:10px 14px;border-radius:12px;font-weight:700;cursor:pointer}
.btn:hover{background:var(--primary-hover)}
.btn.ghost{background:transparent;color:var(--text);border:1px dashed var(--border)}
.btn.danger{background:var(--danger);}

/* Table */
.table-wrap{overflow:auto;max-height:62vh;border:1px solid var(--border);border-radius:12px}
table{width:100%;border-collapse:collapse;background:#fff}
thead th{position:sticky;top:0;background:#f8fafc;font-weight:800;color:#334155;border-bottom:1px solid var(--border);z-index:1}
th,td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:14px;text-align:left}
tbody tr:nth-child(odd){background:#fcfdff}
tbody tr:hover{background:#f2f6ff}
.actions{display:flex;gap:8px}

/* Add form modal-ish card */
.add-box{background:#fcfdff;border:1px dashed var(--border);border-radius:12px;padding:12px;display:flex;flex-wrap:wrap;gap:10px}
.add-box input{flex:1 1 240px;padding:10px 12px;border:1px solid var(--border);border-radius:12px}

/* Toast */
.toast{position:fixed;right:24px;bottom:24px;padding:12px 16px;background:#111827;color:#fff;border-radius:12px;box-shadow:var(--shadow);opacity:0;transform:translateY(12px);transition:.2s}
.toast.show{opacity:1;transform:translateY(0)}
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

  <!-- Content -->
  <main class="content">
    <div class="page-head">
      <h1>Registered Clients (Users)</h1>
      <div class="chip" style="background:var(--chip);color:var(--chiptext);padding:6px 10px;border-radius:999px;font-weight:700;">
        Total: <span id="countSpan"><?= count($users) ?></span>
      </div>
    </div>

    <div class="card" style="margin-bottom:12px">
      <div class="add-box">
        <input id="add_name" placeholder="Full name" />
        <input id="add_email" placeholder="Email" type="email" />
        <input id="add_pass" placeholder="Password (min 6 chars)" type="password" />
        <button class="btn" id="btnAdd">➕ Add User</button>
      </div>
      <div class="toolbar" style="margin-top:12px">
        <input type="text" id="searchBox" placeholder="🔍 Search name/email...">
        <button class="btn ghost" id="downloadCsv">⬇️ Export CSV</button>
      </div>

      <div class="table-wrap">
        <table id="userTable">
          <thead>
            <tr>
              <th style="width:80px">ID</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Created</th>
              <th>Updated</th>
              <th style="width:160px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!count($users)): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:20px">No users found.</td></tr>
            <?php else: foreach($users as $u): ?>
              <tr data-id="<?= (int)$u['id'] ?>">
                <td><?= (int)$u['id'] ?></td>
                <td><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['updated_at'] ?? '') ?></td>
                <td class="actions">
                  <button class="btn danger btnDel">Delete</button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div class="toast" id="toast">Saved.</div>

<script>
const toast=(m='Saved.')=>{const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),1400)};

// In-memory rows for client-side search/export
function tableRows(){ return Array.from(document.querySelectorAll('#userTable tbody tr')); }
function refreshCount(){ document.getElementById('countSpan').textContent = tableRows().filter(r=>r.style.display!=='none').length; }

// Search
const searchBox=document.getElementById("searchBox");
searchBox.addEventListener("input",()=>{
  const q=searchBox.value.toLowerCase();
  tableRows().forEach(r=>{
    const txt = r.textContent.toLowerCase();
    r.style.display = txt.includes(q) ? "" : "none";
  });
  refreshCount();
});

// Export CSV
document.getElementById("downloadCsv").addEventListener("click",()=>{
  const visible = tableRows().filter(r=>r.style.display!=='none');
  let csv = "ID,Full Name,Email,Created,Updated\n";
  visible.forEach(r=>{
    const cells = Array.from(r.cells).slice(0,5).map(td=>`"${(td.textContent||'').replace(/"/g,'""')}"`).join(",");
    csv += cells + "\n";
  });
  const blob = new Blob([csv],{type:"text/csv"});
  const a = document.createElement("a"); a.href=URL.createObjectURL(blob); a.download="users.csv"; a.click();
});

// Add user
document.getElementById('btnAdd').addEventListener('click', async ()=>{
  const full_name = document.getElementById('add_name').value.trim();
  const email     = document.getElementById('add_email').value.trim();
  const password  = document.getElementById('add_pass').value;

  if(!full_name || !email || !password){ alert('Please fill name, email and password.'); return; }

  const fd = new URLSearchParams({full_name, email, password});
  const r = await fetch('?ajax=add', {method:'POST', body:fd});
  const j = await r.json();
  if(j.ok){
    // reload list
    await reloadUsers();
    document.getElementById('add_name').value='';
    document.getElementById('add_email').value='';
    document.getElementById('add_pass').value='';
    toast('User added');
  }else{
    alert(j.error || 'Failed to add user');
  }
});

// Delete user
function bindDeleteButtons(){
  document.querySelectorAll('.btnDel').forEach(btn=>{
    btn.onclick = async ()=>{
      const tr = btn.closest('tr'); const id = tr?.dataset.id;
      if(!id) return;
      if(!confirm('Delete this user?')) return;
      const fd = new URLSearchParams({id});
      const r = await fetch('?ajax=delete', {method:'POST', body:fd});
      const j = await r.json();
      if(j.ok){ tr.remove(); toast('User deleted'); refreshCount(); }
      else alert(j.error || 'Delete failed');
    };
  });
}
bindDeleteButtons();

// Reload users from server (used after add)
async function reloadUsers(){
  const r = await fetch('?ajax=list');
  const list = await r.json();

  const tbody = document.querySelector('#userTable tbody');
  tbody.innerHTML = '';
  if(!list.length){
    const tr=document.createElement('tr');
    tr.innerHTML = `<td colspan="6" style="text-align:center;color:var(--muted);padding:20px">No users found.</td>`;
    tbody.appendChild(tr);
  }else{
    list.forEach(u=>{
      const tr=document.createElement('tr');
      tr.dataset.id = u.id;
      tr.innerHTML = `
        <td>${u.id}</td>
        <td>${(u.full_name||'').replace(/</g,'&lt;')}</td>
        <td>${(u.email||'').replace(/</g,'&lt;')}</td>
        <td>${u.created_at || ''}</td>
        <td>${u.updated_at || ''}</td>
        <td class="actions"><button class="btn danger btnDel">Delete</button></td>`;
      tbody.appendChild(tr);
    });
  }
  bindDeleteButtons();
  refreshCount();
}
</script>
</body>
</html>
