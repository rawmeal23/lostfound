<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// CHANGE: Read public listings and item details from the database.
function items_search(array $filters, int $page = 1, int $per_page = 12): array
{
    $conditions = ["i.status = 'approved'"];
    $parameters = [];

    if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
        $conditions[] = '(i.title LIKE :title_query OR i.description LIKE :description_query)';
        $query = '%' . trim((string) $filters['q']) . '%';
        $parameters['title_query'] = $query;
        $parameters['description_query'] = $query;
    }
    foreach (['type', 'category_id', 'location_id'] as $filter) {
        if (isset($filters[$filter]) && $filters[$filter] !== '') {
            $conditions[] = match ($filter) {
                'type' => 'i.type = :type',
                'category_id' => 'i.category_id = :category_id',
                'location_id' => 'i.location_id = :location_id',
            };
            $parameters[$filter] = $filters[$filter];
        }
    }
    foreach (['date_from' => '>=', 'date_to' => '<='] as $filter => $operator) {
        if (isset($filters[$filter]) && $filters[$filter] !== '') {
            $conditions[] = 'COALESCE(i.date_lost, i.date_found) ' . $operator . ' :' . $filter;
            $parameters[$filter] = $filters[$filter];
        }
    }

    $limit = max(1, min($per_page, 100));
    $where = implode(' AND ', $conditions);
    $count_statement = db()->prepare("SELECT COUNT(*) FROM items i WHERE $where");
    $count_statement->execute($parameters);
    $total = (int) $count_statement->fetchColumn();
    $page_count = max(1, (int) ceil($total / $limit));
    $offset = (min(max(1, $page), $page_count) - 1) * $limit;

    $statement = db()->prepare(
        "SELECT i.id, i.type, i.title, i.description, i.photo_path, i.date_lost, i.date_found,
                i.kept_at, c.name AS category, l.name AS location
         FROM items i
         INNER JOIN categories c ON c.id = i.category_id
         INNER JOIN locations l ON l.id = i.location_id
         WHERE $where
         ORDER BY i.created_at DESC, i.id DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . $name, $value);
    }
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll();

    return ['rows' => $rows, 'total' => $total];
}

function item_get(int $id): ?array
{
    $statement = db()->prepare(
        "SELECT i.id, i.user_id, i.type, i.title, i.description, i.location_note, i.photo_path,
                i.date_lost, i.date_found, i.kept_at, i.status,
                c.name AS category, l.name AS location
         FROM items i
         INNER JOIN categories c ON c.id = i.category_id
         INNER JOIN locations l ON l.id = i.location_id
         WHERE i.id = :id AND i.status = 'approved'
         LIMIT 1"
    );
    $statement->execute(['id' => $id]);
    $item = $statement->fetch();

    return $item ?: null;
}

function items_recent(string $type, int $limit = 4): array
{
    if (!in_array($type, ['lost', 'found'], true)) {
        throw new InvalidArgumentException('Item type must be lost or found.');
    }

    $limit = max(1, min($limit, 12));
    $statement = db()->prepare(
        "SELECT i.id, i.type, i.title, i.description, i.photo_path, i.date_lost, i.date_found,
                i.kept_at, i.created_at, c.name AS category, l.name AS location
         FROM items i
         INNER JOIN categories c ON c.id = i.category_id
         INNER JOIN locations l ON l.id = i.location_id
         WHERE i.status = 'approved' AND i.type = :type
         ORDER BY i.created_at DESC, i.id DESC
         LIMIT :limit"
    );
    $statement->bindValue(':type', $type);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}
