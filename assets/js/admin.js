/**
 * WooCommerce Priority Processing — Admin Dashboard
 * Live preview + statistics refresh
 */
jQuery(document).ready(function($) {
  'use strict';

  // ── Live preview ────────────────────────────────────────────────────────────

  function updatePreview() {
    var sectionTitle  = $('#wpp_section_title').val() || 'Express Options';
    var checkboxLabel = $('#wpp_checkbox_label').val() || 'Priority processing + Express shipping';
    var description   = $('#wpp_description').val() || '';
    var feeAmount     = $('#wpp_fee_amount').val() || '0.00';
    var enabled       = $('#wpp_enabled').is(':checked');

    $('#preview-section-title').text(sectionTitle);
    $('#preview-checkbox-label').text(checkboxLabel);
    $('#preview-fee-amount').text(feeAmount);

    if (description) {
      $('#preview-description').text(description).show();
    } else {
      $('#preview-description').hide();
    }

    var $preview  = $('#checkout-preview');
    var $checkbox = $preview.find('input[type="checkbox"]');
    $preview.css('opacity', enabled ? '1' : '0.6');
    $checkbox.css('opacity', enabled ? '1' : '0.5');

    var $status = $('.wpp-status');
    if (enabled) {
      $status.removeClass('wpp-status-disabled').addClass('wpp-status-enabled').text(wpp_admin_ajax.text_active);
    } else {
      $status.removeClass('wpp-status-enabled').addClass('wpp-status-disabled').text(wpp_admin_ajax.text_inactive);
    }

    $('.wpp-stat-value').first().text(feeAmount);
    $('.wpp-stat-value').eq(1).text(enabled ? '✅' : '❌');
  }

  function updatePermissionSummary() {
    var selectedRoles = [ wpp_admin_ajax.text_shop_managers ];
    var allowGuests   = $('#wpp_allow_guests').is(':checked');

    $('input[name="wpp_allowed_user_roles[]"]:checked').each(function() {
      selectedRoles.push($(this).parent().find('strong').text());
    });

    if (allowGuests) {
      selectedRoles.push(wpp_admin_ajax.text_guests);
    }

    var $summary = $('#wpp-permission-summary');
    if (selectedRoles.length > 1) {
      var listItems = selectedRoles.map(function(r) { return '<li>' + r + '</li>'; }).join('');
      $summary.html(
        '<div style="background:#e7f3ff;border:1px solid #b3d9ff;padding:10px;border-radius:4px;">' +
        '<strong>' + wpp_admin_ajax.text_available_to + '</strong><ul style="margin:5px 0 0 20px;">' + listItems + '</ul></div>'
      );
    } else {
      $summary.html(
        '<div style="background:#fff2e5;border:1px solid #ffcc99;padding:10px;border-radius:4px;">' +
        '<strong>' + wpp_admin_ajax.text_only_managers + '</strong></div>'
      );
    }

    $('.wpp-guest-status').text(allowGuests ? wpp_admin_ajax.text_allowed : wpp_admin_ajax.text_denied);

    $('#preview-permission-summary').html(
      selectedRoles.length > 1
        ? '<strong>' + wpp_admin_ajax.text_available + '</strong> ' + selectedRoles.join(', ')
        : '<strong>' + wpp_admin_ajax.text_no_access + '</strong>'
    );
  }

  $('input[name="wpp_allowed_user_roles[]"], #wpp_allow_guests').on('change', function() {
    updatePermissionSummary();
    updatePreview();
  });

  $('#wpp_section_title, #wpp_checkbox_label, #wpp_description, #wpp_fee_amount').on('input', updatePreview);
  $('#wpp_enabled').on('change', updatePreview);

  setTimeout(function() {
    updatePreview();
    updatePermissionSummary();
  }, 100);

  // ── Statistics refresh ──────────────────────────────────────────────────────

  $('#wpp-refresh-stats').on('click', function(e) {
    e.preventDefault();

    var $button     = $(this);
    var originalHtml = $button.html();

    $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + wpp_admin_ajax.refreshing_text);
    $('#wpp-statistics-container').addClass('wpp-loading');

    fetch(wpp_admin_ajax.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'wpp_refresh_stats',
        nonce:  wpp_admin_ajax.nonce
      }).toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(response) {
      if (response.success && response.data.formatted) {
        var f = response.data.formatted;
        $('#stat-total-orders').text(f.total_priority_orders);
        $('#stat-total-revenue').html(f.total_priority_revenue);
        $('#stat-percentage').text(f.priority_percentage);
        $('#stat-avg-fee').html(f.average_priority_fee);
        $('#stat-today').text(f.today_priority_orders);
        $('#stat-this-week').text(f.this_week_priority_orders);
        $('#stat-this-month').text(f.this_month_priority_orders);

        var now = new Date();
        $('#stat-last-updated').text(
          now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0')
        );

        $('#wpp-statistics-container').addClass('wpp-updated');
        setTimeout(function() { $('#wpp-statistics-container').removeClass('wpp-updated'); }, 2000);
      } else {
        alert(wpp_admin_ajax.error_refresh);
      }
    })
    .catch(function() {
      alert(wpp_admin_ajax.error_refresh);
    })
    .finally(function() {
      $button.prop('disabled', false).html(originalHtml);
      $('#wpp-statistics-container').removeClass('wpp-loading');
    });
  });
});
