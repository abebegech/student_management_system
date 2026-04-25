<?php
session_start();
require_once 'includes/Auth.php';

$auth = new Auth();
$error = "";

// If already logged in, redirect to appropriate dashboard
if ($auth->isLoggedIn()) {
    $auth->redirectToDashboard();
    exit;
}

if(isset($_POST['login'])){
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        if ($auth->login($username, $password)) {
            // Successful login - redirect to role-specific dashboard
            $auth->redirectToDashboard();
            exit;
        } else {
            $error = "Invalid username/email or password.";
        }
    } else {
        $error = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BDU Student System - Professional Login</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/premium-ui.js"></script>
    <style>
        /* Professional Login Page Styles */
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #0F172A 100%);
            position: relative;
            overflow: hidden;
        }

        /* 3D Login Container */
        .auth-shell {
            position: relative;
            z-index: 10;
        }

        .auth-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%);
            border: 2px solid rgba(139, 92, 246, 0.3);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            position: relative;
            transform-style: preserve-3d;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            box-shadow: 
                0 40px 80px -20px rgba(139, 92, 246, 0.3),
                0 0 120px rgba(139, 92, 246, 0.2),
                0 0 180px rgba(236, 72, 153, 0.1);
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            background: linear-gradient(45deg, var(--primary), var(--secondary), var(--accent), var(--primary));
            border-radius: 24px;
            opacity: 0;
            z-index: -1;
            transition: opacity 0.4s ease;
            animation: border-glow 3s linear infinite;
            background-size: 400% 400%;
        }

        .auth-card:hover {
            transform: perspective(1000px) rotateX(5deg) rotateY(-5deg) translateZ(20px) scale(1.02);
            box-shadow: 
                0 60px 120px -30px rgba(139, 92, 246, 0.4),
                0 0 150px rgba(139, 92, 246, 0.3),
                0 0 200px rgba(236, 72, 153, 0.2);
            border-color: var(--primary-light);
        }

        .auth-card:hover::before {
            opacity: 0.7;
        }

        /* 3D Header */
        .auth-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .logo-circle {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            font-weight: 900;
            color: white;
            position: relative;
            transform-style: preserve-3d;
            animation: float-logo 4s ease-in-out infinite;
            box-shadow: 
                0 20px 40px rgba(139, 92, 246, 0.4),
                0 0 80px rgba(139, 92, 246, 0.3);
        }

        @keyframes float-logo {
            0%, 100% { transform: translateY(0) rotateZ(0deg); }
            25% { transform: translateY(-15px) rotateZ(5deg); }
            50% { transform: translateY(-8px) rotateZ(-5deg); }
            75% { transform: translateY(-12px) rotateZ(3deg); }
        }

        .auth-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text);
            margin: 0 0 0.5rem 0;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 
                0 1px 0 #ccc,
                0 2px 0 #c9c9c9,
                0 3px 0 #bbb,
                0 4px 0 #b9b9b9,
                0 5px 0 #aaa,
                0 6px 1px rgba(139, 92, 246, 0.3),
                0 0 20px rgba(139, 92, 246, 0.5);
            animation: title-glow 3s ease-in-out infinite alternate;
        }

        @keyframes title-glow {
            from { text-shadow: 0 6px 1px rgba(139, 92, 246, 0.3), 0 0 20px rgba(139, 92, 246, 0.5); }
            to { text-shadow: 0 6px 1px rgba(139, 92, 246, 0.6), 0 0 30px rgba(139, 92, 246, 0.8); }
        }

        .auth-subtitle {
            color: var(--muted);
            font-size: 1.1rem;
            margin: 0;
        }

        /* 3D Role Cards */
        .role-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .role-card {
            padding: 1.5rem;
            border: 2px solid rgba(139, 92, 246, 0.2);
            border-radius: 16px;
            text-align: center;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            transform-style: preserve-3d;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(139, 92, 246, 0.3) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .role-card:hover {
            transform: perspective(800px) rotateX(10deg) rotateY(-10deg) translateZ(15px) scale(1.05);
            border-color: var(--primary-light);
            box-shadow: 
                0 20px 40px rgba(139, 92, 246, 0.3),
                0 0 60px rgba(139, 92, 246, 0.2);
        }

        .role-card:hover::before {
            opacity: 1;
        }

        .role-card h4 {
            margin: 0 0 0.5rem 0;
            color: var(--primary-light);
            font-size: 1.2rem;
            font-weight: 700;
        }

        .role-card p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--muted);
        }

        /* Premium Input Fields */
        .field {
            margin-bottom: 1.5rem;
        }

        .field label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .field input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid rgba(139, 92, 246, 0.2);
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
            position: relative;
        }

        .field input:focus {
            outline: none;
            border-color: var(--primary-light);
            transform: perspective(800px) rotateX(2deg) translateZ(5px);
            box-shadow: 
                0 10px 30px rgba(139, 92, 246, 0.3),
                0 0 40px rgba(139, 92, 246, 0.2),
                inset 0 0 20px rgba(139, 92, 246, 0.1);
        }

        .field input::placeholder {
            color: var(--muted);
        }

        /* Dramatic Login Button - ENHANCED VISIBILITY */
        .btn-primary {
            width: 100%;
            padding: 1.5rem 2rem;
            border: 3px solid var(--primary-light);
            border-radius: 16px;
            font-size: 1.3rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: white;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--secondary) 100%);
            cursor: pointer;
            position: relative;
            overflow: visible;
            transform-style: preserve-3d;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 15px 40px rgba(139, 92, 246, 0.6),
                0 0 60px rgba(139, 92, 246, 0.4),
                0 0 80px rgba(236, 72, 153, 0.3);
            z-index: 10;
            margin-top: 1rem;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.4) 50%, transparent 100%);
            transform: translate(-50%, -50%);
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1), height 1s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }

        .btn-primary::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
            transition: left 1s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2;
        }

        .btn-primary:hover {
            transform: translateY(-12px) rotateX(8deg) rotateY(-8deg) translateZ(25px) scale(1.1);
            box-shadow: 
                0 40px 80px rgba(139, 92, 246, 0.7),
                0 0 120px rgba(139, 92, 246, 0.6),
                0 0 180px rgba(236, 72, 153, 0.5);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary) 50%, var(--primary) 100%);
            border-color: white;
        }

        .btn-primary:hover::before {
            width: 600px;
            height: 600px;
        }

        .btn-primary:hover::after {
            left: 100%;
        }

        .btn-primary:active {
            transform: translateY(-4px) rotateX(2deg) rotateY(-2deg) translateZ(8px) scale(1.02);
        }

        /* Enhanced Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 600;
            text-align: center;
            animation: alert-shake 0.5s ease-in-out;
        }

        @keyframes alert-shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(239, 68, 68, 0.1) 100%);
            border: 2px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.2);
        }

        /* Auth Footer */
        .auth-footer {
            margin-top: 2rem;
            text-align: center;
        }

        .muted-text {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .auth-card {
                padding: 2rem;
                margin: 1rem;
            }

            .role-info {
                grid-template-columns: 1fr;
            }

            .auth-title {
                font-size: 1.5rem;
            }
        }

        /* Background 3D Elements */
        .bg-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .bg-shape {
            position: absolute;
            width: 80px;
            height: 80px;
            opacity: 0.1;
            animation: float-shape 20s ease-in-out infinite;
        }

        @keyframes float-shape {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-30px) rotate(90deg); }
            50% { transform: translateY(-15px) rotate(180deg); }
            75% { transform: translateY(-25px) rotate(270deg); }
        }
    </style>
</head>
<body>
    <!-- 3D Background Elements -->
    <div class="bg-shapes">
        <div class="bg-shape" style="top: 10%; left: 10%; animation-delay: 0s;">
            <div class="sphere-3d" style="transform: scale(0.5);">
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
            </div>
        </div>
        <div class="bg-shape" style="top: 20%; right: 15%; animation-delay: 2s;">
            <div class="pyramid-3d" style="transform: scale(0.4);">
                <div class="pyramid-face front"></div>
                <div class="pyramid-face back"></div>
                <div class="pyramid-face left"></div>
                <div class="pyramid-face right"></div>
            </div>
        </div>
        <div class="bg-shape" style="bottom: 30%; left: 20%; animation-delay: 4s;">
            <div class="cube-3d" style="transform: scale(0.3);">
                <div class="cube-face front">🎓</div>
                <div class="cube-face back">📊</div>
                <div class="cube-face right">👥</div>
                <div class="cube-face left">📚</div>
                <div class="cube-face top">💰</div>
                <div class="cube-face bottom">🎯</div>
            </div>
        </div>
        <div class="bg-shape" style="top: 60%; right: 25%; animation-delay: 6s;">
            <div class="sphere-3d" style="transform: scale(0.4);">
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
                <div class="sphere-face"></div>
            </div>
        </div>
        <div class="bg-shape" style="bottom: 15%; right: 10%; animation-delay: 8s;">
            <div class="pyramid-3d" style="transform: scale(0.5);">
                <div class="pyramid-face front"></div>
                <div class="pyramid-face back"></div>
                <div class="pyramid-face left"></div>
                <div class="pyramid-face right"></div>
            </div>
        </div>
    </div>

    <div class="auth-shell">
        <div class="auth-card card-3d reveal-dramatic">
            <div class="auth-header">
                <div class="logo-circle">BDU</div>
                <div>
                    <h1 class="auth-title text-3d">Student Management System</h1>
                    <p class="auth-subtitle">Sign in to access your dashboard</p>
                </div>
            </div>

            <div class="role-info">
                <div class="role-card tilt-3d">
                    <h4>👨‍🎓 Student</h4>
                    <p>View grades & attendance</p>
                </div>
                <div class="role-card tilt-3d">
                    <h4>👨‍🏫 Teacher</h4>
                    <p>Manage grades & analytics</p>
                </div>
                <div class="role-card tilt-3d">
                    <h4>⚙️ Admin</h4>
                    <p>Full system access</p>
                </div>
            </div>

            <?php if (!empty($error)) { ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <form method="POST" class="form">
                <div class="field">
                    <label for="username">Username or Email</label>
                    <input id="username" name="username" autocomplete="username" placeholder="Enter username or email" required class="tilt-3d">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Enter password" required class="tilt-3d">
                </div>

                <button class="btn btn-primary btn-liquid btn-particle-burst" name="login" type="submit">Sign In</button>
            </form>

            <div class="auth-footer">
                <span class="muted-text">
                    Default Admin: username: <strong>admin</strong> | password: <strong>password</strong><br>
                    Use your assigned credentials for role-based access
                </span>
            </div>
        </div>
    </div>

    <script>
        // Initialize Premium UI for login page
        document.addEventListener('DOMContentLoaded', function() {
            // Create premium UI instance
            const premiumUI = new PremiumUI();
            
            // Initialize all premium features
            setTimeout(() => {
                premiumUI.createBackgroundParticles();
                premiumUI.createParallaxBackground();
                premiumUI.setupCustomCursor();
                premiumUI.setup3DTilt();
                console.log('Premium Login UI initialized!');
            }, 500);
        });
    </script>
</body>
</html>