<?php
require_once 'config/session.php';
requireLogin();
require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$success = "";
$errors = [];

/* =========================
   CSRF
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   FETCH USER
========================= */
$stmt = $conn->prepare("
    SELECT id, name, email, phone, city, blood_group, availability,
           tattoo_12m, hiv_positive, profile_pic
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found");
}

/* =========================
   DEFAULT PROFILE IMAGE
========================= */
$default_image = "assets/images/default-profile.png";

/* =========================
   UPDATE PROFILE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = "Invalid request";
    }

    $name         = trim($_POST['name'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $blood_group  = trim($_POST['blood_group'] ?? '');
    $availability = trim($_POST['availability'] ?? '');
    $tattoo       = $_POST['tattoo_12m'] ?? 'No';
    $hiv          = $_POST['hiv_positive'] ?? 'No';

    if ($name === '') {
        $errors[] = "Name required";
    }

    if ($city === '') {
        $errors[] = "City required";
    }

    if ($phone === '') {
        $errors[] = "Phone required";
    }

    $valid_groups = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];
    if (!in_array($blood_group, $valid_groups, true)) {
        $errors[] = "Invalid blood group";
    }

    $valid_status = ['Available', 'Not Available'];
    if (!in_array($availability, $valid_status, true)) {
        $errors[] = "Invalid availability status";
    }

    $valid_yes_no = ['Yes', 'No'];
    if (!in_array($tattoo, $valid_yes_no, true)) {
        $errors[] = "Invalid tattoo value";
    }

    if (!in_array($hiv, $valid_yes_no, true)) {
        $errors[] = "Invalid HIV value";
    }

    $profile_pic = $user['profile_pic'];

    /* =========================
       PROFILE IMAGE UPLOAD
    ========================= */
    if (!empty($_FILES['profile_pic']['name'])) {

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $max = 2 * 1024 * 1024;

        $file = $_FILES['profile_pic'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            $errors[] = "Invalid image format";
        }

        if (($file['size'] ?? 0) > $max) {
            $errors[] = "Image too large";
        }

        if (($file['error'] ?? 0) !== 0) {
            $errors[] = "Image upload failed";
        }

        if (empty($errors)) {

            if (!is_dir("uploads")) {
                mkdir("uploads", 0755, true);
            }

            $new_name = "user_" . $user_id . "_" . time() . "." . $ext;
            $target_path = "uploads/" . $new_name;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {

                if (
                    !empty($user['profile_pic']) &&
                    file_exists("uploads/" . $user['profile_pic'])
                ) {
                    @unlink("uploads/" . $user['profile_pic']);
                }

                $profile_pic = $new_name;
            } else {
                $errors[] = "Failed to save uploaded image";
            }
        }
    }

    /* =========================
       UPDATE USER
    ========================= */
    if (empty($errors)) {

        $update = $conn->prepare("
            UPDATE users
            SET name = ?, phone = ?, city = ?, blood_group = ?, availability = ?,
                tattoo_12m = ?, hiv_positive = ?, profile_pic = ?
            WHERE id = ?
        ");

        $update->bind_param(
            "ssssssssi",
            $name,
            $phone,
            $city,
            $blood_group,
            $availability,
            $tattoo,
            $hiv,
            $profile_pic,
            $user_id
        );

        if ($update->execute()) {
            $success = "Profile updated successfully";
        } else {
            $errors[] = "Update failed";
        }
        $update->close();

        /* REFRESH USER DATA */
        $stmt = $conn->prepare("
            SELECT id, name, email, phone, city, blood_group, availability,
                   tattoo_12m, hiv_positive, profile_pic
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

/* =========================
   IMAGE PATH
========================= */
if (
    !empty($user['profile_pic']) &&
    file_exists("uploads/" . $user['profile_pic'])
) {
    $img = "uploads/" . $user['profile_pic'];
} else {
    $img = $default_image;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - BloodConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            margin:0;
            font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #fdf4f7 0%, #f9eef2 100%);
            min-height:100vh;
            color:#2e2e2e;
        }

        .page-wrap{
            padding-top: 95px; /* fixed navbar irundha overlap aagadhu */
            padding-bottom: 50px;
        }

        .page-title{
            text-align:center;
            font-size: 28px;
            font-weight: 700;
            color:#232323;
            margin-bottom: 36px;
        }

        .profile-card{
            max-width: 910px;
            margin: 0 auto;
            background: rgba(255,255,255,0.88);
            border-radius: 24px;
            box-shadow: 0 14px 34px rgba(0,0,0,0.08);
            padding: 38px 38px 34px;
            border: 1px solid rgba(255,255,255,0.65);
        }

        .profile-top{
            display:flex;
            align-items:center;
            gap:22px;
            margin-bottom: 28px;
            flex-wrap:wrap;
        }

        .profile-img{
            width:118px;
            height:118px;
            border-radius:50%;
            object-fit:cover;
            border:4px solid #ffffff;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
            background:#fff;
        }

        .profile-name-wrap h3{
            margin:0 0 10px;
            font-size: 28px;
            font-weight: 700;
            color:#242424;
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }

        .blood-badge{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width: 74px;
            height: 48px;
            padding: 0 18px;
            border-radius: 18px;
            background: linear-gradient(90deg, #ff3556 0%, #f21d3c 100%);
            color:#fff;
            font-size: 22px;
            font-weight: 700;
            line-height:1;
            box-shadow: 0 6px 16px rgba(242,29,60,0.30);
        }

        .file-input{
            width: 320px;
            max-width: 100%;
        }

        .alert{
            border-radius: 14px;
            font-size: 15px;
        }

        .form-label{
            font-size: 16px;
            font-weight: 500;
            color:#3a3a3a;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select{
            height: 48px;
            border-radius: 12px;
            border: 1px solid #e8dfe4;
            background-color: rgba(255,255,255,0.92);
            font-size: 16px;
            color:#3a3a3a;
            box-shadow: none !important;
        }

        .form-control:focus,
        .form-select:focus{
            border-color:#f24a63;
            box-shadow: 0 0 0 0.15rem rgba(242,74,99,0.12) !important;
        }

        .form-control[disabled]{
            background:#f5f0f3;
            color:#666;
            opacity:1;
        }

        .field-gap{
            margin-top: 18px;
        }

        .btn-update{
            width:100%;
            margin-top: 34px;
            height: 46px;
            border:none;
            border-radius: 999px;
            background: linear-gradient(90deg, #ff3556 0%, #f21d3c 100%);
            color:#fff;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.2px;
            box-shadow: 0 8px 20px rgba(242,29,60,0.25);
            transition: 0.2s ease;
        }

        .btn-update:hover{
            transform: translateY(-1px);
            opacity:0.98;
        }

        @media (max-width: 767.98px){
            .page-wrap{
                padding-top: 88px;
            }

            .profile-card{
                padding: 24px 18px 24px;
                border-radius: 18px;
            }

            .profile-top{
                align-items:flex-start;
                gap:16px;
            }

            .profile-img{
                width:96px;
                height:96px;
            }

            .profile-name-wrap h3{
                font-size:24px;
            }

            .blood-badge{
                min-width:64px;
                height:42px;
                font-size:20px;
                border-radius:16px;
            }

            .file-input{
                width:100%;
            }
        }
    </style>
</head>
<body>

<?php $activePage = 'profile'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="container page-wrap">
    <h2 class="page-title">Profile</h2>

    <div class="profile-card">

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

        <form method="POST" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="profile-top">
                <img src="<?= htmlspecialchars($img) ?>" alt="Profile Image" class="profile-img">

                <div class="profile-name-wrap">
                    <h3>
                        <?= htmlspecialchars($user['name']) ?>
                        <span class="blood-badge"><?= htmlspecialchars($user['blood_group']) ?></span>
                    </h3>

                    <input type="file" name="profile_pic" class="form-control file-input" accept=".jpg,.jpeg,.png,.webp">
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        value="<?= htmlspecialchars($user['name']) ?>"
                        required
                    >

                    <div class="field-gap">
                        <label class="form-label">Email</label>
                        <input
                            type="email"
                            class="form-control"
                            value="<?= htmlspecialchars($user['email']) ?>"
                            disabled
                        >
                    </div>

                    <div class="field-gap">
                        <label class="form-label">Phone</label>
                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="field-gap">
                        <label class="form-label">City</label>
                        <input
                            type="text"
                            name="city"
                            class="form-control"
                            value="<?= htmlspecialchars($user['city']) ?>"
                            required
                        >
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-select" required>
                        <?php
                        $groups = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];
                        foreach ($groups as $g):
                        ?>
                            <option value="<?= htmlspecialchars($g) ?>" <?= (($user['blood_group'] ?? '') === $g) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="field-gap">
                        <label class="form-label">Status</label>
                        <select name="availability" class="form-select" required>
                            <option value="Available" <?= (($user['availability'] ?? '') === 'Available') ? 'selected' : '' ?>>
                                Available
                            </option>
                            <option value="Not Available" <?= (($user['availability'] ?? '') === 'Not Available') ? 'selected' : '' ?>>
                                Not Available
                            </option>
                        </select>
                    </div>

                    <div class="field-gap">
                        <label class="form-label">Tattoo in last 12 months</label>
                        <select name="tattoo_12m" class="form-select" required>
                            <option value="No" <?= (($user['tattoo_12m'] ?? '') === 'No') ? 'selected' : '' ?>>No</option>
                            <option value="Yes" <?= (($user['tattoo_12m'] ?? '') === 'Yes') ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>

                    <div class="field-gap">
                        <label class="form-label">HIV Positive</label>
                        <select name="hiv_positive" class="form-select" required>
                            <option value="No" <?= (($user['hiv_positive'] ?? '') === 'No') ? 'selected' : '' ?>>No</option>
                            <option value="Yes" <?= (($user['hiv_positive'] ?? '') === 'Yes') ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-update">
                Update Profile
            </button>
        </form>
    </div>
</div>

</body>
</html>