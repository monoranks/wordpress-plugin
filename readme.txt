=== MonoRanks – AI SEO Audit, AEO and GEO with One-Click Fixes ===
Contributors: veronalabs, mostafas1990, kashani
Tags: seo, ai-seo, seo-audit, aeo, geo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.24
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find what keeps your pages out of Google and AI answers. A weekly AI SEO audit with fixes you approve, written into WordPress, with undo.

== Description ==

**See why your pages miss page one and AI answers. Approve a fix, and it is done.**

[MonoRanks](https://monoranks.com) audits every page of your site each week and tells you what to fix first, in plain words. This plugin connects your site, so the fixes you approve are **written into WordPress for you**, and every one can be undone.

It checks four things:

* 🔍 **SEO**: how Google sees and ranks your pages
* 💬 **AEO** (answer engine optimization): whether answer engines can quote you
* 🤖 **GEO** (generative engine optimization): whether ChatGPT, Perplexity and Gemini find and name you
* ⚡ **Speed**: the pages that load too slowly

= What you get =

* **One-click fixes.** SEO titles, meta descriptions, canonical URLs, noindex, image alt text and redirects.
* **Category and tag pages.** SEO title and meta description for category, tag and product category pages.
* **Answer-first openings.** A short opening paragraph for pages that bury the answer. You see a before/after first.
* **AI crawler control and llms.txt.** Rules for GPTBot, ClaudeBot, PerplexityBot, Google-Extended and others in robots.txt, plus an llms.txt file.
* **A MonoRanks screen in WordPress.** Scores and how they moved, search clicks, pages that need attention, fixes ready to apply.
* **Scores in Posts and Pages.** Sort by score or open the **Needs attention** view.
* **A box in the post editor.** Scores, Google clicks, top searches and revenue (with Google Analytics) for each post.
* **What to do next.** Link ideas, backlinks won and lost, competitors, keyword movers and your latest report.
* **Fresh results.** Change a post and MonoRanks rechecks it the same day.
* ↩️ **Undo for everything.** Every change is listed under **MonoRanks → Settings** and can be undone for 30 days.

= How it works =

1. **Install** the plugin from Plugins → Add Plugin.
2. **Connect** a free MonoRanks account with one button and approve it in WordPress.
3. **Approve fixes** in MonoRanks. The plugin writes them into your site, and you can undo any of them.

= Works with your SEO plugin =

* **Yoast SEO and Rank Math:** fixes go into their own fields, including category and tag pages.
* **The SEO Framework:** fixes go into its fields for posts and pages.
* **All in One SEO:** fixes reach it through its filters, for posts and pages.
* **No SEO plugin?** MonoRanks prints the approved title, description, canonical and noindex itself, and writes the category description.
* **Another SEO plugin**, such as SEOPress? Titles, descriptions, canonicals and noindex are not written, and MonoRanks tells you why. The other fixes still work.

= You stay in control =

* **Nothing changes on its own.** MonoRanks only writes what you approved, one change at a time.
* **It cannot edit** your theme, settings or users.
* **The opening paragraph** never rewrites your text. It is added on top and can be removed with Undo.
* When you approve MonoRanks, it may switch this plugin on if it is installed but inactive.

= Free to start =

The plugin is free. A free MonoRanks account covers 3 websites with weekly audits. Paid plans with more websites, pages and keywords are on [monoranks.com](https://monoranks.com/pricing/).

= This plugin uses an external service =

The plugin talks to [MonoRanks](https://monoranks.com). **Nothing is sent until you connect your site**, either by approving MonoRanks in WordPress or by pasting a connector key under **MonoRanks → Settings**.

**How it connects**

* **To send data**, the plugin uses a connector key that belongs to this site only. It can send this site's content, change alerts and plugin status, and read back this site's audit results for the MonoRanks screens. It cannot read any other site or account data.
* **To apply fixes**, MonoRanks uses the "MonoRanks" Application Password you approve in WordPress.

**What is sent, over HTTPS**

* **Published content details:** post type, URL, title, a 40-word excerpt, author display name and avatar URL, categories, tags, publish and modified dates, SEO title, meta description, canonical URL, noindex flag, and image URLs with their alt text. Sent when you connect, when MonoRanks asks, and once a day.
* **Category, tag and other archive pages:** name, URL, a 40-word description, SEO title, meta description and noindex flag.
* **When you publish, update or unpublish a post:** its ID, URL and the details above, so MonoRanks can recheck that page.
* **Plugin status:** site name and address, plugin and WordPress version, active SEO plugin, and the number of published items per type and of categories, tags and other terms.

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

1. Go to **MonoRanks → Overview** and click **Connect**.
2. Sign in or sign up at MonoRanks (a free account is enough).
3. WordPress asks you to approve MonoRanks. Click **Yes, I approve of this connection**. That is all: the settings page shows **Connected** and the first content is sent.

You can also start from MonoRanks: open your website, go to **Integrations → WordPress** and click **Connect to WordPress**.

= Connect with a key instead =

If your site cannot use Application Passwords (they need HTTPS, and some security plugins turn them off), create a key in MonoRanks under **Settings → API and MCP** and paste it in WordPress under **MonoRanks → Settings**. Your content reaches MonoRanks, but approved fixes cannot be written back until you connect as above.

= Disconnect =

Go to **MonoRanks → Settings → Disconnect**. To also stop MonoRanks from writing, go to **Users → Profile → Application Passwords** and click **Revoke** next to "MonoRanks". Fixes already applied stay on your site.

Step-by-step guide: [MonoRanks WordPress plugin docs](https://monoranks.com/docs/wordpress-plugin/).

== Frequently Asked Questions ==

= Is it free? =

**Yes.** The plugin is free, and a free MonoRanks account covers 3 websites. Paid plans for more websites are on [monoranks.com](https://monoranks.com/pricing/).

= Do I need a MonoRanks account? =

**Yes.** The audit runs at MonoRanks, so the plugin needs a free account to connect. You can sign up from the connect button.

= Does it change my site without asking? =

**No.** It only writes changes you approved one by one in MonoRanks. It cannot edit your theme, settings or users.

= Can I undo a change? =

**Yes.** Every change is listed under **MonoRanks → Settings** with its old and new value, and can be undone for 30 days.

= Which SEO plugins does it work with? =

**Yoast SEO, Rank Math, The SEO Framework and All in One SEO.** It also works with no SEO plugin: then MonoRanks prints the approved title, description, canonical and noindex tags itself.

= Can it fix category and tag pages? =

**Yes,** with Yoast SEO or Rank Math: it writes their SEO title and meta description. With no SEO plugin, it writes the category description.

= What is sent, and is my data safe? =

Only published content details and plugin status, over HTTPS, and only after you connect. Never drafts, private posts, comments, user emails or passwords. See **This plugin uses an external service** above.

= My site is local or behind a firewall. Does it work? =

**It connects** with one button, even when MonoRanks cannot reach your site. Approved fixes are sent by MonoRanks over the internet, so writing them needs a site it can reach.

= Does it slow my site down? =

**No.** Content details are sent in the background (WP-Cron, or when MonoRanks asks), so visitors never wait. Approved redirects are stored as one option and checked early in the request.

= How do I disconnect or remove all data? =

Go to **MonoRanks → Settings → Disconnect**, and revoke the "MonoRanks" Application Password under **Users → Profile**. Deleting the plugin removes its settings and scheduled tasks. Deleting the website in MonoRanks removes what was sent there.

= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of the MonoRanks plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/2bfe1074-71dc-42e7-89ac-c10e0df3ed52). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin.

== Screenshots ==

1. The MonoRanks screen: health and AEO scores, search clicks, pages that need attention and fixes ready to apply.
2. Scores for every post in the Posts list. Hover for fixes waiting and open issues.
3. Settings: connection, what is sent, and every change with Undo.
4. Before you connect: the steps to link your site to MonoRanks.

== Changelog ==

= 0.1.24 - 2026-10-23 =
* **New:** Category, tag and product category pages are sent to MonoRanks with their SEO title and meta description.
* **New:** MonoRanks can write and undo the SEO title and meta description of these pages in Rank Math and Yoast SEO. Without an SEO plugin, it writes the category description.
* **Enhancement:** Edited or deleted categories and tags are rechecked in MonoRanks within minutes.

= 0.1.23 - 2026-10-17 =
* **Enhancement:** Sites MonoRanks cannot reach, such as local or firewalled sites, now connect with one button too: the key comes back by itself, nothing to copy.
* **Enhancement:** Simpler Settings and Overview screens: short rows, with details in (i) tips.
* **Enhancement:** "What is sent" opens on a click.

= 0.1.22 - 2026-10-16 =
* **New:** Connect to MonoRanks with one button: sign in or sign up, approve in WordPress, done. The settings page shows "Connected" by itself.
* **Enhancement:** The WordPress approve screen shows the MonoRanks logo and what it can do.
* **Enhancement:** Shorter connect steps on the Overview and Settings screens.

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
