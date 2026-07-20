# VediSMM for WordPress

Send WordPress posts and pages to VediSMM as drafts, scheduled posts, or explicit publish jobs.

The plugin maps WordPress title, content, permalink, selected VediSMM accounts, groups, and media references into the VediSMM DraftInput contract. It requires a VediSMM account and sends server-side requests to `https://vedismm.ru/api/v1`.

Highlights:

- Default action creates a draft only.
- Scheduling and publishing are explicit editor actions.
- API token is stored server-side and never rendered back to the browser.
- Stable idempotency keys prevent duplicate submissions.
- English and Russian documentation included.
