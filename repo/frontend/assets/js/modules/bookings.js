/**
 * PantryPilot Bookings Module
 * Booking creation, slot picker, pickup point loading.
 */
(function () {
  const P = window.PantryPilot;
  let selectedSlot = null;
  let selectedBookingId = 0;
  let pickupPointsCache = [];

  async function loadPickupPoints() {
    const select = document.getElementById("pickupPointSelect");
    if (!select) return;
    try {
      const data = await P.apiRequest("/bookings/pickup-points");
      pickupPointsCache = data.items || [];
      select.innerHTML = `<option value="">Pickup point</option>` + pickupPointsCache
        .map((p) => `<option value="${P.escapeHtml(p.id)}">${P.escapeHtml(p.name || "Point")} (#${P.escapeHtml(p.id)})</option>`)
        .join("");
      const preferred = P.inputValue("pickupPointManual");
      if (preferred) select.value = preferred;
    } catch { pickupPointsCache = []; }
  }

  function resolvePickupPointId() {
    const fromSelect = Number(P.inputValue("pickupPointSelect") || 0);
    if (fromSelect > 0) return fromSelect;
    const fromManual = Number(P.inputValue("pickupPointManual") || 0);
    if (fromManual > 0) return fromManual;
    throw new Error("Pickup point is required.");
  }

  async function loadSlotPicker() {
    const picker = document.getElementById("slotPicker");
    const pickupPointId = resolvePickupPointId();
    const dateRaw = P.inputValue("slotDate"); if (!dateRaw) throw new Error("slotDate is required");
    const [year, month, day] = dateRaw.split("-").map(v => Number(v));
    const startRaw = P.inputValue("slotWindowStart"); const endRaw = P.inputValue("slotWindowEnd");
    if (!startRaw || !endRaw) throw new Error("slotWindowStart and slotWindowEnd are required");
    const stepMinutes = Number(P.inputValue("slotStepMinutes"));
    if (!Number.isFinite(stepMinutes) || stepMinutes < 15) throw new Error("slotStepMinutes must be >= 15");
    const [sh, sm] = startRaw.split(":").map(v => Number(v));
    const [eh, em] = endRaw.split(":").map(v => Number(v));
    const startMin = sh * 60 + sm; const endMin = eh * 60 + em;
    if (endMin <= startMin) throw new Error("slot window end must be later than start");

    const cards = [];
    for (let cursor = startMin; cursor + stepMinutes <= endMin; cursor += stepMinutes) {
      const start = new Date(year, month - 1, day, 0, 0, 0, 0); start.setMinutes(cursor);
      const end = new Date(start.getTime() + stepMinutes * 60 * 1000);
      const slotStart = start.toISOString().slice(0, 19).replace("T", " ");
      const slotEnd = end.toISOString().slice(0, 19).replace("T", " ");
      try {
        const cap = await P.apiRequest(`/bookings/slot-capacity?pickup_point_id=${pickupPointId}&slot_start=${encodeURIComponent(slotStart)}&slot_end=${encodeURIComponent(slotEnd)}`);
        cards.push({ slotStart, slotEnd, remaining: Number(cap.remaining || 0), capacity: Number(cap.capacity || 0) });
      } catch { cards.push({ slotStart, slotEnd, remaining: -1, capacity: -1 }); }
    }
    if (!cards.length) throw new Error("No slots generated.");

    picker.innerHTML = cards.map((slot, idx) => `
      <button class="slot-card ${slot.remaining <= 0 ? "disabled" : ""}" data-idx="${idx}" ${slot.remaining <= 0 ? "disabled" : ""}>
        <strong>${slot.slotStart.slice(11, 16)} - ${slot.slotEnd.slice(11, 16)}</strong>
        <span>${slot.remaining >= 0 ? `${slot.remaining}/${slot.capacity} remaining` : "Unavailable"}</span>
      </button>
    `).join("");

    picker.querySelectorAll(".slot-card").forEach((btn) => {
      btn.addEventListener("click", () => {
        picker.querySelectorAll(".slot-card").forEach(x => x.classList.remove("active"));
        btn.classList.add("active");
        selectedSlot = cards[Number(btn.dataset.idx || "0")];
        P.setFeedback("bookingFeedback", { selected_recipe_id: P.getSelectedRecipeId(), pickup_point_id: pickupPointId, selected_slot: selectedSlot });
      });
    });
  }

  function bindBookingEvents() {
    document.getElementById("btnLoadBookings").addEventListener("click", async () => {
      const data = await P.apiRequest("/bookings"); P.renderTable("bookingsTable", data.items || []);
      const trs = document.getElementById("bookingsTable").querySelectorAll("tr");
      trs.forEach((tr, idx) => { if (idx === 0) return; tr.style.cursor = "pointer"; tr.addEventListener("click", () => {
        trs.forEach((x, i) => { if (i > 0) x.classList.remove("active-row"); }); tr.classList.add("active-row");
        const rows = data.items || []; const source = rows[idx - 1] || {};
        selectedBookingId = Number(source.id || 0);
        const input = document.getElementById("bookingSelectedId"); if (input) input.value = selectedBookingId ? String(selectedBookingId) : "";
      }); });
    });
    document.getElementById("btnRecipeDetail").addEventListener("click", async () => {
      try { if (!P.getSelectedRecipeId()) throw new Error("Select a recipe first");
        const data = await P.apiRequest(`/bookings/recipe/${P.getSelectedRecipeId()}`);
        P.setFeedback("bookingFeedback", data);
      } catch (e) { P.setFeedback("bookingFeedback", e.message); }
    });
    document.getElementById("btnCheckCapacity").addEventListener("click", async () => {
      try { await loadSlotPicker(); P.setFeedback("bookingFeedback", "Slot capacities refreshed"); } catch (e) { P.setFeedback("bookingFeedback", e.message); }
    });
    document.getElementById("btnCreateBooking").addEventListener("click", async () => {
      const btn = document.getElementById("btnCreateBooking");
      if (!P.guardSubmit("createBooking", btn)) return;
      try {
        if (!selectedSlot) throw new Error("Please choose a slot first");
        const pickupPointId = resolvePickupPointId();
        if (!P.getSelectedRecipeId()) throw new Error("Select a recipe first");
        const quantity = Number(P.inputValue("bookingQuantity"));
        if (!Number.isFinite(quantity) || quantity < 1) throw new Error("bookingQuantity must be >= 1");
        const zip4 = P.inputValue("bookingZip4"), region = P.inputValue("bookingRegionCode"), lat = P.inputValue("bookingLatitude"), lon = P.inputValue("bookingLongitude");
        if (!zip4 || !region || !lat || !lon) throw new Error("bookingZip4, bookingRegionCode, bookingLatitude and bookingLongitude are required");
        const data = await P.apiRequest("/bookings", "POST", {
          recipe_id: P.getSelectedRecipeId(), pickup_point_id: pickupPointId, pickup_at: selectedSlot.slotStart,
          slot_start: selectedSlot.slotStart, slot_end: selectedSlot.slotEnd, quantity,
          customer_zip4: zip4, customer_region_code: region, customer_latitude: Number(lat), customer_longitude: Number(lon), note: P.inputValue("bookingNote")
        });
        P.setFeedback("bookingFeedback", data); layui.layer.msg("Booking created");
      } catch (e) { P.setFeedback("bookingFeedback", e.message); } finally { P.releaseSubmit("createBooking", btn); }
    });
  }

  window.PantryPilot = Object.assign(window.PantryPilot || {}, {
    loadPickupPoints, loadSlotPicker, bindBookingEvents,
    getSelectedBookingId: () => selectedBookingId,
  });
})();
