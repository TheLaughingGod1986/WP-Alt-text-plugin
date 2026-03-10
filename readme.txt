=== BeepBeep AI – Alt Text Generator ===
Plugin Name: BeepBeep AI – Alt Text Generator
Contributors: beepbeepv2
Plugin URI: https://oppti.dev/beepbeep-ai-alt-text-generator
Author URI: https://oppti.dev
Tags: accessibility, alt text, image seo, woocommerce, media library
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 4.5.19
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: beepbeep-ai-alt-text-generator

Generate AI alt text for WordPress and WooCommerce images in bulk. Fix missing alt text, improve image SEO, and meet accessibility standards.

== Description ==

Images without alt text hurt your site's accessibility and visibility in Google Images. Manually writing descriptions for hundreds of images is slow and tedious.

BeepBeep AI fixes that. This alt text generator scans your WordPress media library and WooCommerce product images, finds what's missing, and generates AI descriptions you can review before publishing. Fix missing alt text in bulk, improve image SEO, and meet accessibility standards—without spending hours on manual work.

Works with standard WordPress media and WooCommerce product images, including featured images and galleries. No theme changes required.

= Fix Missing Image ALT Text in Minutes =

Missing ALT text hurts accessibility and image SEO. This plugin scans your WordPress media library, finds what's missing, and generates descriptive AI ALT text automatically. It works especially well for WooCommerce stores, large media libraries, and content-heavy sites.

Who it helps: WooCommerce stores with product catalogs, content-heavy sites, SEO teams improving image search visibility, and site owners who need to fix missing alt text at scale.

Free plan: 50 AI alt text generations per month. Start with 10 trial generations, no account required.

= Who this plugin is for =

* WooCommerce stores with many product images — Optimize product catalogs for image SEO and accessibility without changing your theme
* Content-heavy WordPress sites — Fix missing alt text across large media libraries in minutes
* SEO teams — Improve image search visibility and product discoverability with consistent, descriptive metadata
* Site owners improving accessibility — Meet WCAG requirements for image descriptions across your site
* Agencies managing client sites — Bulk-optimize media libraries across multiple WordPress installations

= Works Great With WooCommerce =

BeepBeep AI works particularly well for WooCommerce stores with large product image libraries.

Scan product images, generate AI-generated image alt text automatically, and improve accessibility and image SEO across your catalog.

Whether your store has 50 products or 5,000, BeepBeep AI helps ensure every product image includes meaningful alt text without manual work.

= Perfect for Large Media Libraries =

Managing ALT text across a large WordPress media library can quickly become overwhelming.

BeepBeep AI scans your media library and generates descriptive AI alt text automatically, helping ensure every image includes meaningful alt text without manual writing.

Whether your site has hundreds or thousands of images, BeepBeep AI makes it easy to maintain strong accessibility and image SEO across your entire library.

= How it works =

1. **Scan your media library** — Find images missing alt text across WordPress media and WooCommerce product images.
2. **Generate AI alt text** — Get descriptive, context-aware suggestions for each image.
3. **Review and save** — Approve, edit, or regenerate before publishing. You stay in control.

= Why install this plugin? =

* **Saves time** — Generate alt text in bulk instead of writing descriptions one by one
* **Fixes missing alt text** — Scan your library and fill gaps across WordPress and WooCommerce images
* **Improves image SEO** — Better alt text helps Google Images and product search understand your content
* **Supports accessibility** — Meet WCAG requirements for image descriptions
* **WooCommerce-ready** — Optimize product catalogs without changing your theme or catalog structure
* **Scales with your library** — Works with 50 images or 5,000; the workflow stays simple

= Demo Video =

https://www.youtube.com/watch?v=XK9snigPH2c

= Features =

* **Bulk alt text generation** — Fix hundreds of images in minutes instead of hours
* **Automatic alt text for new uploads** — New images get AI descriptions when you add them (optional)
* **WooCommerce product image optimization** — Featured images and galleries covered
* **AI review workflow** — Preview, edit, or regenerate suggestions before they go live
* **Improved image SEO** — Consistent, descriptive metadata helps Google Images and product search
* **Accessibility support** — Meet WCAG requirements for image descriptions
* **Less manual work** — Spend time on content, not writing alt text one by one

= Manual vs BeepBeep AI =

Manual: 1-2 minutes per image. 500 images can take 8-16 hours.
BeepBeep: Scan → generate → review. Hundreds of images in minutes.

= Free plan and paid options =

You can start with 10 trial generations without an account. The free account plan includes 50 AI ALT text generations per month. Paid plans add higher monthly limits and additional account features.

**Paid plans unlock:**

* Higher monthly limits and unlimited options
* Priority processing
* Advanced account and billing options
* Priority support

== Installation ==

1. Go to WordPress Admin -> Plugins -> Add New.
2. Search for "BeepBeep AI Alt Text Generator".
3. Click Install Now, then Activate.
4. Open BeepBeep AI -> Dashboard (or ALT Library) to generate your first descriptions.
5. Optional: enable auto-generation in Settings to process new uploads automatically.
6. For WooCommerce stores, run bulk generation to fix existing product images and galleries.

== Development Notes ==

Non-minified source files are included in this plugin package in `assets/src/` and `admin/components/`. Compiled assets are shipped in `assets/dist/` and `assets/css/`.

== Frequently Asked Questions ==

= What are the SEO benefits of AI alt text? =

AI-generated alt text gives search engines clearer context for every image, improving relevance signals for Google Images and product-focused search queries. BeepBeep AI Alt Text Generator helps you scale consistent, descriptive metadata across your library, which strengthens image SEO and supports better discoverability.

= Is this plugin compatible with WooCommerce product images? =

Yes. BeepBeep AI Alt Text Generator supports WooCommerce featured images, gallery images, and standard WordPress media attachments used on product pages. You can apply WooCommerce image optimization workflows in bulk without changing your catalog structure or theme templates.

= How do monthly free credits work? =

You get 10 free trial generations immediately with no account required. After signup, the free plan includes 50 monthly credits, with one credit used per generated alt text, and Bulk edit tools help you apply those credits efficiently across larger media libraries.

== Screenshots ==
1. See your alt text coverage, usage, and next actions at a glance—dashboard overview.
2. Generate AI alt text for your WordPress media library in bulk.
3. Review, edit, and score AI alt text before saving—quality control in one place.
4. Fix missing alt text across hundreds of images in minutes.
5. Optimize WooCommerce featured and gallery images for image SEO and accessibility.
6. New images get AI alt text automatically when you enable it in Settings.

== External Services ==

This plugin connects to external APIs to generate image alt text and provide account/billing features.

Service: AltText AI Backend API (https://alttext-ai-backend.onrender.com)
Purpose: Generate alt text descriptions, perform alt text review checks, and handle authentication, license, usage, billing, and contact requests.
When: When you generate/review alt text, authenticate, view usage/account data, manage billing, or submit a support/contact request.
Data sent: Image metadata and image content (image URL or base64), image context (title, caption, filename, optional parent post title), site URL/hash/fingerprint, and authenticated user/site identifiers.
Privacy policy: https://oppti.dev/privacy

Service: OpenAI API (used by the backend service)
Purpose: Generate and review alt text descriptions.
When: During alt text generation or review requests processed by the backend service.
Data sent: Image metadata and image content, plus context text needed for generation/review.
Privacy policy: https://openai.com/privacy

Service: Stripe Checkout
Purpose: Process plan upgrades and credit purchases.
When: When you choose a paid plan/upgrade or purchase credits.
Data sent: Selected plan/price and checkout context. Payment details are handled by Stripe.
Privacy policy: https://stripe.com/privacy

Service: Resend Email API (used by backend contact delivery)
Purpose: Deliver contact/support form messages.
When: When you submit a contact/support form in the plugin.
Data sent: Name, email, subject, message, site URL, WordPress version, and plugin version.
Privacy policy: https://resend.com/legal/privacy

== Changelog ==

= 4.5.19 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.5.18 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.5.17 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.5.16 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.5.15 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.5.14 =
* Refined exhausted-credit panel layout to improve hierarchy and reduce empty space.
* Added reusable inline quota-exhausted callout with reset-date/usage context and upgrade CTA.
* Updated dashboard and library action sections to render cleaner locked-state layouts.

= 4.5.13 =
* Fixed credit-exhausted bulk actions by removing generate/regenerate buttons when quota is exhausted.
* Added clear out-of-credits messaging directing users to upgrade or wait for monthly reset.
* Hardened quota lock behavior to prevent UI freeze on locked actions.

= 4.5.12 =
* Fixed exhausted-credit UI lockups in dashboard and library generation controls.
* Improved quota messaging and upgrade prompts when monthly limits are reached.
* Reliability update for locked control handling during bulk actions.

= 4.5.11 =
* Fixed Debug Logs filter requests in environments where `/wp-json` routes return 404.
* Improved debug log level filtering reliability for Error/Warning selections.
* Added resilient debug logs fetch/clear routing to prevent filter fallbacks from failing.

= 4.5.10 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.5.9 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.5.8 =
* Improved Re-optimise All reliability and handler compatibility across dashboard flows.
* Refined dashboard, library, analytics, and auth admin UX/stability updates.
* Maintenance updates for multisite/release workflows and overall plugin reliability.

= 4.5.7 =
* Improved Re-optimise All reliability and handler compatibility across dashboard flows.
* Refined dashboard, library, analytics, and auth admin UX/stability updates.
* Maintenance updates for multisite/release workflows and overall plugin reliability.

= 4.5.6 =
* Improved monthly quota handling with clearer reset timing and upgrade guidance across admin flows.
* Unified upgrade modal fallbacks to prevent dead-end quota dialogs.
* Prevented duplicate queue scheduling during bulk operations by honoring a skip-schedule flag.
* Routed single-image regenerate quota errors through the shared limit handler for consistent behavior.

= 4.5.5 =
* Added backend-driven member auth flow support so personal credentials can be used on connected sites.
* Removed local registration hard-blocks so team/member eligibility is enforced by backend rules.
* Kept shared site quota behavior while improving invite-related auth error handling in the modal and AJAX flow.

= 4.5.4 =
* Registration no longer hard-blocks when a site is already connected; backend team/member rules now decide access.
* Added explicit auth error handling for invite-required member flows while keeping site-wide shared quota behavior.
* Improved login/register modal handling for member onboarding and backend invite messaging.

= 4.5.3 =
* Enforced site-wide account linking: new registration is blocked when this site is already connected.
* Improved auth modal flow to redirect users to login when a site-connected account already exists.
* Ensured generation requests use shared site quota context across all WordPress users.

= 4.5.2 =
* Fixed onboarding flow so users can exit normally and no longer get stuck in redirect loops.
* Prevented duplicate regenerate click handling that could trigger multiple generation requests.
* Improved generation flow safety to avoid duplicate queue + inline processing for the same images.

= 4.5.1 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.12 =
* Added full compatibility for WordPress 7.0.
* Updated FAQ and Screenshot metadata for better accessibility and SEO.
* Refined WooCommerce image optimization descriptions.

= 4.4.11 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.10 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.9 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.8 =
* Maintenance release that improves alt text generation reliability and overall stability.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.7 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.6 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.5 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.4 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.3 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

= 4.4.1 =
* Recommended update with WordPress.org compliance, security hardening, and usage-tracking improvements.

= 4.3.0 =
* Feature update with SEO workflow improvements and accessibility-focused UI enhancements.


== Upgrade Notice ==

= 4.5.19 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.5.18 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.5.17 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.5.16 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.5.15 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.5.14 =
Improves out-of-credit dashboard and library layout with a clearer quota-exhausted callout.

= 4.5.13 =
Fixes exhausted-credit UI lockups and improves quota messaging on dashboard and library actions.

= 4.5.12 =
Fixes exhausted-credit UI lockups and improves quota messaging on dashboard and library actions.

= 4.5.11 =
Recommended update for Debug Logs filter reliability and routing compatibility.

= 4.5.10 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.5.9 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.5.8 =
Recommended maintenance update for more reliable bulk regeneration and improved admin workflow stability.

= 4.5.7 =
Recommended maintenance update for more reliable bulk regeneration and improved admin workflow stability.

= 4.5.6 =
Improves quota-limit handling and upgrade prompts for bulk + single generation workflows.

= 4.5.5 =
Supports personal credentials on connected sites while preserving shared site quota behavior.

= 4.5.4 =
Adds backend-driven member auth flow support so personal credentials can be used while credits remain shared site-wide.

= 4.5.3 =
Enforces site-wide account sharing and guides users to log in with the connected account. Includes quota-sharing fixes across WordPress users.

= 4.5.2 =
Fixes onboarding lockups and duplicate generation triggers. Recommended for all users.

= 4.5.1 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.4.11 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.4.10 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.4.9 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.4.8 =
Recommended maintenance update that improves alt text generation reliability and overall stability.

= 4.4.7 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.4.6 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.4.5 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.4.4 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.4.3 =
Recommended maintenance update with WooCommerce, Image SEO, and Accessibility reliability improvements.

= 4.4.1 =
Recommended update with WordPress.org compliance, security hardening, and usage-tracking improvements.

= 4.3.0 =
Feature update with SEO workflow improvements and accessibility-focused UI enhancements.

== Credits ==

Developed by beepbeepv2
https://profiles.wordpress.org/beepbeepv2/

== Privacy & Security ==

This plugin:
* Stores usage data locally in your WordPress database
* Stores trial usage count locally in `wp_options` using an anonymous site identifier key (`bbai_trial_usage_{site_hash}`)
* Does not require an email address during the initial 10-generation trial stage
* Stores account and license details if you connect an account (e.g., email, plan)
* Stores contact form submissions if you submit support requests (name, email, message)
* Stores per-user usage logs linked to WordPress user IDs
* Transmits image data and prompt/context text to external APIs during generation and review
* Uses secure HTTPS connections for all API communication
* Allows users to disable auto-generation through settings
* Provides transparent information about external service usage
* Images are processed and immediately deleted from our servers
* Supports WordPress privacy export/erasure tools for stored data
