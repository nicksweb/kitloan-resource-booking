# Changelog

All notable changes to Kitloan are documented here. Versions follow [Semantic Versioning](https://semver.org/):
breaking changes bump the major version and are always called out explicitly, since they need extra care on
upgrade (see [Updating an existing instance](README.md#updating-an-existing-instance)).

## [1.0.0] - 2026-08-19

Initial public release.

### Added

- Resource Pools with `individual` (discrete tracked items) and `quantity` allocation modes.
- Booking workflow with conflict detection, preparation/return buffers, and row-locked concurrent-safe
  allocation.
- Approval logic with hard/soft constraints and auto-approval lead-time thresholds.
- OIDC authentication (generic, tested against Microsoft Entra ID) with local-account matching and
  auto-provisioning by allowed email domain.
- Emergency break-glass local admin login (off by default).
- Admin impersonation with full audit trail.
- Optional Snipe-IT integration: asset import, scheduled sync, outage-tolerant local snapshot.
- Email notifications (booking, approval request, daily summary) via queued jobs.
- IT dashboard and logistics day view; List view for all bookings.
- Docker Compose deployment: Nginx + PHP-FPM, PostgreSQL (MySQL/MariaDB also supported), queue and scheduler
  workers.
