<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once 'admin-header.php';

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $allowed = [
        'site_name', 'site_email', 'currency', 'tax_rate',
        'shipping_cost', 'free_shipping_threshold',
        'maintenance_mode', 'store_address', 'store_phone', 'meta_description'
    ];
    $saved = [];
    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            // Numeric sanity checks
            if (in_array($key, ['tax_rate','shipping_cost','free_shipping_threshold'])) {
                $val = (string)max(0, (float)$val);
            }
            setSetting($key, $val);
            $saved[] = $key;
        }
    }
    logActivity('settings_updated', $_SESSION['user_id'], 'Updated: ' . implode(', ', $saved));
    flash('Settings saved successfully.', 'success');
    header('Location: settings.php'); exit;
}

// Load all settings
$s = [
    'site_name'               => getSetting('site_name',               SITE_NAME),
    'site_email'              => getSetting('site_email',              SITE_EMAIL),
    'currency'                => getSetting('currency',                CURRENCY),
    'tax_rate'                => getSetting('tax_rate',                TAX_RATE),
    'shipping_cost'           => getSetting('shipping_cost',           SHIPPING_COST),
    'free_shipping_threshold' => getSetting('free_shipping_threshold', FREE_SHIPPING_THRESHOLD),
    'maintenance_mode'        => getSetting('maintenance_mode',        '0'),
    'store_address'           => getSetting('store_address',           ''),
    'store_phone'             => getSetting('store_phone',             ''),
    'meta_description'        => getSetting('meta_description',        ''),
];

adminHeader('Settings', 'settings');
?>

<form method="POST" style="max-width:760px">
    <?= csrfField() ?>

    <!-- Store Identity -->
    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><span class="card-title">🏪 Store Identity</span></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Site Name <span class="req">*</span></label>
                    <input type="text" name="site_name" class="form-control"
                           value="<?= htmlspecialchars($s['site_name']) ?>" required>
                    <span class="form-hint">Shown in the browser tab and emails.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Store Email</label>
                    <input type="email" name="site_email" class="form-control"
                           value="<?= htmlspecialchars($s['site_email']) ?>">
                    <span class="form-hint">Sender address for order emails.</span>
                </div>
                <div class="form-group form-col-full">
                    <label class="form-label">Meta Description</label>
                    <textarea name="meta_description" class="form-control" rows="2"><?= htmlspecialchars($s['meta_description']) ?></textarea>
                    <span class="form-hint">Short description used by search engines.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing & Shipping -->
    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><span class="card-title">💰 Pricing &amp; Shipping</span></div>
        <div class="card-body">
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label">Currency Symbol</label>
                    <input type="text" name="currency" class="form-control"
                           value="<?= htmlspecialchars($s['currency']) ?>" maxlength="5">
                    <span class="form-hint">e.g. $, £, €, ₦</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Tax Rate</label>
                    <input type="number" name="tax_rate" class="form-control" step="0.001" min="0" max="1"
                           value="<?= htmlspecialchars($s['tax_rate']) ?>">
                    <span class="form-hint">Decimal: 0.08 = 8%</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Shipping Cost</label>
                    <input type="number" name="shipping_cost" class="form-control" step="0.01" min="0"
                           value="<?= htmlspecialchars($s['shipping_cost']) ?>">
                    <span class="form-hint">Default flat-rate fee.</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Free Shipping Threshold</label>
                    <input type="number" name="free_shipping_threshold" class="form-control" step="0.01" min="0"
                           value="<?= htmlspecialchars($s['free_shipping_threshold']) ?>">
                    <span class="form-hint">Orders above this amount get free shipping. Set 0 to disable.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Info -->
    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><span class="card-title">📍 Contact &amp; Location</span></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Store Phone</label>
                    <input type="text" name="store_phone" class="form-control"
                           value="<?= htmlspecialchars($s['store_phone']) ?>" placeholder="+1 (555) 000-0000">
                </div>
                <div class="form-group">
                    <label class="form-label">Store Address</label>
                    <input type="text" name="store_address" class="form-control"
                           value="<?= htmlspecialchars($s['store_address']) ?>" placeholder="123 Main St, City, Country">
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Mode -->
    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><span class="card-title">⚙ Site Status</span></div>
        <div class="card-body">
            <label class="form-check" style="gap:.75rem;align-items:flex-start">
                <input type="checkbox" name="maintenance_mode" value="1"
                       <?= $s['maintenance_mode'] === '1' ? 'checked' : '' ?>
                       style="margin-top:.2rem;width:18px;height:18px;accent-color:#dc2626">
                <div>
                    <div class="form-check-label" style="font-weight:700">🔧 Maintenance Mode</div>
                    <div style="font-size:.8rem;color:var(--gray-400);margin-top:.15rem">
                        When enabled, visitors see a maintenance message. Admin can still access the panel.
                    </div>
                </div>
            </label>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">💾 Save Settings</button>
    <span style="margin-left:1rem;font-size:.8rem;color:var(--gray-400)">Changes take effect immediately.</span>
</form>

<?php adminFooter(); ?>
