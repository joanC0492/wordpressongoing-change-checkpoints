/**
 * Change Checkpoints Admin JavaScript
 * Vanilla JavaScript for modal interactions, confirmations, and AJAX calls
 */

(function () {
  "use strict";

  // Wait for DOM to be ready
  document.addEventListener("DOMContentLoaded", function () {
    initializeCheckpoints();
  });

  /**
   * Initialize all checkpoint functionality
   */
  function initializeCheckpoints() {
    initializeModals();
    initializeBulkActions();
    initializeCheckboxes();
    initializeCreateForm();
  }

  /**
   * Initialize modal functionality
   */
  function initializeModals() {
    // Modal elements
    const modal = document.getElementById("ccp-create-modal");
    const overlay = document.getElementById("ccp-modal-overlay");
    const closeButton = document.querySelector(".ccp-modal-close");

    if (!modal || !overlay) return;

    // Close modal when clicking close button or overlay
    if (closeButton) {
      closeButton.addEventListener("click", hideCreateModal);
    }

    overlay.addEventListener("click", hideCreateModal);

    // Close modal with Escape key
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal.style.display === "block") {
        hideCreateModal();
      }
    });
  }

  /**
   * Initialize bulk actions functionality
   */
  function initializeBulkActions() {
    // Select all checkboxes functionality
    const selectAllTop = document.getElementById("cb-select-all-1");
    const selectAllBottom = document.getElementById("cb-select-all-2");
    const checkboxes = document.querySelectorAll('input[name="checkpoint[]"]');

    if (selectAllTop) {
      selectAllTop.addEventListener("change", function () {
        toggleAllCheckboxes(this.checked);
        if (selectAllBottom) selectAllBottom.checked = this.checked;
      });
    }

    if (selectAllBottom) {
      selectAllBottom.addEventListener("change", function () {
        toggleAllCheckboxes(this.checked);
        if (selectAllTop) selectAllTop.checked = this.checked;
      });
    }

    // Update select all state when individual checkboxes change
    checkboxes.forEach(function (checkbox) {
      checkbox.addEventListener("change", updateSelectAllState);
    });
  }

  /**
   * Initialize individual checkbox behavior
   */
  function initializeCheckboxes() {
    updateSelectAllState();
  }

  /**
   * Initialize create checkpoint form
   */
  function initializeCreateForm() {
    const form = document.getElementById("ccp-create-form");
    if (!form) return;

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      handleCreateCheckpoint();
    });
  }

  /**
   * Show create checkpoint modal
   */
  function showCreateModal() {
    const modal = document.getElementById("ccp-create-modal");
    const overlay = document.getElementById("ccp-modal-overlay");

    if (modal && overlay) {
      modal.style.display = "block";
      overlay.style.display = "block";

      // Focus on first input
      const firstInput = modal.querySelector("input, textarea");
      if (firstInput) {
        setTimeout(function () {
          firstInput.focus();
        }, 100);
      }
    }
  }

  /**
   * Hide create checkpoint modal
   */
  function hideCreateModal() {
    const modal = document.getElementById("ccp-create-modal");
    const overlay = document.getElementById("ccp-modal-overlay");
    const form = document.getElementById("ccp-create-form");

    if (modal && overlay) {
      modal.style.display = "none";
      overlay.style.display = "none";
    }

    // Reset form
    if (form) {
      form.reset();
    }
  }

  /**
   * Toggle all checkboxes
   */
  function toggleAllCheckboxes(checked) {
    const checkboxes = document.querySelectorAll('input[name="checkpoint[]"]');
    checkboxes.forEach(function (checkbox) {
      checkbox.checked = checked;
    });
  }

  /**
   * Update select all checkbox state
   */
  function updateSelectAllState() {
    const selectAllTop = document.getElementById("cb-select-all-1");
    const selectAllBottom = document.getElementById("cb-select-all-2");
    const checkboxes = document.querySelectorAll('input[name="checkpoint[]"]');

    const checkedCount = Array.from(checkboxes).filter(
      (cb) => cb.checked
    ).length;
    const allChecked =
      checkedCount === checkboxes.length && checkboxes.length > 0;

    if (selectAllTop) selectAllTop.checked = allChecked;
    if (selectAllBottom) selectAllBottom.checked = allChecked;
  }

  /**
   * Handle create checkpoint via AJAX
   */
  function handleCreateCheckpoint() {
    const form = document.getElementById("ccp-create-form");
    const submitButton = form.querySelector('button[type="submit"]');
    const title = form.querySelector("#checkpoint_title").value;
    const note = form.querySelector("#checkpoint_note").value;

    // Disable form
    setFormLoading(form, true);
    submitButton.textContent = ccpAdmin.strings.creating;

    // Prepare data
    const data = new FormData();
    data.append("action", "ccp_create_checkpoint");
    data.append("nonce", ccpAdmin.nonce);
    data.append("title", title);
    data.append("note", note);

    // Make AJAX request
    fetch(ccpAdmin.ajaxUrl, {
      method: "POST",
      body: data,
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          showAdminNotice(result.data.message, "success");
          hideCreateModal();
          // Reload page to show new checkpoint
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showAdminNotice(
            result.data.message || ccpAdmin.strings.error,
            "error"
          );
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showAdminNotice(ccpAdmin.strings.error, "error");
      })
      .finally(() => {
        setFormLoading(form, false);
        submitButton.textContent = ccpAdmin.strings.create || "Create";
      });
  }

  /**
   * Close checkpoint
   */
  function closeCheckpoint(checkpointId, redirectUrl) {
    if (
      !confirm(
        ccpAdmin.strings.confirmClose ||
          "Are you sure you want to close this checkpoint?"
      )
    ) {
      return;
    }

    // Prepare data
    const data = new FormData();
    data.append("action", "ccp_close_checkpoint");
    data.append("nonce", ccpAdmin.nonce);
    data.append("checkpoint_id", checkpointId);

    // Show loading
    showAdminNotice(ccpAdmin.strings.closing, "info");

    // Make AJAX request
    fetch(ccpAdmin.ajaxUrl, {
      method: "POST",
      body: data,
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          showAdminNotice(result.data.message, "success");
          // Redirect or reload
          if (redirectUrl) {
            setTimeout(() => (window.location.href = redirectUrl), 1000);
          } else {
            setTimeout(() => window.location.reload(), 1000);
          }
        } else {
          showAdminNotice(
            result.data.message || ccpAdmin.strings.error,
            "error"
          );
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showAdminNotice(ccpAdmin.strings.error, "error");
      });
  }

  /**
   * Delete checkpoint
   */
  function deleteCheckpoint(checkpointId, redirectUrl) {
    if (!confirm(ccpAdmin.strings.confirmDelete)) {
      return;
    }

    // Prepare data
    const data = new FormData();
    data.append("action", "ccp_delete_checkpoint");
    data.append("nonce", ccpAdmin.nonce);
    data.append("checkpoint_id", checkpointId);

    // Show loading
    showAdminNotice(ccpAdmin.strings.deleting, "info");

    // Make AJAX request
    fetch(ccpAdmin.ajaxUrl, {
      method: "POST",
      body: data,
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          showAdminNotice(result.data.message, "success");
          // Redirect or reload
          if (redirectUrl) {
            setTimeout(() => (window.location.href = redirectUrl), 1000);
          } else {
            setTimeout(() => window.location.reload(), 1000);
          }
        } else {
          showAdminNotice(
            result.data.message || ccpAdmin.strings.error,
            "error"
          );
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showAdminNotice(ccpAdmin.strings.error, "error");
      });
  }

  /**
   * Confirm bulk action
   */
  function confirmBulkAction() {
    const form = document.getElementById("ccp-checkpoints-form");
    if (!form) return false;

    const action = getSelectedBulkAction(form);
    const selectedCheckpoints = getSelectedCheckpoints();

    if (!action || action === "-1") {
      alert("Please select an action.");
      return false;
    }

    if (action === "delete_all") {
      return confirm(ccpAdmin.strings.confirmDeleteAll);
    } else if (action === "delete") {
      if (selectedCheckpoints.length === 0) {
        alert("Please select checkpoints to delete.");
        return false;
      }
      return confirm(ccpAdmin.strings.confirmBulkDelete);
    }

    return true;
  }

  /**
   * Get selected bulk action
   */
  function getSelectedBulkAction(form) {
    const actionSelect = form.querySelector('select[name="action"]');
    const action2Select = form.querySelector('select[name="action2"]');

    const action = actionSelect ? actionSelect.value : "";
    const action2 = action2Select ? action2Select.value : "";

    return action !== "-1" ? action : action2;
  }

  /**
   * Get selected checkpoints
   */
  function getSelectedCheckpoints() {
    const checkboxes = document.querySelectorAll(
      'input[name="checkpoint[]"]:checked'
    );
    return Array.from(checkboxes).map((cb) => cb.value);
  }

  /**
   * Set form loading state
   */
  function setFormLoading(form, loading) {
    const inputs = form.querySelectorAll("input, textarea, button, select");

    inputs.forEach((input) => {
      input.disabled = loading;
    });

    if (loading) {
      form.classList.add("ccp-loading");
    } else {
      form.classList.remove("ccp-loading");
    }
  }

  /**
   * Show admin notice
   */
  function showAdminNotice(message, type) {
    // Remove existing notices
    const existingNotices = document.querySelectorAll(".ccp-notice");
    existingNotices.forEach((notice) => notice.remove());

    // Create new notice
    const notice = document.createElement("div");
    notice.className = `notice ccp-notice notice-${type} is-dismissible`;
    notice.innerHTML = `<p>${escapeHtml(message)}</p>`;

    // Insert after h1
    const h1 = document.querySelector(".wrap h1");
    if (h1) {
      h1.parentNode.insertBefore(notice, h1.nextSibling);
    } else {
      // Fallback: insert at top of wrap
      const wrap = document.querySelector(".wrap");
      if (wrap) {
        wrap.insertBefore(notice, wrap.firstChild);
      }
    }

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
      if (notice.parentNode) {
        notice.remove();
      }
    }, 5000);

    // Make dismissible
    const dismissButton = document.createElement("button");
    dismissButton.type = "button";
    dismissButton.className = "notice-dismiss";
    dismissButton.innerHTML =
      '<span class="screen-reader-text">Dismiss this notice.</span>';
    dismissButton.addEventListener("click", () => notice.remove());
    notice.appendChild(dismissButton);
  }

  /**
   * Escape HTML to prevent XSS
   */
  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, (m) => map[m]);
  }

  // Global functions for inline event handlers
  window.ccpShowCreateModal = showCreateModal;
  window.ccpHideCreateModal = hideCreateModal;
  window.ccpCloseCheckpoint = closeCheckpoint;
  window.ccpDeleteCheckpoint = deleteCheckpoint;
  window.ccpConfirmBulkAction = confirmBulkAction;
})();