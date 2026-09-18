# User titles

User titles is a local plugin for Moodle LMS that lets an institution maintain
its own catalogue of name prefixes and assign one title to each user.

The plugin installs with one example:

- Name: `Professor`
- Abbreviation: `Prof.`

Institutions can edit or remove that example and create the titles that fit
their own policies and culture.

## Features

- Institution-managed title catalogue.
- Add, edit, enable, disable, order, and delete titles.
- One optional title assignment per Moodle user.
- Configurable self-selection from active titles.
- Separate capabilities for catalogue management, assignment, and
  self-selection.
- Global visual display on standard Moodle user profile links.
- Dynamic support for participant lists and AJAX-updated page content.
- Public formatting API for integrations.
- Privacy API implementation for export and deletion.
- Audit events for catalogue and assignment changes.

The visual display layer never changes Moodle usernames, first names, surnames,
alternate names, grade data, report data, or exported files.
The AJAX lookup used by the visual layer also applies Moodle profile-visibility checks per requested user before returning title data.

## Requirements

- Moodle 4.5 through Moodle 5.2.
- A database and PHP version supported by the installed Moodle release.

## Installation

1. Copy the plugin directory to:

   ```text
   local/usertitles
   ```

2. Visit **Site administration > Notifications** and complete the upgrade.
3. Open **Site administration > Users > Accounts > Manage user titles**.

The installation creates only the `Prof.` example.

## Configuration

Open:

```text
Site administration
  > Users
    > Accounts
      > User title settings
```

### Allow users to select their own title

This setting is disabled by default.

When enabled, users with the `local/usertitles:selectowntitle` capability can
choose an active title from their user settings. They cannot create values or
enter free text.

### Display titles throughout Moodle pages

This setting is enabled by default.

When enabled, the plugin adds assigned abbreviations in the browser to standard
Moodle links that point to user profiles. This includes common locations such
as:

- course participant lists;
- forum authors;
- user profiles and user settings;
- activity pages that use standard Moodle profile links; and
- content inserted dynamically after the page loads.

This behavior changes only the rendered page. It does not change the user
record returned by Moodle APIs or the values used to build CSV, spreadsheet,
gradebook, or report exports.

Third-party components that render a name without a standard user profile link
cannot be detected safely by the visual layer. Those components should call the
plugin integration API explicitly.

## Permissions

| Capability | Purpose | Default archetype |
| --- | --- | --- |
| `local/usertitles:managetitles` | Manage the institutional catalogue | Manager |
| `local/usertitles:assignusertitles` | Assign a title to another user | Manager |
| `local/usertitles:selectowntitle` | Select a title for the current user | Authenticated user |

The self-selection setting must also be enabled before the last capability
takes effect.

## Assigning titles

Managers can open a user's profile or user settings and select **User title**.
When self-selection is enabled, users can open the same page for their own
account.

Disabling a title prevents new assignments. Existing assignments remain until
they are changed or removed.

Deleting a title displays the number of affected users and requires
confirmation. Confirming the deletion removes its assignments.

## Integration API

Other plugins can use the namespaced API:

```php
$displayname = \local_usertitles\manager::format_name($user);
$legalname = \local_usertitles\manager::format_name($user, false);
```

A compatibility function is also available:

```php
$displayname = local_usertitles_format_name($user);
```

The methods return plain text. The calling component remains responsible for
escaping output for its target format.

This API is opt-in. Moodle exports continue to use their original name data
unless an export plugin deliberately calls this formatting method with
`$includetitle` set to `true`.

## Certificates

This version does not modify certificate activities or templates.

Certificate integrations should explicitly choose between:

```php
\local_usertitles\manager::format_name($user, true);  // Include the title.
\local_usertitles\manager::format_name($user, false); // Exclude the title.
```

An optional Custom certificate element is planned as a separate companion
plugin so that certificate templates can opt in to titles without making
`mod_customcert` a dependency of this plugin.

## Privacy

The plugin stores:

- the user id;
- the assigned title id;
- a legacy synchronization tracking field retained for safe upgrades; and
- assignment creation and modification times.

The Moodle Privacy API can export and delete this data.

## License

Copyright 2026 Richard Rangel.

This plugin is licensed under the GNU General Public License, version 3 or
later.
