<?php
$current = basename($_SERVER['PHP_SELF']);
function navActive($f){ global $current; return $current===$f?'active':''; }

$justSaved = ($_SERVER['REQUEST_METHOD']==='POST');

$defaults = [
  'company_name'  => 'HaulPro Logistics Ltd.',
  'support_email' => 'support@haulpro.example',
  'support_phone' => '+880-1XXXXXXXXX',
  'notify_email'  => 1,
  'notify_sms'    => 0,
  'theme'         => 'auto',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Settings — HaulPro Admin</title>

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

    /* Sidebar — neutral (no blue hover/glow) */
    .sidebar{
      width:240px;background:#fff;border-right:1px solid var(--border);
      padding:20px;position:sticky;top:0;height:100vh;
      display:flex;flex-direction:column;align-items:center;gap:10px;
      box-shadow:2px 0 8px rgba(0,0,0,.05);
    }
    .sidebar img[alt="HaulPro Logo"]{width:160px;height:auto;margin-top:6px}
    .sidebar h3{margin:6px 0 16px;font-size:20px;color:#3c4b64;font-weight:800}
    .sidebar .menu{list-style:none;width:100%;padding:0;margin:8px 0 0}
    .sidebar .menu li{margin-bottom:10px}
    .sidebar .menu a{
      display:flex;align-items:center;gap:12px;text-decoration:none;
      color:#3c4b64;font-weight:600;padding:10px 12px;border-radius:10px;
      transition:background .2s ease,color .2s ease,border-color .2s ease;
      border:1px solid transparent;
    }
    .sidebar .menu a img{width:40px;object-fit:contain}
    /* NEUTRAL hover (no blue tint) */
    .sidebar .menu a:hover{background:#f3f4f6;color:#3c4b64;border-color:var(--border)}
    /* NEUTRAL active (no blue bg/glow) */
    .sidebar .menu a.active{
      background:#e5e7eb;color:#0f172a;border-color:#e5e7eb;box-shadow:none
    }

    .help-card{
      margin-top:auto;width:100%;text-align:center;background:#f2f2f9;
      border-radius:12px;padding:14px
    }
    .help-card img{width:60px;height:60px;margin-bottom:8px}
    .help-card p{margin:0 0 8px;font-weight:700;color:#3c4b64}
    .help-card button{
      background:#ffc107;border:none;padding:8px 14px;font-weight:800;color:#000;
      border-radius:8px;cursor:pointer
    }

    /* Content */
    .content{flex:1;padding:28px;max-width:1700px;margin:0 auto}
    h4{margin:0 0 14px;font-size:24px;font-weight:800;color:var(--primary)}

    .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow)}
    .section-title{font-weight:800;color:var(--subtle)}
    .hint{color:var(--muted);font-size:.9rem}

    .btn-theme{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 14px;font-weight:800}
    .btn-theme:hover{background:var(--primary-hover)}
    .btn-outline-theme{border:1px solid var(--border);background:#f1f5f9;color:#0f172a;border-radius:10px;font-weight:700}
    .btn-outline-theme:hover{background:#e2e8f0}

    .danger-zone{border:1px dashed #ffb4b4;background:#fff7f7}
  </style>
</head>
<body>
<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <img src="Image/Logo.png" alt="HaulPro Logo" />
    <h3>HaulPro Admin</h3>
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
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="mb-0">Settings</h4>
      <a href="index.html" class="btn btn-outline-theme">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right me-1" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 0 .5.5h3A1.5 1.5 0 0 0 15 11.5v-7A1.5 1.5 0 0 0 13.5 3h-3a.5.5 0 0 0 0 1h3A.5.5 0 0 1 14 4.5v7a.5.5 0 0 1-.5.5h-3a.5.5 0 0 0-.5.5z"/><path fill-rule="evenodd" d="M7.854 11.354a.5.5 0 0 1-.708-.708L9.293 8 7.146 5.854a.5.5 0 1 1 .708-.708l2.5 2.5a.5.5 0 0 1 0 .708l-2.5 2.5z"/><path fill-rule="evenodd" d="M9.5 8a.5.5 0 0 1-.5.5H1.5a.5.5 0 0 1 0-1H9a.5.5 0 0 1 .5.5z"/></svg>
        Logout
      </a>
    </div>

    <?php if ($justSaved): ?>
      <div class="alert alert-success d-flex align-items-center" role="alert">
        <strong class="me-2">✔</strong> Settings saved (demo). Connect DB to persist.
      </div>
    <?php endif; ?>

    <form method="post" class="needs-validation" novalidate>
      <div class="card p-3 mb-3">
        <div class="section-title mb-2"><i class="bi bi-building me-2"></i>Company Profile</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Company Name</label>
            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($defaults['company_name']) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Support Email</label>
            <input type="email" name="support_email" class="form-control" value="<?= htmlspecialchars($defaults['support_email']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Support Phone</label>
            <input type="text" name="support_phone" class="form-control" value="<?= htmlspecialchars($defaults['support_phone']) ?>">
          </div>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <div class="section-title mb-2"><i class="bi bi-bell me-2"></i>Notifications</div>
        <div class="row g-3">
          <div class="col-md-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="notifyEmail" name="notify_email" <?= $defaults['notify_email']?'checked':'' ?>>
              <label class="form-check-label" for="notifyEmail">Email reminders</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="notifySms" name="notify_sms" <?= $defaults['notify_sms']?'checked':'' ?>>
              <label class="form-check-label" for="notifySms">SMS reminders</label>
            </div>
          </div>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <div class="section-title mb-2"><i class="bi bi-palette me-2"></i>Appearance</div>
        <div class="d-flex gap-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="theme" id="themeLight" value="light" <?= $defaults['theme']=='light'?'checked':'' ?>>
            <label class="form-check-label" for="themeLight"><i class="bi bi-sun me-1"></i> Light</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="theme" id="themeDark" value="dark" <?= $defaults['theme']=='dark'?'checked':'' ?>>
            <label class="form-check-label" for="themeDark"><i class="bi bi-moon me-1"></i> Dark</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="theme" id="themeAuto" value="auto" <?= $defaults['theme']=='auto'?'checked':'' ?>>
            <label class="form-check-label" for="themeAuto"><i class="bi bi-circle-half me-1"></i> Auto</label>
          </div>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <div class="section-title mb-2"><i class="bi bi-shield-lock me-2"></i>Security</div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Current Password</label>
            <input type="password" class="form-control" name="cur_pass" placeholder="••••••••">
          </div>
          <div class="col-md-4">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" name="new_pass" placeholder="At least 8 chars">
          </div>
          <div class="col-md-4">
            <label class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" name="new_pass_confirm" placeholder="Re-type new password">
          </div>
        </div>
        <div class="hint mt-2">Demo-only; wire to your <code>users</code> table later.</div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="index.html" class="btn btn-outline-theme me-auto">
          <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
        <button type="reset" class="btn btn-outline-theme">Reset</button>
        <button type="submit" class="btn btn-theme"><i class="bi bi-check2 me-1"></i> Save Changes</button>
      </div>
    </form>

    <div class="danger-zone rounded p-3 mt-4">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="section-title mb-1 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</div>
          <div class="hint">Demo-only actions.</div>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-danger" id="btnDisableNotify"><i class="bi bi-bell-slash me-1"></i> Disable Notifications</button>
          <button type="button" class="btn btn-outline-danger" id="btnClearDemo"><i class="bi bi-trash3 me-1"></i> Clear Demo State</button>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('btnDisableNotify')?.addEventListener('click', ()=>{
  document.getElementById('notifyEmail').checked=false;
  document.getElementById('notifySms').checked=false;
  alert("Notifications disabled (demo)");
});
document.getElementById('btnClearDemo')?.addEventListener('click', ()=>{
  alert("Demo state cleared (no DB connected)");
});
</script>
</body>
</html>
