=== AltText AI by OpptiAI ===
Contributors: benjaminoats
Donate link: https://oppti.ai
Tags: accessibility, alt text, images, automation, ai, wcag, media library
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AltText AI automatically generates descriptive, accessibility-friendly alt text for images in the WordPress Media Library.

== Description ==

AltText AI helps site owners create clear and accessible image descriptions by generating alt text automatically on upload or on demand. The plugin analyses the image and returns a descriptive text string that can be edited before saving.

This plugin may connect to an external service (OpptiAI API) to process images and generate descriptions. The service receives the image file and returns text. No personal user data is sent. Details on processing and data handling are available at https://oppti.ai/privacy.

This plugin can be used with a free monthly quota or an optional paid plan for higher usage. All features available in the free version remain functional indefinitely.

### Key Features

* Generate alt text automatically on image upload
* Generate or regenerate alt text from the Media Library
* WCAG-friendly alt text output
* Optional bulk generation tools
* Optional monthly limits depending on plan
* Admin dashboard for viewing usage
* Works with any theme or plugin
* No changes required to your existing content workflow

### How It Works

1. Install and activate the plugin  
2. Open the Media Library  
3. Upload a new image or select an existing one  
4. Use the “Generate Alt Text” button to create a description  
5. Edit the generated text before saving (if needed)

AltText AI stores usage data within your site’s WordPress database. No external analytics are included.

### External Services

This plugin may connect to the OpptiAI API to generate text from images.  
Data sent: image file only  
Data returned: alt text string  
Terms: https://oppti.ai/terms  
Privacy: https://oppti.ai/privacy  

### Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin from the Plugins page
3. Navigate to **AltText AI** in the WordPress admin menu
4. Optional: configure settings such as language and generation rules

### Frequently Asked Questions

= Is an external service required? =  
Yes. The plugin uses the OpptiAI API to generate alt text. Only image content is processed.

= Can I edit the generated alt text? =  
Yes, all generated text appears in an editable field before saving.

= Does this plugin replace existing alt text? =  
Only when the user chooses to regenerate or overwrite it.

= Are paid plans required? =  
No. The free plan includes a monthly quota. Paid plans are optional.

= Is this plugin compatible with my theme or other plugins? =  
Yes. It works with standard WordPress media fields.

### Screenshots

1. Dashboard showing usage information  
2. Generate alt text inside the Media Library  
3. Settings page  
4. Bulk tools (optional)

### Changelog

= 1.0.0 =
* Initial release

### Upgrade Notice

= 1.0.0 =
First release of AltText AI by OpptiAI.

### Credits

Developed by OpptiAI  
https://oppti.ai