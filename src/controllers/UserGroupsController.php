<?php

namespace bymayo\craftcontentfreeze\controllers;

use bymayo\craftcontentfreeze\Plugin;

use Craft;
use craft\web\Controller;
use craft\elements\User;
use craft\models\UserGroup;
use yii\web\Response;

class UserGroupsController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionCloneAndRedirect(): \yii\web\Response
    {
        
        $this->requirePermission('manageUserGroups');

        $groupId = Craft::$app->request->getQueryParam('groupId');
        $originalGroup = Craft::$app->userGroups->getGroupById($groupId);

        if (!$originalGroup) {
            Craft::$app->getSession()->setError('Original group not found.');
            return $this->redirect('settings/plugins/content-freeze');
        }

        // Generate new group name
        $newGroupName = $originalGroup->name . ' (Content Freeze)';
        $newGroup = new UserGroup();
        $newGroup->name = $newGroupName;
        $newGroup->handle = $this->generateHandle($newGroupName);

        if (!Craft::$app->userGroups->saveGroup($newGroup)) {
            $errors = $newGroup->getFirstErrors();
            $errorMessage = 'Failed to create new group: ' . implode(', ', $errors);
            Craft::$app->getSession()->setError($errorMessage);
            return $this->redirect('settings/plugins/content-freeze');
        }

        // Set view-only permissions based on original group
        $this->setViewOnlyPermissions($newGroup, $originalGroup);

        Craft::$app->getSession()->setNotice('Group cloned successfully!');
        return $this->redirect('settings/plugins/content-freeze');
    }

    /**
     * Set view-only permissions for a user group based on original group
     */
    private function setViewOnlyPermissions(UserGroup $newGroup, UserGroup $originalGroup): void
    {
        // Get the original group's permissions
        $originalPermissions = Craft::$app->getUserPermissions()->getPermissionsByGroupId($originalGroup->id);

        Plugin::log(print_r($originalPermissions, true));
        
        // Build permissions array with a loop
        $viewOnlyPermissions = [];
        
        foreach ($originalPermissions as $permission) {

            // Keep permissions that start with "view"
            if (strpos($permission, 'view') === 0) {
                $viewOnlyPermissions[] = $permission;
            }

            if ($permission === 'commerce-manageorders' || $permission === 'accessplugin-commerce' ) {
                $viewOnlyPermissions[] = $permission;
            }
            
        }
        
        // Add essential permissions for basic access
        $viewOnlyPermissions[] = 'accessCp';
        $viewOnlyPermissions[] = 'accessSiteWhenSystemIsOff';
        
        // Set permissions for the new group
        Craft::$app->getUserPermissions()->saveGroupPermissions($newGroup->id, $viewOnlyPermissions);
    }

    /**
     * Generate a unique handle for the new group
     */
    private function generateHandle(string $name): string
    {
        // Convert to camelCase
        $handle = strtolower(trim($name));
        $handle = preg_replace('/[^a-z0-9\s]/', '', $handle);
        $handle = preg_replace('/\s+/', ' ', $handle);
        $handle = trim($handle);
        
        // Convert to camelCase
        $words = explode(' ', $handle);
        $handle = $words[0];
        for ($i = 1; $i < count($words); $i++) {
            $handle .= ucfirst($words[$i]);
        }
        
        // Ensure it starts with a letter
        if (!preg_match('/^[a-z]/', $handle)) {
            $handle = 'group' . ucfirst($handle);
        }
        
        // Ensure uniqueness
        $originalHandle = $handle;
        $counter = 1;
        
        while (Craft::$app->userGroups->getGroupByHandle($handle)) {
            $handle = $originalHandle . $counter;
            $counter++;
        }
        
        return $handle;
    }
} 