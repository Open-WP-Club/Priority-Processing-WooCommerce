const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function boot({ loading = false, containers = 2 } = {}) {
  let cart = {};
  let update;
  let ready;
  const nodes = Array.from({ length: containers }, () => ({
    children: ['server-rendered notice'],
    replaceChildren() { this.children = []; },
    appendChild(node) { this.children.push(node); }
  }));
  const document = {
    readyState: loading ? 'loading' : 'complete',
    querySelectorAll: () => nodes,
    createElement: () => ({ style: {}, textContent: '' }),
    addEventListener(name, callback) { assert.equal(name, 'DOMContentLoaded'); ready = callback; }
  };
  vm.runInNewContext(readFileSync('assets/js/cart-messages.js', 'utf8'), {
    document,
    window: { wp: { data: {
      select(name) { assert.equal(name, 'wc/store/cart'); return { getCartData: () => cart }; },
      subscribe(callback, name) { assert.equal(name, 'wc/store/cart'); update = callback; }
    } } }
  });
  return { nodes, ready: () => ready(), set(data) { cart = data; update(); } };
}

const response = (message, items = [{}]) => ({ items, extensions: { 'wpp-priority': { cart_message: message } } });

test('preserves server rendering until Store API data is available', () => {
  const env = boot();
  env.set({ items: [] });
  assert.deepEqual(env.nodes[0].children, ['server-rendered notice']);
});

test('reveals and hides messages when the server threshold or permission changes', () => {
  const env = boot();
  env.set(response(''));
  assert.equal(env.nodes[0].children.length, 0);
  env.set(response('Priority available'));
  for (const node of env.nodes) assert.equal(node.children[0].textContent, 'Priority available');
  env.set(response(''));
  for (const node of env.nodes) assert.equal(node.children.length, 0);
});

test('treats Store API text as plain text and does not recreate an unchanged notice', () => {
  const env = boot();
  const message = '<img src=x onerror=alert(1)> & priority';
  env.set(response(message));
  const notice = env.nodes[0].children[0];
  assert.equal(notice.textContent, message);
  assert.equal(notice.innerHTML, undefined);
  env.set(response(message));
  assert.equal(env.nodes[0].children[0], notice);
});

test('removes the message when the last cart item is removed', () => {
  const env = boot();
  env.set(response('Priority available'));
  env.set(response('Priority available', []));
  assert.equal(env.nodes[0].children.length, 0);
});

test('initializes after DOMContentLoaded when script is loaded early', () => {
  const env = boot({ loading: true });
  env.ready();
  env.set(response('Ready'));
  assert.equal(env.nodes[0].children[0].textContent, 'Ready');
});

test('works on a page without message containers', () => {
  assert.doesNotThrow(() => boot({ containers: 0 }));
});
