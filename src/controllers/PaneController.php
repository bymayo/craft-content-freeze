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
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * content-freeze/pane action
     */
    public function actionContentFreeze(): Response
    {

        $notice = Plugin::getInstance()->freezes->getEffectiveNotice();

        // No freeze in effect: nothing to show, send them on to the CP.
        if ($notice === null) {
            return $this->redirect('');
        }

        return $this->renderTemplate('content-freeze/_noticePane', [
            'heading' => $notice['noticePaneHeading'],
            'text' => $notice['noticePaneText'],
            'dateFrom' => $notice['dateFrom'],
            'dateTo' => $notice['dateTo'],
        ]);

    }
}
