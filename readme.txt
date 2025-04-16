=== GeoDirectory Google Analytics ===
Contributors: stiofansisland, paoltaia, ayecode
Donate link: https://wpgeodirectory.com
Tags: geodirectory, ga4, google analytics, geodirectory google analytics, tracking
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 5.6
Stable tag: 2.3.5
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Allows Google Analytics 4 tracking for the listings.

== Description ==

= Add Google Analytics 4 for your users viewing =

The GeoDirectory Google Analytics addon lets you easily add Google Analytics(GA4) tracking to your listings.

A widget/shortcode/block is provided to be able to show basic Google Analytics stats on the details page, you can choose to show this to listing owners and the admins, just the admins or to everyone including non logged in users.

== Installation ==

1. Upload 'geodir-google-analytics' directory to the '/wp-content/plugins/' directory
2. Activate the plugin "GeoDirectory - Google Analytics" through the 'Plugins' menu in WordPress
3. Go to WordPress Admin -> GeoDirectory -> Settings -> Google Analytics and customize behaviour as needed

== Frequently Asked Questions ==

= Does this plugin support Google Analytics 4(GA4) =

Yes

== Screenshots ==

== Changelog ==

= 2.3.6 - TBD =
* Remove Universal Analytics support as UA properties has stopped processing data - CHANGED

= 2.3.5 - 2024-12-11 =
* Analytics shows JS error when rendered via FSE theme block - FIXED
* PHP v8.3 compatibility changes - CHANGED

= 2.3.4 - 2024-08-06 =
* Chart.js updated to v3.9 - CHANGED
* Show week, month chart lines curved - CHANGED

= 2.3.3 - 2024-05-30 =
* Sometimes restricted HTTP_REFERRER in AJAX request breaks analytics - FIXED

= 2.3.2 - 2023-12-08 =
* WordPress v6.4 compatibility check - CHANGED

= 2.3.1 - 2023-05-10 =
* Google Analytics 4 compatibility - ADDED

= 2.3 - 2023-03-16 =
* Changes for AUI Bootstrap 5 compatibility - ADDED

= 2.2.1 - 2022-11-15 =
* Graph line & bar color changed - CHANGED
* Single quote in translations breaks analytics graph - FIXED
* Google OAuth 2.0 authorization compatibility changes - CHANGED

= 2.2 - 2022-02-22 =
* Changes to support GeoDirectory v2.2 new settings UI - CHANGED

= 2.1.1.2 =
* Analytics stats not working with owner,administrator user roles - FIXED

= 2.1.1.1 =
* Added some extra escaping to prevent XSS - CHANGED

= 2.1.1.0 =
* Prevent the block/widget class loading when not required - CHANGED

= 2.1.0.1 =
* Fix conflicts with Uncanny Automator Pro plugin - FIXED
* Chart.js updated to v3.2 - CHANGED

= 2.1.0.0 =
* Changes for AyeCode UI compatibility - CHANGED

= 2.0.0.5 =
* This Month vs Last Month option added in analytics stats view - ADDED
* Allow UWP and BuddyPress profile owners to view their profile stats - ADDED
* Changes for Google App Verification - CHANGED

= 2.0.0.4 =
* Analytics widget can now be placed on any page and shown to anyone - ADDED

= 2.0.0.3 =
* Small PHP notice fix - FIXED

= 2.0.0.2 =
* Install/update function added - ADDED
* Uninstall file added - FIXED

= 2.0.0.1-beta =
* All logged in setting not working properly - FIXED

= 2.0.0.0-beta =
* Initial release.