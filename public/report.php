<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/items_member.php';
require_once __DIR__ . '/../src/uploads.php';

require_login();
$user = current_user();
$type = $_GET['type'] ?? 'lost';
if (!is_string($type) || !in_array($type, ['lost', 'found'], true)) {
    $type = 'lost';
}

$errors = [];
$success = flash_get();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($form_values as $key => $unused) {
        $value = $_POST[$key] ?? '';
        $form_values[$key] = is_string($value) ? trim($value) : '';
    }

    $upload = upload_photo($_FILES['photo'] ?? []);
    if (!$upload['ok']) {
        $errors = array_merge($errors, $upload['errors']);
    } else {
        $result = item_create((int) $user['id'], [
            'type' => $type,
            'title' => $form_values['title'],
            'category_id' => $form_values['category_id'],
            'location_id' => $form_values['location_id'],
            'date' => $form_values['date'],
            'location_note' => $form_values['location_note'],
            'description' => $form_values['description'],
            'kept_at' => $form_values['kept_at'],
            'photo_path' => $upload['path'] !== '' ? $upload['path'] : null,
        ]);
        if ($result['ok']) {
            flash_set('success', 'Your report was saved and is waiting for admin review.');
            redirect('browse.php');
        }

        $errors = array_merge($errors, $result['errors']);
        if ($upload['path'] !== '') {
            $uploaded_file = __DIR__ . '/../uploads/' . basename($upload['path']);
            if (is_file($uploaded_file)) {
                unlink($uploaded_file);
            }
        }
    }
}

$page_title = $type === 'lost' ? 'Report Lost Item' : 'Post Found Item';
require __DIR__ . '/../templates/header.php';
?>
<main>
  <div class="page-head"><div class="container">
    <h1><?= $type === 'lost' ? 'Report a Lost Item' : 'Post a Found Item' ?></h1>
    <p><?= $type === 'lost' ? 'Tell us what you lost so others can help you find it.' : 'Tell us what you found so the owner can claim it.' ?></p>
  </div></div>
  <section><div class="container"><div class="panel">
    <?php if (($success['message'] ?? '') !== ''): ?>
      <p class="msg show" role="status"><?= e((string) $success['message']) ?></p>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
      <div class="msg show" role="alert" style="background:#fde8e7;border-color:#e1aaa5;color:#8a1f17">
        <?php foreach ($errors as $error): ?><p><?= e((string) $error) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" action="report.php?type=<?= e($type) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field full">
          <label for="title">Item name</label>
          <input id="title" name="title" maxlength="120" required value="<?= e($form_values['title']) ?>" placeholder="e.g. Black wallet">
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
          <textarea id="description" name="description" rows="4" required placeholder="Color, brand, marks, and other identifying details"><?= e($form_values['description']) ?></textarea>
        </div>
        <div class="field full">
          <label for="photo">Photo (JPG or PNG, maximum 3 MB)</label>
          <input type="file" id="photo" name="photo" accept="image/png,image/jpeg">
        </div>
        <div class="field full"><button class="btn">Submit report</button></div>
      </div>
    </form>
  </div></div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
