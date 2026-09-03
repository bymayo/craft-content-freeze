<?php

return [

    // Show the notice pane once users login during a freeze
    'showNoticePane' => true,

    // Heading of the notice pane
    'noticePaneHeading' => 'Content Freeze',

    // Text of the notice pane
    'noticePaneText' => 'Editing is currently paused as part of a scheduled content freeze, from {dateFrom} until {dateTo}. Viewing is still available, but changes can’t be made until the freeze is lifted.',

    // Show the notice bar at the top of the CMS during a freeze
    'showNoticeBar' => true,

    // Text of the notice bar
    'noticeBarText' => 'Editing is paused as part of a scheduled content freeze, and will resume in {remaining}.',

    // Queue a database backup when a freeze becomes active
    'backupOnFreeze' => false,

    // Extra permissions to preserve when cloning a "view only" group, on top of
    // the built-in support (Craft core, Commerce, Freeform, Formie, Comments,
    // SEOmatic, Navigation). Add other plugins' view/read/access permission
    // handles here (lowercase — find them under Settings > Users > Permissions).
    // For example:
    //
    // 'viewOnlyKeepPermissions' => [
    //     'accessplugin-myplugin',
    //     'myplugin-viewsomething',
    // ],
    'viewOnlyKeepPermissions' => [],

];
