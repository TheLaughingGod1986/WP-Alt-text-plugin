const fs = require('fs');
const path = require('path');
const { describe, test } = require('node:test');
const assert = require('node:assert/strict');

const root = path.join(__dirname, '..');

describe('logged-in dashboard generation preflight progress', () => {
  test('opens progress UI before fetching and queueing missing image IDs', () => {
    const template = fs.readFileSync(
      path.join(root, 'admin', 'partials', 'dashboard-logged-in-hero.php'),
      'utf8'
    );

    const handlerStart = template.indexOf('function dispatchGenerateMissing( e, trigger )');
    const preflightIndex = template.indexOf('window.bbaiShowGenerationPreflightProgress', handlerStart);
    const fetchIndex = template.indexOf('fetchMissingAttachmentIds( limit )', handlerStart);
    const queueIndex = template.indexOf('return postBulkQueue( ids )', handlerStart);

    assert.notEqual(handlerStart, -1);
    assert.notEqual(preflightIndex, -1);
    assert.notEqual(fetchIndex, -1);
    assert.notEqual(queueIndex, -1);
    assert.ok(preflightIndex < fetchIndex);
    assert.ok(fetchIndex < queueIndex);
  });

  test('cleans up preflight progress when generation cannot start', () => {
    const template = fs.readFileSync(
      path.join(root, 'admin', 'partials', 'dashboard-logged-in-hero.php'),
      'utf8'
    );

    assert.match(template, /function hidePreflightProgress\(\)/);
    assert.match(template, /window\.bbaiHideGenerationPreflightProgress\(\)/);
    assert.match(template, /if \( ids\.length === 0 \)[\s\S]*hidePreflightProgress\(\);/);
    assert.match(template, /if \( ! queueJson\.success \)[\s\S]*hidePreflightProgress\(\);/);
    assert.match(template, /\.catch\( function \( err \)[\s\S]*hidePreflightProgress\(\);/);
  });

  test('exposes modern preflight helpers from the shared admin script', () => {
    const script = fs.readFileSync(
      path.join(root, 'assets', 'js', 'bbai-admin.js'),
      'utf8'
    );

    assert.match(script, /window\.bbaiShowGenerationPreflightProgress = function/);
    assert.match(script, /showBulkProgress\(title, total, 0\)/);
    assert.match(script, /window\.bbaiUpdateGenerationPreflightProgress = function/);
    assert.match(script, /window\.bbaiHideGenerationPreflightProgress = hideBulkProgress/);
  });
});
