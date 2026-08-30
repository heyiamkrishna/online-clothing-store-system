<?php
if (!ob_get_level()) {
    ob_start();
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection Loader
if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
}

// Require Login
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = (int)$_SESSION['customer_id'];

// 1. Handle Cart Actions (Quantity Update & Remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $prodId = (int)($_POST['product_id'] ?? 0);
    $size   = trim($_POST['size'] ?? '');

    if ($action === 'update_qty') {
        $newQty = max(1, (int)($_POST['quantity'] ?? 1));
        if ($cartId > 0) {
            $upd = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?");
            $upd->execute([$newQty, $cartId, $customerId]);
        } else {
            $upd = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ? AND size = ?");
            $upd->execute([$newQty, $customerId, $prodId, $size]);
        }
    } elseif ($action === 'remove') {
        if ($cartId > 0) {
            $del = $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?");
            $del->execute([$cartId, $customerId]);
        } else {
            $del = $pdo->prepare("DELETE FROM cart WHERE customer_id = ? AND product_id = ? AND size = ?");
            $del->execute([$customerId, $prodId, $size]);
        }
    }
    header('Location: cart.php');
    exit;
}

// 2. Fetch Cart Items
$stmt = $pdo->prepare("
    SELECT c.*, p.product_name, p.price, p.image_url, p.category, p.stock, p.gender 
    FROM cart c 
    JOIN product p ON c.product_id = p.product_id 
    WHERE c.customer_id = ?
");
$stmt->execute([$customerId]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Subtotal & Free Delivery Rule (Threshold: ₹999)
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += ((float)$item['price'] * (int)$item['quantity']);
}

// FREE DELIVERY THRESHOLD = ₹999
$freeDeliveryThreshold = 999;

if ($subtotal > $freeDeliveryThreshold || $subtotal == 0) {
    $shippingFee = 0;
    $freeShippingProgress = 100;
    $remainingForFreeShipping = 0;
} else {
    $shippingFee = 99;
    $remainingForFreeShipping = $freeDeliveryThreshold - $subtotal;
    $freeShippingProgress = min(100, round(($subtotal / $freeDeliveryThreshold) * 100));
}

$grandTotal = $subtotal + $shippingFee;

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
    <title>Your Shopping Bag — CLOTHSTORE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8F9FA] text-neutral-900 min-h-screen antialiased selection:bg-black selection:text-white">

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2 border-b border-neutral-200 pb-5">
            <div>
                <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Review Selection</span>
                <h1 class="text-3xl font-black text-neutral-900 tracking-tight mt-0.5">Your Shopping Bag</h1>
            </div>
            <a href="shop.php" class="text-xs font-black uppercase tracking-wider text-neutral-500 hover:text-black transition">
                &larr; Continue Browsing
            </a>
        </div>

        <?php if (empty($cartItems)): ?>
            <!-- Empty State -->
            <div class="bg-white rounded-[3rem] p-16 text-center border border-neutral-200/80 shadow-sm space-y-4 max-w-lg mx-auto">
                <div class="w-16 h-16 rounded-full bg-neutral-100 flex items-center justify-center mx-auto text-2xl">
                    🛍️
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-black text-neutral-900">Your bag is completely empty</h3>
                    <p class="text-xs text-neutral-400">Discover dropped-shoulder tees, utility cargos, and heavyweight pieces in the vault.</p>
                </div>
                <a href="shop.php" class="inline-block px-8 py-3.5 rounded-full bg-black text-white text-xs font-black uppercase tracking-widest hover:bg-neutral-800 transition shadow-lg">
                    Explore Catalog &rarr;
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left: Bag Items List -->
                <div class="lg:col-span-7 space-y-4">
                    <?php foreach ($cartItems as $item): 
                        $cartKeyId = (int)($item['cart_id'] ?? 0);
                        $qty = (int)$item['quantity'];
                    ?>
                        <div class="bg-white rounded-[2rem] p-4 sm:p-5 border border-neutral-200/80 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                            
                            <div class="flex items-center gap-4 w-full sm:w-auto">
                                <div class="w-20 h-24 sm:w-24 sm:h-28 rounded-2xl overflow-hidden bg-neutral-100 shrink-0 relative">
                                    <img 
                                        src="<?= htmlspecialchars($item['image_url']) ?>" 
                                        alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                        class="w-full h-full object-cover"
                                        onerror="this.onerror=null;this.src='<?= $defaultSvg ?>';"
                                    >
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-neutral-400 bg-neutral-100 px-2 py-0.5 rounded-md">
                                            <?= htmlspecialchars($item['category']) ?>
                                        </span>
                                        <?php if (!empty($item['size'])): ?>
                                            <span class="text-[9px] font-black uppercase tracking-widest text-white bg-neutral-950 px-2 py-0.5 rounded-md">
                                                Size: <?= htmlspecialchars($item['size']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-sm font-black text-neutral-900 line-clamp-1">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </h3>
                                    <p class="text-xs font-black text-neutral-900">
                                        ₹<?= number_format($item['price'], 0) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Quantity Controls & Remove -->
                            <div class="flex items-center justify-between sm:justify-end gap-4 w-full sm:w-auto border-t sm:border-t-0 pt-3 sm:pt-0 border-neutral-100">
                                
                                <form action="cart.php" method="POST" class="flex items-center gap-1.5 bg-neutral-100 rounded-full p-1">
                                    <input type="hidden" name="action" value="update_qty">
                                    <input type="hidden" name="cart_id" value="<?= $cartKeyId ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                    <input type="hidden" name="size" value="<?= htmlspecialchars($item['size'] ?? '') ?>">

                                    <button type="submit" name="quantity" value="<?= max(1, $qty - 1) ?>" class="w-7 h-7 rounded-full bg-white text-xs font-black flex items-center justify-center hover:bg-neutral-200 transition shadow-sm">-</button>
                                    <span class="px-2 text-xs font-black text-neutral-900"><?= $qty ?></span>
                                    <button type="submit" name="quantity" value="<?= min((int)$item['stock'], $qty + 1) ?>" class="w-7 h-7 rounded-full bg-white text-xs font-black flex items-center justify-center hover:bg-neutral-200 transition shadow-sm">+</button>
                                </form>

                                <form action="cart.php" method="POST">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_id" value="<?= $cartKeyId ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                    <input type="hidden" name="size" value="<?= htmlspecialchars($item['size'] ?? '') ?>">
                                    <button type="submit" class="text-[10px] font-black uppercase tracking-wider text-red-500 hover:text-red-700 transition px-2 py-1">
                                        Remove
                                    </button>
                                </form>

                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Right: Free Shipping Goal & Order Summary -->
                <div class="lg:col-span-5 space-y-5">
                    
                    <!-- ACCURATE DYNAMIC FREE DELIVERY THRESHOLD CARD -->
                    <div class="bg-white rounded-[2rem] p-6 border border-neutral-200/80 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full <?= $freeShippingProgress >= 100 ? 'bg-emerald-500' : 'bg-neutral-950' ?> text-white flex items-center justify-center text-xs">
                                    <?= $freeShippingProgress >= 100 ? '✓' : '🚚' ?>
                                </div>
                                <div>
                                    <h4 class="text-xs font-black text-neutral-900 uppercase tracking-wider">Free Delivery Goal</h4>
                                    <p class="text-[11px] text-neutral-500">
                                        <?php if ($remainingForFreeShipping > 0): ?>
                                            Add <strong class="text-neutral-900 font-black">₹<?= number_format($remainingForFreeShipping, 0) ?></strong> more for Free Shipping
                                        <?php else: ?>
                                            <span class="text-emerald-600 font-black">Free Standard Delivery Unlocked!</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs font-black px-2.5 py-1 rounded-full <?= $freeShippingProgress >= 100 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-neutral-100 text-neutral-900' ?>">
                                <?= $freeShippingProgress ?>%
                            </span>
                        </div>

                        <!-- Real Dynamic Progress Bar -->
                        <div class="w-full h-2.5 bg-neutral-100 rounded-full overflow-hidden">
                            <div 
                                class="h-full <?= $freeShippingProgress >= 100 ? 'bg-emerald-500' : 'bg-neutral-950' ?> transition-all duration-500 rounded-full" 
                                style="width: <?= $freeShippingProgress ?>%;"
                            ></div>
                        </div>
                    </div>

                    <!-- ORDER SUMMARY -->
                    <div class="bg-white rounded-[2.5rem] p-6 sm:p-7 border border-neutral-200/80 shadow-sm space-y-5">
                        <h3 class="text-sm font-black uppercase tracking-wider text-neutral-900 border-b border-neutral-100 pb-3">Order Summary</h3>

                        <div class="space-y-3 text-xs font-bold text-neutral-600">
                            <div class="flex justify-between">
                                <span>Bag Subtotal</span>
                                <span class="text-neutral-900 font-black">₹<?= number_format($subtotal, 0) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Shipping Fee</span>
                                <span class="<?= $shippingFee === 0 ? 'text-emerald-600 font-black' : 'text-neutral-900 font-black' ?>">
                                    <?= $shippingFee === 0 ? 'FREE' : '₹' . number_format($shippingFee, 0) ?>
                                </span>
                            </div>
                            <div class="flex justify-between text-base font-black text-neutral-900 pt-3 border-t border-neutral-100">
                                <span>Grand Total</span>
                                <span>₹<?= number_format($grandTotal, 0) ?></span>
                            </div>
                        </div>

                        <a 
                            href="checkout.php" 
                            class="w-full py-4 rounded-full bg-neutral-950 hover:bg-neutral-800 text-white text-xs font-black uppercase tracking-widest text-center block transition shadow-lg active:scale-95"
                        >
                            Proceed to Checkout &rarr;
                        </a>

                        <div class="grid grid-cols-2 gap-2 text-center text-[10px] font-bold text-neutral-400 pt-1">
                            <span>📦 Cash on Delivery</span>
                            <span>⚡ 48-Hour Dispatch</span>
                        </div>
                    </div>

                </div>

            </div>
        <?php endif; ?>

    </main>

    <?php 
    if (file_exists(__DIR__ . '/includes/footer.php')) {
        require_once __DIR__ . '/includes/footer.php'; 
    }
    ?>

</body>
</html>