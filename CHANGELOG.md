# Release Notes for Content Freeze

## 2.0.0 - 2026-06-11

> [!WARNING]
> This is a major release that replaces the single global freeze with multiple, schedulable freezes. Your existing freeze configuration is migrated automatically into one freeze record on update. The `CONTENT_FREEZE_ENABLED` env var and the `enabled` / `dateFrom` / `dateTo` / `userGroups` plugin settings have been removed.

### Added
- Multiple freezes, managed from a new Content Freeze section in the CP - each with its own Enabled toggle, optional Date From/To, Description, and user group mapping
- Schedule freezes for future dates; they activate and lift automatically
- Open-ended freezes: leave the start date blank to start immediately, or the end date blank to stay frozen until disabled
- Per-freeze notice (pane/bar) overrides, falling back to the plugin defaults
- Dashboard widget listing active and upcoming freezes
- Back Up Database on Freeze setting - queues a database backup when a freeze becomes active
- Per-freeze Notify Users by Email option - emails affected users (with CP access) when a freeze is scheduled, becomes active and ends, using editable System Messages
- `craft.contentFreeze` front-end Twig variable (`enabled`, `freezes`, `dateRange`) for blocking forms/purchases while frozen
- `php craft content-freeze/run` console command for precise, cron-driven activation
- The Clone button prompts for the new view-only group's name (prefilled with "X (Content Freeze)")
- Clone view-only groups keep view/read/access permissions for Commerce, Freeform, Formie, Comments, SEOmatic and Navigation, plus a `viewOnlyKeepPermissions` config setting for other plugins

### Changed
- The freeze edit screen is split into General, User Groups and Notices tabs, with error indicators on the tab when a field fails validation
- Enabled user-group rows require a "Move Users To" group, with an inline error if one isn't chosen
- Access is controlled by the Access Content Freeze permission (no longer admin-only)
- User moves run via a queue job (no more blocking the request)
- Freezes are stored in the database (not project config)
- Notices reflect the active freeze window via `{dateFrom}` / `{dateTo}`
- A "Move Users To" group can be reused across freezes as long as it's always mapped from the same source group; only a target shared by two *different* source groups is blocked
- The freeze list is cached per request, and user group reassignment fetches current memberships in a single query

### Security
- The "Move Users To" target is restricted to groups the editor is allowed to assign (`assignUserGroup:<uid>`), preventing privilege escalation for non-admins with the Access Content Freeze permission
- Notice bar text is HTML-escaped before rendering in the CP alert, preventing stored XSS

### Fixed
- Deleting or disabling an active freeze moves its users back to their original groups
- A failed user-move job retries instead of assuming the move succeeded
- User moves skip groups that no longer exist instead of erroring

### Removed
- `CONTENT_FREEZE_ENABLED` env var and the single-freeze settings (`enabled`, `dateFrom`, `dateTo`, `userGroups`)

## 1.0.3 - 2026-02-18

### Fixed
- Clone action now uses POST request with CSRF protection
- User group assignments now preserve existing group memberships instead of replacing them
- Notice pane no longer crashes when dates are null
- Notice pane uses correct CP URL instead of hardcoded `/admin` path
- Misleading "License purchase required" title in notice pane

### Changed
- `moveUsers()` no longer runs on every request, only on settings save
- Added type hints and validation rules to Settings model

### Removed
- Debug logging of permission data
- Unused imports and dead code

## 1.0.2 - 2025-07-15

### Fixed
- License

## 1.0.1 - 2025-07-15

### Fixed
- Icon

## 1.0.0 - 2025-07-15
- Initial release
