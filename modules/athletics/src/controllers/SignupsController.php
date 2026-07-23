<?php

namespace modules\athletics\controllers;

use craft\elements\Entry;
use craft\web\Controller;
use craft\web\Response;
use modules\athletics\AthleticsModule;
use modules\athletics\services\Signups;

/**
 * Handles registration submissions and the commit / withdraw panel actions.
 *
 * Every action requires a logged-in user; there is no anonymous access.
 * actionSave answers the ajax tl-form (JSON); commit/withdraw are plain POST
 * forms that redirect back to the sport page.
 */
class SignupsController extends Controller
{
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $signups = AthleticsModule::getInstance()->signups;
        $model = Signups::newSignup($this->request->getBodyParams());

        if (!$model->validate()) {
            return $this->asModelFailure($model, 'Please fix the highlighted fields.');
        }

        // validateHash unhashed teamEntryId in place.
        $teamId = (int) $model->teamEntryId;

        // Default status filter returns only live entries, and the section
        // filter guarantees it is a team — a disabled or non-team id fails here.
        $team = Entry::find()->section('teams')->id($teamId)->one();

        if (!$team) {
            return $this->asFailure('This team is not available for signup.');
        }

        if (!$signups->isRegistrationOpen($team)) {
            return $this->asFailure('Registration for this team is not currently open.');
        }

        $participantKey = Signups::participantKey(
            $model->participantFirstName,
            $model->participantLastName,
            $model->dateOfBirth,
        );

        if ($signups->participantExists($teamId, $participantKey)) {
            $model->addError('participantFirstName', 'This child is already registered for this team.');

            return $this->asModelFailure($model, 'This child is already registered for this team.');
        }

        $saved = $signups->register((int) \Craft::$app->getUser()->getId(), $teamId, [
            'participantFirstName' => $model->participantFirstName,
            'participantLastName' => $model->participantLastName,
            'dateOfBirth' => $model->dateOfBirth,
            'guardianEmail' => $model->guardianEmail,
            'guardianPhone' => $model->guardianPhone,
            'interestedInCoaching' => !empty($model->interestedInCoaching),
            'status' => $model->status,
        ]);

        if (!$saved) {
            return $this->asFailure('We could not save your registration. Please try again.');
        }

        \Craft::$app->getSession()->setSuccess('Your registration has been received.');

        return $this->asSuccess('Registration received.');
    }

    public function actionCommit(): Response
    {
        return $this->handlePanelAction('commit');
    }

    public function actionWithdraw(): Response
    {
        return $this->handlePanelAction('withdraw');
    }

    private function handlePanelAction(string $action): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $signupId = (int) $this->request->getRequiredBodyParam('signupId');
        $userId = (int) \Craft::$app->getUser()->getId();
        $signups = AthleticsModule::getInstance()->signups;
        $session = \Craft::$app->getSession();

        $ok = $action === 'commit'
            ? $signups->commit($signupId, $userId)
            : $signups->withdraw($signupId, $userId);

        if (!$ok) {
            $session->setError('We could not update that registration.');
        } else {
            $session->setSuccess($action === 'commit'
                ? 'Registration confirmed.'
                : 'Registration withdrawn.');
        }

        return $this->redirectToPostedUrl();
    }
}
