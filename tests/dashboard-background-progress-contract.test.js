const fs = require('fs');
const path = require('path');
const { describe, test } = require('node:test');
const assert = require('node:assert/strict');

const root = path.join(__dirname, '..');

describe('dashboard background generation progress contract', () => {
  test('renders an accessible inline progress surface beneath the hero CTA', () => {
    const template = fs.readFileSync(
      path.join(root, 'admin', 'partials', 'dashboard-logged-in-hero.php'),
      'utf8'
    );

    assert.match(template, /data-bbai-hero-generation-progress="1"/);
    assert.match(template, /bbai-live-region--visible/);
    assert.match(template, /data-bbai-hero-generation-progress-track="1"/);
    assert.match(template, /role="progressbar"/);
    assert.match(template, /aria-live="polite"/);
    assert.match(template, /data-bbai-hero-progress-view="1"/);
  });

  test('subscribes the inline progress surface to the shared job state', () => {
    const script = fs.readFileSync(
      path.join(root, 'assets', 'js', 'admin', 'job-widget.js'),
      'utf8'
    );

    assert.match(script, /function renderInlineProgress\(state\)/);
    assert.match(script, /window\.bbaiJobState\.subscribe\(render\)/);
    assert.match(script, /data-bbai-hero-generation-progress-fill/);
    assert.match(script, /aria-valuetext/);
    assert.match(script, /Review generated ALT text/);
  });
});
