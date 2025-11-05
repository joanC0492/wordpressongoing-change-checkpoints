/**
 * Change Tracker Admin JavaScript
 * Vanilla JavaScript for confirmations and AJAX calls
 */

(function () {
  "use strict";

  // Wait for DOM to be ready
  document.addEventListener("DOMContentLoaded", function () {
    initializeChangeTracker();
  });

  /**
   * Initialize all functionality
   */
  function initializeChangeTracker() {
    // No initialization needed for simple interface
  }

  /**
   * Clear all changes
   */
  function clearAll() {
    if (!confirm(ccpAdmin.strings.confirmClearAll)) {
      return;
    }

    // Show loading
    showAdminNotice(ccpAdmin.strings.clearing, "info");

    // Prepare data
    const data = new FormData();
    data.append("action", "ccp_clear_all");
    data.append("nonce", ccpAdmin.nonce);

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
          // Reload page to show empty state
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
      });
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
  window.ccpClearAll = clearAll;
})();