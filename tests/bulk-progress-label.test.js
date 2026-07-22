const fs = require('fs');
const path = require('path');
const { describe, test } = require('node:test');
const assert = require('node:assert/strict');

function loadFormatter() {
  const source = fs.readFileSync(
    path.join(__dirname, '..', 'assets', 'js', 'bbai-admin.js'),
    'utf8'
  );
  const start = source.indexOf('function formatBulkProgressLogDoneLabel(state)');
  const nextFunction = source.indexOf('\n    function ', start + 1);
  const functionSource = source.slice(start, nextFunction).trim();
  const translate = (message) => message;
  const sprintf = (template, first, second) => template
    .replace('%1$s', first)
    .replace('%2$s', second);

  return new Function(
    '__',
    'sprintf',
    'formatDashboardNumber',
    'isBulkProgressCompleteState',
    `${functionSource}; return formatBulkProgressLogDoneLabel;`
  )(translate, sprintf, String, (state) => Boolean(state && state.complete));
}

function loadOutcomeClassifier() {
  const source = fs.readFileSync(
    path.join(__dirname, '..', 'assets', 'js', 'bbai-admin.js'),
    'utf8'
  );
  const start = source.indexOf('function getBulkProgressOutcome(state)');
  const nextFunction = source.indexOf('\n    function ', start + 1);
  const functionSource = source.slice(start, nextFunction).trim();

  return new Function(`${functionSource}; return getBulkProgressOutcome;`)();
}

describe('bulk progress live-feed count', () => {
  const formatLabel = loadFormatter();

  test('shows completed slots while a run is active', () => {
    assert.equal(
      formatLabel({ current: 3, processed: 2, total: 5, complete: false }),
      '3 of 5 done'
    );
  });

  test('shows successful optimisations after a partial run completes', () => {
    assert.equal(
      formatLabel({ current: 5, processed: 4, total: 5, complete: true }),
      '4 of 5 optimised'
    );
  });

  test('shows matching totals when every image is optimised', () => {
    assert.equal(
      formatLabel({ current: 5, processed: 5, total: 5, complete: true }),
      '5 of 5 optimised'
    );
  });
});

describe('bulk progress completion outcome', () => {
  const classify = loadOutcomeClassifier();

  test('classifies a mixed result as partial completion', () => {
    assert.equal(classify({ processed: 4, failed: 1, skipped: 0 }), 'partial');
  });

  test('reserves failure for runs with no successful images', () => {
    assert.equal(classify({ processed: 0, failed: 5, skipped: 0 }), 'failure');
  });

  test('classifies a clean run as success', () => {
    assert.equal(classify({ processed: 5, failed: 0, skipped: 0 }), 'success');
  });

  test('treats quota-limited partial progress as partial completion', () => {
    assert.equal(classify({ processed: 2, quotaBlocked: true }), 'partial');
  });
});
