<?php
// Initialize output buffering and session at the very beginning
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

// Fetch all active products
$stmt = $pdo->query("SELECT * FROM product WHERE is_active = 1 ORDER BY product_id DESC");
$products = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Calculate Department Counts for Tabs
$menCount   = 0;
$womenCount = 0;
$kidsCount  = 0;
$totalCount = count($products);

foreach ($products as $p) {
    $g = strtolower($p['gender'] ?? 'men');
    if ($g === 'men' || $g === 'unisex') $menCount++;
    if ($g === 'women' || $g === 'unisex') $womenCount++;
    if ($g === 'kids' || $g === 'unisex') $kidsCount++;
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
    <title>CLOTHSTORE — Engineered Vault Rotation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8F9FA] text-neutral-900 min-h-screen antialiased selection:bg-black selection:text-white">

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-14 sm:space-y-20">

        <!-- 1. EDITORIAL HERO SECTION -->
        <section class="bg-neutral-950 text-white rounded-[2rem] sm:rounded-[2.5rem] lg:rounded-[3.5rem] p-6 sm:p-10 lg:p-14 border border-white/10 shadow-2xl relative overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
                
                <div class="lg:col-span-7 space-y-6">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-neutral-300 border border-white/5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Signature Vault Drop 01</span>
                    </div>

                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight leading-[1.08]">
                        Unleash Your <br>
                        <span class="text-neutral-400">Silhouette.</span> <br>
                        Engineered Rotation.
                    </h1>

                    <p class="text-xs sm:text-sm text-neutral-400 font-normal max-w-lg leading-relaxed">
                        Heavyweight 240+ GSM combed cotton, dropped-shoulder cuts, and tactical utilitarian layers designed for modern street culture.
                    </p>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <a 
                            href="#curated-section" 
                            class="w-full sm:w-auto text-center px-8 py-4 rounded-full bg-white text-black text-xs font-black uppercase tracking-widest hover:bg-neutral-200 transition active:scale-95 shadow-xl"
                        >
                            Explore Drop &darr;
                        </a>
                        <a 
                            href="shop.php" 
                            class="w-full sm:w-auto text-center px-8 py-4 rounded-full bg-neutral-900 text-white border border-white/15 text-xs font-black uppercase tracking-widest hover:bg-neutral-800 transition active:scale-95"
                        >
                            Vault Catalog
                        </a>
                    </div>

                    <div class="grid grid-cols-3 gap-3 sm:gap-4 pt-6 border-t border-white/10 max-w-md">
                        <div>
                            <span class="block text-base sm:text-lg font-black text-white">240 GSM</span>
                            <span class="text-[9px] sm:text-[10px] uppercase font-bold text-neutral-400 tracking-wider">Heavyweight</span>
                        </div>
                        <div>
                            <span class="block text-base sm:text-lg font-black text-white">COD</span>
                            <span class="text-[9px] sm:text-[10px] uppercase font-bold text-neutral-400 tracking-wider">Doorstep Pay</span>
                        </div>
                        <div>
                            <span class="block text-base sm:text-lg font-black text-white">48 Hrs</span>
                            <span class="text-[9px] sm:text-[10px] uppercase font-bold text-neutral-400 tracking-wider">Express Transit</span>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-5 grid grid-cols-2 gap-3 sm:gap-4">
                    <div class="aspect-[3/4] sm:aspect-[4/5] rounded-[1.5rem] sm:rounded-[2rem] overflow-hidden bg-neutral-900 border border-white/10 relative shadow-2xl group">
                        <img 
                            src="https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600&q=80" 
                            alt="Vault Editorial 1" 
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent flex items-end p-4">
                            <span class="text-[9px] font-black uppercase tracking-wider text-white bg-black/50 backdrop-blur-md px-2.5 py-1 rounded-md border border-white/10">Look 01</span>
                        </div>
                    </div>
                    
                    <div class="aspect-[3/4] sm:aspect-[4/5] rounded-[1.5rem] sm:rounded-[2rem] overflow-hidden bg-neutral-900 border border-white/10 relative shadow-2xl mt-4 sm:mt-8 group">
                        <img 
                            src="https://images.unsplash.com/photo-1509631179647-0177331693ae?w=600&q=80" 
                            alt="Vault Editorial 2" 
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent flex items-end p-4">
                            <span class="text-[9px] font-black uppercase tracking-wider text-white bg-black/50 backdrop-blur-md px-2.5 py-1 rounded-md border border-white/10">Look 02</span>
                        </div>
                    </div>
                </div>

            </div>
        </section>


        <!-- 2. VALUE PROPS TICKER -->
        <section class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
            <div class="p-4 sm:p-5 rounded-[1.5rem] bg-white border border-neutral-200/80 shadow-sm flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-neutral-100 flex items-center justify-center text-sm font-bold">📦</div>
                <div>
                    <h4 class="text-xs font-black text-neutral-900">Cash on Delivery</h4>
                    <p class="text-[10px] text-neutral-400 font-medium">Pay on doorstep</p>
                </div>
            </div>

            <div class="p-4 sm:p-5 rounded-[1.5rem] bg-white border border-neutral-200/80 shadow-sm flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-neutral-100 flex items-center justify-center text-sm font-bold">⚡</div>
                <div>
                    <h4 class="text-xs font-black text-neutral-900">48-Hr Priority</h4>
                    <p class="text-[10px] text-neutral-400 font-medium">Express logistics</p>
                </div>
            </div>

            <div class="p-4 sm:p-5 rounded-[1.5rem] bg-white border border-neutral-200/80 shadow-sm flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-neutral-100 flex items-center justify-center text-sm font-bold">🛡️</div>
                <div>
                    <h4 class="text-xs font-black text-neutral-900">Heavyweight Fit</h4>
                    <p class="text-[10px] text-neutral-400 font-medium">Pre-shrunk cotton</p>
                </div>
            </div>

            <div class="p-4 sm:p-5 rounded-[1.5rem] bg-white border border-neutral-200/80 shadow-sm flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-neutral-100 flex items-center justify-center text-sm font-bold">🔄</div>
                <div>
                    <h4 class="text-xs font-black text-neutral-900">Easy Returns</h4>
                    <p class="text-[10px] text-neutral-400 font-medium">7-Day doorstep pickup</p>
                </div>
            </div>
        </section>


        <!-- 3. CURATED PIECES GRID WITH SEGMENTED FILTERS & VIEW MORE -->
        <section id="curated-section" class="space-y-8 scroll-mt-6">
            
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-neutral-200 pb-5">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Signature Vault Drop</span>
                    <h2 class="text-2xl sm:text-3xl font-black text-neutral-900 tracking-tight mt-0.5">Curated Pieces</h2>
                </div>

                <!-- Responsive Segmented Tabs -->
                <div class="w-full sm:w-auto bg-neutral-200/80 p-1.5 rounded-2xl sm:rounded-full border border-neutral-300/60">
                    <div class="grid grid-cols-4 sm:flex items-center gap-1">
                        
                        <button 
                            type="button" 
                            onclick="setTab('all', this)" 
                            class="dept-tab py-2 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 bg-black text-white shadow-sm"
                        >
                            <span>All</span>
                            <span class="tab-count text-[9px] px-1.5 py-0.2 rounded-full bg-white/20 text-white"><?= $totalCount ?></span>
                        </button>

                        <button 
                            type="button" 
                            onclick="setTab('men', this)" 
                            class="dept-tab py-2 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 text-neutral-600 hover:text-black"
                        >
                            <span>Men</span>
                            <span class="tab-count text-[9px] px-1.5 py-0.2 rounded-full bg-neutral-300 text-neutral-700"><?= $menCount ?></span>
                        </button>

                        <button 
                            type="button" 
                            onclick="setTab('women', this)" 
                            class="dept-tab py-2 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 text-neutral-600 hover:text-black"
                        >
                            <span>Women</span>
                            <span class="tab-count text-[9px] px-1.5 py-0.2 rounded-full bg-neutral-300 text-neutral-700"><?= $womenCount ?></span>
                        </button>

                        <button 
                            type="button" 
                            onclick="setTab('kids', this)" 
                            class="dept-tab py-2 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 text-neutral-600 hover:text-black"
                        >
                            <span>Kids</span>
                            <span class="tab-count text-[9px] px-1.5 py-0.2 rounded-full bg-neutral-300 text-neutral-700"><?= $kidsCount ?></span>
                        </button>

                    </div>
                </div>
            </div>

            <!-- Product Cards Grid -->
            <div id="productGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                <?php foreach ($products as $prod): 
                    $gender = !empty($prod['gender']) ? strtolower($prod['gender']) : 'men';
                    $displayImg = strpos($prod['image_url'], 'http') === 0 ? $prod['image_url'] : $prod['image_url'];
                    $prodId = (int)$prod['product_id'];
                ?>
                    <div 
                        class="item-card bg-white rounded-[1.8rem] sm:rounded-[2rem] p-3.5 sm:p-4 border border-neutral-200/80 shadow-[0_4px_20px_rgba(0,0,0,0.02)] hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group" 
                        data-gender="<?= htmlspecialchars($gender) ?>"
                    >
                        <div>
                            <!-- Image Container -->
                            <div class="w-full aspect-[4/5] rounded-[1.25rem] sm:rounded-[1.5rem] overflow-hidden bg-neutral-100 relative mb-3.5 shadow-inner">
                                <img 
                                    src="<?= htmlspecialchars($displayImg) ?>" 
                                    alt="<?= htmlspecialchars($prod['product_name']) ?>" 
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                                    onerror="this.onerror=null;this.src='<?= $defaultSvg ?>';"
                                >
                                
                                <span class="absolute top-2.5 left-2.5 px-2.5 py-1 rounded-lg bg-neutral-950/75 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider border border-white/10 shadow-sm">
                                    <?= strtoupper($gender) ?>
                                </span>

                                <?php if ($prod['stock'] <= 5 && $prod['stock'] > 0): ?>
                                    <span class="absolute bottom-2.5 right-2.5 px-2 py-0.5 rounded-md bg-amber-500 text-white text-[8px] font-black uppercase tracking-wider shadow-sm">
                                        Only <?= $prod['stock'] ?> Left
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Meta -->
                            <div class="space-y-1 px-1">
                                <span class="text-[9px] font-black uppercase tracking-widest text-neutral-400 block">
                                    <?= htmlspecialchars($prod['category']) ?>
                                </span>
                                <h3 class="text-xs font-black text-neutral-900 line-clamp-1 group-hover:text-neutral-600 transition-colors">
                                    <?= htmlspecialchars($prod['product_name']) ?>
                                </h3>
                                <p class="text-xs sm:text-sm font-black text-neutral-900 pt-0.5">
                                    ₹<?= number_format($prod['price'], 0) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Button Links Directly to product_details.php -->
                        <div class="pt-4 px-1">
                            <a 
                                href="product_details.php?id=<?= $prodId ?>" 
                                class="w-full py-2.5 sm:py-3 rounded-full bg-neutral-100 group-hover:bg-neutral-950 group-hover:text-white text-neutral-900 text-[10px] font-black uppercase tracking-widest text-center block transition-all active:scale-95 shadow-sm"
                            >
                                View Garment &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty Notice Fallback -->
            <div id="emptyNotice" class="hidden bg-white rounded-[2.5rem] p-12 sm:p-16 text-center border border-neutral-200 shadow-sm space-y-3 max-w-md mx-auto">
                <div class="w-14 h-14 rounded-full bg-neutral-100 flex items-center justify-center mx-auto text-xl">⚡</div>
                <h3 class="text-sm font-black text-neutral-900">No silhouettes found in this department</h3>
                <p class="text-xs text-neutral-400">New seasonal streetwear pieces are scheduled to drop soon.</p>
                <button 
                    type="button" 
                    onclick="setTab('all', document.querySelectorAll('.dept-tab')[0])" 
                    class="inline-block px-6 py-2.5 rounded-full bg-black text-white text-xs font-black uppercase tracking-wider hover:bg-neutral-800 transition"
                >
                    View All Vault Pieces
                </button>
            </div>

            <!-- Dynamic View More Button Redirecting to shop.php -->
            <div class="pt-4 pb-2 text-center flex flex-col sm:flex-row items-center justify-center gap-4">
                <a 
                    id="viewMoreShopBtn" 
                    href="shop.php" 
                    class="w-full sm:w-auto px-10 py-4 rounded-full bg-neutral-950 hover:bg-neutral-800 text-white text-xs font-black uppercase tracking-widest transition-all duration-300 shadow-xl hover:shadow-2xl hover:scale-105 active:scale-95 inline-flex items-center justify-center gap-3 group"
                >
                    <span id="viewMoreBtnText">Explore Full Vault Drop</span>
                    <span class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center group-hover:translate-x-1 transition-transform">
                        &rarr;
                    </span>
                </a>
            </div>

        </section>


        <!-- 4. ATELIER CRAFT & ARCHITECTURE MATRIX -->
        <section class="space-y-6">
            <div class="text-center max-w-xl mx-auto space-y-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400">Garment Precision</span>
                <h2 class="text-2xl sm:text-3xl font-black text-neutral-900 tracking-tight">The Darkwear Standard</h2>
                <p class="text-xs text-neutral-500">Every garment is engineered with tactile durability and contemporary silhouette architecture.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 sm:gap-6">
                
                <div class="bg-white rounded-[2.5rem] p-7 sm:p-8 border border-neutral-200/80 shadow-[0_10px_30px_rgba(0,0,0,0.02)] space-y-4 hover:-translate-y-1 transition duration-300">
                    <div class="w-12 h-12 rounded-2xl bg-neutral-950 text-white flex items-center justify-center text-xl shadow-md">
                        🧵
                    </div>
                    <div class="space-y-1.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-neutral-400">Fabric Composition</span>
                        <h3 class="text-base font-black text-neutral-900">240+ GSM Compact Combed</h3>
                        <p class="text-xs text-neutral-500 leading-relaxed">
                            Interlock knitted for heavy drape without stiffness. Pre-shrunk structure maintains its silhouette wash after wash.
                        </p>
                    </div>
                </div>

                <div class="bg-white rounded-[2.5rem] p-7 sm:p-8 border border-neutral-200/80 shadow-[0_10px_30px_rgba(0,0,0,0.02)] space-y-4 hover:-translate-y-1 transition duration-300">
                    <div class="w-12 h-12 rounded-2xl bg-neutral-950 text-white flex items-center justify-center text-xl shadow-md">
                        📐
                    </div>
                    <div class="space-y-1.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-neutral-400">Pattern Engineering</span>
                        <h3 class="text-base font-black text-neutral-900">Dropped Box Tailoring</h3>
                        <p class="text-xs text-neutral-500 leading-relaxed">
                            Proportionally balanced sleeve drop and relaxed chest profile, calibrated specifically for effortless street layering.
                        </p>
                    </div>
                </div>

                <div class="bg-white rounded-[2.5rem] p-7 sm:p-8 border border-neutral-200/80 shadow-[0_10px_30px_rgba(0,0,0,0.02)] space-y-4 hover:-translate-y-1 transition duration-300">
                    <div class="w-12 h-12 rounded-2xl bg-neutral-950 text-white flex items-center justify-center text-xl shadow-md">
                        🛡️
                    </div>
                    <div class="space-y-1.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-neutral-400">Reinforced Longevity</span>
                        <h3 class="text-base font-black text-neutral-900">Lock-Stitch Seams</h3>
                        <p class="text-xs text-neutral-500 leading-relaxed">
                            Double-needle collar binding and heavy-duty ribbed cuffs prevent neckline sagging over extended wear cycles.
                        </p>
                    </div>
                </div>

            </div>
        </section>


        <!-- 5. VIP VAULT ACCESS NEWSLETTER BANNER -->
        <section class="bg-neutral-950 text-white rounded-[2.5rem] sm:rounded-[3.5rem] p-8 sm:p-12 lg:p-14 border border-white/10 shadow-2xl relative overflow-hidden">
            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                
                <div class="lg:col-span-7 space-y-3">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-white/10 text-[9px] font-black uppercase tracking-widest text-neutral-300 border border-white/5">
                        <span>VIP Early Access</span>
                    </div>
                    <h3 class="text-2xl sm:text-4xl font-black tracking-tight leading-tight">
                        Receive 10% Off Your First Vault Order.
                    </h3>
                    <p class="text-xs sm:text-sm text-neutral-400 leading-relaxed max-w-lg">
                        Get private access to limited capsule releases, unlisted restocks, and seasonal lookbooks directly to your inbox.
                    </p>
                </div>

                <div class="lg:col-span-5">
                    <form onsubmit="event.preventDefault(); alert('Welcome to the Vault Club! Your discount code is: VAULT10');" class="flex flex-col sm:flex-row gap-2.5">
                        <input 
                            type="email" 
                            required 
                            placeholder="Enter your email address..." 
                            class="w-full px-5 py-4 rounded-full bg-neutral-900/90 border border-white/15 text-white text-xs font-semibold placeholder:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-white focus:bg-neutral-900"
                        >
                        <button 
                            type="submit" 
                            class="px-7 py-4 rounded-full bg-white text-black text-xs font-black uppercase tracking-widest hover:bg-neutral-200 transition shrink-0 shadow-lg active:scale-95 text-center"
                        >
                            Claim Code &rarr;
                        </button>
                    </form>
                    <p class="text-[10px] text-neutral-500 pt-2.5 px-2">Zero spam. Instant code generation upon confirmation.</p>
                </div>

            </div>
        </section>


        <!-- 6. HIGH-TRUST FULFILLMENT MATRIX -->
        <section class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-neutral-200/80 pt-8">
            <div class="flex items-center gap-3.5 p-4 rounded-2xl bg-white border border-neutral-200/60">
                <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center text-base shrink-0">🚚</div>
                <div>
                    <h5 class="text-xs font-black text-neutral-900">Doorstep Cash on Delivery</h5>
                    <p class="text-[10px] text-neutral-400">Inspect parcel and pay courier directly</p>
                </div>
            </div>

            <div class="flex items-center gap-3.5 p-4 rounded-2xl bg-white border border-neutral-200/60">
                <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center text-base shrink-0">🔄</div>
                <div>
                    <h5 class="text-xs font-black text-neutral-900">Hassle-Free Size Exchanges</h5>
                    <p class="text-[10px] text-neutral-400">7-day easy reverse pickup at your door</p>
                </div>
            </div>

            <div class="flex items-center gap-3.5 p-4 rounded-2xl bg-white border border-neutral-200/60">
                <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center text-base shrink-0">✨</div>
                <div>
                    <h5 class="text-xs font-black text-neutral-900">Dust-Bag Protected Delivery</h5>
                    <p class="text-[10px] text-neutral-400">Custom matte black protective packaging</p>
                </div>
            </div>
        </section>

    </main>

    <!-- Interactive Department Filter & Dynamic Shop Redirect Script -->
    <script>
        function setTab(gender, btn) {
            // Reset tab styling
            document.querySelectorAll('.dept-tab').forEach(b => {
                b.className = "dept-tab py-2 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 text-neutral-600 hover:text-black";
                const countBadge = b.querySelector('.tab-count');
                if (countBadge) countBadge.className = "tab-count text-[9px] px-1.5 py-0.2 rounded-full bg-neutral-300 text-neutral-700";
            });

            // Activate clicked tab
            btn.className = "dept-tab py-2 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 bg-black text-white shadow-sm";
            const activeBadge = btn.querySelector('.tab-count');
            if (activeBadge) activeBadge.className = "tab-count text-[9px] px-1.5 py-0.2 rounded-full bg-white/20 text-white";

            // Filter item cards
            const cards = document.querySelectorAll('.item-card');
            let count = 0;

            cards.forEach(card => {
                const cardGender = card.getAttribute('data-gender');
                if (gender === 'all' || cardGender === gender || cardGender === 'unisex') {
                    card.style.display = 'flex';
                    count++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Empty state notice
            const notice = document.getElementById('emptyNotice');
            if (count === 0) {
                notice.classList.remove('hidden');
            } else {
                notice.classList.add('hidden');
            }

            // Dynamically update the 'View More' button URL & Label
            const shopBtn = document.getElementById('viewMoreShopBtn');
            const shopBtnText = document.getElementById('viewMoreBtnText');

            if (gender === 'all') {
                shopBtn.href = "shop.php";
                shopBtnText.textContent = "Explore Full Vault Drop";
            } else {
                const capitalized = gender.charAt(0).toUpperCase() + gender.slice(1);
                shopBtn.href = "shop.php?gender=" + encodeURIComponent(capitalized);
                shopBtnText.textContent = "Explore All " + capitalized + " Silhouettes";
            }
        }
    </script>

    <?php 
    if (file_exists(__DIR__ . '/includes/footer.php')) {
        require_once __DIR__ . '/includes/footer.php'; 
    }
    ?>

</body>
</html>