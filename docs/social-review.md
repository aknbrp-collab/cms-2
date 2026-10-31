# Social media: app setup and review checklist

The CMS publishes to Facebook Pages, Instagram Business accounts and TikTok through the official APIs. Until the apps are approved, run with `SOCIAL_DRIVER=fake`: every screen works, and connecting and publishing are simulated (a caption containing `#gagal` simulates a failure).

Going live:
1. Create the Meta and TikTok apps (below).
2. Put the keys in `.env`.
3. Set `SOCIAL_DRIVER=live`.
4. Pass the reviews.

## URLs the reviewers need

Replace `admin.pondok.example` with your `ADMIN_DOMAIN`. All of them must be HTTPS in production.

| Purpose | URL |
|---|---|
| Privacy policy | `https://admin.pondok.example/kebijakan-privasi` |
| Terms of service | `https://admin.pondok.example/syarat-ketentuan` |
| Meta OAuth redirect URI | `https://admin.pondok.example/social/callback/meta` |
| TikTok redirect URI | `https://admin.pondok.example/social/callback/tiktok` |
| Meta data deletion callback | `https://admin.pondok.example/api/social/meta/data-deletion` |
| Meta deauthorize callback | `https://admin.pondok.example/api/social/meta/deauthorize` |
| App domain / website | `https://admin.pondok.example` |

Before submitting, put the institution's real contact email in the Pondok site settings (it appears on the legal pages).

## Production prerequisites

- **Public media URLs.** Instagram and TikTok download media from a URL, so the media library must live on public storage. Use `MEDIA_DISK=s3` (or another S3-compatible store) with a public bucket or CDN.
- **TikTok URL ownership.** `PULL_FROM_URL` only accepts verified domains. In the TikTok developer portal, verify the media domain (S3/CDN bucket domain) under *URL properties*.
- **Workers.** Run the queue (Horizon) and the scheduler (`schedule:run` every minute). `social:dispatch-due` runs every minute and `social:refresh-tokens` every hour.

---

## Meta (Facebook Pages + Instagram)

### 1. Create the app

1. Go to <https://developers.facebook.com/apps> → **Create app** → use case *Other* → type **Business**. Attach it to the institution's Business Portfolio.
2. **Add products**: *Facebook Login for Business*. The Instagram API with Facebook Login comes with the permissions below.
3. **App settings → Basic**: add the app domain, privacy policy URL, terms URL, app icon (1024×1024), category *Education*, and the data deletion **callback URL**.
4. **Facebook Login → Settings**: add the Meta redirect URI and turn on *Enforce HTTPS*. Add the deauthorize callback URL.
5. Copy the App ID and secret to `.env`: `META_APP_ID`, `META_APP_SECRET`.
6. Make sure each Instagram account is a **Professional (Business or Creator) account linked to a Facebook Page**. Only linked accounts appear.

### 2. Permissions requested (keep it minimal)

| Permission | Why the CMS needs it |
|---|---|
| `pages_show_list` | list the Pages the staff member manages so they can be connected |
| `pages_read_engagement` | read the Page name and picture; required alongside `pages_manage_posts` |
| `pages_manage_posts` | publish posts, photos and videos to the institution's Page |
| `business_management` | Pages owned through a Business Portfolio |
| `instagram_basic` | read the linked Instagram account's username and picture |
| `instagram_content_publish` | publish photos, carousels and Reels to Instagram |

Nothing else (no messaging, insights or ads).

### 3. App Review submission

For **each** permission, Meta asks for a description and a screencast. Use this script (record in English or with English captions, at least 720p):

1. Show the login page of the CMS and sign in as a Social Media Manager.
2. Open **Social media → Accounts**, choose the site, click **Facebook**.
3. In the Facebook dialog, select the institution's Page (and Instagram account) and approve the permissions.
4. Back in the CMS, show the connected Page and Instagram account in the list.
5. Open **Social media → Posts → New post**: write a caption, pick an image, select the Page and the Instagram account, click **Publish now**, and confirm.
6. Show the post appearing on the Facebook Page and on Instagram (open the "View post" links).
7. Show **Disconnect** removing the account. Then show the privacy policy and the data deletion section.

Description template (`pages_manage_posts`):

> Our institution (an Islamic boarding school) uses this internal CMS to publish its news and announcements. Staff with the Social Media Manager role connect the school's own Facebook Page and publish or schedule posts they write in the CMS. We only post content the staff create; we do not read or manage comments, messages or insights.

Use similar wording for the other permissions, describing exactly the step in the screencast where each is used.

Also complete **Business Verification** for the portfolio and switch the app to **Live** mode after approval.

---

## TikTok

### 1. Create the app

1. Go to <https://developers.tiktok.com> → **Manage apps** → **Connect an app**. Use the institution's organization account.
2. Fill in the app icon, name, description, category *Education*, terms URL and privacy URL (table above). Platform: **Web**, with the admin domain as website.
3. **Add products**: *Login Kit* (redirect URI as above) and *Content Posting API* (enable **Direct Post**).
4. Scopes: `user.info.basic`, `video.publish`.
5. **URL properties**: verify the domain that serves media (S3/CDN). Required for `PULL_FROM_URL`.
6. Copy the client key and secret to `.env`: `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`.

### 2. Before the audit: what unaudited apps can do

Until TikTok audits the Content Posting API integration, every post is **private** (`SELF_ONLY`) and at most 5 users can post in 24 hours. The composer shows exactly the privacy options TikTok returns, so the restriction is visible. Apply for the audit once the screencast below is ready.

### 3. UX requirements (already built into the composer)

TikTok's Content Sharing Guidelines are checked during the audit. The composer implements them:

- [x] Shows the creator's nickname from `creator_info` before posting ("Posting as …").
- [x] Privacy dropdown filled from `privacy_level_options`, **no default value**. Publishing is blocked until one is chosen.
- [x] Comment / Duet / Stitch checkboxes, **unchecked by default**. They are disabled when the creator turned them off in TikTok. Duet and Stitch appear only for videos.
- [x] Commercial content disclosure switch, **off by default**. When on, "Your brand" and/or "Branded content" must be chosen. Branded content cannot be private (the "Only me" option is disabled).
- [x] Consent text above publishing: Music Usage Confirmation, plus the Branded Content Policy when branded content is selected.
- [x] The user sees the caption and media preview and must click **Publish now** (with confirmation) or **Schedule**. Nothing is posted automatically without that action.
- [x] Tells the user that TikTok processing can take a few minutes. Status is polled (`post/publish/status/fetch`).
- [x] Shows the maximum video duration from `creator_info`.

### 4. Audit screencast script

1. Sign in to the CMS, **Accounts → TikTok**, authorize in TikTok, and show the connected account.
2. **New post**: upload a video, select the TikTok account, and show the nickname, the empty privacy dropdown, the unchecked interaction boxes, the disclosure switch (turn it on and show both options), and the consent text.
3. Choose a privacy level, click **Publish now**, and confirm.
4. Show the status changing to Published and the video on the TikTok profile.
5. Show **Disconnect** and the privacy policy.

---

## After approval

- Set `SOCIAL_DRIVER=live` and restart the queue workers.
- Reconnect any accounts that were created in demo mode (demo accounts are marked "Demo").
- TikTok tokens last 24 hours and are refreshed automatically (refresh token valid for 365 days). Meta Page tokens do not expire unless the user removes the app or changes their password. Accounts that need attention show **Reconnect needed**.
