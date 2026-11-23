<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// CHANGE: Load live reports, claimant details, and persistent item history.
function items_admin_all(): array
{
    $item_statement = db()->prepare(
        'SELECT id, type, title, photo_path, date_lost, date_found, status,
                (type = \'lost\' AND status = \'approved\'
                 AND date_lost <= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) AS outcome_eligible
         FROM items
         ORDER BY created_at DESC, id DESC'
    );
    $item_statement->execute();
    $items = $item_statement->fetchAll();

    $claim_statement = db()->prepare(
        'SELECT c.id, c.item_id, c.proof_details, c.status, c.created_at,
                u.school_id, u.full_name
         FROM claims c
         INNER JOIN users u ON u.id = c.claimant_id
         ORDER BY c.created_at DESC, c.id DESC'
    );
    $claim_statement->execute();
    $claims_by_item = [];

    foreach ($claim_statement->fetchAll() as $claim) {
        $claims_by_item[(int) $claim['item_id']][] = $claim;
    }

    foreach ($items as &$item) {
        $item['claims'] = $claims_by_item[(int) $item['id']] ?? [];
    }
    unset($item);

    return $items;
}

function item_logs_all(): array
{
    $statement = db()->prepare(
        'SELECT il.id, il.item_id, il.claim_id, il.event_type, il.claim_date,
                il.details, il.created_at, i.title AS item_title,
                actor.school_id AS actor_school_id, claimant.school_id AS claimant_school_id
         FROM item_logs il
         INNER JOIN items i ON i.id = il.item_id
         LEFT JOIN users actor ON actor.id = il.actor_user_id
         LEFT JOIN claims c ON c.id = il.claim_id
         LEFT JOIN users claimant ON claimant.id = c.claimant_id
         ORDER BY il.created_at DESC, il.id DESC'
    );
    $statement->execute();

    return $statement->fetchAll();
}

function items_pending(): array
{
    $statement = db()->prepare(
        "SELECT id, user_id, type, title, date_lost, date_found, status
         FROM items
         WHERE status = 'pending'
         ORDER BY created_at ASC, id ASC"
    );
    $statement->execute();

    return $statement->fetchAll();
}

function item_set_status(int $id, string $status, int $admin_id): array
{
    $allowed_transitions = [
        'pending' => ['approved', 'rejected'],
        'claimed' => ['returned'],
    ];

    if (!in_array($status, ['approved', 'rejected', 'returned'], true)) {
        return ['ok' => false, 'errors' => ['Invalid item status.']];
    }

    $connection = db();
    $connection->beginTransaction();

    try {
        $check = $connection->prepare('SELECT status FROM items WHERE id = :id FOR UPDATE');
        $check->execute(['id' => $id]);
        $item = $check->fetch();

        if (!$item || !in_array($status, $allowed_transitions[$item['status']] ?? [], true)) {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['That item status change is not allowed.']];
        }

        if ($status === 'returned') {
            $update = $connection->prepare(
                "UPDATE items SET status = 'returned', returned_at = CURRENT_TIMESTAMP WHERE id = :id"
            );
        } else {
            $update = $connection->prepare('UPDATE items SET status = :status WHERE id = :id');
        }

        $parameters = ['id' => $id];
        if ($status !== 'returned') {
            $parameters['status'] = $status;
        }
        $update->execute($parameters);

        $event_type = match ($status) {
            'approved' => 'item_approved',
            'rejected' => 'item_rejected',
            'returned' => 'item_returned',
        };
        $log = $connection->prepare(
            'INSERT INTO item_logs (item_id, actor_user_id, event_type, details)
             VALUES (:item_id, :actor_user_id, :event_type, :details)'
        );
        $log->execute([
            'item_id' => $id,
            'actor_user_id' => $admin_id,
            'event_type' => $event_type,
            'details' => 'Item status changed to ' . $status,
        ]);
        $connection->commit();

        return ['ok' => true, 'errors' => []];
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}

function item_record_outcome(int $id, int $admin_id, string $outcome): array
{
    $events = [
        'found' => 'outcome_found',
        'never_found' => 'outcome_never_found',
    ];
    if (!isset($events[$outcome])) {
        return ['ok' => false, 'errors' => ['Choose found or never found.']];
    }

    $connection = db();
    $connection->beginTransaction();

    try {
        $check = $connection->prepare(
            'SELECT type, status FROM items WHERE id = :id AND date_lost <= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) FOR UPDATE'
        );
        $check->execute(['id' => $id]);
        $item = $check->fetch();
        if (!$item || $item['type'] !== 'lost' || $item['status'] !== 'approved') {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['Only active lost reports at least three months old can be closed.']];
        }

        $update = $connection->prepare(
            "UPDATE items SET status = 'archived' WHERE id = :id AND status = 'approved'"
        );
        $update->execute(['id' => $id]);
        if ($update->rowCount() !== 1) {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['The report could not be archived. Refresh and try again.']];
        }

        $log = $connection->prepare(
            'INSERT INTO item_logs (item_id, actor_user_id, event_type, details)
             VALUES (:item_id, :actor_user_id, :event_type, :details)'
        );
        $log->execute([
            'item_id' => $id,
            'actor_user_id' => $admin_id,
            'event_type' => $events[$outcome],
            'details' => $outcome === 'found' ? 'Lost item was found' : 'Lost item was not found',
        ]);
        $log->execute([
            'item_id' => $id,
            'actor_user_id' => $admin_id,
            'event_type' => 'item_archived',
            'details' => 'Archived after recording the three-month outcome',
        ]);
        $connection->commit();

        return ['ok' => true, 'errors' => []];
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}
