// Script khusus halaman login/register/lupa-password.

// Toggle show/hide password
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-toggle-password');
        var input = document.getElementById(targetId);
        if (!input) return;
        var icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            btn.setAttribute('aria-label', 'Sembunyikan password');
        } else {
            input.type = 'password';
            if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
            btn.setAttribute('aria-label', 'Tampilkan password');
        }
    });
});

// Indikator kekuatan password sederhana (panjang + variasi karakter)
document.querySelectorAll('[data-strength-target]').forEach(function (input) {
    var meterId = input.getAttribute('data-strength-target');
    var meter = document.getElementById(meterId);
    var label = document.getElementById(meterId + '-label');
    if (!meter) return;
    var bars = meter.querySelectorAll('span');

    function scorePassword(value) {
        var score = 0;
        if (value.length >= 6) score++;
        if (value.length >= 10) score++;
        if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
        if (/[0-9]/.test(value) && /[^A-Za-z0-9]/.test(value)) score++;
        return score; // 0-4
    }

    var levels = [
        { text: '', className: '' },
        { text: 'Lemah', className: 'is-weak' },
        { text: 'Cukup', className: 'is-fair' },
        { text: 'Kuat', className: 'is-good' },
        { text: 'Sangat kuat', className: 'is-strong' }
    ];

    input.addEventListener('input', function () {
        var score = input.value.length ? Math.max(1, scorePassword(input.value)) : 0;
        bars.forEach(function (bar, i) {
            bar.className = i < score ? 'is-filled ' + levels[score].className : '';
        });
        if (label) {
            label.textContent = levels[score].text;
            label.className = 'auth-strength-label ' + levels[score].className;
        }
    });
});
