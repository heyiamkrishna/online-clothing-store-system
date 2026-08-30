<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cartCount = 0;
if (isset($_SESSION['customer_id']) && isset($pdo)) {
    $countStmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE customer_id = ?");
    $countStmt->execute([(int)$_SESSION['customer_id']]);
    $cartCount = (int)$countStmt->fetchColumn();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentCategory = $_GET['category'] ?? '';

// Extract first name for the navbar display
$customerDisplayName = 'Account';
if (isset($_SESSION['customer_name'])) {
    $customerDisplayName = htmlspecialchars(explode(' ', trim($_SESSION['customer_name']))[0]);
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloth Store — Minimal Aesthetics</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Font (Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#FAFAFA] text-neutral-900 font-sans min-h-screen flex flex-col justify-between antialiased selection:bg-black selection:text-white">

    <!-- Full-Width Edge-to-Edge Glassmorphism Header -->
    <header class="sticky top-0 z-50 w-full backdrop-blur-2xl bg-white/70 border-b border-black/[0.06] shadow-[0_4px_30px_rgba(0,0,0,0.03)] transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-8 h-16 sm:h-20 flex justify-between items-center">
            
            <!-- Brand Logo -->
            <a href="index.php" class="flex items-center gap-2.5 text-base sm:text-lg font-black tracking-widest uppercase text-neutral-900 hover:opacity-75 transition z-50">
                <span class="w-2 h-2 rounded-full bg-black"></span>
                <span>CLOTH<span class="text-neutral-400 font-light">STORE</span></span>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden md:flex items-center space-x-8 text-xs font-bold tracking-widest uppercase text-neutral-500">
                <a href="index.php" class="hover:text-black transition-colors <?= ($currentPage === 'index.php' && empty($currentCategory)) ? 'text-black' : '' ?>">Home</a>
                <a href="shop.php" class="hover:text-black transition-colors <?= ($currentPage === 'shop.php' && empty($currentCategory)) ? 'text-black' : '' ?>">Shop All</a>
                <a href="shop.php?category=T-Shirts" class="hover:text-black transition-colors <?= ($currentCategory === 'T-Shirts') ? 'text-black' : '' ?>">T-Shirts</a>
                <a href="shop.php?category=Shirts" class="hover:text-black transition-colors <?= ($currentCategory === 'Shirts') ? 'text-black' : '' ?>">Shirts</a>
                <a href="shop.php?category=Jeans" class="hover:text-black transition-colors <?= ($currentCategory === 'Jeans') ? 'text-black' : '' ?>">Jeans</a>
                <a href="shop.php?category=Hoodies" class="hover:text-black transition-colors <?= ($currentCategory === 'Hoodies') ? 'text-black' : '' ?>">Hoodies</a>
            </nav>

            <!-- Actions (Cart, Account, Mobile Toggle) -->
            <div class="flex items-center gap-3 z-50">
                
                <!-- Shopping Cart Pill -->
                <a href="cart.php" class="relative px-3.5 sm:px-4 py-2 rounded-full border border-black/10 hover:border-black transition-all flex items-center gap-2 text-neutral-900 bg-white/50 backdrop-blur-md text-xs font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span class="hidden sm:inline">Bag</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="bg-black text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded-full"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>

                <?php if (isset($_SESSION['customer_id'])): ?>
                    <!-- Desktop User Dropdown -->
                    <div class="relative group hidden sm:inline-block">
                        <button type="button" class="flex items-center gap-2 px-4 py-2 rounded-full bg-black text-white hover:bg-neutral-800 transition shadow-sm text-xs font-bold tracking-wide">
                            <span class="w-4 h-4 rounded-full bg-white/20 flex items-center justify-center text-[10px] uppercase font-black">
                                <?= strtoupper(substr($customerDisplayName, 0, 1)) ?>
                            </span>
                            <span><?= $customerDisplayName ?></span>
                        </button>

                        <div class="absolute right-0 top-full pt-2 w-56 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 ease-out transform group-hover:translate-y-0 translate-y-2 z-50">
                            <div class="bg-white/90 backdrop-blur-2xl border border-black/5 rounded-2xl shadow-[0_15px_40px_rgba(0,0,0,0.08)] p-2 text-xs divide-y divide-neutral-100">
                                <div class="px-3.5 py-2.5">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-neutral-400">Signed in as</p>
                                    <p class="font-black text-neutral-900 text-sm truncate"><?= htmlspecialchars($_SESSION['customer_name'] ?? 'Customer') ?></p>
                                </div>
                                <div class="py-2 px-1.5 space-y-1 text-xs font-bold text-neutral-600">
    
    <!-- My Profile -->
    <a href="dashboard.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl hover:bg-neutral-100 hover:text-black transition-all group">
        <span class="w-7 h-7 rounded-xl bg-neutral-100 text-neutral-600 flex items-center justify-center group-hover:bg-black group-hover:text-white transition-all">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </span>
        <span class="tracking-tight">My Profile</span>
    </a>

    <!-- Order History -->
    <a href="order_history.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl hover:bg-neutral-100 hover:text-black transition-all group">
        <span class="w-7 h-7 rounded-xl bg-neutral-100 text-neutral-600 flex items-center justify-center group-hover:bg-black group-hover:text-white transition-all">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
        </span>
        <span class="tracking-tight">Order History</span>
    </a>

</div>
                                <div class="pt-1.5">
                                    <a href="logout.php" class="flex items-center gap-2 px-3 py-2 rounded-xl text-red-600 hover:bg-red-50 font-bold transition">Log Out</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="hidden sm:flex items-center gap-3 text-xs font-bold tracking-wide">
                        <a href="login.php" class="hover:text-black text-neutral-500 transition px-2">Login</a>
                        <a href="register.php" class="bg-black text-white px-4 py-2 rounded-full hover:bg-neutral-800 transition shadow-sm">Join</a>
                    </div>
                <?php endif; ?>

                <!-- Full-Screen Mobile Toggle Button -->
                <button 
                    id="fullscreen-menu-toggle" 
                    type="button" 
                    class="md:hidden p-2 text-neutral-900 hover:bg-black/5 rounded-full transition focus:outline-none"
                    aria-label="Toggle navigation menu"
                >
                    <svg id="icon-hamburger" class="w-6 h-6 block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                    <svg id="icon-close" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

            </div>
        </div>
    </header>

    <!-- Full-Screen Mobile Glassmorphism Overlay -->
    <div id="fullscreen-nav" class="fixed inset-0 z-40 bg-white/95 backdrop-blur-3xl opacity-0 pointer-events-none transition-all duration-300 flex flex-col justify-between p-8 pt-28 md:hidden">
        
        <!-- Large Nav Links -->
        <nav class="flex flex-col space-y-5 text-2xl font-black uppercase tracking-tight text-neutral-800">
            <a href="index.php" class="hover:text-neutral-400 transition-colors <?= ($currentPage === 'index.php' && empty($currentCategory)) ? 'text-black' : '' ?>">Home</a>
            <a href="shop.php" class="hover:text-neutral-400 transition-colors <?= ($currentPage === 'shop.php' && empty($currentCategory)) ? 'text-black' : '' ?>">Shop All</a>
            <a href="shop.php?category=T-Shirts" class="hover:text-neutral-400 transition-colors <?= ($currentCategory === 'T-Shirts') ? 'text-black' : '' ?>">T-Shirts</a>
            <a href="shop.php?category=Shirts" class="hover:text-neutral-400 transition-colors <?= ($currentCategory === 'Shirts') ? 'text-black' : '' ?>">Shirts</a>
            <a href="shop.php?category=Jeans" class="hover:text-neutral-400 transition-colors <?= ($currentCategory === 'Jeans') ? 'text-black' : '' ?>">Jeans</a>
            <a href="shop.php?category=Hoodies" class="hover:text-neutral-400 transition-colors <?= ($currentCategory === 'Hoodies') ? 'text-black' : '' ?>">Hoodies</a>
        </nav>

        <!-- Bottom Account Area in Full Screen -->
        <div class="pt-6 border-t border-black/10">
            <?php if (isset($_SESSION['customer_id'])): ?>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-neutral-400">Signed in as</p>
                        <p class="font-extrabold text-neutral-900 text-base"><?= htmlspecialchars($_SESSION['customer_name'] ?? 'Customer') ?></p>
                    </div>
                    <a href="logout.php" class="text-xs font-bold text-red-600 bg-red-50 px-3.5 py-2 rounded-full">Log Out</a>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <a href="dashboard.php" class="py-3 text-center rounded-2xl bg-neutral-100 font-bold text-xs">My Profile</a>
                    <a href="order_history.php" class="py-3 text-center rounded-2xl bg-neutral-100 font-bold text-xs">Order History</a>
                </div>
            <?php else: ?>
                <div class="flex gap-3">
                    <a href="login.php" class="w-1/2 py-3.5 text-center rounded-full bg-neutral-100 text-neutral-900 font-bold text-sm">Login</a>
                    <a href="register.php" class="w-1/2 py-3.5 text-center rounded-full bg-black text-white font-bold text-sm">Join Store</a>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-8 py-8 flex-grow w-full">

    <!-- Full-Screen Menu Toggle Script -->
    <script>
        const fsToggleBtn = document.getElementById('fullscreen-menu-toggle');
        const fsNav = document.getElementById('fullscreen-nav');
        const iconHamburger = document.getElementById('icon-hamburger');
        const iconClose = document.getElementById('icon-close');

        if (fsToggleBtn && fsNav) {
            fsToggleBtn.addEventListener('click', () => {
                const isOpen = !fsNav.classList.contains('pointer-events-none');
                
                if (isOpen) {
                    // Close Fullscreen Menu
                    fsNav.classList.add('opacity-0', 'pointer-events-none');
                    fsNav.classList.remove('opacity-100', 'pointer-events-auto');
                    iconHamburger.classList.remove('hidden');
                    iconClose.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                } else {
                    // Open Fullscreen Menu
                    fsNav.classList.remove('opacity-0', 'pointer-events-none');
                    fsNav.classList.add('opacity-100', 'pointer-events-auto');
                    iconHamburger.classList.add('hidden');
                    iconClose.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                }
            });
        }
    </script>