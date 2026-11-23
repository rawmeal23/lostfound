<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// CHANGE: Save claims and review them transactionally with dated item history.
function claim_create(int $item_id, int $claimant_id, string $proof): array
{
    $proof = trim($proof);
    if ($proof === '') {
        return ['ok' => false, 'errors' => ['Describe details that verify your claim.'], 'id' => 0];
    }

    $connection = db();
    $connection->beginTransaction();

    try {
        $item_statement = $connection->prepare(
            'SELECT user_id, status FROM items WHERE id = :id FOR UPDATE'
        );
        $item_statement->execute(['id' => $item_id]);
        $item = $item_statement->fetch();

        if (!$item || $item['status'] !== 'approved') {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['This item is not available to claim.'], 'id' => 0];
        }
        if ((int) $item['user_id'] === $claimant_id) {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['You cannot claim your own report.'], 'id' => 0];
        }

        $duplicate = $connection->prepare(
            'SELECT id FROM claims WHERE item_id = :item_id AND claimant_id = :claimant_id LIMIT 1'
        );
        $duplicate->execute(['item_id' => $item_id, 'claimant_id' => $claimant_id]);
        if ($duplicate->fetch()) {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['You have already submitted a claim for this item.'], 'id' => 0];
        }

        $insert = $connection->prepare(
            "INSERT INTO claims (item_id, claimant_id, proof_details, status)
             VALUES (:item_id, :claimant_id, :proof_details, 'pending')"
        );
        $insert->execute([
            'item_id' => $item_id,
            'claimant_id' => $claimant_id,
            'proof_details' => $proof,
        ]);
        $claim_id = (int) $connection->lastInsertId();

        $claim_date_statement = $connection->prepare(
            'SELECT created_at FROM claims WHERE id = :id'
        );
        $claim_date_statement->execute(['id' => $claim_id]);
        $claim_date = $claim_date_statement->fetchColumn();

        $log = $connection->prepare(
            "INSERT INTO item_logs (item_id, claim_id, actor_user_id, event_type, claim_date)
             VALUES (:item_id, :claim_id, :actor_user_id, 'claim_submitted', :claim_date)"
        );
        $log->execute([
            'item_id' => $item_id,
            'claim_id' => $claim_id,
            'actor_user_id' => $claimant_id,
            'claim_date' => $claim_date,
        ]);
        $connection->commit();

        return ['ok' => true, 'errors' => [], 'id' => $claim_id];
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}

function claims_pending(): array
{
    $statement = db()->prepare(
        "SELECT c.id, c.item_id, c.proof_details, c.status, c.created_at,
                i.title AS item_title, u.school_id, u.full_name
         FROM claims c
         INNER JOIN items i ON i.id = c.item_id
         INNER JOIN users u ON u.id = c.claimant_id
         WHERE c.status = 'pending'
         ORDER BY c.created_at ASC, c.id ASC"
    );
    $statement->execute();

    return $statement->fetchAll();
}

function claim_review(int $claim_id, int $admin_id, string $decision): array
{
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return ['ok' => false, 'errors' => ['Invalid claim decision.']];
    }

    $connection = db();
    $connection->beginTransaction();

    try {
        $claim_statement = $connection->prepare(
            'SELECT item_id, status, created_at FROM claims WHERE id = :id FOR UPDATE'
        );
        $claim_statement->execute(['id' => $claim_id]);
        $claim = $claim_statement->fetch();

        if (!$claim || $claim['status'] !== 'pending') {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['This claim is no longer pending.']];
        }

        $item_statement = $connection->prepare(
            'SELECT status FROM items WHERE id = :id FOR UPDATE'
        );
        $item_statement->execute(['id' => (int) $claim['item_id']]);
        $item = $item_statement->fetch();

        if (!$item || ($decision === 'approved' && $item['status'] !== 'approved')) {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['Only claims for approved items can be approved.']];
        }

        $review = $connection->prepare(
            'UPDATE claims
             SET status = :status, reviewed_by = :admin_id, reviewed_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = \'pending\''
        );
        $review->execute([
            'status' => $decision,
            'admin_id' => $admin_id,
            'id' => $claim_id,
        ]);

        if ($decision === 'approved') {
            $mark_claimed = $connection->prepare(
                "UPDATE items SET status = 'claimed' WHERE id = :id AND status = 'approved'"
            );
            $mark_claimed->execute(['id' => (int) $claim['item_id']]);

            if ($mark_claimed->rowCount() !== 1) {
                $connection->rollBack();
                return ['ok' => false, 'errors' => ['The item is no longer available for a claim.']];
            }
        }

        $event_type = $decision === 'approved' ? 'claim_approved' : 'claim_rejected';
        $log = $connection->prepare(
            'INSERT INTO item_logs
                (item_id, claim_id, actor_user_id, event_type, claim_date, details)
             VALUES
                (:item_id, :claim_id, :actor_user_id, :event_type, :claim_date, :details)'
        );
        $log->execute([
            'item_id' => (int) $claim['item_id'],
            'claim_id' => $claim_id,
            'actor_user_id' => $admin_id,
            'event_type' => $event_type,
            'claim_date' => $claim['created_at'],
            'details' => 'Claim ' . $decision,
        ]);

        $connection->commit();
        return ['ok' => true, 'errors' => []];
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}
