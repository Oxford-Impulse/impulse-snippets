# Task: Demo video / GIF for WP.org listing and GitHub README

Status: READY TO RECORD — Omer records (Claude can't capture screen video);
script + shot list below. Target: one ~60-second video (or three short GIFs).

## Tooling (free)

- **Video**: OBS Studio (obsproject.com) — Display Capture source, 1920×1080,
  30fps. Or Windows 11's built-in Snipping Tool → Record button for quick takes.
- **GIF**: ScreenToGif (screentogif.com) — record a region, trims + optimizes,
  exports directly. WP.org doesn't embed video in listings, so GIFs double as
  animated "screenshots" on GitHub; WP.org itself only takes static PNGs, but
  the video can be linked from the readme description (YouTube unlisted).
- Record against the Playground site (clean, no client data on screen).
  Browser at 100% zoom, bookmarks bar hidden, 1280px+ viewport.

## Shot list (~60s total)

1. **(0–10s) Create a snippet.** Snippets → Add New: type a name, paste a
   two-line JS snippet, pick "Head", Publish. Cut on the success notice.
2. **(10–20s) Toggle + priority.** The snippets list: flip the on/off toggle
   (no reload), point at the Priority column, drag nothing — just the toggle.
3. **(20–35s) One-click integration.** Integrations page: paste a GA4 ID,
   click Connect, show the green "Connected" state and the consent-mode nudge.
4. **(35–50s) Form lead tracking (the differentiator).** Open the Conversion
   tracking panel → Form lead tracking → pick the form, type a label, tick
   hashed email, Track this form → cut to the contact page, submit the form,
   show the console `dataLayer` with generate_lead + conversion appearing.
   (Pre-type `dataLayer` so the take is smooth; ad blocker OFF for the take.)
5. **(50–60s) Check my setup.** Click "Check my setup ↗", show the overlay's
   green ✓ rows. End card: plugin name + "wordpress.org/plugins/impulse-snippets".

## Notes

- Keep the mouse slow; every click should be readable at a glance.
- No audio needed — captions in the listing/readme do the work, and it avoids
  re-recording for translations.
- After WP.org approval, upload to YouTube (unlisted is fine) and link it in
  readme.txt's description section; put the GIFs in the GitHub README.
