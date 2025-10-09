<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HaulPro — Fleet Analysis</title>
  <link rel="stylesheet" href="dashboad_style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --brand:#007bff;
      --ok:#28a745;
      --warn:#f2b01e;
      --bad:#dc3545;
      --panel:#ffffff;
      --line:#e9eef5;
      --radius:14px;
      --shadow:0 4px 14px rgba(0,0,0,0.08);
    }

    body {
      font-family:"Inter",system-ui,sans-serif;
      background:#f5f7fb;
      color:#1e293b;
      line-height:1.6;
      margin:0;
    }

    .content-wrap {
      max-width:1700px;
      margin:0 auto;
      padding:28px;
    }

    /* KPI cards */
    .card-grid {
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
      gap:22px;
      margin-bottom:28px;
    }
    .kpi {
      background:var(--panel);
      border-radius:var(--radius);
      padding:22px;
      box-shadow:var(--shadow);
      transition:0.3s ease;
    }
    .kpi:hover {transform:translateY(-4px);}
    .kpi h4 {margin:0 0 8px;font-size:14px;font-weight:600;color:#64748b;}
    .kpi .big {font-size:28px;font-weight:700;margin-bottom:6px;color:#111827;}
    .kpi .sub {font-size:13px;color:#475569;}
    .progress {height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;}
    .bar {height:100%;background:linear-gradient(90deg,#60a5fa,#2563eb);}

    /* Tabs */
    .tabs {
      display:flex;
      gap:14px;
      margin:20px 0 28px;
      flex-wrap:wrap;
    }
    .tab-btn {
      padding:10px 20px;
      border:1px solid #e2e8f0;
      border-radius:999px;
      background:#fff;
      color:#334155;
      font-weight:600;
      cursor:pointer;
      transition:all 0.25s ease;
    }
    .tab-btn:hover {background:#f1f5f9;}
    .tab-btn.active {
      background:var(--brand);
      color:#fff;
      border-color:var(--brand);
      box-shadow:var(--shadow);
    }
    .tab {display:none;}
    .tab.active {display:block;animation:fade .3s ease;}
    @keyframes fade {from{opacity:0}to{opacity:1}}

    /* Panels */
    .panel {
      background:var(--panel);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      margin-bottom:28px;
      overflow:hidden;
    }
    .panel .hd {
      padding:16px 20px;
      border-bottom:1px solid var(--line);
      background:#fafbfc;
    }
    .panel .hd h3 {
      margin:0;
      font-size:16px;
      font-weight:600;
      color:#1e293b;
    }
    .panel .body {padding:20px;}

    /* Charts */
    .chart-wrap {position:relative;width:100%;height:320px;}
    .chart-wrap canvas {width:100%!important;height:100%!important;}
    .chart-row {display:grid;grid-template-columns:1.2fr .8fr;gap:20px;}

    /* Table */
    table {width:100%;border-collapse:collapse;}
    th,td {
      padding:12px 14px;
      border-bottom:1px solid var(--line);
      font-size:14px;
      text-align:left;
    }
    th {background:#f8fafc;font-weight:600;}
    .eff {display:flex;align-items:center;gap:10px;}
    .meter {flex:1;height:8px;border-radius:999px;background:#e2e8f0;overflow:hidden;}
    .meter span {display:block;height:100%;background:linear-gradient(90deg,#60a5fa,#2563eb);}
    .badge {padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700;background:#eef2ff;color:var(--brand);}
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
        <li class="has-submenu active">
          <a href="#"><img src="Image/chart.png" alt="" style="width:40px"/>Analysis</a>
          <ul class="submenu" style="padding-left:45px;">
            <li><a href="delivery_performance.php"><img src="Image/continuous-improvement.png" alt="" />Delivery Performance</a></li>
          <li><a href="Revenue_analysis.php"><img src="Image/profit-margin.png" alt="" />Revenue Analysis</a></li>
          <li><a href="fleet_analysis.php"><img src="Image/delivery-truck.png" alt="" />Fleet Efficiency</a></li>
          </ul>
        </li>
        <li><a href="calculationInput.php"><img src="Image/plus.png" alt=""/>Add Trips</a></li>
        <li><a href="Payment_customer.php"><img src="Image/wallet.png" alt=""/>Payment Method</a></li>
        <li><a href="Lorry_owner.php"><img src="Image/businessman.png" alt=""/>Lorry Owner List</a></li>
        <li><a href="lorrylist.php"><img src="Image/truck.png" alt=""/>Lorry List</a></li>
        <li><a href="Customer_settings.php"><img src="Image/settings.png" alt=""/>Settings</a></li>
        <li><a href="faq.html"><img src="Image/faq.png" alt=""/>FAQ</a></li>
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
      <div class="header"><h1>Fleet Analysis</h1></div>
      <div class="content-wrap">
        <!-- KPI Cards -->
        <div class="card-grid">
          <div class="kpi"><h4>Fleet Utilization</h4><div class="big">91%</div><div class="progress"><div class="bar" style="width:91%"></div></div><div class="sub">↑ +5.2% from last month</div></div>
          <div class="kpi"><h4>Fuel Efficiency</h4><div class="big">8.2 km/L</div><div class="badge">12.8% better vs industry</div></div>
          <div class="kpi"><h4>Active Vehicles</h4><div class="big">142</div><div class="sub">of 156 (91% operational)</div></div>
          <div class="kpi"><h4>Maintenance Cost</h4><div class="big">৳284,750</div><div class="sub">৳100.00/1000km</div></div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
          <button class="tab-btn active" data-tab="eff">Efficiency Trends</button>
          <button class="tab-btn" data-tab="veh">Vehicle Analysis</button>
          <button class="tab-btn" data-tab="route">Route Performance</button>
          <button class="tab-btn" data-tab="maint">Maintenance</button>
        </div>

        <!-- Efficiency Trends -->
        <section id="eff" class="tab active">
          <div class="panel">
            <div class="hd"><h3>Fleet Efficiency Trends</h3></div>
            <div class="body"><div class="chart-wrap"><canvas id="effLine"></canvas></div></div>
          </div>
        </section>

        <!-- Vehicle Analysis -->
        <section id="veh" class="tab">
          <div class="panel">
            <div class="hd"><h3>Fleet Composition & Performance</h3></div>
            <div class="body">
              <div class="chart-row">
                <div class="panel"><div class="hd"><h3>Fleet Composition</h3></div><div class="body"><div class="chart-wrap"><canvas id="fleetPie"></canvas></div></div></div>
                <div class="panel"><div class="hd"><h3>Vehicle Type Performance</h3></div><div class="body" style="padding:0">
                  <table>
                    <thead><tr><th>Type</th><th>Count</th><th>Utilization</th><th>Efficiency</th></tr></thead>
                    <tbody>
                      <tr><td>Heavy Trucks</td><td>45</td><td>94%</td><td>6.8 km/L</td></tr>
                      <tr><td>Medium Trucks</td><td>62</td><td>89%</td><td>8.5 km/L</td></tr>
                      <tr><td>Light Trucks</td><td>35</td><td>87%</td><td>12.2 km/L</td></tr>
                      <tr><td>Vans</td><td>14</td><td>92%</td><td>15.8 km/L</td></tr>
                    </tbody>
                  </table>
                </div></div>
              </div>
            </div>
          </div>
        </section>

        <!-- Route Performance -->
        <section id="route" class="tab">
          <div class="panel">
            <div class="hd"><h3>Route Performance Analysis</h3></div>
            <div class="body" style="padding:0">
              <table>
                <thead><tr><th>Route</th><th>Distance (km)</th><th>Avg Time (hrs)</th><th>Fuel Usage (L)</th><th>Efficiency</th><th>Total Trips</th></tr></thead>
                <tbody>
                  <tr><td>Route A-1</td><td>245</td><td>4.2</td><td>28.5</td><td class="eff"><div class="meter"><span style="width:95%"></span></div>95%</td><td>156</td></tr>
                  <tr><td>Route B-2</td><td>180</td><td>3.1</td><td>21.2</td><td class="eff"><div class="meter"><span style="width:92%"></span></div>92%</td><td>134</td></tr>
                  <tr><td>Route C-3</td><td>320</td><td>5.8</td><td>38.4</td><td class="eff"><div class="meter"><span style="width:88%"></span></div>88%</td><td>98</td></tr>
                  <tr><td>Route D-4</td><td>150</td><td>2.5</td><td>17.8</td><td class="eff"><div class="meter"><span style="width:96%"></span></div>96%</td><td>187</td></tr>
                  <tr><td>Route E-5</td><td>280</td><td>4.9</td><td>33.2</td><td class="eff"><div class="meter"><span style="width:90%"></span></div>90%</td><td>112</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Maintenance -->
        <section id="maint" class="tab">
          <div class="chart-row">
            <div class="panel"><div class="hd"><h3>Maintenance Trends</h3></div><div class="body"><div class="chart-wrap"><canvas id="maintBar"></canvas></div></div></div>
            <div class="panel"><div class="hd"><h3>Maintenance Costs & Downtime</h3></div><div class="body"><div class="chart-wrap"><canvas id="costLine"></canvas></div></div></div>
          </div>
        </section>
      </div>
    </main>
  </div>

  <script>
    // Efficiency line chart
    new Chart(document.getElementById('effLine'), {
      type:'line',
      data:{
        labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets:[
          {label:'Utilization %', data:[89,88,92,90,91,93,94,91,90,94,92,91], borderColor:'#007bff', backgroundColor:'rgba(0,123,255,.2)', fill:true, tension:.4},
          {label:'Fuel Efficiency (km/L)', data:[6.5,6.6,6.7,6.9,7.0,7.1,7.0,6.9,6.9,7.0,7.1,7.2], borderColor:'#28a745', backgroundColor:'rgba(40,167,69,.2)', fill:true, tension:.4, yAxisID:'y1'}
        ]
      },
      options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{position:'bottom'}},
        scales:{
          y:{beginAtZero:false, ticks:{callback:v=>v+'%'}},
          y1:{position:'right', beginAtZero:false, grid:{drawOnChartArea:false}}
        }
      }
    });

    // Fleet composition pie
    new Chart(document.getElementById('fleetPie'), {
      type:'doughnut',
      data:{labels:['Heavy','Medium','Light','Vans'],datasets:[{data:[45,62,35,14],backgroundColor:['#007bff','#28a745','#f2b01e','#dc3545']}]},
      options:{responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{legend:{position:'bottom'}}}
    });

    // Maintenance bar
    new Chart(document.getElementById('maintBar'), {
      type:'bar',
      data:{labels:['Jan','Feb','Mar','Apr','May','Jun'],datasets:[{label:'Scheduled',data:[12,15,19,14,16,13],backgroundColor:'#28a745',borderRadius:6},{label:'Unscheduled',data:[8,5,7,6,4,9],backgroundColor:'#f2b01e',borderRadius:6}]},
      options:{responsive:true, maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}
    });

    // Cost & downtime line
    new Chart(document.getElementById('costLine'), {
      type:'line',
      data:{labels:['Jan','Feb','Mar','Apr','May','Jun'],datasets:[{label:'Cost (৳)',data:[21000,19000,25500,21500,20500,23500],borderColor:'#007bff',fill:true,backgroundColor:'rgba(0,123,255,.2)',tension:.4},{label:'Downtime (hrs)',data:[48,32,44,39,28,52],borderColor:'#dc3545',fill:true,backgroundColor:'rgba(220,53,69,.2)',tension:.4,yAxisID:'y1'}]},
      options:{responsive:true, maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},scales:{y1:{position:'right',grid:{drawOnChartArea:false}}}}
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
