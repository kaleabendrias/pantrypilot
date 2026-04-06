/**
 * PantryPilot Admin, Messages, Files Module
 * User management, audit, roles, permissions, notifications, file governance.
 */
(function () {
  const P = window.PantryPilot;
  let selectedFileId = 0;

  function bindAdminEvents() {
    // Messages
    document.getElementById("btnLoadEvents").addEventListener("click", async () => { const data = await P.apiRequest("/notifications/events"); P.renderTable("eventsTable", data.items || []); });
    document.getElementById("btnQueueEvent").addEventListener("click", async () => { const btn = document.getElementById("btnQueueEvent"); if (!P.guardSubmit("queueEvent", btn)) return; try { const et = P.inputValue("eventType"), ec = P.inputValue("eventChannel"); if (!et || !ec) throw new Error("eventType and eventChannel required"); await P.apiRequest("/notifications/events", "POST", { event_type: et, channel: ec, payload: { source: ec, created_at: new Date().toISOString() } }); layui.layer.msg("Event queued"); } catch (e) { P.setFeedback("msgFeedback", e.message); } finally { P.releaseSubmit("queueEvent", btn); } });
    document.getElementById("btnSendMarketing").addEventListener("click", async () => { const btn = document.getElementById("btnSendMarketing"); if (!P.guardSubmit("sendMarketing", btn)) return; try { const userId = Number(P.inputValue("messageUserId") || 0); if (!userId) throw new Error("messageUserId required"); const title = P.inputValue("messageTitle"), body = P.inputValue("messageBody"); if (!title || !body) throw new Error("messageTitle and messageBody required"); const data = await P.apiRequest("/notifications/messages", "POST", { user_id: userId, title, body, is_marketing: P.inputValue("messageMarketing") !== "0" }); P.setFeedback("msgFeedback", data); } catch (e) { P.setFeedback("msgFeedback", e.message); } finally { P.releaseSubmit("sendMarketing", btn); } });
    document.getElementById("btnLoadInbox").addEventListener("click", async () => { const data = await P.apiRequest("/notifications/inbox"); P.renderTable("eventsTable", data.items || []); });
    document.getElementById("btnMsgAnalytics").addEventListener("click", async () => { const data = await P.apiRequest("/notifications/analytics"); P.setFeedback("msgFeedback", data); });

    // Files
    document.getElementById("btnUploadMockFile").addEventListener("click", async () => {
      const btn = document.getElementById("btnUploadMockFile"); if (!P.guardSubmit("uploadFile", btn)) return;
      try { const mimeType = P.inputValue("fileMimeType"); if (!mimeType) throw new Error("fileMimeType required");
        const filename = P.inputValue("fileName") || (mimeType === "text/csv" ? "upload.csv" : "upload.pdf");
        const ownerType = P.inputValue("fileOwnerType"); if (!ownerType) throw new Error("fileOwnerType required");
        const ownerId = Number(P.inputValue("fileOwnerId") || 0), watermark = P.inputValue("fileWatermark") === "1", manualContent = P.inputValue("fileContent");
        let contentBase64 = "";
        if (manualContent) { contentBase64 = btoa(unescape(encodeURIComponent(manualContent))); }
        else if (mimeType === "application/pdf") { contentBase64 = btoa("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"); }
        else if (mimeType === "text/csv") { contentBase64 = btoa("id,name\n1,example\n"); }
        else if (mimeType === "image/png") { contentBase64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+Lw0AAAAASUVORK5CYII="; }
        else if (mimeType === "image/jpeg") { contentBase64 = "/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEBAVFhUVFRUVFRUVFRUVFRUVFRUXFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGhAQGi0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAADAQAAAAAAAAAAAAAAAAAAAQID/8QAFhEBAQEAAAAAAAAAAAAAAAAAAQAC/9oADAMBAAIQAxAAAAG2gP/EABcQAAMBAAAAAAAAAAAAAAAAAAABESL/2gAIAQEAAQUCyf/EABURAQEAAAAAAAAAAAAAAAAAABAR/9oACAEDAQE/Acf/xAAVEQEBAAAAAAAAAAAAAAAAAAAQEf/aAAgBAgEBPwGH/8QAFhABAQEAAAAAAAAAAAAAAAAAABEQ/9oACAEBAAY/Amf/xAAWEAEBAQAAAAAAAAAAAAAAAAABABH/2gAIAQEAAT8hY//aAAwDAQACAAMAAAAQ8//EABYRAQEBAAAAAAAAAAAAAAAAAAARAf/aAAgBAwEBPxBf/8QAFhEBAQEAAAAAAAAAAAAAAAAAABEB/9oACAECAQE/EDf/xAAWEAEBAQAAAAAAAAAAAAAAAAABABH/2gAIAQEAAT8QW3//2Q=="; }
        else { throw new Error("Unsupported mime type"); }
        const payload = { filename, mime_type: mimeType, content_base64: contentBase64, watermark };
        if (ownerType) payload.owner_type = ownerType; if (ownerId > 0) payload.owner_id = ownerId;
        const data = await P.apiRequest("/files/upload-base64", "POST", payload); P.setFeedback("fileFeedback", data);
      } catch (e) { P.setFeedback("fileFeedback", e.message); } finally { P.releaseSubmit("uploadFile", btn); } });
    document.getElementById("btnLoadFiles").addEventListener("click", async () => {
      const data = await P.apiRequest("/files"); P.renderTable("filesTable", data.items || []);
      const trs = document.getElementById("filesTable").querySelectorAll("tr");
      trs.forEach((tr, idx) => { if (idx === 0) return; tr.style.cursor = "pointer"; tr.addEventListener("click", () => {
        trs.forEach((x, i) => { if (i > 0) x.classList.remove("active-row"); }); tr.classList.add("active-row");
        const source = (data.items || [])[idx - 1] || {}; selectedFileId = Number(source.id || 0);
        const input = document.getElementById("fileSelectedId"); if (input) input.value = selectedFileId ? String(selectedFileId) : "";
      }); });
    });
    document.getElementById("btnSignFile").addEventListener("click", async () => { try { const fileId = Number(document.getElementById("fileSelectedId").value || selectedFileId || 0); if (!fileId) throw new Error("Select a file row first"); const data = await P.apiRequest(`/files/${fileId}/signed-url`); P.setFeedback("fileFeedback", data); } catch (e) { P.setFeedback("fileFeedback", e.message); } });
    document.getElementById("btnCleanupFiles").addEventListener("click", async () => { const data = await P.apiRequest("/files/cleanup", "POST", {}); P.setFeedback("fileFeedback", data); });

    // Admin
    document.getElementById("btnLoadUsers").addEventListener("click", async () => { const data = await P.apiRequest("/admin/users"); P.renderTable("usersTable", data.items || []); });
    document.getElementById("btnLoadAudit").addEventListener("click", async () => { const data = await P.apiRequest("/admin/audit-logs"); P.renderTable("auditTable", data.items || []); });
    document.getElementById("btnOpsDashboard").addEventListener("click", async () => { const data = await P.apiRequest("/operations/dashboard"); P.setFeedback("adminFeedback", data); });
    document.getElementById("btnAnomalies").addEventListener("click", async () => { const data = await P.apiRequest("/reporting/anomalies"); P.setFeedback("adminFeedback", data); });
    document.getElementById("btnExportCsv").addEventListener("click", async () => { try { const data = await P.apiRequest("/reporting/exports/bookings-csv"); const raw = atob(data.content_base64); const blob = new Blob([raw], { type: "text/csv" }); const url = URL.createObjectURL(blob); const a = document.createElement("a"); a.href = url; a.download = data.filename || "export.csv"; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url); P.setFeedback("adminFeedback", `CSV downloaded: ${data.filename}`); } catch (e) { P.setFeedback("adminFeedback", e.message); } });
    document.getElementById("btnCreateRole").addEventListener("click", async () => { const code = `role_${Date.now()}`; const data = await P.apiRequest("/admin/roles", "POST", { code, name: `Role ${code}` }); P.setFeedback("adminFeedback", data); });
    document.getElementById("btnLoadRoles").addEventListener("click", async () => { const data = await P.apiRequest("/admin/roles"); P.setFeedback("adminFeedback", data.items || []); });
    document.getElementById("btnLoadPerms").addEventListener("click", async () => { const perms = await P.apiRequest("/admin/permissions"); const resources = await P.apiRequest("/admin/resources"); P.setFeedback("adminFeedback", { permissions: perms.items || [], resources: resources.items || [] }); });
    document.getElementById("btnEnableUser").addEventListener("click", async () => { const btn = document.getElementById("btnEnableUser"); if (!P.guardSubmit("enableUser", btn)) return; try { const userId = P.numberInput("adminTargetUserId", true); const data = await P.apiRequest(`/admin/users/${userId}/enable`, "POST", {}); P.setFeedback("adminFeedback", data); } catch (e) { P.setFeedback("adminFeedback", e.message); } finally { P.releaseSubmit("enableUser", btn); } });
    document.getElementById("btnDisableUser").addEventListener("click", async () => { const btn = document.getElementById("btnDisableUser"); if (!P.guardSubmit("disableUser", btn)) return; try { const userId = P.numberInput("adminTargetUserId", true); const data = await P.apiRequest(`/admin/users/${userId}/disable`, "POST", {}); P.setFeedback("adminFeedback", data); } catch (e) { P.setFeedback("adminFeedback", e.message); } finally { P.releaseSubmit("disableUser", btn); } });
    document.getElementById("btnResetPassword").addEventListener("click", async () => { const btn = document.getElementById("btnResetPassword"); if (!P.guardSubmit("resetPassword", btn)) return; try { const userId = P.numberInput("adminTargetUserId", true); const newPassword = P.inputValue("adminNewPassword"); if (!newPassword) throw new Error("adminNewPassword required"); const data = await P.apiRequest(`/admin/users/${userId}/reset-password`, "POST", { new_password: newPassword }); P.setFeedback("adminFeedback", data); } catch (e) { P.setFeedback("adminFeedback", e.message); } finally { P.releaseSubmit("resetPassword", btn); } });
    document.getElementById("btnGrantPermission").addEventListener("click", async () => { const btn = document.getElementById("btnGrantPermission"); if (!P.guardSubmit("grantPermission", btn)) return; try { const data = await P.apiRequest("/admin/grants", "POST", { role_id: P.numberInput("adminRoleId", true), permission_id: P.numberInput("adminPermId", true), resource_id: P.numberInput("adminResourceId", true) }); P.setFeedback("adminFeedback", data); } catch (e) { P.setFeedback("adminFeedback", e.message); } finally { P.releaseSubmit("grantPermission", btn); } });
    document.getElementById("btnAssignRole").addEventListener("click", async () => { const btn = document.getElementById("btnAssignRole"); if (!P.guardSubmit("assignRole", btn)) return; try { const data = await P.apiRequest("/admin/user-roles", "POST", { user_id: P.numberInput("adminTargetUserId", true), role_id: P.numberInput("adminRoleId", true) }); P.setFeedback("adminFeedback", data); } catch (e) { P.setFeedback("adminFeedback", e.message); } finally { P.releaseSubmit("assignRole", btn); } });
    document.getElementById("btnUpdateScopes").addEventListener("click", async () => { const btn = document.getElementById("btnUpdateScopes"); if (!P.guardSubmit("updateScopes", btn)) return; try { const userId = P.numberInput("adminTargetUserId", true); const store = P.inputValue("adminScopeStore").split(",").map(s => s.trim()).filter(Boolean); const warehouse = P.inputValue("adminScopeWarehouse").split(",").map(s => s.trim()).filter(Boolean); const department = P.inputValue("adminScopeDept").split(",").map(s => s.trim()).filter(Boolean); const data = await P.apiRequest(`/admin/users/${userId}/scopes`, "POST", { store, warehouse, department }); P.setFeedback("adminFeedback", data); } catch (e) { P.setFeedback("adminFeedback", e.message); } finally { P.releaseSubmit("updateScopes", btn); } });
  }

  window.PantryPilot = Object.assign(window.PantryPilot || {}, { bindAdminEvents });
})();
