// ============================================================
// CMMS Shared Configuration & Helpers
// ============================================================

//const API_BASE = window.CMMS_API_URL ||
//  (window.location.hostname === 'localhost' || window.location.hostname === '192.168.0.48'
//    ? 'http://192.168.0.48:3001/api'
//    : '/api');
const API_BASE = '/api';


// ── Token Management ─────────────────────────────────────────
function getToken() { return localStorage.getItem('cmms_token'); }
function getUser()  { return JSON.parse(localStorage.getItem('cmms_user') || 'null'); }

function setSession(token, user) {
  localStorage.setItem('cmms_token', token);
  localStorage.setItem('cmms_user', JSON.stringify(user));
}

function clearSession() {
  localStorage.removeItem('cmms_token');
  localStorage.removeItem('cmms_user');
}

function requireAuth(allowedRoles) {
  const token = getToken();
  const user  = getUser();
  if (!token || !user) { window.location.href = '../login.html'; return null; }
  if (allowedRoles && !allowedRoles.includes(user.role)) {
    alert('Access denied. Redirecting...');
    window.location.href = '../login.html';
    return null;
  }
  return user;
}

// ── API Fetch Wrapper ─────────────────────────────────────────
async function api(path, options = {}) {
    const token = getToken();
    
    const fetchOptions = {
        method: options.method || 'GET',
        headers: {
            'Content-Type': 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...options.headers
        }
    };
    
    // Only add body for methods that support it (not GET/HEAD)
    if (options.body && !['GET', 'HEAD'].includes(fetchOptions.method.toUpperCase())) {
        fetchOptions.body = options.body;
    }
    
    const response = await fetch(`${API_BASE}${path}`, fetchOptions);

    if (response.status === 401) {
        clearSession();
        window.location.href = '../login.html';
        return null;
    }

    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'API Error');
    return data;
}

// ── Toast Notifications ───────────────────────────────────────
function showToast(message, type = 'success') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.style.cssText = `
    padding:.75rem 1.25rem;border-radius:8px;color:#fff;font-weight:600;font-size:.9rem;
    box-shadow:0 4px 12px rgba(0,0,0,.2);min-width:220px;max-width:350px;
    background:${type==='success'?'#16a34a':type==='error'?'#dc2626':'#d97706'};
    animation:slideIn .3s ease;
  `;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

// ── String Escape Helpers (for onclick attributes & HTML) ───
function escJ(s) {
  // Safe for use inside onclick="fn('${escJ(val)}')" template literals
  return String(s || '').replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/"/g, '&quot;');
}

function escHtml(s) {
  // Safe for use inside HTML attribute strings
  return String(s || '').replace(/'/g, "\'");
}

// ── Format Helpers ────────────────────────────────────────────
function fmtDuration(minutes) {
  if (!minutes && minutes !== 0) return '—';
  const m = Math.round(minutes);
  if (m < 60) return `${m} min`;
  const h = Math.floor(m / 60), rem = m % 60;
  return rem ? `${h}h ${rem}m` : `${h}h`;
}

function fmtDateTime(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('en-IN', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit', hour12:true });
}

function fmtDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
}

// ── Role Label Helper ────────────────────────────────────────
function getRoleLabel(role) {
  if (role === 'operator')    return 'Shift-in-Charge';
  if (role === 'maintenance') return 'Department Head';
  if (role === 'management')  return 'Management';
  if (role === 'admin')       return 'Admin';
  return role || '';
}

// ── DOM Helpers ───────────────────────────────────────────────
function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

// ── Navigation Helpers ───────────────────────────────────────
function goBack() {
  window.history.back();
}

// ── Safe fallback for updateCounts (overridden by page-level definition) ──
if (typeof updateCounts === 'undefined') {
  window.updateCounts = function() {};
}

// CSS keyframes injection
const style = document.createElement('style');
style.textContent = `@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}`;
document.head.appendChild(style);
