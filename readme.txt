=== WP Alt Text AI ===
Contributors: benjaminoats
Donate link: https://oppti.ai
Tags: accessibility, alt text, images, automation, ai, wcag, media library, seo
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generates SEO-optimized and WCAG-compliant alt text for WordPress images using AI.

== Description ==

WP Alt Text AI helps site owners create clear, accessible, and SEO-friendly image descriptions by automatically generating alt text for images in the WordPress Media Library. The plugin uses artificial intelligence to analyze images and generate descriptive text that improves accessibility compliance and image search rankings.

This plugin connects to an external API service to process images and generate descriptions. Only image files are transmitted to the service; no personal user data is collected. The service returns descriptive text that can be edited before saving. For details on data handling, visit https://oppti.ai/privacy.

= Free Plan Features =

The plugin includes a functional free tier with:
* 50 AI-generated alt texts per month
* Generate alt text automatically on image upload
* Generate or regenerate alt text from the Media Library
* Edit generated text before saving
* WCAG-friendly alt text output
* Admin dashboard for viewing usage
* All core functionality available without payment

Paid plans (Pro and Agency) offer higher monthly quotas and additional features, but are completely optional. The free tier remains fully functional indefinitely.

= External Service Notice =

This plugin requires an active internet connection and connects to the OpptiAI API service to generate alt text descriptions. When you use the generate feature:

* Image files are securely transmitted to the OpptiAI API
* The API analyzes the image and returns descriptive text
* No personal user data is collected or stored by the external service
* You can disable API calls at any time through plugin settings
* Image processing complies with GDPR and privacy regulations

API Endpoint: https://alttext-ai-backend.onrender.com

Privacy Policy: https://oppti.ai/privacy
Terms of Service: https://oppti.ai/terms

= Key Features =

* Automatic alt text generation on image upload
* On-demand generation from Media Library
* Bulk generation tools for existing images
* WCAG 2.1 AA compliant output
* SEO-optimized descriptions
* Editable generated text
* Usage tracking dashboard
* Monthly quota management
* Works with any WordPress theme or plugin
* No changes required to existing content workflow

= How It Works =

1. Install and activate the plugin  
2. Open the WordPress Media Library
3. Upload a new image or select an existing one  
4. Click "Generate Alt Text" to create an AI-generated description
5. Edit the generated text if needed
6. Save the alt text to your image

The plugin stores usage data locally in your WordPress database. No external analytics or tracking is included by default. All data remains on your server except for image files temporarily transmitted to the API service for processing.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Media → AI Alt Text in the WordPress admin menu
4. Optional: Configure settings such as language preferences and generation rules

== Frequently Asked Questions ==

= Is an external service required? =  

Yes. The plugin uses the OpptiAI API to generate alt text descriptions. Only image content is transmitted to the service. No personal data is collected.

= Can I use this plugin without an internet connection? =

No. The plugin requires an active internet connection to connect to the API service for image processing.

= Can I edit the generated alt text? =  

Yes. All generated text appears in an editable field before saving. You can modify, improve, or completely rewrite the generated description.

= Does this plugin replace existing alt text? =  

No. The plugin only replaces existing alt text when you explicitly choose to regenerate or overwrite it. Existing alt text is preserved by default.

= Are paid plans required to use the plugin? =

No. The free plan includes 50 AI-generated alt texts per month and all core functionality. Paid plans are completely optional and only provide higher monthly quotas and additional features.

= What happens when I reach my monthly quota? =

Free users receive 50 generations per month. When the quota is reached, you can wait until the next month (quota resets on the 1st) or upgrade to a paid plan for higher limits.

= Is this plugin compatible with my theme or other plugins? =  

Yes. The plugin works with standard WordPress media fields and is compatible with any WordPress theme or plugin that uses the standard Media Library.

= Does the plugin collect personal data? =

No. The plugin only transmits image files to the API service for processing. No personal user data is collected or transmitted.

= Can I disable API calls? =

Yes. You can disable API calls through the plugin settings page. However, disabling API calls will prevent alt text generation functionality.

== Screenshots ==

1. Dashboard showing usage information and quota status
2. Generate alt text button in the WordPress Media Library
3. Settings page with API configuration options
4. Bulk generation tools for processing multiple images

== Changelog ==

= 4.2.2 =
* Enhanced license management for Pro and Agency users
* Improved admin authentication flow
* Fixed countdown timer accuracy
* Updated UI for better consistency
* Security improvements

= 4.2.1 =
* Added license key support for Pro users
* Improved agency dashboard layout
* Enhanced site usage tracking
* Bug fixes and performance improvements

= 4.2.0 =
* Major UI overhaul with modern design
* Added agency license support
* Improved usage tracking
* Enhanced authentication flow
* Better mobile responsiveness

= 1.0.0 =
* Initial release
* Basic alt text generation functionality
* Free tier with 50 monthly generations
* Media Library integration

== Upgrade Notice ==

= 4.2.2 =
Recommended update with enhanced license management and security improvements.

= 4.2.1 =
Recommended update for Pro and Agency users with license key support.

= 4.2.0 =
Major update with redesigned UI and new agency features. Recommended for all users.

= 1.0.0 =
First release of WP Alt Text AI.

== Credits ==

Developed by Benjamin Oats
https://oppti.ai

== Privacy & Security ==

This plugin:
* Stores all data locally in your WordPress database
* Only transmits image files to the API service when generating alt text
* Does not collect personal user data
* Complies with WordPress.org privacy guidelines
* Uses secure HTTPS connections for all API communication
* Allows users to disable API calls through settings
* Provides transparent information about external service usage
