# Security review — 2026-09-02 (pre-1.6.1)

A full read-through of the application's security-relevant surface. This records what was examined, what
was fixed in **1.6.1**, and what was consciously left alone (with the reasoning), so the next review can
start from here rather than from scratch.

## Fixed in 1.6.1

| # | Finding | Severity | Fix |
| - | ------- | -------- | --- |
| 1 | `bookings.helpdesk_url` validated with a bare `url` rule → accepts `javascript://%0a…` and other non-web schemes → stored XSS when IT / a booked officer clicks the link on the booking page. Any authenticated user can set it on their own booking. | Medium | `url:http,https` on all three entry points (wizard, amend form, inline detail editor) + `Booking::safeHelpdeskUrl()` guard on the two `href` render sites. Markdown emails were already safe (CommonMark `allow_unsafe_links => false`). |
| 2 | `TemplateRenderer::intro()` / `policyNotice()` output is raw-echoed (`{!! !!}`) into a Markdown mail whose CommonMark pass passes HTML through. Free-text tokens (`notes`, `officer`, `room`, …) carry user input, so a booking's notes could land in the email as live markup if an admin references the token in the intro. | Low–Medium | `substitute()` HTML-escapes token values in the intro/policy-notice (HTML) context. Subjects are plain-text headers → left raw. |
| 3 | Site-logo upload accepted `svg`. SVG carries inline `<script>`, is served from this origin, and appears on the unauthenticated login page. | Low (admin-only) | Logo uploads restricted to PNG / JPG / WebP. |
| 4 | App emitted no `X-Content-Type-Options` / `Referrer-Policy` of its own — present in production only because Cloudflare / NPM add them. | Low (hardening) | `FrameEmbedding` middleware sets both when absent (edge proxy still wins). |

## Examined, no change needed

- **AuthN.** OIDC + local break-glass password (min 12 chars, bcrypt via `hashed` cast) + mandatory TOTP
  2FA for password-holding admins. Dual rate-limiting (IP+email short window, email-only long window) plus
  a durable `users.locked_until` hard lock at 10 failures, on both the password and the 2FA-code step.
  `two_factor_secret` is `encrypted`, recovery codes are `encrypted:array` **and** hashed. Session
  regenerated on every successful login.
- **AuthZ.** Gates (`AppServiceProvider`) are role checks; `Gate::before` grants admins everything;
  `BookingPolicy` covers view/update/cancel/approve/reject/reallocate; every `BookingDetail` action method
  re-authorises. Route groups gate `it/*` (`operate-bookings`), `admin/*` (`manage-catalog` /
  `manage-users` / `manage-settings` / `view-audit-log` / `view-reports`). Impersonation is admin-only,
  refuses self / other admins / disabled targets / nested use, and audits both ends.
- **Mass assignment.** `#[Fillable]` on `User` excludes `password`, `two_factor_*`, roles; `#[Hidden]`
  hides the secrets. Models are populated through explicit service arrays, not request bags.
- **Injection.** No `DB::raw` / `whereRaw` / `eval` / `shell_exec` / `unserialize` anywhere in `app/`.
  Search filters use bound Eloquent `like`.
- **CSRF.** Livewire enforces it; the plain `<form>`s (logout, impersonate, reject) carry `@csrf`.
- **Signed URLs.** `bookings.public-view` (guest, read-only), `bookings.approve` / `bookings.reject.show`
  (also `auth` + policy). HMAC over the full URL with `APP_KEY`.
- **SSRF.** The only outbound HTTP is the Snipe-IT client, whose base URL + token are **env-only**, fixed
  at deploy — not settable from the UI.
- **Transport / cookies.** Prod `APP_DEBUG=false`, `APP_ENV=production`. Session cookie
  `secure; httponly; samesite=lax`; embedding promotes it to `SameSite=None; Secure`. `frame-ancestors`
  CSP + conditional `X-Frame-Options` from `FrameEmbedding`.
- **`trustProxies(at: '*')`.** Safe **only** because the app/webserver containers publish no host port
  (confirmed in `docker-compose.yml`) — NPM on the internal Docker network is the sole ingress. If a port
  is ever exposed, this must be narrowed to the proxy's IP or `X-Forwarded-*` becomes spoofable (audit-log
  IPs, client-IP-derived rate-limit keys).
- **Dependencies.** `composer audit` and `npm audit` both clean on the 1.6.1 lockfiles.

## Deliberately not changed (accepted risk / out of scope for a patch release)

- **Booking references are sequential** (`EX-2026-00042`, from the row id). Every reference-taking route
  is policy- or signature-gated, so enumeration buys nothing; the reference is user-facing and printed, so
  changing its shape is a UX/data decision, not a security fix.
- **`approve()` doesn't check the booking is still `pending`.** Approving a previously-rejected booking
  flips the status without re-allocating resources. Minor state-machine gap; no privilege or data-exposure
  impact. Worth tightening in a future functional release.
- **`AllBookings` is visible to every authenticated user** (time / room / requestor name / pool / status —
  no notes, students or emails). This is the intended shared booking calendar for staff, not a leak.
- **CSV export formula injection.** Report / location CSV exports aren't prefixed against
  `= + - @` for spreadsheet apps. Low impact for this data; revisit if exports grow.
