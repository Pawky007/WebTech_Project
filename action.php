<?php
/****************************************************
 * Chatbase Custom Actions API (single file)
 * - Secure token auth via header: X-Chatbase-Action-Key
 * - Supports multiple read-only actions routed by ?action=
 * - Returns JSON { ok, data, meta } suitable for Chatbase
 ****************************************************/

// ---------- CONFIG ----------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'webtech_project';  // <-- change
$DB_USER = 'root';          // <-- change if needed
$DB_PASS = '';              // <-- change if needed
$AUTH_TOKEN = 'REPLACE_WITH_A_LONG_RANDOM_SECRET'; // set same in Chatbase action header

// ---------- BOILERPLATE ----------
header('Content-Type: application/json; charset=utf-8');

function fail($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

$hdrToken = $_SERVER['HTTP_X_CHATBASE_ACTION_KEY'] ?? '';
if (!$hdrToken || !hash_equals($AUTH_TOKEN, $hdrToken)) fail('Unauthorized', 401);

try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",$DB_USER,$DB_PASS,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
} catch (Throwable $e) {
  fail('DB connection failed: '.$e->getMessage(), 500);
}

$action = $_GET['action'] ?? '';
$limit  = max(1, min((int)($_GET['limit'] ?? 20), 200));
$offset = max(0, (int)($_GET['offset'] ?? 0));

function dateOrNull($v){
  if (!$v) return null;
  $t = strtotime($v);
  return $t ? date('Y-m-d', $t) : null;
}

switch ($action) {

  /* ========== DRIVERS ========== */
  case 'drivers.search': {
    $q      = trim($_GET['q'] ?? '');        // name/contact/license search
    $status = trim($_GET['status'] ?? '');   // Active / Inactive etc.
    $since  = dateOrNull($_GET['since'] ?? '');
    $sql = "SELECT id, user_id, full_name, contact, license_no, address, status, created_at, updated_at
            FROM drivers WHERE 1";
    $args = [];
    if ($q !== '') {
      $sql .= " AND (full_name LIKE ? OR contact LIKE ? OR license_no LIKE ? OR address LIKE ?)";
      for($i=0;$i<4;$i++) $args[] = "%$q%";
    }
    if ($status !== '') { $sql .= " AND status = ?"; $args[] = $status; }
    if ($since) { $sql .= " AND DATE(created_at) >= ?"; $args[] = $since; }
    $sql .= " ORDER BY COALESCE(updated_at, created_at) DESC LIMIT ? OFFSET ?";
    $args[] = $limit; $args[] = $offset;

    $st = $pdo->prepare($sql); $st->execute($args);
    echo json_encode(['ok'=>true,'data'=>$st->fetchAll(),'meta'=>compact('limit','offset')]); exit;
  }

  /* ========== TRUCKS ========== */
  case 'trucks.list': {
    $status = trim($_GET['status'] ?? '');     // e.g., 'In Transit', 'Available', 'Waiting for Load'
    $type   = trim($_GET['truck_type'] ?? ''); // Small/Medium/Large/Open etc.
    $loc    = trim($_GET['location'] ?? '');   // current_location contains...
    $sql = "SELECT reg_number AS reg_no, driver, driver_phone, status, truck_type,
                   current_load_description, current_location, eta_to_depot, notes
            FROM truck_data WHERE 1";
    $args = [];
    if ($status !== '') { $sql .= " AND status = ?"; $args[] = $status; }
    if ($type   !== '') { $sql .= " AND truck_type = ?"; $args[] = $type; }
    if ($loc    !== '') { $sql .= " AND current_location LIKE ?"; $args[] = "%$loc%"; }
    $sql .= " ORDER BY COALESCE(eta_to_depot, CURRENT_TIMESTAMP) ASC LIMIT ? OFFSET ?";
    $args[] = $limit; $args[] = $offset;

    $st = $pdo->prepare($sql); $st->execute($args);
    echo json_encode(['ok'=>true,'data'=>$st->fetchAll(),'meta'=>compact('limit','offset')]); exit;
  }

  /* ========== TRIPS (LIVE) ========== */
  case 'trips.search': {
    $from  = trim($_GET['from'] ?? '');    // route_from
    $to    = trim($_GET['to'] ?? '');      // route_to
    $d1    = dateOrNull($_GET['date_from'] ?? '');
    $d2    = dateOrNull($_GET['date_to'] ?? '');
    $truck = (int)($_GET['truck_id'] ?? 0);
    $status= trim($_GET['status'] ?? '');  // Pending/Delivered etc.

    $sql = "SELECT id, truck_id, trip_date, route_from, route_to, trip_type, distance_km,
                   revenue_bdt, driver_id, customer_id, driver_fee, fuel_cost, toll_cost,
                   labor_cost, gate_cost, other_cost, receipt_no, created_at, updated_at,
                   trip_status
            FROM trips WHERE 1";
    $args = [];
    if ($from !== '') { $sql .= " AND route_from = ?"; $args[] = $from; }
    if ($to   !== '') { $sql .= " AND route_to = ?";   $args[] = $to; }
    if ($d1)         { $sql .= " AND trip_date >= ?";  $args[] = $d1; }
    if ($d2)         { $sql .= " AND trip_date <= ?";  $args[] = $d2; }
    if ($truck)      { $sql .= " AND truck_id = ?";    $args[] = $truck; }
    if ($status!==''){ $sql .= " AND trip_status = ?"; $args[] = $status; }
    $sql .= " ORDER BY trip_date DESC, id DESC LIMIT ? OFFSET ?";
    $args[] = $limit; $args[] = $offset;

    $st = $pdo->prepare($sql); $st->execute($args);
    echo json_encode(['ok'=>true,'data'=>$st->fetchAll(),'meta'=>compact('limit','offset')]); exit;
  }

  /* ========== TRIP HISTORY (ROLLUP) ========== */
  case 'trip_history.search': {
    $q   = trim($_GET['route_contains'] ?? ''); // substring inside route string
    $d1  = dateOrNull($_GET['date_from'] ?? '');
    $d2  = dateOrNull($_GET['date_to'] ?? '');
    $sql = "SELECT trip_no, date_bd, route, trip_type, distance_km,
                   revenue_bdt AS revenue, expense_bdt AS expense, profit_bdt AS profit
            FROM trip_history WHERE 1";
    $args = [];
    if ($q   !== '') { $sql .= " AND route LIKE ?"; $args[] = "%$q%"; }
    if ($d1)         { $sql .= " AND date_bd >= ?"; $args[] = $d1; }
    if ($d2)         { $sql .= " AND date_bd <= ?"; $args[] = $d2; }
    $sql .= " ORDER BY date_bd DESC, trip_no DESC LIMIT ? OFFSET ?";
    $args[] = $limit; $args[] = $offset;

    $st = $pdo->prepare($sql); $st->execute($args);
    echo json_encode(['ok'=>true,'data'=>$st->fetchAll(),'meta'=>compact('limit','offset')]); exit;
  }

  /* ========== OWNERS / FLEET ========== */
  case 'owners.search': {
    $q      = trim($_GET['q'] ?? '');        // owner_name, vehicle_no, contact, address
    $status = trim($_GET['status'] ?? '');
    $type   = trim($_GET['truck_type'] ?? '');
    $sql = "SELECT id, vehicle_no, owner_type, owner_name, truck_type, status, driver_id,
                   contact, address, capacity, notes, created_at, user_id
            FROM lorry_owners WHERE 1";
    $args = [];
    if ($q !== '') {
      $sql .= " AND (owner_name LIKE ? OR vehicle_no LIKE ? OR contact LIKE ? OR address LIKE ?)";
      for($i=0;$i<4;$i++) $args[] = "%$q%";
    }
    if ($status !== '') { $sql .= " AND status = ?"; $args[] = $status; }
    if ($type   !== '') { $sql .= " AND truck_type = ?"; $args[] = $type; }
    $sql .= " ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?";
    $args[] = $limit; $args[] = $offset;

    $st = $pdo->prepare($sql); $st->execute($args);
    echo json_encode(['ok'=>true,'data'=>$st->fetchAll(),'meta'=>compact('limit','offset')]); exit;
  }

  /* ========== PAYMENTS ========== */
  case 'payments.recent': {
    $user_id = (int)($_GET['user_id'] ?? 0);  // optional filter
    $status  = trim($_GET['status'] ?? '');   // approved/rejected/pending
    $method  = trim($_GET['method'] ?? '');   // bkash/nagad/rocket/...
    $d1      = dateOrNull($_GET['date_from'] ?? '');
    $d2      = dateOrNull($_GET['date_to'] ?? '');
    $sql = "SELECT id, user_id, year, month, paid_date, method, method_ref, txn_no,
                   amount_bdt, status, reviewed_by, reviewed_at, review_note, created_at
            FROM payments WHERE 1";
    $args = [];
    if ($user_id)     { $sql .= " AND user_id = ?";   $args[] = $user_id; }
    if ($status!=='') { $sql .= " AND status = ?";    $args[] = $status; }
    if ($method!=='') { $sql .= " AND method = ?";    $args[] = $method; }
    if ($d1)          { $sql .= " AND paid_date >= ?";$args[] = $d1; }
    if ($d2)          { $sql .= " AND paid_date <= ?";$args[] = $d2; }
    $sql .= " ORDER BY paid_date DESC, id DESC LIMIT ? OFFSET ?";
    $args[] = $limit; $args[] = $offset;

    $st = $pdo->prepare($sql); $st->execute($args);
    $rows = $st->fetchAll();

    // Simple totals
    $total = 0.0; foreach($rows as $r) $total += (float)$r['amount_bdt'];

    echo json_encode(['ok'=>true,'data'=>$rows,'meta'=>['limit'=>$limit,'offset'=>$offset,'sum_amount_bdt'=>$total]]); exit;
  }

  /* ========== BILLING SUMMARY ========== */
  case 'billing.summary': {
    $user_id = (int)($_GET['user_id'] ?? 0);    // optional: per customer
    $year    = (int)($_GET['year'] ?? 0);       // optional
    $month   = (int)($_GET['month'] ?? 0);      // optional
    $sql = "SELECT user_id, year, month, amount_due, amount_paid, created_at
            FROM billing_cycles WHERE 1";
    $args = [];
    if ($user_id) { $sql .= " AND user_id = ?"; $args[] = $user_id; }
    if ($year)    { $sql .= " AND year = ?";    $args[] = $year; }
    if ($month)   { $sql .= " AND month = ?";   $args[] = $month; }
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $args[] = $limit; $args[] = $offset;

    $st = $pdo->prepare($sql); $st->execute($args);
    $rows = $st->fetchAll();

    $due=0.0; $paid=0.0;
    foreach($rows as $r){ $due+=(float)$r['amount_due']; $paid+=(float)$r['amount_paid']; }

    echo json_encode(['ok'=>true,'data'=>$rows,'meta'=>['limit'=>$limit,'offset'=>$offset,'total_due'=>$due,'total_paid'=>$paid,'balance'=>$due-$paid]]); exit;
  }

  default:
    fail('Unknown action', 400);
}
