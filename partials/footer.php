<?php
/* partials/footer.php — shared bottom of every page */
// Include-only file: never serve it directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
?>
<footer class="app-footer">
    IS Project 1 &mdash; CNS 3205 Server Administration &middot; Brandon Kiplangat &amp; Ian Muyala, CNS 3A &middot; Strathmore University
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
