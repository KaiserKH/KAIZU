<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once 'admin-header.php';

$uploadDir = __DIR__ . '/../assets/images/';
$categories = getCategories();
$errors     = [];
$editing    = null;

// Helper: handle image upload
function handleImageUpload($fieldName, $existingImage = '') {
    global $uploadDir, $errors;
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingImage; // no new upload
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Image upload failed (error '.$file['error'].').'; return $existingImage; }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) { $errors[] = 'Image must be under 5MB.'; return $existingImage; }

    $mime = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) { $errors[] = 'Only JPG, PNG, GIF, WEBP images are allowed.'; return $existingImage; }

    $ext      = $allowed[$mime];
    $filename = uniqid('product_', true) . '.' . $ext;
    $dest     = $uploadDir . $filename;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        // Delete old image if replacing
        if ($existingImage && file_exists($uploadDir . $existingImage)) {
            @unlink($uploadDir . $existingImage);
        }
        return $filename;
    }
    $errors[] = 'Could not save uploaded image.';
    return $existingImage;
}

// Edit
if (isset($_GET['edit'])) {
    $editing = getProductById((int)$_GET['edit']);
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $p  = getProductById($id);
    if ($p && $p['image'] && file_exists($uploadDir . $p['image'])) {
        @unlink($uploadDir . $p['image']);
    }
    db()->prepare("DELETE FROM products WHERE id=?")->bind_param('i',$id)->execute();
    flash('Product deleted.', 'success');
    header('Location: products.php'); exit;
}

// Save (create or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $catId     = (int)($_POST['category_id'] ?? 0);
    $desc      = trim($_POST['description'] ?? '');
    $price     = (float)($_POST['price'] ?? 0);
    $salePrice = ($_POST['sale_price'] !== '') ? (float)$_POST['sale_price'] : null;
    $stock     = (int)($_POST['stock'] ?? 0);
    $featured  = isset($_POST['featured']) ? 1 : 0;
    $status    = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    if (!$name)     $errors[] = 'Product name is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';

    // Image upload (validate before checking errors to collect all errors)
    $currentImage = $id > 0 ? (getProductById($id)['image'] ?? '') : '';
    $image = handleImageUpload('image_upload', $currentImage);

    if (empty($errors)) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        // Ensure unique slug
        $existing = db()->prepare("SELECT id FROM products WHERE slug=? AND id!=?");
        $existing->bind_param('si', $slug, $id);
        $existing->execute();
        if ($existing->get_result()->num_rows > 0) {
            $slug .= '-' . ($id ?: time());
        }

        if ($id > 0) {
            $stmt = db()->prepare("UPDATE products SET category_id=?,name=?,slug=?,description=?,price=?,sale_price=?,stock=?,image=?,featured=?,status=? WHERE id=?");
            $stmt->bind_param('isssddiissi', $catId,$name,$slug,$desc,$price,$salePrice,$stock,$image,$featured,$status,$id);
        } else {
            $stmt = db()->prepare("INSERT INTO products (category_id,name,slug,description,price,sale_price,stock,image,featured,status) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('isssddisis', $catId,$name,$slug,$desc,$price,$salePrice,$stock,$image,$featured,$status);
        }
        $stmt->execute();
        flash($id > 0 ? 'Product updated!' : 'Product created!', 'success');
        header('Location: products.php'); exit;
    }
}

// List
$search   = trim($_GET['search'] ?? '');
$catFilter = (int)($_GET['category'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where = "WHERE 1";
$params = []; $types = '';
if ($search)    { $where .= " AND p.name LIKE ?"; $params[] = "%$search%"; $types .= 's'; }
if ($catFilter) { $where .= " AND p.category_id=?"; $params[] = $catFilter; $types .= 'i'; }

$countStmt = db()->prepare("SELECT COUNT(*) AS c FROM products p $where");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['c'];

$listStmt = db()->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
$listTypes  = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$products = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

adminHeader('Products', 'products');
?>

<div class="page-toolbar">
    <form method="GET" class="toolbar-search" style="gap:.5rem;flex-wrap:wrap">
        <input type="text" name="search" class="form-control" placeholder="Search products…" value="<?= sanitize($search) ?>">
        <select name="category" class="form-control" style="width:auto">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $catFilter==$cat['id']?'selected':'' ?>><?= sanitize($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm">Filter</button>
        <?php if ($search || $catFilter): ?><a href="products.php" class="btn btn-ghost btn-sm">✕ Clear</a><?php endif; ?>
    </form>
    <a href="?add=1" class="btn btn-primary">+ Add Product</a>
</div>

<!-- Add / Edit Form -->
<?php if (isset($_GET['add']) || $editing): ?>
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
        <span class="card-title"><?= $editing ? 'Edit Product: ' . sanitize($editing['name']) : 'New Product' ?></span>
        <a href="products.php" class="btn btn-ghost btn-sm">✕ Cancel</a>
    </div>
    <div class="card-body">
        <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= sanitize($e) ?></div><?php endforeach; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

            <!-- Section: Basic Info -->
            <div class="form-section">
                <div class="form-section-title">Basic Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Product Name <span class="req">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= sanitize($editing['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control">
                            <option value="0">— No Category —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($editing['category_id'] ?? 0)==$cat['id']?'selected':'' ?>>
                                <?= sanitize($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-col-full">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= sanitize($editing['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section: Pricing & Stock -->
            <div class="form-section">
                <div class="form-section-title">Pricing & Stock</div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Regular Price <span class="req">*</span></label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0.01" required
                               value="<?= $editing['price'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sale Price</label>
                        <input type="number" name="sale_price" class="form-control" step="0.01" min="0"
                               value="<?= $editing['sale_price'] ?? '' ?>" placeholder="Leave blank for none">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock" class="form-control" min="0"
                               value="<?= $editing['stock'] ?? 0 ?>">
                    </div>
                </div>
            </div>

            <!-- Section: Image Upload -->
            <div class="form-section">
                <div class="form-section-title">Product Image</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="file-drop" for="imageInput">
                            <input type="file" id="imageInput" name="image_upload" accept="image/*" onchange="previewImage(this)">
                            <div class="file-drop-icon">🖼</div>
                            <div class="file-drop-text">
                                <strong>Click to upload</strong> or drag & drop<br>
                                <small>JPG, PNG, WEBP up to 5MB</small>
                            </div>
                        </label>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;justify-content:center">
                        <div>
                            <p style="font-size:.78rem;color:var(--gray-400);margin-bottom:.5rem;text-align:center">Current Image</p>
                            <img id="imgPreview" src="<?= productImageUrl($editing['image'] ?? '') ?>"
                                 alt="Preview" class="img-preview" style="margin:0 auto">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Settings -->
            <div class="form-section">
                <div class="form-section-title">Settings</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active"   <?= ($editing['status'] ?? 'active')==='active'?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= ($editing['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group" style="justify-content:flex-end">
                        <label class="form-check" style="padding-bottom:.5rem">
                            <input type="checkbox" name="featured" value="1" <?= !empty($editing['featured'])?'checked':'' ?>>
                            <span class="form-check-label">⭐ Featured Product</span>
                        </label>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:.75rem">
                <button type="submit" class="btn btn-primary">
                    <?= $editing ? '💾 Update Product' : '➕ Create Product' ?>
                </button>
                <a href="products.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('imgPreview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php endif; ?>

<!-- Products Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Products</span>
        <span style="color:var(--gray-400);font-size:.85rem"><?= number_format($total) ?> product<?= $total!=1?'s':'' ?></span>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Featured</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr class="no-results"><td colspan="8">No products found.</td></tr>
            <?php else: ?>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><img src="<?= productImageUrl($p['image']) ?>" alt="<?= sanitize($p['name']) ?>"></td>
                    <td>
                        <strong><?= sanitize($p['name']) ?></strong>
                        <div style="font-size:.75rem;color:var(--gray-400)">ID #<?= $p['id'] ?></div>
                    </td>
                    <td><?= sanitize($p['cat_name'] ?? '—') ?></td>
                    <td>
                        <?php if ($p['sale_price']): ?>
                            <strong style="color:var(--danger)"><?= price($p['sale_price']) ?></strong>
                            <del style="color:var(--gray-400);font-size:.78rem;display:block"><?= price($p['price']) ?></del>
                        <?php else: ?>
                            <strong><?= price($p['price']) ?></strong>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-weight:700;color:<?= $p['stock']==0?'#dc2626':($p['stock']<=5?'#d97706':'inherit') ?>">
                            <?= $p['stock'] ?>
                            <?php if ($p['stock']==0): ?><span style="font-size:.7rem;font-weight:400"> Out</span><?php endif; ?>
                        </span>
                    </td>
                    <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td style="text-align:center"><?= $p['featured'] ? '⭐' : '' ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="?edit=<?= $p['id'] ?>" class="btn btn-outline btn-xs">Edit</a>
                            <a href="?delete=<?= $p['id'] ?>" class="btn btn-danger btn-xs"
                               onclick="return confirmAction(this.href,'Delete Product','Permanently delete &quot;<?= sanitize($p['name']) ?>&quot;?')">Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$base = SITE_URL . '/admin/products.php?' . http_build_query(array_filter(['search'=>$search,'category'=>$catFilter?:null]));
echo paginate($total, $perPage, $page, $base);
adminFooter();
?>
