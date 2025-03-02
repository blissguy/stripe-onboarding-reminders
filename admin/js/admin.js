/**
 * Admin JavaScript for Stripe Onboarding Reminders
 *
 * @package Stripe_Onboarding_Reminders
 */

// Define global variables before any other code executes
window.sorData = window.sorData || {};
window.sorSettings = window.sorSettings || {};

(function ($) {
  "use strict";

  // Create a settings object that works with both variable names
  var settings;

  if (
    typeof window.sorSettings !== "undefined" &&
    Object.keys(window.sorSettings).length > 0
  ) {
    settings = window.sorSettings;
    // Make sorData available for backward compatibility
    window.sorData = window.sorSettings;
  } else if (
    typeof window.sorData !== "undefined" &&
    Object.keys(window.sorData).length > 0
  ) {
    settings = window.sorData;
    // Make sorSettings available for forward compatibility
    window.sorSettings = window.sorData;
  } else {
    // Create fallback settings object to prevent errors
    settings = {
      ajaxUrl:
        typeof ajaxurl !== "undefined" ? ajaxurl : "/wp-admin/admin-ajax.php",
      nonce: "",
      sending: "Sending...",
      sent: "Sent!",
      send: "Send Reminder Emails",
      sendReminder: "Send Reminder",
      error: "Error occurred",
    };

    // Ensure both global objects are defined with the fallback
    window.sorData = settings;
    window.sorSettings = settings;
  }

  /**
   * Initialize admin functionality
   */
  function initAdmin() {
    // Get DOM elements
    const $testForm = $("#sor-test-form");
    const $testResponse = $("#sor-test-response");
    const $sendTestButton = $("#sor-send-test");
    const $includeAdminCopy = $("#include_admin_copy");
    const $adminEmailField = $(".admin-email-field").closest("tr");
    const $debugForm = $("#sor-debug-form");
    const $debugResponse = $("#sor-debug-response");
    const $sendDebugButton = $("#sor-send-debug");

    // Initialize toggle for admin email field visibility
    toggleAdminEmailField();

    // Test email form submission
    $sendTestButton.on("click", function (e) {
      e.preventDefault();
      sendTestEmails();
    });

    // Debug email form submission
    $sendDebugButton.on("click", function (e) {
      e.preventDefault();
      sendDebugEmail();
    });

    // Toggle admin email field visibility based on checkbox state
    $includeAdminCopy.on("change", toggleAdminEmailField);

    /**
     * Toggle admin email field visibility
     */
    function toggleAdminEmailField() {
      if ($includeAdminCopy.is(":checked")) {
        $adminEmailField.show();
      } else {
        $adminEmailField.hide();
      }
    }

    /**
     * Send test emails via AJAX
     */
    function sendTestEmails() {
      try {
        // Get selected statuses
        const statuses = [];
        $testForm
          .find('input[name="test_statuses[]"]:checked')
          .each(function () {
            statuses.push($(this).val());
          });

        if (statuses.length === 0) {
          showResponse(
            $testResponse,
            "error",
            "Please select at least one status."
          );
          return;
        }

        // Get bypass rate limit option
        const bypassRateLimit = $("#bypass_rate_limit").is(":checked");

        // Disable button and show loading state
        $sendTestButton.prop("disabled", true).text(settings.sending);

        // Send AJAX request
        $.ajax({
          url: settings.ajaxUrl,
          type: "POST",
          data: {
            action: "sor_send_test_emails",
            nonce: settings.nonce,
            statuses: statuses,
            bypass_rate_limit: bypassRateLimit.toString(),
          },
          success: function (response) {
            if (response.success) {
              showResponse($testResponse, "success", response.data.message);

              // Update last trigger timestamp if provided
              if (response.data.timestamp) {
                const $lastTrigger = $(".sor-last-trigger");
                if ($lastTrigger.length) {
                  $lastTrigger.find("p strong").html("Last manual trigger: ");
                  $lastTrigger.find("p").append(response.data.timestamp);
                } else {
                  $testResponse.after(
                    `<div class="sor-last-trigger"><p><strong>Last manual trigger:</strong> ${response.data.timestamp}</p></div>`
                  );
                }
              }
            } else {
              showResponse(
                $testResponse,
                "error",
                response.data.message || "Unknown error occurred."
              );
            }
          },
          error: function (xhr, status, error) {
            let errorMessage = "An error occurred. Please try again.";

            try {
              // Try to get more detailed error if available
              const jsonResponse = JSON.parse(xhr.responseText);
              if (
                jsonResponse &&
                jsonResponse.data &&
                jsonResponse.data.message
              ) {
                errorMessage = jsonResponse.data.message;
              }
            } catch (e) {
              // Silent catch
            }

            showResponse($testResponse, "error", errorMessage);
          },
          complete: function () {
            // Re-enable button and restore text
            $sendTestButton.prop("disabled", false).text(settings.send);
          },
        });
      } catch (e) {
        showResponse(
          $testResponse,
          "error",
          "An unexpected error occurred. Please try again."
        );
        $sendTestButton.prop("disabled", false).text(settings.send);
      }
    }

    /**
     * Send debug email via AJAX
     */
    function sendDebugEmail() {
      try {
        // Get selected status and email
        const status = $("#debug_status").val();
        const email = $("#debug_email").val();

        if (!status) {
          showResponse($debugResponse, "error", "Please select a status.");
          return;
        }

        if (!email || !isValidEmail(email)) {
          showResponse(
            $debugResponse,
            "error",
            "Please enter a valid email address."
          );
          return;
        }

        // Disable button and show loading state
        $sendDebugButton.prop("disabled", true).text(settings.sending);

        // Send AJAX request
        $.ajax({
          url: settings.ajaxUrl,
          type: "POST",
          data: {
            action: "sor_send_debug_email",
            nonce: settings.nonce,
            status: status,
            email: email,
          },
          success: function (response) {
            if (response.success) {
              showResponse($debugResponse, "success", response.data.message);
            } else {
              showResponse(
                $debugResponse,
                "error",
                response.data.message || "Unknown error occurred."
              );
            }
          },
          error: function (xhr, status, error) {
            let errorMessage = "An error occurred. Please try again.";

            try {
              // Try to get more detailed error if available
              const jsonResponse = JSON.parse(xhr.responseText);
              if (
                jsonResponse &&
                jsonResponse.data &&
                jsonResponse.data.message
              ) {
                errorMessage = jsonResponse.data.message;
              }
            } catch (e) {
              // Silent catch
            }

            showResponse($debugResponse, "error", errorMessage);
          },
          complete: function () {
            // Re-enable button and restore text
            $sendDebugButton.prop("disabled", false).text(settings.sendDebug);
          },
        });
      } catch (e) {
        showResponse(
          $debugResponse,
          "error",
          "An unexpected error occurred. Please try again."
        );
        $sendDebugButton.prop("disabled", false).text(settings.sendDebug);
      }
    }

    /**
     * Validate email address
     *
     * @param {string} email - Email address to validate
     * @returns {boolean} Whether email is valid
     */
    function isValidEmail(email) {
      const regex =
        /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
      return regex.test(email);
    }

    /**
     * Show response message
     *
     * @param {jQuery} $container - Container to show message in
     * @param {string} type - 'success', 'error', or 'info'
     * @param {string} message - Message to display
     */
    function showResponse($container, type, message) {
      const noticeClass = type === "info" ? "notice-info" : `notice-${type}`;

      $container
        .html(
          `<div class="notice ${noticeClass} inline"><p>${message}</p></div>`
        )
        .hide()
        .fadeIn();

      // Auto-hide success messages after 5 seconds
      if (type === "success") {
        setTimeout(function () {
          $container.fadeOut(function () {
            $(this).html("");
          });
        }, 5000);
      }
    }

    // Users table functionality
    const $usersTable = $("#sor-users-table-container");

    if ($usersTable.length > 0) {
      // Send manual reminder
      $usersTable.on("click", ".sor-send-reminder", function (e) {
        try {
          e.preventDefault();

          const $button = $(this);
          const userId = $button.data("user-id");
          const nonce = $button.data("nonce");

          if (!userId) {
            return;
          }

          // Disable button and show loading state
          $button.prop("disabled", true).text(settings.sending);

          // Send AJAX request
          $.ajax({
            url: settings.ajaxUrl,
            type: "POST",
            data: {
              action: "sor_send_manual_reminder",
              nonce: settings.nonce,
              user_id: userId,
            },
            success: function (response) {
              if (response.success) {
                // Show success message
                const $row = $button.closest("tr");
                const $lastReminderCell = $row.find("td.column-last_reminder");

                // Update the last reminder date
                if ($lastReminderCell.length) {
                  $lastReminderCell.text(response.data.timestamp);
                }

                // Show success message briefly
                $button.text(settings.sent);
                setTimeout(function () {
                  $button.prop("disabled", false).text(settings.sendReminder);
                }, 2000);
              } else {
                // Show error message
                alert(response.data.message);
                $button.prop("disabled", false).text(settings.sendReminder);
              }
            },
            error: function (xhr, status, error) {
              console.error("AJAX Error:", error);
              alert(settings.error);
              $button.prop("disabled", false).text(settings.sendReminder);
            },
          });
        } catch (e) {
          console.error("Error in send reminder:", e);
          alert("An unexpected error occurred. Please try again.");
          $button.prop("disabled", false).text(settings.sendReminder);
        }
      });

      // Refresh table button
      $usersTable.on("click", ".sor-refresh-table", function (e) {
        e.preventDefault();
        location.reload();
      });
    }
  }

  // Initialize when document is ready
  $(document).ready(initAdmin);
})(jQuery);
/* Force sync: Sun Mar  2 22:03:30 GMT 2025 */
