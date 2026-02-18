# Release Notes for Content Freeze

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
