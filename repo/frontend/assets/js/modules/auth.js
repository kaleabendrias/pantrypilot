/**
 * PantryPilot Auth Module
 * Login, role visibility, UI defaults persistence.
 */
(function () {
  const { apiRequest, setToken, getToken, inputValue } = window.PantryPilot;

  let currentUserRole = "";
  let currentUserPermissions = [];
  let explicitTestDefaults = null;

  function applyRoleVisibility() {
    const perms = currentUserPermissions || [];
    const role = currentUserRole;
    const tabItems = document.querySelectorAll(".layui-tab-title li");
    const has = (resource, perm) => perms.includes(`${resource}:${perm}`);

    let allowed;
    if (perms.length > 0) {
      allowed = [0];
      if (has("recipe", "read")) allowed.push(1);
      if (has("booking", "read") || has("booking", "write")) allowed.push(2);
      if (has("booking_ops", "read") || has("operations", "read")) allowed.push(3);
      if (has("payment", "read")) allowed.push(4);
      if (has("notification", "read")) allowed.push(5);
      if (has("file", "read")) allowed.push(6);
      if (has("admin", "read")) allowed.push(7);
    } else {
      const roleTabs = {
        customer: [0, 1, 2], ops_staff: [0, 1, 2, 3], staff: [0, 1, 2, 3],
        manager: [0, 1, 2, 3, 5, 6], finance: [0, 4], admin: [0, 1, 2, 3, 4, 5, 6, 7],
      };
      allowed = roleTabs[role] || [0];
    }
    tabItems.forEach((tab, idx) => { tab.style.display = allowed.includes(idx) ? "" : "none"; });
  }

  async function login() {
    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;
    const data = await apiRequest("/identity/login", "POST", { username, password });
    setToken(data.token);
    const user = data.user || {};
    currentUserRole = user.role || "";
    currentUserPermissions = user.permissions || [];
    applyRoleVisibility();
    layui.layer.msg("Login success");
  }

  function getUiDefaults() {
    if (explicitTestDefaults && typeof explicitTestDefaults === "object") return explicitTestDefaults;
    try {
      const parsed = JSON.parse(localStorage.getItem("pantrypilot_ui_defaults") || "{}");
      return parsed && typeof parsed === "object" ? parsed : {};
    } catch { return {}; }
  }

  function saveUiDefaults(next) {
    if (explicitTestDefaults) return;
    localStorage.setItem("pantrypilot_ui_defaults", JSON.stringify(next));
  }

  async function loadExplicitTestDefaults() {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get("ui_test_defaults") !== "1") return;
    try {
      const resp = await fetch("/test-config/ui-defaults.json", { cache: "no-store" });
      if (!resp.ok) return;
      const parsed = await resp.json();
      if (parsed && typeof parsed === "object") explicitTestDefaults = parsed;
    } catch { explicitTestDefaults = null; }
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
      const el = document.getElementById("slotDate");
      if (el) el.value = `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, "0")}-${String(tomorrow.getDate()).padStart(2, "0")}`;
    }
  }

  function persistOperationalDefaults() {
    const defaults = getUiDefaults();
    const next = { ...defaults };
    const fields = [
      ["username", "username"], ["bookingZip4", "booking_zip4"], ["bookingRegionCode", "booking_region_code"],
      ["bookingLatitude", "booking_latitude"], ["bookingLongitude", "booking_longitude"],
      ["messageUserId", "message_user_id"], ["paymentRefInput", "payment_ref"],
      ["reconIssueId", "recon_issue_id"], ["reconRepairNote", "recon_repair_note"],
      ["slotDate", "slot_date"], ["slotWindowStart", "slot_window_start"],
      ["slotWindowEnd", "slot_window_end"], ["slotStepMinutes", "slot_step_minutes"],
      ["bookingQuantity", "booking_quantity"], ["moduleKey", "module_key"], ["moduleTitle", "module_title"],
      ["moduleBannerImage", "module_banner_image"], ["moduleBannerLink", "module_banner_link"],
      ["templateCode", "template_code"], ["templateTitle", "template_title"],
      ["templateContent", "template_content"], ["templateCategory", "template_category"],
      ["paymentAmount", "payment_amount"], ["paymentMethod", "payment_method"], ["payerName", "payer_name"],
      ["gatewayOrderAmount", "gateway_order_amount"], ["adjustAmount", "adjust_amount"],
      ["adjustReason", "adjust_reason"], ["eventType", "event_type"], ["eventChannel", "event_channel"],
      ["messageTitle", "message_title"], ["messageBody", "message_body"],
      ["fileName", "file_name"], ["fileOwnerType", "file_owner_type"],
      ["recipePrepMinutes", "recipe_prep_minutes"], ["recipeStepCount", "recipe_step_count"],
      ["recipeServings", "recipe_servings"], ["recipeDifficultyCreate", "recipe_difficulty"],
      ["recipeCalories", "recipe_calories"], ["recipeEstimatedCost", "recipe_estimated_cost"],
      ["recipeStatus", "recipe_status"],
    ];
    fields.forEach(([id, key]) => { next[key] = inputValue(id); });
    const pp = inputValue("pickupPointManual") || inputValue("pickupPointSelect");
    if (pp) next.pickup_point_id = pp;
    saveUiDefaults(next);
  }

  window.PantryPilot = Object.assign(window.PantryPilot || {}, {
    login, applyRoleVisibility, applyInitialDefaults, persistOperationalDefaults,
    loadExplicitTestDefaults, getCurrentRole: () => currentUserRole,
    getCurrentPermissions: () => currentUserPermissions,
  });
})();
