/**
 * WooCommerce Blocks Checkout Integration
 * Adds priority processing checkbox to WooCommerce Checkout Block
 */
(function() {
  'use strict';

  // Wait for WooCommerce Blocks to be ready (max 20 attempts = ~10 s)
  const initializeWhenReady = (attempts = 0) => {
    const { registerCheckoutBlock } = window.wc?.blocksCheckout || {};
    const { CheckboxControl } = window.wp?.components || {};
    const { createElement, useState, useEffect } = window.wp?.element || {};
    const { __ } = window.wp?.i18n || {};

    if (!registerCheckoutBlock || !CheckboxControl || !createElement) {
      if (attempts >= 20) {
        console.warn('WPP: Blocks dependencies did not load after 10 seconds — aborting.');
        return;
      }
      setTimeout(() => initializeWhenReady(attempts + 1), 500);
      return;
    }

    /**
     * Priority Processing Block Component
     */
    const PriorityProcessingBlock = ({ cart, extensions }) => {
      const priorityData = extensions?.['wpp-priority'] || {};
      const [isChecked, setIsChecked] = useState(priorityData.is_active || false);
      const [isProcessing, setIsProcessing] = useState(false);

      // Update checkbox state when cart data changes
      useEffect(() => {
        if (priorityData.is_active !== undefined) {
          setIsChecked(priorityData.is_active);
        }
      }, [priorityData.is_active]);

      const [countdown, setCountdown] = useState('');
      const cutoffTs = priorityData.cutoff_ts || 0;

      // Live countdown for the same-day cutoff notice
      useEffect(() => {
        if (!cutoffTs) {
          setCountdown('');
          return undefined;
        }
        const tick = () => {
          const remaining = Math.floor((cutoffTs - Date.now()) / 1000);
          if (remaining <= 0) {
            setCountdown('');
            return false;
          }
          const h = Math.floor(remaining / 3600);
          const m = Math.floor((remaining % 3600) / 60);
          const sec = remaining % 60;
          setCountdown('(' + (h > 0 ? h + 'h ' : '') + m + 'm ' + sec + 's)');
          return true;
        };
        if (!tick()) return undefined;
        const id = setInterval(() => { if (!tick()) clearInterval(id); }, 1000);
        return () => clearInterval(id);
      }, [cutoffTs]);

      // Don't render if not enabled or user doesn't have access
      if (!priorityData.enabled) {
        if (priorityData.limit_reached && priorityData.limit_message) {
          return createElement('div', { className: 'wpp-limit-notice' }, priorityData.limit_message);
        }
        return null;
      }

      const feeAmount = priorityData.fee_amount || 5.00;
      const sectionTitle = priorityData.section_title || 'Express Options';
      const checkboxLabel = priorityData.checkbox_label || 'Priority processing + Express shipping';
      const description = priorityData.description || 'Your order will be processed with priority and shipped via express delivery';

      /**
       * Handle checkbox change using AJAX (more reliable than Store API for custom data)
       */
      const handleChange = (checked) => {
        setIsChecked(checked);
        setIsProcessing(true);

        // Use the existing AJAX endpoint
        fetch(wppBlocksData?.ajax_url || '/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'wpp_update_priority',
            nonce: wppBlocksData?.nonce || '',
            priority_enabled: checked ? 'true' : 'false'
          })
        })
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            console.error('WPP: Failed to update priority processing', data);
            setIsChecked(!checked); // Revert on error

            // Show user-facing error notification
            if (window.wp?.data?.dispatch) {
              const noticesStore = window.wp.data.dispatch('core/notices');
              if (noticesStore && typeof noticesStore.createErrorNotice === 'function') {
                noticesStore.createErrorNotice(
                  'Unable to update priority processing. Please try again.',
                  { type: 'snackbar', isDismissible: true }
                );
              }
            }
          } else {
            // Trigger a cart update to refresh totals
            if (window.wp?.data?.dispatch) {
              const cartStore = window.wp.data.dispatch('wc/store/cart');
              if (cartStore && typeof cartStore.invalidateResolutionForStore === 'function') {
                cartStore.invalidateResolutionForStore();
              }
            }
            // Also trigger jQuery event for compatibility
            if (window.jQuery) {
              window.jQuery(document.body).trigger('update_checkout');
            }
          }
        })
        .catch(error => {
          console.error('WPP: Error updating priority processing:', error);
          setIsChecked(!checked); // Revert on error

          // Show user-facing error notification
          if (window.wp?.data?.dispatch) {
            const noticesStore = window.wp.data.dispatch('core/notices');
            if (noticesStore && typeof noticesStore.createErrorNotice === 'function') {
              noticesStore.createErrorNotice(
                'Unable to update priority processing. Please try again.',
                { type: 'snackbar', isDismissible: true }
              );
            }
          }
        })
        .finally(() => {
          setIsProcessing(false);
        });
      };

      // Format price for display
      const formattedPrice = new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: cart?.cartTotals?.currency_code || 'USD'
      }).format(feeAmount);

      return createElement(
        'div',
        {
          className: 'wpp-priority-section wc-block-components-checkout-step',
          style: {
            background: '#f8f9fa',
            border: '2px solid #dee2e6',
            borderRadius: '6px',
            padding: '20px',
            margin: '20px 0'
          }
        },
        createElement(
          'h3',
          {
            style: {
              margin: '0 0 15px 0',
              color: '#495057',
              fontSize: '16px',
              fontWeight: '600'
            }
          },
          '⚡ ' + sectionTitle
        ),
        createElement(
          'div',
          { className: 'wpp-priority-field-wrapper' },
          createElement(CheckboxControl, {
            label: createElement(
              'span',
              { style: { display: 'flex', flexDirection: 'column', gap: '5px' } },
              createElement(
                'strong',
                { style: { color: '#28a745', fontWeight: '600', fontSize: '14px' } },
                checkboxLabel,
                createElement(
                  'span',
                  { style: { color: '#dc3545', fontWeight: '600', marginLeft: '5px' } },
                  '(+' + formattedPrice + ' added to shipping)'
                )
              ),
              description && createElement(
                'small',
                { style: { color: '#6c757d', fontSize: '13px', lineHeight: '1.4' } },
                description
              )
            ),
            checked: isChecked,
            onChange: handleChange,
            disabled: isProcessing
          })
        ),
        priorityData.cutoff_message && createElement(
          'div',
          {
            className: 'wpp-cutoff-notice',
            style: {
              marginTop: '10px', fontSize: '12px', color: '#856404', background: '#fff3cd',
              border: '1px solid #ffd966', borderRadius: '4px', padding: '5px 10px', display: 'inline-block'
            }
          },
          '⏱ ' + priorityData.cutoff_message + (countdown ? ' ' + countdown : '')
        )
      );
    };

    /**
     * Register the block
     */
    try {
      registerCheckoutBlock({
        metadata: {
          name: 'wpp-priority-processing',
          parent: ['woocommerce/checkout-fields-block']
        },
        component: PriorityProcessingBlock
      });
      console.log('WPP: Blocks checkout integration registered successfully');
    } catch (error) {
      console.error('WPP: Error registering blocks checkout integration:', error);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initializeWhenReady(0));
  } else {
    initializeWhenReady(0);
  }
})();
