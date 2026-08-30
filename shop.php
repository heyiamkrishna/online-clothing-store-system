<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Safe Database Connection Loader
if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
}

// 1. Filter & Search Parameters
$selectedGender   = trim($_GET['gender'] ?? '');
$selectedCategory = trim($_GET['category'] ?? '');
$searchQuery      = trim($_GET['search'] ?? '');
$sortBy           = trim($_GET['sort'] ?? 'latest');




// 2. Build Query
$sql = "SELECT * FROM product WHERE is_active = 1";
$params = [];

// Gender filter
if (!empty($selectedGender) && in_array(strtolower($selectedGender), ['men', 'women', 'kids'])) {
    $sql .= " AND (LOWER(gender) = LOWER(?) OR LOWER(gender) = 'unisex')";
    $params[] = $selectedGender;
}

// Category filter
if (!empty($selectedCategory)) {
    $sql .= " AND LOWER(category) = LOWER(?)";
    $params[] = $selectedCategory;
}

// Search filter
if (!empty($searchQuery)) {
    $sql .= " AND (product_name LIKE ? OR description LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

// Sorting logic
switch ($sortBy) {
    case 'price_low':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY price DESC";
        break;
    case 'name':
        $sql .= " ORDER BY product_name ASC";
        break;
    case 'latest':
    default:
        $sql .= " ORDER BY product_id DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts per Category
$catStmt = $pdo->query("SELECT category, COUNT(*) as cnt FROM product WHERE is_active = 1 GROUP BY category");
$categoryStats = $catStmt ? $catStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Counts per Department
$menCount   = (int)$pdo->query("SELECT COUNT(*) FROM product WHERE is_active = 1 AND (LOWER(gender) = 'men' OR LOWER(gender) = 'unisex')")->fetchColumn();
$womenCount = (int)$pdo->query("SELECT COUNT(*) FROM product WHERE is_active = 1 AND (LOWER(gender) = 'women' OR LOWER(gender) = 'unisex')")->fetchColumn();
$kidsCount  = (int)$pdo->query("SELECT COUNT(*) FROM product WHERE is_active = 1 AND (LOWER(gender) = 'kids' OR LOWER(gender) = 'unisex')")->fetchColumn();
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM product WHERE is_active = 1")->fetchColumn();

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
    <title>Catalog Vault — CLOTHSTORE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8F9FA] text-neutral-900 min-h-screen antialiased selection:bg-black selection:text-white">

    <main class="max-w-7xl mx-auto px-4 sm:px-8 py-8 sm:py-12 space-y-8 sm:space-y-10">

        <!-- 1. RESPONSIVE HERO DEPARTMENT HEADER -->
        <section class="bg-neutral-950 text-white rounded-[2rem] sm:rounded-[2.5rem] lg:rounded-[3rem] p-5 sm:p-8 lg:p-12 border border-white/10 shadow-2xl relative overflow-hidden">
            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6 sm:gap-8">
                
                <div class="space-y-2 sm:space-y-3 max-w-xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-full bg-white/10 text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-neutral-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Archive Catalog Edition</span>
                    </div>

                    <h1 class="text-2xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                        <?= !empty($selectedGender) ? htmlspecialchars(ucfirst($selectedGender)) . " Department" : "Full Catalog Vault" ?>
                    </h1>
                    <p class="text-[11px] sm:text-xs lg:text-sm text-neutral-400 font-normal leading-relaxed">
                        Curated collection of 240+ GSM dropped-shoulder silhouettes, utility cargos, and heavyweight streetwear essentials.
                    </p>
                </div>

                <!-- Responsive Department Selector -->
                <div class="w-full lg:w-auto bg-neutral-900/90 p-1.5 sm:p-2 rounded-2xl sm:rounded-full border border-white/10">
                    <div class="grid grid-cols-4 lg:flex lg:flex-wrap items-center gap-1 sm:gap-2">
                        
                        <a 
                            href="shop.php" 
                            class="py-2.5 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 text-center <?= empty($selectedGender) ? 'bg-white text-black shadow-lg' : 'text-neutral-400 hover:text-white' ?>"
                        >
                            <span>All</span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded-full <?= empty($selectedGender) ? 'bg-black text-white' : 'bg-neutral-800 text-neutral-400' ?>"><?= $totalCount ?></span>
                        </a>
                        
                        <a 
                            href="shop.php?gender=Men" 
                            class="py-2.5 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 text-center <?= strtolower($selectedGender) === 'men' ? 'bg-white text-black shadow-lg' : 'text-neutral-400 hover:text-white' ?>"
                        >
                            <span>Men</span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded-full <?= strtolower($selectedGender) === 'men' ? 'bg-black text-white' : 'bg-neutral-800 text-neutral-400' ?>"><?= $menCount ?></span>
                        </a>

                        <a 
                            href="shop.php?gender=Women" 
                            class="py-2.5 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 text-center <?= strtolower($selectedGender) === 'women' ? 'bg-white text-black shadow-lg' : 'text-neutral-400 hover:text-white' ?>"
                        >
                            <span>Women</span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded-full <?= strtolower($selectedGender) === 'women' ? 'bg-black text-white' : 'bg-neutral-800 text-neutral-400' ?>"><?= $womenCount ?></span>
                        </a>

                        <a 
                            href="shop.php?gender=Kids" 
                            class="py-2.5 px-2 sm:px-4 rounded-xl sm:rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider transition-all flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 text-center <?= strtolower($selectedGender) === 'kids' ? 'bg-white text-black shadow-lg' : 'text-neutral-400 hover:text-white' ?>"
                        >
                            <span>Kids</span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded-full <?= strtolower($selectedGender) === 'kids' ? 'bg-black text-white' : 'bg-neutral-800 text-neutral-400' ?>"><?= $kidsCount ?></span>
                        </a>

                    </div>
                </div>

            </div>
        </section>


        <!-- 2. FILTER CONTROLS & SEARCH BAR -->
        <section class="bg-white rounded-[2rem] p-4 sm:p-5 border border-neutral-200/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <!-- Category Chips -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0 scrollbar-none">
                <a 
                    href="shop.php<?= !empty($selectedGender) ? '?gender=' . urlencode($selectedGender) : '' ?>" 
                    class="px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition <?= empty($selectedCategory) ? 'bg-black text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200' ?>"
                >
                    All Types
                </a>
                <?php foreach ($categoryStats as $cs): 
                    $isActiveCat = strtolower($selectedCategory) === strtolower($cs['category']);
                    $catUrl = 'shop.php?' . (!empty($selectedGender) ? 'gender=' . urlencode($selectedGender) . '&' : '') . 'category=' . urlencode($cs['category']);
                ?>
                    <a 
                        href="<?= $catUrl ?>" 
                        class="px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition <?= $isActiveCat ? 'bg-black text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200' ?>"
                    >
                        <?= htmlspecialchars($cs['category']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Search and Sort Controls -->
            <div class="flex items-center gap-3 shrink-0">
                
                <!-- Quick Sort Dropdown -->
                <form id="sortForm" action="shop.php" method="GET" class="relative">
                    <?php if (!empty($selectedGender)): ?><input type="hidden" name="gender" value="<?= htmlspecialchars($selectedGender) ?>"><?php endif; ?>
                    <?php if (!empty($selectedCategory)): ?><input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>"><?php endif; ?>
                    <?php if (!empty($searchQuery)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>"><?php endif; ?>
                    
                    <select 
                        name="sort" 
                        onchange="document.getElementById('sortForm').submit()" 
                        class="text-xs font-bold bg-neutral-100 hover:bg-neutral-200 border-none rounded-full px-4 py-2.5 text-neutral-800 cursor-pointer focus:ring-2 focus:ring-black outline-none"
                    >
                        <option value="latest" <?= $sortBy === 'latest' ? 'selected' : '' ?>>Newest Arrivals</option>
                        <option value="price_low" <?= $sortBy === 'price_low' ? 'selected' : '' ?>>Price: Low &rarr; High</option>
                        <option value="price_high" <?= $sortBy === 'price_high' ? 'selected' : '' ?>>Price: High &rarr; Low</option>
                        <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Alphabetical</option>
                    </select>
                </form>

                <!-- Search Input Form -->
                <form action="shop.php" method="GET" class="relative">
                    <?php if (!empty($selectedGender)): ?><input type="hidden" name="gender" value="<?= htmlspecialchars($selectedGender) ?>"><?php endif; ?>
                    <?php if (!empty($selectedCategory)): ?><input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>"><?php endif; ?>
                    
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($searchQuery) ?>" 
                        placeholder="Search drop..." 
                        class="w-40 sm:w-56 py-2.5 pl-4 pr-9 rounded-full bg-neutral-100 text-neutral-900 text-xs font-semibold placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-black focus:bg-white transition"
                    >
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-black">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </form>

            </div>

        </section>


        <!-- 3. PRODUCT CARDS GRID -->
        <?php if (empty($products)): ?>
            <div class="bg-white rounded-[3rem] p-16 text-center border border-neutral-200/80 shadow-sm space-y-4 max-w-xl mx-auto">
                <div class="w-16 h-16 rounded-full bg-neutral-100 flex items-center justify-center mx-auto text-2xl">
                    ⚡
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-black text-neutral-900">No matching garments discovered</h3>
                    <p class="text-xs text-neutral-400">Try removing search keywords or changing the selected category.</p>
                </div>
                <a href="shop.php" class="inline-block px-7 py-3 rounded-full bg-black text-white text-xs font-black uppercase tracking-wider hover:bg-neutral-800 transition">
                    Clear All Filters
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5 sm:gap-7">
                <?php foreach ($products as $prod): 
                    $genderTag = !empty($prod['gender']) ? strtoupper($prod['gender']) : 'MEN';
                    $prodId = (int)$prod['product_id'];
                ?>
                    <div class="bg-white rounded-[2rem] p-3.5 sm:p-4 border border-neutral-200/80 shadow-[0_10px_30px_rgba(0,0,0,0.02)] hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group">
                        
                        <div>
                            <!-- Image Box -->
                            <div class="w-full aspect-[4/5] rounded-[1.5rem] overflow-hidden bg-neutral-100 relative mb-3.5">
                                <img 
                                    src="<?= htmlspecialchars($prod['image_url']) ?>" 
                                    alt="<?= htmlspecialchars($prod['product_name']) ?>" 
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                                    onerror="this.onerror=null;this.src='<?= $defaultSvg ?>';"
                                >
                                <span class="absolute top-2.5 left-2.5 px-2.5 py-1 rounded-lg bg-neutral-950/75 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider border border-white/10 shadow-sm">
                                    <?= htmlspecialchars($genderTag) ?>
                                </span>

                                <?php if ($prod['stock'] <= 5 && $prod['stock'] > 0): ?>
                                    <span class="absolute bottom-2.5 right-2.5 px-2 py-0.5 rounded-md bg-amber-500 text-white text-[8px] font-black uppercase tracking-wider">
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

                        <!-- Direct Link to product_detail.php -->
                        <div class="pt-4 px-1">
                            <a 
                                href="product_details.php?id=<?= $prodId ?>" 
                                class="w-full py-3 rounded-full bg-neutral-100 group-hover:bg-neutral-950 group-hover:text-white text-neutral-900 text-[10px] font-black uppercase tracking-widest text-center block transition-all shadow-sm"
                            >
                                View Garment &rarr;
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
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