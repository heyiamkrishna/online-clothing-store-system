<?php
require_once 'config/db.php';
require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto py-8">

    <!-- Header -->
    <div class="text-center max-w-xl mx-auto mb-12">
        <span class="text-[11px] font-bold tracking-widest uppercase text-gray-400">Customer Care</span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight mt-1">Returns & Exchanges</h1>
        <p class="text-xs text-gray-500 mt-2">Hassle-free 7-day doorstep returns and size exchanges across India.</p>
    </div>

    <!-- 3-Step Return Process Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm space-y-2">
            <span class="w-8 h-8 rounded-full bg-black text-white text-xs font-bold flex items-center justify-center">01</span>
            <h3 class="font-bold text-sm text-gray-900 pt-1">Request Return</h3>
            <p class="text-xs text-gray-500 leading-relaxed">Go to your Order History or email us with your Order ID within 7 days of delivery.</p>
        </div>
        <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm space-y-2">
            <span class="w-8 h-8 rounded-full bg-black text-white text-xs font-bold flex items-center justify-center">02</span>
            <h3 class="font-bold text-sm text-gray-900 pt-1">Doorstep Pickup</h3>
            <p class="text-xs text-gray-500 leading-relaxed">Our courier partner will pick up the unused package with original tags intact.</p>
        </div>
        <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm space-y-2">
            <span class="w-8 h-8 rounded-full bg-black text-white text-xs font-bold flex items-center justify-center">03</span>
            <h3 class="font-bold text-sm text-gray-900 pt-1">Instant Refund</h3>
            <p class="text-xs text-gray-500 leading-relaxed">Once verified, your refund or replacement size is dispatched within 24-48 hours.</p>
        </div>
    </div>

    <!-- Policy Details Card -->
    <div class="bg-white rounded-[2.5rem] p-8 sm:p-10 border border-gray-200/80 shadow-[0_4px_25px_rgba(0,0,0,0.02)] space-y-8 text-xs text-gray-600 leading-relaxed">
        
        <div class="space-y-2">
            <h2 class="text-base font-bold text-gray-900 uppercase tracking-wider">1. Eligibility Criteria</h2>
            <ul class="list-disc pl-5 space-y-1.5 text-gray-500">
                <li>Items must be unworn, unwashed, and undamaged with all original tags and packaging intact.</li>
                <li>Return/exchange requests must be initiated within <strong>7 days</strong> of delivery.</li>
                <li>Clearance and archive drop items marked "Final Sale" are eligible for size exchanges only.</li>
            </ul>
        </div>

        <div class="space-y-2 border-t border-gray-100 pt-6">
            <h2 class="text-base font-bold text-gray-900 uppercase tracking-wider">2. Size & Color Exchanges</h2>
            <p>
                Ordered the wrong size? We offer <strong>free reverse pickup and re-shipping</strong> for your first size exchange. Simply mention your preferred replacement size when submitting your request.
            </p>
        </div>

        <div class="space-y-2 border-t border-gray-100 pt-6">
            <h2 class="text-base font-bold text-gray-900 uppercase tracking-wider">3. Refund Methods</h2>
            <ul class="list-disc pl-5 space-y-1.5 text-gray-500">
                <li><strong>Cash on Delivery Orders:</strong> Refunds will be transferred directly to your bank account via UPI / NEFT within 2 business days after quality inspection.</li>
                <li><strong>Prepaid / Online Orders:</strong> Refund will be credited back to the original payment source within 3-5 working days.</li>
            </ul>
        </div>

        <!-- Help Banner -->
        <div class="bg-[#F8F9FA] rounded-2xl p-6 border border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>
                <h4 class="font-bold text-gray-900 text-sm">Need help initiating a return?</h4>
                <p class="text-gray-400 text-xs mt-0.5">Reach out to our concierge with your order number.</p>
            </div>
            <a href="mailto:support@clothstore.com" class="bg-black text-white font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-full hover:bg-neutral-800 transition flex-shrink-0">
                Contact Support
            </a>
        </div>

    </div>

</div>

<?php require_once 'includes/footer.php'; ?>