/**
 * PantryPilot Finance Module
 * Payments, gateway orders, reconciliation, reauth, refunds, adjustments.
 */
(function () {
  const P = window.PantryPilot;
  let latestBatchRef = "";
  let latestReauthToken = "";
  let selectedPaymentRef = "";

  function bindFinanceEvents() {
    document.getElementById("btnLoadPayments").addEventListener("click", async () => {
      const data = await P.apiRequest("/payments"); P.renderTable("paymentsTable", data.items || []);
      const trs = document.getElementById("paymentsTable").querySelectorAll("tr");
      trs.forEach((tr, idx) => { if (idx === 0) return; tr.style.cursor = "pointer"; tr.addEventListener("click", () => {
        trs.forEach((x, i) => { if (i > 0) x.classList.remove("active-row"); }); tr.classList.add("active-row");
        const source = (data.items || [])[idx - 1] || {}; selectedPaymentRef = String(source.payment_ref || "");
        const input = document.getElementById("paymentRefInput"); if (input && selectedPaymentRef) input.value = selectedPaymentRef;
      }); });
    });
    document.getElementById("btnListBatches").addEventListener("click", async () => { try { const data = await P.apiRequest("/payments/reconcile/batches"); P.renderTable("paymentsTable", data.items || []); } catch (e) { P.setFeedback("financeFeedback", e.message); } });
    document.getElementById("btnListIssues").addEventListener("click", async () => { try { const br = latestBatchRef ? `?batch_ref=${encodeURIComponent(latestBatchRef)}` : ""; const data = await P.apiRequest(`/payments/reconcile/issues${br}`); P.renderTable("paymentsTable", data.items || []); } catch (e) { P.setFeedback("financeFeedback", e.message); } });
    document.getElementById("btnCreatePayment").addEventListener("click", async () => {
      const btn = document.getElementById("btnCreatePayment"); if (!P.guardSubmit("createPayment", btn)) return;
      try { const bookingId = Number(document.getElementById("bookingSelectedId").value || P.getSelectedBookingId() || 0);
        if (!bookingId) throw new Error("Select a booking row first"); const amount = P.numberInput("paymentAmount", true);
        const method = P.inputValue("paymentMethod"), payerName = P.inputValue("payerName");
        if (!method || !payerName) throw new Error("paymentMethod and payerName required");
        await P.apiRequest("/payments", "POST", { booking_id: bookingId, amount, method, status: "captured", payer_name: payerName });
        layui.layer.msg("Payment created"); } catch (e) { layui.layer.msg(e.message); } finally { P.releaseSubmit("createPayment", btn); } });
    document.getElementById("btnCreateGwOrder").addEventListener("click", async () => {
      const btn = document.getElementById("btnCreateGwOrder"); if (!P.guardSubmit("createGwOrder", btn)) return;
      try { const bookingId = Number(document.getElementById("bookingSelectedId").value || P.getSelectedBookingId() || 0);
        if (!bookingId) throw new Error("Select a booking row first"); const amount = P.numberInput("gatewayOrderAmount", true);
        const data = await P.apiRequest("/payments/gateway/orders", "POST", { booking_id: bookingId, amount }); P.setFeedback("financeFeedback", data);
      } catch (e) { P.setFeedback("financeFeedback", e.message); } finally { P.releaseSubmit("createGwOrder", btn); } });
    document.getElementById("btnAutoCancelGw").addEventListener("click", async () => { const btn = document.getElementById("btnAutoCancelGw"); if (!P.guardSubmit("autoCancelGw", btn)) return; try { const data = await P.apiRequest("/payments/gateway/auto-cancel", "POST", {}); P.setFeedback("financeFeedback", data); } catch (e) { P.setFeedback("financeFeedback", e.message); } finally { P.releaseSubmit("autoCancelGw", btn); } });
    document.getElementById("btnDailyRecon").addEventListener("click", async () => { const btn = document.getElementById("btnDailyRecon"); if (!P.guardSubmit("dailyRecon", btn)) return; try { const data = await P.apiRequest("/payments/reconcile/daily", "POST", { date: new Date().toISOString().slice(0, 10) }); latestBatchRef = data.batch_ref || ""; P.setFeedback("financeFeedback", data); } catch (e) { P.setFeedback("financeFeedback", e.message); } finally { P.releaseSubmit("dailyRecon", btn); } });
    document.getElementById("btnIssueReauth").addEventListener("click", async () => { try { const pwd = document.getElementById("password").value; const data = await P.apiRequest("/payments/reauth", "POST", { password: pwd }); latestReauthToken = data.reauth_token; P.setFeedback("financeFeedback", { reauth_token_received: true, expire_at: data.expire_at }); } catch (e) { P.setFeedback("financeFeedback", e.message); } });
    document.getElementById("btnRepairIssue").addEventListener("click", async () => { const btn = document.getElementById("btnRepairIssue"); if (!P.guardSubmit("repairIssue", btn)) return; try { const issueId = Number(P.inputValue("reconIssueId") || 0); if (!issueId) throw new Error("reconIssueId required"); const note = P.inputValue("reconRepairNote"); if (!note) throw new Error("reconRepairNote required"); const data = await P.apiRequest("/payments/reconcile/repair", "POST", { issue_id: issueId, note, reauth_token: latestReauthToken }); P.setFeedback("financeFeedback", data); } catch (e) { P.setFeedback("financeFeedback", e.message); } finally { P.releaseSubmit("repairIssue", btn); } });
    document.getElementById("btnCloseBatch").addEventListener("click", async () => { const btn = document.getElementById("btnCloseBatch"); if (!P.guardSubmit("closeBatch", btn)) return; try { const data = await P.apiRequest("/payments/reconcile/close", "POST", { batch_ref: latestBatchRef, reauth_token: latestReauthToken }); P.setFeedback("financeFeedback", data); } catch (e) { P.setFeedback("financeFeedback", e.message); } finally { P.releaseSubmit("closeBatch", btn); } });
    document.getElementById("btnRefund").addEventListener("click", async () => { const btn = document.getElementById("btnRefund"); if (!P.guardSubmit("refund", btn)) return; try { const paymentRef = P.inputValue("paymentRefInput") || selectedPaymentRef; if (!paymentRef) throw new Error("paymentRefInput required"); const data = await P.apiRequest("/payments/refund", "POST", { payment_ref: paymentRef, reauth_token: latestReauthToken }); P.setFeedback("financeFeedback", data); } catch (e) { P.setFeedback("financeFeedback", e.message); } finally { P.releaseSubmit("refund", btn); } });
    document.getElementById("btnAdjust").addEventListener("click", async () => { const btn = document.getElementById("btnAdjust"); if (!P.guardSubmit("adjust", btn)) return; try { const paymentRef = P.inputValue("paymentRefInput") || selectedPaymentRef; if (!paymentRef) throw new Error("paymentRefInput required"); const amount = P.numberInput("adjustAmount", true); const reason = P.inputValue("adjustReason"); if (!reason) throw new Error("adjustReason required"); const data = await P.apiRequest("/payments/adjust", "POST", { payment_ref: paymentRef, amount, reason, reauth_token: latestReauthToken }); P.setFeedback("financeFeedback", data); } catch (e) { P.setFeedback("financeFeedback", e.message); } finally { P.releaseSubmit("adjust", btn); } });
  }

  window.PantryPilot = Object.assign(window.PantryPilot || {}, { bindFinanceEvents });
})();
