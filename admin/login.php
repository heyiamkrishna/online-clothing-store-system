<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            // Set all standard session keys used across dashboards
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['user_id'] = $admin['admin_id'];
            $_SESSION['role'] = 'admin';

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Access denied. Invalid credentials.';
        }
    } else {
        $error = 'Please fill in both fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloth Store — Admin Portal</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
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
<body class="bg-white text-gray-900 font-sans min-h-screen flex flex-col justify-between antialiased selection:bg-black selection:text-white p-4">

    <!-- Top Brand Link -->
    <header class="max-w-md w-full mx-auto pt-6 flex justify-between items-center">
        <a href="../index.php" class="flex items-center gap-2 text-sm font-black tracking-widest uppercase text-gray-900 hover:opacity-80 transition">
            <span class="w-2.5 h-2.5 rounded-full bg-black"></span>
            <span>CLOTH<span class="text-gray-400 font-normal">STORE</span></span>
        </a>
        <span class="text-[10px] font-extrabold tracking-widest uppercase text-gray-500 bg-gray-100 border border-gray-200 px-3 py-1 rounded-full">
            Admin Console
        </span>
    </header>

    <!-- Main Login Card Area -->
    <main class="max-w-md w-full mx-auto my-8">
        
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Management Access</h1>
            <p class="text-xs text-gray-500 mt-1.5">Sign in with authorized administrator credentials.</p>
        </div>

        <!-- Clean Card Frame -->
        <div class="bg-[#F8F9FA] border border-gray-200/80 shadow-[0_4px_30px_rgba(0,0,0,0.03)] rounded-[2.5rem] p-8 sm:p-10">
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl mb-6 text-xs font-semibold flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Username / Admin ID</label>
                    <input 
                        type="text" 
                        name="username" 
                        required 
                        autocomplete="off"
                        placeholder="e.g. admin"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        class="w-full p-3.5 bg-white border border-gray-200 rounded-2xl text-xs text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                    >
                </div>

                <div>
                    <label class="block font-bold uppercase tracking-wider text-gray-700 mb-1.5">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        required 
                        placeholder="••••••••••••"
                        class="w-full p-3.5 bg-white border border-gray-200 rounded-2xl text-xs text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-black focus:border-transparent focus:outline-none transition"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-black text-white font-bold text-xs uppercase tracking-widest py-4 rounded-full hover:bg-neutral-800 transition shadow-md hover:shadow-lg mt-3 flex items-center justify-center gap-2"
                >
                    <span>Authenticate & Access</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                <a href="../index.php" class="text-xs text-gray-500 hover:text-black font-semibold transition inline-flex items-center gap-1.5">
                    <span>&larr;</span>
                    <span>Return to Storefront</span>
                </a>
            </div>

        </div>

    </main>

    <!-- Simple Bottom Footer -->
    <footer class="max-w-md w-full mx-auto pb-6 text-center text-[11px] text-gray-400">
        &copy; <?php echo date('Y'); ?> Cloth Store System • Authorized Personnel Only
    </footer>

</body>
</html>