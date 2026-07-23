<?php

namespace modules\athletics\services;

use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use modules\athletics\records\Signup as SignupRecord;
use yii\base\Component;
use yii\db\IntegrityException;

/**
 * Athletics signups service.
 *
 * Query and mutation surface for {{%athletics_signups}}. Deals in records and
 * primitives; the HTTP/form layer (models\Signup, the controller) sits on top
 * and calls in here.
 */
class Signups extends Component
{
    public const STATUS_INTERESTED = 'interested';
    public const STATUS_COMMITTED = 'committed';

    public const STATE_PENDING = 'pending';
    public const STATE_FORMING = 'forming';
    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_OVER_TARGET = 'over_target';
    public const STATE_CLOSED = 'closed';

    // --- Writes -------------------------------------------------------------

    /**
     * Build and insert a registration. No transaction: capacity is a soft
     * display target, so the unique (teamEntryId, participantKey) index is the
     * only guard the write path needs. The catch handles the duplicate race.
     *
     * @param array $data validated participant fields
     */
    public function register(int $userId, int $teamEntryId, array $data): ?SignupRecord
    {
        $record = new SignupRecord();
        $record->userId = $userId;
        $record->teamEntryId = $teamEntryId;
        $record->participantFirstName = $data['participantFirstName'];
        $record->participantLastName = $data['participantLastName'];
        $record->dateOfBirth = $data['dateOfBirth'];
        $record->guardianEmail = $data['guardianEmail'];
        $record->guardianPhone = $data['guardianPhone'];
        $record->interestedInCoaching = !empty($data['interestedInCoaching']);
        $record->status = ($data['status'] ?? self::STATUS_INTERESTED) === self::STATUS_COMMITTED
            ? self::STATUS_COMMITTED
            : self::STATUS_INTERESTED;
        $record->participantKey = self::participantKey(
            $data['participantFirstName'],
            $data['participantLastName'],
            $data['dateOfBirth'],
        );

        try {
            return $record->save() ? $record : null;
        } catch (IntegrityException) {
            // Lost the race on the unique index — treat as a duplicate.
            return null;
        }
    }

    /**
     * Promote an interested signup to committed. Ownership-checked.
     */
    public function commit(int $signupId, int $userId): bool
    {
        $record = SignupRecord::findOne($signupId);

        if (!$record || (int) $record->userId !== $userId) {
            return false;
        }

        if ($record->status === self::STATUS_COMMITTED) {
            return true;
        }

        $record->status = self::STATUS_COMMITTED;

        return $record->save(false);
    }

    /**
     * Delete a signup. Ownership-checked.
     */
    public function withdraw(int $signupId, int $userId): bool
    {
        $record = SignupRecord::findOne($signupId);

        if (!$record || (int) $record->userId !== $userId) {
            return false;
        }

        return (bool) $record->delete();
    }

    // --- Reads --------------------------------------------------------------

    /**
     * A parent's own signups for one team, all statuses. Feeds the signup panel.
     *
     * @return SignupRecord[]
     */
    public function getSignupsForUserAndTeam(int $userId, int $teamEntryId): array
    {
        return SignupRecord::find()
            ->where(['userId' => $userId, 'teamEntryId' => $teamEntryId])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();
    }

    /**
     * The committed roster for a team. Contact columns are included only when
     * $includeContact is true — the gate is enforced here, not in the template.
     * Pass canViewContact(currentUser) as the flag.
     */
    public function getRoster(int $teamEntryId, bool $includeContact = false): array
    {
        $records = SignupRecord::find()
            ->where(['teamEntryId' => $teamEntryId, 'status' => self::STATUS_COMMITTED])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        return array_map(function (SignupRecord $r) use ($includeContact) {
            $row = [
                'id' => (int) $r->id,
                'name' => trim($r->participantFirstName . ' ' . $r->participantLastName),
            ];

            if ($includeContact) {
                $row['guardianEmail'] = $r->guardianEmail;
                $row['guardianPhone'] = $r->guardianPhone;
                $row['interestedInCoaching'] = (bool) $r->interestedInCoaching;
            }

            return $row;
        }, $records);
    }

    public function getCommittedCount(int $teamEntryId): int
    {
        return (int) SignupRecord::find()
            ->where(['teamEntryId' => $teamEntryId, 'status' => self::STATUS_COMMITTED])
            ->count();
    }

    public function getInterestedCount(int $teamEntryId): int
    {
        return (int) SignupRecord::find()
            ->where(['teamEntryId' => $teamEntryId, 'status' => self::STATUS_INTERESTED])
            ->count();
    }

    /**
     * Whether a participant is already registered for a team. Backs the form's
     * duplicate check so the parent gets a clean field error before the insert.
     */
    public function participantExists(int $teamEntryId, string $participantKey): bool
    {
        return SignupRecord::find()
            ->where(['teamEntryId' => $teamEntryId, 'participantKey' => $participantKey])
            ->exists();
    }

    // --- Team state ---------------------------------------------------------

    /**
     * Registration window fields are required in the CMS, so a null here means a
     * team predating that constraint — fail closed (no form) rather than throw.
     */
    public function isRegistrationOpen(Entry $team): bool
    {
        $opens = $team->registrationOpens;
        $closes = $team->registrationCloses;

        if (!$opens || !$closes) {
            return false;
        }

        $now = DateTimeHelper::now();

        return $now >= $opens && $now <= $closes;
    }

    /**
     * Spots left against the soft target. Null when capacity is unset; may be
     * negative once signups exceed the target.
     */
    public function getRemainingCapacity(Entry $team): ?int
    {
        $capacity = $team->capacity;

        if ($capacity === null || $capacity === '') {
            return null;
        }

        return (int) $capacity - $this->getCommittedCount((int) $team->id);
    }

    /**
     * One of the STATE_* constants. Counts committed rows only — that is what
     * fields a team (spec §4).
     */
    public function getTeamState(Entry $team): string
    {
        $opens = $team->registrationOpens;
        $closes = $team->registrationCloses;
        $now = DateTimeHelper::now();

        if ($opens && $now < $opens) {
            return self::STATE_PENDING;
        }

        if ($closes && $now > $closes) {
            return self::STATE_CLOSED;
        }

        $committed = $this->getCommittedCount((int) $team->id);
        $minimum = $team->minimumPlayers;
        $capacity = $team->capacity;

        if ($minimum !== null && $minimum !== '' && $committed < (int) $minimum) {
            return self::STATE_FORMING;
        }

        if ($capacity !== null && $capacity !== '' && $committed >= (int) $capacity) {
            return self::STATE_OVER_TARGET;
        }

        return self::STATE_CONFIRMED;
    }

    // --- Authorization ------------------------------------------------------

    /**
     * Whether $user may see guardian contact columns on a roster: Craft admins,
     * or members of the athleticsStaff group.
     */
    public function canViewContact(?User $user): bool
    {
        return $user !== null && ($user->admin || $user->isInGroup('athleticsStaff'));
    }

    // --- Helpers ------------------------------------------------------------

    /**
     * Deterministic duplicate key: normalized first|last|dob, hashed. The caller
     * passes dob as a `Y-m-d` string.
     */
    public static function participantKey(string $first, string $last, string $dob): string
    {
        $normalized = strtolower(trim($first)) . '|'
            . strtolower(trim($last)) . '|'
            . trim($dob);

        return hash('sha256', $normalized);
    }
}
