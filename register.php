<?php
require_once 'config/session.php';
require_once 'config/db.php';

$errors = [];
$success = '';

/* =========================
   DEFAULT VALUES
========================= */
$name = '';
$email = '';
$city = '';
$blood_group = '';
$lat = null;
$lon = null;

/* =========================
   CSRF TOKEN
========================= */
$csrf_token = generateCSRFToken();

/* =========================
   SANITIZE FUNCTION
========================= */
function sanitize($input){
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/* =========================
   FORM SUBMIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request. Refresh page and try again.";
    }

    $name        = sanitize($_POST['name'] ?? '');
    $email       = sanitize($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $blood_group = sanitize($_POST['blood_group'] ?? '');
    $city        = sanitize($_POST['city'] ?? '');
    $lat         = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
    $lon         = isset($_POST['lon']) && $_POST['lon'] !== '' ? (float)$_POST['lon'] : null;

    /* VALIDATION */
    if (!$name || !$email || !$password || !$blood_group || !$city) {
        $errors[] = "All fields are required.";
    }

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if ($password && strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    $allowed_groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    if ($blood_group && !in_array($blood_group, $allowed_groups, true)) {
        $errors[] = "Invalid blood group selected.";
    }

    if (empty($errors)) {

        /* CHECK EXISTING EMAIL */
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result = $stmt_check->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "Email already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users
                (name, email, password, blood_group, city, latitude, longitude, role, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'user', NOW())
            ");

            $stmt->bind_param(
                "sssssdd",
                $name,
                $email,
                $hashed_password,
                $blood_group,
                $city,
                $lat,
                $lon
            );

            if ($stmt->execute()) {
                $success = "Registration successful. You can login now.";

                $name = '';
                $email = '';
                $city = '';
                $blood_group = '';
                $lat = null;
                $lon = null;
            } else {
                $errors[] = "Something went wrong. Try again.";
            }

            $stmt->close();
        }

        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BloodConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            min-height:100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #f6e7ee 0%, #f4e4eb 100%);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:40px 16px;
        }

        .register-wrapper{
            width:100%;
            max-width:640px;
        }

        .register-card{
            background:#ffffff;
            border:none;
            border-radius:12px;
            box-shadow:0 8px 24px rgba(80, 40, 60, 0.12);
            padding:28px 32px 24px;
        }

        .register-title{
            text-align:center;
            font-size:24px;
            font-weight:600;
            color:#2e2e2e;
            margin-bottom:28px;
        }

        .form-label{
            font-size:15px;
            font-weight:500;
            color:#444;
            margin-bottom:8px;
        }

        .form-control,
        .form-select{
            height:48px;
            border:1px solid #e2dfe3;
            border-radius:8px;
            font-size:16px;
            color:#333;
            background:#fff;
            box-shadow:none;
        }

        .form-control:focus,
        .form-select:focus{
            border-color:#ff4d6d;
            box-shadow:0 0 0 0.15rem rgba(255, 77, 109, 0.12);
        }

        .register-btn{
            width:100%;
            height:46px;
            border:none;
            border-radius:6px;
            background:linear-gradient(90deg, #ff2a4f 0%, #ff2d55 100%);
            color:#fff;
            font-size:16px;
            font-weight:600;
            margin-top:8px;
            transition:0.2s ease;
        }

        .register-btn:hover{
            opacity:0.95;
        }

        .bottom-text{
            text-align:center;
            margin-top:18px;
            color:#444;
            font-size:15px;
        }

        .bottom-text a{
            color:#2a6db0;
            text-decoration:underline;
            font-weight:500;
        }

        .alert{
            border-radius:8px;
            font-size:14px;
            margin-bottom:18px;
        }

        .row-gap-custom{
            row-gap: 2px;
        }

        @media (max-width: 576px){
            .register-card{
                padding:22px 18px 20px;
            }

            .register-title{
                font-size:22px;
                margin-bottom:22px;
            }
        }
    </style>
</head>
<body>

<div class="register-wrapper">
    <div class="register-card">

        <h2 class="register-title">Donor Registration</h2>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
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
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars((string)$lat) ?>">
            <input type="hidden" name="lon" id="lon" value="<?= htmlspecialchars((string)$lon) ?>">

            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="<?= htmlspecialchars($name) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    value="<?= htmlspecialchars($email) ?>"
                    required
                >
            </div>

            <div class="row row-gap-custom">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        required
                    >
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-select" required>
                        <option value="">Select</option>
                        <option value="A+" <?= ($blood_group === "A+") ? "selected" : "" ?>>A+</option>
                        <option value="A-" <?= ($blood_group === "A-") ? "selected" : "" ?>>A-</option>
                        <option value="B+" <?= ($blood_group === "B+") ? "selected" : "" ?>>B+</option>
                        <option value="B-" <?= ($blood_group === "B-") ? "selected" : "" ?>>B-</option>
                        <option value="O+" <?= ($blood_group === "O+") ? "selected" : "" ?>>O+</option>
                        <option value="O-" <?= ($blood_group === "O-") ? "selected" : "" ?>>O-</option>
                        <option value="AB+" <?= ($blood_group === "AB+") ? "selected" : "" ?>>AB+</option>
                        <option value="AB-" <?= ($blood_group === "AB-") ? "selected" : "" ?>>AB-</option>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">City</label>
                <input
                    type="text"
                    name="city"
                    class="form-control"
                    value="<?= htmlspecialchars($city) ?>"
                    required
                >
            </div>

            <button type="submit" class="register-btn">Register</button>
        </form>

        <div class="bottom-text">
            Already have account?
            <a href="login.php">Login</a>
        </div>

    </div>
</div>

<script>
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById("lat").value = position.coords.latitude;
                document.getElementById("lon").value = position.coords.longitude;
            },
            function(error) {
                console.log("Location permission denied or unavailable.");
            }
        );
    }
</script>

</body>
</html>

<?php $conn->close(); ?>