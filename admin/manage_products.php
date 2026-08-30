<?php
session_start();
require_once '../config/db.php';

// 1. Verify Admin Authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$msg = '';
$err = '';

// 2. Handle Product Deletion
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    
    // Find image to unlink from disk
    $imgStmt = $pdo->prepare("SELECT image_url FROM product WHERE product_id = ?");
    $imgStmt->execute([$deleteId]);
    $imgFile = $imgStmt->fetchColumn();

    if ($imgFile && file_exists("../" . $imgFile)) {
        unlink("../" . $imgFile);
    }

    $delStmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
    $delStmt->execute([$deleteId]);
    header('Location: manage_products.php?msg=Product deleted successfully');
    exit;
}

// 3. Handle Quick Stock / Price Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_update') {
    $productId = (int)$_POST['product_id'];
    $newPrice  = (float)$_POST['price'];
    $newStock  = (int)$_POST['stock'];

    if ($newPrice >= 0 && $newStock >= 0) {
        $updateStmt = $pdo->prepare("UPDATE product SET price = ?, stock = ? WHERE product_id = ?");
        $updateStmt->execute([$newPrice, $newStock, $productId]);
        $msg = "Product #$productId updated successfully.";
    } else {
        $err = "Price and stock must be non-negative numbers.";
    }
}

// 4. Handle Add New Product & Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $name     = trim($_POST['product_name'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $stock    = (int)($_POST['stock'] ?? 0);
    $desc     = trim($_POST['description'] ?? '');

    if (empty($name) || empty($category) || $price <= 0 || $stock < 0) {
        $err = 'Please enter valid product details.';
    } elseif (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
        $err = 'Please upload a valid product image.';
    } else {
        $fileTmpPath = $_FILES['product_image']['tmp_name'];
        $fileName    = $_FILES['product_image']['name'];
        $fileSize    = $_FILES['product_image']['size'];
        $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $maxFileSize = 3 * 1024 * 1024; // 3MB limit

        if (!in_array($fileExt, $allowedExts)) {
            $err = 'Invalid file format. Only JPG, JPEG, PNG, and WEBP images are allowed.';
        } elseif ($fileSize > $maxFileSize) {
            $err = 'File size exceeds the 3MB maximum limit.';
        } else {
            // Generate unique filename to avoid collision
            $newFileName = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            $uploadDirectory = '../uploads/';

            // Ensure destination directory exists
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }

            $destPath = $uploadDirectory . $newFileName;
            $dbPath   = 'uploads/' . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO product (product_name, price, category, stock, description, image_url) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                if ($insertStmt->execute([$name, $price, $category, $stock, $desc, $dbPath])) {
                    $msg = 'Product uploaded and published to catalog!';
                } else {
                    $err = 'Failed to save product in database.';
                }
            } else {
                $err = 'Failed to move uploaded image to storage folder.';
            }
        }
    }
}

// 5. Fetch all inventory records
$products = $pdo->query("SELECT * FROM product ORDER BY product_id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen">

    <!-- Admin Topbar Navigation -->
    <nav class="bg-gray-900 text-white px-8 py-4 flex justify-between items-center shadow">
        <h1 class="text-xl font-bold tracking-wide">Store Admin Panel</h1>
        <div class="space-x-6 text-sm font-medium">
            <a href="dashboard.php" class="hover:text-blue-400 transition">Dashboard</a>
            <a href="manage_products.php" class="text-blue-400 font-bold">Manage Products</a>
            <a href="manage_orders.php" class="hover:text-blue-400 transition">Manage Orders</a>
            <a href="logout.php" class="bg-red-600 px-3 py-1.5 rounded hover:bg-red-700 transition">Logout</a>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4">
        
        <!-- Flash Feedback Alerts -->
        <?php if (!empty($msg) || isset($_GET['msg'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm">
                <?= htmlspecialchars($msg ?: $_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($err)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
                <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            
            <!-- Add Product Form Card -->
            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm sticky top-6">
                <h2 class="text-lg font-bold text-gray-900 mb-1">Add New Item</h2>
                <p class="text-xs text-gray-500 mb-4">Upload clothing pieces to make them live on the storefront.</p>

                <form action="manage_products.php" method="POST" enctype="multipart/form-data" class="space-y-4 text-sm">
                    <input type="hidden" name="action" value="add_product">

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Product Title</label>
                        <input type="text" name="product_name" required placeholder="e.g. Oversized Graphic T-Shirt" class="w-full mt-1 p-2.5 border rounded-lg focus:ring-1 focus:ring-black focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Price (₹)</label>
                            <input type="number" step="0.01" min="0" name="price" required placeholder="999.00" class="w-full mt-1 p-2.5 border rounded-lg focus:ring-1 focus:ring-black focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Stock Qty</label>
                            <input type="number" min="0" name="stock" required placeholder="25" class="w-full mt-1 p-2.5 border rounded-lg focus:ring-1 focus:ring-black focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Category</label>
                        <select name="category" required class="w-full mt-1 p-2.5 border rounded-lg bg-white focus:ring-1 focus:ring-black focus:outline-none">
                            <option value="T-Shirts">T-Shirts</option>
                            <option value="Shirts">Shirts</option>
                            <option value="Jeans">Jeans</option>
                            <option value="Jackets">Jackets</option>
                            <option value="Hoodies">Hoodies</option>
                            <option value="Trousers">Trousers</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Description</label>
                        <textarea name="description" rows="3" placeholder="Fabric details, fit, wash care..." class="w-full mt-1 p-2.5 border rounded-lg focus:ring-1 focus:ring-black focus:outline-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Product Image</label>
                        <input type="file" name="product_image" accept=".jpg,.jpeg,.png,.webp" required class="w-full mt-1 text-xs file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        <p class="text-[11px] text-gray-400 mt-1">Accepted: JPG, PNG, WEBP (Max 3MB)</p>
                    </div>

                    <button type="submit" class="w-full bg-black text-white py-2.5 rounded-lg font-bold text-xs uppercase tracking-wider hover:bg-neutral-800 transition">
                        Upload & Publish
                    </button>
                </form>
            </div>

            <!-- Existing Products Inventory Table -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Current Stock</h2>
                        <p class="text-xs text-gray-500">Edit inventory counts, unit prices, or remove listings.</p>
                    </div>
                    <span class="text-xs font-bold text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                        <?= count($products) ?> Total Listed
                    </span>
                </div>

                <?php if (empty($products)): ?>
                    <div class="p-12 text-center text-gray-400 text-sm">
                        No products uploaded yet. Use the upload panel on the left to add items.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                <tr>
                                    <th class="py-3 px-4">Item</th>
                                    <th class="py-3 px-4">Category</th>
                                    <th class="py-3 px-4">Price & Stock</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($products as $prod): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        
                                        <!-- Image and Name -->
                                        <td class="py-3 px-4 flex items-center gap-3">
                                            <img src="../<?= htmlspecialchars($prod['image_url']) ?>" alt="<?= htmlspecialchars($prod['product_name']) ?>" class="w-12 h-14 object-cover rounded bg-gray-100 flex-shrink-0" onerror="this.src='https://via.placeholder.com/150'">
                                            <div>
                                                <p class="font-bold text-gray-900 line-clamp-1"><?= htmlspecialchars($prod['product_name']) ?></p>
                                                <span class="text-[11px] text-gray-400">ID: #<?= $prod['product_id'] ?></span>
                                            </div>
                                        </td>

                                        <!-- Category -->
                                        <td class="py-3 px-4 text-xs font-medium text-gray-600">
                                            <?= htmlspecialchars($prod['category']) ?>
                                        </td>

                                        <!-- Quick Edit Form for Price & Stock -->
                                        <td class="py-3 px-4">
                                            <form action="manage_products.php" method="POST" class="flex items-center gap-2">
                                                <input type="hidden" name="action" value="quick_update">
                                                <input type="hidden" name="product_id" value="<?= $prod['product_id'] ?>">
                                                
                                                <div class="flex items-center">
                                                    <span class="text-xs text-gray-400 mr-1">₹</span>
                                                    <input type="number" step="0.01" name="price" value="<?= $prod['price'] ?>" class="w-20 p-1 text-xs border rounded text-right focus:outline-none">
                                                </div>

                                                <div class="flex items-center">
                                                    <input type="number" name="stock" value="<?= $prod['stock'] ?>" class="w-16 p-1 text-xs border rounded text-center focus:outline-none <?= $prod['stock'] <= 0 ? 'border-red-400 text-red-600 font-bold' : '' ?>">
                                                    <span class="text-[10px] text-gray-400 ml-1">qty</span>
                                                </div>

                                                <button type="submit" title="Save changes" class="text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded transition">
                                                    Save
                                                </button>
                                            </form>
                                        </td>

                                        <!-- Delete Action -->
                                        <td class="py-3 px-4 text-right">
                                            <a 
                                                href="manage_products.php?delete=<?= $prod['product_id'] ?>" 
                                                onclick="return confirm('Are you sure you want to permanently delete \'<?= addslashes(htmlspecialchars($prod['product_name'])) ?>\'? This will also remove the image.')" 
                                                class="text-red-500 hover:text-red-700 text-xs font-semibold hover:underline"
                                            >
                                                Delete
                                            </a>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>