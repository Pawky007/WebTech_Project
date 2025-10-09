<?php
/****************************************************
 * HaulPro — AI Chat Bot (OpenRouter + Live DB)
 * - Sidebar included (dashboard style logo)
 * - Wide chat area (fills all space to the right)
 * - User messages right, assistant left
 * - Markdown rendering + "How-To" onboarding mode
 ****************************************************/

// ----------------------- CONFIG -----------------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'webtech_project';   // <-- change if needed
$DB_USER = 'root';              // <-- change if needed
$DB_PASS = '';                  // <-- change if needed

// Optional: app identity for OpenRouter (recommended)
$OPENROUTER_REFERER = 'http://localhost';  // your site/local URL
$OPENROUTER_TITLE   = 'HaulPro Assistant';

// Model (start with auto; later you can pin a model)
$OPENROUTER_MODEL = 'openrouter/auto';
// $OPENROUTER_MODEL = 'deepseek/deepseek-r1';
// $OPENROUTER_MODEL = 'deepseek/deepseek-r1:free'; // if available in your account

// Production hardening (hide PHP errors to users)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ----------------------- UTIL -------------------------
function json_out($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function starts_with($haystack, $needle) {
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
}

// -------------------- .env loader ----------------------
$dotenvPath = __DIR__ . '/.env';
if (is_file($dotenvPath) && is_readable($dotenvPath)) {
    foreach (file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv($parts[0] . '=' . $parts[1]);
            $_ENV[$parts[0]] = $parts[1];
        }
    }
}

// ------------- API KEY (single source of truth) --------
$OPENROUTER_API_KEY = getenv('OPENROUTER_API_KEY');
// $OPENROUTER_API_KEY = 'sk-or-v1-PASTE_YOUR_KEY_HERE'; // (DEV ONLY)

if (!$OPENROUTER_API_KEY || !starts_with($OPENROUTER_API_KEY, 'sk-or-v1-')) {
    json_out(['error' => 'OPENROUTER_API_KEY not set or invalid. It should start with sk-or-v1-'], 500);
}

// ----------------- DB CONNECTION (PDO) ----------------
function db() {
    static $pdo = null;
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    if ($pdo) return $pdo;
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        json_out(['error' => 'DB connection failed', 'details' => $e->getMessage()], 500);
    }
}

// ------------- DISCOVERY: find relevant tables --------
function find_tables_for_topic(PDO $pdo, string $topic): array {
    // Align to your schema
    $topicMap = [
      // Payments / Billing / Invoices
      'payment'   => ['billing_payments','payments','payment_methods','payment_prefs','billing_cycles','invoices','invoice_items','customers'],
      'invoice'   => ['invoices','invoice_items','billing_payments','payments','customers','billing_cycles','payment_methods'],
      'billing'   => ['billing_payments','billing_cycles','billing_prefs','payments','invoices','invoice_items','payment_methods','payment_prefs'],

      // Customers / Users / Sessions
      'customer'  => ['customers','users','user_sessions'],
      'client'    => ['customers','users','user_sessions'],
      'user'      => ['users','user_sessions','customers','payment_prefs'],

      // Lorries / Trucks / Owners / Status
      'lorry'     => ['truck_data','lorry_owners','owners','truck_status_events','maintenance'],
      'truck'     => ['truck_data','truck_status_events','maintenance','lorry_owners','owners'],
      'owner'     => ['lorry_owners','owners','truck_data'],

      // Drivers / Driver activity
      'driver'    => ['drivers','drives','routes','trips','trip_history','fuel_logs'],

      // Trips / Routing / Costs
      'trip'      => ['trips','trip_history','routes','trip_costs','truck_data','drivers','drives'],
      'delivery'  => ['trips','trip_history','routes','trip_costs','drivers','drives'],
      'route'     => ['routes','trips','trip_history','trip_costs'],

      // Fuel / Maintenance / Ops
      'fuel'      => ['fuel_logs','trips','trip_history','truck_data','drivers'],
      'maint'     => ['maintenance','truck_data','truck_status_events'],
      'maintenance'=> ['maintenance','truck_data','truck_status_events'],
      'status'    => ['truck_status_events','truck_data','maintenance'],

      // Settings / Prefs
      'setting'   => ['billing_prefs','payment_prefs'],
      'prefs'     => ['billing_prefs','payment_prefs'],
    ];

    $topicLC = strtolower($topic);
    $patterns = [];

    foreach ($topicMap as $key => $subs) {
        if (strpos($topicLC, $key) !== false) $patterns = array_merge($patterns, $subs);
    }

    // capture exact mentions
    $allTables = [
        'billing_cycles','billing_payments','billing_prefs','customers','drivers','drives',
        'fuel_logs','invoice_items','invoices','lorry_owners','maintenance','owners',
        'payment_methods','payment_prefs','payments','routes','trip_costs','trip_history',
        'trips','truck_data','truck_status_events','user_sessions','users'
    ];
    foreach ($allTables as $t) {
        if (strpos($topicLC, strtolower($t)) !== false) $patterns[] = $t;
    }

    // helpful aliases
    if (str_contains($topicLC, 'latest') && str_contains($topicLC, 'payment')) {
        $patterns = array_merge($patterns, ['billing_payments','payments']);
    }
    if ((str_contains($topicLC, 'today') || str_contains($topicLC, 'recent')) && str_contains($topicLC, 'trip')) {
        $patterns = array_merge($patterns, ['trips','trip_history']);
    }
    if (str_contains($topicLC, 'due') && str_contains($topicLC, 'invoice')) {
        $patterns = array_merge($patterns, ['invoices','invoice_items','customers']);
    }
    if (str_contains($topicLC, 'owner')) {
        $patterns = array_merge($patterns, ['lorry_owners','owners']);
    }
    if (str_contains($topicLC, 'truck') || str_contains($topicLC, 'lorry')) {
        $patterns = array_merge($patterns, ['truck_data','truck_status_events','maintenance']);
    }
    if (str_contains($topicLC, 'driver')) {
        $patterns = array_merge($patterns, ['drivers','drives','fuel_logs']);
    }
    if (str_contains($topicLC, 'fuel')) {
        $patterns = array_merge($patterns, ['fuel_logs','trips']);
    }
    if (str_contains($topicLC, 'route')) {
        $patterns = array_merge($patterns, ['routes','trips','trip_history']);
    }

    // fallback
    if (!$patterns) {
        foreach (preg_split('/[^a-z0-9_]+/i', $topicLC) as $tok) {
            if ($tok && strlen($tok) >= 4) $patterns[] = $tok;
        }
        if (!$patterns) $patterns = ['billing_payments','payments','invoices','invoice_items','customers','trips','trip_history','routes','truck_data','lorry_owners'];
    }

    $patterns = array_values(array_unique($patterns));
    $in = implode(' OR ', array_fill(0, count($patterns), 'TABLE_NAME LIKE ?'));
    $sql = "SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() AND ($in)
            ORDER BY TABLE_NAME ASC";
    $stmt = $pdo->prepare($sql);
    $i = 1;
    foreach ($patterns as $p) $stmt->bindValue($i++, "%$p%");
    $stmt->execute();
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $names = array_values(array_unique($names));
    return array_slice($names, 0, 8);
}

// ------- FETCH: recent rows from each relevant table ----
function guess_order_column(PDO $pdo, string $table): ?string {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $cols = $stmt->fetchAll();
    if (!$cols) return null;

    $candidates = [
        'updated_at','created_at','paid_at','payment_date','invoice_date','billing_date',
        'date','datetime','event_time','ts','timestamp','time','id','ID'
    ];
    $have = array_map(fn($c)=>strtolower($c['Field']), $cols);
    foreach ($candidates as $cand) {
        $idx = array_search(strtolower($cand), $have);
        if ($idx !== false) return $cols[$idx]['Field'];
    }
    return $cols[0]['Field'] ?? null;
}

function fetch_table_preview(PDO $pdo, string $table, int $limit = 15): array {
    try {
        $orderCol = guess_order_column($pdo, $table);
        if ($orderCol) {
            $sql = "SELECT * FROM `$table` ORDER BY `$orderCol` DESC LIMIT :lim";
        } else {
            $sql = "SELECT * FROM `$table` LIMIT :lim";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return $rows ?: [];
    } catch (Throwable $e) {
        return ['_error' => 'Query failed: ' . $e->getMessage()];
    }
}

// ---------- Build grounding context ----------
function build_grounding_context(PDO $pdo, string $userMessage): array {
    $tables = find_tables_for_topic($pdo, $userMessage);
    $context = [
        'matched_tables' => $tables,
        'tables' => []
    ];
    foreach ($tables as $t) {
        $preview = fetch_table_preview($pdo, $t, 15);
        $context['tables'][] = [
            'name' => $t,
            'sample' => $preview
        ];
    }
    return $context;
}

// ------------------ OpenRouter Chat Completion ----------------
function chat_with_openrouter(array $messages) {
    global $OPENROUTER_API_KEY, $OPENROUTER_MODEL, $OPENROUTER_REFERER, $OPENROUTER_TITLE;

    $payload = [
        'model' => $OPENROUTER_MODEL,
        'temperature' => 0.2,
        'messages' => $messages,
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $OPENROUTER_API_KEY,
        'HTTP-Referer: ' . $OPENROUTER_REFERER,
        'Referer: ' . $OPENROUTER_REFERER,
        'X-Title: ' . $OPENROUTER_TITLE,
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 90
    ]);

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        json_out(['error' => 'OpenRouter request failed', 'details' => $err], 502);
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($code >= 400) {
        $decoded = json_decode($resp, true);
        json_out([
            'error'    => 'OpenRouter API error',
            'status'   => $code,
            'response' => $decoded ?: $resp
        ], 502);
    }

    $data = json_decode($resp, true);
    return $data['choices'][0]['message']['content'] ?? '';
}

// ----------------------- AJAX ROUTE ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['chat'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = trim($input['message'] ?? '');

    if ($userMessage === '') {
        json_out(['error' => 'Empty message'], 400);
    }

    $pdo = db();
    $context = build_grounding_context($pdo, $userMessage);

$appOverview = <<<TXT
You are an in-app assistant for a lorry management system.

THERE ARE TWO MODES:

1) DATA MODE (analytics/status answers):
   - Use ONLY the "Live DB Context (JSON)" below.
   - OUTPUT FORMAT (Markdown):
     ## Answer
     - 2–6 concise bullet points with the result.
     ### Source tables
     - List table names you used.
     ### Notes
     - If data is missing: "I couldn't find this in the live data." and propose a specific filter (table/column/time).
   - Summarize only the latest relevant rows.

2) HOW-TO MODE (onboarding/product guidance):
   - Trigger when the user asks “how to…”, “steps…”, “where do I…”, “guide”, “create/add/edit/delete …” etc.
   - You MAY answer without DB context, using known modules and common UI patterns.
   - OUTPUT FORMAT (Markdown):
     ## Steps
     1. Path in the app: `Dashboard → Lorries/Vehicles → Add` (or similarly named)
     2. List clear, numbered steps.
     ### Required fields
     - Bullet list of typical fields (e.g., `registration_no`, `model`, `owner_id`, `capacity`, `purchase_date`, `status`)
     ### After saving
     - What the user should see/check next (e.g., appears in list, assign owner, upload docs).
     ### Related tables (for admins)
     - Mention tables likely affected (e.g., `truck_data`, `lorry_owners`, `maintenance`).
   - Keep it short, confident, and practical.

General rules:
- Keep responses scannable with headings + bullets.
- No external market news. Only product guidance and/or live data.
- Do NOT ask clarifying questions; provide the best, safe default path.

Modules:
- Dashboard (KPIs: revenue, expenses, profit, deliveries)
- Lorries/Vehicles (list, owners, availability)
- Clients/Customers (profiles, payments)
- Payments & Receipts (customer payments, receipts)
- Deliveries/Trips (routes, distances, performance)
TXT;

    $messages = [
        ['role' => 'system', 'content' => $appOverview],
        ['role' => 'system', 'content' => 'Live DB Context (JSON): ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ['role' => 'user',   'content' => $userMessage],
    ];

    $answer = chat_with_openrouter($messages);
    json_out([
        'ok' => true,
        'answer' => $answer,
        'used_tables' => $context['matched_tables']
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>HaulPro — AI Chat Bot</title>

<!-- Optional project styles you already use -->
<link rel="stylesheet" href="dashboad_style.css" />
<link rel="stylesheet" href="analysis_css.css" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>

<style>
  :root{
    --accent:#0d6efd; --bg:#f6f8ff; --card:#ffffff; --text:#111827; --muted:#5b6470;
    --ring:rgba(13,110,253,.28); --border:#e5e7eb;
    --shadow:0 16px 40px rgba(13,110,253,.08), 0 6px 20px rgba(17,24,39,.06);
    --radius:18px;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:Inter,system-ui,Segoe UI,Arial,sans-serif;color:var(--text);background:var(--bg)}

  /* --- Layout with Sidebar --- */
  .container{display:flex;min-height:100vh}
  .dashboard{
    flex:1;display:flex;flex-direction:column;min-height:100vh;
    background:
      radial-gradient(1200px 500px at 5% -15%, rgba(13,110,253,.08), transparent 60%),
      radial-gradient(1000px 500px at 105% 10%, rgba(13,110,253,.06), transparent 60%),
      var(--bg);
  }
  .header{
    display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);
    background:rgba(255,255,255,.82);backdrop-filter:saturate(160%) blur(10px);padding:14px 18px;position:sticky;top:0;z-index:5
  }
  .header h1{margin:0;font-size:20px}
  .header .badge{font-size:12px;background:#eaf2ff;border:1px solid #cfe2ff;color:#1d4ed8;padding:3px 8px;border-radius:999px;margin-left:8px}

  /* ------------- WIDE CHAT AREA ------------- */
  .content{
    flex:1 1 auto;                  /* fill remaining space */
    max-width:none !important;      /* remove fixed cap */
    width:100%;
    margin:0 !important;
    padding:12px 20px 20px;
    height:calc(100vh - 64px);      /* viewport minus header */
    display:flex;
  }

  /* --- Chat App (namespaced with hp-*) --- */
  #chatapp{flex:1; display:flex;}
  #chatapp .hp-card{
    flex:1; min-height:100%;
    background:linear-gradient(180deg,#ffffff 0%, #fbfcff 100%);
    border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);
    display:flex;flex-direction:column;overflow:hidden;
  }
  #chatapp .hp-msgs{flex:1; min-height:0; overflow:auto; padding:24px 20px 8px; scroll-behavior:smooth}
  #chatapp .hp-msgs::-webkit-scrollbar{height:10px;width:10px}
  #chatapp .hp-msgs::-webkit-scrollbar-thumb{background:#d6d9e0;border-radius:999px}
  #chatapp .hp-msgs::-webkit-scrollbar-thumb:hover{background:#c3c7d0}

  #chatapp .hp-row{display:flex;gap:12px;margin:14px 0;align-items:flex-end}
  #chatapp .hp-row.hp-bot{justify-content:flex-start}
  #chatapp .hp-row.hp-user{justify-content:flex-end}

  #chatapp .hp-avatar{flex:0 0 36px;height:36px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:14px;color:#fff}
  #chatapp .hp-avatar.hp-bot{background:linear-gradient(135deg,var(--accent),#5aa7ff)}
  #chatapp .hp-avatar.hp-user{background:#9aa6b2}

  #chatapp .hp-bubble{
    max-width:min(1100px, 100%);    /* wider bubbles */
    padding:14px 16px;border-radius:14px;border:1px solid var(--border);background:#fff;box-shadow:0 1px 0 #f1f3f5 inset
  }
  #chatapp .hp-row.hp-user .hp-bubble{background:#e7f1ff;border-color:#cfe2ff}

  /* Markdown typography */
  #chatapp .hp-bubble h2{margin:.2rem 0 .4rem;font-size:1.05rem}
  #chatapp .hp-bubble h3{margin:.6rem 0 .25rem;font-size:.98rem;color:#1f3a8a}
  #chatapp .hp-bubble p{margin:.4rem 0;line-height:1.55}
  #chatapp .hp-bubble ul{margin:.35rem 0 .5rem .9rem}
  #chatapp .hp-bubble ol{margin:.35rem 0 .5rem .9rem}
  #chatapp .hp-bubble li{margin:.15rem 0}
  #chatapp .hp-bubble code{background:#f3f6ff;border:1px solid #dfe7ff;border-radius:6px;padding:2px 5px;font-family:ui-monospace, SFMono-Regular, Menlo, monospace;font-size:.95em}
  #chatapp .hp-bubble pre{background:#0b1220;color:#e5edff;border-radius:10px;padding:12px 14px;overflow:auto}
  #chatapp .hp-bubble pre code{background:transparent;border:0;color:inherit;padding:0}
  #chatapp .hp-hr{height:1px;background:linear-gradient(90deg,transparent,#e5e7eb,transparent);margin:.6rem 0}

  #chatapp .hp-chips{display:flex;gap:8px;padding:10px 14px;border-top:1px dashed var(--border);background:#fafbff}
  #chatapp .hp-chip{font-size:12px;padding:6px 10px;border-radius:999px;border:1px solid #dbe2f1;background:#fff;cursor:pointer}
  #chatapp .hp-chip:hover{border-color:var(--accent);color:var(--accent)}

  #chatapp .hp-composer{display:flex;align-items:center;gap:10px;padding:14px;border-top:1px solid var(--border);background:rgba(255,255,255,.96);backdrop-filter:saturate(160%) blur(8px)}
  #chatapp .hp-input{flex:1;display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--border);border-radius:12px;padding:10px 12px;box-shadow:0 0 0 2px transparent;transition:.15s}
  #chatapp .hp-input:focus-within{box-shadow:0 0 0 4px var(--ring)}
  #chatapp textarea{width:100%;resize:none;border:none;outline:none;font:inherit;line-height:1.5;max-height:180px}
  #chatapp .hp-btn{display:inline-flex;align-items:center;gap:8px;border:none;border-radius:10px;padding:10px 14px;background:var(--accent);color:#fff;font-weight:700;cursor:pointer;box-shadow:0 6px 14px rgba(13,110,253,.18);transition:transform .04s ease}
  #chatapp .hp-btn:active{transform:translateY(1px)}

  /* Typing indicator */
  #chatapp .hp-typing{display:inline-flex;gap:4px;align-items:center}
  #chatapp .hp-dot{width:6px;height:6px;border-radius:50%;background:#a0aec0;animation:hp-bounce 1s infinite ease-in-out}
  #chatapp .hp-dot:nth-child(2){animation-delay:.15s}
  #chatapp .hp-dot:nth-child(3){animation-delay:.3s}
  @keyframes hp-bounce{0%,80%,100%{transform:translateY(0);opacity:.4}40%{transform:translateY(-4px);opacity:1}}
</style>
</head>
<body>
  <div class="container">
    <!-- ============ SIDEBAR (dashboard style) ============ -->
    <aside class="sidebar" id="sidebar">
      <img class="sidebar-logo" src="Image/Logo.png" alt="HaulPro Logo" width="160"  />
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
      <!-- Help Section with Working Contact Button -->
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

    <!-- ============ MAIN / CHAT APP ============ -->
    <main class="dashboard" id="dashboard">
      <div class="header">
        <h1>AI Chat Bot <span class="badge">OpenRouter + Live DB</span></h1>
        <div style="color:#5b6470;font-size:13px">Shift+Enter = newline • Enter = send</div>
      </div>

      <div class="content" id="chatapp">
        <div class="hp-card">
          <div class="hp-msgs" id="hp-msgs">
            <div class="hp-row hp-bot">
              <div class="hp-avatar hp-bot">A</div>
              <div class="hp-bubble">
                <h3>Welcome</h3>
                <p>I can answer live data questions and guide new users with <strong>How-To</strong> steps.</p>
                <div class="hp-hr"></div>
                <p>Try: <code>latest payments today</code> • <code>due invoices this month</code> • <code>How to add a new lorry</code></p>
              </div>
            </div>
          </div>

          <div class="hp-chips" id="hp-chips">
            <button class="hp-chip" data-text="How to add a new lorry">How to add a new lorry</button>
            <button class="hp-chip" data-text="latest payments today">latest payments today</button>
            <button class="hp-chip" data-text="due invoices this month">due invoices this month</button>
            <button class="hp-chip" data-text="last 5 trips Dhaka to Chittagong">last 5 trips Dhaka to Chittagong</button>
          </div>

          <div class="hp-composer">
            <div class="hp-input">
              <textarea id="hp-message" rows="1" placeholder="Ask data questions or 'How to …' guides (e.g., How to add a new lorry)"></textarea>
            </div>
            <button class="hp-btn" id="hp-send">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 11l18-8-8 18-2-7-8-3z" stroke="white" stroke-width="1.7" fill="none"/></svg>
              Send
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>

<script>
/* ---------- Minimal, safe Markdown renderer ---------- */
function hpEscapeHTML(s){return s.replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));}
function hpRenderMarkdown(md){
  md = hpEscapeHTML(md);
  md = md.replace(/```([\s\S]*?)```/g, (_,code)=>`<pre><code>${code}</code></pre>`);
  md = md.replace(/^###\s?(.*)$/gm, '<h3>$1</h3>');
  md = md.replace(/^##\s?(.*)$/gm, '<h2>$1</h2>');
  md = md.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');
  md = md.replace(/(^|[^*])\*(?!\s)(.+?)\*(?!\w)/g,'$1<em>$2</em>');
  md = md.replace(/`([^`]+?)`/g,'<code>$1</code>');
  md = md.replace(/(?:^|\n)(\s*-\s.+(?:\n\s*-\s.+)*)/g, (m)=> {
    const items = m.trim().split('\n').map(l=>l.replace(/^\s*-\s/,'').trim());
    return '\n<ul>' + items.map(i=>`<li>${i}</li>`).join('') + '</ul>';
  });
  md = md.replace(/(?:^|\n)(\s*\d+\.\s.+(?:\n\s*\d+\.\s.+)*)/g, (m)=> {
    const items = m.trim().split('\n').map(l=>l.replace(/^\s*\d+\.\s/,'').trim());
    return '\n<ol>' + items.map(i=>`<li>${i}</li>`).join('') + '</ol>';
  });
  md = md.split(/\n{2,}/).map(block=>{
    if (/^\s*<(h2|h3|ul|ol|pre)/.test(block)) return block;
    return '<p>' + block.replace(/\n/g,'<br>') + '</p>';
  }).join('\n');
  return md;
}

/* ---------- UI helpers (namespaced) ---------- */
const $msgs = document.getElementById('hp-msgs');
const $input = document.getElementById('hp-message');
const $send  = document.getElementById('hp-send');
const $chips = document.getElementById('hp-chips');

function hpAddRow({html, text, who='bot'}){
  const row = document.createElement('div');
  row.className = 'hp-row ' + (who === 'user' ? 'hp-user' : 'hp-bot');

  const avatar = document.createElement('div');
  avatar.className = 'hp-avatar ' + (who === 'user' ? 'hp-user' : 'hp-bot');
  avatar.textContent = who === 'user' ? 'You' : 'A';

  const bubble = document.createElement('div');
  bubble.className = 'hp-bubble';
  if (html) bubble.innerHTML = html; else bubble.textContent = text || '';

  if (who === 'user'){
    row.appendChild(bubble);
    row.appendChild(avatar);
  } else {
    row.appendChild(avatar);
    row.appendChild(bubble);
  }

  $msgs.appendChild(row);
  $msgs.scrollTop = $msgs.scrollHeight;
  return row;
}
function hpShowTyping(){
  const row = document.createElement('div');
  row.className = 'hp-row hp-bot';
  row.innerHTML = `
    <div class="hp-avatar hp-bot">A</div>
    <div class="hp-bubble"><span class="hp-typing">
      <span class="hp-dot"></span><span class="hp-dot"></span><span class="hp-dot"></span>
    </span></div>`;
  $msgs.appendChild(row);
  $msgs.scrollTop = $msgs.scrollHeight;
  return row;
}
function hpRemove(el){ if(el && el.parentNode){ el.parentNode.removeChild(el); } }

/* ---------- Autosize textarea ---------- */
function hpAutosize(){
  $input.style.height = 'auto';
  $input.style.height = Math.min($input.scrollHeight, 180) + 'px';
}
$input.addEventListener('input', hpAutosize);

/* ---------- Chips ---------- */
$chips.addEventListener('click', (e)=>{
  const btn = e.target.closest('.hp-chip');
  if(!btn) return;
  $input.value = btn.dataset.text;
  hpAutosize();
  $input.focus();
});

/* ---------- Send logic ---------- */
async function hpSend(){
  const text = $input.value.trim();
  if(!text) return;

  hpAddRow({text, who:'user'});
  $input.value = ''; hpAutosize(); $input.focus();

  const typing = hpShowTyping();

  try{
    const res = await fetch('?chat=1', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ message: text })
    });
    const data = await res.json();
    hpRemove(typing);

    if(!res.ok || !data.ok){
      let msg = 'Sorry, I hit an error.';
      if (data.error)   msg += '\n' + data.error;
      if (data.status)  msg += '\nstatus: ' + data.status;
      if (data.response) msg += '\n' + (typeof data.response === 'string'
        ? data.response
        : JSON.stringify(data.response));
      hpAddRow({text: msg, who:'bot'});
      return;
    }

    const html = hpRenderMarkdown(data.answer);
    hpAddRow({html, who:'bot'});
  }catch(err){
    hpRemove(typing);
    hpAddRow({text:'Network/OpenRouter error: ' + err.message, who:'bot'});
  }
}
$send.addEventListener('click', hpSend);
$input.addEventListener('keydown', (e)=>{
  if(e.key === 'Enter' && !e.shiftKey){
    e.preventDefault();
    hpSend();
  }
});

// Focus input initially
$input.focus();
</script>
</body>
</html>
