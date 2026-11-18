# Listener Notifications 1.1.1

Editable WordPress plugin source lives in `src/ian-raymond-notifications`. Build the installable ZIP with `python3 Notifications/tools/build.py` from the repository root. Upload `Notifications/release/ian-raymond-notifications.zip` through Plugins → Add New → Upload Plugin and replace the installed plugin. Version 2 of the database schema upgrades automatically and preserves subscribers, announcements, incoming replies, and queued messages. Back up the WordPress database before a production upgrade.

## Two-way texts

Open **Listener Updates → Replies → Open conversation / Reply**. Incoming SMS and recent outbound texts appear together. Write a reply and click **Send reply**; the durable background queue sends it from the number that received the listener’s text. For older incoming messages whose receiving number was not stored, replies use the configured sender. Email replies remain read-only.

Forms require administrator access and nonces. A unique submission key prevents double-clicks or POST retries from adding duplicate replies. The composer preserves a draft when validation fails. STOP blocks composing and sending personal replies, including replies already queued; START permits personal replies again without silently restoring announcement subscriptions. Twilio also enforces its suppression rules. Webhooks verify signatures and account identity, deduplicate incoming Message SIDs, and return an error if storage fails so the provider can retry.

Queued, failed, and accepted-by-Twilio states appear in the conversation. “Accepted” is not delivery confirmation. Refresh the page for new messages and status. Each conversation shows the latest 100 messages; completed outbound rows expire after 90 days. Incoming messages are paginated in Replies. Network timeouts retain the existing conservative retry policy; as before, a timeout before a provider ID is received has a small duplicate-delivery risk.

## Provider configuration

In **Listener Updates → Settings**, supply the existing account values; do not commit credentials to this repository. Secrets can instead be defined as `IRN_TWILIO_TOKEN` and `IRN_SENDGRID_KEY` in server-side `wp-config.php`. Configuration constants take precedence over saved form settings. Blank secret fields preserve saved values.

For SMS, set the Twilio Account SID, that account’s Auth Token, and an SMS-capable Twilio number in E.164 format (or a Messaging Service SID). In the Twilio console, configure **A message comes in → Webhook → HTTP POST** to the exact URL displayed in plugin settings (normally `https://ianraymond.live/wp-json/irn/v1/twilio`). Avoid URL redirects. For a Messaging Service, route incoming messages to this webhook or defer to the number’s webhook. Replies use the actual receiving number so a service’s sender pool does not change the conversation number. Ensure that number belongs to the configured account. See [Twilio messaging webhooks](https://www.twilio.com/docs/usage/webhooks/messaging-webhooks) and [Advanced Opt-Out](https://help.twilio.com/articles/360034798533-Getting-Started-with-Advanced-Opt-Out-for-Messaging-Services).

For email, configure a SendGrid Mail Send API key, verified From address/name, three active Dynamic Template IDs (welcome, new music, new show), and the unsubscribe group. Templates receive the values defined in `includes/signup.php` and `includes/announcements.php`; include the SendGrid unsubscribe link in each template. Configure the reply subdomain’s Inbound Parse destination to the private URL displayed in Settings and use a reply-to address on that subdomain. These are external account/DNS settings; filling the plugin form alone does not configure them.

Settings show missing SMS/email fields and malformed SMS account/sender values. These local checks do not authenticate the account or confirm webhook routing, sender approval, template activation, or DNS. Correct provider errors and explicitly choose **Resume sending** if the queue is paused; waiting messages will then be sent. WordPress cron must be functional for delivery.

## Verification

Run `php Notifications/tools/tests/messaging_test.php` with PHP’s SQLite3 extension installed. The isolated WordPress/database adapter covers authenticated inbound texts, forged requests, duplicate webhooks, storage failure, permissions, validation, durable and idempotent reply queueing, STOP/START, paused account recovery, original sender routing, and provider response classification. No real messages are sent.

Before calling the live setup complete, install the upgrade, verify schema migration and existing data in WordPress, configure the real provider accounts/webhooks, and test a full inbound/outbound conversation using a user-authorized trial-approved test number. Verify signup/welcome and approved music/show announcements on the live site, including the player release hook. Local tests do not establish production delivery or provider configuration.

## Live configuration — October 4, 2026

Version 1.1.0 is installed on ianraymond.live. Twilio credentials and sender +17372583742 are saved in WordPress; Twilio confirmed the incoming POST webhook. An unsigned public webhook request correctly returns 403. The existing queue remains paused. At the last console check, Twilio still showed “Step 3: Confirm and Pay,” with a $20 starting balance and auto-recharge enabled in the order summary. The owner must finish this payment step before outbound SMS is enabled. Until the account dashboard confirms the upgrade, the queue remains paused. Twilio trial accounts restrict custom outbound SMS bodies; see [current trial restrictions](https://www.twilio.com/docs/usage/trials). Do not resume the waiting welcome/announcement queue as a way to send a single test reply. SendGrid configuration and real two-way SMS verification remain incomplete. See `release/live-configuration-20261004.json`.


Twilio was upgraded after the first account check. The active console now has a $20 balance but an empty phone-number inventory. Number search requires a primary compliance profile; available SMS-capable local numbers show $1.15 per month. U.S. local-number application messaging also requires approved A2P 10DLC brand and campaign registration. No number has been purchased and no A2P application submitted. For an eligible sole proprietor, Twilio lists $4.50 one-time brand registration, $15 campaign vetting, and $2/month campaign registration; Twilio also charges carrier messaging fees. See [current A2P requirements](https://www.twilio.com/docs/messaging/compliance/a2p-10dlc) and [Twilio fee schedule](https://help.twilio.com/articles/1260803965530-What-pricing-and-fees-are-associated-with-the-A2P-10DLC-service). The old trial sender is no longer valid.
 Twilio’s Individual profile flow is open in the account browser and has paused at its Persona identity verification step, which requires your biometric-processing consent. Complete that verification in Twilio before setup can continue.


## Public SMS signup — October 4, 2026

Version 1.1.1 adds `[irn_signup]` and publishes `/text-updates/`. This server-rendered form shows the unchecked SMS consent checkbox and policy links immediately, including for visitors who previously dismissed the popup. It submits through the existing signup endpoint and records the same consent evidence. The delayed popup is suppressed on this form page. Existing policy pages are preserved during upgrades; the live SMS terms were separately updated to clarify that START unblocks messages and another signup restores announcements.

A2P registration uses `https://ianraymond.live/text-updates/` for public consent review, `https://ianraymond.live/privacy-policy/` for Privacy Policy, and `https://ianraymond.live/sms-terms/` for Terms & Conditions. The campaign samples demonstrate album, song, and show promotions. A successful automated precheck is not carrier approval.

Twilio accepted the revised campaign submission on October 4 and displayed “Your A2P Campaign registration is being reviewed.” Approval is pending. The sending queue remains paused. See `release/a2p-resubmission-20261004.json`.
