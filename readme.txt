=== MonoRanks ===
Contributors: monoranks
Tags: seo, audit, ai, redirects, meta description
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects your site to MonoRanks (SEO, AEO and GEO audits) and applies the fixes you approve there, with undo.

== Description ==

MonoRanks audits every page of your website each week, finds what keeps it off page one or out of AI answers (SEO, AEO and GEO checks, page speed), ranks the fixes by what they are worth, and explains each one in plain words. This plugin is the link between your site and your MonoRanks account: it sends published content metadata so audits and rechecks stay current, and it writes the fixes you approve in MonoRanks into WordPress, each one with Undo.

What you get with the plugin connected:

* One-click fixes for titles, meta descriptions, headings, image alt text, canonical URLs, noindex and redirects, written into the same fields Yoast SEO, Rank Math and All in One SEO read.
* An answer-first opening paragraph for pages that bury their answer, shown as a before/after diff first.
* AI crawler rules (GPTBot, ClaudeBot, PerplexityBot, Google-Extended and others) in robots.txt and an llms.txt file, published only after you approve them.
* A recheck of the affected pages the same day a post changes, so the audit never goes stale.
* A change log under Settings → MonoRanks with Undo for every write.

= This plugin relies on an external service =

The plugin talks to MonoRanks (https://monoranks.com). Nothing is sent until you connect the site: either by approving MonoRanks in WordPress from your MonoRanks account, or by pasting a connector key under Settings → MonoRanks.

How it connects:

* To send data, the plugin uses a connector key that belongs to this website only. The key can send this website's content, change alerts and plugin status; it cannot read anything from MonoRanks. You can revoke it in MonoRanks or disconnect under Settings → MonoRanks.
* To apply fixes, MonoRanks uses the "MonoRanks" Application Password you approve in WordPress. Revoke it in Users → Profile to stop all writes.

What is sent to MonoRanks, over HTTPS:

* Published content metadata: post type, URL, title, a 40-word excerpt, author display name and avatar URL, categories, tags, publish and modified dates, SEO title, meta description, canonical URL, noindex flag, and image URLs with their alt text. Sent in batches when MonoRanks asks, when you connect, and once a day.
* When you publish, update or unpublish a post: its ID, URL and the metadata above, so MonoRanks can recheck that page.
* Plugin status: site name and address, plugin and WordPress version, active SEO plugin, number of published items per type.

Never sent: drafts, private or password-protected posts, comments, user emails or passwords, plugin or theme settings.

What MonoRanks can change, only after you approve each change in MonoRanks: SEO title, meta description, canonical URL, noindex, image alt text, redirects, one opening paragraph at the top of a post (shown to you as a before/after diff first; it never rewrites your existing text and can be removed again with Undo), per-crawler AI access lines in robots.txt, and an llms.txt file. It cannot edit themes, settings or users. When you approve MonoRanks, it may turn this plugin on if it is installed but inactive. Every change is listed under Settings → MonoRanks and can be undone from MonoRanks for 30 days.

Works with Yoast SEO, Rank Math and All in One SEO. Without an SEO plugin, the connector prints the approved title, description, canonical and noindex itself.

* Terms of service: https://monoranks.com/legal/terms/
* Privacy policy: https://monoranks.com/legal/privacy/

= Disconnect =

Settings → MonoRanks → Disconnect stops sending. Revoking the "MonoRanks" Application Password in Users → Profile stops writes. Deactivating the plugin stops both.

== Installation ==

1. Install and activate the plugin.
2. In your MonoRanks account, open the website → Integrations → WordPress → Connect to WordPress, and approve MonoRanks when WordPress asks. The plugin turns itself on and sends the first batch of content metadata.
3. Alternatively, copy the connector key from MonoRanks and paste it under Settings → MonoRanks in WordPress.

To stop everything: Settings → MonoRanks → Disconnect (and revoke the "MonoRanks" application password under Users → Profile).

== Frequently Asked Questions ==

= Does the plugin change my site on its own? =

No. It only applies changes you approved one by one in MonoRanks, and lists every change under Settings → MonoRanks, where each can be undone from MonoRanks for 30 days.

= What if I do not use Yoast, Rank Math or All in One SEO? =

The connector prints the approved title, description, canonical and noindex tags itself. Nothing else about your theme is touched.

= Does it slow my site down? =

No. Content metadata is sent in the background from WP-Cron (or when MonoRanks asks); visitors never wait for it. Redirects approved in MonoRanks are stored as one option and checked early in the request.

= What is sent, exactly? =

See the "This plugin relies on an external service" section above. Never: drafts, private posts, comments, user emails or passwords, plugin or theme settings.

= How do I remove all data? =

Deleting the plugin removes its settings and scheduled tasks. Deleting the website in MonoRanks removes what was sent there.

== Screenshots ==

1. Settings → MonoRanks: connection state, what was sent, and the change log.
2. The website's Integrations screen in MonoRanks with the connector card.
3. An action in MonoRanks with the change ready to approve.

== Changelog ==

= 1.0.0 =
* Initial release.
