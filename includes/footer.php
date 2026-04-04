    </div><!-- .content -->
</div><!-- .main-wrapper -->

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}
function toggleUserMenu() {
    document.getElementById('userMenu').classList.toggle('show');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-dropdown')) {
        document.getElementById('userMenu')?.classList.remove('show');
    }
});

// Modal helpers
function openModal(id) {
    document.getElementById(id)?.classList.add('show');
}
function closeModal(id) {
    document.getElementById(id)?.classList.remove('show');
}

// Confirm delete
function confirmDelete(url, msg) {
    if (confirm(msg || 'আপনি কি নিশ্চিত?')) {
        window.location.href = url;
    }
}

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity .5s';
        a.style.opacity = '0';
        setTimeout(() => a.remove(), 500);
    });
}, 4000);

// Print
function printPage() { window.print(); }

// Form validation highlight
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        let valid = true;
        form.querySelectorAll('[required]').forEach(f => {
            if (!f.value.trim()) {
                f.style.borderColor = 'var(--danger)';
                valid = false;
            } else {
                f.style.borderColor = '';
            }
        });
        if (!valid) e.preventDefault();
    });
});
</script>
<?php if (isset($extraJS)) echo $extraJS; ?>
</body>
</html>
