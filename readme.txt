=== BeepBeep AI – Alt Text Generator for Image SEO & WooCommerce ===
Plugin Name: BeepBeep AI – Alt Text Generator for Image SEO & WooCommerce
Contributors: beepbeepv2
Plugin URI: https://oppti.dev/beepbeep-ai-alt-text-generator
Author URI: https://oppti.dev
Tags: accessibility, AI, Alt Text, image seo, WooCommerce
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 4.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: beepbeep-ai-alt-text-generator

Stop losing traffic: generate SEO-ready, WCAG-friendly alt text in <60 seconds for WordPress and WooCommerce images.

== Description ==

**Stop losing traffic from Google Images and product search. Fix missing alt text automatically in <60 seconds.**

Missing alt text hurts rankings, accessibility, and conversions. When product images have blank alt tags, search engines lose context and shoppers using assistive tech lose critical information.

**BeepBeep AI solves this automatically.**

BeepBeep AI generates clear, SEO-ready, WCAG-friendly alt text for your WordPress media library and WooCommerce catalog. You can bulk-fix legacy images, auto-generate on upload, and review every suggestion before saving.

= Built for WooCommerce at Scale =

Running a large store? BeepBeep AI is optimized for WooCommerce bulk generation workflows:

* Detect and fix blank alt tags across your product image library
* Bulk-generate alt text for 1,000+ product images in one workflow
* Improve discoverability for product, category, and Google Images traffic
* Keep descriptions consistent across featured and gallery images

= Time-to-Value in Minutes =

After activation, most sites see their first AI-generated alt text results in **<60 seconds**:

* Generate from Media Library in one click
* Apply bulk actions to existing images
* Enable automatic generation for all future uploads

= Why Teams Choose BeepBeep AI =

= Fix Missing Alt Text in Bulk =

Process hundreds or thousands of images without manual writing. BeepBeep AI is designed for real-world media libraries, including high-volume WooCommerce stores.

= Improve Image SEO =

AI-generated alt text helps search engines better understand your images, improving relevance for image search and product discovery.

= Meet Accessibility Standards =

Generate descriptive alt text aligned with WCAG 2.1 AA expectations for screen reader users and accessibility-focused teams.

= Save Hours Every Month =

Manual alt text can take 1-2 minutes per image. BeepBeep AI handles repetitive work so your team can focus on content and merchandising.

= Stay in Control =

Every generated description can be reviewed and edited before saving.

= Perfect For =

* WooCommerce stores with large product catalogs
* Blogs and publishers with growing media libraries
* Agencies managing multiple WordPress sites
* Marketing teams focused on image SEO and accessibility

= Free Plan Features =

* 50 AI-generated alt texts monthly
* Bulk generation tools
* Auto-generation on upload
* Editable descriptions before save
* Usage dashboard and quota tracking

**Paid plans unlock:**

* Higher monthly limits and unlimited options
* Priority processing
* Advanced account and billing options
* Priority support

== Installation ==

1. Go to WordPress Admin -> Plugins -> Add New.
2. Search for "BeepBeep AI Alt Text Generator".
3. Click Install Now, then Activate.
4. Open Media -> AI Alt Text to generate your first descriptions.
5. Optional: enable auto-generation in Settings to process new uploads automatically.
6. For WooCommerce stores, run bulk generation to fix existing product images and galleries.

== Development Notes ==

Non-minified source files are included in this plugin package in `assets/src/` and `admin/components/`. Compiled assets are shipped in `assets/dist/` and `assets/css/`.

== Frequently Asked Questions ==

= How fast will I see results? =

Most installs can generate their first AI alt text results in under 60 seconds after setup.

= Does this plugin help with image SEO? =

Yes. Alt text helps search engines understand image content. Better image context can improve visibility in image search and product discovery.

= Is the generated alt text WCAG compliant? =

Generated output is designed to align with WCAG 2.1 AA best practices for descriptive alt text. You can review and edit every result before saving.

= Does this work with WooCommerce product images? =

Yes. BeepBeep AI works with WooCommerce featured images, product galleries, and standard WordPress media items.

= Can I bulk-fix 1,000+ product images? =

Yes. The bulk workflow is built for large media libraries and high-volume WooCommerce catalogs.

= Can I edit generated descriptions? =

Yes. Every generated description is editable before save.

= Will this slow down my website? =

No. Generation is processed through secure external APIs and does not block normal frontend page rendering.

= What happens to existing alt text? =

Existing alt text is preserved by default. You control when to regenerate.

= Is my image data secure? =

Images and metadata are sent securely over HTTPS for processing. See External Services and Privacy & Security sections below.

= Is there a free version? =

Yes. The free plan includes 50 AI-generated alt texts per month.

== Screenshots ==

1. image_1.png: Screenshot showing a friendly digital robot character actively pointing a digital scanning beam at a stylized image placeholder with a prominent 'ALT' text tag. The robot mascot (image_1.png) makes the plugin feel trustworthy and professional.
2. Dashboard view with usage stats, generation controls, and quick actions for image SEO workflows.
3. WooCommerce-focused bulk workflow for fixing missing alt tags across large product catalogs.
4. Settings page for auto-generation, account controls, and optimization preferences.

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

= 4.4.1 =
* WordPress.org compliance fixes across AJAX, REST permissions, nonces, and admin asset loading.

= 4.4.0 =
* Fixed credit usage display accuracy after generation.
* Improved backend response handling and fresh usage fetch behavior.
* Security hardening for escaped output in admin UI.
* Production cleanup for WordPress.org release quality.

= 4.3.0 =
* Added SEO character counter and quality checker guidance.
* Added schema-focused metadata support for image SEO workflows.
* Improved tooltip, modal UX, and accessibility behavior in admin UI.

= 4.2.x and earlier =
* Major UI modernization, license/account improvements, tracking fixes, and performance/security enhancements.
* Initial public release introduced AI alt text generation, media library integration, and free monthly usage tier.

== Upgrade Notice ==

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
* Stores account and license details if you connect an account (e.g., email, plan)
* Stores contact form submissions if you submit support requests (name, email, message)
* Stores per-user usage logs linked to WordPress user IDs
* Transmits image data and prompt/context text to external APIs during generation and review
* Uses secure HTTPS connections for all API communication
* Allows users to disable auto-generation through settings
* Provides transparent information about external service usage
* Images are processed and immediately deleted from our servers
* Supports WordPress privacy export/erasure tools for stored data
