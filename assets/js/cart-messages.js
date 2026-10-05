/** Keep Cart Block messages in sync with server-side permissions and thresholds. */
(function () {
  'use strict';

  const start = function () {
    const containers = document.querySelectorAll('.wpp-cart-block-message');
    const data = window.wp && window.wp.data;
    if (!containers.length || !data) return;

    let previousMessage;
    const update = function () {
      const store = data.select('wc/store/cart');
      if (!store || typeof store.getCartData !== 'function') return;
      const cart = store.getCartData();
      const extension = cart && cart.extensions && cart.extensions['wpp-priority'];
      // Preserve server-rendered content until the Store API response arrives.
      if (!extension || typeof extension.cart_message !== 'string') return;
      const message = cart.items && cart.items.length === 0 ? '' : extension.cart_message;
      if (message === previousMessage) return;
      previousMessage = message;

      containers.forEach(function (container) {
        container.replaceChildren();
        if (!message) return;
        const notice = document.createElement('div');
        notice.className = 'wpp-motivation-message';
        notice.style.cssText = 'background:#fff8e1;border-left:4px solid #ffb300;border-radius:4px;padding:12px 16px;margin:15px 0;font-size:14px;line-height:1.5;color:#5f4500;';
        notice.textContent = message;
        container.appendChild(notice);
      });
    };
    data.subscribe(update, 'wc/store/cart');
    update();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
