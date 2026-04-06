/**
 * PantryPilot API Utility Layer
 * Centralized HTTP client, token management, offline queue, and shared helpers.
 */
const API_BASE = "/api/v1";
const PENDING_QUEUE_KEY = "pantrypilot_offline_queue";
const AUTH_TOKEN_KEY = "pantrypilot_auth_token";
const UI_DEFAULTS_KEY = "pantrypilot_ui_defaults";

const _submitting = {};

function escapeHtml(str) {
  const s = String(str ?? "");
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

function guardSubmit(key, btn) {
  if (_submitting[key]) return false;
  _submitting[key] = true;
  if (btn) btn.disabled = true;
  return true;
}

function releaseSubmit(key, btn) {
  _submitting[key] = false;
  if (btn) btn.disabled = false;
}

function inputValue(id) {
  const el = document.getElementById(id);
  return el ? String(el.value || "").trim() : "";
}

function numberInput(id, required = false) {
  const v = inputValue(id);
  if (!v) {
    if (required) throw new Error(`${id} is required`);
    return null;
  }
  const n = Number(v);
  if (!Number.isFinite(n)) throw new Error(`${id} must be numeric`);
  return n;
}

function getToken() {
  return sessionStorage.getItem(AUTH_TOKEN_KEY) || "";
}

function setToken(token) {
  const authStateEl = document.getElementById("authState");
  if (token) {
    sessionStorage.setItem(AUTH_TOKEN_KEY, token);
    if (authStateEl) authStateEl.textContent = "Authenticated";
  } else {
    sessionStorage.removeItem(AUTH_TOKEN_KEY);
    if (authStateEl) authStateEl.textContent = "Not authenticated";
  }
}

function getQueue() {
  try {
    return JSON.parse(localStorage.getItem(PENDING_QUEUE_KEY) || "[]");
  } catch (e) {
    return [];
  }
}

function setQueue(items) {
  localStorage.setItem(PENDING_QUEUE_KEY, JSON.stringify(items));
  const stateEl = document.getElementById("syncState");
  if (stateEl) stateEl.textContent = `${items.length} pending action(s)`;
}

async function apiRequest(path, method = "GET", body = null) {
  const headers = { "Content-Type": "application/json" };
  const token = getToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  try {
    const response = await fetch(`${API_BASE}${path}`, {
      method,
      headers,
      body: body ? JSON.stringify(body) : null
    });
    const raw = await response.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      const contentType = response.headers.get("content-type") || "unknown";
      const snippet = raw.replace(/\s+/g, " ").trim().slice(0, 220) || "(empty response body)";
      throw new Error(`Invalid JSON from ${method} ${path} (HTTP ${response.status}, content-type: ${contentType}). Body preview: ${snippet}`);
    }

    if (!data || typeof data !== "object" || Array.isArray(data)) {
      throw new Error(`Invalid API payload shape from ${method} ${path}`);
    }

    if (!response.ok || !data.success) throw new Error(data.message || `Request failed (HTTP ${response.status})`);
    return data.data;
  } catch (err) {
    const isNetworkError = err instanceof TypeError && err.message.toLowerCase().includes("fetch");
    const isAuthPath = path.includes("/identity/") || path.includes("/admin/reauth");
    if (method !== "GET" && body && isNetworkError && !isAuthPath) {
      const safeBody = { ...body };
      delete safeBody.password;
      delete safeBody.reauth_token;
      const queue = getQueue();
      queue.push({ path, method, body: safeBody, at: Date.now() });
      setQueue(queue);
    }
    throw err;
  }
}

function renderTable(tableId, rows) {
  const table = document.getElementById(tableId);
  if (!rows || rows.length === 0) {
    table.innerHTML = "<tr><td>No records.</td></tr>";
    return;
  }
  const headers = Object.keys(rows[0]);
  table.innerHTML = `<tr>${headers.map((h) => `<th>${escapeHtml(h)}</th>`).join("")}</tr>` +
    rows.map((row) => `<tr>${headers.map((h) => `<td>${escapeHtml(row[h] ?? "")}</td>`).join("")}</tr>`).join("");
}

function setFeedback(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = typeof value === "string" ? value : JSON.stringify(value);
}

async function syncQueue() {
  const queue = getQueue();
  if (!queue.length) return layui.layer.msg("No pending actions");
  const remaining = [];
  for (const item of queue) {
    try { await apiRequest(item.path, item.method, item.body); } catch { remaining.push(item); }
  }
  setQueue(remaining);
  layui.layer.msg(`Synced. Remaining ${remaining.length}`);
}

// Export for modules
window.PantryPilot = window.PantryPilot || {};
Object.assign(window.PantryPilot, {
  apiRequest, renderTable, setFeedback, escapeHtml,
  guardSubmit, releaseSubmit, inputValue, numberInput,
  getToken, setToken, getQueue, setQueue, syncQueue,
});
