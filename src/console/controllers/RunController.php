<?php

namespace bymayo\craftcontentfreeze\console\controllers;

use bymayo\craftcontentfreeze\Plugin;

use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Applies the correct content freeze state for the current time.
 *
 * Intended to be run on a schedule (e.g. every minute via cron) so freezes
 * activate and deactivate precisely at their start/end:
 *
 *     * * * * * php craft content-freeze/run
 */
class RunController extends Controller
{
    /**
     * @var bool Re-apply the freeze state even if it hasn't changed.
     */
    public bool $force = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['force']);
    }

    public function actionIndex(): int
    {
        Plugin::getInstance()->freezes->reconcile($this->force);

        $this->stdout("Content freeze state reconciled.\n");

        return ExitCode::OK;
    }
}
