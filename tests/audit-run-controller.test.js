const fs = require('fs');
const path = require('path');
const { describe, test, beforeEach } = require('node:test');
const assert = require('node:assert/strict');

const SOURCE = fs.readFileSync(
  path.join(__dirname, '..', 'assets', 'js', 'bbai-audit.js'),
  'utf8'
);

/**
 * The smallest DOM the controller actually touches.
 */
function makeElement(attrs = {}) {
  return {
    attributes: { ...attrs },
    listeners: {},
    style: {},
    disabled: false,
    textContent: '',
    getAttribute(name) {
      return Object.prototype.hasOwnProperty.call(this.attributes, name)
        ? this.attributes[name]
        : null;
    },
    setAttribute(name, value) {
      this.attributes[name] = String(value);
    },
    removeAttribute(name) {
      delete this.attributes[name];
    },
    hasAttribute(name) {
      return Object.prototype.hasOwnProperty.call(this.attributes, name);
    },
    addEventListener(event, handler) {
      this.listeners[event] = handler;
    },
    click() {
      if (this.listeners.click) {
        this.listeners.click();
      }
    },
  };
}

/**
 * Build the harness: fake DOM, scripted fetch responses, controllable timers.
 */
function setup({ responses = {}, rootAttrs = {}, config = {} } = {}) {
  const els = {
    root: makeElement({ 'data-run-id': '', 'data-status': 'not_started', ...rootAttrs }),
    start: makeElement(),
    resume: makeElement({ hidden: 'hidden' }),
    cancel: makeElement({ hidden: 'hidden' }),
    progress: makeElement({ hidden: 'hidden' }),
    bar: makeElement(),
    fill: makeElement(),
    status: makeElement(),
    error: makeElement({ hidden: 'hidden' }),
  };

  const selectorMap = {
    '[data-bbai-audit-action="start"]': els.start,
    '[data-bbai-audit-action="resume"]': els.resume,
    '[data-bbai-audit-action="cancel"]': els.cancel,
    '[data-bbai-audit-progress]': els.progress,
    '[data-bbai-audit-bar]': els.bar,
    '.bbai-audit-run__fill': els.fill,
    '[data-bbai-audit-status]': els.status,
    '[data-bbai-audit-error]': els.error,
  };

  els.root.querySelector = (selector) => selectorMap[selector] || null;

  const calls = [];
  const timers = [];
  let reloaded = false;

  const fetchImpl = (url, options) => {
    const params = new URLSearchParams(options.body);
    const action = params.get('action');
    calls.push({ action, params });

    const responder = responses[action];
    if (!responder) {
      return Promise.resolve({ json: () => Promise.resolve({ success: true, data: {} }) });
    }

    const result = typeof responder === 'function' ? responder(calls.length) : responder;
    return Promise.resolve({ json: () => Promise.resolve(result) });
  };

  global.window = {
    bbaiAudit: {
      ajaxUrl: '/wp-admin/admin-ajax.php',
      nonce: 'test-nonce',
      strings: {},
      ...config,
    },
    fetch: fetchImpl,
    setTimeout: (fn) => {
      timers.push(fn);
      return timers.length;
    },
    confirm: () => true,
    location: {
      reload: () => {
        reloaded = true;
      },
    },
  };

  global.document = {
    querySelector: (selector) =>
      selector === '[data-bbai-audit-run]' ? els.root : null,
  };

  // eslint-disable-next-line no-new-func
  new Function(SOURCE)();

  return {
    els,
    calls,
    isReloaded: () => reloaded,
    /** Drain queued timers, letting the polling loop advance. */
    async flush(rounds = 10) {
      for (let i = 0; i < rounds; i++) {
        await Promise.resolve();
        await Promise.resolve();
        const pending = timers.splice(0, timers.length);
        pending.forEach((fn) => fn());
      }
      await Promise.resolve();
    },
  };
}

const ok = (data) => ({ success: true, data });
const fail = (message, extra = {}) => ({ success: false, data: { message, ...extra } });

const runningState = (overrides = {}) => ({
  run_id: 'run-1',
  status: 'running',
  done: false,
  can_resume: true,
  percent: 40,
  total_images: 250,
  processed_images: 100,
  ...overrides,
});

const doneState = (overrides = {}) => ({
  run_id: 'run-1',
  status: 'completed',
  done: true,
  can_resume: false,
  percent: 100,
  total_images: 250,
  processed_images: 250,
  ...overrides,
});

describe('image audit run controller', () => {
  beforeEach(() => {
    delete global.window;
    delete global.document;
  });

  test('does nothing without a nonce, so it cannot fire unauthenticated requests', async () => {
    const harness = setup({ config: { nonce: '' } });

    harness.els.start.click();
    await harness.flush(2);

    assert.deepEqual(harness.calls, []);
  });

  test('starting a run posts the start action with the nonce', async () => {
    const harness = setup({ responses: { bbai_audit_start: ok(runningState()) } });

    harness.els.start.click();
    await harness.flush(1);

    assert.equal(harness.calls[0].action, 'bbai_audit_start');
    assert.equal(harness.calls[0].params.get('nonce'), 'test-nonce');
  });

  test('a started run polls until it reports done', async () => {
    let ticks = 0;
    const harness = setup({
      responses: {
        bbai_audit_start: ok(runningState({ percent: 0, processed_images: 0 })),
        bbai_audit_tick: () => {
          ticks++;
          return ticks < 3
            ? ok(runningState({ percent: ticks * 33, processed_images: ticks * 80 }))
            : ok(doneState());
        },
      },
    });

    harness.els.start.click();
    await harness.flush();

    const tickCalls = harness.calls.filter((c) => c.action === 'bbai_audit_tick');
    assert.equal(tickCalls.length, 3, 'should stop polling once done');
    assert.ok(harness.isReloaded(), 'a finished run reloads to show the results');
  });

  test('each tick carries the run id so a stale poll cannot drive another run', async () => {
    const harness = setup({
      responses: {
        bbai_audit_start: ok(runningState({ run_id: 'run-xyz' })),
        bbai_audit_tick: ok(doneState({ run_id: 'run-xyz' })),
      },
    });

    harness.els.start.click();
    await harness.flush();

    const tick = harness.calls.find((c) => c.action === 'bbai_audit_tick');
    assert.equal(tick.params.get('run_id'), 'run-xyz');
  });

  test('progress is written to the bar, the fill and the live region', async () => {
    const harness = setup({
      responses: {
        bbai_audit_start: ok(runningState({ percent: 40, processed_images: 100, total_images: 250 })),
        bbai_audit_tick: ok(doneState()),
      },
    });

    harness.els.start.click();
    await harness.flush(1);

    assert.equal(harness.els.fill.style.width, '40%');
    assert.equal(harness.els.bar.getAttribute('aria-valuenow'), '40');
    assert.match(harness.els.status.textContent, /100/);
    assert.match(harness.els.status.textContent, /250/);
    assert.match(harness.els.status.textContent, /40/);
    assert.equal(harness.els.progress.hasAttribute('hidden'), false);
    assert.equal(harness.els.cancel.hasAttribute('hidden'), false);
  });

  test('a refused start offers to resume the existing run', async () => {
    const harness = setup({
      responses: {
        bbai_audit_start: fail('An audit is already in progress.', {
          code: 'bbai_audit_in_progress',
          state: runningState({ status: 'failed', can_resume: true }),
        }),
      },
    });

    harness.els.start.click();
    await harness.flush(2);

    assert.equal(harness.els.resume.hasAttribute('hidden'), false);
    assert.match(harness.els.error.textContent, /already in progress/);
    assert.equal(harness.els.start.disabled, false, 'the start button must be usable again');
  });

  test('resuming posts the resume action and keeps polling', async () => {
    const harness = setup({
      responses: {
        bbai_audit_resume: ok(runningState()),
        bbai_audit_tick: ok(doneState()),
      },
    });

    harness.els.resume.click();
    await harness.flush();

    assert.equal(harness.calls[0].action, 'bbai_audit_resume');
    assert.ok(harness.calls.some((c) => c.action === 'bbai_audit_tick'));
  });

  test('cancelling stops the polling loop', async () => {
    const harness = setup({
      responses: {
        bbai_audit_start: ok(runningState()),
        bbai_audit_tick: ok(runningState()),
        bbai_audit_cancel: ok({ ...runningState(), status: 'cancelled', done: true, can_resume: false }),
      },
    });

    harness.els.start.click();
    await harness.flush(2);

    const before = harness.calls.filter((c) => c.action === 'bbai_audit_tick').length;
    harness.els.cancel.click();
    await harness.flush(5);
    const after = harness.calls.filter((c) => c.action === 'bbai_audit_tick').length;

    assert.equal(after, before, 'no further ticks after cancelling');
    assert.ok(harness.calls.some((c) => c.action === 'bbai_audit_cancel'));
  });

  test('a transient tick failure is retried rather than abandoning the run', async () => {
    let ticks = 0;
    const harness = setup({
      responses: {
        bbai_audit_start: ok(runningState()),
        bbai_audit_tick: () => {
          ticks++;
          return ticks === 1 ? fail('Temporary glitch.') : ok(doneState());
        },
      },
    });

    harness.els.start.click();
    await harness.flush();

    assert.ok(ticks >= 2, 'should retry after a single failure');
    assert.ok(harness.isReloaded());
  });

  test('repeated failures stop the loop and offer a resume', async () => {
    const harness = setup({
      responses: {
        bbai_audit_start: ok(runningState()),
        bbai_audit_tick: fail('The server keeps failing.'),
      },
    });

    harness.els.start.click();
    await harness.flush(12);

    const tickCalls = harness.calls.filter((c) => c.action === 'bbai_audit_tick').length;
    assert.equal(tickCalls, 3, 'gives up after the retry budget rather than hammering the site');
    assert.equal(harness.els.resume.hasAttribute('hidden'), false);
    assert.equal(harness.els.start.disabled, false);
    assert.match(harness.els.error.textContent, /keeps failing/);
  });

  test('a run left in flight is picked back up on page load', async () => {
    const harness = setup({
      rootAttrs: { 'data-status': 'running', 'data-run-id': 'run-resumed' },
      responses: { bbai_audit_tick: ok(doneState({ run_id: 'run-resumed' })) },
    });

    await harness.flush();

    assert.ok(harness.calls.some((c) => c.action === 'bbai_audit_tick'));
  });

  test('a stale run is not auto-resumed, so the user chooses', async () => {
    const harness = setup({
      rootAttrs: { 'data-status': 'running', 'data-run-id': 'run-stale' },
      config: { isStale: true },
      responses: { bbai_audit_tick: ok(doneState()) },
    });

    await harness.flush(3);

    assert.deepEqual(harness.calls, []);
  });
});
