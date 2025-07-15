<img src="https://github.com/bymayo/craft-content-freeze/blob/craft-5/resources/icon.png" width="60">

# Content Freeze for Craft CMS 5

Content Freeze is a Craft CMS plugin that allows you to freeze adding/editing of content for users within the CMS so you can schedule server transfers, major updates etc without content between environments being lost.

<img src="https://raw.githubusercontent.com/bymayo/craft-content-freeze/craft-5/resources/screenshot.png" width="850">

## Features

- Set a date range for the content freeze
- Move users to a new group when the content freeze is active
- Show a notice bar at the top of the CMS once users login
- Show a notice pane in the CMS once users login

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

This plugin works by moving users to a new user group when the content freeze is active which has limited permissions (Particularly "View" or "Manage" permissions only).

1. Ensure you have at least one user group, and that your content editing users are sorted in to groups.
2. In the plugin settings you can use the "Clone" button to create a new user group with the same permissions as the original group, but with "View" or "Manage" permissions only.
3. Choose a user group to move users to when the content freeze is active - Per user group.
4. You can then enable the freeze per member group (This is particularly useful if you have member groups that don't have CMS access, e.g. "Customers" which don't need permissions changing)
5. Once you want to freeze content hit "Enabled" or edit this via the `config.php` file or via a `env` var.
6. You should now see a pane/notice bar in the CMS (If these settings are enabled)
7. Once you disable the freeze, users will be moved back to their original group.

## Config

You can configure the plugin via the plugin settings in the Craft Control Panel.

You can also configure the plugin via the `config.php` file or using `env` vars within the `config.php` file.

## Supported Plugins

- Craft Commerce
- (More to come)

## Caveats

1. This plugin does not move admin users. The reason for this is because it can be too risky to block admin users from the CMS. So it's important that all users who can edit content are sorted in to groups - Not just marked as "Admin".
2. Member groups are required to make this plugin function. 
3. Large amounts of users could potentially cause performance issues (See roadmap)

## Support

If you have any issues (Surely not!) then I'll aim to reply to these as soon as possible. If it's a site-breaking-oh-no-what-has-happened moment, then hit me up on the Craft CMS Discord - @bymayo

## Roadmap

- Widget to enable a content freeze from the dashboard
- Move users via a queue job to fix performance issues with large amounts of users
- Add a permission for accessing the content freeze settings
- Block front end option/variable e.g. `{% if craft.contentFreeze.enabled %}` useful for hiding forms, or stopping purchases etc
- CLI command to start freeze
- More plugin support - Freeform, Formie etc
- Hide cloned groups in User Groups settings if there are a lot of groups