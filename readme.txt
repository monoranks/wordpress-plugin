=== MonoRanks ===
Contributors: veronalabs, mostafas1990, kashani
Tags: seo, aeo, geo, ai, audit
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.21
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Weekly SEO, AEO and GEO checks for your site, with fixes written for you after you approve them. Every change can be undone.

== Description ==

**Find what keeps your pages off page one and out of AI answers, then fix it in a click.**

[MonoRanks](https://monoranks.com) checks every page of your site each week for:

* 🔍 **SEO**: how Google sees and ranks your pages
* 💬 **AEO**: whether answer engines can quote you
* 🤖 **GEO**: whether AI search (ChatGPT, Perplexity, Gemini) finds and names you
* ⚡ **Speed**: the pages that load too slowly

Each problem comes with a plain explanation and a suggested fix, sorted by what it is worth. This plugin connects your site, so the fixes you approve are **written into WordPress for you**.

= What you get =

* **A MonoRanks screen in WordPress.** Your scores and how they moved this week, search clicks, the pages that need attention most, and fixes ready to apply.
* **Scores in Posts and Pages.** A MonoRanks column shows each page's scores. Sort by score, or open the **Needs attention** view.
* **What to do next.** Sites worth asking for a link, backlinks won and lost, your competitors and the keywords they win, keyword movers this week, and your latest report.
* **MonoRanks in the post editor.** Each post's scores, Google clicks and top searches, revenue when Google Analytics is connected, lost links to win back, and the last change MonoRanks saw.
* **One-click fixes.** Titles, meta descriptions, image alt text, canonical URLs, noindex and redirects.
* **Answer-first openings.** A short opening paragraph for pages that bury their answer. You see a before/after first.
* **AI crawler control.** Rules for GPTBot, ClaudeBot, PerplexityBot, Google-Extended and others in robots.txt, plus an llms.txt file.
* **Always up to date.** When you change a post, MonoRanks rechecks that page the same day.
* ↩️ **Undo for everything.** Every change is listed under **MonoRanks → Settings** and can be undone for 30 days.

= Works with your SEO plugin =

**Yoast SEO**, **Rank Math** and **The SEO Framework**: fixes go into their own fields. **All in One SEO**: fixes are passed to it through its filters. **No SEO plugin?** MonoRanks prints the approved title, description, canonical and noindex itself. **Another SEO plugin**, such as SEOPress? Those four fixes are not written, and MonoRanks tells you why, because that plugin prints its own tags. The other fixes still work.

= You stay in control =

* **Nothing changes on its own.** MonoRanks only writes what you approved, one change at a time.
* **It cannot edit** your theme, settings or users.
* **The opening paragraph** never rewrites your text. It is added on top and can be removed with Undo.
* When you approve MonoRanks, it may switch this plugin on if it is installed but inactive.

= This plugin uses an external service =

The plugin talks to [MonoRanks](https://monoranks.com). **Nothing is sent until you connect your site**, either by approving MonoRanks in WordPress or by pasting a connector key under **MonoRanks → Settings**.

**How it connects**

* **To send data**, the plugin uses a connector key that belongs to this site only. It can send this site's content, change alerts and plugin status, and read back this site's audit results for the MonoRanks screens. It cannot read any other site or account data.
* **To apply fixes**, MonoRanks uses the "MonoRanks" Application Password you approve in WordPress.

**What is sent, over HTTPS**

* **Published content details:** post type, URL, title, a 40-word excerpt, author display name and avatar URL, categories, tags, publish and modified dates, SEO title, meta description, canonical URL, noindex flag, and image URLs with their alt text. Sent when you connect, when MonoRanks asks, and once a day.
* **When you publish, update or unpublish a post:** its ID, URL and the details above, so MonoRanks can recheck that page.
* **Plugin status:** site name and address, plugin and WordPress version, active SEO plugin, and the number of published items per type.

**Never sent:** drafts, private or password-protected posts, comments, user emails or passwords, plugin or theme settings.

**What is read back:** this site's audit results (scores, open issues, the fixes you approved, and search clicks if you connected Google Search Console in MonoRanks), and this site's backlinks, outreach targets, competitors, keyword positions, reports, page history, and visits and revenue per page if you connected Google Analytics in MonoRanks. They are cached in WordPress and shown to admins only, never on the public site.

**Legal:** [Terms of service](https://monoranks.com/legal/terms/) · [Privacy policy](https://monoranks.com/legal/privacy/)

= Source code =

The admin screens are built with React. `build/main.js` and `build/main.css` are compiled from `resources/admin/src`, published with the build tools in the [plugin's GitHub repository](https://github.com/monoranks/wordpress-plugin). Run `npm ci && npm run build` to rebuild them.

== Installation ==

= Install the plugin =

1. In your WordPress admin, go to **Plugins → Add Plugin** (called **Add New** in older WordPress versions).
2. Search for **MonoRanks**, click **Install Now**, then **Activate**.

= Connect it to MonoRanks =

1. Sign in at [app.monoranks.com](https://app.monoranks.com) (a free account is enough) and add your website.
2. Open the website, go to **Integrations → WordPress** and click **Connect to WordPress**.
3. WordPress asks you to approve MonoRanks. Click **Yes, I approve of this connection**. That is all: the plugin turns itself on and sends the first batch of content metadata.

= Connect with a key instead =

If your site cannot use Application Passwords (they need HTTPS, and some security plugins turn them off), create a key in MonoRanks under **Settings → API and MCP** and paste it in WordPress under **MonoRanks → Settings**. Your content reaches MonoRanks, but approved fixes cannot be written back until you connect as above.

= Disconnect =

Go to **MonoRanks → Settings → Disconnect**. To also stop MonoRanks from writing, go to **Users → Profile → Application Passwords** and click **Revoke** next to "MonoRanks". Fixes already applied stay on your site.

Step-by-step guide: [MonoRanks WordPress plugin docs](https://monoranks.com/docs/wordpress-plugin/).

== Frequently Asked Questions ==

= Does the plugin change my site on its own? =

**No.** It only applies changes you approved one by one in MonoRanks. Every change is listed under **MonoRanks → Settings** and can be undone for 30 days.

= Do I need Yoast, Rank Math, All in One SEO or The SEO Framework? =

**No.** Without an SEO plugin, MonoRanks prints the approved title, description, canonical and noindex tags itself. Nothing else about your theme is touched.

= Does it slow my site down? =

**No.** Content details are sent in the background (WP-Cron, or when MonoRanks asks), so visitors never wait. Approved redirects are stored as one option and checked early in the request.

= What is sent, exactly? =

See **This plugin uses an external service** above. Never sent: drafts, private posts, comments, user emails or passwords, plugin or theme settings.

= How do I disconnect? =

Go to **MonoRanks → Settings → Disconnect** to stop sending. To stop writes too, go to **Users → Profile → Application Passwords** and click **Revoke** next to "MonoRanks". Deactivating the plugin stops both.

= How do I remove all data? =

Deleting the plugin removes its settings and scheduled tasks. Deleting the website in MonoRanks removes what was sent there.

== Screenshots ==

1. MonoRanks → Overview: site health and AEO scores, search clicks, pages needing attention, fixes ready to apply, recent changes with Undo (also in Persian).
2. The MonoRanks column in Posts: each page's health and AEO score rings; hovering opens a card with fixes waiting, open issues, audit dates and links. Sortable, with a "Needs attention" view (also in Persian).
3. MonoRanks → Settings: connection state, connector key, last sync, what is sent, recent changes (also in Persian).
4. Before connecting: the three steps to connect MonoRanks (also in Persian).

== Changelog ==

= 0.1.21 - 2026-10-09 =
* **Enhancement:** The content sync also sends each image in a post with the alt written in the post body, next to the alt stored on the image.
* **Fix:** Image alt text is checked and written where it shows: the alt in the post body when the image sits there, and the stored alt. Fixes are no longer refused as changed when the page and the stored alt differ.

= 0.1.20 - 2026-10-07 =
* **Enhancement:** Each fix on the Overview reads "Image alt on <page>" and shows the old value, an arrow and the new one, with the image name.
* **Enhancement:** The Reports panel lists the website's weekly emails (name, next send, recipients, on or off) next to its reports.
* **Fix:** A fix whose value was changed in WordPress after it was reviewed is no longer listed as ready to apply. It shows under "Needs review in MonoRanks" with a link, and MonoRanks moves it back to review.
* **Fix:** The apply error no longer says the whole page changed; it names what happened.

= 0.1.19 - 2026-10-04 =
* **New:** "What to do next" on the Overview: outreach, backlinks, competitors and keyword movers.
* **New:** A MonoRanks box in the post editor with search, visits, revenue and lost links for the page.
* **Enhancement:** The Posts list card shows clicks, revenue and lost links.
* **Fix:** Persian translation file header.

= 0.1.18 =
* After every fix MonoRanks writes (or undoes), the plugin clears that page from the active page cache, so the change shows right away. Adds WP-Optimize, Nginx Helper, Breeze, Kinsta, WP Engine and Pantheon to WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache and SiteGround Optimizer.
* MonoRanks is told which cache was cleared, so it can tell you when a page may still show the old version.

= 0.1.17 =
* After MonoRanks writes llms.txt or the AI crawler rules, the plugin clears /llms.txt or /robots.txt from WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache and SiteGround Optimizer, so an old cached copy does not hide the change.
* /llms.txt is sent with headers that tell caches not to keep it.

= 0.1.16 =
* The Overview says how many fixes are ready to apply and how many still need a value in MonoRanks, instead of counting them all as ready.

= 0.1.15 =
* Works with The SEO Framework: approved titles, descriptions, canonicals and noindex go into its own fields, and the page shows them once.
* On the homepage, if The SEO Framework's Homepage Settings already set that field, the fix is refused with the reason, because that setting wins.
* With an SEO plugin MonoRanks cannot write into yet (such as SEOPress), those fixes are refused with a reason that names the plugin, and no second set of tags is printed.

= 0.1.14 =
* In block editor posts, the approved opening paragraph goes in as its own paragraph block before the first block. Other blocks, including those from other plugins, are never changed.
* Every body edit is checked right after it is saved. If a block would break, the post is put back as it was and the fix is reported as failed, with the reason.

= 0.1.13 =
* The description lists only the fixes the plugin can write.
* With All in One SEO active, approved titles, descriptions, canonicals and noindex go through its own tags instead of adding a second set.
* Without an SEO plugin, an approved noindex joins WordPress's robots tag instead of adding a second one.

= 0.1.11 =
* When no fix is approved yet, the MonoRanks screen says how many fixes MonoRanks can write for this site, by type, with a link to review them.

= 0.1.10 =
* The search clicks change on the MonoRanks screen says what it is compared with: the previous 28 days, not this week.
* Persian: the last untranslated messages on the MonoRanks screen are translated.
* A site that was never connected no longer offers to erase data it does not have.

= 0.1.9 =
* An empty score ring in the Posts list explains that the page has not been checked, instead of looking like a fault.

= 0.1.8 =
* Reads every score again after this update, so posts left without one get theirs back.

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
