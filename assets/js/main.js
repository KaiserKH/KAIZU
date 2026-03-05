// ShopPHP — Main JavaScript

document.addEventListener('DOMContentLoaded', function () {

    // ── Sticky header shadow ─────────────────────────────────
    const header = document.querySelector('.header');
    if (header) {
        window.addEventListener('scroll', () => {
            header.classList.toggle('scrolled', window.scrollY > 10);
        }, { passive: true });
    }

    // ── Animated hamburger menu ──────────────────────────────
    const hamburger = document.getElementById('hamburger');
    const navLinks  = document.getElementById('navLinks');
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', () => {
            const open = navLinks.classList.toggle('open');
            hamburger.classList.toggle('active', open);
            hamburger.setAttribute('aria-expanded', open);
        });
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                navLinks.classList.remove('open');
                hamburger.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ── Mobile filter drawer ─────────────────────────────────
    const filterBtn     = document.getElementById('filterToggleBtn');
    const shopSidebar   = document.getElementById('shopSidebar');
    const filterOverlay = document.getElementById('filterOverlay');
    const sidebarClose  = document.getElementById('sidebarClose');

    function openFilters() {
        shopSidebar?.classList.add('open');
        filterOverlay?.classList.add('active');
        if (sidebarClose) sidebarClose.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeFilters() {
        shopSidebar?.classList.remove('open');
        filterOverlay?.classList.remove('active');
        document.body.style.overflow = '';
    }
    filterBtn?.addEventListener('click', openFilters);
    filterOverlay?.addEventListener('click', closeFilters);
    sidebarClose?.addEventListener('click', closeFilters);

    // ── Auto-dismiss alerts ──────────────────────────────────
    document.querySelectorAll('.alert:not(.alert-static)').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .4s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // ── Quantity buttons (product detail) ────────────────────
    const qtyInput = document.getElementById('quantity');
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!qtyInput) return;
            let val = parseInt(qtyInput.value) || 1;
            const max = parseInt(qtyInput.max) || 99;
            if (btn.dataset.action === 'plus'  && val < max)  qtyInput.value = val + 1;
            if (btn.dataset.action === 'minus' && val > 1)    qtyInput.value = val - 1;
        });
    });

    // ── Add to Cart (AJAX) ────────────────────────────────────
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            const qtyEl     = document.getElementById('quantity');
            const quantity  = qtyEl ? parseInt(qtyEl.value) || 1 : 1;
            const original  = this.innerHTML;
            this.disabled   = true;
            this.innerHTML  = '<span style="opacity:.7">Adding…</span>';

            try {
                const res  = await fetch(SITE_URL + '/api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add', product_id: productId, quantity })
                });
                const data = await res.json();
                if (data.success) {
                    updateCartCount(data.cart_count);
                    showToast('✓ Added to cart!', 'success');
                    // Bounce animation on cart icon
                    document.querySelectorAll('.cart-icon').forEach(el => {
                        el.style.transform = 'scale(1.3)';
                        setTimeout(() => el.style.transform = '', 200);
                    });
                } else {
                    showToast(data.message || 'Could not add item.', 'danger');
                }
            } catch {
                showToast('Network error. Please try again.', 'danger');
            } finally {
                this.disabled  = false;
                this.innerHTML = original;
            }
        });
    });

    // ── Cart: update quantity ─────────────────────────────────
    document.querySelectorAll('.cart-qty-input').forEach(input => {
        let timer;
        input.addEventListener('change', function () {
            clearTimeout(timer);
            timer = setTimeout(() => updateCartItem(this.dataset.cartId, parseInt(this.value) || 1), 600);
        });
    });

    // ── Cart: remove item ─────────────────────────────────────
    document.querySelectorAll('.remove-item-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const row = document.querySelector(`[data-row-id="${this.dataset.cartId}"]`);
            if (row) { row.style.opacity = '.4'; row.style.pointerEvents = 'none'; }
            await updateCartItem(this.dataset.cartId, 0);
        });
    });

    // ── Wishlist toggle ───────────────────────────────────────
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const productId = this.dataset.productId;
            const me = this;
            me.style.transform = 'scale(1.3)';
            setTimeout(() => me.style.transform = '', 200);

            try {
                const res  = await fetch(SITE_URL + '/api/wishlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId })
                });
                const data = await res.json();
                if (data.success) {
                    me.classList.toggle('active', data.in_wishlist);
                    me.textContent = data.in_wishlist ? '♥' : '♡';
                    showToast(data.in_wishlist ? '♥ Added to wishlist' : 'Removed from wishlist', data.in_wishlist ? 'success' : 'info');
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } catch {
                showToast('An error occurred.', 'danger');
            }
        });
    });

    // ── Tabs ──────────────────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const target    = this.dataset.tab;
            const container = this.closest('.tabs-container') || document;
            container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            container.querySelectorAll('.tab-panel, .tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const panel = document.getElementById(target);
            if (panel) panel.classList.add('active');
        });
    });

    // ── Product image gallery ─────────────────────────────────
    document.querySelectorAll('.thumb-img').forEach(img => {
        img.addEventListener('click', function () {
            const main = document.getElementById('mainProductImage');
            if (main) {
                main.style.opacity = '0';
                setTimeout(() => { main.src = this.src; main.style.opacity = '1'; }, 150);
                document.querySelectorAll('.thumb-img').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });
    if (document.getElementById('mainProductImage')) {
        document.getElementById('mainProductImage').style.transition = 'opacity .15s';
    }

    // ── Review star rating ────────────────────────────────────
    const ratingInput = document.getElementById('ratingInput');
    const starBtns    = document.querySelectorAll('.star-select');
    starBtns.forEach(star => {
        star.addEventListener('mouseover', function () {
            starBtns.forEach(s => s.classList.toggle('hover', parseInt(s.dataset.val) <= parseInt(this.dataset.val)));
        });
        star.addEventListener('mouseout', () => starBtns.forEach(s => s.classList.remove('hover')));
        star.addEventListener('click', function () {
            if (ratingInput) ratingInput.value = this.dataset.val;
            starBtns.forEach(s => s.classList.toggle('selected', parseInt(s.dataset.val) <= parseInt(this.dataset.val)));
        });
    });
});

// ── Helpers ───────────────────────────────────────────────────────────────────

async function updateCartItem(cartId, quantity) {
    try {
        const res  = await fetch(SITE_URL + '/api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: quantity > 0 ? 'update' : 'remove', cart_id: cartId, quantity })
        });
        const data = await res.json();
        if (data.success) {
            updateCartCount(data.cart_count);
            if (quantity <= 0) {
                const row = document.querySelector(`[data-row-id="${cartId}"]`);
                if (row) {
                    row.style.transition = 'opacity .3s, transform .3s';
                    row.style.opacity = '0'; row.style.transform = 'translateX(-10px)';
                    setTimeout(() => row.remove(), 300);
                }
            }
            updateCartSummary(data.totals);
        }
    } catch {
        showToast('An error occurred.', 'danger');
    }
}

function updateCartCount(count) {
    document.querySelectorAll('#cart-count, .cart-count').forEach(el => {
        el.textContent = count;
        el.style.display = count > 0 ? '' : 'none';
    });
}

function updateCartSummary(totals) {
    if (!totals) return;
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('summary-subtotal', totals.subtotal_fmt);
    set('summary-shipping', totals.shipping_fmt);
    set('summary-tax',      totals.tax_fmt);
    set('summary-total',    totals.total_fmt);
}

let toastContainer;
function showToast(message, type = 'success') {
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;max-width:320px;';
        document.body.appendChild(toastContainer);
    }
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-static`;
    toast.style.cssText = 'box-shadow:0 8px 24px rgba(0,0,0,.12);transform:translateX(110%);transition:transform .3s ease;';
    toast.innerHTML = message;
    toastContainer.appendChild(toast);
    requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });
    setTimeout(() => {
        toast.style.transform = 'translateX(110%)';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}


document.addEventListener('DOMContentLoaded', function () {

    // ── Mobile Menu ──────────────────────────────────────────────
    const menuBtn   = document.getElementById('mobileMenuBtn');
    const navLinks  = document.getElementById('navLinks');
    if (menuBtn && navLinks) {
        menuBtn.addEventListener('click', () => navLinks.classList.toggle('open'));
    }

    // ── Auto-dismiss alerts ──────────────────────────────────────
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => alert.remove(), 5000);
    });

    // ── Quantity buttons (product detail) ────────────────────────
    const qtyInput = document.getElementById('quantity');
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!qtyInput) return;
            let val = parseInt(qtyInput.value) || 1;
            const max = parseInt(qtyInput.max) || 99;
            if (btn.dataset.action === 'plus'  && val < max)  qtyInput.value = val + 1;
            if (btn.dataset.action === 'minus' && val > 1)    qtyInput.value = val - 1;
        });
    });

    // ── Add to Cart (AJAX) ────────────────────────────────────────
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            const qtyEl     = document.getElementById('quantity');
            const quantity  = qtyEl ? parseInt(qtyEl.value) || 1 : 1;
            const original  = this.innerHTML;
            this.disabled  = true;
            this.innerHTML = 'Adding…';

            try {
                const res  = await fetch(SITE_URL + '/api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add', product_id: productId, quantity })
                });
                const data = await res.json();
                if (data.success) {
                    updateCartCount(data.cart_count);
                    showToast('Added to cart!', 'success');
                } else {
                    showToast(data.message || 'Failed to add to cart.', 'danger');
                }
            } catch {
                showToast('An error occurred.', 'danger');
            } finally {
                this.disabled = false;
                this.innerHTML = original;
            }
        });
    });

    // ── Cart: Update quantity ─────────────────────────────────────
    document.querySelectorAll('.cart-qty-input').forEach(input => {
        let timer;
        input.addEventListener('change', function () {
            clearTimeout(timer);
            const cartId  = this.dataset.cartId;
            const qty     = parseInt(this.value) || 1;
            timer = setTimeout(() => updateCartItem(cartId, qty), 500);
        });
    });

    // ── Cart: Remove item ─────────────────────────────────────────
    document.querySelectorAll('.remove-item-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            if (!confirm('Remove this item?')) return;
            const cartId = this.dataset.cartId;
            await updateCartItem(cartId, 0);
        });
    });

    // ── Wishlist Toggle ───────────────────────────────────────────
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const productId = this.dataset.productId;
            try {
                const res  = await fetch(SITE_URL + '/api/wishlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId })
                });
                const data = await res.json();
                if (data.success) {
                    this.classList.toggle('active', data.in_wishlist);
                    this.textContent = data.in_wishlist ? '♥' : '♡';
                    showToast(data.in_wishlist ? 'Added to wishlist!' : 'Removed from wishlist', 'success');
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } catch {
                showToast('An error occurred.', 'danger');
            }
        });
    });

    // ── Tabs ──────────────────────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const target  = this.dataset.tab;
            const container = this.closest('.tabs-container') || document;
            container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            container.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const panel = container.querySelector('#' + target);
            if (panel) panel.classList.add('active');
        });
    });

    // ── Product image gallery ──────────────────────────────────────
    document.querySelectorAll('.thumb-img').forEach(img => {
        img.addEventListener('click', function () {
            const main = document.getElementById('mainProductImage');
            if (main) {
                main.src = this.src;
                document.querySelectorAll('.thumb-img').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });

    // ── Checkout: same as billing toggle ─────────────────────────
    const sameAddrChk = document.getElementById('sameAddress');
    if (sameAddrChk) {
        sameAddrChk.addEventListener('change', function () {
            document.querySelectorAll('.shipping-only').forEach(el => {
                el.style.display = this.checked ? 'none' : 'block';
            });
        });
    }

    // ── Rating stars (review form) ────────────────────────────────
    const ratingInput   = document.getElementById('ratingInput');
    const starBtns      = document.querySelectorAll('.star-select');
    starBtns.forEach(star => {
        star.addEventListener('mouseover', function () {
            const val = this.dataset.val;
            starBtns.forEach(s => s.classList.toggle('hover', s.dataset.val <= val));
        });
        star.addEventListener('mouseout', () => starBtns.forEach(s => s.classList.remove('hover')));
        star.addEventListener('click', function () {
            const val = this.dataset.val;
            if (ratingInput) ratingInput.value = val;
            starBtns.forEach(s => s.classList.toggle('selected', s.dataset.val <= val));
        });
    });
});

// ── Helpers ───────────────────────────────────────────────────────────────────

async function updateCartItem(cartId, quantity) {
    try {
        const res  = await fetch(SITE_URL + '/api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: quantity > 0 ? 'update' : 'remove', cart_id: cartId, quantity })
        });
        const data = await res.json();
        if (data.success) {
            updateCartCount(data.cart_count);
            if (quantity <= 0) {
                const row = document.querySelector(`[data-row-id="${cartId}"]`);
                if (row) row.remove();
            }
            updateCartSummary(data.totals);
        }
    } catch {
        showToast('An error occurred.', 'danger');
    }
}

function updateCartCount(count) {
    document.querySelectorAll('#cart-count, .cart-count').forEach(el => {
        el.textContent = count;
        el.style.display = count > 0 ? '' : 'none';
    });
}

function updateCartSummary(totals) {
    if (!totals) return;
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('summary-subtotal', totals.subtotal_fmt);
    set('summary-shipping', totals.shipping_fmt);
    set('summary-tax',      totals.tax_fmt);
    set('summary-total',    totals.total_fmt);
}

function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = 'min-width:280px;box-shadow:0 4px 12px rgba(0,0,0,.15);animation:slideIn .3s ease;';
    toast.innerHTML = message;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, 3000);
}

// CSS animation for toast
const style = document.createElement('style');
style.textContent = '@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}';
document.head.appendChild(style);
