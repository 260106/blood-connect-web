<?php
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

/* =========================
   CSRF TOKEN
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   SANITIZE FUNCTION
========================= */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/* =========================
   FORM SUBMIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = "Invalid request. Try again.";
    }

    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = "Email and password required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($errors)) {

        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            if ($user['role'] === 'admin') {
                header("Location: users.php");
            } else {
                header("Location: profile.php");
            }
            exit();

        } else {
            $errors[] = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BloodConnect</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            min-height: 100%;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f7eaf2;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 30px 15px;
            color: #222;
        }

        .login-wrapper {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 610px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.10);
            padding: 34px 34px 30px;
        }

        .login-title {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: #222;
            margin-bottom: 28px;
        }

        .alert {
            width: 100%;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-danger {
            background: #ffe6e6;
            border: 1px solid #ffbdbd;
            color: #b42318;
        }

        .alert-warning {
            background: #fff4db;
            border: 1px solid #f6d68a;
            color: #8a6116;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-label {
            display: block;
            font-size: 17px;
            font-weight: 500;
            color: #333;
            margin-bottom: 10px;
        }

        .form-control {
            width: 100%;
            height: 42px;
            border: 1px solid #d8d8d8;
            border-radius: 6px;
            padding: 0 14px;
            font-size: 15px;
            outline: none;
            background: #fff;
            transition: 0.2s ease;
        }

        .form-control:focus {
            border-color: #ff5b7f;
            box-shadow: 0 0 0 3px rgba(255, 91, 127, 0.12);
        }

        .login-btn {
            width: 100%;
            height: 46px;
            border: none;
            border-radius: 6px;
            background: linear-gradient(90deg, #ff2d55, #ff335f);
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 2px;
            box-shadow: 0 3px 8px rgba(255, 45, 85, 0.22);
            transition: 0.2s ease;
        }

        .login-btn:hover {
            opacity: 0.96;
            transform: translateY(-1px);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .register-text {
            text-align: center;
            margin-top: 24px;
            font-size: 16px;
            color: #333;
        }

        .register-text a {
            color: #4c7da8;
            text-decoration: underline;
        }

        .register-text a:hover {
            color: #345d82;
        }

        @media (max-width: 768px) {
            .login-card {
                max-width: 100%;
                padding: 26px 22px 24px;
            }

            .login-title {
                font-size: 22px;
                margin-bottom: 24px;
            }

            .form-label {
                font-size: 16px;
            }

            .register-text {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            <h2 class="login-title">Login</h2>

            <?php if (!empty($_GET['msg'])): ?>
                <div class="alert alert-warning">
                    <?php
                    if ($_GET['msg'] === 'session_required') {
                        echo "Please login to continue.";
                    } elseif ($_GET['msg'] === 'session_expired') {
                        echo "Session expired. Please login again.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        autocomplete="off"
                        required
                    >
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <div class="register-text">
                Don't have an account? <a href="register.php">Register</a>
            </div>
        </div>
    </div>

</body>
</html>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>