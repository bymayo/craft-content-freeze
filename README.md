<img src="https://github.com/bymayo/craft-content-freeze/blob/craft-5/resources/icon.png" width="60">

# Content Freeze for Craft CMS 5

Content Freeze is a Craft CMS plugin that allows you to freeze adding/editing of content for users within the CMS so you can schedule server transfers, major updates etc without content between environments being lost.

<img src="https://raw.githubusercontent.com/bymayo/craft-content-freeze/craft-5/resources/screenshot.png" width="850">

## Features

- **Multiple freezes** - Create as many as you need, each with its own optional schedule (date from/to)
- **Scheduling** - Freezes activate and lift automatically at their start/end times
- **Live status** - See each freeze's status at a glance, with a "Starts in" / "Ends in" countdown
- **Dashboard widget** - Lists active and upcoming freezes
- **View-only access** - Move users into a view-only group per user group while a freeze is in effect
- **Notice bar** - Show a bar at the top of the CMS while a freeze is active
- **Notice pane** - Show a full-screen notice when users log in during a freeze
- **Custom notices** - Set plugin-wide defaults, and optionally override the bar/pane per freeze
- **Database backups** - Optionally queue a backup when a freeze becomes active
- **Email notifications** - Optionally email affected users when a freeze is scheduled, becomes active and ends
- **Front-end variable** - Block front-end actions while frozen with the `craft.contentFreeze` Twig variable
- **Permissions** - Control access with the Access Content Freeze permission (no longer admin-only)
- **Console commands** - Trigger (enable) or lift (disable) freezes from the command line by ID

## Install

-  Install with Composer via `composer require bymayo/content-freeze` from your project directory
-  Enable / Install the plugin in the Craft Control Panel under `Settings > Plugins`
-  Follow the [Setup](#setup) instructions below

You can also install the plugin via the Plugin Store in the Craft Admin CP by searching for `Content Freeze`.

## Requirements

- Craft CMS 5.x
- PHP 8.2
- MySQL (No PostgreSQL support)

## Setup

This plugin works by moving users into a view-only user group while a freeze is in effect (a group with limited permissions - typically "View" or "Manage" only).

1. Ensure you have at least one user group, and that your content editing users are sorted into groups.
2. Go to Content Freeze in the CP nav and create a freeze with New freeze.
3. On the freeze, use the "Clone" button to create a view-only copy of a group (same permissions, view/manage only). You'll be prompted to name the new group (prefilled with "X (Content Freeze)").
4. For each user group, set Move Users To and toggle Enabled (skip groups that don't need CMS access, e.g. "Customers"). A "Move Users To" group can be reused across freezes as long as it's always paired with the same source group - two *different* groups can't share one target, so users restore correctly when the freeze lifts.
5. Set the freeze's own Enabled toggle, and optionally a Date From / Date To:
   - No start date = active as soon as it's enabled.
   - No end date = stays in effect until you disable it.
6. Optionally, under Notices on the freeze, customise the notice bar/pane for this freeze - leave a field blank to fall back to the plugin default.
7. While a freeze is in effect you'll see a notice pane/bar in the CMS (if those settings are enabled).
8. When the freeze ends (or you disable it) users are moved back to their original group.

### Permissions

Access to the Content Freeze section is controlled by the Access Content Freeze permission (under Settings → Users → [group/user] → Permissions). Admins always have access.

Two actions require additional Craft permissions, so a non-admin can't use the plugin to grant other users access they don't already control:

- Cloning a view-only group requires the Manage user groups permission.
- Choosing a Move Users To target requires permission to assign users to that group - only groups you're allowed to assign appear in the dropdown. (Moving users into a group grants them that group's permissions, so this prevents privilege escalation.)

### Scheduling (cron)

Freezes are applied on every CP request, but for scheduled freezes to activate/lift precisely at their start/end times - even when nobody is in the control panel - run the console command on a schedule:

```
* * * * * php craft content-freeze/run
* * * * * php craft queue/run
```

The `queue/run` line ensures the queued user moves actually execute (Craft also runs the queue automatically on web requests).

## Console commands

```
# Reconcile the freeze state for the current time (use on a cron - see above)
php craft content-freeze/run

# List every freeze with its id, status and window
php craft content-freeze/freezes/list

# Trigger (enable) a freeze by id, applied immediately
php craft content-freeze/freezes/enable <id>

# Lift (disable) a freeze by id, applied immediately
php craft content-freeze/freezes/disable <id>
```

Enabling a freeze respects its schedule - if it has a future Date From, it won't become active until then.

## Notices

The notice bar and pane defaults are configured under Settings → Plugins → Content Freeze (or via `config.php`). Each freeze can override the bar/pane under its Notices section - any field left blank falls back to the plugin default.

When several freezes are active at once, the earliest one's notices are shown. The `{dateFrom}` / `{dateTo}` placeholders work in both the notice pane text and the notice bar text, and reflect that freeze's window.

## Backups

Enable Back Up Database on Freeze (under Settings → Plugins → Content Freeze, or `backupOnFreeze` in `config.php`) to queue a database backup whenever a freeze becomes active. Backups run via the queue and are saved to Craft's `storage/backups` folder. This is a database backup only (not files/assets), and respects Craft's `backupCommand` general config setting.

## Email notifications

Turn on Notify Users by Email on a freeze to email the affected users (members of that freeze's frozen groups who have control-panel access) at each stage:

- Scheduled - when a future-dated freeze is saved/enabled
- Active - when the freeze starts
- Ended - when the freeze lifts

The email subjects and bodies are editable under Utilities → System Messages (the three "content freeze" messages), with `{{ name }}`, `{{ description }}`, `{{ dateFrom }}`, `{{ dateTo }}` and `{{ user }}` available. Emails are sent via the queue.

As with scheduling, the active/ended emails fire exactly on the freeze's start/end only if `content-freeze/run` is on cron (see [Scheduling](#scheduling-cron)); otherwise they're sent on the next control-panel request after the boundary.

## Front-end templating

A `craft.contentFreeze` Twig variable is available in your front-end templates, so you can react to a freeze being in effect - for example hiding a form, disabling add-to-cart, or showing a message. It's time-based, so it's accurate without any control-panel activity.

Is a freeze currently in effect?

```twig
{% if craft.contentFreeze.enabled %}
    <p>Editing is paused while a content freeze is in effect.</p>
{% endif %}
```

`craft.contentFreeze.active` is an alias of `enabled`, if you prefer it.

Hide a form / block an action while frozen:

```twig
{% if not craft.contentFreeze.enabled %}
    <form method="post">
        {# ...contact form, comment form, etc. #}
    </form>
{% else %}
    <p>Submissions are temporarily paused.</p>
{% endif %}
```

Stop purchases (Commerce):

```twig
{% if craft.contentFreeze.enabled %}
    <button disabled>Checkout unavailable during freeze</button>
{% else %}
    <a href="{{ cart.getCheckoutUrl() }}">Checkout</a>
{% endif %}
```

Show the freeze window / details:

```twig
{% set range = craft.contentFreeze.dateRange %}
{% if craft.contentFreeze.enabled and range.to %}
    <p>Editing resumes {{ range.to|datetime('short') }}.</p>
{% endif %}

{# Loop the active freezes for names/dates #}
{% for freeze in craft.contentFreeze.freezes %}
    <li>{{ freeze.name }}{% if freeze.dateTo %} - until {{ freeze.dateTo|datetime('short') }}{% endif %}</li>
{% endfor %}
```

Available on `craft.contentFreeze`:

| Property/method | Returns | Notes |
|---|---|---|
| `enabled` | `bool` | True if any freeze is in effect right now |
| `active` | `bool` | Alias of `enabled` |
| `freezes` | `Freeze[]` | The freezes currently in effect |
| `dateRange` | `{from, to}` | Combined start/end across active freezes (either may be `null`) |

## Config

You can configure the default notices and the backup-on-freeze option via the plugin settings in the Craft Control Panel, or via the `config.php` file. Freezes themselves (including any per-freeze notice overrides) are managed under the Content Freeze section (stored in the database, not project config).

## Supported Plugins

When you use Clone to create a view-only group, Content Freeze keeps Craft's core `view*` permissions plus the view/read/access permissions for these plugins, so frozen users can still *view* (but not edit) their content. Editing/manage/create/delete permissions are intentionally dropped.

- Craft Commerce - keeps order access (`accessplugin-commerce`, `commerce-manageorders`)
- Solspace Freeform - keeps forms/submissions access and submission read (`freeform-formsaccess`, `freeform-submissionsaccess`, `freeform-submissionsread`, `freeform-notificationsaccess`)
- Verbb Formie - keeps forms/submissions access (`formie-accessforms`, `formie-accesssubmissions`, `formie-accesssentnotifications`)
- Verbb Comments - keeps access to the comments index; moderation (edit/trash/delete) is dropped
- nystudio107 SEOmatic - keeps the read-only dashboard; the editable meta sections are dropped, so SEO can't be changed while frozen
- Verbb Navigation - keeps section access only. Navigation has no view-only permission, so individual navs are hidden while frozen

### Adding support for other plugins

Any plugin's view/read/access permissions can be preserved by adding their handles to the `viewOnlyKeepPermissions` array in `config/content-freeze.php` (lowercase). You can find the exact handles under Settings → Users → [group] → Permissions. For example:

```php
'viewOnlyKeepPermissions' => [
    'accessplugin-myplugin',
    'myplugin-viewsomething',
],
```

## Caveats

1. This plugin does not move admin users. The reason for this is because it can be too risky to block admin users from the CMS. So it's important that all users who can edit content are sorted in to groups - Not just marked as "Admin".
2. Member groups are required to make this plugin function. 

## Support

If you have any issues (Surely not!) then I'll aim to reply to these as soon as possible. If it's a site-breaking-oh-no-what-has-happened moment, then hit me up on the Craft CMS Discord - @bymayo