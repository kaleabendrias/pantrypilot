/**
 * PantryPilot Operations Module
 * Staff pickups, check-in, no-show sweep, dispatch, campaigns, modules, templates, manager dashboard.
 */
(function () {
  const P = window.PantryPilot;

  function bindOpsEvents() {
    document.getElementById("btnTodayPickups").addEventListener("click", async () => { const data = await P.apiRequest("/bookings/today-pickups"); P.renderTable("opsTable", data.items || []); });
    document.getElementById("btnCheckIn").addEventListener("click", async () => {
      const btn = document.getElementById("btnCheckIn"); if (!P.guardSubmit("checkIn", btn)) return;
      try { const bookingId = Number(document.getElementById("bookingSelectedId").value || P.getSelectedBookingId() || 0);
        if (!bookingId) throw new Error("Select a booking row first"); const data = await P.apiRequest("/bookings/check-in", "POST", { booking_id: bookingId });
        P.setFeedback("opsFeedback", data); } catch (e) { P.setFeedback("opsFeedback", e.message); } finally { P.releaseSubmit("checkIn", btn); } });
    document.getElementById("btnSweepNoShow").addEventListener("click", async () => {
      const btn = document.getElementById("btnSweepNoShow"); if (!P.guardSubmit("sweepNoShow", btn)) return;
      try { const data = await P.apiRequest("/bookings/no-show-sweep", "POST", {}); P.setFeedback("opsFeedback", data); } catch (e) { P.setFeedback("opsFeedback", e.message); } finally { P.releaseSubmit("sweepNoShow", btn); } });
    document.getElementById("btnDispatchNote").addEventListener("click", async () => {
      try { const bookingId = Number(document.getElementById("bookingSelectedId").value || P.getSelectedBookingId() || 0);
        if (!bookingId) throw new Error("Select a booking row first"); const data = await P.apiRequest(`/bookings/${bookingId}/dispatch-note`);
        P.setFeedback("opsFeedback", data); } catch (e) { P.setFeedback("opsFeedback", e.message); } });
    document.getElementById("btnLoadModules").addEventListener("click", async () => { try { const data = await P.apiRequest("/operations/homepage-modules"); P.renderTable("opsTable", data.items || []); } catch (e) { P.setFeedback("opsFeedback", e.message); } });
    document.getElementById("btnSaveModule").addEventListener("click", async () => {
      const btn = document.getElementById("btnSaveModule"); if (!P.guardSubmit("saveModule", btn)) return;
      try { const k = P.inputValue("moduleKey"); if (!k) throw new Error("moduleKey required"); const t = P.inputValue("moduleTitle"), img = P.inputValue("moduleBannerImage"), lnk = P.inputValue("moduleBannerLink");
        if (!t || !img || !lnk) throw new Error("moduleTitle, moduleBannerImage, moduleBannerLink required");
        const data = await P.apiRequest("/operations/homepage-modules", "POST", { module_key: k, enabled: 1, banners: [{ title: t, image: img, link: lnk }] }); P.setFeedback("opsFeedback", data);
      } catch (e) { P.setFeedback("opsFeedback", e.message); } finally { P.releaseSubmit("saveModule", btn); } });
    document.getElementById("btnSaveTemplate").addEventListener("click", async () => {
      const btn = document.getElementById("btnSaveTemplate"); if (!P.guardSubmit("saveTemplate", btn)) return;
      try { const code = P.inputValue("templateCode"); if (!code) throw new Error("templateCode required");
        const t = P.inputValue("templateTitle"), c = P.inputValue("templateContent"), cat = P.inputValue("templateCategory");
        if (!t || !c || !cat) throw new Error("templateTitle, templateContent, templateCategory required");
        const data = await P.apiRequest("/operations/message-templates", "POST", { template_code: code, title: t, content: c, category: cat, active: 1 }); P.setFeedback("opsFeedback", data);
      } catch (e) { P.setFeedback("opsFeedback", e.message); } finally { P.releaseSubmit("saveTemplate", btn); } });
    document.getElementById("btnLoadCampaigns").addEventListener("click", async () => { try { const data = await P.apiRequest("/operations/campaigns"); P.renderTable("opsTable", data.items || []); } catch (e) { P.setFeedback("opsFeedback", e.message); } });
    document.getElementById("btnCreateCampaign").addEventListener("click", async () => {
      const btn = document.getElementById("btnCreateCampaign"); if (!P.guardSubmit("createCampaign", btn)) return;
      try { const name = P.inputValue("campaignName"); if (!name) throw new Error("campaignName required");
        const startAt = P.inputValue("campaignStartAt"), endAt = P.inputValue("campaignEndAt");
        if (!startAt || !endAt) throw new Error("campaign start and end times required");
        const budget = Number(P.inputValue("campaignBudget") || "0");
        const data = await P.apiRequest("/operations/campaigns", "POST", { name, slot: P.inputValue("campaignSlot") || "default", start_at: startAt.replace("T", " "), end_at: endAt.replace("T", " "), budget, status: "planned" }); P.setFeedback("opsFeedback", data);
      } catch (e) { P.setFeedback("opsFeedback", e.message); } finally { P.releaseSubmit("createCampaign", btn); } });
    document.getElementById("btnLoadTemplates").addEventListener("click", async () => { try { const data = await P.apiRequest("/operations/message-templates"); P.renderTable("opsTable", data.items || []); } catch (e) { P.setFeedback("opsFeedback", e.message); } });
    document.getElementById("btnManagerDashboard").addEventListener("click", async () => { try { const data = await P.apiRequest("/operations/dashboard"); P.setFeedback("opsFeedback", data); } catch (e) { P.setFeedback("opsFeedback", e.message); } });
    document.getElementById("btnManagerAnomalies").addEventListener("click", async () => { try { const data = await P.apiRequest("/reporting/anomalies"); P.setFeedback("opsFeedback", data); } catch (e) { P.setFeedback("opsFeedback", e.message); } });
    document.getElementById("btnManagerExportCsv").addEventListener("click", async () => {
      try { const data = await P.apiRequest("/reporting/exports/bookings-csv"); const raw = atob(data.content_base64);
        const blob = new Blob([raw], { type: "text/csv" }); const url = URL.createObjectURL(blob); const a = document.createElement("a");
        a.href = url; a.download = data.filename || "export.csv"; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
        P.setFeedback("opsFeedback", `CSV downloaded: ${data.filename}`); } catch (e) { P.setFeedback("opsFeedback", e.message); } });
  }

  window.PantryPilot = Object.assign(window.PantryPilot || {}, { bindOpsEvents });
})();
