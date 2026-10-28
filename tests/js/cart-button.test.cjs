const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function el(tag) {
  return {
    tag,
    children: [],
    attrs: {},
    style: {},
    textContent: '',
    disabled: false,
    parentNode: null,
    replaceChildren() { this.children = []; },
    appendChild(node) { node.parentNode = this; this.children.push(node); },
    setAttribute(name, value) { this.attrs[name] = value; },
    getAttribute(name) { return this.attrs[name] ?? null; },
    querySelector() { return null; },
    closest(selector) { return this.matches && this.matches.includes(selector) ? this : null; }
  };
}

function boot({ block = false, jq = true, ok = true, items = [{}] } = {}) {
  let cart = {};
  let update;
  let click;
  const container = el('div');
  const calls = { fetch: [], invalidate: 0, wcUpdate: 0, reload: 0 };
  const document = {
    readyState: 'complete',
    querySelectorAll: () => (block ? [container] : []),
    createElement: el,
    addEventListener(name, callback) { assert.equal(name, 'click'); click = callback; }
  };
  const window = {
    wppCartButton: { ajax_url: '/ajax', nonce: 'n1', error: 'Failed' },
    location: { reload() { calls.reload++; } },
    jQuery: jq ? () => ({ trigger(name) { if (name === 'wc_update_cart') calls.wcUpdate++; } }) : undefined,
    wp: { data: {
      select() { return { getCartData: () => cart }; },
      dispatch() { return { invalidateResolutionForStore() { calls.invalidate++; } }; },
      subscribe(callback) { update = callback; }
    } }
  };
  vm.runInNewContext(readFileSync('assets/js/cart-button.js', 'utf8'), {
    document, window, Intl, URLSearchParams, Array, JSON, String,
    fetch(url, init) {
      calls.fetch.push({ url, body: init.body.toString() });
      return Promise.resolve({ json: () => Promise.resolve({ success: ok }) });
    }
  });
  const set = (state, currency = 'USD') => {
    cart = { items, totals: { currency_code: currency }, extensions: { 'wpp-cart-button': state } };
    update();
  };
  const press = (button, inBlock) => {
    button.matches = ['[data-wpp-cart-toggle]'];
    button.closest = (selector) => {
      if (selector === '[data-wpp-cart-toggle]') return button;
      if (selector === '.wpp-cart-button-block') return inBlock ? container : null;
      return null;
    };
    let prevented = false;
    click({ target: button, preventDefault() { prevented = true; } });
    return () => prevented;
  };
  return { container, calls, set, press };
}

const state = (extra = {}) => ({
  eligible: true, is_active: false, fee_amount: 5, add_label: 'Add Priority Processing',
  remove_label: 'Remove Priority Processing', description: 'Fast', ...extra
});
const toggleOf = (env) => env.container.children[0].children[0];
const tick = () => new Promise((resolve) => setImmediate(resolve));

test('keeps server-rendered content until Store API data arrives', () => {
  const env = boot({ block: true });
  env.container.children = ['server'];
  env.set(undefined);
  assert.deepEqual(env.container.children, ['server']);
});

test('renders add state with formatted fee and description', () => {
  const env = boot({ block: true });
  env.set(state());
  const button = toggleOf(env);
  assert.equal(button.textContent, '⚡ Add Priority Processing (+$5.00)');
  assert.equal(button.getAttribute('data-wpp-active'), '0');
  assert.equal(env.container.children[0].children[1].textContent, 'Fast');
});

test('renders remove state without a price when active', () => {
  const env = boot({ block: true });
  env.set(state({ is_active: true }));
  const button = toggleOf(env);
  assert.equal(button.textContent, '✓ Remove Priority Processing');
  assert.equal(button.getAttribute('aria-pressed'), 'true');
});

test('hides when ineligible or the cart is empty, and treats text as plain text', () => {
  const env = boot({ block: true });
  env.set(state({ description: '<img src=x onerror=alert(1)>' }));
  assert.equal(env.container.children[0].children[1].textContent, '<img src=x onerror=alert(1)>');
  env.set(state({ eligible: false }));
  assert.equal(env.container.children.length, 0);

  const empty = boot({ block: true, items: [] });
  empty.set(state());
  assert.equal(empty.container.children.length, 0);
});

test('does not rebuild an unchanged button', () => {
  const env = boot({ block: true });
  env.set(state());
  const first = env.container.children[0];
  env.set(state());
  assert.equal(env.container.children[0], first);
});

test('block click posts the existing AJAX action and invalidates the cart store', async () => {
  const env = boot({ block: true });
  env.set(state());
  const button = toggleOf(env);
  const prevented = env.press(button, true);
  assert.equal(prevented(), true);
  assert.equal(button.disabled, true);
  await tick();
  const body = new URLSearchParams(env.calls.fetch[0].body);
  assert.equal(env.calls.fetch[0].url, '/ajax');
  assert.equal(body.get('action'), 'wpp_update_priority');
  assert.equal(body.get('nonce'), 'n1');
  assert.equal(body.get('priority_enabled'), 'true');
  assert.equal(env.calls.invalidate, 1);
  assert.equal(env.calls.wcUpdate, 0);
});

test('active button asks the server to disable', async () => {
  const env = boot({ block: true });
  env.set(state({ is_active: true }));
  env.press(toggleOf(env), true);
  await tick();
  assert.equal(new URLSearchParams(env.calls.fetch[0].body).get('priority_enabled'), 'false');
});

test('classic click refreshes the cart with wc_update_cart, or reloads without jQuery', async () => {
  const classic = el('button');
  classic.setAttribute('data-wpp-active', '0');
  const env = boot();
  env.press(classic, false);
  await tick();
  assert.equal(env.calls.wcUpdate, 1);
  assert.equal(classic.disabled, false);

  const plain = boot({ jq: false });
  plain.press(classic, false);
  await tick();
  assert.equal(plain.calls.reload, 1);
});

test('failed request re-enables the button and shows an error without refreshing', async () => {
  const env = boot({ block: true, ok: false });
  env.set(state());
  const wrapper = env.container.children[0];
  const button = wrapper.children[0];
  env.press(button, true);
  await tick();
  assert.equal(button.disabled, false);
  assert.equal(env.calls.invalidate, 0);
  assert.equal(wrapper.children.at(-1).textContent, 'Failed');
});
