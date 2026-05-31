/**
 * Priority Processing - Frontend Block Checkout Handler
 * Handles real-time updates for priority processing checkbox (classic checkout)
 */
(function($) {
  'use strict';

  $(document).ready(function() {
    initPriorityProcessing();
  });

  function initPriorityProcessing() {
    $(document).on('change', '#priority_processing, input[name="priority_processing"]', function() {
      handlePriorityChange($(this).is(':checked'));
    });

    $(document.body).on('updated_checkout', function() {
      bindCheckboxEvents();
    });
  }

  function bindCheckboxEvents() {
    $('input[name="priority_processing"]').off('change.wpp').on('change.wpp', function() {
      handlePriorityChange($(this).is(':checked'));
    });
  }

  function handlePriorityChange(isChecked) {
    fetch(wppData.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'wpp_update_priority',
        nonce: wppData.nonce,
        priority_enabled: isChecked ? 'true' : 'false'
      }).toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(response) {
      if (response.success) {
        if (response.data && response.data.fragments) {
          $.each(response.data.fragments, function(key, value) {
            var $target = $(key);
            if ($target.length) {
              $target.replaceWith(value);
            }
          });
        }
        $(document.body).trigger('updated_checkout', [response.data]);
      } else {
        var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
        showErrorMessage(errorMsg);
      }
    })
    .catch(function() {
      showErrorMessage('An error occurred. Please try again.');
    });
  }

  function showErrorMessage(message) {
    $('.wpp-error-message').remove();
    var $error = $('<div class="woocommerce-error wpp-error-message">' + message + '</div>');
    $('.woocommerce-checkout').prepend($error);
    $('html, body').animate({ scrollTop: $error.offset().top - 100 }, 500);
    setTimeout(function() {
      $error.fadeOut(400, function() { $(this).remove(); });
    }, 5000);
  }

})(jQuery);
