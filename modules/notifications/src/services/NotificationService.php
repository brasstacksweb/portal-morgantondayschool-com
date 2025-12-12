<?php

namespace modules\registration\services;

use Craft;
use craft\db\Query;
use modules\registration\records\UserUpdateReadStatusRecord;

class NotificationService
{
    public static function getUnreadCount(int $userId): int
    {
        $subscribedClasses = SubscriptionService::getSubscribedClasses($userId);
        
        if (empty($subscribedClasses)) {
            return 0;
        }
        
        $classIds = array_map(fn($class) => $class->id, $subscribedClasses);
        $updatesSectionId = self::getUpdatesSectionId();
        
        if (!$updatesSectionId) {
            return 0;
        }
        
        return (new Query())
            ->from(['cu' => '{{%entries}}'])
            ->innerJoin(['rel' => '{{%relations}}'], 'cu.id = rel.sourceId')
            ->leftJoin(['urs' => '{{%user_update_read_status}}'], 
                'cu.id = urs.updateEntryId AND urs.userId = :userId')
            ->where([
                'cu.sectionId' => $updatesSectionId,
                'rel.targetId' => $classIds,
            ])
            ->andWhere(['urs.id' => null])
            ->addParams([':userId' => $userId])
            ->count();
    }

    public static function getUnreadUpdates(int $userId): array
    {
        $subscribedClasses = SubscriptionService::getSubscribedClasses($userId);
        
        if (empty($subscribedClasses)) {
            return [];
        }
        
        $classIds = array_map(fn($class) => $class->id, $subscribedClasses);
        $updatesSectionId = self::getUpdatesSectionId();
        
        if (!$updatesSectionId) {
            return [];
        }
        
        $updateIds = (new Query())
            ->select('cu.id')
            ->from(['cu' => '{{%entries}}'])
            ->innerJoin(['rel' => '{{%relations}}'], 'cu.id = rel.sourceId')
            ->leftJoin(['urs' => '{{%user_update_read_status}}'], 
                'cu.id = urs.updateEntryId AND urs.userId = :userId')
            ->where([
                'cu.sectionId' => $updatesSectionId,
                'rel.targetId' => $classIds,
            ])
            ->andWhere(['urs.id' => null])
            ->orderBy('cu.postDate DESC')
            ->addParams([':userId' => $userId])
            ->column();
        
        if (empty($updateIds)) {
            return [];
        }
        
        return Craft::$app->getEntries()
            ->id($updateIds)
            ->orderBy('postDate DESC')
            ->all();
    }

    public static function markAsRead(int $userId, int $updateEntryId): bool
    {
        $existingRecord = UserUpdateReadStatusRecord::find()
            ->where(['userId' => $userId, 'updateEntryId' => $updateEntryId])
            ->one();

        if ($existingRecord) {
            return true;
        }

        $updateEntry = Craft::$app->getEntries()->getElementById($updateEntryId);
        if (!$updateEntry) {
            return false;
        }

        $readStatus = new UserUpdateReadStatusRecord();
        $readStatus->userId = $userId;
        $readStatus->updateEntryId = $updateEntryId;
        
        return $readStatus->save();
    }

    public static function markAllAsRead(int $userId): bool
    {
        $unreadUpdates = self::getUnreadUpdates($userId);
        
        if (empty($unreadUpdates)) {
            return true;
        }
        
        $transaction = Craft::$app->getDb()->beginTransaction();
        
        try {
            foreach ($unreadUpdates as $update) {
                if (!self::markAsRead($userId, $update->id)) {
                    throw new \Exception("Failed to mark update {$update->id} as read");
                }
            }
            
            $transaction->commit();
            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            Craft::error("Failed to mark all as read for user {$userId}: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private static function getUpdatesSectionId(): ?int
    {
        $section = Craft::$app->getSections()->getSectionByHandle('classUpdates');
        return $section ? $section->id : null;
    }
}