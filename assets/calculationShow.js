// Debug check
console.log("✅ calculationShow.js loaded");

// ---------- Navigation + Filters ----------
function goHome(){ 
  // Update if your home page is different
  window.location.href = "calculationShow.php"; 
}

function setRange(days){
  const url = new URL(window.location.href);
  url.searchParams.delete('anchor');
  url.searchParams.set('range', days);
  window.location.href = url.toString();
}

// ---------- Dark Mode with Persistence ----------
const body = document.body;
const themeBtn = document.getElementById('themeToggle');

function applyTheme(){
  const saved = localStorage.getItem('hp-theme') || 'light';
  if(saved === 'dark'){
    body.classList.add('dark');
    themeBtn.textContent = '☀️ Light Mode';
  }else{
    body.classList.remove('dark');
    themeBtn.textContent = '🌙 Dark Mode';
  }
}

if (themeBtn) {
  themeBtn.addEventListener('click', ()=>{
    const newTheme = body.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('hp-theme', newTheme);
    applyTheme();
  });
}
applyTheme();

// ---------- Calendar (Flatpickr) ----------
function isoToDMY(iso){
  const [y,m,d] = iso.split('-');
  return `${d}-${m}-${y}`;
}

document.addEventListener('DOMContentLoaded', function(){
  const isoDefault = (window.HAULPRO && window.HAULPRO.anchorIso) ? window.HAULPRO.anchorIso : null;

  if (window.flatpickr) {
    window.flatpickr("#jumpDate", {
      dateFormat: "d-m-Y",
      defaultDate: isoDefault ? isoToDMY(isoDefault) : null,
      onChange: function(selectedDates, dateStr) {
        if (!dateStr) return;
        const [dd,mm,yyyy] = dateStr.split("-");
        const iso = `${yyyy}-${mm}-${dd}`;
        const url = new URL(window.location.href);
        url.searchParams.set('anchor', iso);
        url.searchParams.set('range', 30);
        window.location.href = url.toString();
      }
    });
  }
});

// ---------- Printable Receipt ----------
function openReceipt(data){
  const w = window.open('', '_blank', 'width=720,height=900');
  const html = `
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt ${data.receipt}</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#111}
  .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
  .brand{font-weight:800;font-size:20px}
  .meta{color:#555}
  .card{border:1px solid #e3e6eb;border-radius:10px;padding:16px;margin-top:10px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:10px;border:1px solid #e3e6eb;text-align:left}
  th{background:#f3f6fb}
  .tot{font-weight:800}
  .actions{margin-top:16px}
  .btn{padding:8px 12px;border:1px solid #ddd;border-radius:8px;background:#0d6efd;color:#fff;cursor:pointer}
</style>
</head>
<body>
  <div class="head">
    <div class="brand">HaulPro – Truck 1</div>
    <div class="meta">Receipt #: ${data.receipt}</div>
  </div>
  <div class="card">
    <table>
      <tr><th>Trip No</th><td>${data.no}</td></tr>
      <tr><th>Date</th><td>${data.date}</td></tr>
      <tr><th>Route</th><td>${data.route}</td></tr>
      <tr><th>Trip Type</th><td>${data.type}</td></tr>
      <tr><th>Distance</th><td>${data.distance} km</td></tr>
      <tr><th>Revenue</th><td>৳${data.revenue}</td></tr>
      <tr><th>Expense</th><td>৳${data.expense}</td></tr>
      <tr class="tot"><th>Profit</th><td>৳${data.profit}</td></tr>
    </table>
  </div>
  <div class="actions">
    <button class="btn" onclick="window.print()">Print</button>
  </div>
</body>
</html>`;
  w.document.open(); 
  w.document.write(html); 
  w.document.close();
}

// ---------- Expose functions for inline HTML ----------
window.goHome = goHome;
window.setRange = setRange;
window.openReceipt = openReceipt;
