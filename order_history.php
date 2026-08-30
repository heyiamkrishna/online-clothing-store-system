<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = (int)$_SESSION['customer_id'];
$alertMessage = '';
$alertType    = '';

// 1. Handle Order Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $orderIdToCancel = (int)($_POST['order_id'] ?? 0);

    $checkStmt = $pdo->prepare("SELECT order_id, payment_stat FROM orders WHERE order_id = ? AND customer_id = ?");
    $checkStmt->execute([$orderIdToCancel, $customerId]);
    $targetOrder = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetOrder) {
        $alertMessage = "Order record not found or unauthorized request.";
        $alertType = "error";
    } else {
        $currentStat = strtolower(trim($targetOrder['payment_stat']));
        
        if (in_array($currentStat, ['pending', 'processing'])) {
            try {
                $pdo->beginTransaction();

                // Update Status to Cancelled
                $updateStmt = $pdo->prepare("UPDATE orders SET payment_stat = 'Cancelled' WHERE order_id = ?");
                $updateStmt->execute([$orderIdToCancel]);

                // Restore Stock
                $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $itemsStmt->execute([$orderIdToCancel]);
                $itemsToRestock = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                $restockStmt = $pdo->prepare("UPDATE product SET stock = stock + ? WHERE product_id = ?");
                foreach ($itemsToRestock as $it) {
                    $restockStmt->execute([(int)$it['quantity'], (int)$it['product_id']]);
                }

                $pdo->commit();
                $alertMessage = "Order #ORD-" . str_pad((string)$orderIdToCancel, 5, '0', STR_PAD_LEFT) . " has been successfully cancelled.";
                $alertType = "success";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $alertMessage = "Failed to cancel order. Please try again.";
                $alertType = "error";
            }
        } else {
            $alertMessage = "This order is already in transit or completed and cannot be cancelled.";
            $alertType = "error";
        }
    }
}

// 2. Fetch Customer Orders
$ordersQuery = "
    SELECT 
        o.order_id, 
        o.order_date, 
        o.total_amount, 
        o.shipping_add, 
        o.payment_stat
    FROM orders o
    WHERE o.customer_id = ?
    ORDER BY o.order_id DESC
";
$stmt = $pdo->prepare($ordersQuery);
$stmt->execute([$customerId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStageInfo($status) {
    $cleanStatus = strtolower(trim((string)$status));
    switch ($cleanStatus) {
        case 'delivered':
            return ['step' => 4, 'percent' => '100%', 'label' => 'Delivered to Doorstep', 'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-200'];
        case 'verified':
        case 'shipped':
        case 'dispatched':
            return ['step' => 3, 'percent' => '66%', 'label' => 'In Courier Transit', 'badge' => 'bg-blue-100 text-blue-800 border-blue-200'];
        case 'processing':
            return ['step' => 2, 'percent' => '33%', 'label' => 'Vault Packaging', 'badge' => 'bg-amber-100 text-amber-800 border-amber-200'];
        case 'cancelled':
            return ['step' => 0, 'percent' => '0%', 'label' => 'Order Cancelled', 'badge' => 'bg-red-100 text-red-800 border-red-200'];
        case 'pending':
        default:
            return ['step' => 1, 'percent' => '15%', 'label' => 'Order Placed & Queued', 'badge' => 'bg-neutral-100 text-neutral-800 border-neutral-200'];
    }
}

$defaultSvg = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="500" viewBox="0 0 400 500"><rect width="100%" height="100%" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="12" font-weight="bold" fill="%239ca3af">CLOTHSTORE</text></svg>';

if (file_exists(__DIR__ . '/includes/header.php')) {
    require_once __DIR__ . '/includes/header.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History & Tracking — CLOTHSTORE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8F9FA] text-neutral-900 min-h-screen antialiased selection:bg-black selection:text-white">

    <main class="max-w-6xl mx-auto px-4 sm:px-8 py-8 sm:py-12 space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-neutral-200 pb-6">
            <div>
                <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Personal Archive</span>
                <h1 class="text-3xl font-black text-neutral-900 tracking-tight mt-1">Order Tracking &amp; History</h1>
            </div>
            <a href="shop.php" class="text-xs font-black uppercase tracking-wider text-neutral-500 hover:text-black transition">
                &larr; Return to Storefront
            </a>
        </div>

        <?php if (!empty($alertMessage)): ?>
            <div class="p-4 rounded-2xl text-xs font-bold <?= $alertType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-700' ?>">
                <?= htmlspecialchars($alertMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-[2.5rem] p-16 text-center border border-neutral-200 shadow-sm space-y-4">
                <div class="w-14 h-14 rounded-full bg-neutral-100 flex items-center justify-center mx-auto text-xl">📦</div>
                <h2 class="text-lg font-black text-neutral-900">No past orders discovered</h2>
                <p class="text-xs text-neutral-400 max-w-sm mx-auto">When you confirm orders, your real-time tracking milestones and PDF invoices will appear here.</p>
                <a href="shop.php" class="inline-block mt-2 px-8 py-4 rounded-full bg-black text-white text-xs font-black uppercase tracking-widest hover:bg-neutral-800 transition shadow-md">
                    Explore Drop &rarr;
                </a>
            </div>
        <?php else: ?>

            <div class="space-y-8">
                <?php foreach ($orders as $ord): 
                    $stage = getStageInfo($ord['payment_stat']);
                    $orderStatusLower = strtolower(trim($ord['payment_stat']));
                    $canCancel = in_array($orderStatusLower, ['pending', 'processing']);
                    $formattedOrderId = '#ORD-' . str_pad((string)$ord['order_id'], 5, '0', STR_PAD_LEFT);

                    $itemsStmt = $pdo->prepare("
                        SELECT oi.*, p.product_name, p.image_url, p.category 
                        FROM order_items oi
                        JOIN product p ON oi.product_id = p.product_id
                        WHERE oi.order_id = ?
                    ");
                    $itemsStmt->execute([$ord['order_id']]);
                    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                    <div class="bg-white rounded-[2.5rem] p-6 sm:p-9 border border-neutral-200/80 shadow-sm space-y-8">
                        
                        <!-- 1. Header Bar -->
                        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-neutral-100 pb-5">
                            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                                <span class="font-mono text-xs sm:text-sm font-black text-neutral-900"><?= $formattedOrderId ?></span>
                                <span class="text-xs text-neutral-400 font-medium">Placed on <?= date('d M Y, h:i A', strtotime($ord['order_date'])) ?></span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border <?= $stage['badge'] ?>">
                                    <?= htmlspecialchars($ord['payment_stat']) ?>
                                </span>
                            </div>

                            <div class="flex items-center gap-5">
                                <span class="text-sm font-black text-neutral-900">Total: ₹<?= number_format($ord['total_amount'], 0) ?></span>

                                <!-- PDF Invoice Link -->
                                <a 
                                    href="invoice.php?id=<?= $ord['order_id'] ?>" 
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 text-xs font-black text-neutral-950 hover:text-neutral-600 transition group"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>
                                    <svg class="w-3.5 h-3.5 text-neutral-900 group-hover:translate-y-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    <span class="underline underline-offset-2 font-bold tracking-tight">Invoice (PDF)</span>
                                </a>

                                <!-- Cancel Button -->
                                <?php if ($canCancel): ?>
                                    <button 
                                        type="button"
                                        onclick="openCancelModal('<?= $ord['order_id'] ?>', '<?= $formattedOrderId ?>', '₹<?= number_format($ord['total_amount'], 0) ?>')"
                                        class="px-4 py-2 rounded-full bg-red-50 text-red-600 hover:bg-red-600 hover:text-white border border-red-200 text-[10px] font-black uppercase tracking-wider transition"
                                    >
                                        Cancel Order
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 2. Shipment Progress Bar -->
                        <?php if ($stage['step'] > 0): ?>
                            <div class="bg-neutral-50 rounded-[2rem] p-6 sm:p-8 border border-neutral-200/60 space-y-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                                        <span class="text-xs font-black uppercase tracking-wider text-neutral-900"><?= $stage['label'] ?></span>
                                    </div>
                                    <span class="text-[10px] font-mono font-black uppercase tracking-widest text-neutral-400">Step <?= $stage['step'] ?> of 4</span>
                                </div>

                                <div class="relative w-full h-2.5 bg-neutral-200 rounded-full overflow-visible">
                                    <div class="h-full bg-black rounded-full transition-all duration-1000 ease-out" style="width: <?= $stage['percent'] ?>;"></div>
                                    <div class="absolute top-1/2 -translate-y-1/2 -ml-3.5 w-7 h-7 rounded-full bg-black text-white flex items-center justify-center shadow-lg border-2 border-white transition-all duration-1000 ease-out" style="left: <?= $stage['percent'] ?>;">
                                        <svg class="w-3.5 h-3.5 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="grid grid-cols-4 gap-2 text-center pt-2">
                                    <div class="space-y-1">
                                        <div class="w-2.5 h-2.5 rounded-full mx-auto <?= $stage['step'] >= 1 ? 'bg-black' : 'bg-neutral-300' ?>"></div>
                                        <p class="text-[10px] font-black uppercase tracking-wider <?= $stage['step'] >= 1 ? 'text-neutral-900' : 'text-neutral-400' ?>">1. Placed</p>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="w-2.5 h-2.5 rounded-full mx-auto <?= $stage['step'] >= 2 ? 'bg-black' : 'bg-neutral-300' ?>"></div>
                                        <p class="text-[10px] font-black uppercase tracking-wider <?= $stage['step'] >= 2 ? 'text-neutral-900' : 'text-neutral-400' ?>">2. Packed</p>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="w-2.5 h-2.5 rounded-full mx-auto <?= $stage['step'] >= 3 ? 'bg-black' : 'bg-neutral-300' ?>"></div>
                                        <p class="text-[10px] font-black uppercase tracking-wider <?= $stage['step'] >= 3 ? 'text-neutral-900' : 'text-neutral-400' ?>">3. In Transit</p>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="w-2.5 h-2.5 rounded-full mx-auto <?= $stage['step'] >= 4 ? 'bg-emerald-600' : 'bg-neutral-300' ?>"></div>
                                        <p class="text-[10px] font-black uppercase tracking-wider <?= $stage['step'] >= 4 ? 'text-emerald-700' : 'text-neutral-400' ?>">4. Delivered</p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold text-center">
                                This order was cancelled. Stock has been restored to inventory.
                            </div>
                        <?php endif; ?>

                        <!-- 3. Items -->
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block">Garments in Consignment</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($orderItems as $item): 
                                    $displayImg = strpos($item['image_url'], 'http') === 0 ? $item['image_url'] : $item['image_url'];
                                ?>
                                    <div class="flex items-center gap-4 p-3.5 rounded-2xl bg-neutral-50 border border-neutral-100">
                                        <div class="w-14 h-16 rounded-xl overflow-hidden bg-neutral-200 shrink-0">
                                            <img src="<?= htmlspecialchars($displayImg) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="w-full h-full object-cover" onerror="this.onerror=null;this.src='<?= $defaultSvg ?>';">
                                        </div>
                                        <div class="min-w-0 flex-1 space-y-0.5">
                                            <p class="text-xs font-black text-neutral-900 truncate"><?= htmlspecialchars($item['product_name']) ?></p>
                                            <p class="text-[10px] text-neutral-400">Size: <strong class="text-neutral-700"><?= htmlspecialchars($item['size'] ?? 'M') ?></strong> &bull; Qty: <strong class="text-neutral-700"><?= $item['quantity'] ?></strong></p>
                                            <p class="text-xs font-black text-neutral-900">₹<?= number_format($item['unit_price'], 0) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 4. Shipping Address -->
                        <div class="pt-3 border-t border-neutral-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-neutral-500">
                            <p><span class="font-bold text-neutral-800">Destination:</span> <?= htmlspecialchars($ord['shipping_add']) ?></p>
                            <span class="font-bold text-neutral-800 shrink-0">Payment: Cash on Delivery (COD)</span>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>

    <!-- CANCEL ORDER CONFIRMATION POP-UP CARD -->
    <div id="cancelModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[3rem] p-8 sm:p-10 max-w-md w-full border border-neutral-200 shadow-2xl space-y-6 text-center animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Warning Badge -->
            <div class="w-16 h-16 rounded-full bg-red-50 text-red-600 flex items-center justify-center mx-auto text-2xl font-black shadow-inner">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <!-- Content -->
            <div class="space-y-2">
                <span class="px-3 py-1 bg-red-50 text-red-700 border border-red-200 text-[10px] font-black uppercase tracking-widest rounded-full inline-block">
                    Cancellation Warning
                </span>
                <h3 class="text-2xl font-black text-neutral-900 tracking-tight">Cancel This Order?</h3>
                <p class="text-xs text-neutral-400 font-medium leading-relaxed">
                    Are you sure you want to cancel <strong id="modalOrderLabel" class="text-neutral-900">#ORD-00000</strong>? This will stop packaging and restore reserved garments to store inventory.
                </p>
            </div>

            <!-- Details Card -->
            <div class="bg-neutral-50 rounded-2xl p-4 border border-neutral-200 text-left text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-neutral-400">Order Ref:</span>
                    <span id="modalSummaryId" class="font-mono font-bold text-neutral-900">#ORD-00000</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-400">Order Amount:</span>
                    <span id="modalSummaryTotal" class="font-bold text-neutral-900">₹0</span>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <form id="cancelOrderForm" action="order_history.php" method="POST" class="grid grid-cols-2 gap-3 pt-2">
                <input type="hidden" name="action" value="cancel_order">
                <input type="hidden" name="order_id" id="modalOrderIdInput" value="">

                <button 
                    type="button" 
                    onclick="closeCancelModal()" 
                    class="py-4 rounded-full bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-xs font-black uppercase tracking-wider transition"
                >
                    Keep Order
                </button>

                <button 
                    type="submit" 
                    class="py-4 rounded-full bg-red-600 hover:bg-red-700 text-white text-xs font-black uppercase tracking-wider transition shadow-md active:scale-95"
                >
                    Yes, Cancel
                </button>
            </form>

        </div>
    </div>

    <!-- Modal Trigger JS -->
    <script>
        function openCancelModal(orderId, orderLabel, totalAmount) {
            document.getElementById('modalOrderIdInput').value = orderId;
            document.getElementById('modalOrderLabel').textContent = orderLabel;
            document.getElementById('modalSummaryId').textContent = orderLabel;
            document.getElementById('modalSummaryTotal').textContent = totalAmount;
            document.getElementById('cancelModal').classList.remove('hidden');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.add('hidden');
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeCancelModal();
        });
        document.getElementById('cancelModal').addEventListener('click', (e) => {
            if (e.target.id === 'cancelModal') closeCancelModal();
        });
    </script>

    <?php 
    if (file_exists(__DIR__ . '/includes/footer.php')) {
        require_once __DIR__ . '/includes/footer.php'; 
    }
    ?>

</body>
</html>