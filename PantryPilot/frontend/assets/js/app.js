const API_BASE = "/api/v1";
const PENDING_QUEUE_KEY = "pantrypilot_offline_queue";
const AUTH_TOKEN_KEY = "pantrypilot_auth_token";

const stateEl = document.getElementById("syncState");
const authStateEl = document.getElementById("authState");

let latestBatchRef = "";
let latestReauthToken = "";
let selectedRecipeId = 1;
let selectedSlot = null;
let selectedBookingId = 0;
let selectedFileId = 0;

function getToken() {
  return localStorage.getItem(AUTH_TOKEN_KEY) || "";
}

function setToken(token) {
  if (token) {
    localStorage.setItem(AUTH_TOKEN_KEY, token);
    authStateEl.textContent = "Authenticated";
  } else {
    localStorage.removeItem(AUTH_TOKEN_KEY);
    authStateEl.textContent = "Not authenticated";
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
  stateEl.textContent = `${items.length} pending action(s)`;
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
    if (method !== "GET" && body) {
      const queue = getQueue();
      queue.push({ path, method, body, at: Date.now() });
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
  table.innerHTML = `<tr>${headers.map((h) => `<th>${h}</th>`).join("")}</tr>` +
    rows.map((row) => `<tr>${headers.map((h) => `<td>${row[h] ?? ""}</td>`).join("")}</tr>`).join("");

  if (tableId === "bookingsTable" || tableId === "filesTable") {
    const trs = table.querySelectorAll("tr");
    trs.forEach((tr, idx) => {
      if (idx === 0) return;
      tr.style.cursor = "pointer";
      tr.addEventListener("click", () => {
        trs.forEach((x, i) => { if (i > 0) x.classList.remove("active-row"); });
        tr.classList.add("active-row");
        const source = rows[idx - 1] || {};
        if (tableId === "bookingsTable") {
          selectedBookingId = Number(source.id || 0);
          const input = document.getElementById("bookingSelectedId");
          if (input) input.value = selectedBookingId ? String(selectedBookingId) : "";
        }
        if (tableId === "filesTable") {
          selectedFileId = Number(source.id || 0);
          const input = document.getElementById("fileSelectedId");
          if (input) input.value = selectedFileId ? String(selectedFileId) : "";
        }
      });
    });
  }
}

function setFeedback(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = typeof value === "string" ? value : JSON.stringify(value);
}

async function login() {
  const username = document.getElementById("username").value.trim();
  const password = document.getElementById("password").value;
  const data = await apiRequest("/identity/login", "POST", { username, password });
  setToken(data.token);
  layui.layer.msg("Login success");
}

async function loadDashboard() {
  const data = await apiRequest("/reporting/dashboard");
  const cards = document.getElementById("kpiCards");
  const icons = {
    recipes: "layui-icon-read",
    bookings: "layui-icon-form",
    pending_bookings: "layui-icon-time",
    captured_payments: "layui-icon-rmb"
  };
  cards.innerHTML = Object.entries(data).map(([k, v]) => `
    <div class="card kpi-card">
      <div class="kpi-icon"><i class="layui-icon ${icons[k] || "layui-icon-chart"}"></i></div>
      <h3>${k.replace(/_/g, " ")}</h3>
      <div class="value">${v}</div>
    </div>`).join("");
}

function recipeDifficultyClass(difficulty) {
  const value = String(difficulty || "easy").toLowerCase();
  if (value === "hard") return "hard";
  if (value === "medium") return "medium";
  return "easy";
}

function renderRecipeGrid(items) {
  const grid = document.getElementById("recipesGrid");
  if (!items || items.length === 0) {
    grid.innerHTML = `<div class="empty-state">No recipes found for the current filters.</div>`;
    return;
  }
  grid.innerHTML = items.map((item) => `
    <article class="recipe-card" data-id="${item.id}">
      <h3>${item.name || "Unnamed recipe"}</h3>
      <div class="recipe-meta">
        <span><i class="layui-icon layui-icon-time"></i> ${item.prep_minutes || 0} min</span>
        <span class="difficulty ${recipeDifficultyClass(item.difficulty)}">${item.difficulty || "easy"}</span>
      </div>
      <div class="recipe-stats">
        <span>${item.calories || 0} kcal</span>
        <span>$${Number(item.estimated_cost || 0).toFixed(2)}</span>
      </div>
    </article>
  `).join("");

  grid.querySelectorAll(".recipe-card").forEach((card) => {
    card.addEventListener("click", async () => {
      const id = Number(card.dataset.id || "0");
      if (!id) return;
      selectedRecipeId = id;
      try {
        const detail = await apiRequest(`/bookings/recipe/${id}`);
        layui.layer.open({
          type: 1,
          title: detail.name || `Recipe #${id}`,
          area: ["680px", "520px"],
          shadeClose: true,
          content: `<div class="recipe-modal">
            <p><strong>Description:</strong> ${detail.description || "N/A"}</p>
            <p><strong>Prep:</strong> ${detail.prep_minutes || 0} min | <strong>Difficulty:</strong> ${detail.difficulty || "easy"}</p>
            <p><strong>Calories:</strong> ${detail.calories || 0} | <strong>Estimated cost:</strong> $${Number(detail.estimated_cost || 0).toFixed(2)}</p>
            <p><strong>Ingredients:</strong> ${(detail.ingredients || []).join(", ") || "N/A"}</p>
            <p><strong>Cookware:</strong> ${(detail.cookware || []).join(", ") || "N/A"}</p>
            <p><strong>Allergens:</strong> ${(detail.allergens || []).join(", ") || "N/A"}</p>
          </div>`
        });
      } catch (e) {
        layui.layer.msg(e.message);
      }
    });
  });
}

async function searchRecipes() {
  const ingredient = document.getElementById("recipeSearchIngredient").value.trim();
  const cookware = document.getElementById("recipeSearchCookware").value.trim();
  const excludeAllergens = document.getElementById("recipeSearchExcludeAllergens").value.trim();
  const prep = document.getElementById("recipeSearchPrep").value;
  const stepCountMax = document.getElementById("recipeSearchStepCount").value;
  const difficulty = document.getElementById("recipeSearchDifficulty").value;
  const maxCalories = document.getElementById("recipeSearchMaxCalories").value;
  const budget = document.getElementById("recipeSearchBudget").value;
  const rankMode = document.getElementById("recipeSearchRankMode").value;
  const qs = new URLSearchParams();
  if (ingredient) qs.set("ingredient", ingredient);
  if (cookware) qs.set("cookware", cookware);
  if (excludeAllergens) qs.set("exclude_allergens", excludeAllergens);
  if (prep) qs.set("prep_under", prep);
  if (stepCountMax) qs.set("step_count_max", stepCountMax);
  if (difficulty) qs.set("difficulty", difficulty);
  if (maxCalories) qs.set("max_calories", maxCalories);
  if (budget) qs.set("max_budget", budget);
  if (rankMode) qs.set("rank_mode", rankMode);
  const data = await apiRequest(`/recipes/search?${qs.toString()}`);
  const items = data.items || [];
  if (items.length > 0) selectedRecipeId = Number(items[0].id || selectedRecipeId);
  renderRecipeGrid(items);
}

async function loadSlotPicker() {
  const picker = document.getElementById("slotPicker");
  const slots = [10, 10.5, 11, 11.5, 12];
  const day = new Date(Date.now() + 24 * 3600 * 1000);
  const cards = [];
  for (const hour of slots) {
    const start = new Date(day);
    start.setHours(Math.floor(hour), hour % 1 ? 30 : 0, 0, 0);
    const end = new Date(start.getTime() + 30 * 60 * 1000);
    const slotStart = start.toISOString().slice(0, 19).replace("T", " ");
    const slotEnd = end.toISOString().slice(0, 19).replace("T", " ");
    try {
      const cap = await apiRequest(`/bookings/slot-capacity?pickup_point_id=1&slot_start=${encodeURIComponent(slotStart)}&slot_end=${encodeURIComponent(slotEnd)}`);
      cards.push({ slotStart, slotEnd, remaining: Number(cap.remaining || 0), capacity: Number(cap.capacity || 0) });
    } catch {
      cards.push({ slotStart, slotEnd, remaining: -1, capacity: -1 });
    }
  }

  picker.innerHTML = cards.map((slot, idx) => `
    <button class="slot-card ${slot.remaining <= 0 ? "disabled" : ""}" data-idx="${idx}" ${slot.remaining <= 0 ? "disabled" : ""}>
      <strong>${slot.slotStart.slice(11, 16)} - ${slot.slotEnd.slice(11, 16)}</strong>
      <span>${slot.remaining >= 0 ? `${slot.remaining}/${slot.capacity} remaining` : "Unavailable"}</span>
    </button>
  `).join("");

  picker.querySelectorAll(".slot-card").forEach((btn) => {
    btn.addEventListener("click", () => {
      picker.querySelectorAll(".slot-card").forEach((x) => x.classList.remove("active"));
      btn.classList.add("active");
      selectedSlot = cards[Number(btn.dataset.idx || "0")];
      setFeedback("bookingFeedback", { selected_recipe_id: selectedRecipeId, selected_slot: selectedSlot });
    });
  });
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

function randomFutureDate(hours = 24) {
  return new Date(Date.now() + 3600 * 1000 * hours).toISOString().slice(0, 19).replace("T", " ");
}

function bindEvents() {
  document.getElementById("btnLogin").addEventListener("click", async () => {
    try { await login(); } catch (e) { layui.layer.msg(e.message); }
  });
  document.getElementById("btnSync").addEventListener("click", syncQueue);

  document.getElementById("btnLoadRecipes").addEventListener("click", async () => {
    const data = await apiRequest("/recipes");
    const items = data.items || [];
    if (items.length > 0) selectedRecipeId = Number(items[0].id || selectedRecipeId);
    renderRecipeGrid(items);
  });

  document.getElementById("btnSearchRecipes").addEventListener("click", async () => {
    try {
      await searchRecipes();
    } catch (e) {
      layui.layer.msg(e.message);
    }
  });

  document.getElementById("btnCreateRecipe").addEventListener("click", async () => {
    try {
      await apiRequest("/recipes", "POST", {
        name: `Quick Recipe ${Date.now()}`,
        description: "Created from kiosk",
        prep_minutes: 20,
        step_count: 5,
        servings: 4,
        difficulty: "easy",
        calories: 420,
        estimated_cost: 12.5,
        ingredients: ["chickpea", "tomato"],
        cookware: ["pot"],
        allergens: ["none"],
        status: "draft",
        created_by: 1
      });
      layui.layer.msg("Recipe queued/created");
    } catch (e) { layui.layer.msg(e.message); }
  });

  document.getElementById("btnLoadBookings").addEventListener("click", async () => {
    const data = await apiRequest("/bookings");
    renderTable("bookingsTable", data.items || []);
  });

  document.getElementById("btnRecipeDetail").addEventListener("click", async () => {
    try {
      const data = await apiRequest(`/bookings/recipe/${selectedRecipeId}`);
      setFeedback("bookingFeedback", data);
    } catch (e) { setFeedback("bookingFeedback", e.message); }
  });

  document.getElementById("btnCheckCapacity").addEventListener("click", async () => {
    try {
      await loadSlotPicker();
      setFeedback("bookingFeedback", "Slot capacities refreshed");
    } catch (e) { setFeedback("bookingFeedback", e.message); }
  });

  document.getElementById("btnCreateBooking").addEventListener("click", async () => {
    try {
      if (!selectedSlot) {
        throw new Error("Please choose a slot first");
      }
      const slotStart = selectedSlot.slotStart;
      const slotEnd = selectedSlot.slotEnd;
      const data = await apiRequest("/bookings", "POST", {
        recipe_id: selectedRecipeId,
        pickup_point_id: 1,
        pickup_at: slotStart,
        slot_start: slotStart,
        slot_end: slotEnd,
        quantity: 1,
        customer_zip4: "12345-6789",
        customer_region_code: "REG-001",
        customer_latitude: 40.7128,
        customer_longitude: -74.006,
        note: "Kiosk booking"
      });
      setFeedback("bookingFeedback", data);
      layui.layer.msg("Booking queued/created");
    } catch (e) { setFeedback("bookingFeedback", e.message); }
  });

  document.getElementById("btnTodayPickups").addEventListener("click", async () => {
    const data = await apiRequest("/bookings/today-pickups");
    renderTable("opsTable", data.items || []);
  });
  document.getElementById("btnCheckIn").addEventListener("click", async () => {
    try {
      const bookingId = Number(document.getElementById("bookingSelectedId").value || selectedBookingId || 0);
      if (!bookingId) throw new Error("Select a booking row first or enter a booking id");
      const data = await apiRequest("/bookings/check-in", "POST", { booking_id: bookingId });
      setFeedback("opsFeedback", data);
    } catch (e) { setFeedback("opsFeedback", e.message); }
  });
  document.getElementById("btnSweepNoShow").addEventListener("click", async () => {
    try {
      const data = await apiRequest("/bookings/no-show-sweep", "POST", {});
      setFeedback("opsFeedback", data);
    } catch (e) { setFeedback("opsFeedback", e.message); }
  });
  document.getElementById("btnDispatchNote").addEventListener("click", async () => {
    try {
      const bookingId = Number(document.getElementById("bookingSelectedId").value || selectedBookingId || 0);
      if (!bookingId) throw new Error("Select a booking row first or enter a booking id");
      const data = await apiRequest(`/bookings/${bookingId}/dispatch-note`);
      setFeedback("opsFeedback", data);
    } catch (e) { setFeedback("opsFeedback", e.message); }
  });
  document.getElementById("btnLoadModules").addEventListener("click", async () => {
    try {
      const data = await apiRequest("/operations/homepage-modules");
      renderTable("opsTable", data.items || []);
    } catch (e) { setFeedback("opsFeedback", e.message); }
  });
  document.getElementById("btnSaveModule").addEventListener("click", async () => {
    try {
      const data = await apiRequest("/operations/homepage-modules", "POST", {
        module_key: "carousel_banners",
        enabled: 1,
        banners: [{ title: "Fresh Picks", image: "banner-1.png", link: "/recipes" }]
      });
      setFeedback("opsFeedback", data);
    } catch (e) { setFeedback("opsFeedback", e.message); }
  });
  document.getElementById("btnSaveTemplate").addEventListener("click", async () => {
    try {
      const data = await apiRequest("/operations/message-templates", "POST", {
        template_code: "PROMO_DAILY",
        title: "Daily Promo",
        content: "Your local pantry has fresh offers.",
        category: "marketing",
        active: 1
      });
      setFeedback("opsFeedback", data);
    } catch (e) { setFeedback("opsFeedback", e.message); }
  });

  document.getElementById("btnLoadPayments").addEventListener("click", async () => {
    const data = await apiRequest("/payments");
    renderTable("paymentsTable", data.items || []);
  });

  document.getElementById("btnCreatePayment").addEventListener("click", async () => {
    try {
      const bookingId = Number(document.getElementById("bookingSelectedId").value || selectedBookingId || 0);
      if (!bookingId) throw new Error("Select a booking row first or enter a booking id");
      await apiRequest("/payments", "POST", {
        booking_id: bookingId,
        amount: 12.5,
        method: "cash",
        status: "captured",
        payer_name: "Local Customer"
      });
      layui.layer.msg("Payment queued/created");
    } catch (e) { layui.layer.msg(e.message); }
  });

  document.getElementById("btnCreateGwOrder").addEventListener("click", async () => {
    try {
      const bookingId = Number(document.getElementById("bookingSelectedId").value || selectedBookingId || 0);
      if (!bookingId) throw new Error("Select a booking row first or enter a booking id");
      const data = await apiRequest("/payments/gateway/orders", "POST", { booking_id: bookingId, amount: 11.2 });
      setFeedback("financeFeedback", data);
    } catch (e) { setFeedback("financeFeedback", e.message); }
  });
  document.getElementById("btnAutoCancelGw").addEventListener("click", async () => {
    const data = await apiRequest("/payments/gateway/auto-cancel", "POST", {});
    setFeedback("financeFeedback", data);
  });
  document.getElementById("btnDailyRecon").addEventListener("click", async () => {
    const data = await apiRequest("/payments/reconcile/daily", "POST", { date: new Date().toISOString().slice(0, 10) });
    latestBatchRef = data.batch_ref || "";
    setFeedback("financeFeedback", data);
  });
  document.getElementById("btnIssueReauth").addEventListener("click", async () => {
    try {
      const pwd = document.getElementById("password").value;
      const data = await apiRequest("/admin/reauth", "POST", { password: pwd });
      latestReauthToken = data.reauth_token;
      setFeedback("financeFeedback", { reauth_token_received: true, expire_at: data.expire_at });
    } catch (e) { setFeedback("financeFeedback", e.message); }
  });
  document.getElementById("btnRepairIssue").addEventListener("click", async () => {
    try {
      const data = await apiRequest("/payments/reconcile/repair", "POST", {
        issue_id: 1,
        note: "manual repair",
        reauth_token: latestReauthToken
      });
      setFeedback("financeFeedback", data);
    } catch (e) { setFeedback("financeFeedback", e.message); }
  });
  document.getElementById("btnCloseBatch").addEventListener("click", async () => {
    try {
      const data = await apiRequest("/payments/reconcile/close", "POST", {
        batch_ref: latestBatchRef,
        reauth_token: latestReauthToken
      });
      setFeedback("financeFeedback", data);
    } catch (e) { setFeedback("financeFeedback", e.message); }
  });
  document.getElementById("btnRefund").addEventListener("click", async () => {
    try {
      const data = await apiRequest("/payments/refund", "POST", {
        payment_ref: "PAY-REF",
        reauth_token: latestReauthToken
      });
      setFeedback("financeFeedback", data);
    } catch (e) { setFeedback("financeFeedback", e.message); }
  });
  document.getElementById("btnAdjust").addEventListener("click", async () => {
    try {
      const data = await apiRequest("/payments/adjust", "POST", {
        payment_ref: "PAY-REF",
        amount: -1.5,
        reason: "manual adjustment",
        reauth_token: latestReauthToken
      });
      setFeedback("financeFeedback", data);
    } catch (e) { setFeedback("financeFeedback", e.message); }
  });

  document.getElementById("btnLoadEvents").addEventListener("click", async () => {
    const data = await apiRequest("/notifications/events");
    renderTable("eventsTable", data.items || []);
  });
  document.getElementById("btnQueueEvent").addEventListener("click", async () => {
    await apiRequest("/notifications/events", "POST", {
      event_type: "booking.created",
      channel: "kiosk",
      payload: { source: "web", created_at: new Date().toISOString() }
    });
    layui.layer.msg("Event queued");
  });
  document.getElementById("btnSendMarketing").addEventListener("click", async () => {
    try {
      const data = await apiRequest("/notifications/messages", "POST", {
        user_id: 1,
        title: "Weekend special",
        body: "Save 10% this weekend",
        is_marketing: true
      });
      setFeedback("msgFeedback", data);
    } catch (e) { setFeedback("msgFeedback", e.message); }
  });
  document.getElementById("btnLoadInbox").addEventListener("click", async () => {
    const data = await apiRequest("/notifications/inbox");
    renderTable("eventsTable", data.items || []);
  });
  document.getElementById("btnMsgAnalytics").addEventListener("click", async () => {
    const data = await apiRequest("/notifications/analytics");
    setFeedback("msgFeedback", data);
  });

  document.getElementById("btnUploadMockFile").addEventListener("click", async () => {
    const pdf = btoa("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n");
    const data = await apiRequest("/files/upload-base64", "POST", {
      filename: "dispatch.pdf",
      mime_type: "application/pdf",
      content_base64: pdf,
      watermark: true
    });
    setFeedback("fileFeedback", data);
  });
  document.getElementById("btnLoadFiles").addEventListener("click", async () => {
    const data = await apiRequest("/files");
    renderTable("filesTable", data.items || []);
  });
  document.getElementById("btnSignFile").addEventListener("click", async () => {
    try {
      const fileId = Number(document.getElementById("fileSelectedId").value || selectedFileId || 0);
      if (!fileId) throw new Error("Select a file row first or enter a file id");
      const data = await apiRequest(`/files/${fileId}/signed-url`);
      setFeedback("fileFeedback", data);
    } catch (e) { setFeedback("fileFeedback", e.message); }
  });
  document.getElementById("btnCleanupFiles").addEventListener("click", async () => {
    const data = await apiRequest("/files/cleanup", "POST", {});
    setFeedback("fileFeedback", data);
  });

  document.getElementById("btnLoadUsers").addEventListener("click", async () => {
    const data = await apiRequest("/admin/users");
    renderTable("usersTable", data.items || []);
  });
  document.getElementById("btnLoadAudit").addEventListener("click", async () => {
    const data = await apiRequest("/admin/audit-logs");
    renderTable("auditTable", data.items || []);
  });
  document.getElementById("btnOpsDashboard").addEventListener("click", async () => {
    const data = await apiRequest("/operations/dashboard");
    setFeedback("adminFeedback", data);
  });
  document.getElementById("btnAnomalies").addEventListener("click", async () => {
    const data = await apiRequest("/reporting/anomalies");
    setFeedback("adminFeedback", data);
  });
  document.getElementById("btnExportCsv").addEventListener("click", async () => {
    const data = await apiRequest("/reporting/exports/bookings-csv");
    setFeedback("adminFeedback", `CSV ready: ${data.filename} (${data.content_base64.length} b64 chars)`);
  });
  document.getElementById("btnCreateRole").addEventListener("click", async () => {
    const code = `role_${Date.now()}`;
    const data = await apiRequest("/admin/roles", "POST", { code, name: `Role ${code}` });
    setFeedback("adminFeedback", data);
  });
  document.getElementById("btnLoadRoles").addEventListener("click", async () => {
    const data = await apiRequest("/admin/roles");
    setFeedback("adminFeedback", data.items || []);
  });
  document.getElementById("btnLoadPerms").addEventListener("click", async () => {
    const perms = await apiRequest("/admin/permissions");
    const resources = await apiRequest("/admin/resources");
    setFeedback("adminFeedback", { permissions: perms.items || [], resources: resources.items || [] });
  });
}

layui.use([], async () => {
  setQueue(getQueue());
  if (getToken()) authStateEl.textContent = "Authenticated";
  bindEvents();
  await loadSlotPicker();
  await apiRequest("/recipes").then((data) => {
    const items = data.items || [];
    if (items.length > 0) {
      selectedRecipeId = Number(items[0].id || 1);
      renderRecipeGrid(items);
    }
  }).catch(() => {});
  try { await loadDashboard(); } catch (e) { layui.layer.msg(`Dashboard unavailable: ${e.message}`); }
});

window.addEventListener("unhandledrejection", (event) => {
  const reason = event.reason;
  const message = reason && reason.message ? reason.message : String(reason || "Unexpected request failure");
  layui.layer.msg(message);
  event.preventDefault();
});
