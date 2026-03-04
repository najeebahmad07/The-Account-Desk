// File: assets/js/app.js
// OPSTUS Main JavaScript
// Smart Multi-Center Financial Management

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initThemeToggle();
    initSidebar();
    initAlerts();
    initPageLoader();
    initFormValidation();
    initDeleteConfirm();
});

/* ============================================
   Theme Toggle (Dark/Light Mode)
   ============================================ */
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;

    // Load saved preference
    const savedTheme = localStorage.getItem('opstus_theme');
    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
        updateThemeIcon(true);
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            localStorage.setItem('opstus_theme', isDark ? 'dark' : 'light');
            updateThemeIcon(isDark);

            // Update charts if they exist
            if (typeof updateChartColors === 'function') {
                updateChartColors(isDark);
            }
        });
    }
}

function updateThemeIcon(isDark) {
    const icon = document.querySelector('#themeToggle i');
    const text = document.querySelector('#themeToggle span');
    if (icon) {
        icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    }
    if (text) {
        text.textContent = isDark ? 'Light' : 'Dark';
    }
}

/* ============================================
   Sidebar Toggle
   ============================================ */
function initSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                // Mobile: slide in/out
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            } else {
                // Desktop: collapse
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('opstus_sidebar',
                    body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
            }
        });
    }

    // Close sidebar on overlay click (mobile)
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // Restore sidebar state (desktop)
    const savedSidebar = localStorage.getItem('opstus_sidebar');
    if (savedSidebar === 'collapsed' && window.innerWidth >= 992) {
        body.classList.add('sidebar-collapsed');
    }

    // Handle resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
    });
}

/* ============================================
   Alert Auto-dismiss
   ============================================ */
function initAlerts() {
    const alerts = document.querySelectorAll('.alert-custom');
    alerts.forEach(function(alert) {
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);

        // Close button
        const closeBtn = alert.querySelector('.alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }
    });
}

/* ============================================
   Page Loader
   ============================================ */
function initPageLoader() {
    const loader = document.querySelector('.page-loader');
    if (loader) {
        window.addEventListener('load', function() {
            setTimeout(function() {
                loader.style.opacity = '0';
                setTimeout(function() {
                    loader.style.display = 'none';
                }, 300);
            }, 300);
        });
    }
}

/* ============================================
   Form Validation
   ============================================ */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Prevent negative amounts
    const amountInputs = document.querySelectorAll('input[name="amount"]');
    amountInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            if (parseFloat(this.value) < 0) {
                this.value = '';
            }
        });
    });
}

/* ============================================
   Delete Confirmation
   ============================================ */
function initDeleteConfirm() {
    const deleteButtons = document.querySelectorAll('[data-delete]');
    deleteButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
}

/* ============================================
   Utility: Show/Hide category field based on type
   ============================================ */
function toggleCategory() {
    const typeSelect = document.getElementById('type');
    const categoryGroup = document.getElementById('categoryGroup');

    if (typeSelect && categoryGroup) {
        if (typeSelect.value === 'expense') {
            categoryGroup.style.display = 'block';
            categoryGroup.querySelector('select').required = true;
        } else {
            categoryGroup.style.display = 'none';
            categoryGroup.querySelector('select').required = false;
            categoryGroup.querySelector('select').value = '';
        }
    }
}

/* ============================================
   Number Formatting
   ============================================ */
function formatNumber(num) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(num);
}

/* ============================================
   Show Toast Notification
   ============================================ */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert-custom alert-${type}`;
    toast.style.position = 'fixed';
    toast.style.top = '80px';
    toast.style.right = '24px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    document.body.appendChild(toast);

    setTimeout(function() {
        toast.style.opacity = '0';
        setTimeout(function() { toast.remove(); }, 300);
    }, 4000);
}