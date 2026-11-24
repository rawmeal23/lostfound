<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/items_member.php';
require_once __DIR__ . '/../src/uploads.php';

require_login();
$user = current_user();
if ($user === null) {
    redirect('login.php');
}

// Load the report, but only if it belongs to this user.
$raw_id = $_GET['id'] ?? '';
$item_id = is_string($raw_id) ? filter_var($raw_id, FILTER_VALIDATE_INT) : false;
$item = ($item_id !== false && $item_id !== null) ? item_get_own((int) $item_id, (int) $user['id']) : null;

if ($item === null) {
    http_response_code(404);
} elseif ($item['status'] !== 'pending') {
    flash_set('error', 'Only reports that are still waiting for review can be edited.');
    redirect('my_posts.php');
}

$type = $item['type'] ?? 'lost';
$errors = [];
$options = item_form_options();
$form_values = [
    'title' => '',
    'category_id' => '',
    'location_id' => '',
    'date' => '',
    'location_note' => '',
    'description' => '',
    'kept_at' => '',
];
if ($item !== null) {
    // Start the form with what is saved now.
    $form_values = [
        'title' => (string) $item['title'],
        'category_id' => (string) $item['category_id'],
        'location_id' => (string) $item['location_id'],
        'date' => (string) ($type === 'lost' ? $item['date_lost'] : $item['date_found']),
        'location_note' => (string) ($item['location_note'] ?? ''),
        'description' => (string) $item['description'],
        'kept_at' => (string) ($item['kept_at'] ?? ''),
    ];
}

if ($item !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($form_values as $key => $unused) {
        $value = $_POST[$key] ?? '';
        $form_values[$key] = is_string($value) ? trim($value) : '';
    }

    $upload = upload_photo($_FILES['photo'] ?? []);
    if (!$upload['ok']) {
        $errors = array_merge($errors, $upload['errors']);
    } else {
        try {
            $result = item_update_own((int) $item['id'], (int) $user['id'], [
                'title' => $form_values['title'],
                'category_id' => $form_values['category_id'],
                'location_id' => $form_values['location_id'],
                'date' => $form_values['date'],
                'location_note' => $form_values['location_note'],
                'description' => $form_values['description'],
                'kept_at' => $form_values['kept_at'],
                'photo_path' => $upload['path'] !== '' ? $upload['path'] : null,
            ]);
        } catch (PDOException $exception) {
            // Most likely sql/upgrade_edit_delete.sql has not been imported yet.
            $result = ['ok' => false, 'errors' => ['The report could not be saved. Ask the developer to import sql/upgrade_edit_delete.sql.']];
        }
        if ($result['ok']) {
            flash_set('success', 'Your report was updated.');
            redirect('my_posts.php');
        }

        $errors = array_merge($errors, $result['errors']);
        // The update failed, so the new photo is not needed.
        if ($upload['path'] !== '') {
            item_remove_photo_file($upload['path']);
        }
    }
}

$page_title = 'Edit Report | LSPU Lost and Found';
require __DIR__ . '/../templates/header.php';
?>
<main>
  <div class="page-head"><div class="container">
    <h1>Edit Report</h1>
    <p>You can change a report until the admin reviews it.</p>
  </div></div>
  <section><div class="container"><div class="panel">
    <?php if ($item === null): ?>
      <p class="empty">Report not found.</p>
      <p class="button-row"><a class="btn" href="my_posts.php">Back to My Posts</a></p>
    <?php else: ?>
      <?php if ($errors !== []): ?>
        <div class="msg show is-error" role="alert">
          <?php foreach ($errors as $error): ?><p><?= e((string) $error) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form method="post" action="edit_post.php?id=<?= (int) $item['id'] ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field full">
            <span class="meta">Report type: <strong><?= $type === 'lost' ? 'Lost item' : 'Found item' ?></strong> (cannot be changed)</span>
          </div>
          <div class="field full">
            <label for="title">Item name</label>
            <input id="title" name="title" maxlength="120" required value="<?= e($form_values['title']) ?>">
          </div>
          <div class="field">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
              <option value="">Select category</option>
              <?php foreach ($options['categories'] as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === $form_values['category_id'] ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="location_id"><?= $type === 'lost' ? 'Where did you lose it?' : 'Where did you find it?' ?></label>
            <select id="location_id" name="location_id" required>
              <option value="">Select location</option>
              <?php foreach ($options['locations'] as $location): ?>
                <option value="<?= (int) $location['id'] ?>" <?= (string) $location['id'] === $form_values['location_id'] ? 'selected' : '' ?>><?= e((string) $location['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="date"><?= $type === 'lost' ? 'Date lost' : 'Date found' ?></label>
            <input type="date" id="date" name="date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($form_values['date']) ?>">
          </div>
          <div class="field">
            <label for="location_note">Additional location details</label>
            <input id="location_note" name="location_note" maxlength="150" value="<?= e($form_values['location_note']) ?>">
          </div>
          <?php if ($type === 'found'): ?>
            <div class="field full">
              <label for="kept_at">Where is the item kept?</label>
              <select id="kept_at" name="kept_at" required>
                <option value="">Select where it is kept</option>
                <option value="with_finder" <?= $form_values['kept_at'] === 'with_finder' ? 'selected' : '' ?>>With the finder</option>
                <option value="at_office" <?= $form_values['kept_at'] === 'at_office' ? 'selected' : '' ?>>At the office</option>
              </select>
            </div>
          <?php endif; ?>
          <div class="field full">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" required><?= e($form_values['description']) ?></textarea>
          </div>
          <div class="field full">
            <label for="photo">Replace photo (optional, JPG or PNG, maximum 3 MB)</label>
            <?php if (!empty($item['photo_path'])): ?>
              <img src="<?= e((string) $item['photo_path']) ?>" alt="Current photo" width="160" style="border-radius:8px;margin-bottom:6px">
            <?php endif; ?>
            <input type="file" id="photo" name="photo" accept="image/png,image/jpeg">
          </div>
          <div class="field full">
            <div class="button-row" style="justify-content:flex-start">
              <button class="btn">Save changes</button>
              <a class="btn ghost" href="my_posts.php">Cancel</a>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div></div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
