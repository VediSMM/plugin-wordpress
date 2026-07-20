=== VediSMM ===
Contributors: vedismm
Tags: social media, publishing, scheduling, autoposting, vedismm
Requires at least: 7.0
Tested up to: 7.0.2
Requires PHP: 8.1
Stable tag: 0.1.0-dev
License: MIT
License URI: https://opensource.org/license/mit/

Send WordPress posts and pages to VediSMM as explicit drafts, scheduled posts, or publish jobs.

== Description ==

VediSMM connects WordPress editorial content to your VediSMM account. The plugin maps post title, content, permalink, selected account IDs, group IDs, and media IDs into the shared VediSMM DraftInput contract.

The default action creates a VediSMM draft. Scheduling and publishing are explicit editor actions and never happen automatically when a WordPress post is saved.

This plugin sends content to the external VediSMM API at https://vedismm.ru/api/v1 and requires a VediSMM account and API token. The token is stored only in WordPress server-side options, is never rendered back into the admin form, and is redacted from errors.

== Installation ==

1. Upload the `vedismm` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Open Settings -> VediSMM and paste a VediSMM API token.
4. Open a post or page and use the VediSMM box to create a draft, schedule, or publish.

== Frequently Asked Questions ==

= Does it publish automatically? =

No. Saving a WordPress post does not publish to VediSMM. Editors must choose a VediSMM action explicitly.

= Where is the API token stored? =

The token is stored server-side in WordPress options and is not rendered back into the settings field.

== Changelog ==

= 0.1.0-dev =

Initial development release package.
