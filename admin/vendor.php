
<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('vendor');

header('Location: my_products.php');
exit;
