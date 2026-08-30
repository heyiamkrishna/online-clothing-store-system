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

$productId = (int)($_GET['id'] ?? 0);

// 1. Fetch Product Details
$stmt = $pdo->prepare("SELECT * FROM product WHERE product_id = ? AND is_active = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Garment Not Found — CLOTHSTORE</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-[#F8F9FA] min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-10 rounded-[2.5rem] border border-neutral-200 text-center max-w-md space-y-4 shadow-xl">
            <div class="text-4xl">⚡</div>
            <h1 class="text-xl font-black text-neutral-900">Garment Not Found</h1>
            <p class="text-xs text-neutral-500">The requested item reference (#<?= htmlspecialchars((string)$productId) ?>) does not exist or has been retired.</p>
            <a href="shop.php" class="inline-block px-6 py-3 rounded-full bg-black text-white text-xs font-black uppercase tracking-wider hover:bg-neutral-800 transition">
                Return to Catalog &rarr;
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$alertMsg  = '';
$alertType = '';

// 2. Safe & Schema-Adaptive Add to Cart Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['customer_id'])) {
        header('Location: login.php');
        exit;
    }

    $customerId   = (int)$_SESSION['customer_id'];
    $selectedSize = trim($_POST['size'] ?? 'M');
    $quantity     = max(1, (int)($_POST['quantity'] ?? 1));

    if ($product['stock'] < $quantity) {
        $alertMsg  = "Requested quantity exceeds available stock.";
        $alertType = "error";
    } else {
        try {
            // Inspect columns of table 'cart' to adapt dynamically
            $colStmt = $pdo->query("DESCRIBE cart");
            $tableColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN);

            $hasSizeCol = in_array('size', $tableColumns);
            
            // Check existing item
            if ($hasSizeCol) {
                $check = $pdo->prepare("SELECT * FROM cart WHERE customer_id = ? AND product_id = ? AND size = ?");
                $check->execute([$customerId, $productId, $selectedSize]);
            } else {
                $check = $pdo->prepare("SELECT * FROM cart WHERE customer_id = ? AND product_id = ?");
                $check->execute([$customerId, $productId]);
            }
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $newQty = min((int)$product['stock'], ((int)($existing['quantity'] ?? 1)) + $quantity);
                
                if ($hasSizeCol) {
                    $upd = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ? AND size = ?");
                    $upd->execute([$newQty, $customerId, $productId, $selectedSize]);
                } else {
                    $upd = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ?");
                    $upd->execute([$newQty, $customerId, $productId]);
                }
            } else {
                if ($hasSizeCol) {
                    $ins = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity, size) VALUES (?, ?, ?, ?)");
                    $ins->execute([$customerId, $productId, $quantity, $selectedSize]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?)");
                    $ins->execute([$customerId, $productId, $quantity]);
                }
            }

            $alertMsg  = "Garment added to your bag successfully!";
            $alertType = "success";
        } catch (PDOException $e) {
            $alertMsg  = "Cart error: " . $e->getMessage();
            $alertType = "error";
        }
    }
}

// 3. Fetch Related Garments
$relatedStmt = $pdo->prepare("
    SELECT * FROM product 
    WHERE is_active = 1 AND product_id != ? AND (category = ? OR gender = ?) 
    ORDER BY product_id DESC 
    LIMIT 4
");
$relatedStmt->execute([$productId, $product['category'], $product['gender'] ?? 'Men']);
$relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title><?= htmlspecialchars($product['product_name']) ?> — CLOTHSTORE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8F9FA] text-neutral-900 min-h-screen antialiased selection:bg-black selection:text-white">

    <main class="max-w-6xl mx-auto px-4 sm:px-8 py-8 sm:py-12 space-y-12 sm:space-y-16">
        
        <!-- Breadcrumb Navigation -->
        <nav class="flex items-center gap-2 text-xs font-bold text-neutral-400">
            <a href="shop.php" class="hover:text-black transition">Catalog</a>
            <span>/</span>
            <a href="shop.php?gender=<?= urlencode($product['gender'] ?? 'Men') ?>" class="hover:text-black transition"><?= htmlspecialchars($product['gender'] ?? 'Men') ?></a>
            <span>/</span>
            <span class="text-neutral-900 truncate"><?= htmlspecialchars($product['product_name']) ?></span>
        </nav>

        <!-- Notification Banner -->
        <?php if ($alertMsg): ?>
            <div class="p-4 rounded-2xl text-xs font-bold flex items-center justify-between <?= $alertType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-700' ?>">
                <span><?= $alertType === 'success' ? '&#x2713;' : '&#x26A0;' ?> <?= htmlspecialchars($alertMsg) ?></span>
                <?php if ($alertType === 'success'): ?>
                    <a href="cart.php" class="underline underline-offset-2 font-black hover:text-emerald-950">View Bag &rarr;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Main Product Overview Card -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12 bg-white rounded-[2.5rem] sm:rounded-[3rem] p-6 sm:p-10 border border-neutral-200/80 shadow-[0_15px_40px_rgba(0,0,0,0.03)] items-start">
            
            <!-- Left: Garment Image -->
            <div class="md:col-span-6">
                <div class="w-full aspect-[4/5] rounded-[2rem] overflow-hidden bg-neutral-100 relative shadow-inner">
                    <img 
                        src="<?= htmlspecialchars($product['image_url']) ?>" 
                        alt="<?= htmlspecialchars($product['product_name']) ?>" 
                        referrerpolicy="no-referrer"
                        class="w-full h-full object-cover"
                        onerror="this.onerror=null;this.src='<?= $defaultSvg ?>';"
                    >
                    <span class="absolute top-4 left-4 px-3 py-1.5 rounded-xl bg-black/80 backdrop-blur-md text-white text-[10px] font-black uppercase tracking-wider">
                        <?= strtoupper($product['gender'] ?? 'MEN') ?>
                    </span>

                    <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                        <span class="absolute bottom-4 right-4 px-3 py-1 rounded-xl bg-amber-500 text-white text-[9px] font-black uppercase tracking-wider">
                            Low Stock: Only <?= $product['stock'] ?> Left
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Details & Purchase Form -->
            <div class="md:col-span-6 space-y-6">
                
                <div class="space-y-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block"><?= htmlspecialchars($product['category']) ?></span>
                    <h1 class="text-2xl sm:text-3xl font-black text-neutral-900 tracking-tight leading-tight">
                        <?= htmlspecialchars($product['product_name']) ?>
                    </h1>
                    <div class="flex items-baseline gap-3 pt-1">
                        <span class="text-3xl font-black text-neutral-900">
                            ₹<?= number_format($product['price'], 0) ?>
                        </span>
                        <span class="text-xs font-bold text-neutral-400">Inclusive of all taxes</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block">Garment Architecture</span>
                    <p class="text-xs sm:text-sm text-neutral-500 leading-relaxed">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </p>
                </div>

                <form action="product_details.php?id=<?= $productId ?>" method="POST" class="space-y-6 pt-3 border-t border-neutral-100">
                    
                    <!-- Size Selector -->
                    <div class="space-y-2.5">
                        <div class="flex justify-between items-center">
                            <label class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Select Silhouette Size</label>
                            <span class="text-[10px] font-bold text-neutral-400">Oversized Street Fit</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <?php foreach (['S', 'M', 'L', 'XL'] as $idx => $s): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="size" value="<?= $s ?>" <?= $idx === 1 ? 'checked' : '' ?> class="peer sr-only">
                                    <span class="w-12 h-12 rounded-2xl bg-neutral-100 text-neutral-800 text-xs font-black flex items-center justify-center border-2 border-transparent peer-checked:bg-black peer-checked:text-white peer-checked:border-black transition-all shadow-sm">
                                        <?= $s ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quantity Control -->
                    <div class="space-y-2.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block">Quantity</label>
                        <div class="flex items-center gap-3">
                            <input 
                                type="number" 
                                name="quantity" 
                                value="1" 
                                min="1" 
                                max="<?= max(1, (int)$product['stock']) ?>" 
                                class="w-20 py-3 px-3.5 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-bold text-neutral-900 focus:outline-none focus:ring-2 focus:ring-black"
                            >
                            <span class="text-xs text-neutral-400 font-medium"><?= $product['stock'] ?> units available</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="pt-2 space-y-2.5">
                        <?php if ($product['stock'] > 0): ?>
                            <button 
                                type="submit" 
                                name="add_to_cart" 
                                class="w-full py-4 rounded-full bg-black text-white text-xs font-black uppercase tracking-widest hover:bg-neutral-800 transition active:scale-98 shadow-lg block text-center"
                            >
                                Add to Bag &rarr;
                            </button>
                        <?php else: ?>
                            <button 
                                type="button" 
                                disabled
                                class="w-full py-4 rounded-full bg-neutral-200 text-neutral-400 text-xs font-black uppercase tracking-widest cursor-not-allowed block text-center"
                            >
                                Out of Stock
                            </button>
                        <?php endif; ?>
                        
                        <a 
                            href="shop.php" 
                            class="w-full py-3.5 rounded-full bg-neutral-100 hover:bg-neutral-200 text-neutral-700 text-xs font-black uppercase tracking-wider transition block text-center"
                        >
                            &larr; Back to Catalog
                        </a>
                    </div>

                </form>

                <div class="grid grid-cols-2 gap-3 pt-4 border-t border-neutral-100 text-[11px] font-bold text-neutral-600">
                    <div class="flex items-center gap-2">
                        <span>📦</span> <span>Cash on Delivery</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span>⚡</span> <span>48-Hour Transit</span>
                    </div>
                </div>

            </div>

        </div>

        <!-- Technical Specs Matrix -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-white rounded-[2.5rem] p-7 sm:p-8 border border-neutral-200/80 shadow-sm space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-neutral-100 flex items-center justify-center text-xl">🧵</div>
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Material Architecture</span>
                    <h3 class="text-base font-black text-neutral-900">Heavyweight Fabric</h3>
                    <p class="text-xs text-neutral-500 leading-relaxed">Crafted with 240+ GSM dense combed cotton, double-needle locked hems, and pre-shrunk treatment to maintain structure.</p>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] p-7 sm:p-8 border border-neutral-200/80 shadow-sm space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-neutral-100 flex items-center justify-center text-xl">📐</div>
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Silhouette Engineering</span>
                    <h3 class="text-base font-black text-neutral-900">Boxy Dropped Shoulder</h3>
                    <p class="text-xs text-neutral-500 leading-relaxed">Relaxed chest profile with custom elongated sleeve lengths designed specifically for modern oversized street tailoring.</p>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] p-7 sm:p-8 border border-neutral-200/80 shadow-sm space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-neutral-100 flex items-center justify-center text-xl">🧼</div>
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Preservation Guide</span>
                    <h3 class="text-base font-black text-neutral-900">Wash &amp; Care</h3>
                    <p class="text-xs text-neutral-500 leading-relaxed">Cold machine wash inside out at 30°C. Do not tumble dry. Flat dry in shade and iron on reverse to protect texture integrity.</p>
                </div>
            </div>
        </section>

        <!-- Brand Banner -->
        <section class="bg-neutral-950 text-white rounded-[2.5rem] sm:rounded-[3rem] p-8 sm:p-12 border border-white/10 shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-8">
            <div class="space-y-2 text-center md:text-left max-w-lg">
                <span class="px-3.5 py-1 rounded-full bg-white/10 text-[9px] font-black uppercase tracking-widest text-neutral-300 inline-block">Vault Security &amp; Delivery</span>
                <h3 class="text-2xl sm:text-3xl font-black tracking-tight">Direct Inspection Before Transit</h3>
                <p class="text-xs text-neutral-400 font-normal leading-relaxed">Every individual piece undergoes rigorous seam checks, barcode tagging, and custom darkwear dust-bag packaging before courier handover.</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 w-full md:w-auto shrink-0">
                <div class="p-4 rounded-2xl bg-neutral-900/90 border border-white/10 text-center">
                    <span class="block text-sm font-black text-white">100%</span>
                    <span class="text-[9px] uppercase font-bold text-neutral-400">Authentic</span>
                </div>
                <div class="p-4 rounded-2xl bg-neutral-900/90 border border-white/10 text-center">
                    <span class="block text-sm font-black text-white">7-Day</span>
                    <span class="text-[9px] uppercase font-bold text-neutral-400">Exchange</span>
                </div>
                <div class="p-4 rounded-2xl bg-neutral-900/90 border border-white/10 text-center col-span-2 sm:col-span-1">
                    <span class="block text-sm font-black text-white">COD</span>
                    <span class="text-[9px] uppercase font-bold text-neutral-400">Verified</span>
                </div>
            </div>
        </section>

        <!-- Complementary Pieces -->
        <?php if (!empty($relatedProducts)): ?>
            <section class="space-y-6 pt-2">
                <div class="flex items-center justify-between border-b border-neutral-200 pb-4">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Vault Rotation</span>
                        <h2 class="text-2xl font-black text-neutral-900 tracking-tight">Complementary Silhouettes</h2>
                    </div>
                    <a href="shop.php?category=<?= urlencode($product['category']) ?>" class="text-xs font-black uppercase tracking-wider text-neutral-500 hover:text-black transition">
                        View All &rarr;
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-5">
                    <?php foreach ($relatedProducts as $rel): 
                        $relGender = !empty($rel['gender']) ? strtoupper($rel['gender']) : 'MEN';
                    ?>
                        <div class="bg-white rounded-[2rem] p-3.5 sm:p-4 border border-neutral-200/80 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group">
                            <div>
                                <div class="w-full aspect-[4/5] rounded-[1.5rem] overflow-hidden bg-neutral-100 relative mb-3">
                                    <img 
                                        src="<?= htmlspecialchars($rel['image_url']) ?>" 
                                        alt="<?= htmlspecialchars($rel['product_name']) ?>" 
                                        loading="lazy"
                                        referrerpolicy="no-referrer"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                                        onerror="this.onerror=null;this.src='<?= $defaultSvg ?>';"
                                    >
                                    <span class="absolute top-2.5 left-2.5 px-2.5 py-1 rounded-lg bg-neutral-950/75 backdrop-blur-md text-white text-[8px] font-black uppercase tracking-wider border border-white/10">
                                        <?= htmlspecialchars($relGender) ?>
                                    </span>
                                </div>

                                <div class="space-y-0.5 px-1">
                                    <span class="text-[8px] font-black uppercase tracking-widest text-neutral-400 block"><?= htmlspecialchars($rel['category']) ?></span>
                                    <h3 class="text-xs font-black text-neutral-900 truncate group-hover:text-neutral-600 transition-colors"><?= htmlspecialchars($rel['product_name']) ?></h3>
                                    <p class="text-xs font-black text-neutral-900 pt-0.5">₹<?= number_format($rel['price'], 0) ?></p>
                                </div>
                            </div>

                            <div class="pt-3 px-1">
                                <a 
                                    href="product_details.php?id=<?= $rel['product_id'] ?>" 
                                    class="w-full py-2.5 rounded-full bg-neutral-100 group-hover:bg-neutral-950 group-hover:text-white text-neutral-900 text-[9px] font-black uppercase tracking-widest text-center block transition-all shadow-sm"
                                >
                                    View Piece &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <?php 
    if (file_exists(__DIR__ . '/includes/footer.php')) {
        require_once __DIR__ . '/includes/footer.php'; 
    }
    ?>

</body>
</html>