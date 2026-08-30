<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in
if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$name = '';
$email = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Form Validations
    if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check for existing account
        $checkStmt = $pdo->prepare("SELECT customer_id FROM customer WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            $error = "An account with this email already exists.";
        } else {
            // Secure Password Hashing
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Direct Insert into Customer Table
            $insertStmt = $pdo->prepare("
                INSERT INTO customer (name, email, password, phone, address) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$name, $email, $hashedPassword, $phone, $address]);
            $newCustomerId = (int)$pdo->lastInsertId();

            // Direct Login Session
            $_SESSION['customer_id']   = $newCustomerId;
            $_SESSION['customer_name'] = $name;

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — CLOTHSTORE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8F9FA] text-neutral-900 min-h-screen flex items-center justify-center p-4 sm:p-8 antialiased selection:bg-black selection:text-white">

    <div class="max-w-xl w-full bg-white rounded-[2.5rem] p-8 sm:p-12 border border-neutral-200/80 shadow-[0_20px_50px_rgba(0,0,0,0.05)] space-y-8">
        
        <!-- Header -->
        <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-neutral-100 text-[10px] font-black uppercase tracking-widest text-neutral-600 mb-1">
                <span>Account Access</span>
            </div>
            <h1 class="text-3xl font-black text-neutral-900 tracking-tight">Create Account</h1>
            <p class="text-xs text-neutral-400 font-medium">Register your details for streamlined checkout and bag access.</p>
        </div>

        <?php if ($error): ?>
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="" method="POST" class="space-y-5">
            
            <div class="space-y-1">
                <label class="block text-[10px] font-black uppercase tracking-widest text-neutral-500">Full Name</label>
                <input 
                    type="text" 
                    name="name" 
                    value="<?= htmlspecialchars($name) ?>" 
                    required 
                    placeholder="e.g. John Doe" 
                    class="w-full p-4 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-semibold text-neutral-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black transition"
                >
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-neutral-500">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        value="<?= htmlspecialchars($email) ?>" 
                        required 
                        placeholder="name@domain.com" 
                        class="w-full p-4 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-semibold text-neutral-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black transition"
                    >
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-neutral-500">Phone Number</label>
                    <input 
                        type="tel" 
                        name="phone" 
                        value="<?= htmlspecialchars($phone) ?>" 
                        required 
                        placeholder="+91 98765 43210" 
                        class="w-full p-4 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-semibold text-neutral-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black transition"
                    >
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-[10px] font-black uppercase tracking-widest text-neutral-500">Delivery Address</label>
                <textarea 
                    name="address" 
                    rows="2" 
                    required 
                    placeholder="House/Flat No., Street, City, State, PIN" 
                    class="w-full p-4 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-semibold text-neutral-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black transition"
                ><?= htmlspecialchars($address) ?></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-neutral-500">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        required 
                        placeholder="••••••••" 
                        class="w-full p-4 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-semibold text-neutral-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black transition"
                    >
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-neutral-500">Confirm Password</label>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        required 
                        placeholder="••••••••" 
                        class="w-full p-4 bg-neutral-50 border border-neutral-200 rounded-2xl text-xs font-semibold text-neutral-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black transition"
                    >
                </div>
            </div>

            <button 
                type="submit" 
                class="w-full py-4 rounded-full bg-black text-white text-xs font-black uppercase tracking-widest hover:bg-neutral-800 transition active:scale-98 shadow-md mt-2"
            >
                Create Account &rarr;
            </button>
        </form>

        <div class="pt-6 border-t border-neutral-100 text-center">
            <p class="text-xs text-neutral-400 font-medium">
                Already registered? 
                <a href="login.php" class="font-black text-neutral-900 hover:underline ml-1">Sign In to Account</a>
            </p>
        </div>

    </div>

</body>
</html>