<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// Roles a person may choose when registering. 'admin' is never allowed here.
const REGISTER_ROLES = ['student', 'teacher', 'staff'];

/*
 * Create a new member account.
 * $data keys: school_id, full_name, email, contact_no (optional), role,
 *             password, password_confirm
 * Returns ['ok' => bool, 'errors' => [field => message], 'id' => int]
 * (the 'id' key is only present when ok is true).
 */
function register_user(array $data): array
{
    $school_id = trim((string) ($data['school_id'] ?? ''));
    $full_name = trim((string) ($data['full_name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $contact_no = trim((string) ($data['contact_no'] ?? ''));
    $role = (string) ($data['role'] ?? 'student');
    $password = (string) ($data['password'] ?? '');
    $password_confirm = (string) ($data['password_confirm'] ?? '');

    $errors = [];

    // School ID: letters, numbers and hyphens only, 3 to 30 characters.
    if (preg_match('/^[A-Za-z0-9-]{3,30}$/', $school_id) !== 1) {
        $errors['school_id'] = 'Use 3 to 30 letters, numbers or hyphens.';
    }

    if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 100) {
        $errors['full_name'] = 'Enter your full name (2 to 100 characters).';
    }

    if (strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    }

    // Contact number is optional.
    if ($contact_no !== '' && preg_match('/^[0-9+() -]{7,20}$/', $contact_no) !== 1) {
        $errors['contact_no'] = 'Use 7 to 20 digits, spaces, + ( ) or -.';
    }

    if (!in_array($role, REGISTER_ROLES, true)) {
        $errors['role'] = 'Choose student, teacher or staff.';
    }

    // bcrypt only reads the first 72 bytes, so longer passwords are refused.
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors['password'] = 'Use 8 to 72 characters.';
    } elseif ($password !== $password_confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    // Only check for duplicates when the basic fields are fine.
    if (!isset($errors['school_id']) && !isset($errors['email'])) {
        $statement = db()->prepare(
            'SELECT school_id, email FROM users
             WHERE school_id = :school_id OR email = :email'
        );
        $statement->execute(['school_id' => $school_id, 'email' => $email]);
        foreach ($statement->fetchAll() as $row) {
            if (strcasecmp((string) $row['school_id'], $school_id) === 0) {
                $errors['school_id'] = 'This school ID is already registered.';
            }
            if (strcasecmp((string) $row['email'], $email) === 0) {
                $errors['email'] = 'This email is already registered.';
            }
        }
    }

    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    try {
        $insert = db()->prepare(
            'INSERT INTO users (school_id, full_name, email, password_hash, role, contact_no)
             VALUES (:school_id, :full_name, :email, :password_hash, :role, :contact_no)'
        );
        $insert->execute([
            'school_id' => $school_id,
            'full_name' => $full_name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'contact_no' => $contact_no === '' ? null : $contact_no,
        ]);
    } catch (PDOException $exception) {
        // Two people registering the same ID at the same moment.
        return ['ok' => false, 'errors' => ['school_id' => 'This school ID or email is already registered.']];
    }

    return ['ok' => true, 'errors' => [], 'id' => (int) db()->lastInsertId()];
}
