=== BeepBeep AI – Alt Text Generator ===
Plugin Name: BeepBeep AI – Alt Text Generator
Contributors: beepbeepv2
Plugin URI: https://oppti.dev/beepbeep-ai-alt-text-generator
Author URI: https://oppti.dev
Tags: accessibility, AI, Alt Text, image seo, WooCommerce
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 4.4.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: beepbeep-ai-alt-text-generator

Generate image alt text for WordPress to improve accessibility and image search.

== Description ==

**Stop losing traffic from Google Images and product search. Fix missing alt text automatically in <60 seconds.**

Missing alt text hurts rankings, accessibility, and conversions. When product images have blank alt tags, search engines lose context and shoppers using assistive tech lose critical information.

**BeepBeep AI solves this automatically.**

BeepBeep AI generates clear, SEO-ready, WCAG-friendly alt text for your WordPress media library and WooCommerce catalog. You can bulk-fix legacy images, auto-generate on upload, and review every suggestion before saving.

= Demo Video =

https://www.youtube.com/watch?v=XK9snigPH2c

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

= Try Before You Sign Up =

Get started immediately with **10 free trial generations** — no account or email required. When you're ready, create a free account to unlock 50 credits per month.

= Free Plan Features =

* 10 instant trial generations (no account needed)
* 50 AI-generated alt texts monthly with a free account
* Bulk generation tools (within monthly credits)
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
1. Dashboard view of the AI Alt Text Generator showing one-click generation, live credit tracking, and editable suggestions so teams can review accessibility text before saving.
2. Bulk edit workflow in the WordPress Media Library where multiple images are selected and processed together, making large-scale alt text cleanup faster and more consistent.
3. WooCommerce product screen demonstrating WooCommerce image optimization for featured and gallery images, with AI-generated alt text improving accessibility and image SEO for product discovery.

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
* Maintenance release with bug fixes and stability improvements.
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

= 4.4.3 =
* Maintenance release with bug fixes and stability improvements.
* Improved WooCommerce, Image SEO, and Accessibility workflows.
* Internal performance and reliability optimizations.

== Upgrade Notice ==

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
