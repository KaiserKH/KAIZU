<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once 'admin-header.php';

$errors  = [];
$editing = null;

// Edit load
if (isset($_GET['edit'])) {
    $stmt = db()->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->bind_param('i', $_GET['edit']);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $used = db()->query("SELECT COUNT(*) AS c FROM products WHERE category_id=$id")->fetch_assoc()['c'];
    if ($used > 0) {
        flash("Cannot delete: $used product(s) use this category. Reassign them first.", 'danger');
    } else {
        db()->prepare("DELETE FROM categories WHERE id=?")->bind_param('i',$id)->execute();
        flash('Category deleted.', 'success');
    }
    header('Location: categories.php'); exit;
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!$name) $errors[] = 'Category name is required.';
    if (!$slug) $slug = strtolower(preg_replace('/[^a-z0-9]+/i','-', $name));

    // Unique slug check
    $existing = db()->prepare("SELECT id FROM categories WHERE slug=? AND id!=?");
    $existing->bind_param('si', $slug, $id);
    $existing->execute();
    if ($existing->get_result()->num_rows > 0) $errors[] = 'Slug already in use. Choose a different slug.';

    if (empty($errors)) {
        if ($id > 0) {
            $stmt = db()->prepare("UPDATE categories SET name=?, slug=?, description=? WHERE id=?");
            $stmt->bind_param('sssi', $name, $slug, $desc, $id);
        } else {
            $stmt = db()->prepare("INSERT INTO categories (name, slug, description) VALUES (?,?,?)");
            $stmt->bind_param('sss', $name, $slug, $desc);
        }
        $stmt->execute();
        flash($id > 0 ? 'Category updated.' : 'Category created.', 'success');
        header('Location: categories.php'); exit;
    }
    // Re-populate for form
    $editing = ['id'=>$id, 'name'=>$name, 'slug'=>$slug, 'description'=>$desc];
}

// List
$categories = db()->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetch_all(MYSQLI_ASSOC);

adminHeader('Categories', 'categories');
?>

<div class="page-toolbar">
    <div></div>
    <a href="?add=1" class="btn btn-primary">+ Add Category</a>
</div>

<!-- Form -->
<?php if (isset($_GET['add']) || $editing): ?>
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
        <span class="card-title"><?= $editing && !empty($editing['id']) ? 'Edit Category' : 'New Category' ?></span>
    </div>
    <div class="card-body">
        <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= sanitize($e) ?></div><?php endforeach; ?>
        <form method="POST">
            <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= sanitize($editing['name'] ?? '') ?>"
                           oninput="autoSlug(this)" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" id="slugField" class="form-control"
                           value="<?= sanitize($editing['slug'] ?? '') ?>"
                           placeholder="auto-generated">
                    <span class="form-hint">URL-friendly identifier (letters, numbers, hyphens)</span>
                </div>
                <div class="form-group form-col-full">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= sanitize($editing['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div style="display:flex;gap:.75rem;margin-top:1rem">
                <button type="submit" class="btn btn-primary"><?= ($editing && !empty($editing['id'])) ? 'Update' : 'Create' ?> Category</button>
                <a href="categories.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
let userEditedSlug = <?= ($editing && !empty($editing['id'])) ? 'true' : 'false' ?>;
function autoSlug(nameInput) {
    if (userEditedSlug) return;
    document.getElementById('slugField').value = nameInput.value
        .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
document.getElementById('slugField').addEventListener('input', () => { userEditedSlug = true; });
</script>
<?php endif; ?>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Categories</span>
        <span style="font-size:.85rem;color:var(--gray-400)"><?= count($categories) ?> total</span>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Products</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($categories)): ?>
                <tr class="no-results"><td colspan="6">No categories yet.</td></tr>
            <?php else: ?>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><strong><?= sanitize($cat['name']) ?></strong></td>
                    <td><code style="font-size:.8rem;background:var(--gray-100);padding:.15rem .4rem;border-radius:4px"><?= sanitize($cat['slug']) ?></code></td>
                    <td style="color:var(--gray-500);max-width:280px">
                        <?= $cat['description'] ? sanitize(mb_strimwidth($cat['description'], 0, 80, '…')) : '—' ?>
                    </td>
                    <td>
                        <a href="products.php?category=<?= $cat['id'] ?>" style="color:var(--primary)">
                            <?= number_format($cat['product_count']) ?>
                        </a>
                    </td>
                    <td style="white-space:nowrap;font-size:.8rem"><?= date('M j, Y', strtotime($cat['created_at'])) ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="?edit=<?= $cat['id'] ?>" class="btn btn-outline btn-xs">Edit</a>
                            <?php if ($cat['product_count'] == 0): ?>
                            <a href="?delete=<?= $cat['id'] ?>" class="btn btn-danger btn-xs"
                               onclick="return confirmAction(this.href,'Delete Category','Delete &quot;<?= sanitize($cat['name']) ?>&quot; permanently?')">Delete</a>
                            <?php else: ?>
                            <span class="btn btn-ghost btn-xs" title="Reassign products first" style="cursor:not-allowed;opacity:.5">Delete</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php adminFooter(); ?>
