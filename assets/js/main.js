// assets/js/main.js — Bibliotheca Library System

document.addEventListener('DOMContentLoaded', () => {

    // ── Flash message auto-dismiss ──────────────────────────────────
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(a => {
        setTimeout(() => {
            a.style.transition = 'opacity 0.4s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 400);
        }, 4500);
    });

    // ── File upload drag-and-drop ───────────────────────────────────
    const uploadArea = document.querySelector('.file-upload-area');
    if (uploadArea) {
        const input = uploadArea.querySelector('input[type="file"]');
        const label = uploadArea.querySelector('.upload-label');

        ['dragenter', 'dragover'].forEach(e =>
            uploadArea.addEventListener(e, ev => { ev.preventDefault(); uploadArea.classList.add('dragover'); })
        );
        ['dragleave', 'drop'].forEach(e =>
            uploadArea.addEventListener(e, ev => { ev.preventDefault(); uploadArea.classList.remove('dragover'); })
        );
        uploadArea.addEventListener('drop', ev => {
            const files = ev.dataTransfer.files;
            if (files[0]) { input.files = files; updateLabel(files[0].name); }
        });
        input.addEventListener('change', () => {
            if (input.files[0]) updateLabel(input.files[0].name);
        });

        function updateLabel(name) {
            if (label) label.textContent = '✓ ' + name;
        }

        // Cover preview
        input.addEventListener('change', () => {
            const preview = document.getElementById('coverPreview');
            if (preview && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        });
    }

    // ── Modal helpers ───────────────────────────────────────────────
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.modal;
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', e => {
            if (e.target === el) {
                document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
            }
        });
    });
    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', e => e.stopPropagation());
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
    });

    // ── Delete confirmation ─────────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm(btn.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });

    // ── Populate edit modal ────────────────────────────────────────
    document.querySelectorAll('[data-edit]').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.dataset.edit);
            const modal = document.getElementById('editModal');
            if (!modal) return;
            Object.entries(data).forEach(([key, val]) => {
                const el = modal.querySelector(`[name="${key}"]`);
                if (el) el.value = val;
            });
        });
    });

    // ── Search filter (client-side live) ───────────────────────────
    const searchInput = document.getElementById('liveSearch');
    if (searchInput) {
        const items = document.querySelectorAll('[data-search-item]');
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.toLowerCase();
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    // ── Tooltip on truncated text ───────────────────────────────────
    document.querySelectorAll('[data-truncate]').forEach(el => {
        if (el.scrollWidth > el.clientWidth) el.title = el.textContent;
    });

    // ── Active nav link ────────────────────────────────────────────
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href').split('/').pop().replace('.php', ''))) {
            link.classList.add('active');
        }
    });

    // ── Admin Charts (Chart.js) ─────────────────────────────────────
    if (typeof Chart !== 'undefined') {
        Chart.defaults.color = '#8a9ab5';
        Chart.defaults.font.family = "'DM Sans', sans-serif";

        // Reservations by month chart
        const resCanvas = document.getElementById('reservationsChart');
        if (resCanvas) {
            const labels = JSON.parse(resCanvas.dataset.labels || '[]');
            const data = JSON.parse(resCanvas.dataset.values || '[]');
            new Chart(resCanvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Reservations',
                        data,
                        backgroundColor: 'rgba(201,168,76,0.2)',
                        borderColor: '#c9a84c',
                        borderWidth: 2,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.05)' } },
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        }

        // Status doughnut chart
        const statusCanvas = document.getElementById('statusChart');
        if (statusCanvas) {
            const labels = JSON.parse(statusCanvas.dataset.labels || '[]');
            const data = JSON.parse(statusCanvas.dataset.values || '[]');
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: ['#4caf7d','#f0a500','#8a9ab5','#e05252','#c9a84c'],
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { padding: 16, usePointStyle: true } }
                    },
                    cutout: '72%'
                }
            });
        }
    }
});
