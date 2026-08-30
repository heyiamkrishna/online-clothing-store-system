<?php
require_once 'config/db.php';
require_once 'includes/header.php';

$error = '';
$message = '';

if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = strtolower(trim($_POST['email'] ?? ''));
    $phone       = trim($_POST['phone'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // Normalize phone number format
    $cleanPhone = preg_replace('/[\s\-\(\)]+/', '', $phone);

    // 1. Basic validation
    if (empty($email) || empty($cleanPhone) || empty($newPassword) || empty($confirmPass)) {
        $error = 'Please fill out all fields.';
    } elseif (!str_ends_with($email, '@gmail.com')) {
        $error = 'Please enter a valid Gmail address ending with @gmail.com.';
    } elseif ($newPassword !== $confirmPass) {
        $error = 'New password and confirmation password do not match.';
    } 
    // 2. Strong Password Validation
    elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $error = 'Password must contain at least one uppercase letter (A-Z).';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $error = 'Password must contain at least one lowercase letter (a-z).';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $error = 'Password must contain at least one numeric number (0-9).';
    } elseif (!preg_match('/[@$!%*?&#^+=~._\-]/', $newPassword)) {
        $error = 'Password must contain at least one special symbol (e.g. @, #, $, %, !, &, *).';
    } else {
        // Verify customer identity with Email + Phone match
        $stmt = $pdo->prepare("SELECT customer_id FROM customer WHERE email = ? AND (phone = ? OR phone = ?)");
        // Check for 10-digit number and standard +91 variant
        $raw10Digit = substr($cleanPhone, -10);
        $stmt->execute([$email, $cleanPhone, $raw10Digit]);
        $customer = $stmt->fetch();

        if ($customer) {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $update = $pdo->prepare("UPDATE customer SET password = ? WHERE customer_id = ?");
            
            if ($update->execute([$hashedPassword, $customer['customer_id']])) {
                header('Location: login.php?reset=success');
                exit;
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        } else {
            $error = 'No account found matching this Gmail and registered phone number combination.';
        }
    }
}
?>

<div class="max-w-md mx-auto py-8">
    
    <!-- Top Step Tag -->
    <div class="text-center mb-6">
        <span class="text-[11px] font-bold tracking-widest uppercase text-gray-400">Account Recovery</span>
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight mt-1">Reset Password</h1>
        <p class="text-xs text-gray-500 mt-1.5">Verify your registered details to set a new password.</p>
    </div>

    <!-- Recovery Card Container -->
    <div class="bg-white rounded-[2.5rem] p-8 sm:p-10 border border-gray-200/80 shadow-[0_4px_25px_rgba(0,0,0,0.03)]">
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl mb-6 text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST" class="space-y-4 text-xs">
            <div>
                <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Registered Gmail</label>
                <input 
                    type="email" 
                    name="email" 
                    required 
                    placeholder="username@gmail.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                >
            </div>

            <div>
                <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Registered Phone Number</label>
                <input 
                    type="tel" 
                    name="phone" 
                    required 
                    placeholder="+91 98765 43210"
                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                    class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                >
            </div>

            <div>
                <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">New Password</label>
                <input 
                    type="password" 
                    name="new_password" 
                    required 
                    minlength="8"
                    placeholder="Min. 8 chars with uppercase, number & symbol"
                    class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                >
            </div>

            <div>
                <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Confirm New Password</label>
                <input 
                    type="password" 
                    name="confirm_password" 
                    required 
                    minlength="8"
                    placeholder="Re-enter your new password"
                    class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                >
            </div>

            <button 
                type="submit" 
                class="w-full bg-black text-white font-bold text-xs uppercase tracking-widest py-4 rounded-full hover:bg-neutral-800 transition shadow-md hover:shadow-lg mt-3"
            >
                Reset Password &rarr;
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-500">
                Remembered your password? 
                <a href="login.php" class="text-black font-bold hover:underline ml-1">Back to Sign In</a>
            </p>
        </div>

    </div>

</div>

<?php require_once 'includes/footer.php'; ?>