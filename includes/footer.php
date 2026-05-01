<?php
/**
 * includes/footer.php
 * ─────────────────────────────────────────────────────────
 * Shared footer for all user-facing pages.
 * Include at the very bottom of every page, before </body>.
 *
 * USAGE:
 *   require 'includes/footer.php';
 */

$_depth = (strpos(__DIR__, DIRECTORY_SEPARATOR . 'admin') !== false) ? '../' : '';
?>

<!-- ── FOOTER ──────────────────────────────────────────── -->
<footer class="site-footer">
  <div class="footer-inner">
    <span class="footer-brand">Lost<span>&Found</span> Portal</span>
    <span class="footer-copy">© <?= date('Y') ?> · Built for the community</span>
  </div>
</footer>
<!-- ── END FOOTER ──────────────────────────────────────── -->

<script src="<?= $_depth ?>assets/js/main.js"></script>
</body>
</html>
