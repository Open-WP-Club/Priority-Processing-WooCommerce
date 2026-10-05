const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function boot(success = true) {
  const events = [];
  const requests = [];
  const texts = [];
  const document = { body: {} };
  const error = '<img src=x onerror=alert(1)>';
  const selection = {
    length: 1,
    ready(callback) { callback(); return this; },
    on(name, selector, callback) { events.push({ name, callback: callback || selector }); return this; },
    off() { return this; },
    is() { return true; },
    remove() { return this; },
    text(value) { texts.push(value); return this; },
    prepend() { return this; },
    offset() { return { top: 100 }; },
    animate() { return this; },
    trigger(name) { for (const event of events) if (event.name === name) event.callback(); return this; }
  };
  const $ = () => selection;
  vm.runInNewContext(readFileSync('assets/js/frontend-blocks.js', 'utf8'), {
    jQuery: $, document, URLSearchParams,
    wppData: { ajax_url: '/ajax', nonce: 'nonce', cutoff_ts: 0 },
    setTimeout() {},
    fetch(url, options) { requests.push({ url, options }); return Promise.resolve({ json: () => Promise.resolve({ success, data: { message: error } }) }); }
  });
  return { events, requests, texts, error, change() { for (const event of events) if (event.name.startsWith('change')) event.callback(); }, refresh() { selection.trigger('updated_checkout'); } };
}

test('each checkbox change sends one AJAX request even after checkout refreshes', async () => {
  const env = boot();
  env.refresh(); env.refresh(); env.refresh();
  env.change();
  await new Promise(setImmediate);
  assert.equal(env.requests.length, 1);
  const payload = new URLSearchParams(env.requests[0].options.body);
  assert.equal(payload.get('action'), 'wpp_update_priority');
  assert.equal(payload.get('priority_enabled'), 'true');
  env.refresh(); env.change();
  await new Promise(setImmediate);
  assert.equal(env.requests.length, 2);
});

test('AJAX error messages are inserted through text rather than HTML', async () => {
  const env = boot(false);
  env.change();
  await new Promise(setImmediate);
  assert.deepEqual(env.texts, [env.error]);
});
