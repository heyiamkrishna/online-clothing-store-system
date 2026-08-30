<?php
session_start();
require_once '../config/db.php';

// Verify Admin Authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$adminId = (int)$_SESSION['admin_id'];
$msg = '';
$err = '';

// Handle Status Updates (Pending / Verified / Cancelled)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId   = (int)$_POST['order_id'];
    $newStatus = trim($_POST['status']);

    if (in_array($newStatus, ['Pending', 'Verified', 'Cancelled'])) {
        $verifiedByValue = ($newStatus === 'Verified') ? $adminId : null;

        $updateStmt = $pdo->prepare("
            UPDATE orders 
            SET payment_stat = ?, verified_by = ? 
            WHERE order_id = ?
        ");
        
        if ($updateStmt->execute([$newStatus, $verifiedByValue, $orderId])) {
            $msg = "Order #$orderId status updated to '$newStatus'.";
        } else {
            $err = "Failed to update order status.";
        }
    }
}

// Fetch all orders with customer details
$ordersQuery = $pdo->query("
    SELECT o.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, a.username AS verified_admin
    FROM orders o
    JOIN customer c ON o.customer_id = c.customer_id
    LEFT JOIN admin a ON o.verified_by = a.admin_id
    ORDER BY o.order_id DESC
");
$orders = $ordersQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen text-gray-800">

    <!-- Admin Navigation -->
    <nav class="bg-gray-900 text-white px-8 py-4 flex justify-between items-center shadow">
        <h1 class="text-xl font-bold tracking-wide">Store Admin Panel</h1>
        <div class="space-x-6 text-sm font-medium">
            <a href="dashboard.php" class="hover:text-blue-400 transition">Dashboard</a>
            <a href="manage_products.php" class="hover:text-blue-400 transition">Manage Products</a>
            <a href="manage_orders.php" class="text-blue-400 font-bold">Manage Orders</a>
            <a href="logout.php" class="bg-red-600 px-3 py-1.5 rounded hover:bg-red-700 transition">Logout</a>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto py-8 px-4">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-extrabold text-gray-900">Customer Orders</h2>
                <p class="text-xs text-gray-500 mt-1">Review orders, verify payments, and inspect order receipts.</p>
            </div>
            <span class="bg-white border px-3 py-1.5 rounded-lg text-xs font-bold text-gray-700 shadow-sm">
                Total Orders: <?= count($orders) ?>
            </span>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded-lg mb-6 text-sm">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($err)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded-lg mb-6 text-sm">
                <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-xl p-12 text-center border border-gray-200">
                <p class="text-gray-500 font-medium text-sm">No orders have been recorded in the database yet.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <?php
                    // Fetch items for this specific order
                    $itemsStmt = $pdo->prepare("
                        SELECT oi.*, p.product_name, p.category, p.image_url 
                        FROM order_items oi 
                        JOIN product p ON oi.product_id = p.product_id 
                        WHERE oi.order_id = ?
                    ");
                    $itemsStmt->execute([$order['order_id']]);
                    $items = $itemsStmt->fetchAll();
                    ?>

                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <!-- Order Top Header -->
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-base text-gray-900">Order #<?= $order['order_id'] ?></span>
                                    <span class="text-xs text-gray-400">•</span>
                                    <span class="text-xs text-gray-500"><?= date('d M Y, h:i A', strtotime($order['order_date'])) ?></span>
                                </div>
                                <p class="text-xs text-gray-600">
                                    <strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?> (<?= htmlspecialchars($order['customer_email']) ?> | <?= htmlspecialchars($order['customer_phone']) ?>)
                                </p>
                            </div>

                            <!-- Payment & Verification Action Form -->
                            <div class="flex items-center gap-3">
                                <form action="manage_orders.php" method="POST" class="flex items-center gap-2">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    
                                    <select name="status" class="text-xs border border-gray-300 rounded-lg p-2 font-medium bg-white focus:outline-none focus:ring-1 focus:ring-black">
                                        <option value="Pending" <?= $order['payment_stat'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Verified" <?= $order['payment_stat'] === 'Verified' ? 'selected' : '' ?>>Verified</option>
                                        <option value="Cancelled" <?= $order['payment_stat'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    
                                    <button type="submit" class="bg-black text-white px-3 py-2 rounded-lg text-xs font-semibold hover:bg-neutral-800 transition">
                                        Update
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Purchased Items Details -->
                        <div class="p-6">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Items Ordered</h4>
                            <div class="divide-y divide-gray-100">
                                <?php foreach ($items as $item): ?>
                                    <div class="py-2 flex items-center justify-between text-sm">
                                        <div class="flex items-center gap-3">
                                            <img src="../<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="w-10 h-10 object-cover rounded bg-gray-100" onerror="this.src='https://via.placeholder.com/100'">
                                            <div>
                                                <p class="font-medium text-gray-800"><?= htmlspecialchars($item['product_name']) ?></p>
                                                <p class="text-[11px] text-gray-400"><?= htmlspecialchars($item['category']) ?> • Qty: <?= $item['quantity'] ?> × ₹<?= number_format($item['unit_price'], 2) ?></p>
                                            </div>
                                        </div>
                                        <span class="font-bold text-gray-900">₹<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Summary Footer -->
                        <div class="bg-gray-50 px-6 py-3 border-t border-gray-100 flex flex-wrap justify-between items-center text-xs text-gray-600 gap-4">
                            <div>
                                <strong>Delivery Destination:</strong> <?= htmlspecialchars($order['shipping_add']) ?>
                                <?php if (!empty($order['verified_admin'])): ?>
                                    <span class="block text-[11px] text-green-700 mt-0.5">Verified by admin: <strong><?= htmlspecialchars($order['verified_admin']) ?></strong></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <span class="text-gray-500">Order Total:</span>
                                <span class="text-base font-black text-gray-900 ml-1">₹<?= number_format($order['total_amount'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>