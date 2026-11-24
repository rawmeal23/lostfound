<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// CHANGE: Load report form options and save member reports with a creation log.
function item_form_options(): array
{
    $categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    $locations = db()->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();

    return ['categories' => $categories, 'locations' => $locations];
}

function item_create(int $user_id, array $data): array
{
    $errors = [];
    $type = $data['type'] ?? '';
    $title = trim((string) ($data['title'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $location_note = trim((string) ($data['location_note'] ?? ''));
    $date = (string) ($data['date'] ?? '');
    $photo_path = $data['photo_path'] ?? null;
    $category_id = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
    $location_id = filter_var($data['location_id'] ?? null, FILTER_VALIDATE_INT);
    $kept_at = $data['kept_at'] ?? null;

    if (!in_array($type, ['lost', 'found'], true)) {
        $errors[] = 'Choose whether the item was lost or found.';
    }
    if ($title === '' || mb_strlen($title) > 120) {
        $errors[] = 'Enter an item name of at most 120 characters.';
    }
    if ($description === '') {
        $errors[] = 'Enter an item description.';
    }
    if ($location_note !== '' && mb_strlen($location_note) > 150) {
        $errors[] = 'The location note must be 150 characters or fewer.';
    }
    if ($category_id === false || $category_id < 1) {
        $errors[] = 'Choose a valid category.';
    }
    if ($location_id === false || $location_id < 1) {
        $errors[] = 'Choose a valid location.';
    }

    $event_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $date_errors = DateTimeImmutable::getLastErrors();
    if (
        $event_date === false
        || ($date_errors !== false && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0))
        || $event_date->format('Y-m-d') !== $date
        || $event_date > new DateTimeImmutable('today')
    ) {
        $errors[] = 'Choose a valid date that is not in the future.';
    }

    if ($type === 'found' && !in_array($kept_at, ['with_finder', 'at_office'], true)) {
        $errors[] = 'Choose where the found item is being kept.';
    }
    if ($photo_path !== null && (!is_string($photo_path) || $photo_path === '')) {
        $errors[] = 'The uploaded photo could not be saved.';
    }

    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors, 'id' => 0];
    }

    $lookup = db()->prepare(
        'SELECT
            EXISTS(SELECT 1 FROM categories WHERE id = :category_id) AS category_exists,
            EXISTS(SELECT 1 FROM locations WHERE id = :location_id) AS location_exists'
    );
    $lookup->execute(['category_id' => $category_id, 'location_id' => $location_id]);
    $valid_lookups = $lookup->fetch();
    if (!$valid_lookups || !(bool) $valid_lookups['category_exists'] || !(bool) $valid_lookups['location_exists']) {
        return ['ok' => false, 'errors' => ['Choose a valid category and location.'], 'id' => 0];
    }

    $connection = db();
    $connection->beginTransaction();

    try {
        if ($type === 'lost') {
            $insert = $connection->prepare(
                "INSERT INTO items
                    (user_id, category_id, location_id, type, title, description, location_note,
                     date_lost, date_found, photo_path, status)
                 VALUES
                    (:user_id, :category_id, :location_id, 'lost', :title, :description, :location_note,
                     :event_date, NULL, :photo_path, 'pending')"
            );
        } else {
            $insert = $connection->prepare(
                "INSERT INTO items
                    (user_id, category_id, location_id, type, title, description, location_note,
                     date_lost, date_found, kept_at, photo_path, status)
                 VALUES
                    (:user_id, :category_id, :location_id, 'found', :title, :description, :location_note,
                     NULL, :event_date, :kept_at, :photo_path, 'pending')"
            );
        }

        $parameters = [
            'user_id' => $user_id,
            'category_id' => $category_id,
            'location_id' => $location_id,
            'title' => $title,
            'description' => $description,
            'location_note' => $location_note !== '' ? $location_note : null,
            'event_date' => $date,
            'photo_path' => $photo_path,
        ];
        if ($type === 'found') {
            $parameters['kept_at'] = $kept_at;
        }

        $insert->execute($parameters);
        $item_id = (int) $connection->lastInsertId();
        $log = $connection->prepare(
            "INSERT INTO item_logs (item_id, actor_user_id, event_type, details)
             VALUES (:item_id, :actor_user_id, 'report_created', :details)"
        );
        $log->execute([
            'item_id' => $item_id,
            'actor_user_id' => $user_id,
            'details' => $type . ' report submitted',
        ]);
        $connection->commit();

        return ['ok' => true, 'errors' => [], 'id' => $item_id];
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}

/*
 * All reports submitted by one user, newest first (any status).
 */
function items_by_user(int $user_id): array
{
    $statement = db()->prepare(
        'SELECT i.id, i.type, i.title, i.status, i.date_lost, i.date_found,
                i.created_at, c.name AS category, l.name AS location
         FROM items i
         JOIN categories c ON c.id = i.category_id
         JOIN locations l ON l.id = i.location_id
         WHERE i.user_id = :user_id
           AND NOT EXISTS (
               SELECT 1 FROM item_logs il
               WHERE il.item_id = i.id AND il.event_type = \'report_withdrawn\'
           )
         ORDER BY i.created_at DESC, i.id DESC'
    );
    $statement->execute(['user_id' => $user_id]);

    return $statement->fetchAll();
}

/*
 * One report that belongs to this user (used by the edit page).
 * Returns null when the report does not exist or belongs to someone else.
 */
function item_get_own(int $item_id, int $user_id): ?array
{
    $statement = db()->prepare(
        'SELECT id, type, title, description, category_id, location_id, location_note,
                date_lost, date_found, kept_at, photo_path, status
         FROM items
         WHERE id = :id AND user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['id' => $item_id, 'user_id' => $user_id]);
    $item = $statement->fetch();

    return $item ?: null;
}

/*
 * Checks the text fields of the report form.
 * These are the same rules that item_create() uses; keep the two in step
 * if one of them changes.
 * Returns ['errors' => [...], 'clean' => [...]].
 */
function item_check_fields(string $type, array $data): array
{
    $errors = [];
    $title = trim((string) ($data['title'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $location_note = trim((string) ($data['location_note'] ?? ''));
    $date = (string) ($data['date'] ?? '');
    $category_id = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
    $location_id = filter_var($data['location_id'] ?? null, FILTER_VALIDATE_INT);
    $kept_at = $data['kept_at'] ?? null;

    if ($title === '' || mb_strlen($title) > 120) {
        $errors[] = 'Enter an item name of at most 120 characters.';
    }
    if ($description === '') {
        $errors[] = 'Enter an item description.';
    }
    if ($location_note !== '' && mb_strlen($location_note) > 150) {
        $errors[] = 'The location note must be 150 characters or fewer.';
    }
    if ($category_id === false || $category_id < 1) {
        $errors[] = 'Choose a valid category.';
    }
    if ($location_id === false || $location_id < 1) {
        $errors[] = 'Choose a valid location.';
    }

    $event_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $date_errors = DateTimeImmutable::getLastErrors();
    if (
        $event_date === false
        || ($date_errors !== false && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0))
        || $event_date->format('Y-m-d') !== $date
        || $event_date > new DateTimeImmutable('today')
    ) {
        $errors[] = 'Choose a valid date that is not in the future.';
    }

    if ($type === 'found' && !in_array($kept_at, ['with_finder', 'at_office'], true)) {
        $errors[] = 'Choose where the found item is being kept.';
    }

    // Make sure the chosen category and location really exist.
    if ($errors === []) {
        $lookup = db()->prepare(
            'SELECT
                EXISTS(SELECT 1 FROM categories WHERE id = :category_id) AS category_exists,
                EXISTS(SELECT 1 FROM locations WHERE id = :location_id) AS location_exists'
        );
        $lookup->execute(['category_id' => $category_id, 'location_id' => $location_id]);
        $valid_lookups = $lookup->fetch();
        if (!$valid_lookups || !(bool) $valid_lookups['category_exists'] || !(bool) $valid_lookups['location_exists']) {
            $errors[] = 'Choose a valid category and location.';
        }
    }

    return [
        'errors' => $errors,
        'clean' => [
            'title' => $title,
            'description' => $description,
            'location_note' => $location_note !== '' ? $location_note : null,
            'date' => $date,
            'category_id' => (int) $category_id,
            'location_id' => (int) $location_id,
            'kept_at' => $type === 'found' ? $kept_at : null,
        ],
    ];
}

/*
 * Deletes a photo file from the uploads folder.
 * It only removes files saved by upload_photo() (../uploads/<32 letters or
 * digits>.jpg or .png), so it can never remove any other file.
 */
function item_remove_photo_file(?string $photo_path): void
{
    if ($photo_path === null || preg_match('#^\.\./uploads/[a-f0-9]{32}\.(jpg|png)$#', $photo_path) !== 1) {
        return;
    }

    $file = __DIR__ . '/../uploads/' . basename($photo_path);
    if (is_file($file)) {
        unlink($file);
    }
}

/*
 * Edit one of the user's own reports, only while it is still pending.
 * $data keys: title, category_id, location_id, date, location_note,
 *             description, kept_at, photo_path (null = keep the current photo)
 * The type (lost or found) cannot be changed. The edit is written to the
 * item history. Returns ['ok' => bool, 'errors' => [...]].
 */
function item_update_own(int $item_id, int $user_id, array $data): array
{
    $new_photo = $data['photo_path'] ?? null;

    $connection = db();
    $connection->beginTransaction();

    try {
        $check = $connection->prepare(
            'SELECT type, status, photo_path FROM items WHERE id = :id AND user_id = :user_id FOR UPDATE'
        );
        $check->execute(['id' => $item_id, 'user_id' => $user_id]);
        $item = $check->fetch();

        if (!$item) {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['That report was not found.']];
        }
        if ($item['status'] !== 'pending') {
            $connection->rollBack();
            return ['ok' => false, 'errors' => ['Only reports that are still waiting for review can be edited.']];
        }

        $checked = item_check_fields((string) $item['type'], $data);
        if ($checked['errors'] !== []) {
            $connection->rollBack();
            return ['ok' => false, 'errors' => $checked['errors']];
        }
        $clean = $checked['clean'];

        $is_lost = $item['type'] === 'lost';
        $photo_path = is_string($new_photo) && $new_photo !== '' ? $new_photo : $item['photo_path'];

        $update = $connection->prepare(
            "UPDATE items
             SET category_id = :category_id, location_id = :location_id, title = :title,
                 description = :description, location_note = :location_note,
                 date_lost = :date_lost, date_found = :date_found,
                 kept_at = :kept_at, photo_path = :photo_path
             WHERE id = :id AND user_id = :user_id AND status = 'pending'"
        );
        $update->execute([
            'category_id' => $clean['category_id'],
            'location_id' => $clean['location_id'],
            'title' => $clean['title'],
            'description' => $clean['description'],
            'location_note' => $clean['location_note'],
            'date_lost' => $is_lost ? $clean['date'] : null,
            'date_found' => $is_lost ? null : $clean['date'],
            'kept_at' => $clean['kept_at'],
            'photo_path' => $photo_path,
            'id' => $item_id,
            'user_id' => $user_id,
        ]);

        $log = $connection->prepare(
            "INSERT INTO item_logs (item_id, actor_user_id, event_type, details)
             VALUES (:item_id, :actor_user_id, 'report_edited', :details)"
        );
        $log->execute([
            'item_id' => $item_id,
            'actor_user_id' => $user_id,
            'details' => 'Report edited by its owner',
        ]);
        $connection->commit();
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }

    // The old photo is no longer used once a new one has replaced it.
    if (is_string($new_photo) && $new_photo !== '') {
        item_remove_photo_file($item['photo_path'] === null ? null : (string) $item['photo_path']);
    }

    return ['ok' => true, 'errors' => []];
}

/*
 * Delete one of the user's own reports, only while it is still pending.
 * The report is archived instead of removed, so the item history stays
 * complete, and it no longer shows in My Posts or anywhere else on the site.
 * Returns true when the report was deleted.
 */
function item_delete_own(int $item_id, int $user_id): bool
{
    $connection = db();
    $connection->beginTransaction();

    try {
        $check = $connection->prepare(
            'SELECT status FROM items WHERE id = :id AND user_id = :user_id FOR UPDATE'
        );
        $check->execute(['id' => $item_id, 'user_id' => $user_id]);
        $item = $check->fetch();

        if (!$item || $item['status'] !== 'pending') {
            $connection->rollBack();
            return false;
        }

        $update = $connection->prepare(
            "UPDATE items SET status = 'archived' WHERE id = :id AND status = 'pending'"
        );
        $update->execute(['id' => $item_id]);

        $log = $connection->prepare(
            "INSERT INTO item_logs (item_id, actor_user_id, event_type, details)
             VALUES (:item_id, :actor_user_id, 'report_withdrawn', :details)"
        );
        $log->execute([
            'item_id' => $item_id,
            'actor_user_id' => $user_id,
            'details' => 'Report deleted by its owner before review',
        ]);
        $connection->commit();

        return true;
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}

/*
 * The owner of a LOST report says "I already found this item".
 * The report is archived (not removed) so the history stays complete, and it
 * disappears from My Posts and from the whole site. Works only on the user's own
 * lost reports that are still pending or approved. No new report is needed.
 * Returns true when the report was closed.
 */
function item_mark_found_by_owner(int $item_id, int $user_id): bool
{
    $connection = db();
    $connection->beginTransaction();

    try {
        $check = $connection->prepare(
            'SELECT type, status FROM items WHERE id = :id AND user_id = :user_id FOR UPDATE'
        );
        $check->execute(['id' => $item_id, 'user_id' => $user_id]);
        $item = $check->fetch();

        if (!$item || $item['type'] !== 'lost' || !in_array($item['status'], ['pending', 'approved'], true)) {
            $connection->rollBack();
            return false;
        }

        $update = $connection->prepare(
            "UPDATE items SET status = 'archived' WHERE id = :id AND status IN ('pending', 'approved')"
        );
        $update->execute(['id' => $item_id]);

        // Same history event as delete, so no new SQL is needed.
        $log = $connection->prepare(
            "INSERT INTO item_logs (item_id, actor_user_id, event_type, details)
             VALUES (:item_id, :actor_user_id, 'report_withdrawn', :details)"
        );
        $log->execute([
            'item_id' => $item_id,
            'actor_user_id' => $user_id,
            'details' => 'Owner found the lost item (report closed by the owner)',
        ]);
        $connection->commit();

        return true;
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}
