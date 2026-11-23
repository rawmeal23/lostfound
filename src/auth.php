<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

// CHANGE: Add session-backed account lookup and admin access checks.
function current_user(): ?array
{
    $user_id = $_SESSION['user_id'] ?? null;
    if (!is_int($user_id)) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, school_id, full_name, email, role
         FROM users
         WHERE id = :id'
    );
    $statement->execute(['id' => $user_id]);
    $user = $statement->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function require_login(): void
{
    if (current_user() === null) {
        redirect('login.php');
    }
}

function require_admin(): void
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }

    if ($user['role'] !== 'admin') {
        http_response_code(403);
        $page_title = 'Admin access only | LSPU Lost and Found';
        require __DIR__ . '/../templates/header.php';
        ?>
<main>
  <section><div class="container"><div class="panel notice-card">
    <h1>Admin access only</h1>
    <p>This page is for the Lost and Found office. Your account does not have permission to open it.</p>
    <a class="btn" href="index.php">Back to home</a>
  </div></div></section>
</main>
<?php
        require __DIR__ . '/../templates/footer.php';
        exit;
    }
}

function login_user(string $id_or_email, string $password): bool
{
    $statement = db()->prepare(
        'SELECT id, password_hash
         FROM users
         WHERE school_id = :school_id OR email = :email
         LIMIT 1'
    );
    $statement->execute([
        'school_id' => $id_or_email,
        'email' => $id_or_email,
    ]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
}
