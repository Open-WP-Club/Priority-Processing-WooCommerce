/** One-click Priority Processing toggle for the classic cart and the Cart Block. */
(function () {
  'use strict';

  const config = window.wppCartButton || {};

  const formatPrice = function (amount, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
    } catch (e) {
      return String(amount);
    }
  };

  // Same AJAX action and payload as blocks-checkout.js, so session and fee behaviour stay identical.
  const sendToggle = function (enable) {
    return fetch(config.ajax_url || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'wpp_update_priority',
        nonce: config.nonce || '',
        priority_enabled: enable ? 'true' : 'false'
      })
    })
      .then(function (response) { return response.json(); })
      .then(function (data) { return !!(data && data.success); })
      .catch(function () { return false; });
  };

  const showError = function (button) {
    let note = button.parentNode && button.parentNode.querySelector('.wpp-cart-button__error');
    if (!note) {
      note = document.createElement('small');
      note.className = 'wpp-cart-button__error';
      note.setAttribute('role', 'alert');
      note.style.cssText = 'display:block;margin-top:6px;color:#dc3545;font-size:13px;';
      button.parentNode.appendChild(note);
    }
    note.textContent = config.error || '';
  };

  const refreshClassicCart = function () {
    const jq = window.jQuery;
    if (jq) {
      jq(document.body).trigger('wc_update_cart');
    } else if (window.location) {
      window.location.reload();
    }
  };

  const refreshBlockCart = function () {
    const dispatch = window.wp && window.wp.data && window.wp.data.dispatch('wc/store/cart');
    if (dispatch && typeof dispatch.invalidateResolutionForStore === 'function') {
      dispatch.invalidateResolutionForStore();
    }
  };

  // One delegated handler for server-rendered and block-built buttons; the refresh depends on where it lives.
  const onClick = function (event) {
    const target = event.target;
    const button = target && target.closest && target.closest('[data-wpp-cart-toggle]');
    if (!button || button.disabled) return;

    const inBlock = !!button.closest('.wpp-cart-button-block');
    const enable = button.getAttribute('data-wpp-active') !== '1';
    event.preventDefault();
    button.disabled = true;
    sendToggle(enable).then(function (ok) {
      if (ok) {
        // Block buttons stay disabled until the invalidated store re-renders them.
        if (inBlock) {
          refreshBlockCart();
        } else {
          refreshClassicCart();
        }
      } else {
        showError(button);
      }
      if (!ok || !inBlock) button.disabled = false;
    });
  };

  const buildBlockButton = function (state, currency) {
    const wrapper = document.createElement('div');
    wrapper.className = 'wpp-cart-button';
    wrapper.style.cssText = 'margin:0 0 15px;';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'wp-element-button wc-block-components-button wpp-cart-button__toggle';
    button.setAttribute('data-wpp-cart-toggle', '');
    button.setAttribute('data-wpp-active', state.is_active ? '1' : '0');
    button.setAttribute('aria-pressed', state.is_active ? 'true' : 'false');
    button.style.cssText = 'width:100%;';
    const price = !state.is_active && state.fee_amount > 0 ? ' (+' + formatPrice(state.fee_amount, currency) + ')' : '';
    button.textContent = (state.is_active ? '✓ ' + state.remove_label : '⚡ ' + state.add_label) + price;
    wrapper.appendChild(button);

    if (state.description) {
      const description = document.createElement('small');
      description.className = 'wpp-cart-button__description';
      description.style.cssText = 'display:block;margin-top:6px;color:#6c757d;font-size:13px;line-height:1.4;';
      description.textContent = state.description;
      wrapper.appendChild(description);
    }
    return wrapper;
  };

  // Cart Block: hydrate the container from the wpp-cart-button Store API extension.
  const startBlock = function (containers) {
    const data = window.wp && window.wp.data;
    if (!data) return;

    let previousKey;

    const update = function () {
      const store = data.select('wc/store/cart');
      if (!store || typeof store.getCartData !== 'function') return;
      const cart = store.getCartData();
      const state = cart && cart.extensions && cart.extensions['wpp-cart-button'];
      // Preserve server-rendered content until the Store API response arrives.
      if (!state || typeof state.eligible !== 'boolean') return;

      const empty = cart.items && cart.items.length === 0;
      const visible = state.eligible && !empty;
      const currency = cart.totals && cart.totals.currency_code;
      const key = visible ? JSON.stringify([state, currency]) : '';
      if (key === previousKey) return;
      previousKey = key;

      containers.forEach(function (container) {
        container.replaceChildren();
        if (visible) container.appendChild(buildBlockButton(state, currency));
      });
    };
    data.subscribe(update, 'wc/store/cart');
    update();
  };

  const start = function () {
    document.addEventListener('click', onClick);
    const containers = document.querySelectorAll('.wpp-cart-button-block');
    if (containers.length) startBlock(Array.from(containers));
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
