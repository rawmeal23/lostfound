<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/*
 * Save one notification for a user.
 * It takes the open database connection so it can run inside the same
 * transaction as the status change that caused it.
 */
function notification_add(PDO $connection, int $user_id, string $message): void
{
    $insert = $connection->prepare(
        'INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)'
    );
    // The column holds 255 characters at most.
    $insert->execute(['user_id' => $user_id, 'message' => mb_substr($message, 0, 255)]);
}

/*
 * How many notifications the user has not opened yet (shown in the menu).
 */
function notifications_unread_count(int $user_id): int
{
    $statement = db()->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
    );
    $statement->execute(['user_id' => $user_id]);

    return (int) $statement->fetchColumn();
}

/*
 * The user's latest notifications, newest first.
 */
function notifications_by_user(int $user_id, int $limit = 50): array
{
    $statement = db()->prepare(
        'SELECT id, message, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY created_at DESC, id DESC
         LIMIT ' . max(1, $limit)
    );
    $statement->execute(['user_id' => $user_id]);

    return $statement->fetchAll();
}

/*
 * Mark every notification of this user as read.
 */
function notifications_mark_all_read(int $user_id): void
{
    $statement = db()->prepare(
        'UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0'
    );
    $statement->execute(['user_id' => $user_id]);
}
