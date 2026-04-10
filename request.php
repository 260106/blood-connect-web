<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/session.php';
requireLogin();

require_once 'config/db.php';

$activePage = 'request';

$user_id = $_SESSION['user_id'];
$errors = [];

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Flash */
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

/* Default form values */
$blood_group   = '';
$city          = '';
$contact       = '';
$hospital      = '';
$urgency       = 'Normal';
$required_date = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = "Invalid request token.";
    }

    $blood_group   = trim($_POST['blood_group'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $contact       = trim($_POST['contact_number'] ?? '');
    $hospital      = trim($_POST['hospital_name'] ?? '');
    $urgency       = trim($_POST['urgency'] ?? 'Normal');
    $required_date = $_POST['required_date'] ?? '';

    $allowed_groups  = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    $allowed_urgency = ['Normal','Urgent','Critical'];

    if (!in_array($blood_group, $allowed_groups, true)) {
        $errors[] = 'Select valid blood group.';
    }

    if ($city === '') {
        $errors[] = 'City required.';
    }

    if (!preg_match('/^[0-9+\-\s]{7,15}$/', $contact)) {
        $errors[] = 'Invalid contact number.';
    }

    if ($hospital === '') {
        $errors[] = 'Hospital required.';
    }

    if (!in_array($urgency, $allowed_urgency, true)) {
        $errors[] = 'Invalid urgency.';
    }

    if ($required_date === '' || $required_date < date('Y-m-d')) {
        $errors[] = 'Invalid required date.';
    }

    if (empty($errors)) {
        $check = $conn->prepare("
            SELECT COUNT(*) as c
            FROM requests
            WHERE user_id = ? AND status = 'Pending'
        ");
        $check->bind_param('i', $user_id);
        $check->execute();
        $result = $check->get_result();
        $active_count = $result->fetch_assoc()['c'] ?? 0;
        $check->close();

        if ($active_count >= 3) {
            $errors[] = 'You already have 3 active requests.';
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO requests
            (user_id, blood_group, city, contact_number, hospital_name, urgency, required_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ");

        $stmt->bind_param(
            'issssss',
            $user_id,
            $blood_group,
            $city,
            $contact,
            $hospital,
            $urgency,
            $required_date
        );

        if ($stmt->execute()) {
            $stmt->close();

            $_SESSION['success_message'] = "Request submitted successfully.";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header("Location: request.php");
            exit();
        } else {
            $stmt->close();
            $errors[] = "Database error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Request Blood - BloodConnect</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root{
            --primary-red: #ef3340;
            --primary-red-dark: #d91f34;
            --soft-pink: #f8e9ef;
            --soft-pink-2: #f6dfe8;
            --text-dark: #2b2b2b;
            --muted-text: #5f5f68;
            --input-border: #e6dbe0;
            --card-white: rgba(255,255,255,0.72);
            --shadow-soft: 0 10px 35px rgba(214, 67, 103, 0.12);
            --shadow-light: 0 8px 20px rgba(0, 0, 0, 0.05);
            --radius-xl: 26px;
            --radius-lg: 20px;
            --radius-md: 14px;
        }

        body{
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(255,255,255,0.55), transparent 25%),
                linear-gradient(180deg, #f9eef3 0%, #f6e6ee 40%, #f3e0e8 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .page-wrap{
            padding-top: 120px;
            padding-bottom: 60px;
        }

        .request-shell{
            max-width: 920px;
            margin: 0 auto;
            background: var(--card-white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            padding: 34px;
            border: 1px solid rgba(255,255,255,0.45);
            backdrop-filter: blur(8px);
        }

        .request-header{
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 26px;
        }

        .blood-icon{
            font-size: 52px;
            line-height: 1;
            filter: drop-shadow(0 6px 10px rgba(239, 51, 64, 0.18));
        }

        .request-title{
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-red);
            letter-spacing: 0.2px;
        }

        .request-subtitle{
            margin: 10px 0 0;
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--muted-text);
            max-width: 680px;
        }

        .form-box{
            background: rgba(255,255,255,0.82);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-light);
            border: 1px solid #f0e5ea;
        }

        .form-label{
            font-size: 1rem;
            font-weight: 700;
            color: #34343b;
            margin-bottom: 10px;
        }

        .form-control,
        .form-select{
            height: 54px;
            border-radius: 12px;
            border: 1px solid var(--input-border);
            background: #fff;
            color: #30303a;
            font-size: 1.05rem;
            padding-left: 16px;
            box-shadow: none !important;
        }

        .form-control:focus,
        .form-select:focus{
            border-color: #f26778;
            box-shadow: 0 0 0 0.2rem rgba(239, 51, 64, 0.10) !important;
        }

        .submit-btn{
            height: 52px;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(90deg, #ff3650 0%, #ff2945 100%);
            box-shadow: 0 10px 18px rgba(255, 51, 79, 0.18);
            transition: 0.25s ease;
        }

        .submit-btn:hover{
            transform: translateY(-1px);
            background: linear-gradient(90deg, #f82e48 0%, #f11f3d 100%);
        }

        .custom-alert{
            border-radius: 14px;
            padding: 14px 16px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .alert-danger.custom-alert{
            background: #fff3f4;
            border: 1px solid #ffcfd5;
            color: #b42333;
        }

        .alert-success.custom-alert{
            background: #eefaf1;
            border: 1px solid #c8ead0;
            color: #1f7a39;
        }

        .two-col{
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .navbar-space-fix{
            height: 12px;
        }

        @media (max-width: 768px){
            .page-wrap{
                padding-top: 100px;
                padding-left: 12px;
                padding-right: 12px;
            }

            .request-shell{
                padding: 20px;
                border-radius: 20px;
            }

            .form-box{
                padding: 20px;
            }

            .request-title{
                font-size: 1.6rem;
            }

            .request-subtitle{
                font-size: 1rem;
                line-height: 1.6;
            }

            .two-col{
                grid-template-columns: 1fr;
                gap: 0;
            }

            .blood-icon{
                font-size: 42px;
            }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="page-wrap">
    <div class="container">
        <div class="request-shell">

            <div class="request-header">
                <div class="blood-icon">🩸</div>
                <div>
                    <h1 class="request-title">Request Blood</h1>
                    <p class="request-subtitle">
                        Fill out the form below to request blood. Submit the details and we will
                        connect you with suitable blood donors quickly.
                    </p>
                </div>
            </div>

            <div class="form-box">

                <?php if ($success_message): ?>
                    <div class="alert alert-success custom-alert">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger custom-alert">
                        <?php foreach ($errors as $e): ?>
                            <div><?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-4">
                        <label class="form-label">Blood Group</label>
                        <select name="blood_group" class="form-select" required>
                            <option value="">Select Blood Group</option>
                            <?php
                            $groups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
                            foreach ($groups as $group):
                            ?>
                                <option value="<?= $group ?>" <?= ($blood_group === $group) ? 'selected' : '' ?>>
                                    <?= $group ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">City</label>
                        <input
                            type="text"
                            name="city"
                            class="form-control"
                            placeholder="Enter city"
                            value="<?= htmlspecialchars($city) ?>"
                            required
                        >
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Contact Number</label>
                        <input
                            type="text"
                            name="contact_number"
                            class="form-control"
                            placeholder="Enter contact number"
                            value="<?= htmlspecialchars($contact) ?>"
                            required
                        >
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Hospital Name</label>
                        <input
                            type="text"
                            name="hospital_name"
                            class="form-control"
                            placeholder="Enter hospital name"
                            value="<?= htmlspecialchars($hospital) ?>"
                            required
                        >
                    </div>

                    <div class="two-col mb-4">
                        <div class="mb-3 mb-md-0">
                            <label class="form-label">Urgency</label>
                            <select name="urgency" class="form-select">
                                <option value="Normal" <?= ($urgency === 'Normal') ? 'selected' : '' ?>>Normal</option>
                                <option value="Urgent" <?= ($urgency === 'Urgent') ? 'selected' : '' ?>>Urgent</option>
                                <option value="Critical" <?= ($urgency === 'Critical') ? 'selected' : '' ?>>Critical</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Required Date</label>
                            <input
                                type="date"
                                name="required_date"
                                class="form-control"
                                min="<?= date('Y-m-d') ?>"
                                value="<?= htmlspecialchars($required_date) ?>"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn w-100 submit-btn">
                        Submit Request
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>