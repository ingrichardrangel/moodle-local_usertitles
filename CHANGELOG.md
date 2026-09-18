# Changelog

All notable changes to User titles are documented in this file.

## 1.0.1 - 2026-09-18

### Fixed

- Add the package-level GNU GPL v3 `LICENSE` file requested during Moodle Plugins review.
- Use direct language-string assignments without concatenation in `lang/en/local_usertitles.php`.
- Add the required Moodle module copyright and license metadata to the AMD source file.
- Restrict the AJAX title lookup to target users whose Moodle profiles the current caller is authorized to view.
- Add PHPUnit coverage for unauthorized cross-user lookups and access to the current user's own title.

## 1.0.0 - 2026-07-31

### Added

- Stable release of User titles.
- Institutional title catalogue and title assignment management.
- Configurable user self-selection.
- Global visual title display without modifying Moodle's stored user names.
- Compatibility with Moodle 4.5 through 5.2.
- Privacy API, capabilities, audit events, PHPUnit tests, and CI coverage.

## 1.0.0-beta.7 - 2026-07-31

### Fixed

- Use the canonical Moodle GPL boilerplate spacing in all PHP and JavaScript
  source files.

## 1.0.0-beta.6 - 2026-07-31

### Fixed

- Resolve Moodle CodeSniffer formatting violations in capability, form, event,
  and privacy provider files.
- Document the JavaScript initialisation parameter required by Moodle ESLint.

## 1.0.0-beta.5 - 2026-07-30

### Fixed

- Align all PHP file headers with Moodle's canonical GPL boilerplate.
- Remove redundant internal-access checks from function-only callback files.
- Sort English language keys according to Moodle coding standards.
- Declare PHPUnit coverage metadata for all test classes.

## 1.0.0-beta.4 - 2026-07-30

### Fixed

- Add titles only when a profile link's visible text matches the user's Moodle
  full name.
- Preserve profile images before the visual title in participant lists.
- Exclude navigation labels, course profile names, and generic profile links
  such as "Profile" and "View more".

## 1.0.0-beta.3 - 2026-07-30

### Added

- Global visual title display for standard Moodle user profile links.
- Dynamic page support for participant lists and other AJAX-updated content.
- Read-only AJAX service for retrieving visible title abbreviations.

### Changed

- Titles are now applied only in the browser presentation layer by default.
- Alternate name synchronization is disabled and safely cleaned up during the
  upgrade so exports and stored names remain unchanged.

## 1.0.0-beta.2 - 2026-07-30

### Fixed

- Load Moodle's administration library before using administration page helpers.
- Load the plugin callback library before checking title-editing permissions on
  the direct assignment page.

## 1.0.0-beta.1 - 2026-07-30

### Added

- Institutional title catalogue.
- Initial `Professor` / `Prof.` example.
- Administrative title creation, editing, activation, ordering, and deletion.
- Per-user title assignments.
- Configurable user self-selection.
- Moodle capabilities for each management scope.
- User profile integration.
- Public name formatting API.
- Conflict-safe Alternate name synchronization and cleanup.
- Privacy API implementation.
- Audit events.
- PHPUnit coverage for title assignment, formatting, and synchronization.
