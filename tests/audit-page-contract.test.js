const fs = require('fs');
const path = require('path');
const { describe, test } = require('node:test');
const assert = require('node:assert/strict');

const root = path.join(__dirname, '..');

const read = (...parts) => fs.readFileSync(path.join(root, ...parts), 'utf8');

describe('image audit page contract', () => {
  test('the score card carries the required health-indicator disclaimer', () => {
    const service = read('includes', 'audit', 'class-image-accessibility-score-service.php');
    const card = read('admin', 'partials', 'components', 'audit-score-card.php');

    // The exact wording is owned by the service so it cannot drift per-surface.
    assert.match(service, /This score is an OpptiAI health indicator based on the checks available in this plugin\. It is not a formal accessibility certification\./);
    assert.match(card, /Image_Accessibility_Score_Service::disclaimer\(\)/);
    assert.match(card, /bbai-audit-score__disclaimer/);
  });

  test('audit surfaces make no compliance or certification claims', () => {
    const forbidden = [
      'wcag compliant',
      'wcag-compliant',
      'guaranteed accessible',
      'legally compliant',
      'perfect accessibility',
      'ai-certified',
      'fully accessible',
    ];

    const surfaces = [
      read('admin', 'partials', 'audit-tab.php'),
      read('admin', 'partials', 'components', 'audit-score-card.php'),
      read('admin', 'partials', 'components', 'audit-stat-grid.php'),
      read('admin', 'partials', 'components', 'audit-progress.php'),
    ].join('\n').toLowerCase();

    forbidden.forEach((phrase) => {
      assert.ok(!surfaces.includes(phrase), `audit copy must not claim "${phrase}"`);
    });
  });

  test('the empty-alt finding explains that decorative images may be empty', () => {
    const page = read('admin', 'partials', 'audit-tab.php');

    assert.match(page, /Decorative images may correctly have empty alt text/);
  });

  test('progress is exposed to assistive technology', () => {
    const progress = read('admin', 'partials', 'components', 'audit-progress.php');

    assert.match(progress, /role="progressbar"/);
    assert.match(progress, /aria-valuenow=/);
    assert.match(progress, /aria-valuemin="0"/);
    assert.match(progress, /aria-valuemax="100"/);
    assert.match(progress, /aria-live="polite"/);
    assert.match(progress, /role="alert"/);
  });

  test('run controls are real buttons, not clickable divs', () => {
    const progress = read('admin', 'partials', 'components', 'audit-progress.php');

    ['start', 'resume', 'cancel'].forEach((action) => {
      const pattern = new RegExp(`<button[^>]*data-bbai-audit-action="${action}"`, 's');
      assert.match(progress, pattern, `${action} must be a <button>`);
    });
  });

  test('every dynamic value in the audit surfaces is escaped', () => {
    const surfaces = ['audit-tab.php'].map((f) => read('admin', 'partials', f))
      .concat(['audit-score-card.php', 'audit-stat-grid.php', 'audit-progress.php']
        .map((f) => read('admin', 'partials', 'components', f)));

    surfaces.forEach((source) => {
      // Any `echo $var` without an esc_*/number_format/printf wrapper is a leak.
      const unescaped = source.match(/echo\s+\$bbai_[a-z_]+\s*;/g) || [];
      assert.deepEqual(unescaped, [], `unescaped output: ${unescaped.join(', ')}`);
    });
  });

  test('the page renders a state for first run, empty, error and stale', () => {
    const page = read('admin', 'partials', 'audit-tab.php');

    assert.match(page, /bbai-audit-page__state--first-run/);
    assert.match(page, /bbai-audit-page__state--empty/);
    assert.match(page, /bbai-audit-page__state--error/);
    assert.match(page, /No issues detected by the current audit rules/);
    assert.match(page, /Audit storage is not ready/);

    const progress = read('admin', 'partials', 'components', 'audit-progress.php');
    assert.match(progress, /stopped before it finished/);
    assert.match(progress, /<noscript>/);
  });

  test('audit assets load only on the audit tab', () => {
    const assets = read('admin', 'traits', 'trait-core-assets.php');

    assert.match(assets, /private function is_audit_tab\(\)/);
    assert.match(assets, /if \( \$this->is_audit_tab\(\) \) \{\s*\$this->enqueue_audit_assets\(/);
    assert.match(assets, /'bbai-audit-page' => 'audit'|'bbai-audit'\s*=> 'audit'/);
  });

  test('the issues-table controller loads only on the issues view', () => {
    const assets = read('admin', 'traits', 'trait-core-assets.php');

    assert.match(assets, /if \( 'issues' === \$audit_view \) \{/);
    assert.match(assets, /wp_enqueue_script\(\s*'bbai-audit-issues'/);
  });
});

describe('image audit issues table', () => {
  const table = read('admin', 'partials', 'components', 'audit-issues-table.php');

  test('generation delegates to the existing generator, not a copy of it', () => {
    const controller = read('assets', 'js', 'bbai-audit-issues.js');

    // The audit must never grow its own generation path.
    assert.match(controller, /post\(\s*'beepbeepai_regenerate_single'/);
    assert.ok(
      !/api_key|openai|anthropic|\/v1\/chat/i.test(controller),
      'the table must not talk to a generation API directly'
    );
  });

  test('a successful generation re-audits just that image', () => {
    const controller = read('assets', 'js', 'bbai-audit-issues.js');

    assert.match(controller, /post\(\s*'bbai_audit_rescan'/);
  });

  test('review routes to the existing ALT Library queue rather than a new one', () => {
    assert.match(table, /'page'\s*=> 'bbai-library'/);
    assert.match(table, /'status'\s*=> 'needs_review'/);
  });

  test('manual editing uses the WordPress attachment editor', () => {
    assert.match(table, /\$bbai_edit\s*=\s*\(string\) \( \$bbai_meta\['edit_link'\]/);
  });

  test('filters are keyboard-operable links with an active state', () => {
    assert.match(table, /<a[\s\S]{0,200}bbai-audit-issues__filter/);
    assert.match(table, /aria-current="/);
    assert.match(table, /aria-label="<\?php esc_attr_e\( 'Filter audit findings'/);
  });

  test('the table scrolls inside its own container, not the page', () => {
    assert.match(table, /bbai-audit-issues__scroll/);

    const css = read('assets', 'css', 'features', 'audit', 'audit.css');
    assert.match(css, /\.bbai-audit-issues__scroll\s*\{[^}]*overflow-x:\s*auto/);
  });

  test('thumbnails are decorative so screen readers skip them', () => {
    assert.match(table, /class="bbai-audit-issues__thumb"[\s\S]{0,200}alt=""/);
    assert.match(table, /loading="lazy"/);
  });

  test('every column the brief asks for is present', () => {
    ['Image', 'Current alt text', 'Findings', 'Used on', 'Last scanned', 'Actions'].forEach((column) => {
      assert.ok(table.includes(column), `missing column: ${column}`);
    });
  });

  test('table output is escaped', () => {
    const unescaped = table.match(/echo\s+\$bbai_[a-z_]+\s*;/g) || [];
    assert.deepEqual(unescaped, []);
  });

  test('filter slugs are validated before reaching SQL', () => {
    const page = read('admin', 'partials', 'audit-tab.php');

    assert.match(page, /Audit_Filters::sanitize\(/);
    assert.match(page, /absint\( wp_unslash\( \$_GET\['audit_page'\] \) \)/);
  });

  test('rescanning checks the user can edit that specific attachment', () => {
    const controller = read('includes', 'audit', 'class-audit-ajax-controller.php');

    assert.match(controller, /current_user_can_edit_attachment/);
    assert.match(controller, /current_user_can\( 'edit_post', \$attachment_id \)/);
    assert.match(controller, /'attachment' !== get_post_type\( \$attachment_id \)/);
  });

  test('every attachment-scoped action checks that same permission', () => {
    const controller = read('includes', 'audit', 'class-audit-ajax-controller.php');

    ['handle_rescan', 'handle_decorative', 'handle_dismiss'].forEach((handler) => {
      const body = controller.split(`public function ${handler}()`)[1] || '';
      const scoped = body.split('public function')[0];

      assert.ok(
        scoped.includes('current_user_can_edit_attachment'),
        `${handler} must verify the user can edit that attachment`
      );
    });
  });
});

describe('image audit decorative and dismissal', () => {
  const table = read('admin', 'partials', 'components', 'audit-issues-table.php');
  const controller = read('assets', 'js', 'bbai-audit-issues.js');

  test('decorative marking is reversible from the table', () => {
    assert.match(table, /data-decorative="1"/);
    assert.match(table, /data-decorative="0"/);
    assert.match(table, /Mark decorative/);
    assert.match(table, /Not decorative/);
  });

  test('the decorative control explains what decorative means', () => {
    assert.match(table, /Decorative images carry no meaning and should normally use an empty alt attribute/);
  });

  test('dismissal says plainly that the image is not changed', () => {
    assert.match(table, /Hide this finding without changing the image/);
    assert.match(controller, /The image itself is not changed/);
  });

  test('dismissal asks before hiding a finding', () => {
    assert.match(controller, /window\.confirm\(\s*t\(\s*'dismissConfirm'/);
  });

  test('marking decorative never rewrites the image alt text', () => {
    const service = read('includes', 'audit', 'class-decorative-image-service.php');

    // Only the decorative meta keys are written — never _wp_attachment_image_alt.
    assert.ok(
      !service.includes('_wp_attachment_image_alt'),
      'the decorative service must not touch stored alt text'
    );
  });

  test('nothing infers a decorative decision automatically', () => {
    const service = read('includes', 'audit', 'class-decorative-image-service.php');

    assert.ok(!/filename|basename|guess|detect|infer/i.test(service.replace(/\/\*[\s\S]*?\*\//g, '')));
  });
});

describe('image audit entitlements and telemetry', () => {
  test('the free-plan cap is enforced in the query, not just hidden in markup', () => {
    const page = read('admin', 'partials', 'audit-tab.php');

    assert.match(page, /Audit_Entitlement_Service::result_limit\(\)/);
    assert.match(page, /array_slice\( \$bbai_audit_results\['rows'\]/);
    assert.match(page, /\$bbai_audit_page_num = min\( \$bbai_audit_page_num, \$bbai_audit_max_page \)/);
  });

  test('the upgrade CTA states what is being withheld', () => {
    const table = read('admin', 'partials', 'components', 'audit-issues-table.php');

    assert.match(table, /Your plan shows the first %1\$s of %2\$s findings/);
    assert.match(table, /data-bbai-audit-upgrade=/);
  });

  test('telemetry rides the existing analytics endpoint', () => {
    const controller = read('assets', 'js', 'bbai-audit-issues.js');

    assert.match(controller, /'action', 'beepbeepai_telemetry'/);
    // Telemetry must never block or break the action it is reporting on.
    assert.match(controller, /\.catch\( function \(\) \{[\s\S]{0,120}\} \)/);
  });

  test('telemetry is skipped without consent', () => {
    const controller = read('assets', 'js', 'bbai-audit-issues.js');
    const assets = read('admin', 'traits', 'trait-core-assets.php');

    assert.match(controller, /if \( ! config\.telemetry \) \{\s*return;/);
    assert.match(assets, /BBAI_Telemetry::has_telemetry_consent\(\)/);
  });

  test('no audit telemetry property carries free text', () => {
    const service = read('includes', 'audit', 'class-audit-telemetry-service.php');

    ['alt_text', 'file_name', 'image_url', 'api_key', 'post_content', 'message'].forEach((banned) => {
      assert.ok(
        !new RegExp(`'${banned}'`).test(service.replace(/\/\*[\s\S]*?\*\//g, '')),
        `${banned} must not appear in the telemetry whitelist`
      );
    });
  });
});
