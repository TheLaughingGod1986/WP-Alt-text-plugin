=== BeepBeep AI – Alt Text Generator ===
Contributors: benjaminoats
Tags: accessibility, alt text, images, automation, ai, wcag, media library, seo
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generates SEO-optimized, accessibility-ready alt text for WordPress images using advanced AI.

== Description ==

BeepBeep AI automatically generates clean, meaningful, SEO-optimized alt text for every image in your WordPress media library. Designed for blogs, agencies, WooCommerce stores, photographers, and publishers, it uses advanced AI models to boost your image SEO, accessibility, and workflow speed.

Features:

* WCAG-compliant accessibility-friendly alt text  

* Boosts Google image search rankings  

* Works automatically in background  

* Bulk-generate for thousands of images  

* Multi-model support (Backend API + OpenAI fallback)  

* Perfect for agencies with high-volume media workflows  

* No configuration required

Perfect for WordPress users who need an AI alt text generator that creates accessibility-ready descriptions. This WordPress alt text plugin improves WCAG compliance and boosts SEO through automated image optimization.

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

= Key Features =

* Automatic AI alt text generation on image upload
* On-demand generation from Media Library
* Bulk generation tools for existing images
* WCAG 2.1 AA compliant output for accessibility
* SEO-optimized descriptions for better search rankings
* Editable generated text
* Usage tracking dashboard
* Monthly quota management
* Works with any WordPress theme or plugin
* No changes required to existing content workflow

This WordPress alt text generator ensures every image has proper accessibility attributes while improving your site's SEO performance through optimized AI alt text descriptions.

= How It Works =

1. Install and activate the plugin  
2. Open the WordPress Media Library
3. Upload a new image or select an existing one  
4. Click "Generate Alt Text" to create an AI-generated description
5. Edit the generated text if needed
6. Save the alt text to your image

The plugin stores usage data locally in your WordPress database. No external analytics or tracking is included by default. All data remains on your server except for image files temporarily transmitted to the API service for processing.

== External Services ==

This plugin uses external APIs to provide AI-powered image alt text generation, usage tracking, and secure plan upgrades.

1. OpenAI API  
   - Purpose: Generates AI alt text for images.  
   - Data sent: The image file name or short text prompt plus the user's OpenAI API key (if the user enters their own key).  
   - Terms: https://openai.com/policies/terms-of-use  
   - Privacy: https://openai.com/policies/privacy-policy  

2. Stripe Checkout  
   - Purpose: Processes paid upgrades for Pro, Agency, and Credit packages.  
   - Data sent: User email, checkout metadata, and plan selection.  
   - Terms: https://stripe.com/legal  
   - Privacy: https://stripe.com/privacy  

3. OptiAI API  
   - Purpose: Authenticates users, syncs account status, and retrieves usage limits.  
   - Data sent: Site URL, plugin ID, API token, and usage statistics.  
   - Privacy Policy URL: (insert once your website is live)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Media → AI Alt Text in the WordPress admin menu
4. Optional: Configure settings such as language preferences and generation rules

== Frequently Asked Questions ==

= Is an external service required? =  

Yes. The plugin uses the Alt Text AI Backend API (hosted at https://alttext-ai-backend.onrender.com) which coordinates alt text generation. The backend service may utilize OpenAI for generating descriptions. Only image content is transmitted to the service. No personal data is collected.

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

Developed by beepbeepv2
https://profiles.wordpress.org/beepbeepv2/

== Privacy & Security ==

This plugin:
* Stores all data locally in your WordPress database
* Only transmits image files to the API service when generating alt text
* Does not collect personal user data
* Complies with WordPress.org privacy guidelines
* Uses secure HTTPS connections for all API communication
* Allows users to disable API calls through settings
* Provides transparent information about external service usage
