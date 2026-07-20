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
