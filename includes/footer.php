<?php
/**
 * Global Footer Component
 */
?>
        <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
            <div class="container-fluid px-4 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                <span>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars(APP_NAME) ?></strong> &bull; Version <?= APP_VERSION ?></span>
                <span class="text-secondary"><i class="bi bi-shield-check text-success me-1"></i> Anti-Cheat & Lab Workstation Monitoring Active</span>
            </div>
        </footer>
    </div><!-- End main-content -->
</div><!-- End app-wrapper -->

<!-- Local Offline jQuery & Bootstrap Bundle -->
<script src="<?= BASE_URL ?>/assets/js/jquery.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>

<!-- Global Toast / Alert Scripts -->
<script>
    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            var bsAlert = bootstrap.Alert.getInstance(alert);
            if (!bsAlert) {
                bsAlert = new bootstrap.Alert(alert);
            }
            if (bsAlert) {
                bsAlert.close();
            }
        });
    }, 5000);
</script>
</body>
</html>
