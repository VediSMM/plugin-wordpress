# VediSMM for WordPress Guide

VediSMM for WordPress lets editors send posts and pages to VediSMM without exposing the API token to the browser.

## Setup

1. Install and activate the plugin.
2. Open Settings -> VediSMM.
3. Paste a VediSMM API token from your VediSMM account.
4. Leave the token field empty on later saves to keep the existing token.

## Editorial Flow

- Create draft sends the current WordPress content to VediSMM and keeps it as a draft.
- Schedule first creates a draft, then schedules that draft for the selected time.
- Publish first creates a draft, then queues an explicit VediSMM publish job.

Saving a WordPress post never publishes automatically.

## Tracking Links

The VediSMM box has **Shorten links** and **Add network source** checkboxes.
Both are off by default. Source attribution is enabled only while shortening is
enabled; turning shortening off also clears and disables the source option.
The plugin sends these values only under `options.tracking`, does not rewrite
the editor's URLs, and stores no generated-link state.

VediSMM creates a separate short link for each target network. With source
attribution enabled, if a non-empty `utm_source` is absent, VediSMM adds
`utm_source=<network>` and preserves an existing `utm_term`. If `utm_source`
exists, VediSMM preserves it and replaces every existing `utm_term` (or adds
one) with exactly one `utm_term=<network>`. Other query parameters, encoded
values, their order, and the fragment remain unchanged.
