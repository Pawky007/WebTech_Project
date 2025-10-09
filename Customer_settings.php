<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Customer Settings — HaulPro</title>
  <link rel="stylesheet" href="dashboad_style.css" />
  <style>
    :root{
      --primary:#2563eb; --primary-hover:#1d4ed8;
      --bg:#f5f7fb; --surface:#fff; --border:#e5e7eb;
      --text:#0f172a; --muted:#64748b;
      --radius:12px; --shadow:0 4px 12px rgba(0,0,0,0.08);
      --ok:#16a34a; --warn:#f59e0b; --bad:#dc2626;
    }
    *{box-sizing:border-box}
    body{margin:0; font-family:'Segoe UI',sans-serif; background:var(--bg); color:var(--text);}
    .container{display:flex;}
    main{flex:1; padding:28px;}
    .header h1{margin:0 0 20px; font-size:28px; color:var(--primary);}

    /* Pill tabs */
    .tabs{display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap;}
    .tab-btn{
      padding:10px 18px; border:1px solid var(--border);
      background:#fff; color:#0f172a; font-weight:600;
      border-radius:999px; cursor:pointer; transition:.25s;
    }
    .tab-btn.active{
      background:var(--primary); color:#fff; border-color:var(--primary);
      box-shadow:0 6px 16px rgba(37,99,235,.25);
    }

    .tab{display:none;}
    .tab.active{display:block; animation:fade .3s ease;}
    @keyframes fade{from{opacity:0; transform:translateY(8px);} to{opacity:1; transform:translateY(0);}}

    .card{
      background:var(--surface); border:1px solid var(--border);
      border-radius:var(--radius); box-shadow:var(--shadow);
      padding:24px; margin-bottom:20px;
    }
    h2{font-size:20px; margin:0 0 16px;}
    label{display:block; font-weight:600; margin-bottom:6px; color:var(--muted);}
    input,select{
      width:100%; padding:12px; font-size:15px;
      border:1px solid var(--border); border-radius:10px; margin-bottom:14px; background:#fff;
    }
    .btn{background:var(--primary); color:#fff; padding:12px 20px; border:none;
      border-radius:12px; font-weight:600; cursor:pointer; transition:.25s; position:relative;}
    .btn:hover{background:var(--primary-hover);}
    .btn.unsaved::after{
      content:"•"; position:absolute; top:-6px; right:-8px; width:16px; height:16px;
      background:#ef4444; color:#fff; border-radius:999px; display:flex; align-items:center; justify-content:center; font-weight:700;
    }
    #map{height:300px; width:100%; border-radius:12px; margin-bottom:12px; box-shadow:var(--shadow);}

    /* Toggles */
    .toggle-row{display:flex; align-items:center; justify-content:space-between; gap:16px; padding:8px 0;}
    .toggle-row .label{font-weight:600; color:var(--muted);}
    .toggle{display:inline-flex; align-items:center; cursor:pointer; user-select:none;}
    .toggle input{position:absolute; opacity:0; width:0; height:0;}
    .switch{
      position:relative; width:54px; height:30px; border-radius:999px;
      background:#e5e7eb; border:1px solid var(--border); transition:.2s;
      display:inline-block;
    }
    .switch::after{
      content:""; position:absolute; top:50%; left:3px; transform:translateY(-50%);
      width:24px; height:24px; border-radius:999px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.2);
      transition:.2s;
    }
    .toggle input:checked + .switch{background:var(--primary); border-color:var(--primary);}
    .toggle input:checked + .switch::after{left:27px;}

    /* Helpers */
    .grid-2{display:grid; grid-template-columns:1fr 1fr; gap:12px;}
    .hint{font-size:12px; color:var(--muted); margin:-6px 0 12px;}
    .text-btn{
      background:#f1f5f9; border:1px solid var(--border); padding:8px 12px;
      border-radius:10px; cursor:pointer; font-weight:600; margin-bottom:12px;
    }

    /* Password strength */
    .meter{height:8px; background:#e5e7eb; border-radius:999px; overflow:hidden; margin:-4px 0 12px;}
    .meter > span{display:block; height:100%; width:0%; background:var(--bad); transition:width .25s, background .25s; border-radius:999px;}
    .meter.ok > span{background:var(--ok);}
    .meter.warn > span{background:var(--warn);}

    /* Logo preview */
    .logo-preview{width:120px; height:120px; border:1px dashed var(--border); border-radius:12px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:12px; background:#fff;}
    .logo-preview img{max-width:100%; max-height:100%; display:block;}

    /* Toast */
    .toast{
      position:fixed; right:24px; bottom:24px; padding:12px 16px;
      background:#111827; color:#fff; border-radius:10px; box-shadow:var(--shadow); opacity:0; transform:translateY(10px); transition:.2s;
    }
    .toast.show{opacity:1; transform:translateY(0);}

    .row-actions{display:flex; gap:10px; flex-wrap:wrap;}
  </style>
</head>
<body>
<div class="container">
  <!-- Sidebar (unchanged) -->
  <aside class="sidebar" id="sidebar">
    <img src="Image/Logo.png" alt="HaulPro Logo" width="160"/>
    <h3>HaulPro</h3>
    <ul class="menu">
      <li><a href="dashboard.php"><img src="Image/dashboard.png" alt=""/>Dashboard</a></li>
      <li class="has-submenu">
        <a href="#"><img src="Image/chart.png" alt=""/>Analysis</a>
        <ul class="submenu" style="padding-left:45px;">
          <li><a href="delivery_performance.php"><img src="Image/continuous-improvement.png" alt=""/>Delivery Performance</a></li>
          <li><a href="Revenue_analysis.php"><img src="Image/profit-margin.png" alt=""/>Revenue Analysis</a></li>
          <li><a href="fleet_analysis.php"><img src="Image/delivery-truck.png" alt=""/>Fleet Efficiency</a></li>
        </ul>
      </li>
      <li><a href="calculationInput.php"><img src="Image/plus.png" alt=""/>Add Trips</a></li>
      <li><a href="Payment_customer.php"><img src="Image/wallet.png" alt=""/>Payment Method</a></li>
      <li><a href="Lorry_owner.php"><img src="Image/businessman.png" alt=""/>Lorry Owner List</a></li>
      <li><a href="lorrylist.php"><img src="Image/truck.png" alt=""/>Lorry List</a></li>
      <li><a href="customer_settings.php" class="active"><img src="Image/settings.png" alt=""/>Settings</a></li>
      <li><a href="faq.html"><img src="Image/faq.png" alt=""/>FAQ</a></li>
      <li><a href="ai_chatbot.php"><img src="Image/robot.png" alt="" style="width:40px" />AI Chat Bot</a></li>
      <li><a href="login.php"><img src="Image/right-arrow.png" alt="" style="width:40px" />Log Out</a></li>
    </ul>
  </aside>

  <!-- Main -->
  <main>
    <div class="header"><h1>⚙ Customer Settings</h1></div>

    <!-- Tabs -->
    <div class="tabs">
      <button class="tab-btn active" data-tab="profile">Profile</button>
      <button class="tab-btn" data-tab="preferences">Preferences</button>
      <button class="tab-btn" data-tab="notifications">Notifications</button>
      <button class="tab-btn" data-tab="security">Security</button>
    </div>

    <!-- Profile -->
    <section id="profile" class="tab active">
      <div class="card">
        <h2>Profile Settings</h2>

        <label>Company Name</label>
        <input id="companyName" type="text" value="HaulPro Logistics Ltd.">

        <label>Logo</label>
        <div class="logo-preview" id="logoPreview"><span style="color:#94a3b8">No logo</span></div>
        <input id="logoInput" type="file" accept="image/*">

        <label>Full Name</label><input id="fullName" type="text" value="John Doe">
        <label>Email</label><input id="email" type="email" value="john.doe@haulpro.com">
        <label>Phone</label><input id="phone" type="text" value="+8801712345678">

        <label>Company Location</label>
        <div id="map"></div>
        <div class="row-actions">
          <small>Click "Change Location" and then click on the map to set new location.</small>
          <button type="button" class="text-btn" id="useMyLocation">📍 Use My Location</button>
        </div>
        <input type="hidden" id="lat" value="23.8103">
        <input type="hidden" id="lng" value="90.4125">
        <div class="row-actions">
          <button type="button" class="btn" id="changeLocationBtn">📍 Change Customer Location</button>
          <button class="btn" id="saveProfile">💾 Save Profile</button>
        </div>
      </div>
    </section>

    <!-- Preferences -->
    <section id="preferences" class="tab">
      <div class="card">
        <h2>Preferences</h2>
        <label>Default Currency</label>
        <select id="currency">
          <option>BDT</option>
          <option selected>USD</option>
        </select>

        <label>Distance Unit</label>
        <select id="unit">
          <option selected>Kilometers</option>
          <option>Miles</option>
        </select>

        <label>Time Zone</label><input id="tz" type="text" value="Asia/Dhaka">

        <label>Language</label>
        <select id="lang">
          <option selected>English</option>
          <option>বাংলা</option>
        </select>

        <div class="row-actions">
          <button class="btn" id="savePrefs">💾 Save Preferences</button>
          <!--<button class="text-btn" id="exportSettings">⬇️ Export Settings (JSON)</button>
          <label class="text-btn" style="margin:0;">
            ⬆️ Import Settings
            <input type="file" id="importSettings" accept="application/json" style="display:none;">
          </label>
        </div>-->
      </div>
    </section>

    <!-- Notifications -->
    <section id="notifications" class="tab">
      <div class="card">
        <h2>Notification Settings</h2>

        <div class="toggle-row">
          <span class="label">Email Notifications</span>
          <label class="toggle">
            <input type="checkbox" id="emailNotif" checked />
            <span class="switch" aria-hidden="true"></span>
          </label>
        </div>

        <div class="toggle-row">
          <span class="label">SMS Notifications</span>
          <label class="toggle">
            <input type="checkbox" id="smsNotif" />
            <span class="switch" aria-hidden="true"></span>
          </label>
        </div>

        <div class="toggle-row">
          <span class="label">App Push</span>
          <label class="toggle">
            <input type="checkbox" id="pushNotif" checked />
            <span class="switch" aria-hidden="true"></span>
          </label>
        </div>

        <button class="btn" id="saveNotifs">💾 Save Notifications</button>
      </div>
    </section>

    <!-- Security -->
    <section id="security" class="tab">
      <div class="card">
        <h2>Security</h2>

        <div class="grid-2">
          <div>
            <label>Password</label>
            <input type="password" id="password" autocomplete="new-password" placeholder="Enter password" />
          </div>
          <div>
            <label>Confirm Password</label>
            <input type="password" id="confirmPassword" autocomplete="new-password" placeholder="Re-enter password" />
          </div>
        </div>
        <div class="meter" id="meter"><span></span></div>
        <button class="text-btn" id="togglePwd">Show Passwords</button>
        <div class="hint" id="pwHint">Use at least 8 characters, with a number & symbol.</div>

        <div class="toggle-row">
          <span class="label">Enable Two-Factor Authentication (2FA)</span>
          <label class="toggle">
            <input type="checkbox" id="twoFA" checked />
            <span class="switch" aria-hidden="true"></span>
          </label>
        </div>

        <button class="btn" id="saveSecurity">💾 Save Security</button>
      </div>
    </section>
  </main>
</div>

<div class="toast" id="toast">Saved.</div>

<!-- Google Maps -->
<script>
  /* ===== Map code EXACTLY from your working snippet (kept intact) ===== */
  let marker, map;
  let changeMode = false;

  function initMap() {
    const lat = parseFloat(document.getElementById("lat").value);
    const lng = parseFloat(document.getElementById("lng").value);
    map = new google.maps.Map(document.getElementById("map"), {
      zoom: 12,
      center: { lat, lng }
    });
    marker = new google.maps.Marker({
      position: { lat, lng },
      map,
      draggable: false,
      icon: { url: "http://maps.google.com/mapfiles/ms/icons/red-dot.png" }
    });

    map.addListener("click", function(e) {
      if (changeMode) {
        marker.setPosition(e.latLng);
        document.getElementById("lat").value = e.latLng.lat().toFixed(6);
        document.getElementById("lng").value = e.latLng.lng().toFixed(6);
        changeMode = false;
        document.getElementById("changeLocationBtn").innerText = "📍 Change Customer Location";
      }
    });
  }
  /* =================================================================== */
</script>

<script>
  // Small helpers
  function toast(msg="Saved.") {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(()=> t.classList.remove('show'), 1600);
  }
  function pwScore(pw){
    let s = 0;
    if (pw.length >= 8) s++;
    if (/[A-Z]/.test(pw)) s++;
    if (/[a-z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return s; // 0..5
  }
  function renderMeter(score){
    const m = document.getElementById('meter');
    const bar = m.querySelector('span');
    const pct = Math.min(100, score*20);
    bar.style.width = pct + '%';
    m.classList.remove('ok','warn');
    if (score >= 4) m.classList.add('ok');
    else if (score >= 2) m.classList.add('warn');
  }
  function markUnsaved(btn){ btn.classList.add('unsaved'); }
  function clearUnsaved(btn){ btn.classList.remove('unsaved'); }

  document.addEventListener("DOMContentLoaded", ()=>{
    // Tabs
    document.querySelectorAll(".tab-btn").forEach(btn=>{
      btn.addEventListener("click", ()=>{
        document.querySelectorAll(".tab-btn").forEach(b=>b.classList.remove("active"));
        document.querySelectorAll(".tab").forEach(t=>t.classList.remove("active"));
        btn.classList.add("active");
        const target = document.getElementById(btn.dataset.tab);
        target.classList.add("active");
        // When showing Profile, nudge the map tiles to lay out if already initialized
        if (btn.dataset.tab === "profile" && window.google && window.map){
          setTimeout(()=>{
            try { google.maps.event.trigger(map, "resize"); if (marker) map.setCenter(marker.getPosition()); } catch(e){}
          }, 50);
        }
      });
    });

    // Location change button (same behavior as your snippet)
    document.getElementById("changeLocationBtn").addEventListener("click", ()=>{
      changeMode = true;
      document.getElementById("changeLocationBtn").innerText = "✅ Click on the map to set new location";
    });

    // Geolocate to set pin quickly
    document.getElementById("useMyLocation").addEventListener("click", ()=>{
      if (!navigator.geolocation) { alert("Geolocation not supported."); return; }
      navigator.geolocation.getCurrentPosition((pos)=>{
        const lat = pos.coords.latitude, lng = pos.coords.longitude;
        document.getElementById('lat').value = lat.toFixed(6);
        document.getElementById('lng').value = lng.toFixed(6);
        if (window.map && window.google){
          const p = new google.maps.LatLng(lat, lng);
          marker.setPosition(p); map.setCenter(p); map.setZoom(14);
        }
      }, ()=> alert("Unable to fetch your location."));
    });

    // Logo preview
    document.getElementById('logoInput').addEventListener('change', (e)=>{
      const file = e.target.files[0];
      const box = document.getElementById('logoPreview');
      if (!file){ box.innerHTML = '<span style="color:#94a3b8">No logo</span>'; return; }
      const img = document.createElement('img');
      img.onload = ()=> { box.innerHTML = ''; box.appendChild(img); };
      img.src = URL.createObjectURL(file);
      markUnsaved(document.getElementById('saveProfile'));
    });

    // Unsaved indicators
    ['companyName','fullName','email','phone'].forEach(id=>{
      const el = document.getElementById(id);
      el.addEventListener('input', ()=> markUnsaved(document.getElementById('saveProfile')));
    });
    ['currency','unit','tz','lang'].forEach(id=>{
      const el = document.getElementById(id);
      el.addEventListener('input', ()=> markUnsaved(document.getElementById('savePrefs')));
      el.addEventListener('change', ()=> markUnsaved(document.getElementById('savePrefs')));
    });
    ['emailNotif','smsNotif','pushNotif'].forEach(id=>{
      document.getElementById(id).addEventListener('change', ()=> markUnsaved(document.getElementById('saveNotifs')));
    });
    ['password','confirmPassword','twoFA'].forEach(id=>{
      document.getElementById(id).addEventListener('input', ()=> markUnsaved(document.getElementById('saveSecurity')));
      document.getElementById(id).addEventListener('change', ()=> markUnsaved(document.getElementById('saveSecurity')));
    });

    // SECURITY: show/hide + strength + match
    const pw = document.getElementById('password');
    const cpw = document.getElementById('confirmPassword');
    const hint = document.getElementById('pwHint');
    pw.addEventListener('input', ()=>{
      const s = pwScore(pw.value); renderMeter(s);
      hint.textContent = (s>=4) ? 'Strong password 👍' : 'Use at least 8 characters, with a number & symbol.';
    });
    cpw.addEventListener('input', ()=>{
      if (cpw.value && pw.value !== cpw.value) hint.textContent = 'Passwords do not match.'; else if (!pw.value && !cpw.value) hint.textContent = 'Use at least 8 characters, with a number & symbol.'; else hint.textContent = '';
    });
    document.getElementById('togglePwd').addEventListener('click', ()=>{
      const show = pw.type === 'password';
      pw.type = show ? 'text' : 'password';
      cpw.type = show ? 'text' : 'password';
      document.getElementById('togglePwd').textContent = show ? 'Hide Passwords' : 'Show Passwords';
    });

    // Save buttons (localStorage demo persistence)
    document.getElementById('saveProfile').addEventListener('click', ()=>{
      localStorage.setItem('hp_company', document.getElementById('companyName').value);
      localStorage.setItem('hp_fullname', document.getElementById('fullName').value);
      localStorage.setItem('hp_email', document.getElementById('email').value);
      localStorage.setItem('hp_phone', document.getElementById('phone').value);
      localStorage.setItem('hp_lat', document.getElementById('lat').value);
      localStorage.setItem('hp_lng', document.getElementById('lng').value);
      clearUnsaved(document.getElementById('saveProfile'));
      toast('Profile saved');
    });

    document.getElementById('savePrefs').addEventListener('click', ()=>{
      localStorage.setItem('hp_currency', document.getElementById('currency').value);
      localStorage.setItem('hp_unit', document.getElementById('unit').value);
      localStorage.setItem('hp_tz', document.getElementById('tz').value);
      localStorage.setItem('hp_lang', document.getElementById('lang').value);
      clearUnsaved(document.getElementById('savePrefs'));
      toast('Preferences saved');
    });

    document.getElementById('saveNotifs').addEventListener('click', ()=>{
      localStorage.setItem('hp_notif_email', document.getElementById('emailNotif').checked ? '1':'0');
      localStorage.setItem('hp_notif_sms', document.getElementById('smsNotif').checked ? '1':'0');
      localStorage.setItem('hp_notif_push', document.getElementById('pushNotif').checked ? '1':'0');
      clearUnsaved(document.getElementById('saveNotifs'));
      toast('Notification settings saved');
    });

    document.getElementById('saveSecurity').addEventListener('click', ()=>{
      const v1 = pw.value, v2 = cpw.value;
      if (v1.length < 8) { alert('Password too short.'); return; }
      if (v1 !== v2) { alert('Passwords do not match.'); return; }
      localStorage.setItem('hp_2fa', document.getElementById('twoFA').checked ? '1':'0');
      // demo only — do not store real passwords in production
      localStorage.setItem('hp_password_demo', v1 ? 'set' : '');
      clearUnsaved(document.getElementById('saveSecurity'));
      toast('Security saved');
    });

    // Export / Import JSON
    document.getElementById('exportSettings').addEventListener('click', ()=>{
      const data = {
        company: document.getElementById('companyName').value,
        fullName: document.getElementById('fullName').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        lat: document.getElementById('lat').value,
        lng: document.getElementById('lng').value,
        currency: document.getElementById('currency').value,
        unit: document.getElementById('unit').value,
        tz: document.getElementById('tz').value,
        lang: document.getElementById('lang').value,
        notif: {
          email: document.getElementById('emailNotif').checked,
          sms: document.getElementById('smsNotif').checked,
          push: document.getElementById('pushNotif').checked
        },
        security: { twoFA: document.getElementById('twoFA').checked }
      };
      const blob = new Blob([JSON.stringify(data,null,2)], {type:'application/json'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'haulpro-settings.json';
      a.click();
    });
    document.getElementById('importSettings').addEventListener('change', (e)=>{
      const file = e.target.files[0]; if (!file) return;
      file.text().then(text=>{
        try{
          const d = JSON.parse(text);
          if (d.company) document.getElementById('companyName').value = d.company;
          if (d.fullName) document.getElementById('fullName').value = d.fullName;
          if (d.email) document.getElementById('email').value = d.email;
          if (d.phone) document.getElementById('phone').value = d.phone;
          if (d.lat) document.getElementById('lat').value = d.lat;
          if (d.lng) document.getElementById('lng').value = d.lng;
          if (d.currency) document.getElementById('currency').value = d.currency;
          if (d.unit) document.getElementById('unit').value = d.unit;
          if (d.tz) document.getElementById('tz').value = d.tz;
          if (d.lang) document.getElementById('lang').value = d.lang;
          if (d.notif){
            document.getElementById('emailNotif').checked = !!d.notif.email;
            document.getElementById('smsNotif').checked = !!d.notif.sms;
            document.getElementById('pushNotif').checked = !!d.notif.push;
          }
          if (d.security){ document.getElementById('twoFA').checked = !!d.security.twoFA; }
          // reflect on map if loaded
          if (window.google && window.map){
            const p = new google.maps.LatLng(parseFloat(document.getElementById('lat').value), parseFloat(document.getElementById('lng').value));
            marker.setPosition(p); map.setCenter(p);
          }
          toast('Settings imported (not saved yet)');
          markUnsaved(document.getElementById('saveProfile'));
          markUnsaved(document.getElementById('savePrefs'));
          markUnsaved(document.getElementById('saveNotifs'));
          markUnsaved(document.getElementById('saveSecurity'));
        } catch(err){ alert('Invalid JSON'); }
      });
    });

    // Restore any saved state
    (function restore(){
      if (localStorage.getItem('hp_company')) document.getElementById('companyName').value = localStorage.getItem('hp_company');
      if (localStorage.getItem('hp_fullname')) document.getElementById('fullName').value = localStorage.getItem('hp_fullname');
      if (localStorage.getItem('hp_email')) document.getElementById('email').value = localStorage.getItem('hp_email');
      if (localStorage.getItem('hp_phone')) document.getElementById('phone').value = localStorage.getItem('hp_phone');

      const lat = localStorage.getItem('hp_lat'); const lng = localStorage.getItem('hp_lng');
      if (lat && lng){ document.getElementById('lat').value = lat; document.getElementById('lng').value = lng; }

      if (localStorage.getItem('hp_currency')) document.getElementById('currency').value = localStorage.getItem('hp_currency');
      if (localStorage.getItem('hp_unit')) document.getElementById('unit').value = localStorage.getItem('hp_unit');
      if (localStorage.getItem('hp_tz')) document.getElementById('tz').value = localStorage.getItem('hp_tz');
      if (localStorage.getItem('hp_lang')) document.getElementById('lang').value = localStorage.getItem('hp_lang');

      if (localStorage.getItem('hp_notif_email')!==null) document.getElementById('emailNotif').checked = localStorage.getItem('hp_notif_email')==='1';
      if (localStorage.getItem('hp_notif_sms')!==null) document.getElementById('smsNotif').checked = localStorage.getItem('hp_notif_sms')==='1';
      if (localStorage.getItem('hp_notif_push')!==null) document.getElementById('pushNotif').checked = localStorage.getItem('hp_notif_push')==='1';

      if (localStorage.getItem('hp_2fa')!==null) document.getElementById('twoFA').checked = localStorage.getItem('hp_2fa')==='1';
    })();
  });
</script>

<!-- Your original Google Maps loader that calls initMap -->
<script src="https://maps.googleapis.com/maps/api/js?key=API&callback=initMap" async defer></script>
</body>
</html>
