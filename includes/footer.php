            </div> <!-- /content-area : penutup area konten yang dibuka di header.php -->
        </main>
    </div>

    <!-- Modal konfirmasi hapus global (pengganti window.confirm bawaan browser):
         dibuka oleh app.js untuk SEMUA form ber-atribut data-confirm, supaya
         visualnya konsisten dengan tema neobrutalism. -->
    <div id="modalConfirm" class="neo-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <div class="neo-modal-box confirm-box">
            <div class="confirm-icon" aria-hidden="true"><i class="fas fa-triangle-exclamation"></i></div>
            <h3 class="modal-title text-center" id="confirmTitle">Yakin lanjutkan?</h3>
            <p class="confirm-message" id="confirmMessage"></p>
            <div class="form-actions confirm-actions">
                <button type="button" class="neo-btn neo-btn-muted" id="confirmCancel">Batal</button>
                <button type="button" class="neo-btn neo-btn-danger" id="confirmOk"><i class="fas fa-trash" aria-hidden="true"></i> Ya, lanjutkan</button>
            </div>
        </div>
    </div>

    <!-- Overlay indikator upload: ditampilkan app.js saat form berisi file
         benar-benar di-submit, supaya file besar tidak terasa "macet". -->
    <div id="uploadOverlay" class="upload-overlay" role="status" aria-hidden="true">
        <div class="upload-box">
            <div class="upload-spinner" aria-hidden="true"></div>
            <strong>Mengunggah file...</strong>
            <small>Jangan tutup atau muat ulang halaman ini.</small>
            <div class="upload-bar"><div class="upload-bar-fill"></div></div>
        </div>
    </div>

    <!-- Chart.js (CDN) untuk diagram dashboard + app.js berisi seluruh interaksi situs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
