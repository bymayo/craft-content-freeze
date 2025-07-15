<?php

return [

    // Enable a content freeze
    'enabled' => false,

    // Date from which the content freeze is active
    'dateFrom' => null,

    // Date to which the content freeze is active
    'dateTo' => null,

    // Show the notice pane once users login
    'showNoticePane' => true,

    // Heading of the notice pane
    'noticePaneHeading' => 'Content Freeze',

    // Text of the notice pane
    'noticePaneText' => 'Editing is currently paused as part of a scheduled content freeze. Viewing is still available, but changes can’t be made until the freeze is lifted.',

    // Show the notice bar at the top of the CMS once users login
    'showNoticeBar' => true,

    // Text of the notice bar
    'noticeBarText' => 'Editing is currently paused as part of a scheduled content freeze. Viewing is still available, but changes can’t be made until the freeze is lifted.',

    // User groups to move users to when the content freeze is active. The key is the user group id.
    'userGroups' => [
        '1' => [
            'enabled' => false,
            'contentFreezeGroup' => null
        ]
    ]

];