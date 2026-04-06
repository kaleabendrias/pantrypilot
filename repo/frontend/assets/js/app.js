/**
 * PantryPilot Application Bootstrap
 * Loads domain modules and initializes the SPA.
 */
(function () {
  const P = window.PantryPilot;

  function bindPersistenceListeners() {
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
      if (el) el.addEventListener("change", P.persistOperationalDefaults);
    });
  }

  async function loadDashboard() {
    const data = await P.apiRequest("/reporting/dashboard");
    const cards = document.getElementById("kpiCards");
    const icons = { recipes: "layui-icon-read", bookings: "layui-icon-form", pending_bookings: "layui-icon-time", captured_payments: "layui-icon-rmb" };
    cards.innerHTML = Object.entries(data).map(([k, v]) => `
      <div class="card kpi-card">
        <div class="kpi-icon"><i class="layui-icon ${icons[k] || "layui-icon-chart"}"></i></div>
        <h3>${P.escapeHtml(k.replace(/_/g, " "))}</h3>
        <div class="value">${P.escapeHtml(v)}</div>
      </div>`).join("");
  }

  layui.use([], async () => {
    P.setQueue(P.getQueue());
    if (P.getToken()) document.getElementById("authState").textContent = "Authenticated";
    await P.loadExplicitTestDefaults();
    P.applyInitialDefaults();

    // Bind auth
    document.getElementById("btnLogin").addEventListener("click", async () => {
      try { await P.login(); } catch (e) { layui.layer.msg(e.message); }
    });
    document.getElementById("btnSync").addEventListener("click", P.syncQueue);

    // Bind domain modules
    P.bindRecipeEvents();
    P.bindBookingEvents();
    P.bindOpsEvents();
    P.bindFinanceEvents();
    P.bindAdminEvents();
    bindPersistenceListeners();

    // Initial data load
    await P.loadPickupPoints();
    try { await P.loadSlotPicker(); } catch (e) { P.setFeedback("bookingFeedback", e.message); }
    await P.apiRequest("/recipes").then((data) => {
      const items = data.items || [];
      if (items.length > 0) { P.setSelectedRecipeId(Number(items[0].id || 0)); P.renderRecipeGrid(items); }
    }).catch(() => {});
    try { await loadDashboard(); } catch (e) { layui.layer.msg(`Dashboard unavailable: ${e.message}`); }
  });

  window.addEventListener("unhandledrejection", (event) => {
    const reason = event.reason;
    const message = reason && reason.message ? reason.message : String(reason || "Unexpected request failure");
    layui.layer.msg(message);
    event.preventDefault();
  });
})();
