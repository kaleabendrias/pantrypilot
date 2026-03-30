const API_BASE = "/api/v1";
const PENDING_QUEUE_KEY = "pantrypilot_offline_queue";
const AUTH_TOKEN_KEY = "pantrypilot_auth_token";
const UI_DEFAULTS_KEY = "pantrypilot_ui_defaults";

const stateEl = document.getElementById("syncState");
const authStateEl = document.getElementById("authState");

let latestBatchRef = "";
let latestReauthToken = "";
let selectedRecipeId = 0;
let selectedSlot = null;
let selectedBookingId = 0;
let selectedFileId = 0;
let selectedPaymentRef = "";
let pickupPointsCache = [];
let explicitTestDefaults = null;

function getUiDefaults() {
  if (explicitTestDefaults && typeof explicitTestDefaults === "object") return explicitTestDefaults;
  try {
    const parsed = JSON.parse(localStorage.getItem(UI_DEFAULTS_KEY) || "{}");
    return parsed && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}

function saveUiDefaults(next) {
  if (explicitTestDefaults) return;
  localStorage.setItem(UI_DEFAULTS_KEY, JSON.stringify(next));
}

async function loadExplicitTestDefaults() {
  const qs = new URLSearchParams(window.location.search);
  if (qs.get("ui_test_defaults") !== "1") return;
  try {
    const resp = await fetch("/test-config/ui-defaults.json", { cache: "no-store" });
    if (!resp.ok) return;
    const parsed = await resp.json();
    if (parsed && typeof parsed === "object") {
      explicitTestDefaults = parsed;
    }
  } catch {
    explicitTestDefaults = null;
  }
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

function applyInitialDefaults() {
  const defaults = getUiDefaults();
  const map = {
    username: defaults.username || "",
    bookingZip4: defaults.booking_zip4 || "",
    bookingRegionCode: defaults.booking_region_code || "",
    bookingLatitude: defaults.booking_latitude || "",
    bookingLongitude: defaults.booking_longitude || "",
    messageUserId: defaults.message_user_id || "",
    paymentRefInput: defaults.payment_ref || "",
    reconIssueId: defaults.recon_issue_id || "",
    reconRepairNote: defaults.recon_repair_note || "",
    pickupPointManual: defaults.pickup_point_id || "",
    slotDate: defaults.slot_date || "",
    slotWindowStart: defaults.slot_window_start || "",
    slotWindowEnd: defaults.slot_window_end || "",
    slotStepMinutes: defaults.slot_step_minutes || "",
    bookingQuantity: defaults.booking_quantity || "",
    moduleKey: defaults.module_key || "",
    moduleTitle: defaults.module_title || "",
    moduleBannerImage: defaults.module_banner_image || "",
    moduleBannerLink: defaults.module_banner_link || "",
    templateCode: defaults.template_code || "",
    templateTitle: defaults.template_title || "",
    templateContent: defaults.template_content || "",
    templateCategory: defaults.template_category || "",
    paymentAmount: defaults.payment_amount || "",
    paymentMethod: defaults.payment_method || "",
    payerName: defaults.payer_name || "",
    gatewayOrderAmount: defaults.gateway_order_amount || "",
    adjustAmount: defaults.adjust_amount || "",
    adjustReason: defaults.adjust_reason || "",
    eventType: defaults.event_type || "",
    eventChannel: defaults.event_channel || "",
    messageTitle: defaults.message_title || "",
    messageBody: defaults.message_body || "",
    fileName: defaults.file_name || "",
    fileOwnerType: defaults.file_owner_type || "",
    recipePrepMinutes: defaults.recipe_prep_minutes || "",
    recipeStepCount: defaults.recipe_step_count || "",
    recipeServings: defaults.recipe_servings || "",
    recipeDifficultyCreate: defaults.recipe_difficulty || "",
    recipeCalories: defaults.recipe_calories || "",
    recipeEstimatedCost: defaults.recipe_estimated_cost || "",
    recipeStatus: defaults.recipe_status || "",
  };
  Object.entries(map).forEach(([id, value]) => {
    const el = document.getElementById(id);
    if (el && !el.value && value) el.value = value;
  });

  if (!inputValue("slotDate")) {
    const tomorrow = new Date(Date.now() + 24 * 3600 * 1000);
    const y = tomorrow.getFullYear();
    const m = String(tomorrow.getMonth() + 1).padStart(2, "0");
    const d = String(tomorrow.getDate()).padStart(2, "0");
    const el = document.getElementById("slotDate");
    if (el) el.value = `${y}-${m}-${d}`;
  }
}

function persistOperationalDefaults() {
  const defaults = getUiDefaults();
  const next = {
    ...defaults,
    username: inputValue("username"),
    booking_zip4: inputValue("bookingZip4"),
    booking_region_code: inputValue("bookingRegionCode"),
    booking_latitude: inputValue("bookingLatitude"),
    booking_longitude: inputValue("bookingLongitude"),
    message_user_id: inputValue("messageUserId"),
    payment_ref: inputValue("paymentRefInput"),
    recon_issue_id: inputValue("reconIssueId"),
    recon_repair_note: inputValue("reconRepairNote"),
    pickup_point_id: inputValue("pickupPointManual") || inputValue("pickupPointSelect"),
    slot_date: inputValue("slotDate"),
    slot_window_start: inputValue("slotWindowStart"),
    slot_window_end: inputValue("slotWindowEnd"),
    slot_step_minutes: inputValue("slotStepMinutes"),
    booking_quantity: inputValue("bookingQuantity"),
    module_key: inputValue("moduleKey"),
    module_title: inputValue("moduleTitle"),
    module_banner_image: inputValue("moduleBannerImage"),
    module_banner_link: inputValue("moduleBannerLink"),
    template_code: inputValue("templateCode"),
    template_title: inputValue("templateTitle"),
    template_content: inputValue("templateContent"),
    template_category: inputValue("templateCategory"),
    payment_amount: inputValue("paymentAmount"),
    payment_method: inputValue("paymentMethod"),
    payer_name: inputValue("payerName"),
    gateway_order_amount: inputValue("gatewayOrderAmount"),
    adjust_amount: inputValue("adjustAmount"),
    adjust_reason: inputValue("adjustReason"),
    event_type: inputValue("eventType"),
    event_channel: inputValue("eventChannel"),
    message_title: inputValue("messageTitle"),
    message_body: inputValue("messageBody"),
    file_name: inputValue("fileName"),
    file_owner_type: inputValue("fileOwnerType"),
    recipe_prep_minutes: inputValue("recipePrepMinutes"),
    recipe_step_count: inputValue("recipeStepCount"),
    recipe_servings: inputValue("recipeServings"),
    recipe_difficulty: inputValue("recipeDifficultyCreate"),
    recipe_calories: inputValue("recipeCalories"),
    recipe_estimated_cost: inputValue("recipeEstimatedCost"),
    recipe_status: inputValue("recipeStatus"),
  };
  saveUiDefaults(next);
}

async function loadPickupPoints() {
  const select = document.getElementById("pickupPointSelect");
  if (!select) return;
  try {
    const data = await apiRequest("/bookings/pickup-points");
    pickupPointsCache = data.items || [];
    select.innerHTML = `<option value="">Pickup point</option>` + pickupPointsCache
      .map((p) => `<option value="${p.id}">${p.name || "Point"} (#${p.id})</option>`)
      .join("");
    const preferred = inputValue("pickupPointManual");
    if (preferred) select.value = preferred;
  } catch {
    pickupPointsCache = [];
  }
}

function resolvePickupPointId() {
  const fromSelect = Number(inputValue("pickupPointSelect") || 0);
  if (fromSelect > 0) return fromSelect;
  const fromManual = Number(inputValue("pickupPointManual") || 0);
  if (fromManual > 0) return fromManual;
  throw new Error("Pickup point is required. Select one from API list or enter an ID.");
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

  if (tableId === "bookingsTable" || tableId === "filesTable" || tableId === "paymentsTable") {
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
        if (tableId === "paymentsTable") {
          selectedPaymentRef = String(source.payment_ref || "");
          const input = document.getElementById("paymentRefInput");
          if (input && selectedPaymentRef) input.value = selectedPaymentRef;
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
  const pickupPointId = resolvePickupPointId();
  const dateRaw = inputValue("slotDate");
  if (!dateRaw) throw new Error("slotDate is required");
  const [year, month, day] = dateRaw.split("-").map((v) => Number(v));
  const startRaw = inputValue("slotWindowStart");
  const endRaw = inputValue("slotWindowEnd");
  if (!startRaw || !endRaw) throw new Error("slotWindowStart and slotWindowEnd are required");
  const stepMinutes = Number(inputValue("slotStepMinutes"));
  if (!Number.isFinite(stepMinutes) || stepMinutes < 15) throw new Error("slotStepMinutes must be >= 15");

  const [sh, sm] = startRaw.split(":").map((v) => Number(v));
  const [eh, em] = endRaw.split(":").map((v) => Number(v));
  const startMin = sh * 60 + sm;
  const endMin = eh * 60 + em;
  if (endMin <= startMin) throw new Error("slot window end must be later than start");

  const cards = [];
  for (let cursor = startMin; cursor + stepMinutes <= endMin; cursor += stepMinutes) {
    const start = new Date(year, month - 1, day, 0, 0, 0, 0);
    start.setMinutes(cursor);
    const end = new Date(start.getTime() + stepMinutes * 60 * 1000);
    const slotStart = start.toISOString().slice(0, 19).replace("T", " ");
    const slotEnd = end.toISOString().slice(0, 19).replace("T", " ");
    try {
      const cap = await apiRequest(`/bookings/slot-capacity?pickup_point_id=${pickupPointId}&slot_start=${encodeURIComponent(slotStart)}&slot_end=${encodeURIComponent(slotEnd)}`);
      cards.push({ slotStart, slotEnd, remaining: Number(cap.remaining || 0), capacity: Number(cap.capacity || 0) });
    } catch {
      cards.push({ slotStart, slotEnd, remaining: -1, capacity: -1 });
    }
  }

  if (!cards.length) throw new Error("No slots generated. Adjust slot window and step.");

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
      setFeedback("bookingFeedback", { selected_recipe_id: selectedRecipeId, pickup_point_id: pickupPointId, selected_slot: selectedSlot });
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
      const name = inputValue("recipeName");
      if (!name) throw new Error("recipeName is required");
      const description = inputValue("recipeDescription");
      if (!description) throw new Error("recipeDescription is required");
      const ingredientTerms = inputValue("recipeIngredients").split(",").map((v) => v.trim()).filter(Boolean);
      const cookwareTerms = inputValue("recipeCookware").split(",").map((v) => v.trim()).filter(Boolean);
      const allergenTerms = inputValue("recipeAllergens").split(",").map((v) => v.trim()).filter(Boolean);
      if (!ingredientTerms.length) throw new Error("recipeIngredients is required");
      if (!cookwareTerms.length) throw new Error("recipeCookware is required");
      if (!allergenTerms.length) throw new Error("recipeAllergens is required");
      const prepMinutes = numberInput("recipePrepMinutes", true);
      const stepCount = numberInput("recipeStepCount", true);
      const servings = numberInput("recipeServings", true);
      const calories = numberInput("recipeCalories", true);
      const estimatedCost = numberInput("recipeEstimatedCost", true);
      const difficulty = inputValue("recipeDifficultyCreate");
      if (!difficulty) throw new Error("recipeDifficultyCreate is required");
      const status = inputValue("recipeStatus");
      if (!status) throw new Error("recipeStatus is required");
      await apiRequest("/recipes", "POST", {
        name,
        description,
        prep_minutes: prepMinutes,
        step_count: stepCount,
        servings,
        difficulty,
        calories,
        estimated_cost: estimatedCost,
        ingredients: ingredientTerms,
        cookware: cookwareTerms,
        allergens: allergenTerms,
        status
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
      if (!selectedRecipeId) throw new Error("Select a recipe first");
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
      const pickupPointId = resolvePickupPointId();
      if (!selectedRecipeId) throw new Error("Select a recipe first");
      const slotStart = selectedSlot.slotStart;
      const slotEnd = selectedSlot.slotEnd;
      const quantity = Number(inputValue("bookingQuantity"));
      if (!Number.isFinite(quantity) || quantity < 1) throw new Error("bookingQuantity must be >= 1");
      const zip4 = inputValue("bookingZip4");
      const region = inputValue("bookingRegionCode");
      const latitude = inputValue("bookingLatitude");
      const longitude = inputValue("bookingLongitude");
      if (!zip4 || !region || !latitude || !longitude) {
        throw new Error("bookingZip4, bookingRegionCode, bookingLatitude and bookingLongitude are required");
      }
      const data = await apiRequest("/bookings", "POST", {
        recipe_id: selectedRecipeId,
        pickup_point_id: pickupPointId,
        pickup_at: slotStart,
        slot_start: slotStart,
        slot_end: slotEnd,
        quantity,
        customer_zip4: zip4,
        customer_region_code: region,
        customer_latitude: Number(latitude),
        customer_longitude: Number(longitude),
        note: inputValue("bookingNote")
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
      const moduleKey = inputValue("moduleKey");
      if (!moduleKey) throw new Error("moduleKey is required");
      const moduleTitle = inputValue("moduleTitle");
      const moduleBannerImage = inputValue("moduleBannerImage");
      const moduleBannerLink = inputValue("moduleBannerLink");
      if (!moduleTitle || !moduleBannerImage || !moduleBannerLink) {
        throw new Error("moduleTitle, moduleBannerImage and moduleBannerLink are required");
      }
      const data = await apiRequest("/operations/homepage-modules", "POST", {
        module_key: moduleKey,
        enabled: 1,
        banners: [{ title: moduleTitle, image: moduleBannerImage, link: moduleBannerLink }]
      });
      setFeedback("opsFeedback", data);
    } catch (e) { setFeedback("opsFeedback", e.message); }
  });
  document.getElementById("btnSaveTemplate").addEventListener("click", async () => {
    try {
      const templateCode = inputValue("templateCode");
      if (!templateCode) throw new Error("templateCode is required");
      const templateTitle = inputValue("templateTitle");
      const templateContent = inputValue("templateContent");
      const templateCategory = inputValue("templateCategory");
      if (!templateTitle || !templateContent || !templateCategory) {
        throw new Error("templateTitle, templateContent and templateCategory are required");
      }
      const data = await apiRequest("/operations/message-templates", "POST", {
        template_code: templateCode,
        title: templateTitle,
        content: templateContent,
        category: templateCategory,
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
      const amount = numberInput("paymentAmount", true);
      const method = inputValue("paymentMethod");
      const payerName = inputValue("payerName");
      if (!method || !payerName) throw new Error("paymentMethod and payerName are required");
      await apiRequest("/payments", "POST", {
        booking_id: bookingId,
        amount,
        method,
        status: "captured",
        payer_name: payerName
      });
      layui.layer.msg("Payment queued/created");
    } catch (e) { layui.layer.msg(e.message); }
  });

  document.getElementById("btnCreateGwOrder").addEventListener("click", async () => {
    try {
      const bookingId = Number(document.getElementById("bookingSelectedId").value || selectedBookingId || 0);
      if (!bookingId) throw new Error("Select a booking row first or enter a booking id");
      const amount = numberInput("gatewayOrderAmount", true);
      const data = await apiRequest("/payments/gateway/orders", "POST", { booking_id: bookingId, amount });
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
      const issueId = Number(inputValue("reconIssueId") || 0);
      if (!issueId) throw new Error("reconIssueId is required");
      const note = inputValue("reconRepairNote");
      if (!note) throw new Error("reconRepairNote is required");
      const data = await apiRequest("/payments/reconcile/repair", "POST", {
        issue_id: issueId,
        note,
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
      const paymentRef = inputValue("paymentRefInput") || selectedPaymentRef;
      if (!paymentRef) throw new Error("paymentRefInput is required");
      const data = await apiRequest("/payments/refund", "POST", {
        payment_ref: paymentRef,
        reauth_token: latestReauthToken
      });
      setFeedback("financeFeedback", data);
    } catch (e) { setFeedback("financeFeedback", e.message); }
  });
  document.getElementById("btnAdjust").addEventListener("click", async () => {
    try {
      const paymentRef = inputValue("paymentRefInput") || selectedPaymentRef;
      if (!paymentRef) throw new Error("paymentRefInput is required");
      const amount = numberInput("adjustAmount", true);
      const reason = inputValue("adjustReason");
      if (!reason) throw new Error("adjustReason is required");
      const data = await apiRequest("/payments/adjust", "POST", {
        payment_ref: paymentRef,
        amount,
        reason,
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
    try {
      const eventType = inputValue("eventType");
      const eventChannel = inputValue("eventChannel");
      if (!eventType || !eventChannel) throw new Error("eventType and eventChannel are required");
      await apiRequest("/notifications/events", "POST", {
        event_type: eventType,
        channel: eventChannel,
        payload: { source: eventChannel, created_at: new Date().toISOString() }
      });
      layui.layer.msg("Event queued");
    } catch (e) {
      setFeedback("msgFeedback", e.message);
    }
  });
  document.getElementById("btnSendMarketing").addEventListener("click", async () => {
    try {
      const userId = Number(inputValue("messageUserId") || 0);
      if (!userId) throw new Error("messageUserId is required");
      const title = inputValue("messageTitle");
      const body = inputValue("messageBody");
      if (!title || !body) throw new Error("messageTitle and messageBody are required");
      const data = await apiRequest("/notifications/messages", "POST", {
        user_id: userId,
        title,
        body,
        is_marketing: inputValue("messageMarketing") !== "0"
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
    try {
      const mimeType = inputValue("fileMimeType");
      if (!mimeType) throw new Error("fileMimeType is required");
      const filename = inputValue("fileName") || (mimeType === "text/csv" ? "upload.csv" : "upload.pdf");
      const ownerType = inputValue("fileOwnerType");
      if (!ownerType) throw new Error("fileOwnerType is required");
      const ownerId = Number(inputValue("fileOwnerId") || 0);
      const watermark = inputValue("fileWatermark") === "1";
      const manualContent = inputValue("fileContent");

      let contentBase64 = "";
      if (manualContent) {
        contentBase64 = btoa(unescape(encodeURIComponent(manualContent)));
      } else if (mimeType === "application/pdf") {
        contentBase64 = btoa("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n");
      } else if (mimeType === "text/csv") {
        contentBase64 = btoa("id,name\n1,example\n");
      } else if (mimeType === "image/png") {
        contentBase64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+Lw0AAAAASUVORK5CYII=";
      } else if (mimeType === "image/jpeg") {
        contentBase64 = "/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEBAVFhUVFRUVFRUVFRUVFRUVFRUXFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGhAQGi0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAADAQAAAAAAAAAAAAAAAAAAAQID/8QAFhEBAQEAAAAAAAAAAAAAAAAAAQAC/9oADAMBAAIQAxAAAAG2gP/EABcQAAMBAAAAAAAAAAAAAAAAAAABESL/2gAIAQEAAQUCyf/EABURAQEAAAAAAAAAAAAAAAAAABAR/9oACAEDAQE/Acf/xAAVEQEBAAAAAAAAAAAAAAAAAAAQEf/aAAgBAgEBPwGH/8QAFhABAQEAAAAAAAAAAAAAAAAAABEQ/9oACAEBAAY/Amf/xAAWEAEBAQAAAAAAAAAAAAAAAAABABH/2gAIAQEAAT8hY//aAAwDAQACAAMAAAAQ8//EABYRAQEBAAAAAAAAAAAAAAAAAAARAf/aAAgBAwEBPxBf/8QAFhEBAQEAAAAAAAAAAAAAAAAAABEB/9oACAECAQE/EDf/xAAWEAEBAQAAAAAAAAAAAAAAAAABABH/2gAIAQEAAT8QW3//2Q==";
      } else {
        throw new Error("Unsupported mime type for generated content");
      }

      const payload = { filename, mime_type: mimeType, content_base64: contentBase64, watermark };
      if (ownerType) payload.owner_type = ownerType;
      if (ownerId > 0) payload.owner_id = ownerId;
      const data = await apiRequest("/files/upload-base64", "POST", payload);
      setFeedback("fileFeedback", data);
    } catch (e) {
      setFeedback("fileFeedback", e.message);
    }
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

  [
    "username", "bookingZip4", "bookingRegionCode", "bookingLatitude", "bookingLongitude",
    "messageUserId", "paymentRefInput", "reconIssueId", "reconRepairNote", "pickupPointManual", "pickupPointSelect", "slotDate",
    "slotWindowStart", "slotWindowEnd", "slotStepMinutes", "bookingQuantity", "moduleKey", "moduleTitle", "moduleBannerImage",
    "moduleBannerLink", "templateCode", "templateTitle", "templateContent", "templateCategory", "paymentAmount", "paymentMethod",
    "payerName", "gatewayOrderAmount", "adjustAmount", "adjustReason", "eventType", "eventChannel", "messageTitle", "messageBody",
    "fileName", "fileOwnerType", "recipePrepMinutes", "recipeStepCount", "recipeServings", "recipeDifficultyCreate", "recipeCalories",
    "recipeEstimatedCost", "recipeStatus"
  ].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener("change", persistOperationalDefaults);
  });
}

layui.use([], async () => {
  setQueue(getQueue());
  if (getToken()) authStateEl.textContent = "Authenticated";
  await loadExplicitTestDefaults();
  applyInitialDefaults();
  bindEvents();
  await loadPickupPoints();
  try { await loadSlotPicker(); } catch (e) { setFeedback("bookingFeedback", e.message); }
  await apiRequest("/recipes").then((data) => {
    const items = data.items || [];
    if (items.length > 0) {
      selectedRecipeId = Number(items[0].id || 0);
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
