<?php
require __DIR__.'/db.php';
require __DIR__.'/auth.php';

$mysqli->query("
CREATE TABLE IF NOT EXISTS users (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  email         VARCHAR(160) NOT NULL UNIQUE,
  full_name     VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$err_login = '';
$err_reg   = '';

// Handle Login
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'login') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  // --- HARD CODED ADMIN LOGIN ---
  if ($email === 'admin@gmail.com' && $pass === 'admin') {
    login_user(0, 'Administrator'); // id=0 since not in users table
    header("Location: adminDashboard.php");
    exit;
  }
  // --- END ADMIN LOGIN ---

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err_login = 'Please enter a valid email.';
  } else {
    $st = $mysqli->prepare("SELECT id, full_name, password_hash FROM users WHERE email=?");
    $st->bind_param('s',$email);
    $st->execute();
    $res = $st->get_result();

    if ($row = $res->fetch_assoc()) {
      if (password_verify($pass, $row['password_hash'])) {
        login_user((int)$row['id'], $row['full_name']);
        $next = $_GET['next'] ?? 'dashboard.php';
        header("Location: ".$next);
        exit;
      } else {
        $err_login = 'Email ID or Password Is Not Correct';
      }
    } else {
      $err_login = 'Email ID is not found';
    }
  }
}

// Handle Register
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'register') {
  $name  = trim($_POST['full_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  if ($name === '')                $err_reg = 'Full name is required.';
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err_reg = 'Please enter a valid email.';
  elseif (strlen($pass) < 6)       $err_reg = 'Password must be at least 6 characters.';
  else {
    $chk = $mysqli->prepare("SELECT id FROM users WHERE email=?");
    $chk->bind_param('s',$email);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
      $err_reg = 'This email is already registered.';
    } else {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $ins  = $mysqli->prepare("INSERT INTO users (email, full_name, password_hash) VALUES (?,?,?)");
      $ins->bind_param('sss',$email,$name,$hash);
      $ins->execute();
      login_user($ins->insert_id, $name);
      header("Location: dashboard.php");
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login / Register</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
  <style>
    .error {
      color:#b91c1c; background:#fee2e2; border:1px solid #fecaca;
      padding:8px 10px; border-radius:8px; margin-bottom:10px;
      opacity:1; transition: opacity 1s ease;
    }
    .error.fade-out { opacity:0; }
    .footer-text a { cursor:pointer; }
    .input-wrapper { display:flex; align-items:center; gap:8px; }
    .input-wrapper input { flex:1; }
    .social-login button img { width:18px; height:18px; }
    .form-panel { display:none; }
    .form-panel.active { display:block; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="form-side">
      <!-- Login Form -->
      <form class="form-panel <?=(empty($err_reg)?'active':'')?>" id="loginPanel" method="post">
        <h2>Login</h2>

        <?php if($err_login): ?>
          <div class="error auto-hide"><?=htmlspecialchars($err_login)?></div>
        <?php endif; ?>

        <input type="hidden" name="action" value="login" />

        <div class="form-group">
          <label for="loginEmail">Email</label>
          <div class="input-wrapper">
            <i><img src="Image/email-file.gif" alt="user" width="20" /></i>
            <input type="email" id="loginEmail" name="email" placeholder="your@email.com" required />
          </div>
        </div>

        <div class="form-group">
          <label for="loginPassword">Password</label>
          <div class="input-wrapper">
            <i><img src="Image/padlock.gif" alt="lock" width="20" /></i>
            <input type="password" id="loginPassword" name="password" placeholder="Password" required />
          </div>
        </div>

        <div class="form-options">
          <label><input type="checkbox" /> Remember Password</label>
          <a href="#">Forgot Password?</a>
        </div>

        <button type="submit">Login</button>

        <div class="footer-text">
          No account yet?
          <a onclick="toggleForm('register')">Register</a>
        </div>
      </form>

      <!-- Register Form -->
      <form class="form-panel <?=($err_reg?'active':'')?>" id="registerPanel" method="post">
        <h2>Register</h2>

        <?php if($err_reg): ?>
          <div class="error auto-hide"><?=htmlspecialchars($err_reg)?></div>
        <?php endif; ?>

        <input type="hidden" name="action" value="register" />

        <div class="form-group">
          <label for="regName">Full Name</label>
          <div class="input-wrapper">
            <i><img src="Image/id-card.png" alt="" width="15" /></i>
            <input type="text" id="regName" name="full_name" placeholder="Your Name" required />
          </div>
        </div>

        <div class="form-group">
          <label for="regEmail">Email</label>
          <div class="input-wrapper">
            <i><img src="Image/email-file.gif" width="20" alt="" /></i>
            <input type="email" id="regEmail" name="email" placeholder="you@example.com" required />
          </div>
        </div>

        <div class="form-group">
          <label for="regPassword">Password</label>
          <div class="input-wrapper">
            <i><img src="Image/padlock.gif" width="20" alt="" /></i>
            <input type="password" id="regPassword" name="password" placeholder="Password" required />
          </div>
        </div>

        <button type="submit">Register</button>

        <div class="footer-text">
          Already have an account?
          <a onclick="toggleForm('login')">Login</a>
        </div>
      </form>
    </div>

    <div class="image-side">
      <img src="Image/Logo.png" alt="Login Illustration" />
    </div>
  </div>

  <script>
    function toggleForm(target) {
      const login = document.getElementById("loginPanel");
      const register = document.getElementById("registerPanel");
      if (target === "register") {
        login.classList.remove("active");
        register.classList.add("active");
      } else {
        register.classList.remove("active");
        login.classList.add("active");
      }
    }
    <?php if($err_reg): ?>toggleForm('register');<?php endif; ?>

    // Fade-out auto-hide errors
    document.querySelectorAll(".error.auto-hide").forEach(el=>{
      setTimeout(()=>{
        el.classList.add("fade-out");
        setTimeout(()=> el.remove(), 1000);
      }, 3000);
    });
  </script>
</body>
</html>
