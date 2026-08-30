<?php
if (!ob_get_level()) {
    ob_start();
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Database Connection
if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
}

// 2. Auth Check
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = (int)$_SESSION['customer_id'];

// 3. Fetch User Details
$userStmt = $pdo->prepare("SELECT * FROM customer WHERE customer_id = ?");
$userStmt->execute([$customerId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 4. Fetch Cart Items
$stmt = $pdo->prepare("
    SELECT c.*, p.product_name, p.price, p.image_url, p.category, p.stock 
    FROM cart c 
    JOIN product p ON c.product_id = p.product_id 
    WHERE c.customer_id = ?
");
$stmt->execute([$customerId]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Subtotal & Free Delivery Calculation (> 999)
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += ((float)$item['price'] * (int)$item['quantity']);
}

$freeDeliveryThreshold = 999;
$shippingFee = ($subtotal > $freeDeliveryThreshold || $subtotal == 0) ? 0 : 99;
$grandTotal  = $subtotal + $shippingFee;

$orderError = '';
$confirmedOrderId = 0;
$confirmedTotal = 0;

// 5. Handle Place Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $paymentMethod = 'Cash on Delivery';

    if (empty($recipientName) || empty($phone) || empty($address)) {
        $orderError = "Please fill in all mandatory destination fields.";
    } elseif (empty($cartItems)) {
        $orderError = "Your cart is empty.";
    } else {
        try {
            $pdo->beginTransaction();

            $orderTable = 'orders';
            try {
                $colStmt = $pdo->query("DESCRIBE `orders`");
            } catch (Exception $e) {
                $orderTable = 'order';
                $colStmt = $pdo->query("DESCRIBE `order`");
            }
            $orderCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);

            $insertFields = [];
            $insertPlaceholders = [];
            $insertValues = [];

            if (in_array('customer_id', $orderCols)) {
                $insertFields[] = '`customer_id`';
                $insertPlaceholders[] = '?';
                $insertValues[] = $customerId;
            }

            if (in_array('total_amount', $orderCols)) {
                $insertFields[] = '`total_amount`';
                $insertPlaceholders[] = '?';
                $insertValues[] = $grandTotal;
            } elseif (in_array('total_price', $orderCols)) {
                $insertFields[] = '`total_price`';
                $insertPlaceholders[] = '?';
                $insertValues[] = $grandTotal;
            } elseif (in_array('total', $orderCols)) {
                $insertFields[] = '`total`';
                $insertPlaceholders[] = '?';
                $insertValues[] = $grandTotal;
            }

            if (in_array('shipping_address', $orderCols)) {
                $insertFields[] = '`shipping_address`';
                $insertPlaceholders[] = '?';
                $insertValues[] = $address;
            } elseif (in_array('address', $orderCols)) {
                $insertFields[] = '`address`';
                $insertPlaceholders[] = '?';
                $insertValues[] = $address;
            }

            if (in_array('phone_number', $orderCols)) {
                $insertFields[] = '`phone_number`';
                $insertPlaceholders[] = '?';
                $insertValues[] = $phone;
            } elseif (in_array('phone', $orderCols)) {
                $insertFields[] = '`phone`';
                $insertPlaceholders[] = '?';
                $insertValues[] = $phone;
            }

            if (in_array('payment_method', $orderCols)) {
                $insertFields[] = '`payment_method`';
                $insertPlaceholders[] = '?';
                $insertValues[] = $paymentMethod;
            }

            if (in_array('order_status', $orderCols)) {
                $insertFields[] = '`order_status`';
                $insertPlaceholders[] = '?';
                $insertValues[] = 'Confirmed';
            }

            if (in_array('created_at', $orderCols)) {
                $insertFields[] = '`created_at`';
                $insertPlaceholders[] = 'NOW()';
            } elseif (in_array('order_date', $orderCols)) {
                $insertFields[] = '`order_date`';
                $insertPlaceholders[] = 'NOW()';
            }

            $sql = "INSERT INTO `{$orderTable}` (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
            $orderInsert = $pdo->prepare($sql);
            $orderInsert->execute($insertValues);
            $orderId = (int)$pdo->lastInsertId();

            $itemTableCandidates = ['order_item', 'order_items', 'order_details'];
            foreach ($itemTableCandidates as $tbl) {
                try {
                    $itemColsStmt = $pdo->query("DESCRIBE `{$tbl}`");
                    $itemCols = $itemColsStmt->fetchAll(PDO::FETCH_COLUMN);

                    $hasSize = in_array('size', $itemCols);
                    if ($hasSize) {
                        $itemStmt = $pdo->prepare("INSERT INTO `{$tbl}` (order_id, product_id, quantity, price, size) VALUES (?, ?, ?, ?, ?)");
                        foreach ($cartItems as $c) {
                            $itemStmt->execute([$orderId, $c['product_id'], $c['quantity'], $c['price'], $c['size'] ?? 'M']);
                        }
                    } else {
                        $itemStmt = $pdo->prepare("INSERT INTO `{$tbl}` (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                        foreach ($cartItems as $c) {
                            $itemStmt->execute([$orderId, $c['product_id'], $c['quantity'], $c['price']]);
                        }
                    }
                    break;
                } catch (Exception $e) {
                    continue;
                }
            }

            // Clear Customer Cart
            $clearCart = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
            $clearCart->execute([$customerId]);

            $pdo->commit();

            // Set variables to trigger the confirmation popup modal
            $confirmedOrderId = $orderId;
            $confirmedTotal   = $grandTotal;
            $cartItems        = [];

        } catch (Exception $e) {
            $pdo->rollBack();
            $orderError = "Unable to complete order dispatch: " . $e->getMessage();
        }
    }
}

if (file_exists(__DIR__ . '/includes/header.php')) {
    require_once __DIR__ . '/includes/header.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Vault Checkout — CLOTHSTORE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8F9FA] text-neutral-900 min-h-screen antialiased selection:bg-black selection:text-white relative">

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-8">

        <div class="space-y-1 border-b border-neutral-200 pb-5">
            <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Vault Dispatch Pipeline</span>
            <h1 class="text-3xl font-black text-neutral-900 tracking-tight">Secure Checkout</h1>
        </div>

        <?php if ($orderError): ?>
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold">
                &#x26A0; <?= htmlspecialchars($orderError) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cartItems) && $confirmedOrderId === 0): ?>
            <div class="bg-white rounded-[3rem] p-16 text-center border border-neutral-200/80 shadow-sm space-y-4 max-w-lg mx-auto">
                <div class="w-16 h-16 rounded-full bg-neutral-100 flex items-center justify-center mx-auto text-2xl">🛍️</div>
                <h3 class="text-base font-black text-neutral-900">Your bag is empty</h3>
                <a href="shop.php" class="inline-block px-8 py-3.5 rounded-full bg-black text-white text-xs font-black uppercase tracking-widest hover:bg-neutral-800 transition">
                    Explore Catalog &rarr;
                </a>
            </div>
        <?php else: ?>

            <form action="checkout.php" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- Destination Information -->
                    <div class="bg-white rounded-[2.5rem] p-6 sm:p-8 border border-neutral-200/80 shadow-sm space-y-5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-neutral-950 text-white flex items-center justify-center text-xs font-black">1</div>
                            <h2 class="text-sm font-black uppercase tracking-wider text-neutral-900">Destination Information</h2>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block">Recipient Full Name</label>
                                <input 
                                    type="text" 
                                    name="recipient_name" 
                                    required 
                                    value="<?= htmlspecialchars($user['name'] ?? $user['customer_name'] ?? '') ?>" 
                                    class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-bold text-neutral-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black"
                                >
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block">Phone Number</label>
                                    <input 
                                        type="tel" 
                                        name="phone" 
                                        required 
                                        value="<?= htmlspecialchars($user['phone'] ?? $user['mobile'] ?? '') ?>" 
                                        class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-bold text-neutral-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black"
                                    >
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block">Email Address</label>
                                    <input 
                                        type="email" 
                                        disabled 
                                        value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                        class="w-full px-4 py-3 bg-neutral-100 border border-neutral-200 rounded-2xl text-xs font-bold text-neutral-500 cursor-not-allowed"
                                    >
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block">Confirm Full Delivery Address</label>
                                <textarea 
                                    name="address" 
                                    rows="3" 
                                    required 
                                    placeholder="House / Flat No., Landmark, City, State, Pincode" 
                                    class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-bold text-neutral-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black"
                                ><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Gateway Selection -->
                    <div class="bg-white rounded-[2.5rem] p-6 sm:p-8 border border-neutral-200/80 shadow-sm space-y-5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-neutral-950 text-white flex items-center justify-center text-xs font-black">2</div>
                            <h2 class="text-sm font-black uppercase tracking-wider text-neutral-900">Payment Gateway</h2>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center justify-between p-4 rounded-2xl border-2 border-black bg-neutral-50 cursor-pointer shadow-sm">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="payment_mode" value="cod" checked class="w-4 h-4 text-black focus:ring-black">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black text-neutral-900">Cash on Delivery (COD)</span>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold">Active</span>
                                        </div>
                                        <p class="text-[10px] text-neutral-500 font-medium">Verify parcel &amp; pay delivery agent directly at doorstep.</p>
                                    </div>
                                </div>
                                <span class="text-lg">💵</span>
                            </label>

                            <div class="flex items-center justify-between p-4 rounded-2xl border border-neutral-200 bg-neutral-50/60 opacity-70 cursor-not-allowed">
                                <div class="flex items-center gap-3">
                                    <input type="radio" disabled class="w-4 h-4 text-neutral-300">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black text-neutral-700">UPI / QR (Google Pay / PhonePe / Paytm)</span>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-neutral-200 text-neutral-600 font-black uppercase tracking-wider">Coming Soon</span>
                                        </div>
                                        <p class="text-[10px] text-neutral-400 font-medium">Instant scan &amp; UPI app payments.</p>
                                    </div>
                                </div>
                                <span class="text-lg">📱</span>
                            </div>

                            <div class="flex items-center justify-between p-4 rounded-2xl border border-neutral-200 bg-neutral-50/60 opacity-70 cursor-not-allowed">
                                <div class="flex items-center gap-3">
                                    <input type="radio" disabled class="w-4 h-4 text-neutral-300">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black text-neutral-700">Razorpay / Cards / Netbanking</span>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-neutral-200 text-neutral-600 font-black uppercase tracking-wider">Coming Soon</span>
                                        </div>
                                        <p class="text-[10px] text-neutral-400 font-medium">Encrypted payment via Visa, Mastercard &amp; NetBanking.</p>
                                    </div>
                                </div>
                                <span class="text-lg">💳</span>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="lg:col-span-5 space-y-5">
                    
                    <div class="bg-white rounded-[2.5rem] p-6 sm:p-8 border border-neutral-200/80 shadow-sm space-y-5">
                        <h3 class="text-sm font-black uppercase tracking-wider text-neutral-900 border-b border-neutral-100 pb-3">Order Overview</h3>

                        <div class="space-y-3 max-h-56 overflow-y-auto pr-1">
                            <?php foreach ($cartItems as $it): ?>
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-3 truncate pr-2">
                                        <span class="text-neutral-400 font-bold"><?= (int)$it['quantity'] ?>x</span>
                                        <span class="text-neutral-800 font-bold truncate"><?= htmlspecialchars($it['product_name']) ?></span>
                                        <span class="text-[9px] px-1.5 py-0.2 bg-neutral-100 rounded text-neutral-500 font-black"><?= htmlspecialchars($it['size'] ?? 'M') ?></span>
                                    </div>
                                    <span class="font-black text-neutral-900 shrink-0">₹<?= number_format((float)$it['price'] * (int)$it['quantity'], 0) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="space-y-3 text-xs font-bold text-neutral-600 pt-3 border-t border-neutral-100">
                            <div class="flex justify-between">
                                <span>Items Subtotal</span>
                                <span class="text-neutral-900 font-black">₹<?= number_format($subtotal, 0) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Express Delivery</span>
                                <span class="<?= $shippingFee === 0 ? 'text-emerald-600 font-black' : 'text-neutral-900 font-black' ?>">
                                    <?= $shippingFee === 0 ? 'FREE' : '₹' . number_format($shippingFee, 0) ?>
                                </span>
                            </div>
                            
                            <?php if ($subtotal > 999): ?>
                                <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-[11px] font-bold flex items-center gap-2">
                                    <span>✓</span> <span>Free express delivery applied (> ₹999).</span>
                                </div>
                            <?php endif; ?>

                            <div class="flex justify-between text-base font-black text-neutral-900 pt-3 border-t border-neutral-100">
                                <span>Total Payable (COD)</span>
                                <span class="text-2xl">₹<?= number_format($grandTotal, 0) ?></span>
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            name="place_order" 
                            class="w-full py-4 rounded-full bg-neutral-950 hover:bg-neutral-800 text-white text-xs font-black uppercase tracking-widest text-center block transition shadow-xl active:scale-95"
                        >
                            Confirm &amp; Place Order &rarr;
                        </button>
                    </div>

                </div>

            </form>

        <?php endif; ?>

    </main>


    <!-- ================= BEAUTIFUL CONFIRMATION MODAL POPUP ================= -->
    <?php if ($confirmedOrderId > 0): ?>
        <div id="orderSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md animate-fade-in">
            <div class="bg-white w-full max-w-lg rounded-[3rem] p-6 sm:p-10 border border-neutral-100 shadow-2xl space-y-6 relative transform transition-all animate-scale-up">
                
                <!-- Close Button -->
                <a href="shop.php" class="absolute top-6 right-6 w-9 h-9 rounded-full bg-neutral-100 hover:bg-neutral-200 text-neutral-500 hover:text-black flex items-center justify-center text-sm font-black transition">
                    &times;
                </a>

                <!-- Header Icon & Status -->
                <div class="text-center space-y-3">
                    <div class="w-16 h-16 rounded-full bg-emerald-500 text-white flex items-center justify-center mx-auto text-2xl font-black shadow-lg shadow-emerald-500/20 animate-bounce">
                        ✓
                    </div>
                    <div class="space-y-1">
                        <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 inline-block">
                            Confirmed &amp; Dispatched
                        </span>
                        <h2 class="text-2xl sm:text-3xl font-black text-neutral-900 tracking-tight">Order #<?= $confirmedOrderId ?> Placed!</h2>
                        <p class="text-xs text-neutral-500 max-w-xs mx-auto">
                            Thank you for shopping. Your garment is queued for 48-hour priority transit.
                        </p>
                    </div>
                </div>

                <!-- Live Status Timeline Card -->
                <div class="bg-neutral-50 rounded-[2rem] p-5 border border-neutral-200/80 space-y-4">
                    <div class="flex items-center justify-between text-xs font-black border-b border-neutral-200/60 pb-3">
                        <span class="text-neutral-500 uppercase tracking-wider text-[10px]">Payment Summary</span>
                        <span class="text-neutral-950 text-sm">₹<?= number_format($confirmedTotal, 0) ?> (COD)</span>
                    </div>

                    <!-- 3-Step Progress Ticker -->
                    <div class="space-y-3 pt-1">
                        <div class="flex items-center gap-3">
                            <div class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">1</div>
                            <div class="text-left">
                                <p class="text-xs font-black text-neutral-900">Order Confirmed</p>
                                <p class="text-[10px] text-neutral-400">Inventory reserved &amp; tag assigned</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="w-6 h-6 rounded-full bg-neutral-900 text-white flex items-center justify-center text-[10px] font-bold">2</div>
                            <div class="text-left">
                                <p class="text-xs font-black text-neutral-900">Quality Seam Check</p>
                                <p class="text-[10px] text-neutral-400">Dust-bag packaging &amp; courier dispatch (48 hrs)</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 opacity-60">
                            <div class="w-6 h-6 rounded-full bg-neutral-200 text-neutral-500 flex items-center justify-center text-[10px] font-bold">3</div>
                            <div class="text-left">
                                <p class="text-xs font-bold text-neutral-700">Doorstep Delivery</p>
                                <p class="text-[10px] text-neutral-400">Verify parcel &amp; pay Cash on Delivery</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CTA Action Buttons -->
                <div class="space-y-2.5 pt-1">
                    <a 
                        href="order_confirmation.php?id=<?= $confirmedOrderId ?>" 
                        class="w-full py-4 rounded-full bg-neutral-950 hover:bg-neutral-800 text-white text-xs font-black uppercase tracking-widest text-center block transition shadow-xl active:scale-95"
                    >
                        View Full Order Receipt &rarr;
                    </a>
                    
                    <a 
                        href="shop.php" 
                        class="w-full py-3 rounded-full bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-xs font-black uppercase tracking-wider text-center block transition"
                    >
                        Continue Shopping &rarr;
                    </a>
                </div>

            </div>
        </div>
    <?php endif; ?>

    <?php 
    if (file_exists(__DIR__ . '/includes/footer.php')) {
        require_once __DIR__ . '/includes/footer.php'; 
    }
    ?>

</body>
</html>