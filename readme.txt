=== MonoRanks ===
Contributors: veronalabs, mostafas1990, kashani
Tags: seo, audit, ai, redirects, meta description
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects your site to MonoRanks (SEO, AEO and GEO audits) and applies the fixes you approve there, with undo.

== Description ==

MonoRanks audits every page of your website each week, finds what keeps it off page one or out of AI answers (SEO, AEO and GEO checks, page speed), ranks the fixes by what they are worth, and explains each one in plain words. This plugin is the link between your site and your MonoRanks account: it sends published content metadata so audits and rechecks stay current, and it writes the fixes you approve in MonoRanks into WordPress, each one with Undo.

What you get with the plugin connected:

* A MonoRanks screen in your WordPress admin: the site's health and AEO scores and how they moved this week, search clicks, the pages that need attention most, the fixes you approved ready to apply with one click, and every change written so far with Undo.
* A MonoRanks column in Posts and Pages with each page's health and AEO scores; hover a row for the fixes waiting, open issues and audit dates. Sortable by score, with a "Needs attention" view.
* One-click fixes for titles, meta descriptions, headings, image alt text, canonical URLs, noindex and redirects, written into the same fields Yoast SEO, Rank Math and All in One SEO read.
* An answer-first opening paragraph for pages that bury their answer, shown as a before/after diff first.
* AI crawler rules (GPTBot, ClaudeBot, PerplexityBot, Google-Extended and others) in robots.txt and an llms.txt file, published only after you approve them.
* A recheck of the affected pages the same day a post changes, so the audit never goes stale.
* A change log under MonoRanks → Settings with Undo for every write.

= This plugin relies on an external service =

The plugin talks to MonoRanks (https://monoranks.com). Nothing is sent until you connect the site: either by approving MonoRanks in WordPress from your MonoRanks account, or by pasting a connector key under MonoRanks → Settings.

How it connects:

* To send data, the plugin uses a connector key that belongs to this website only. The key can send this website's content, change alerts and plugin status, and read back this website's audit results (scores, issues, the fixes you approved) for the MonoRanks screens in WordPress; it cannot read any other website or account data. You can revoke it in MonoRanks or disconnect under MonoRanks → Settings.
* To apply fixes, MonoRanks uses the "MonoRanks" Application Password you approve in WordPress. Revoke it in Users → Profile to stop all writes.

What is sent to MonoRanks, over HTTPS:

* Published content metadata: post type, URL, title, a 40-word excerpt, author display name and avatar URL, categories, tags, publish and modified dates, SEO title, meta description, canonical URL, noindex flag, and image URLs with their alt text. Sent in batches when MonoRanks asks, when you connect, and once a day.
* When you publish, update or unpublish a post: its ID, URL and the metadata above, so MonoRanks can recheck that page.
* Plugin status: site name and address, plugin and WordPress version, active SEO plugin, number of published items per type.

Never sent: drafts, private or password-protected posts, comments, user emails or passwords, plugin or theme settings.

Read from MonoRanks, with the same key, and cached in WordPress: this website's audit results (site and per-page scores, open issues, the fixes you approved, search clicks from Google Search Console if you connected it in MonoRanks). Nothing is read on the public site; only admins see it.

What MonoRanks can change, only after you approve each change in MonoRanks: SEO title, meta description, canonical URL, noindex, image alt text, redirects, one opening paragraph at the top of a post (shown to you as a before/after diff first; it never rewrites your existing text and can be removed again with Undo), per-crawler AI access lines in robots.txt, and an llms.txt file. It cannot edit themes, settings or users. When you approve MonoRanks, it may turn this plugin on if it is installed but inactive. Every change is listed under MonoRanks → Settings and can be undone there or from MonoRanks for 30 days.

Works with Yoast SEO, Rank Math and All in One SEO. Without an SEO plugin, the connector prints the approved title, description, canonical and noindex itself.

* Terms of service: https://monoranks.com/legal/terms/
* Privacy policy: https://monoranks.com/legal/privacy/

= Disconnect =

MonoRanks → Settings → Disconnect stops sending. Revoking the "MonoRanks" Application Password in Users → Profile stops writes. Deactivating the plugin stops both.

== Installation ==

1. Install and activate the plugin.
2. In your MonoRanks account, open the website → Integrations → WordPress → Connect to WordPress, and approve MonoRanks when WordPress asks. The plugin turns itself on and sends the first batch of content metadata.
3. Alternatively, copy the connector key from MonoRanks and paste it under MonoRanks → Settings in WordPress.

To stop everything: MonoRanks → Settings → Disconnect (and revoke the "MonoRanks" application password under Users → Profile).

== Frequently Asked Questions ==

= Does the plugin change my site on its own? =

No. It only applies changes you approved one by one in MonoRanks, and lists every change under MonoRanks → Settings, where each can be undone for 30 days.

= What if I do not use Yoast, Rank Math or All in One SEO? =

The connector prints the approved title, description, canonical and noindex tags itself. Nothing else about your theme is touched.

= Does it slow my site down? =

No. Content metadata is sent in the background from WP-Cron (or when MonoRanks asks); visitors never wait for it. Redirects approved in MonoRanks are stored as one option and checked early in the request.

= What is sent, exactly? =

See the "This plugin relies on an external service" section above. Never: drafts, private posts, comments, user emails or passwords, plugin or theme settings.

= How do I remove all data? =

Deleting the plugin removes its settings and scheduled tasks. Deleting the website in MonoRanks removes what was sent there.

== Screenshots ==

1. MonoRanks → Overview: site health and AEO scores, search clicks, pages needing attention, fixes ready to apply, recent changes with Undo (also in Persian).
2. The MonoRanks column in Posts: each page's health and AEO score rings; hovering opens a card with fixes waiting, open issues, audit dates and links. Sortable, with a "Needs attention" view (also in Persian).
3. MonoRanks → Settings: connection state, connector key, last sync, what is sent, recent changes (also in Persian).
4. Before connecting: the three steps to connect MonoRanks (also in Persian).

== Changelog ==

= 0.1.7 =
* Scores arrive for every post on large sites: a pull that runs out of time carries on where it stopped.

= 0.1.6 =
* Scores appear as soon as you open the MonoRanks screen, also on sites where WP-Cron is switched off.
* "Sync content now" brings the scores back with it.
* The remaining pages still update in the background.

= 0.1.5 =
* Disconnecting in WordPress tells MonoRanks, which revokes this website's key.
* "Open MonoRanks" goes to this website, not the home page.
* Audit results are asked for again within the hour after MonoRanks gains support for them.

= 0.1.4 =
* The Plugin URI and Author URI headers point at plain addresses, without tracking parameters.
* The contributor list uses the right WordPress.org username.
* The public ping route says in the code why it is public: MonoRanks calls it before the site is paired, it reads nothing, and every other route needs an administrator.

= 0.1.3 =
* On a site with many plugins the admin menu is taller than the page; the plugin's screens now stretch to the bottom of it, so the footer is the last thing on the page with nothing left over under it.

= 0.1.2 =
* On a site with a long admin menu the plugin's screens now reach the bottom of the page, so the footer is the last thing on it: no strip and no empty space under it.

= 0.1.1 =
* On a site whose admin menu is taller than the page, the plugin's screens ended in a grey strip under the footer. The dark band now runs to the bottom of the page.

= 0.1.0 =
* Beta, before the first WordPress.org release: MonoRanks menu with an Overview (scores, search clicks, pages needing attention, fixes ready to apply, changes with Undo) and Settings; a MonoRanks column with health and AEO scores in Posts and Pages; content sync and one-click fixes with undo; a danger zone that erases everything the plugin stored; Persian translation.
