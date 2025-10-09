<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HaulPro — Delivery Performance</title>
  <link rel="stylesheet" href="dashboad_style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root{
      --brand:#007bff; --ok:#28a745; --warn:#f2b01e; --bad:#dc3545;
      --panel:#fff; --line:#e9eef5; --radius:12px; --shadow:0 3px 10px rgba(16,24,40,.08);
      --maxw:1700px;
    }
    body{font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; background:#f4f6fa; color:#1e293b;}

    .dashboard{width:100%;}
    .content-wrap{max-width:var(--maxw); margin:0 auto; padding:20px;}

    /* pill-style tabs */
    .tabs{display:flex; gap:12px; margin:10px 0 20px}
    .tab-btn{
      padding:10px 20px;
      border:1px solid #e2e8f0;
      border-radius:999px; /* pill look */
      background:#fff;
      color:#334155;
      font-weight:600;
      cursor:pointer;
      transition:all .25s;
      box-shadow:0 1px 3px rgba(0,0,0,0.05);
    }
    .tab-btn:hover{background:#f1f5f9;}
    .tab-btn.active{
      background:var(--brand);
      color:#fff;
      border-color:var(--brand);
      box-shadow:0 3px 10px rgba(0,123,255,0.3);
    }
    .tab{display:none} 
    .tab.active{display:block; animation:fade .35s ease}
    @keyframes fade{from{opacity:0; transform:translateY(10px);}to{opacity:1; transform:translateY(0);}}

    /* cards */
    .card-grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:16px; margin-bottom:16px}
    .kpi{background:var(--panel); border-radius:var(--radius); padding:18px; box-shadow:var(--shadow); transition:.25s}
    .kpi:hover{transform:translateY(-4px); box-shadow:0 6px 15px rgba(16,24,40,.12);}
    .kpi h4{margin:0 0 6px; font-size:14px; color:#475569;}
    .kpi .big{font-size:22px; font-weight:700; margin-bottom:6px; color:#0f172a;}
    .progress{height:8px; background:#edf2f7; border-radius:999px; overflow:hidden}
    .bar{height:100%; background:var(--ok); transition:width .6s ease;}

    /* panels */
    .panel{background:var(--panel); border-radius:var(--radius); box-shadow:var(--shadow); margin-bottom:20px; overflow:hidden}
    .panel .hd{padding:14px 16px; border-bottom:1px solid var(--line); background:#f9fafb;}
    .panel .hd h3{margin:0; font-size:16px; color:#1e293b;}
    .panel .body{padding:16px}

    /* chart sizing */
    .chart-wrap{position:relative; width:100%}
    .h200{height:200px}
    .h220{height:220px}
    .h320{height:320px}
    .chart-wrap canvas{position:absolute; inset:0; width:100% !important; height:100% !important}

    /* table */
    table{width:100%; border-collapse:collapse}
    th,td{padding:12px 14px; border-bottom:1px solid var(--line); font-size:14px; text-align:left}
    th{background:#f8fafc; font-weight:600; color:#334155;}

    /* performance bars */
    .perf-row{display:flex; align-items:center; gap:10px}
    .meter{flex:1; height:8px; background:#edf2f7; border-radius:999px; overflow:hidden}
    .meter>span{display:block; height:100%; transition:width .6s ease;}
    .g{background:var(--ok)} .y{background:var(--warn)} .r{background:var(--bad)}

    /* side-by-side charts */
    .dual-charts{display:grid; grid-template-columns:1fr 1fr; gap:20px;}
    .dual-charts .panel{margin-bottom:0;}
    
    /* map */
    #region-map{height:320px; width:100%; border-radius:var(--radius); box-shadow:var(--shadow)}
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
      <div class="header"><h1>Delivery Performance</h1></div>
      <div class="content-wrap">
        <!-- Tabs -->
        <div class="tabs">
          <button class="tab-btn active" data-tab="overview">Overview</button>
          <button class="tab-btn" data-tab="carriers">Carriers</button>
          <button class="tab-btn" data-tab="regions">Regions</button>
        </div>

        <!-- OVERVIEW -->
        <section id="overview" class="tab active">
          <div class="card-grid">
            <div class="kpi"><h4>On-Time Delivery Rate</h4><div class="big">85.9%</div><div class="progress"><div class="bar" style="width:86%"></div></div></div>
            <div class="kpi"><h4>Average Delivery Time</h4><div class="big">2.3 days</div><div class="sub">Fastest 1.1 • Slowest 4.7</div></div>
            <div class="kpi"><h4>Customer Satisfaction</h4><div class="big">4.2/5</div><div class="sub">↑ +0.3 from last month</div></div>
          </div>

          <div class="panel">
            <div class="hd"><h3>Delivery Performance Trends</h3></div>
            <div class="body">
              <div class="chart-wrap h200"><canvas id="trendChart"></canvas></div>
            </div>
          </div>

          <div class="dual-charts">
            <div class="panel">
              <div class="hd"><h3>On-Time vs Late Deliveries</h3></div>
              <div class="body"><div class="chart-wrap h220"><canvas id="donutChart"></canvas></div></div>
            </div>
            <div class="panel">
              <div class="hd"><h3>Delay Reasons</h3></div>
              <div class="body"><div class="chart-wrap h220"><canvas id="delayChart"></canvas></div></div>
            </div>
          </div>
          <div class="panel">
            <div class="hd"><h3>Recent Deliveries</h3></div>
            <div class="body" style="padding:0">
              <table>
                <thead><tr><th>ID</th><th>Origin</th><th>Destination</th><th>Status</th><th>Time</th></tr></thead>
                <tbody>
                  <tr><td>#D1234</td><td>Dhaka</td><td>Chittagong</td><td>On-Time</td><td>2025-09-10</td></tr>
                  <tr><td>#D1235</td><td>Khulna</td><td>Dhaka</td><td>Late</td><td>2025-09-11</td></tr>
                  <tr><td>#D1236</td><td>Rajshahi</td><td>Sylhet</td><td>On-Time</td><td>2025-09-12</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- CARRIERS -->
        <section id="carriers" class="tab">
          <div class="panel">
            <div class="hd"><h3>Carrier Performance</h3></div>
            <div class="body">
              <div class="chart-wrap h320"><canvas id="carrierChart"></canvas></div>
            </div>
          </div>
          <div class="panel">
            <div class="hd"><h3>Carrier Details</h3></div>
            <div class="body" style="padding:0">
              <table>
                <thead>
                  <tr><th>Carrier</th><th>On-Time %</th><th>Total Deliveries</th><th>Avg. Delay</th><th>Performance</th></tr>
                </thead>
                <tbody>
                  <tr><td>Express Logistics</td><td>92%</td><td>420</td><td>0.8 days</td><td><div class="perf-row"><div class="meter"><span class="g" style="width:92%"></span></div><span>92%</span></div></td></tr>
                  <tr><td>Swift Carriers</td><td>88%</td><td>356</td><td>1.2 days</td><td><div class="perf-row"><div class="meter"><span class="y" style="width:88%"></span></div><span>88%</span></div></td></tr>
                  <tr><td>Global Shipping</td><td>79%</td><td>284</td><td>1.9 days</td><td><div class="perf-row"><div class="meter"><span class="r" style="width:79%"></span></div><span>79%</span></div></td></tr>
                  <tr><td>Metro Delivery</td><td>84%</td><td>188</td><td>1.5 days</td><td><div class="perf-row"><div class="meter"><span class="y" style="width:84%"></span></div><span>84%</span></div></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- REGIONS -->
        <section id="regions" class="tab">
          <div class="panel">
            <div class="hd"><h3>Regional Performance</h3></div>
            <div class="body"><div class="chart-wrap h200"><canvas id="regionChart"></canvas></div></div>
          </div>
          <div class="panel">
            <div class="hd"><h3>Delivery Performance Map</h3></div>
            <div class="body"><div id="region-map"></div></div>
          </div>
          <div class="panel">
            <div class="hd"><h3>Regional Details</h3></div>
            <div class="body" style="padding:0">
              <table>
                <thead><tr><th>Region</th><th>On-Time %</th><th>Total Deliveries</th><th>Avg. Delay</th><th>Top Issues</th></tr></thead>
                <tbody>
                  <tr><td>North</td><td>88%</td><td>312</td><td>1.2 days</td><td>Weather, Traffic</td></tr>
                  <tr><td>South</td><td>82%</td><td>298</td><td>1.8 days</td><td>Hurricane, Port</td></tr>
                  <tr><td>East</td><td>86%</td><td>342</td><td>1.4 days</td><td>Traffic, Infra</td></tr>
                  <tr><td>West</td><td>87%</td><td>296</td><td>1.3 days</td><td>Wildfires, Port</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>

  <!-- Google Maps -->
  <script>
    function initMap() {
      const map = new google.maps.Map(document.getElementById("region-map"), {
        zoom: 6, center: { lat: 23.685, lng: 90.3563 }
      });
      const regions = [
        {name:"North", pos:{lat:25.7,lng:89.3}},
        {name:"South", pos:{lat:22.7,lng:90.4}},
        {name:"East",  pos:{lat:24.9,lng:91.9}},
        {name:"West",  pos:{lat:24.4,lng:88.6}},
      ];
      regions.forEach(r => new google.maps.Marker({position:r.pos, map, title:r.name}));
    }
  </script>
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB9OwltS3fiIj-fEbmKAPcYH39lj-mOZmM&callback=initMap" async defer></script>

  <!-- Chart.js -->
  <script>
    const blue="#007bff", teal="#20c997", orange="#ff914d", green="#28a745", red="#dc3545";

    new Chart(document.getElementById('trendChart'),{
      type:'bar',
      data:{labels:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
      datasets:[{label:"On-Time %",data:[90,92,88,91,93,94,90,92,95,93,91,94],backgroundColor:blue,borderRadius:6},
      {label:"Late %",data:[18,15,21,18,15,14,18,16,13,15,18,19],backgroundColor:orange,borderRadius:6}]},
      options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,max:100}}}
    });

    new Chart(document.getElementById('donutChart'),{
      type:'doughnut', data:{labels:["On-Time","Late"],datasets:[{data:[86,14],backgroundColor:[green,red]}]},
      options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom'}}}
    });

    new Chart(document.getElementById('delayChart'),{
      type:'pie', data:{labels:["Weather","Traffic","Vehicle","Staff","Other"],datasets:[{data:[32,28,15,12,13],backgroundColor:["#34d399","#60a5fa","#f59e0b","#a78bfa","#94a3b8"]}]},
      options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}
    });

    new Chart(document.getElementById('carrierChart'),{
      type:'bar',
      data:{labels:["Express Logistics","Swift Carriers","Global Shipping","Metro Delivery"],
      datasets:[{label:"On-Time %",data:[92,88,79,84],backgroundColor:blue,borderRadius:6},
      {label:"Total Deliveries",data:[420,356,284,188],backgroundColor:teal,borderRadius:6,yAxisID:'y1'}]},
      options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},
      scales:{y:{beginAtZero:true,max:100,ticks:{callback:v=>v+"%"}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false},ticks:{stepSize:100}}}}
    });

    new Chart(document.getElementById('regionChart'),{
      type:'bar',
      data:{labels:["North","South","East","West"],
      datasets:[{label:"On-Time %",data:[88,82,86,87],backgroundColor:blue,borderRadius:6},
      {label:"Total Deliveries",data:[312,298,342,296],backgroundColor:teal,borderRadius:6,yAxisID:'y1'}]},
      options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},
      scales:{y:{beginAtZero:true,max:100,ticks:{callback:v=>v+"%"}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false},ticks:{stepSize:100}}}}
    });

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn=>{
      btn.addEventListener('click',()=>{
        document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
        window.scrollTo({top:0,behavior:'smooth'});
      });
    });
  </script>
</body>
</html>
