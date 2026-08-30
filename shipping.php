<?php
require_once 'config/db.php';
require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto py-8">

    <!-- Header -->
    <div class="text-center max-w-xl mx-auto mb-12">
        <span class="text-[11px] font-bold tracking-widest uppercase text-gray-400">Logistics & Delivery</span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight mt-1">Shipping Policy</h1>
        <p class="text-xs text-gray-500 mt-2">Fast, secure, and reliable pan-India courier delivery networks.</p>
    </div>

    <!-- Highlights Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-12">
        <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm text-center space-y-1">
            <span class="text-2xl font-black text-gray-900">FREE</span>
            <h4 class="font-bold text-xs uppercase tracking-wider text-gray-700">All India Shipping</h4>
            <p class="text-[11px] text-gray-400">Complimentary on all orders</p>
        </div>
        <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm text-center space-y-1">
            <span class="text-2xl font-black text-gray-900">24-48 Hrs</span>
            <h4 class="font-bold text-xs uppercase tracking-wider text-gray-700">Dispatch Time</h4>
            <p class="text-[11px] text-gray-400">Same or next day packing</p>
        </div>
        <div class="bg-white rounded-3xl p-6 border border-gray-200/80 shadow-sm text-center space-y-1">
            <span class="text-2xl font-black text-gray-900">3-5 Days</span>
            <h4 class="font-bold text-xs uppercase tracking-wider text-gray-700">Transit Duration</h4>
            <p class="text-[11px] text-gray-400">Metro cities within 2-3 days</p>
        </div>
    </div>

    <!-- Policy Content Container -->
    <div class="bg-white rounded-[2.5rem] p-8 sm:p-10 border border-gray-200/80 shadow-[0_4px_25px_rgba(0,0,0,0.02)] space-y-8 text-xs text-gray-600 leading-relaxed">
        
        <div class="space-y-2">
            <h2 class="text-base font-bold text-gray-900 uppercase tracking-wider">1. Order Processing & Dispatch</h2>
            <p>
                All orders are processed and verified within <strong>24 to 48 business hours</strong> (excluding Sundays and national holidays). Once dispatched, an automated confirmation is updated on your dashboard.
            </p>
        </div>

        <div class="space-y-2 border-t border-gray-100 pt-6">
            <h2 class="text-base font-bold text-gray-900 uppercase tracking-wider">2. Estimated Delivery Timelines</h2>
            <div class="space-y-2 text-gray-500">
                <div class="flex justify-between border-b border-gray-100 pb-2">
                    <span class="font-semibold text-gray-800">Tier 1 & Metro Cities (Delhi NCR, Mumbai, Bangalore, Kolkata)</span>
                    <span class="font-bold text-gray-900">2 — 3 Business Days</span>
                </div>
                <div class="flex justify-between border-b border-gray-100 pb-2">
                    <span class="font-semibold text-gray-800">Tier 2 & Tier 3 Regional Cities</span>
                    <span class="font-bold text-gray-900">4 — 5 Business Days</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-semibold text-gray-800">North-East & Remote Locations</span>
                    <span class="font-bold text-gray-900">5 — 7 Business Days</span>
                </div>
            </div>
        </div>

        <div class="space-y-2 border-t border-gray-100 pt-6">
            <h2 class="text-base font-bold text-gray-900 uppercase tracking-wider">3. Cash on Delivery (COD) Rules</h2>
            <p>
                We provide full Cash on Delivery support across all serviceable Indian pincodes. Please ensure someone is available at the provided delivery address to receive the parcel and settle the invoice amount.
            </p>
        </div>

        <div class="space-y-2 border-t border-gray-100 pt-6">
            <h2 class="text-base font-bold text-gray-900 uppercase tracking-wider">4. Damaged or Tampered Parcels</h2>
            <p>
                If the outer packaging appears visibly torn, tampered with, or open upon delivery, please <strong>refuse to accept the package</strong> and immediately notify our concierge at <a href="mailto:support@clothstore.com" class="text-black font-bold underline">support@clothstore.com</a>.
            </p>
        </div>

    </div>

</div>

<?php require_once 'includes/footer.php'; ?>