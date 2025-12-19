<?php

namespace modules\notifications\services;

use craft\elements\Entry;
use modules\notifications\models\Subscriptions as SubscriptionsModel;
use modules\notifications\records\UserClassSubscription;
use yii\base\Component;

class Subscriptions extends Component
{
    public static function newSubscriptions($attrs): SubscriptionsModel
    {
        $subscriptions = new SubscriptionsModel();

        $subscriptions->setAttributes($attrs);

        return $subscriptions;
    }

    public static function getSubscribedClasses(int $userId): array
    {
        $classIds = UserClassSubscription::find()
            ->select('classEntryId')
            ->where(['userId' => $userId])
            ->column();

        if (empty($classIds)) {
            return [];
        }

        return Entry::findAll($classIds);
    }

    public static function subscribeToClass(int $userId, int $classEntryId): bool
    {
        $record = UserClassSubscription::find()
            ->where(['userId' => $userId, 'classEntryId' => $classEntryId])
            ->one();

        if ($record) {
            return true;
        }

        $class = Entry::findOne($classEntryId);
        if (!$class) {
            return false;
        }

        $record = new UserClassSubscription();
        $record->userId = $userId;
        $record->classEntryId = $classEntryId;

        return $record->save();
    }

    public static function unsubscribeFromClass(int $userId, int $classEntryId): bool
    {
        $record = UserClassSubscription::find()
            ->where(['userId' => $userId, 'classEntryId' => $classEntryId])
            ->one();

        if ($record) {
            return $record->delete();
        }

        return true;
    }

    public static function saveSubscriptions(int $userId, array $classEntryIds): bool
    {
        $transaction = \Craft::$app->getDb()->beginTransaction();

        try {
            UserClassSubscription::deleteAll(['userId' => $userId]);

            foreach ($classEntryIds as $classEntryId) {
                if (!self::subscribeToClass($userId, $classEntryId)) {
                    throw new \Exception("Failed to subscribe to class {$classEntryId}");
                }
            }

            $transaction->commit();

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            \Craft::error("Failed to save subscriptions for user {$userId}: ".$e->getMessage(), __METHOD__);

            return false;
        }
    }

    public static function getUsersForClass(int $classEntryId): array
    {
        $userIds = UserClassSubscription::find()
            ->select('userId')
            ->where(['classEntryId' => $classEntryId])
            ->column();

        if (empty($userIds)) {
            return [];
        }

        return User::findAll($userIds);
    }
}
