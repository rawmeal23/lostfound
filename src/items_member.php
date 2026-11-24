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
         ORDER BY i.created_at DESC, i.id DESC'
    );
    $statement->execute(['user_id' => $user_id]);

    return $statement->fetchAll();
}
