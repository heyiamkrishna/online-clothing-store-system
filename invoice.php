<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db.php';

// Authentication Check
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$orderId = (int)($_GET['id'] ?? 0);
$customerId = (int)$_SESSION['customer_id'];

// 1. Fetch Order Record
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
$orderStmt->execute([$orderId, $customerId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("<div style='font-family:sans-serif; padding:40px; text-align:center;'><h2>Order not found or unauthorized access.</h2><a href='order_history.php'>Back to Orders</a></div>");
}

// 2. Fetch Order Items
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.product_name, p.category 
    FROM order_items oi 
    JOIN product p ON oi.product_id = p.product_id 
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Customer Details
$customer = null;
try {
    $custStmt = $pdo->prepare("SELECT * FROM customer WHERE customer_id = ?");
    $custStmt->execute([$customerId]);
    $customer = $custStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $customer = [
        'name'  => $_SESSION['customer_name'] ?? 'Customer',
        'email' => $_SESSION['customer_email'] ?? '',
        'phone' => ''
    ];
}

$custName = $customer['name'] ?? ($customer['customer_name'] ?? ($_SESSION['customer_name'] ?? 'Valued Client'));
$custEmail = $customer['email'] ?? ($customer['customer_email'] ?? 'support@clothstore.com');
$custPhone = $customer['phone'] ?? ($customer['contact'] ?? '+91 98765 43210');
$invoiceNumber = 'INV-' . date('Y', strtotime($order['order_date'] ?? 'now')) . '-' . str_pad($order['order_id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice — <?= $invoiceNumber ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- html2pdf Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            #invoice-card { border: none !important; box-shadow: none !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; }
        }
    </style>
</head>
<body class="bg-[#F2F3F5] p-3 sm:p-10 flex flex-col items-center min-h-screen antialiased selection:bg-black selection:text-white">

    <!-- Top Action Toolbar -->
    <div class="max-w-3xl w-full flex items-center justify-between mb-6 no-print">
        <a href="order_history.php" class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-neutral-500 hover:text-black transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            <span>Back to Orders</span>
        </a>
        <div class="flex items-center gap-2.5">
            <button onclick="window.print()" class="px-5 py-2.5 bg-white border border-neutral-300 hover:border-black text-neutral-800 text-xs font-extrabold uppercase tracking-wider rounded-full shadow-sm transition">
                Print
            </button>
            <button id="download-pdf" class="px-6 py-2.5 bg-black hover:bg-neutral-800 text-white text-xs font-black uppercase tracking-wider rounded-full shadow-lg flex items-center gap-2 hover:scale-105 active:scale-95 transition-all">
                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Download PDF</span>
            </button>
        </div>
    </div>

    <!-- Printable Invoice Container -->
    <div id="invoice-card" class="max-w-3xl w-full bg-white rounded-[2.5rem] p-6 sm:p-12 lg:p-14 shadow-[0_20px_60px_rgba(0,0,0,0.04)] border border-neutral-200/80 text-neutral-900 relative overflow-hidden">
        
        <!-- Enhanced Multi-Layer Right Corner Watermark Circle & Seal -->
        <div class="absolute -right-20 -top-20 w-80 h-80 rounded-full border-[18px] border-neutral-100/70 bg-gradient-to-br from-neutral-50/80 via-neutral-100/40 to-transparent pointer-events-none flex items-center justify-center -z-0">
            <div class="w-48 h-48 rounded-full border border-dashed border-neutral-300/60 flex items-center justify-center p-4">
                <span class="text-[9px] font-black uppercase tracking-[0.25em] text-neutral-300 text-center select-none rotate-12">
                    AUTH. 2026<br>CERTIFIED
                </span>
            </div>
        </div>

        <!-- 1. Header Area -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 border-b border-neutral-100 pb-8 relative z-10">
            <div>
                <a href="index.php" class="flex items-center gap-2.5 text-xl font-black tracking-widest uppercase text-neutral-900">
                    <span class="w-2.5 h-2.5 rounded-full bg-black"></span>
                    <span>CLOTH<span class="text-neutral-400 font-light">STORE</span></span>
                </a>
                <p class="text-[10px] font-extrabold tracking-widest text-neutral-400 uppercase mt-1">Official Purchase Invoice & Tax Document</p>
            </div>

            <div class="text-left sm:text-right">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-black text-white text-[9px] font-black uppercase tracking-widest rounded-full shadow-sm mb-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span><?= htmlspecialchars(strtoupper($order['payment_stat'] ?? 'PAID')) ?></span>
                </div>
                <p class="text-xs font-black tracking-tight text-neutral-900"><?= $invoiceNumber ?></p>
                <p class="text-[11px] text-neutral-400 font-semibold mt-0.5">
                    Issued: <?= date('d M Y, h:i A', strtotime($order['order_date'] ?? 'now')) ?>
                </p>
            </div>
        </div>

        <!-- 2. Meta Grid: Client, Shipping & Payment Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 py-8 border-b border-neutral-100 relative z-10 text-xs">
            
            <!-- Billed To -->
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block mb-1">Billed To</span>
                <p class="font-black text-neutral-900 text-sm"><?= htmlspecialchars($custName) ?></p>
                <p class="text-neutral-500 font-medium"><?= htmlspecialchars($custEmail) ?></p>
                <p class="text-neutral-500 font-medium"><?= htmlspecialchars($custPhone) ?></p>
            </div>

            <!-- Shipping Destination -->
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block mb-1">Shipping Destination</span>
                <p class="text-neutral-700 font-semibold leading-relaxed">
                    <?= nl2br(htmlspecialchars($order['shipping_add'] ?? 'Standard Delivery Address')) ?>
                </p>
            </div>

            <!-- Payment & Security Info -->
            <div class="space-y-1 sm:text-right">
                <span class="text-[10px] font-black uppercase tracking-widest text-neutral-400 block mb-1">Order Details</span>
                <p class="text-neutral-600"><span class="font-bold text-neutral-900">Method:</span> Cash on Delivery</p>
                <p class="text-neutral-600"><span class="font-bold text-neutral-900">Order Ref:</span> #<?= $order['order_id'] ?></p>
                <p class="text-neutral-600"><span class="font-bold text-neutral-900">Status:</span> <?= htmlspecialchars($order['payment_stat'] ?? 'Processing') ?></p>
            </div>

        </div>

        <!-- 3. Line Items Table -->
        <div class="py-6 relative z-10">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-neutral-200 text-[10px] font-black uppercase tracking-widest text-neutral-400">
                        <th class="py-3">Item Description</th>
                        <th class="py-3 text-center">Qty</th>
                        <th class="py-3 text-right">Unit Price</th>
                        <th class="py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <?php 
                    $subtotal = 0;
                    foreach ($items as $item): 
                        $unitPrice = $item['unit_price'] ?? ($item['price'] ?? 0);
                        $itemTotal = $unitPrice * $item['quantity'];
                        $subtotal += $itemTotal;
                    ?>
                        <tr class="group">
                            <td class="py-4">
                                <p class="font-black text-neutral-900 text-sm"><?= htmlspecialchars($item['product_name']) ?></p>
                                <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider"><?= htmlspecialchars($item['category'] ?? 'Apparel') ?></span>
                            </td>
                            <td class="py-4 text-center font-bold text-neutral-700"><?= $item['quantity'] ?></td>
                            <td class="py-4 text-right font-semibold text-neutral-600">₹<?= number_format($unitPrice, 2) ?></td>
                            <td class="py-4 text-right font-black text-neutral-900">₹<?= number_format($itemTotal, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 4. Total Calculation & Verification QR Area -->
        <div class="border-t border-neutral-200 pt-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 relative z-10">
            
            <!-- Dynamic Order Verification QR Stamp -->
            <div class="flex items-center gap-3.5 bg-neutral-50 border border-neutral-200/80 p-3 rounded-2xl">
                <img 
                    src="https://api.qrserver.com/v1/create-qr-code/?size=64x64&data=<?= urlencode("CLOTH-STORE-ORDER-#{$order['order_id']}-TOTAL-{$order['total_amount']}") ?>" 
                    alt="Order QR Code" 
                    class="w-12 h-12 rounded-xl bg-white p-1 border border-neutral-200"
                >
                <div class="text-[10px] space-y-0.5">
                    <p class="font-black uppercase tracking-wider text-neutral-900">Authentic Order</p>
                    <p class="text-neutral-400">Scan to verify invoice & warranty</p>
                </div>
            </div>

            <!-- Financial Calculation Block -->
            <div class="w-full sm:w-64 space-y-2 text-xs">
                <div class="flex justify-between text-neutral-500 font-medium">
                    <span>Subtotal</span>
                    <span class="font-bold text-neutral-800">₹<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="flex justify-between text-neutral-500 font-medium">
                    <span>Express Delivery</span>
                    <span class="font-bold text-emerald-600 uppercase text-[10px]">Free</span>
                </div>
                <div class="flex justify-between text-neutral-500 font-medium">
                    <span>GST (Included)</span>
                    <span class="font-bold text-neutral-800">₹0.00</span>
                </div>
                <div class="flex justify-between border-t border-neutral-200 pt-3 text-base font-black text-neutral-900">
                    <span>Total Paid</span>
                    <span>₹<?= number_format($order['total_amount'] ?? $subtotal, 2) ?></span>
                </div>
            </div>

        </div>

        <!-- 5. Footer Notes -->
        <div class="mt-10 pt-6 border-t border-neutral-100 flex flex-col sm:flex-row items-center justify-between text-[10px] text-neutral-400 font-semibold gap-3 text-center sm:text-left relative z-10">
            <p>© <?= date('Y') ?> Cloth Store System. All rights reserved.</p>
            <p class="uppercase tracking-widest">30-Day Easy Exchange Policy Guaranteed</p>
        </div>

    </div>

    <!-- PDF Generation Script (Preserves Geometry & Canvas Alpha) -->
    <script>
        document.getElementById('download-pdf').addEventListener('click', () => {
            const element = document.getElementById('invoice-card');
            const opt = {
                margin:       [8, 8, 8, 8],
                filename:     '<?= $invoiceNumber ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2.5, 
                    useCORS: true, 
                    letterRendering: true,
                    allowTaint: true
                },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        });
    </script>
</body>
</html>