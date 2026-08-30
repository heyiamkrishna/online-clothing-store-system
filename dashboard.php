<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = (int)$_SESSION['customer_id'];
$successMsg = '';
$errorMsg   = '';

// Handle Profile Update Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $newName    = trim($_POST['name'] ?? '');
    $newPhone   = trim($_POST['phone'] ?? '');
    $newAddress = trim($_POST['address'] ?? '');

    // Normalize phone number (strip spaces, hyphens, parentheses)
    $cleanPhone = preg_replace('/[\s\-\(\)]+/', '', $newPhone);

    if (empty($newName) || empty($cleanPhone) || empty($newAddress)) {
        $errorMsg = 'Please fill out all profile fields.';
    } elseif (!preg_match('/^(?:(?:\+|0{0,2})91(\s*[\-]\s*)?|[0]?)?[6789]\d{9}$/', $cleanPhone)) {
        $errorMsg = 'Please enter a valid 10-digit Indian mobile number (e.g. 9876543210 or +91 9876543210).';
    } else {
        $updateStmt = $pdo->prepare("
            UPDATE customer 
            SET name = ?, phone = ?, address = ? 
            WHERE customer_id = ?
        ");

        if ($updateStmt->execute([$newName, $cleanPhone, $newAddress, $customerId])) {
            $_SESSION['customer_name'] = $newName; // Instantly sync header dropdown name
            $successMsg = 'Profile details updated successfully!';
        } else {
            $errorMsg = 'Failed to update profile. Please try again.';
        }
    }
}

// Fetch Fresh Customer Profile Data
$custStmt = $pdo->prepare("SELECT * FROM customer WHERE customer_id = ?");
$custStmt->execute([$customerId]);
$customer = $custStmt->fetch();

// Fetch Recent Orders (Limit 3)
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY order_id DESC LIMIT 3");
$orderStmt->execute([$customerId]);
$recentOrders = $orderStmt->fetchAll();

// Count Total Orders
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
$countStmt->execute([$customerId]);
$totalOrders = $countStmt->fetchColumn();
?>

<div class="max-w-6xl mx-auto py-8">
    
    <!-- Welcome Header -->
    <div class="mb-8">
        <span class="text-[11px] font-bold tracking-widest uppercase text-gray-400">Account Overview</span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight mt-1">
            Hello, <?= htmlspecialchars(explode(' ', trim($customer['name']))[0]) ?>.
        </h1>
        <p class="text-xs text-gray-500 mt-1.5">Manage your personal delivery details and track your latest clothing orders.</p>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($successMsg)): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl mb-6 text-xs font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span><?= htmlspecialchars($successMsg) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl mb-6 text-xs font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Left Column: Editable Profile Card -->
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-white rounded-[2.5rem] p-6 sm:p-8 border border-gray-200/80 shadow-[0_4px_25px_rgba(0,0,0,0.02)]">
                
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-gray-900">Edit Profile</h2>
                        <p class="text-[11px] text-gray-400 mt-0.5">Update your personal and shipping information.</p>
                    </div>
                    <span class="w-8 h-8 rounded-full bg-black text-white flex items-center justify-center text-xs font-bold uppercase">
                        <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                    </span>
                </div>

                <form action="dashboard.php" method="POST" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="update_profile">

                    <!-- Full Name -->
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Full Name</label>
                        <input 
                            type="text" 
                            name="name" 
                            required 
                            value="<?= htmlspecialchars($customer['name']) ?>"
                            class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                        >
                    </div>

                    <!-- Email (Locked / Read-Only for Security) -->
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="block font-bold uppercase tracking-wider text-gray-700">Gmail Address</label>
                            <span class="text-[10px] text-gray-400 font-semibold">Primary ID</span>
                        </div>
                        <input 
                            type="email" 
                            value="<?= htmlspecialchars($customer['email']) ?>"
                            disabled
                            class="w-full p-3.5 bg-gray-100 border border-gray-200 rounded-2xl text-xs text-gray-500 cursor-not-allowed"
                        >
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Phone Number (India)</label>
                        <input 
                            type="tel" 
                            name="phone" 
                            required 
                            pattern="^(?:(?:\+|0{0,2})91(\s*[\-]\s*)?|[0]?)?[6789]\d{9}$"
                            title="Enter a valid 10-digit Indian phone number starting with 6, 7, 8, or 9"
                            value="<?= htmlspecialchars($customer['phone']) ?>"
                            class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                        >
                    </div>

                    <!-- Shipping Address -->
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Default Shipping Address</label>
                        <textarea 
                            name="address" 
                            required 
                            rows="3"
                            class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                        ><?= htmlspecialchars($customer['address']) ?></textarea>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-black text-white font-bold text-xs uppercase tracking-widest py-3.5 rounded-full hover:bg-neutral-800 transition shadow-md hover:shadow-lg mt-2"
                    >
                        Save Changes
                    </button>
                </form>

            </div>

            <!-- Sign Out Button -->
            <a href="logout.php" class="w-full bg-red-50 text-red-600 font-bold text-xs uppercase tracking-widest py-3.5 rounded-full hover:bg-red-100 border border-red-100 transition flex items-center justify-center gap-1.5">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span>Sign Out</span>
            </a>
        </div>

        <!-- Right Column: Recent Activity & Orders -->
        <div class="lg:col-span-7">
            <div class="bg-white rounded-[2.5rem] p-6 sm:p-8 border border-gray-200/80 shadow-[0_4px_25px_rgba(0,0,0,0.02)] h-full">
                
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Recent Orders</h2>
                        <p class="text-[11px] text-gray-400 mt-0.5">Total orders placed: <strong><?= $totalOrders ?></strong></p>
                    </div>
                    <a href="order_history.php" class="text-xs font-bold uppercase tracking-wider text-black underline hover:text-gray-500 transition">
                        View All
                    </a>
                </div>

                <?php if (empty($recentOrders)): ?>
                    <div class="text-center py-16">
                        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3 text-gray-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                        </div>
                        <p class="text-xs font-bold text-gray-700">No orders placed yet</p>
                        <p class="text-[11px] text-gray-400 mt-1 mb-4">Discover our collection and make your first order.</p>
                        <a href="shop.php" class="inline-block bg-black text-white text-[11px] font-bold uppercase tracking-wider px-6 py-2.5 rounded-full hover:bg-neutral-800 transition">
                            Explore Shop
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recentOrders as $order): ?>
                            <div class="p-5 rounded-2xl border border-gray-100 hover:border-black/20 hover:shadow-sm transition bg-[#FAFBFB] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-gray-900">Order #<?= $order['order_id'] ?></span>
                                        <span class="text-gray-300">•</span>
                                        <span class="text-[11px] text-gray-500"><?= date('d M Y', strtotime($order['order_date'])) ?></span>
                                    </div>
                                    <p class="text-[11px] text-gray-400 truncate max-w-xs">
                                        Deliver to: <?= htmlspecialchars($order['shipping_add']) ?>
                                    </p>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-4">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest border <?= $order['payment_stat'] === 'Verified' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' ?>">
                                        <?= htmlspecialchars($order['payment_stat']) ?>
                                    </span>
                                    <span class="font-extrabold text-sm text-gray-900">
                                        ₹<?= number_format($order['total_amount'], 2) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

</div>

<?php require_once 'includes/footer.php'; ?>