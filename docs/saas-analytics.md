# BeepBeep AI SaaS Analytics

Instrumentation-only contract for billing truth, identity resolution, marketing attribution, and dashboard mapping. Complements [`posthog-telemetry-plan.md`](posthog-telemetry-plan.md).

## Architecture

```
WordPress plugin (intent)          Backend Stripe webhooks (truth)
─────────────────────────          ───────────────────────────────
checkout_started                   checkout_completed
signup_succeeded        ────────►    subscription_activated
UTM first-touch         metadata     subscription_renewed
site_install_id         merge        subscription_upgraded/downgraded
                                   payment_failed / payment_recovered
                                   refund_processed
                                   trial_started/expired/converted
         │                                    │
         └──────────── PostHog 358941 ────────┘
```

**Rule:** Billing revenue events are emitted from verified Stripe webhooks only (`event_source=stripe_webhook`). The plugin may emit intent events (`checkout_started`) but never authoritative payment/subscription state.

Legacy `payment_succeeded` events continue to be emitted for dashboard backward compatibility.

## Canonical Billing Events

| Event | Stripe source | Notes |
| --- | --- | --- |
| `checkout_completed` | `checkout.session.completed` | Subscription and one-time checkout |
| `subscription_activated` | `invoice.payment_succeeded` (`subscription_create`) | First paid period |
| `subscription_renewed` | `invoice.payment_succeeded` (`subscription_cycle`) | Renewal |
| `subscription_upgraded` | `subscription_update` / plan tier increase | Includes MRR expansion |
| `subscription_downgraded` | `subscription_update` / plan tier decrease | Includes MRR contraction |
| `subscription_cancelled` | `customer.subscription.deleted` | Churn signal |
| `payment_failed` | `payment_intent.payment_failed`, `invoice.payment_failed` | Recoverable failures tracked |
| `payment_recovered` | Paid invoice after prior failure | When `payment_recovered` flag set |
| `refund_processed` | `charge.refunded`, `refund.created` | Refund value in `refund_value` |
| `trial_started` | `customer.subscription.updated` (`trialing`) | Trial period begins |
| `trial_expired` | `customer.subscription.trial_will_end` | Trial ending |
| `trial_converted` | Invoice with `is_trial_conversion` | Trial → paid |
| `payment_succeeded` | Legacy alias | Kept for existing dashboards |

Implementation: `oppti-backend/fresh-stack/services/billingTelemetry.js`, wired from `routes/billing.js`.

## Canonical Billing Properties

Every billing event includes (when available):

| Property | Source |
| --- | --- |
| `customer_id` | Stripe customer ID |
| `subscription_id` | Stripe subscription ID |
| `plan`, `previous_plan` | Metadata + account state |
| `billing_interval` | `monthly` / `yearly` / `one_time` |
| `currency`, `amount` | Stripe amount (major units) |
| `mrr_delta`, `arr_delta` | Computed from plan/amount |
| `expansion_mrr`, `contraction_mrr` | Derived from `mrr_delta` |
| `trial_days`, `coupon` | Subscription/checkout metadata |
| `payment_provider` | Always `stripe` |
| `country` | Metadata / billing address when present |
| `plugin_version` | Checkout metadata from plugin |
| `site_install_id`, `host` | Site identity |
| `telemetry_version` | Schema version (`1`) |
| `event_source` | Always `stripe_webhook` |
| `utm_*`, `referrer`, `landing_page`, `acquisition_channel` | First-touch attribution |

Dedup: `$insert_id` = Stripe event ID.

## Identity Flow

1. **Anonymous:** `site_install_id` is the journey key (plugin PHP + JS context).
2. **Auth success:** `bbai-posthog.js` calls `alias(account_id, site_install_id)` once, then `identify(account_id)`.
3. **Billing webhooks:** Backend resolves distinct ID priority: `account_id` → `user_id` → `license_key` → `site_id` → Stripe IDs.
4. **Server alias:** Backend calls PostHog `$create_alias` when account and site_install_id differ.

Fragmentation fix: alias on auth bridges anonymous admin sessions to account IDs without renaming plugin events.

## Marketing Attribution

**Plugin (`includes/class-bbai-attribution.php`):**
- Captures first-touch UTM/referrer/landing page on first admin load.
- Persists in `bbai_marketing_attribution` option.
- Passed through checkout API → Stripe session metadata → billing events.

**Backend:** Merges attribution from Stripe metadata into all billing PostHog events.

## Customer Health Events

Scheduled job (`customerHealthTelemetry.js`), enabled with `CUSTOMER_HEALTH_CRON_ENABLED=1`:

| Event | Trigger |
| --- | --- |
| `customer_inactive_14_days` | No site activity ≥14d |
| `customer_inactive_30_days` | No site activity ≥30d |
| `customer_returned` | *(stub — needs prior inactive marker)* |
| `power_user` | ≥200 generations / 30d |
| `high_usage_customer` | ≥75 generations / 30d |
| `low_usage_customer` | ≤5 generations / 30d |

## Webhook Reliability

| Control | Implementation |
| --- | --- |
| Signature verification | `verifyWebhookSignature` — rejects 400 on bad sig |
| Idempotency | `$insert_id` = Stripe `evt_*` |
| Dedup | PostHog insert ID + `bbai_apply_site_billing_event` RPC |
| Retry safety | Always returns 200 to Stripe after processing; Loops/PostHog failures are logged, not thrown |
| Structured logging | `[billing] webhook_write_trace` per event |
| Graceful recovery | Legacy license fallback when V2 site billing RPC fails |
| Dead letter | Failed handlers return 500 only on unhandled exceptions; monitor via logs |

## Dashboard Mapping

### Growth Dashboard [1813602](https://us.posthog.com/project/358941/dashboard/1813602)

Recommended filters: `plugin_version`, `plan`, `host`, `country`, date range.

Existing insights use `site_install_id`, `plugin_opened`, generation events. No breaking changes.

### Revenue Dashboard [1813611](https://us.posthog.com/project/358941/dashboard/1813611)

Updated insights:
- **S7 Checkout Funnel** — now includes `checkout_completed` → `subscription_activated`
- **S2 New Subscriptions** — `subscription_activated` where `event_source=stripe_webhook` (replaces checkout_started proxy)

Remaining PROXY tiles (S5, S6, S9, S12–S16) still use plugin telemetry until individually migrated.

## Recommended Alerts (HogQL)

```sql
-- No installs 24h
SELECT count() AS installs
FROM events
WHERE event = 'plugin_activated'
  AND timestamp >= now() - INTERVAL 1 DAY
HAVING installs = 0
```

```sql
-- No generations 12h
SELECT count() AS gens
FROM events
WHERE event IN ('generation_completed', 'alt_generated')
  AND timestamp >= now() - INTERVAL 12 HOUR
HAVING gens = 0
```

```sql
-- Payment failures spike (24h vs 7d baseline)
SELECT
  countIf(timestamp >= now() - INTERVAL 1 DAY) AS failures_24h,
  countIf(timestamp >= now() - INTERVAL 7 DAY) / 7 AS avg_daily_7d
FROM events
WHERE event = 'payment_failed'
  AND properties.event_source = 'stripe_webhook'
HAVING failures_24h > avg_daily_7d * 3
```

```sql
-- No new subscriptions 7d
SELECT count() AS new_subs
FROM events
WHERE event = 'subscription_activated'
  AND properties.event_source = 'stripe_webhook'
  AND timestamp >= now() - INTERVAL 7 DAY
HAVING new_subs = 0
```

```sql
-- Refund spike
SELECT count() AS refunds
FROM events
WHERE event = 'refund_processed'
  AND timestamp >= now() - INTERVAL 24 HOUR
HAVING refunds >= 5
```

Configure these as PostHog insights with alert thresholds or Signals scouts.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| No billing events | `POSTHOG_API_KEY` + `POSTHOG_HOST` on backend; webhook logs `[billing] PostHog capture` |
| Duplicate events | Same `$insert_id` should dedupe in PostHog; verify Stripe retries |
| Identity split | Confirm `alias()` fired on login; check `site_install_id` on billing metadata |
| Missing UTM | First admin visit must include params; check `bbai_marketing_attribution` option |
| MRR looks wrong | `mrr_delta` uses catalog defaults when invoice amount missing; verify plan normalization |

## Validation Checklist

- [ ] `npm test -- --testPathPattern=billing` in `oppti-backend/fresh-stack`
- [ ] `php -l` on changed plugin PHP files
- [ ] Stripe test webhook → PostHog Live Events shows `subscription_activated` with `event_source=stripe_webhook`
- [ ] Login → alias merges anonymous `site_install_id` to `account_id`
- [ ] Revenue dashboard S2/S7 render (may be empty until production webhooks flow)

## Files Changed

See parent agent output for full file list per repo.
