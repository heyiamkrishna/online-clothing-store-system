<?php
set_time_limit(5);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Auth Guard
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. Database Inclusion
require_once __DIR__ . '/../config/db.php';

$flashAlert = $_SESSION['flash_alert'] ?? null;
unset($_SESSION['flash_alert']);
session_write_close();

// Offline Instant SVG Placeholder
$defaultSvg = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="120" viewBox="0 0 100 120"><rect width="100%" height="100%" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="bold" fill="%239ca3af">GARMENT</text></svg>';

// Helper function to handle image uploads
function handleImageUpload($fileInputName, $fallbackUrl = '') {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName    = $_FILES[$fileInputName]['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            
            $newFileName = uniqid('prod_', true) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            
            if (@move_uploaded_file($fileTmpPath, $destPath)) {
                return 'uploads/' . $newFileName;
            }
        }
    }
    return !empty($fallbackUrl) ? $fallbackUrl : '';
}

// 3. Handle Admin Actions (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    session_start();
    
    // Add Product with Sizes
    if ($_POST['action'] === 'add_product') {
        $pName  = trim($_POST['product_name'] ?? '');
        $pCat   = trim($_POST['category'] ?? '');
        $pPrice = (float)($_POST['price'] ?? 0);
        $pStock = (int)($_POST['stock'] ?? 0);
        $pUrl   = trim($_POST['image_url'] ?? '');
        $pDesc  = trim($_POST['description'] ?? '');
        
        $sizesArray = $_POST['sizes'] ?? ['S', 'M', 'L', 'XL', 'XXL'];
        $pSizes = implode(',', array_map('trim', $sizesArray));

        $finalImg = handleImageUpload('product_image', $pUrl);

        if (!empty($pName) && !empty($pCat) && $pPrice > 0 && !empty($finalImg)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO product (product_name, category, price, stock, available_sizes, image_url, description, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $insertStmt->execute([$pName, $pCat, $pPrice, $pStock, $pSizes, $finalImg, $pDesc]);
            $_SESSION['flash_alert'] = ['type' => 'success', 'title' => 'Garment Added', 'msg' => "'{$pName}' published with sizes ({$pSizes})."];
        }
    }

    // Edit Product with Sizes
    elseif ($_POST['action'] === 'edit_product') {
        $pId     = (int)($_POST['product_id'] ?? 0);
        $pName   = trim($_POST['product_name'] ?? '');
        $pCat    = trim($_POST['category'] ?? '');
        $pPrice  = (float)($_POST['price'] ?? 0);
        $pStock  = (int)($_POST['stock'] ?? 0);
        $pUrl    = trim($_POST['image_url'] ?? '');
        $pDesc   = trim($_POST['description'] ?? '');
        $currImg = trim($_POST['current_image'] ?? '');

        $sizesArray = $_POST['sizes'] ?? ['S', 'M', 'L', 'XL', 'XXL'];
        $pSizes = implode(',', array_map('trim', $sizesArray));

        $finalImg = handleImageUpload('product_image', $pUrl);
        if (empty($finalImg)) {
            $finalImg = $currImg;
        }

        if ($pId > 0 && !empty($pName) && !empty($pCat) && $pPrice > 0) {
            $updateStmt = $pdo->prepare("
                UPDATE product 
                SET product_name = ?, category = ?, price = ?, stock = ?, available_sizes = ?, image_url = ?, description = ? 
                WHERE product_id = ?
            ");
            $updateStmt->execute([$pName, $pCat, $pPrice, $pStock, $pSizes, $finalImg, $pDesc, $pId]);
            $_SESSION['flash_alert'] = ['type' => 'success', 'title' => 'Updated', 'msg' => "Product #{$pId} updated."];
        }
    }

    // Quick Update Stock
    elseif ($_POST['action'] === 'update_stock') {
        $pId      = (int)($_POST['product_id'] ?? 0);
        $newStock = max(0, (int)($_POST['stock'] ?? 0));
        $updateStmt = $pdo->prepare("UPDATE product SET stock = ? WHERE product_id = ?");
        $updateStmt->execute([$newStock, $pId]);
        $_SESSION['flash_alert'] = ['type' => 'success', 'title' => 'Stock Updated', 'msg' => "Stock set to {$newStock}."];
    }

    // Delete Product
    elseif ($_POST['action'] === 'delete_product') {
        $pId = (int)($_POST['product_id'] ?? 0);
        
        $orderCheck = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $orderCheck->execute([$pId]);
        $hasOrders = $orderCheck->fetchColumn() > 0;

        if ($hasOrders) {
            // Soft delete: keeps invoice data safe, but hides completely from customer storefront
            $archiveStmt = $pdo->prepare("UPDATE product SET is_active = 0, stock = 0 WHERE product_id = ?");
            $archiveStmt->execute([$pId]);
            $pdo->prepare("DELETE FROM cart WHERE product_id = ?")->execute([$pId]);
            $_SESSION['flash_alert'] = ['type' => 'success', 'title' => 'Archived', 'msg' => 'Product archived and removed from store catalog.'];
        } else {
            // Hard delete: permanently remove since no order history depends on it
            $pdo->prepare("DELETE FROM cart WHERE product_id = ?")->execute([$pId]);
            $delStmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
            $delStmt->execute([$pId]);
            $_SESSION['flash_alert'] = ['type' => 'success', 'title' => 'Deleted', 'msg' => 'Product permanently removed.'];
        }
    }

    // Update Status
    elseif ($_POST['action'] === 'update_order_status') {
        $oId       = (int)($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'Pending');
        $statusStmt = $pdo->prepare("UPDATE orders SET payment_stat = ? WHERE order_id = ?");
        $statusStmt->execute([$newStatus, $oId]);
        $_SESSION['flash_alert'] = ['type' => 'success', 'title' => 'Status Updated', 'msg' => "Order #{$oId} updated."];
    }

    session_write_close();
    header('Location: dashboard.php');
    exit;
}

// 4. Metric Calculations
$realizedSales   = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_stat = 'Verified'")->fetchColumn();
$pendingSales    = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_stat = 'Pending'")->fetchColumn();
$lostSales       = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_stat = 'Cancelled'")->fetchColumn();

$totalOrders     = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders   = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_stat = 'Pending'")->fetchColumn();
$cancelledOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_stat = 'Cancelled'")->fetchColumn();

$totalProducts   = (int)$pdo->query("SELECT COUNT(*) FROM product WHERE is_active = 1")->fetchColumn();
$lowStockCount   = (int)$pdo->query("SELECT COUNT(*) FROM product WHERE stock <= 5 AND is_active = 1")->fetchColumn();

// Only fetch active products for dashboard table
$products = $pdo->query("SELECT * FROM product WHERE is_active = 1 ORDER BY product_id DESC")->fetchAll(PDO::FETCH_ASSOC);

$orders = $pdo->query("
    SELECT o.*, c.name AS customer_name, c.email, c.phone 
    FROM orders o 
    LEFT JOIN customer c ON o.customer_id = c.customer_id 
    ORDER BY o.order_id DESC 
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Order Items Including Selected Sizes
$orderItemsMap = [];
$itemsQuery = $pdo->query("
    SELECT oi.order_id, oi.quantity, oi.size, p.product_name, p.category, p.image_url 
    FROM order_items oi 
    JOIN product p ON oi.product_id = p.product_id
");
$allItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
foreach ($allItems as $item) {
    $orderItemsMap[$item['order_id']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Cloth Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    </style>
</head>
<body class="bg-[#F8F9FA] text-gray-900 min-h-screen antialiased selection:bg-black selection:text-white">

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-gray-200 px-6 sm:px-10 py-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="flex items-center gap-2 text-sm font-black tracking-widest uppercase text-gray-900">
                    <span class="w-2.5 h-2.5 rounded-full bg-black"></span>
                    <span>CLOTH<span class="text-gray-400 font-normal">STORE</span></span>
                </a>
                <span class="text-[10px] font-extrabold tracking-widest uppercase bg-black text-white px-2.5 py-0.5 rounded-full">
                    Admin
                </span>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <a href="../shop.php" target="_blank" class="text-gray-500 hover:text-black transition flex items-center gap-1">
                    <span>Live Store</span>
                    <span>&rarr;</span>
                </a>
                <a href="logout.php" class="bg-red-50 text-red-600 px-4 py-1.5 rounded-full hover:bg-red-100 border border-red-100 transition">
                    Logout
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-8 py-8 space-y-8">
        
        <!-- Dashboard Header -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <span class="text-[11px] font-bold tracking-widest uppercase text-gray-400">Management Console</span>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight mt-1">Store Overview</h1>
            </div>
            
            <button 
                onclick="document.getElementById('add-product-modal').classList.remove('hidden')" 
                class="inline-flex items-center gap-2 bg-black text-white text-xs font-bold uppercase tracking-wider px-6 py-3.5 rounded-full hover:bg-neutral-800 transition shadow-sm w-fit"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add New Product</span>
            </button>
        </div>

        <!-- Metrics Deck -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm space-y-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Realized Revenue</span>
                <p class="text-2xl sm:text-3xl font-black text-neutral-900 mt-1">₹<?= number_format($realizedSales, 2) ?></p>
                <span class="text-[11px] text-neutral-400 font-medium block">Verified Orders</span>
            </div>
            <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm space-y-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-amber-500">Pending Pipeline</span>
                <p class="text-2xl sm:text-3xl font-black text-neutral-900 mt-1">₹<?= number_format($pendingSales, 2) ?></p>
                <span class="text-[11px] text-amber-700 font-semibold block"><?= $pendingOrders ?> Orders Awaiting Review</span>
            </div>
            <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm space-y-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-red-500">Cancelled Volume</span>
                <p class="text-2xl sm:text-3xl font-black text-neutral-400 mt-1">₹<?= number_format($lostSales, 2) ?></p>
                <span class="text-[11px] text-red-600 font-semibold block"><?= $cancelledOrders ?> Cancelled Orders</span>
            </div>
            <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm space-y-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Active SKUs</span>
                <p class="text-2xl sm:text-3xl font-black text-neutral-900 mt-1"><?= $totalProducts ?></p>
                <span class="text-[11px] text-neutral-400 font-medium block"><?= $lowStockCount ?> Low Stock Items</span>
            </div>
        </div>

        <!-- Section 1: Customer Orders Table -->
        <section class="bg-white rounded-[2.5rem] border border-gray-200/80 p-6 sm:p-8 shadow-sm">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Recent Customer Orders</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Review purchased garments, selected sizes, quantities, and addresses.</p>
                </div>
                <span class="text-xs font-bold text-gray-500 bg-[#F8F9FA] border border-gray-200 px-3.5 py-1.5 rounded-full">
                    <?= count($orders) ?> Orders
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                            <th class="pb-3 px-2">Order ID</th>
                            <th class="pb-3 px-3">Customer</th>
                            <th class="pb-3 px-3">Items Purchased</th>
                            <th class="pb-3 px-3">Delivery Address</th>
                            <th class="pb-3 px-3 text-right">Amount</th>
                            <th class="pb-3 px-3 text-center">Status</th>
                            <th class="pb-3 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="7" class="py-8 text-center text-gray-400">No orders placed yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $ord): 
                                $currItems = $orderItemsMap[$ord['order_id']] ?? [];
                                $status = $ord['payment_stat'] ?? 'Pending';
                            ?>
                                <tr class="hover:bg-neutral-50/60 transition align-top">
                                    <td class="py-4 px-2 font-black text-gray-900 whitespace-nowrap">
                                        #<?= $ord['order_id'] ?>
                                        <span class="block text-[10px] font-semibold text-gray-400 mt-0.5">
                                            <?= date('d M Y', strtotime($ord['order_date'] ?? 'now')) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-3 space-y-0.5 whitespace-nowrap">
                                        <p class="font-bold text-gray-900"><?= htmlspecialchars($ord['customer_name'] ?? 'Customer') ?></p>
                                        <p class="text-[11px] text-gray-500"><?= htmlspecialchars($ord['phone'] ?? 'N/A') ?></p>
                                    </td>
                                    <td class="py-4 px-3">
                                        <div class="space-y-3 min-w-[240px]">
                                            <?php if (empty($currItems)): ?>
                                                <span class="text-gray-400 italic text-[11px]">No items data</span>
                                            <?php else: ?>
                                                <?php foreach ($currItems as $itm): 
                                                    $displayImg = strpos($itm['image_url'], 'http') === 0 ? $itm['image_url'] : '../' . $itm['image_url'];
                                                ?>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-10 h-12 rounded-xl bg-neutral-100 border border-neutral-200 overflow-hidden shrink-0">
                                                            <img 
                                                                src="<?= htmlspecialchars($displayImg) ?>" 
                                                                alt="Garment" 
                                                                class="w-full h-full object-cover"
                                                                onerror="this.onerror=null;this.src='<?= $defaultSvg ?>';"
                                                            >
                                                        </div>
                                                        <div>
                                                            <p class="font-bold text-gray-900 leading-tight"><?= htmlspecialchars($itm['product_name']) ?></p>
                                                            <div class="flex items-center gap-1.5 mt-1 text-[10px]">
                                                                <span class="uppercase font-extrabold text-neutral-500"><?= htmlspecialchars($itm['category']) ?></span>
                                                                <span class="text-neutral-300">•</span>
                                                                <span class="bg-black text-white px-2 py-0.5 rounded font-black text-[9px]">SIZE: <?= htmlspecialchars($itm['size'] ?? 'M') ?></span>
                                                                <span class="text-neutral-300">•</span>
                                                                <span class="font-bold text-neutral-700">Qty: <?= $itm['quantity'] ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-3 text-gray-600 leading-relaxed max-w-xs font-medium text-[11px]">
                                        <?= nl2br(htmlspecialchars($ord['shipping_add'] ?? '')) ?>
                                    </td>
                                    <td class="py-4 px-3 text-right font-black text-gray-900 text-sm whitespace-nowrap">
                                        ₹<?= number_format($ord['total_amount'], 2) ?>
                                        <span class="block text-[10px] font-semibold text-gray-400">COD</span>
                                    </td>
                                    <td class="py-4 px-3 text-center whitespace-nowrap">
                                        <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider border <?= $status === 'Verified' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($status === 'Cancelled' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-700 border-amber-200') ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-3 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <form action="dashboard.php" method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="update_order_status">
                                                <input type="hidden" name="order_id" value="<?= $ord['order_id'] ?>">
                                                <select name="status" onchange="this.form.submit()" class="p-1.5 bg-[#F8F9FA] border border-gray-200 rounded-xl text-[11px] font-semibold text-gray-700 focus:outline-none cursor-pointer">
                                                    <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="Verified" <?= $status === 'Verified' ? 'selected' : '' ?>>Verified</option>
                                                    <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                </select>
                                            </form>
                                            <a 
                                                href="../invoice.php?id=<?= $ord['order_id'] ?>" 
                                                target="_blank" 
                                                title="View / Print Invoice PDF" 
                                                class="p-2 rounded-xl border border-gray-200 bg-[#F8F9FA] hover:bg-black hover:text-white transition shadow-sm inline-flex items-center"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 2: Catalog Management -->
        <section class="bg-white rounded-[2.5rem] border border-gray-200/80 p-6 sm:p-8 shadow-sm">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Product Inventory & Catalog</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Manage garment details, sizes available, prices, and stock.</p>
                </div>
                <span class="text-xs font-bold text-gray-500 bg-[#F8F9FA] border border-gray-200 px-3.5 py-1.5 rounded-full">
                    <?= count($products) ?> Total Products
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                            <th class="pb-3">Garment</th>
                            <th class="pb-3">Category</th>
                            <th class="pb-3">Sizes</th>
                            <th class="pb-3">Price</th>
                            <th class="pb-3">Current Stock</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($products)): ?>
                            <tr><td colspan="6" class="py-8 text-center text-gray-400">No active products in catalog.</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $prod): 
                                $displayProdImg = strpos($prod['image_url'], 'http') === 0 ? $prod['image_url'] : '../' . $prod['image_url'];
                            ?>
                                <tr class="hover:bg-neutral-50/60 transition">
                                    <td class="py-3.5 flex items-center gap-3">
                                        <div class="w-11 h-14 bg-gray-200 rounded-xl overflow-hidden flex-shrink-0">
                                            <img 
                                                src="<?= htmlspecialchars($displayProdImg) ?>" 
                                                alt="<?= htmlspecialchars($prod['product_name']) ?>" 
                                                class="w-full h-full object-cover"
                                                onerror="this.onerror=null;this.src='<?= $defaultSvg ?>';"
                                            >
                                        </div>
                                        <div>
                                            <a href="../product_details.php?id=<?= $prod['product_id'] ?>" target="_blank" class="font-bold text-gray-900 hover:underline">
                                                <?= htmlspecialchars($prod['product_name']) ?>
                                            </a>
                                            <span class="text-[10px] text-gray-400 block">ID: #<?= $prod['product_id'] ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3.5">
                                        <span class="bg-[#F8F9FA] border border-gray-200 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider text-gray-700">
                                            <?= htmlspecialchars($prod['category']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5">
                                        <span class="font-bold text-neutral-800 text-[11px]"><?= htmlspecialchars($prod['available_sizes'] ?? 'S,M,L,XL,XXL') ?></span>
                                    </td>
                                    <td class="py-3.5 font-black text-gray-900">₹<?= number_format($prod['price'], 2) ?></td>
                                    
                                    <td class="py-3.5">
                                        <form action="dashboard.php" method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="action" value="update_stock">
                                            <input type="hidden" name="product_id" value="<?= $prod['product_id'] ?>">
                                            <input 
                                                type="number" 
                                                name="stock" 
                                                min="0" 
                                                value="<?= $prod['stock'] ?>" 
                                                class="w-16 p-1.5 bg-[#F8F9FA] border border-gray-200 rounded-xl text-xs font-bold text-gray-900 text-center focus:ring-1 focus:ring-black focus:outline-none"
                                            >
                                            <button type="submit" class="text-[10px] font-bold uppercase tracking-wider text-black bg-[#F8F9FA] border border-gray-200 hover:bg-gray-100 px-2.5 py-1.5 rounded-xl transition">
                                                Save
                                            </button>
                                        </form>
                                    </td>

                                    <td class="py-3.5 text-right space-x-2 whitespace-nowrap">
                                        <button 
                                            type="button"
                                            onclick='openEditModal(<?= json_encode($prod, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                            class="text-xs font-bold text-neutral-800 bg-[#F8F9FA] hover:bg-black hover:text-white border border-gray-200 px-3 py-1.5 rounded-xl transition"
                                        >
                                            Edit
                                        </button>

                                        <button 
                                            type="button"
                                            onclick='triggerDeleteModal(<?= $prod['product_id'] ?>, "<?= htmlspecialchars(addslashes($prod['product_name'])) ?>", "<?= htmlspecialchars($displayProdImg) ?>")'
                                            class="text-xs font-bold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 border border-red-100 px-3 py-1.5 rounded-xl transition"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- BEAUTIFUL TOAST POPUP -->
    <?php if ($flashAlert): ?>
        <div id="toast-modal" class="fixed top-6 right-6 z-50 flex items-center gap-3.5 p-4 pr-6 rounded-2xl bg-black text-white shadow-2xl border border-white/10 transition-all duration-300">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 <?= $flashAlert['type'] === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
                <p class="text-xs font-black uppercase tracking-wider"><?= htmlspecialchars($flashAlert['title']) ?></p>
                <p class="text-xs text-neutral-300 font-medium mt-0.5"><?= htmlspecialchars($flashAlert['msg']) ?></p>
            </div>
            <button onclick="document.getElementById('toast-modal').remove()" class="ml-2 text-neutral-500 hover:text-white text-sm">&times;</button>
        </div>
        <script>
            setTimeout(() => {
                const t = document.getElementById('toast-modal');
                if (t) t.remove();
            }, 3000);
        </script>
    <?php endif; ?>

    <!-- 1. ADD PRODUCT MODAL (With Size Checkboxes) -->
    <div id="add-product-modal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] max-w-lg w-full p-8 border border-gray-200 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6 pb-3 border-b border-gray-100">
                <h3 class="text-lg font-black text-gray-900 uppercase tracking-tight">Add New Garment</h3>
                <button onclick="document.getElementById('add-product-modal').classList.add('hidden')" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-black hover:text-white flex items-center justify-center text-xs font-bold transition">&times;</button>
            </div>
            <form action="dashboard.php" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="add_product">
                
                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Product Name</label>
                    <input type="text" name="product_name" required placeholder="e.g. Heavyweight Boxy Fit Tee" class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Category</label>
                        <select name="category" required class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                            <option value="T-Shirts">T-Shirts</option>
                            <option value="Shirts">Shirts</option>
                            <option value="Jeans">Jeans</option>
                            <option value="Hoodies">Hoodies</option>
                            <option value="Outerwear">Outerwear</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Price (INR)</label>
                        <input type="number" step="0.01" name="price" required placeholder="1499.00" class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                    </div>
                </div>

                <!-- Sizes Selector Checkboxes -->
                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-2">Available Sizes</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (['S', 'M', 'L', 'XL', 'XXL'] as $sz): ?>
                            <label class="cursor-pointer">
                                <input type="checkbox" name="sizes[]" value="<?= $sz ?>" checked class="peer sr-only">
                                <span class="px-3.5 py-2 rounded-xl border border-neutral-200 text-xs font-black peer-checked:bg-black peer-checked:text-white transition inline-block">
                                    <?= $sz ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Initial Stock</label>
                        <input type="number" name="stock" required value="25" min="0" class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                    </div>
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Upload Image</label>
                        <input type="file" name="product_image" accept="image/*" class="w-full p-2.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-black file:text-white">
                    </div>
                </div>

                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Or Image URL</label>
                    <input type="url" name="image_url" placeholder="https://..." class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                </div>

                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" rows="2" placeholder="Garment details..." class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition"></textarea>
                </div>

                <button type="submit" class="w-full bg-black text-white font-bold text-xs uppercase tracking-widest py-4 rounded-full hover:bg-neutral-800 transition shadow-md mt-2">Publish Garment &rarr;</button>
            </form>
        </div>
    </div>

    <!-- 2. EDIT PRODUCT MODAL -->
    <div id="edit-product-modal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] max-w-lg w-full p-8 border border-gray-200 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6 pb-3 border-b border-gray-100">
                <h3 class="text-lg font-black text-gray-900 uppercase tracking-tight">Edit Garment</h3>
                <button onclick="document.getElementById('edit-product-modal').classList.add('hidden')" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-black hover:text-white flex items-center justify-center text-xs font-bold transition">&times;</button>
            </div>
            <form action="dashboard.php" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="product_id" id="edit-id">
                <input type="hidden" name="current_image" id="edit-current-image">

                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Product Name</label>
                    <input type="text" name="product_name" id="edit-name" required class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Category</label>
                        <select name="category" id="edit-category" required class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                            <option value="T-Shirts">T-Shirts</option>
                            <option value="Shirts">Shirts</option>
                            <option value="Jeans">Jeans</option>
                            <option value="Hoodies">Hoodies</option>
                            <option value="Outerwear">Outerwear</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Price (INR)</label>
                        <input type="number" step="0.01" name="price" id="edit-price" required class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                    </div>
                </div>

                <!-- Edit Available Sizes -->
                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-2">Available Sizes</label>
                    <div class="flex flex-wrap gap-2" id="edit-sizes-container">
                        <?php foreach (['S', 'M', 'L', 'XL', 'XXL'] as $sz): ?>
                            <label class="cursor-pointer">
                                <input type="checkbox" name="sizes[]" value="<?= $sz ?>" id="edit-size-<?= $sz ?>" class="peer sr-only">
                                <span class="px-3.5 py-2 rounded-xl border border-neutral-200 text-xs font-black peer-checked:bg-black peer-checked:text-white transition inline-block">
                                    <?= $sz ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Stock</label>
                        <input type="number" name="stock" id="edit-stock" required min="0" class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                    </div>
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Replace Image</label>
                        <input type="file" name="product_image" accept="image/*" class="w-full p-2.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-black file:text-white">
                    </div>
                </div>

                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Or Replace URL</label>
                    <input type="url" name="image_url" id="edit-url" placeholder="https://..." class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                </div>

                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" id="edit-description" rows="2" class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black outline-none transition"></textarea>
                </div>

                <button type="submit" class="w-full bg-black text-white font-bold text-xs uppercase tracking-widest py-4 rounded-full hover:bg-neutral-800 transition shadow-md mt-2">Save Changes &rarr;</button>
            </form>
        </div>
    </div>

    <!-- 3. REFINED DELETE CONFIRMATION MODAL -->
    <div id="delete-confirm-modal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[2rem] max-w-sm w-full p-6 sm:p-7 border border-neutral-200 shadow-2xl relative text-center">
            <div class="w-12 h-12 rounded-full bg-red-50 border border-red-100 text-red-500 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </div>
            <h3 class="text-base font-black text-neutral-900 tracking-tight">Remove Garment?</h3>
            <p class="text-xs text-neutral-500 mt-1">Are you sure you want to remove this piece from the catalog?</p>
            <div class="mt-4 mb-6 p-3 rounded-2xl bg-[#F8F9FA] border border-neutral-200 flex items-center gap-3 text-left">
                <div class="w-10 h-12 rounded-xl overflow-hidden bg-neutral-100 border border-neutral-200 shrink-0">
                    <img id="del-prod-img" src="<?= $defaultSvg ?>" alt="Thumbnail" class="w-full h-full object-cover">
                </div>
                <div class="min-w-0">
                    <p id="del-prod-name" class="text-xs font-bold text-neutral-900 truncate">Garment Title</p>
                    <p class="text-[10px] text-neutral-400 font-semibold">SKU ID: #<span id="del-prod-id-txt"></span></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="closeDeleteModal()" class="flex-1 py-3 rounded-full bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-xs font-bold transition uppercase tracking-wider">Cancel</button>
                <form id="delete-form" action="dashboard.php" method="POST" class="flex-1">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" id="del-prod-id">
                    <button type="submit" class="w-full py-3 rounded-full bg-red-600 hover:bg-red-700 text-white text-xs font-black uppercase tracking-wider transition shadow-sm">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Controllers -->
    <script>
        const fallbackSvg = '<?= $defaultSvg ?>';

        function openEditModal(prod) {
            document.getElementById('edit-id').value = prod.product_id;
            document.getElementById('edit-name').value = prod.product_name;
            document.getElementById('edit-category').value = prod.category;
            document.getElementById('edit-price').value = prod.price;
            document.getElementById('edit-stock').value = prod.stock;
            document.getElementById('edit-description').value = prod.description || '';
            document.getElementById('edit-current-image').value = prod.image_url || '';
            document.getElementById('edit-url').value = prod.image_url && prod.image_url.startsWith('http') ? prod.image_url : '';
            
            const available = (prod.available_sizes || 'S,M,L,XL,XXL').split(',').map(s => s.trim());
            ['S', 'M', 'L', 'XL', 'XXL'].forEach(sz => {
                const chk = document.getElementById('edit-size-' + sz);
                if (chk) chk.checked = available.includes(sz);
            });

            document.getElementById('edit-product-modal').classList.remove('hidden');
        }

        function triggerDeleteModal(productId, productName, productImage) {
            document.getElementById('del-prod-id').value = productId;
            document.getElementById('del-prod-id-txt').textContent = productId;
            document.getElementById('del-prod-name').textContent = productName;
            
            const imgElem = document.getElementById('del-prod-img');
            imgElem.onerror = function() { this.onerror = null; this.src = fallbackSvg; };
            imgElem.src = productImage || fallbackSvg;

            document.getElementById('delete-confirm-modal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('delete-confirm-modal').classList.add('hidden');
        }

        document.getElementById('delete-confirm-modal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
    </script>

</body>
</html>