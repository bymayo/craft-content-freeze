<?php

namespace bymayo\craftcontentfreeze\controllers;

use bymayo\craftcontentfreeze\Plugin;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Pane controller
 */
class PaneController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * content-freeze/pane action
     */
    public function actionContentFreeze(): Response
    {

        $settings = Plugin::getInstance()->settings;

        return $this->renderTemplate('content-freeze/_noticePane', [
            'settings' => $settings,
        ]);

    }
}
