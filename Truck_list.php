<?php
date_default_timezone_set('Asia/Dhaka');

// ----- Database Connection -----
$servername = "localhost";  
$username = "root";         
$password = "";             
$dbname = "webtech_project"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----- Fetch Truck Data -----
$sql = "SELECT `Reg Number`, `Driver`, `Driver Phone`, `Status`, `Truck Type`,
               `Current Load Description`, `Current Location`, `ETA to Depot`, `Notes`
        FROM `truck_data`";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Truck Data</title>
  <link rel="stylesheet" href="truck_data.css?v=4">
  <link rel="stylesheet" href="dashboad_style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
        <img src="Image/Logo.png" alt="HaulPro Logo" width="200px" />
        <h3>HaulPro</h3>
        <ul class="menu">
          <li>
            <a href="http://localhost/WebTech_Project/Dashboard.html"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-chart-line"></i> Analytics</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-bullhorn"></i> Banner</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-truck"></i> Vehicle</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-cogs"></i> Load Order</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-credit-card"></i> Payment Method</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-users"></i> Transporter List</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-user-tie"></i> Lorry Owner List</a>
          </li>
          <li>
            <a href="http://localhost/WebTech_Project/Truck_list.php"
              ><i class="fa fa-truck-moving"></i> Lorry List</a
            >
          </li>
          <li>
            <a href="#"><i class="fa fa-cogs"></i> Setting</a>
          </li>
          <li>
            <a href="#"><i class="fa fa-question-circle"></i> FAQ</a>
          </li>
        </ul>
        <div class="help-card">
          <img
            src="https://cdn-icons-png.flaticon.com/512/4712/4712002.png"
            alt="Help"
          />
          <p>Need Help?</p>
          <button>Contact Now</button>
        </div>
      </aside>

<div class="card" >
  <h2 class="table-header">
  <span>🚛 Truck List</span>
  <button class="add-truck-btn" onclick="openForm()">+ Add Truck</button>
</h2>

  <table>
    <thead>
      <tr>
        <th>Reg Number</th>
        <th>Driver</th>
        <th>Driver Phone</th>
        <th>Status</th>
        <th>Truck Type</th>
        <th>Current Location</th>
        <th>Trip History</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['Reg Number']) ?></td>
            <td><?= htmlspecialchars($row['Driver']) ?></td>
            <td><?= htmlspecialchars($row['Driver Phone']) ?></td>

            <!-- Status with Pills -->
            <td>
              <?php
                $status = strtolower($row['Status']);
                if ($status === "active") {
                  echo '<span class="status-pill status-active"><i class="fas fa-check-circle"></i> Active</span>';
                } elseif ($status === "maintenance") {
                  echo '<span class="status-pill status-maintenance"><i class="fas fa-wrench"></i> Maintenance</span>';
                } elseif ($status === "available") {
                  echo '<span class="status-pill status-available"><i class="fas fa-clock"></i> Available</span>';
                } elseif ($status === "out of service") {
                  echo '<span class="status-pill status-out"><i class="fas fa-times-circle"></i> Out of Service</span>';
                } elseif ($status === "in transit") {
                  echo '<span class="status-pill status-transit"><i class="fas fa-truck"></i> In Transit</span>';
                } elseif ($status === "delivered") {
                  echo '<span class="status-pill status-delivered"><i class="fas fa-box"></i> Delivered</span>';
                } elseif ($status === "waiting for load") {
                  echo '<span class="status-pill status-waiting"><i class="fas fa-hourglass-half"></i> Waiting for Load</span>';
                } else {
                  echo htmlspecialchars($row['Status']);
                }
              ?>
            </td>

            <td><?= htmlspecialchars($row['Truck Type']) ?></td>
            <td><?= htmlspecialchars($row['Current Location']) ?></td>

            <!-- Trip History Button -->
            <td>
              <a href="Lorry_List.php" class="history-btn">
                View
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="10">No truck data available.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

    </div>

<!-- Popup Form -->
<div id="truckFormPopup" class="popup-overlay">
  <div class="popup-form">
    <h3>Add Truck</h3>
    <form id="addTruckForm">
      <label>Reg Number:</label>
      <input type="text" name="reg_number" required>

      <label>Driver:</label>
      <input type="text" name="driver" required>

      <label>Driver Phone:</label>
      <input type="text" name="driver_phone" required>

      <label>Status:</label>
      <select name="status" required>
        <option value="">-- Select Status --</option>
        <option value="Active">Active</option>
        <option value="In Transit">In Transit</option>
        <option value="Delivered">Delivered</option>
        <option value="Waiting for Load">Waiting for Load</option>
        <option value="Out of Service">Out of Service</option>
        <option value="Maintenance">Maintenance</option>
      </select>

      <label>Truck Type:</label>
      <select name="truck_type" required>
        <option value="">-- Select Truck Type --</option>
        <option value="Small Truck">Small Truck</option>
        <option value="Medium Truck">Medium Truck</option>
        <option value="Large Truck">Large Truck</option>
        <option value="Covered Van">Covered Van</option>
        <option value="Open Truck">Open Truck</option>
      </select>

      <label>Current Location:</label>
      <input type="text" name="current_location">

      <div class="form-actions">
        <button type="submit" class="done-btn">✅ Done</button>
        <button type="button" class="close-btn" onclick="closeForm()">❌ Cancel</button>
      </div>
    </form>
  </div>
</div>


<script>
function openForm() {
  document.getElementById("truckFormPopup").classList.add("show");
}
function closeForm() {
  document.getElementById("truckFormPopup").classList.remove("show");
}

// Optional: close when clicking outside
document.addEventListener("click", (e) => {
  const overlay = document.getElementById("truckFormPopup");
  if (overlay && e.target === overlay) closeForm();
});

</script>



</body>
</html>
<?php $conn->close(); ?>
