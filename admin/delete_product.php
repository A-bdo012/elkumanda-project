
<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('vendor');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_products.php');
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header('Location: my_products.php');
    exit;
}

$product_id = (int) $_POST['id'];
$vendor_id = (int) $_SESSION['user_id'];

$products_path = __DIR__ . '/../uploads/products/';
$gallery_path = __DIR__ . '/../uploads/products/gallery/';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, image
        FROM products
        WHERE id = ? AND vendor_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $product_id,
        $vendor_id
    ]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $pdo->rollBack();
        header('Location: my_products.php');
        exit;
    }

    $gallery_stmt = $pdo->prepare("
        SELECT image
        FROM product_images
        WHERE product_id = ?
    ");

    $gallery_stmt->execute([$product_id]);

    $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);

    $delete_gallery = $pdo->prepare("
        DELETE FROM product_images
        WHERE product_id = ?
    ");

    $delete_gallery->execute([$product_id]);

    $delete_product = $pdo->prepare("
        DELETE FROM products
        WHERE id = ? AND vendor_id = ?
    ");

    $delete_product->execute([
        $product_id,
        $vendor_id
    ]);

    $pdo->commit();

    if (!empty($product['image'])) {
        $image_name = basename($product['image']);
        $image_path = $products_path . $image_name;

        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    foreach ($gallery_images as $gallery_image) {
        if (!empty($gallery_image['image'])) {
            $image_name = basename($gallery_image['image']);
            $image_path = $gallery_path . $image_name;

            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
    }

    header('Location: my_products.php?deleted=1');
    exit;
} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: my_products.php?error=delete_failed');
    exit;
}
