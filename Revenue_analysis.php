<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HaulPro — Revenue Analysis</title>
  <link rel="stylesheet" href="dashboad_style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
   :root {
  --brand: #007bff;
  --ok: #28a745;
  --warn: #f2b01e;
  --bad: #dc3545;
  --panel: #ffffff;
  --line: #e9eef5;
  --radius: 14px;
  --shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

body {
  font-family: "Inter", system-ui, sans-serif;
  background: #f5f7fb;
  color: #1e293b;
  line-height: 1.6;
}

.content-wrap {
  max-width: 1700px;
  margin: 0 auto;
  padding: 28px;
}

/* KPI cards */
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 22px;
  margin-bottom: 28px;
}
.kpi {
  background: var(--panel);
  border-radius: var(--radius);
  padding: 22px;
  box-shadow: var(--shadow);
  transition: 0.3s ease;
}
.kpi:hover {
  transform: translateY(-4px);
}
.kpi h4 {
  margin: 0 0 8px;
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
}
.kpi .big {
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 6px;
  color: #111827;
}
.kpi .sub {
  font-size: 13px;
  color: #475569;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 14px;
  margin: 20px 0 28px;
}
.tab-btn {
  padding: 10px 20px;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #fff;
  color: #334155;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.25s ease;
}
.tab-btn:hover {
  background: #f1f5f9;
}
.tab-btn.active {
  background: var(--brand);
  color: #fff;
  border-color: var(--brand);
  box-shadow: var(--shadow);
}

/* Panels */
.panel {
  background: var(--panel);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  margin-bottom: 28px;
  overflow: hidden;
}
.panel .hd {
  padding: 16px 20px;
  border-bottom: 1px solid var(--line);
  background: #fafbfc;
}
.panel .hd h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
}
.panel .body {
  padding: 20px;
}

/* Charts */
.chart-wrap {
  position: relative;
  width: 100%;
  height: 320px;
}
.chart-wrap canvas {
  width: 100% !important;
  height: 100% !important;
}

/* Service list */
.service-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.service-list li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  margin-bottom: 12px;
  border-radius: var(--radius);
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  transition: 0.25s ease;
}
.service-list li:hover {
  background: #f1f5f9;
  transform: translateY(-2px);
}
.service-list li span:first-child {
  font-weight: 600;
  color: #111827;
}
.service-list li span:last-child {
  font-weight: 700;
  color: var(--brand);
}


  </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <img src="Image/Logo.png" alt="HaulPro Logo" width="160"/>
      <h3>HaulPro</h3>
      <ul class="menu">
        <li><a href="dashboard.php"><img src="Image/dashboard.png" alt=""/>Dashboard</a></li>
        <li class="has-submenu">
          <a href="#"><img src="Image/chart.png" alt="" style="width:40px"/>Analysis</a>
          <ul class="submenu" style="padding-left:45px;">
            <li><a href="delivery_performance.php"><img src="Image/continuous-improvement.png" alt="" />Delivery Performance</a></li>
          <li><a href="Revenue_analysis.php"><img src="Image/profit-margin.png" alt="" />Revenue Analysis</a></li>
          <li><a href="fleet_analysis.php"><img src="Image/delivery-truck.png" alt="" />Fleet Efficiency</a></li>
          </ul>
        </li>
        <li><a href="calculationInput.php"><img src="Image/plus.png" alt="" style="width:40px"/>Add Trips</a></li>
        <li><a href="Payment_customer.php"><img src="Image/wallet.png" alt="" style="width:40px"/>Payment Method</a></li>
        <li><a href="Lorry_owner.php"><img src="Image/businessman.png" alt="" style="width:40px"/>Lorry Owner List</a></li>
        <li><a href="lorrylist.php"><img src="Image/truck.png" alt="" style="width:40px"/>Lorry List</a></li>
        <li><a href="Customer_settings.php"><img src="Image/settings.png" alt="" style="width:40px"/>Settings</a></li>
        <li><a href="faq.html"><img src="Image/faq.png" alt="" style="width:40px"/>FAQ</a></li>
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
    <main class="dashboard" id="dashboard">
      <div class="header"><h1>Revenue Analysis</h1></div>
      <div class="content-wrap">
        <!-- KPI Cards -->
        <div class="card-grid">
          <div class="kpi"><h4>Total Revenue</h4><div class="big"><b style="font_size:20px">৳</b>2,450,000</div><div class="sub">↑ +15.7% from last year</div></div>
          <div class="kpi"><h4>Monthly Growth</h4><div class="big">+12.5%</div><div class="sub">Consistent upward trend</div></div>
          <div class="kpi"><h4>Avg Order Value</h4><div class="big">৳1,250</div><div class="sub">1,960 total orders</div></div>
          <div class="kpi"><h4>Profit Margin</h4><div class="big">18.5%</div><div class="sub">Above industry average</div></div>
        </div>

        

        <!-- Revenue Trends -->
        <section id="rev-trends" class="tab active">
          <div class="panel">
            <div class="hd"><h3>Monthly Revenue & Profit Trends</h3></div>
            <div class="body">
              <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
            </div>
          </div>
        </section>

        <!-- By Services -->
        <section id="services" class="tab">
          <div class="service-grid">
            <div class="panel">
              <div class="hd"><h3>Revenue Distribution by Service</h3></div>
              <div class="body"><div class="chart-wrap"><canvas id="serviceChart"></canvas></div></div>
            </div>
            <div class="panel">
              <div class="hd"><h3>Service Revenue Details</h3></div>
              <div class="body">
                <ul class="service-list">
                  <li style="border-left:6px solid var(--info)"><span>Freight Shipping (40%)</span><span>৳980,000</span></li>
                  <li style="border-left:6px solid #14b8a6"><span>Warehousing (25%)</span><span>৳612,500</span></li>
                  <li style="border-left:6px solid #f59e0b"><span>Last Mile Delivery (20%)</span><span>৳490,000</span></li>
                  <li style="border-left:6px solid #ef4444"><span>Express Services (10%)</span><span>৳245,000</span></li>
                  <li style="border-left:6px solid #22c55e"><span>Logistics Consulting (5%)</span><span>৳122,500</span></li>
                </ul>
              </div>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>

  <!-- Chart.js -->
  <script>
    const blue="#6366f1", teal="#14b8a6", orange="#f59e0b", red="#ef4444", green="#22c55e";

    // Revenue Trends
    new Chart(document.getElementById('revenueChart'), {
      type: 'bar',
      data: {
        labels:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
        datasets:[
          {label:"Revenue", data:[180000,190000,200000,210000,220000,230000,240000,250000,260000,270000,280000,290000], backgroundColor:blue, borderRadius:8},
          {label:"Profit", data:[30000,35000,40000,45000,50000,55000,60000,65000,70000,75000,80000,85000], backgroundColor:green, borderRadius:8}
        ]
      },
      options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{position:'bottom'}},
        scales:{y:{beginAtZero:true, ticks:{callback:v=>'৳'+v}}}
      }
    });

    // Services Pie
    new Chart(document.getElementById('serviceChart'), {
      type:'pie',
      data:{
        labels:["Freight Shipping","Warehousing","Last Mile Delivery","Express Services","Logistics Consulting"],
        datasets:[{data:[40,25,20,10,5], backgroundColor:[blue, teal, orange, red, green]}]
      },
      options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}}
    });

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn=>{
      btn.addEventListener('click',()=>{
        document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
      });
    });
  </script>
</body>
</html>
