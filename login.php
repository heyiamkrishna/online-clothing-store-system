<?php
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. If already logged in, redirect immediately before outputting HTML
if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// 2. Handle Login Submission BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT customer_id, name, password FROM customer WHERE email = ?");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        if ($customer && password_verify($password, $customer['password'])) {
            $_SESSION['customer_id']   = $customer['customer_id'];
            $_SESSION['customer_name'] = $customer['name'];
            
            // Successful login redirect
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid email address or password combination.';
        }
    }
}

// 3. Include header ONLY after all redirect logic has completed
require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto py-8">
    
    <!-- Top Step Tag -->
    <div class="text-center mb-6">
        <span class="text-[11px] font-bold tracking-widest uppercase text-gray-400">Account Access</span>
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight mt-1">Welcome Back</h1>
        <p class="text-xs text-gray-500 mt-1.5">Sign in to access your bag, past orders, and profile.</p>
    </div>

    <!-- Login Card Container -->
    <div class="bg-white rounded-[2.5rem] p-8 sm:p-10 border border-gray-200/80 shadow-[0_4px_25px_rgba(0,0,0,0.03)]">
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl mb-6 text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl mb-6 text-xs font-semibold">
                Your password has been successfully reset! Please sign in with your new password.
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-5 text-xs">
            <div>
                <label class="block font-bold uppercase tracking-wider text-gray-700 mb-2">Gmail Address</label>
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
                <div class="flex justify-between items-center mb-2">
                    <label class="font-bold uppercase tracking-wider text-gray-700">Password</label>
                    <a href="forgot_password.php" class="text-[11px] font-semibold text-gray-400 hover:text-black transition">
                        Forgot Password?
                    </a>
                </div>
                <input 
                    type="password" 
                    name="password" 
                    required 
                    placeholder="••••••••"
                    class="w-full p-3.5 bg-[#F8F9FA] border border-gray-200 rounded-2xl text-xs text-gray-900 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                >
            </div>

            <button 
                type="submit" 
                class="w-full bg-black text-white font-bold text-xs uppercase tracking-widest py-4 rounded-full hover:bg-neutral-800 transition shadow-md hover:shadow-lg mt-2 flex items-center justify-center gap-2"
            >
                <span>Sign In</span>
                <span class="text-sm">&rarr;</span>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-500">
                Don't have an account yet? 
                <a href="register.php" class="text-black font-bold hover:underline ml-1">Create an account</a>
            </p>
        </div>

    </div>

</div>

<?php require_once 'includes/footer.php'; ?>