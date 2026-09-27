/* jshint esversion: 6, browser: true */
/* globals Chart */

// Seluruh interaksi situs: sidebar, modal, konfirmasi hapus, live-search proker,
// progress bar, dan diagram donat — dipicu via atribut data-* di HTML (tanpa onclick inline).
(function () {
    "use strict";

    // Ambil elemen berdasarkan id (pembungkus document.getElementById).

    function getElement(id) {
        return document.getElementById(id);
    }

    function setElementValue(id, value) {
        const element = getElement(id);

        if (element) {
            element.value = value;
        }
    }

    function openModal(id) {
        const modal = getElement(id);

        if (modal) {
            modal.classList.add("open");
        }
    }

    function closeModal(id) {
        const modal = getElement(id);

        if (modal) {
            modal.classList.remove("open");
        }
    }

    function toggleSidebar() {
        const sidebar = getElement("sidebar");
        const overlay = getElement("sidebarOverlay");

        if (sidebar && overlay) {
            sidebar.classList.toggle("open");
            overlay.classList.toggle("open");
        }
    }

    function openEditProker(trigger) {
        setElementValue("edit_proker_id", trigger.dataset.id);
        setElementValue("edit_proker_nama", trigger.dataset.nama);
        setElementValue("edit_proker_mulai", trigger.dataset.mulai);
        setElementValue("edit_proker_selesai", trigger.dataset.selesai);
        setElementValue("edit_proker_divisi", trigger.dataset.divisi);
        openModal("modalEditProker");
    }

    function openEditTask(trigger) {
        setElementValue("edit_task_id", trigger.dataset.id);
        setElementValue("edit_status", trigger.dataset.status);
        setElementValue("edit_lampiran", trigger.dataset.lampiran);
        openModal("modalEditTask");
    }

    function openEditKas(trigger) {
        setElementValue("edit_kas_id", trigger.dataset.id);
        setElementValue("edit_kas_tanggal", trigger.dataset.tanggal);
        setElementValue("edit_kas_jenis", trigger.dataset.jenis);
        setElementValue("edit_kas_proker", trigger.dataset.proker || "");
        setElementValue("edit_kas_keterangan", trigger.dataset.keterangan);
        setElementValue("edit_kas_jumlah", trigger.dataset.jumlah);

        const buktiInfo = getElement("edit_kas_bukti_current");
        const hapusBukti = getElement("edit_kas_hapus_bukti");

        if (buktiInfo) {
            buktiInfo.textContent = trigger.dataset.bukti ? "Bukti saat ini: " + trigger.dataset.bukti : "Belum ada bukti transaksi.";
        }
        if (hapusBukti) {
            hapusBukti.checked = false;
        }

        openModal("modalEditKas");
    }

    function openEditSeriRapat(trigger) {
        setElementValue("edit_rapat_id", trigger.dataset.id);
        setElementValue("edit_rapat_judul", trigger.dataset.judul);
        setElementValue("edit_rapat_deskripsi", trigger.dataset.deskripsi || "");
        setElementValue("edit_rapat_jam_mulai", trigger.dataset.jamMulai);
        setElementValue("edit_rapat_jam_selesai", trigger.dataset.jamSelesai || "");
        setElementValue("edit_rapat_zoom", trigger.dataset.zoom);
        openModal("modalEditSeriRapat");
    }

    function openEditDivisi(trigger) {
        setElementValue("edit_divisi_id", trigger.dataset.id);
        setElementValue("edit_divisi_nama", trigger.dataset.nama);
        setElementValue("edit_divisi_deskripsi", trigger.dataset.deskripsi || "");
        openModal("modalEditDivisi");
    }

    function openKoreksiPresensi(trigger) {
        setElementValue("koreksi_user_id", trigger.dataset.userId);
        setElementValue("koreksi_status", trigger.dataset.status || "Hadir");
        setElementValue("koreksi_keterangan", trigger.dataset.keterangan || "");

        const namaTarget = getElement("koreksi_nama_target");
        if (namaTarget) {
            namaTarget.textContent = trigger.dataset.nama || "";
        }

        openModal("modalKoreksiPresensi");
    }

    function openEditor(trigger) {
        const modalName = trigger.dataset.modal;

        if (modalName === "edit-proker") {
            openEditProker(trigger);
        } else if (modalName === "edit-task") {
            openEditTask(trigger);
        } else if (modalName === "edit-kas") {
            openEditKas(trigger);
        } else if (modalName === "edit-seri-rapat") {
            openEditSeriRapat(trigger);
        } else if (modalName === "koreksi-presensi") {
            openKoreksiPresensi(trigger);
        } else if (modalName === "edit-divisi") {
            openEditDivisi(trigger);
        }
    }

    // Delegasi klik global: satu listener menangani sidebar, buka/tutup modal,
    // tombol edit (proker/task/kas), dan klik overlay untuk menutup modal.
    function handleClick(event) {
        const target = event.target;

        if (!target || !target.closest) {
            return;
        }

        const sidebarToggle = target.closest("[data-sidebar-toggle]");

        if (sidebarToggle) {
            event.preventDefault();
            toggleSidebar();
            return;
        }

        // Toggle show/hide password (dipakai juga di halaman Profil Saya, bukan hanya auth.js).
        const passwordToggle = target.closest("[data-toggle-password]");

        if (passwordToggle) {
            const input = getElement(passwordToggle.getAttribute("data-toggle-password"));

            if (input) {
                const icon = passwordToggle.querySelector("i");
                const showing = input.type === "text";

                input.type = showing ? "password" : "text";

                if (icon) {
                    icon.classList.toggle("fa-eye", showing);
                    icon.classList.toggle("fa-eye-slash", !showing);
                }
                passwordToggle.setAttribute("aria-label", showing ? "Tampilkan password" : "Sembunyikan password");
            }
            return;
        }

        const modalOpener = target.closest("[data-modal-open]");

        if (modalOpener) {
            event.preventDefault();
            openModal(modalOpener.dataset.modalOpen);
            return;
        }

        const modalCloser = target.closest("[data-modal-close]");

        if (modalCloser) {
            event.preventDefault();
            closeModal(modalCloser.dataset.modalClose);
            if (modalCloser.dataset.modalClose === "modalConfirm") {
                pendingConfirmForm = null;
            }
            return;
        }

        if (handleConfirmClick(event)) {
            return;
        }

        const editor = target.closest("[data-modal]");

        if (editor) {
            event.preventDefault();
            openEditor(editor);
            return;
        }

        if (target.classList.contains("neo-modal-overlay")) {
            const overlay = target.closest(".neo-modal-overlay");

            if (overlay) {
                overlay.classList.remove("open");
                if (overlay.id === "modalConfirm") {
                    pendingConfirmForm = null;
                }
            }
        }
    }

    // Konfirmasi hapus via modal neobrutalism (pengganti dialog bawaan browser):
    // form ber-atribut data-confirm ditahan dulu, pesannya ditampilkan di #modalConfirm,
    // dan form baru di-submit secara native saat tombol "Ya, lanjutkan" diklik.
    // Submit native (form.submit()) tidak memicu event submit lagi, jadi tidak ada loop.
    let pendingConfirmForm = null;

    function openConfirmModal(message, form) {
        pendingConfirmForm = form;
        const msg = getElement("confirmMessage");
        if (msg) {
            msg.textContent = message;
        }
        openModal("modalConfirm");
        const ok = getElement("confirmOk");
        if (ok) {
            ok.focus();
        }
    }

    function closeConfirmModal() {
        closeModal("modalConfirm");
        pendingConfirmForm = null;
    }

    function handleSubmit(event) {
        const form = event.target;
        if (!form || !form.getAttribute) {
            return;
        }
        const message = form.getAttribute("data-confirm");

        if (message && !form.hasAttribute("data-confirm-accepted")) {
            event.preventDefault();
            openConfirmModal(message, form);
        }
    }

    function handleConfirmClick(event) {
        const target = event.target;
        if (!target || !target.closest) {
            return false;
        }
        if (target.closest("#confirmOk")) {
            event.preventDefault();
            const form = pendingConfirmForm;
            closeModal("modalConfirm");
            pendingConfirmForm = null;
            if (form) {
                form.setAttribute("data-confirm-accepted", "1");
                form.submit();
            }
            return true;
        }
        if (target.closest("#confirmCancel")) {
            event.preventDefault();
            closeConfirmModal();
            return true;
        }
        return false;
    }

    // Tombol Escape menutup semua modal yang sedang terbuka.
    function handleKeydown(event) {
        if (event.key === "Escape") {
            document.querySelectorAll(".neo-modal-overlay.open").forEach(function (modal) {
                modal.classList.remove("open");
            });
            pendingConfirmForm = null;
        }
    }

    function escapeHtml(value) {
        const element = document.createElement("div");

        element.textContent = value === null || value === undefined ? "" : String(value);
        return element.innerHTML;
    }

    function showSuggestions(box, content) {
        box.innerHTML = content;
        box.classList.add("is-visible");
    }

    // Live-search proker: debounce 300ms lalu fetch JSON search.php dan tampilkan saran.
    function initializeProkerSearch() {
        const input = getElement("prokerSearchInput");
        const suggestions = getElement("prokerSearchSuggestions");

        if (!input || !suggestions) {
            return;
        }

        let debounceTimer = null;

        function renderSuggestions(data) {
            if (!Array.isArray(data) || data.length === 0) {
                showSuggestions(
                    suggestions,
                    '<div class="suggestion-empty">Tidak ditemukan proker yang cocok.</div>'
                );
                return;
            }

            const links = data.map(function (item) {
                return '<a href="detail.php?id=' + encodeURIComponent(item.id) + '">' +
                    '<div class="suggestion-title">' + escapeHtml(item.nama_proker) + '</div>' +
                    '<div class="suggestion-meta">' + escapeHtml(item.nama_divisi) +
                    ' &middot; ' + escapeHtml(item.status) + '</div>' +
                    '</a>';
            });

            showSuggestions(suggestions, links.join(""));
        }

        function showSearchError() {
            showSuggestions(
                suggestions,
                '<div class="suggestion-empty">Gagal memuat hasil pencarian.</div>'
            );
        }

        input.addEventListener("input", function () {
            const query = this.value.trim();

            window.clearTimeout(debounceTimer);

            if (query.length === 0) {
                suggestions.classList.remove("is-visible");
                suggestions.innerHTML = "";
                return;
            }

            debounceTimer = window.setTimeout(function () {
                window.fetch("search.php?q=" + encodeURIComponent(query))
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error("Permintaan pencarian gagal.");
                        }

                        return response.json();
                    })
                    .then(renderSuggestions)
                    .catch(showSearchError);
            }, 300);
        });

        input.addEventListener("focus", function () {
            if (this.value.trim().length > 0 && suggestions.innerHTML !== "") {
                suggestions.classList.add("is-visible");
            }
        });

        document.addEventListener("click", function (event) {
            if (!suggestions.contains(event.target) && event.target !== input) {
                suggestions.classList.remove("is-visible");
            }
        });
    }

    // Dropdown notifikasi topbar: klik lonceng untuk buka/tutup, klik di luar menutup.
    function initializeNotifDropdown() {
        const bell = getElement("notifBell");
        const dropdown = getElement("notifDropdown");

        if (!bell || !dropdown) {
            return;
        }

        bell.addEventListener("click", function (event) {
            event.stopPropagation();
            const isOpen = dropdown.classList.toggle("is-open");
            bell.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });

        document.addEventListener("click", function (event) {
            if (!dropdown.classList.contains("is-open")) {
                return;
            }
            if (!dropdown.contains(event.target) && event.target !== bell && !bell.contains(event.target)) {
                dropdown.classList.remove("is-open");
                bell.setAttribute("aria-expanded", "false");
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && dropdown.classList.contains("is-open")) {
                dropdown.classList.remove("is-open");
                bell.setAttribute("aria-expanded", "false");
            }
        });
    }

    // Indikator upload file: form yang benar-benar di-submit sambil membawa file
    // menampilkan overlay "Mengunggah..." + mengunci tombol submit, supaya file
    // besar tidak terasa macet. Dilewati bila submit dibatalkan (mis. modal
    // konfirmasi masih menunggu jawaban) — terlihat dari event.defaultPrevented.
    function initializeUploadIndicator() {
        document.addEventListener("submit", function (event) {
            if (event.defaultPrevented) {
                return;
            }
            const form = event.target;
            if (!form || !form.querySelector) {
                return;
            }
            const fileInput = form.querySelector('input[type="file"]');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                return;
            }
            const overlay = getElement("uploadOverlay");
            if (overlay) {
                overlay.classList.add("is-visible");
                overlay.setAttribute("aria-hidden", "false");
            }
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                if (!btn.hasAttribute("data-original-label")) {
                    btn.setAttribute("data-original-label", btn.innerHTML);
                }
                btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Mengunggah...';
            }
        });

        // Kalau user kembali via tombol Back (bfcache), sembunyikan overlay lagi.
        window.addEventListener("pageshow", function () {
            const overlay = getElement("uploadOverlay");
            if (overlay) {
                overlay.classList.remove("is-visible");
                overlay.setAttribute("aria-hidden", "true");
            }
            document.querySelectorAll('button[type="submit"][data-original-label]').forEach(function (btn) {
                btn.disabled = false;
                btn.innerHTML = btn.getAttribute("data-original-label");
                btn.removeAttribute("data-original-label");
            });
        });
    }

    function getChartNumber(canvas, name) {
        const value = Number(canvas.getAttribute(name));

        return Number.isFinite(value) ? value : 0;
    }

    // Parse atribut data-* berisi JSON (dipakai chart yang datanya berupa array, bukan angka tunggal).
    function getChartJSON(canvas, name) {
        try {
            return JSON.parse(canvas.getAttribute(name) || "[]");
        } catch (err) {
            return [];
        }
    }

    // Rupiah singkat untuk sumbu/tooltip chart (1500000 -> "1,5jt").
    function formatRupiahSingkat(value) {
        if (value >= 1000000000) {
            return "Rp " + (value / 1000000000).toFixed(1).replace(".", ",") + "M";
        }
        if (value >= 1000000) {
            return "Rp " + (value / 1000000).toFixed(1).replace(".", ",") + "jt";
        }
        if (value >= 1000) {
            return "Rp " + (value / 1000).toFixed(0) + "rb";
        }

        return "Rp " + value;
    }

    // Bar chart tren kas bulanan (Keuangan): Pemasukan vs Pengeluaran per bulan.
    function initializeKasTrendChart() {
        const canvas = document.querySelector('[data-chart="kas-trend"]');

        if (!canvas || typeof Chart === "undefined") {
            return;
        }

        const context = canvas.getContext("2d");

        if (!context) {
            return;
        }

        new Chart(context, {
            type: "bar",
            data: {
                labels: getChartJSON(canvas, "data-labels"),
                datasets: [
                    {
                        label: "Pemasukan",
                        data: getChartJSON(canvas, "data-pemasukan"),
                        backgroundColor: "#37EB5F",
                        borderColor: "#1a1a1a",
                        borderWidth: 2,
                        borderRadius: 5,
                        maxBarThickness: 34
                    },
                    {
                        label: "Pengeluaran",
                        data: getChartJSON(canvas, "data-pengeluaran"),
                        backgroundColor: "#FF6B6B",
                        borderColor: "#1a1a1a",
                        borderWidth: 2,
                        borderRadius: 5,
                        maxBarThickness: 34
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: "easeOutQuart" },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: "Space Grotesk", weight: "700" } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: "#eee" },
                        ticks: {
                            font: { family: "Space Grotesk", weight: "700" },
                            callback: function (value) { return formatRupiahSingkat(value); }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            font: { family: "Space Grotesk", weight: "800", size: 12 },
                            boxWidth: 14,
                            boxHeight: 14
                        }
                    },
                    tooltip: {
                        backgroundColor: "#1a1a1a",
                        titleFont: { family: "Space Grotesk", weight: "800", size: 13 },
                        bodyFont: { family: "Space Grotesk", weight: "700", size: 13 },
                        padding: 10,
                        cornerRadius: 0,
                        borderColor: "#ffffff",
                        borderWidth: 2,
                        boxPadding: 5,
                        callbacks: {
                            label: function (item) {
                                return " " + item.dataset.label + ": " + formatRupiahSingkat(item.raw);
                            }
                        }
                    }
                }
            }
        });
    }

    // Diagram donat status proker (Chart.js); angka dibaca dari atribut data-* canvas.
    function initializeCharts() {
        const canvas = document.querySelector('[data-chart="proker"]');

        if (!canvas || typeof Chart === "undefined") {
            return;
        }

        const context = canvas.getContext("2d");

        if (!context) {
            return;
        }

        new Chart(context, {
            type: "doughnut",
            data: {
                labels: ["To-do", "In Progress", "Done"],
                datasets: [{
                    data: [
                        getChartNumber(canvas, "data-todo"),
                        getChartNumber(canvas, "data-in-progress"),
                        getChartNumber(canvas, "data-done")
                    ],
                    backgroundColor: ["#FF90E8", "#FFD900", "#37EB5F"],
                    borderColor: "#1a1a1a",
                    borderWidth: 3,
                    borderRadius: 4,
                    spacing: 2,
                    hoverOffset: 4,
                    hoverBorderColor: "#1a1a1a"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                devicePixelRatio: window.devicePixelRatio || 1,
                layout: { padding: 8 },
                cutout: "68%",
                animation: {
                    animateScale: true,
                    duration: 700,
                    easing: "easeOutBack"
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "#1a1a1a",
                        titleFont: { family: "Space Grotesk", weight: "800", size: 13 },
                        bodyFont: { family: "Space Grotesk", weight: "700", size: 13 },
                        padding: 10,
                        cornerRadius: 0,
                        borderColor: "#ffffff",
                        borderWidth: 2,
                        boxPadding: 5,
                        displayColors: true,
                        callbacks: {
                            label: function (item) {
                                const value = item.raw;
                                const dataset = item.dataset.data;
                                const total = dataset.reduce(function (sum, n) { return sum + n; }, 0);
                                const percent = total ? Math.round((value / total) * 100) : 0;

                                return " " + item.label + ": " + value + " (" + percent + "%)";
                            }
                        }
                    }
                }
            }
        });
    }

    // Terapkan lebar progress bar dari atribut data-progress ke variabel CSS --progress
    // (HTML bebas style inline; pengecatan dilakukan di sini).
    function initializeProgressBars() {
        document.querySelectorAll("[data-progress]").forEach(function (bar) {
            const rawValue = bar.getAttribute("data-progress");
            const value = Number(rawValue);

            if (rawValue !== null && Number.isFinite(value)) {
                bar.style.setProperty("--progress", value + "%");
            }
        });
    }

    function initialize() {
        document.addEventListener("click", handleClick);
        document.addEventListener("submit", handleSubmit);
        document.addEventListener("keydown", handleKeydown);
        initializeProkerSearch();
        initializeNotifDropdown();
        initializeUploadIndicator();
        initializeProgressBars();
        initializeKasTrendChart();
        initializeCharts();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize);
    } else {
        initialize();
    }
}());
