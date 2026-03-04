<?php
// File: includes/footer.php
// Footer component - closes the main layout
?>
    </div><!-- /.content-wrapper -->
</main>

<!-- Footer -->
<footer class="main-footer">
    <span>&copy; <?php echo date('Y'); ?> <strong><?php echo APP_NAME; ?></strong>. All rights reserved.</span>
    <span>Version <?php echo APP_VERSION; ?></span>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?php echo APP_URL; ?>/js/app.js"></script>

<?php if (isset($extraJS)): ?>
    <?php echo $extraJS; ?>
<?php endif; ?>

</body>
</html>