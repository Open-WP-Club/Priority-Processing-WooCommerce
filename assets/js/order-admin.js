/**
 * WooCommerce Priority Processing — Order Admin
 * Updates the priority meta box in-place; no page reload required.
 */
(function($) {
  'use strict';

  function bindPriorityButtons() {
    $('#wpp-add-priority, #wpp-remove-priority').off('click.wpp').on('click.wpp', handlePriorityToggle);
  }

  function handlePriorityToggle(e) {
    e.preventDefault();

    var $button  = $(this);
    var orderId  = $button.data('order-id');
    var action   = $button.attr('id') === 'wpp-add-priority' ? 'add' : 'remove';

    $('#wpp-loading').show();
    $button.prop('disabled', true);

    fetch(wpp_order_admin.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action:          'wpp_toggle_order_priority',
        order_id:        orderId,
        priority_action: action,
        nonce:           $('#wpp_order_priority_nonce').val()
      }).toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(response) {
      if (response.success) {
        // Replace meta box contents with fresh server-rendered HTML.
        $('#wpp-order-priority-container').html(response.data.meta_box_html);
        bindPriorityButtons();

        var $notice = $('<div class="notice notice-success" style="padding:8px 12px;margin:10px 0;border-radius:3px;"><p>' + response.data.message + '</p></div>');
        $('#wpp-order-priority-container').before($notice);
        setTimeout(function() {
          $notice.fadeOut(400, function() { $(this).remove(); });
        }, 4000);
      } else {
        $('#wpp-loading').hide();
        $button.prop('disabled', false);
        alert(wpp_order_admin.error_title + ' ' + (response.data || 'Unknown error'));
      }
    })
    .catch(function() {
      $('#wpp-loading').hide();
      $button.prop('disabled', false);
      alert(wpp_order_admin.connection_error);
    });
  }

  $(document).ready(function() {
    bindPriorityButtons();
  });

})(jQuery);
