<?php

namespace bymayo\craftcontentfreeze\console\controllers;

use bymayo\craftcontentfreeze\Plugin;

use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Manages content freezes from the command line.
 */
class FreezesController extends Controller
{
    public $defaultAction = 'list';

    /**
     * Lists every freeze with its id, status and window.
     *
     *     php craft content-freeze/freezes/list
     */
    public function actionList(): int
    {
        $freezes = Plugin::getInstance()->freezes->getAllFreezes();

        if (empty($freezes)) {
            $this->stdout("No freezes found.\n");
            return ExitCode::OK;
        }

        foreach ($freezes as $freeze) {
            $this->stdout(sprintf("#%-4d ", $freeze->id), Console::FG_YELLOW);
            $this->stdout(sprintf(
                "%s  [%s]  %s → %s\n",
                $freeze->name,
                $freeze->getStatusLabel(),
                $freeze->dateFrom ? $freeze->dateFrom->format('Y-m-d H:i') : '-',
                $freeze->dateTo ? $freeze->dateTo->format('Y-m-d H:i') : '-'
            ));
        }

        return ExitCode::OK;
    }

    /**
     * Enables (triggers) a freeze by its id, then applies the change immediately.
     *
     *     php craft content-freeze/freezes/enable 3
     *
     * @param int $id The freeze id (see `content-freeze/freezes/list`).
     */
    public function actionEnable(int $id): int
    {
        return $this->setEnabled($id, true);
    }

    /**
     * Disables (lifts) a freeze by its id, then applies the change immediately.
     *
     *     php craft content-freeze/freezes/disable 3
     *
     * @param int $id The freeze id (see `content-freeze/freezes/list`).
     */
    public function actionDisable(int $id): int
    {
        return $this->setEnabled($id, false);
    }

    private function setEnabled(int $id, bool $enabled): int
    {
        $freezes = Plugin::getInstance()->freezes;
        $freeze = $freezes->getFreezeById($id);

        if ($freeze === null) {
            $this->stderr("No freeze found with id $id.\n", Console::FG_RED);
            return ExitCode::UNAVAILABLE;
        }

        $freeze->enabled = $enabled;

        if (!$freezes->saveFreeze($freeze)) {
            $this->stderr("Couldn’t save freeze #$id.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $freezes->reconcile();

        $verb = $enabled ? 'enabled' : 'disabled';
        $this->stdout("Freeze #$id ($freeze->name) $verb.\n", Console::FG_GREEN);

        if ($enabled && !$freeze->isInEffect()) {
            $this->stdout("Note: it isn’t active yet because of its scheduled window.\n", Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }
}
