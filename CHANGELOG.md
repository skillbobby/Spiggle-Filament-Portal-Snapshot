# Changelog

All notable changes to `spiggle/filament-portal-snapshot` will be documented in this file.

## 1.1.0 - 2026-10-18

### Added

- Stylesheet for the snapshots page, so the guide cards, storage summary and activity list render as a finished panel
- Snapshot settings page for the storage disk, local directory, remote copies, notifications, restore safety and queue
- Settings are saved in the database and applied on boot, with config and environment values as the defaults

### Fixed

- Notification titles that printed literal `\u201c` escape sequences

## 1.0.0 - 2026-09-28

### Added

- Filament 4 and 5 panel plugin for database snapshots
- Create, restore, delete, download and copy snapshots to remote disks
- Scheduled create / restore / cleanup with cron or presets (including midnight demo refresh)
- Golden snapshot marking for demo-site resets
- Filament database notifications plus queued email, failing silently when mail is not configured
- Operation history and stats widget
- Production restore guard and confirmation phrase
- Optional preservation of plugin tables across restores
- Install command and shop-ready documentation
