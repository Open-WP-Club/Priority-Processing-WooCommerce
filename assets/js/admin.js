/**
 * WooCommerce Priority Processing — settings tab
 * Statistics refresh
 */
jQuery(document).ready(function($) {
  'use strict';

  $('#wpp-refresh-stats').on('click', function(e) {
    e.preventDefault();

    var $button      = $(this);
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
