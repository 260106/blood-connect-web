<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/session.php';
requireLogin();

require_once 'config/db.php';

$activePage = 'search';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("
    SELECT id, name, email, phone, blood_group, city, profile_pic, availability
    FROM users
    WHERE id = ? AND role = 'user'
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Donor not found");
}

$donor = $result->fetch_assoc();
$stmt->close();

/* =============================
   DEFAULT IMAGE LOGIC
============================= */
$default_image = "assets/images/default-profile.png";

if (
    !empty($donor['profile_pic']) &&
    file_exists("uploads/" . $donor['profile_pic'])
) {
    $profile_image = "uploads/" . $donor['profile_pic'];
} else {
    $profile_image = $default_image;
}

$phone = trim($donor['phone'] ?? '');
$clean_phone = preg_replace('/\D+/', '', $phone);

if ($clean_phone !== '') {
    if (strlen($clean_phone) === 10) {
        $wa_phone = "91" . $clean_phone;
        $tel_phone = "+91" . $clean_phone;
    } else {
        $wa_phone = $clean_phone;
        $tel_phone = "+" . $clean_phone;
    }
} else {
    $wa_phone = '';
    $tel_phone = '';
}

$message = urlencode("Hi " . $donor['name'] . ", I need " . $donor['blood_group'] . " blood urgently.");
$is_available = strtolower(trim($donor['availability'])) === 'available';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donor Profile - BloodConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root{
            --primary-red: #ef3340;
            --primary-red-dark: #d91f32;
            --soft-pink-1: #fff7fa;
            --soft-pink-2: #fcecf2;
            --soft-pink-3: #f6dee8;
            --text-dark: #2d2433;
            --text-muted: #6f6472;
            --line: rgba(120, 95, 110, 0.12);
            --glass-bg: rgba(255,255,255,0.52);
            --shadow-main: 0 14px 40px rgba(230, 92, 122, 0.14);
            --shadow-btn: 0 10px 24px rgba(239, 51, 64, 0.24);
            --radius-xl: 28px;
            --radius-lg: 18px;
            --radius-md: 14px;
        }

        *{
            box-sizing: border-box;
        }

        body{
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text-dark);
            background:
                radial-gradient(circle at top left, rgba(255,255,255,0.92), transparent 26%),
                radial-gradient(circle at top right, rgba(255,220,232,0.55), transparent 28%),
                linear-gradient(135deg, var(--soft-pink-1) 0%, var(--soft-pink-2) 48%, var(--soft-pink-3) 100%);
            min-height: 100vh;
        }

        /* navbar red-ah maintain panna */
        .navbar,
        nav.navbar{
            background: linear-gradient(90deg, #ef3d4b 0%, #f04a59 100%) !important;
            box-shadow: 0 4px 18px rgba(239, 51, 64, 0.18);
        }

        .page-wrap{
            padding: 42px 0 60px;
        }

        .profile-card{
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 36px 38px 34px;
            box-shadow: var(--shadow-main);
        }

        .top-section{
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .top-left{
            display: flex;
            align-items: center;
            gap: 24px;
            min-width: 0;
            flex: 1;
        }

        .profile-img{
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #f04d63;
            background: #f2f2f2;
            flex-shrink: 0;
        }

        .name-block{
            min-width: 0;
        }

        .donor-name{
            margin: 0 0 8px;
            font-size: 34px;
            line-height: 1.1;
            font-weight: 800;
            color: #2b2231;
        }

        .meta-line{
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-muted);
            font-size: 18px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .status-line{
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 500;
        }

        .status-line.available{
            color: #2f8f46;
        }

        .status-line.unavailable{
            color: #7b7b7b;
        }

        .blood-badge{
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(180deg, #ff4654 0%, #ef3340 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: 800;
            box-shadow: 0 12px 30px rgba(239, 51, 64, 0.25);
            flex-shrink: 0;
        }

        .section-divider{
            border: none;
            border-top: 1px solid var(--line);
            margin: 34px 0 22px;
        }

        .info-row{
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px 0;
            border-bottom: 1px solid var(--line);
        }

        .icon-circle{
            width: 54px;
            height: 54px;
            min-width: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .icon-blood{
            background: #ffe6eb;
            color: #ef3340;
        }

        .icon-mail{
            background: #fff3d9;
            color: #a07b00;
        }

        .icon-phone{
            background: #e7f1ff;
            color: #3d7be0;
        }

        .icon-status{
            background: #e7f5e9;
            color: #3d9b4f;
        }

        .info-text{
            font-size: 19px;
            color: #514555;
            word-break: break-word;
        }

        .info-text strong{
            color: #312736;
            font-weight: 800;
        }

        .status-value.available{
            color: #2f8f46;
        }

        .status-value.unavailable{
            color: #7b7b7b;
        }

        .action-btns{
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            padding-top: 26px;
        }

        .action-btns .btn{
            border: none;
            border-radius: 14px;
            padding: 17px 30px;
            font-size: 17px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .btn-request{
            background: linear-gradient(180deg, #ff4856 0%, #ef3340 100%);
            color: #fff;
            box-shadow: var(--shadow-btn);
        }

        .btn-request:hover{
            background: linear-gradient(180deg, #ff3d4b 0%, #d91f32 100%);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-call{
            background: #f1ebef;
            color: #433842;
            box-shadow: 0 8px 20px rgba(80, 60, 70, 0.08);
        }

        .btn-call:hover{
            background: #e9e1e6;
            color: #433842;
            transform: translateY(-1px);
        }

        .btn-whatsapp{
            background: linear-gradient(180deg, #55c95d 0%, #3fb14a 100%);
            color: #fff;
            box-shadow: 0 10px 22px rgba(63, 177, 74, 0.24);
        }

        .btn-whatsapp:hover{
            background: linear-gradient(180deg, #48bf51 0%, #349f3e 100%);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-disabled{
            opacity: 0.65;
            pointer-events: none;
        }

        @media (max-width: 991px){
            .profile-card{
                padding: 28px 24px;
            }

            .top-section{
                flex-direction: column;
                align-items: flex-start;
            }

            .blood-badge{
                width: 120px;
                height: 120px;
                font-size: 34px;
                align-self: flex-end;
            }

            .profile-img{
                width: 130px;
                height: 130px;
            }

            .donor-name{
                font-size: 30px;
            }
        }

        @media (max-width: 767px){
            .page-wrap{
                padding: 24px 0 40px;
            }

            .top-left{
                flex-direction: column;
                align-items: flex-start;
            }

            .blood-badge{
                width: 100px;
                height: 100px;
                font-size: 28px;
                align-self: flex-start;
            }

            .profile-img{
                width: 110px;
                height: 110px;
            }

            .donor-name{
                font-size: 28px;
            }

            .meta-line,
            .status-line,
            .info-text{
                font-size: 17px;
            }

            .action-btns .btn{
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="container page-wrap">
    <div class="profile-card">

        <div class="top-section">
            <div class="top-left">
                <img src="<?= htmlspecialchars($profile_image, ENT_QUOTES, 'UTF-8') ?>" alt="Donor Profile" class="profile-img">

                <div class="name-block">
                    <h1 class="donor-name"><?= htmlspecialchars($donor['name'], ENT_QUOTES, 'UTF-8') ?></h1>

                    <div class="meta-line">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><?= htmlspecialchars($donor['city'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <?php if($is_available): ?>
                        <div class="status-line available">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Available</span>
                        </div>
                    <?php else: ?>
                        <div class="status-line unavailable">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span>Not Available</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="blood-badge">
                <?= htmlspecialchars($donor['blood_group'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <hr class="section-divider">

        <div class="info-row">
            <div class="icon-circle icon-blood">
                <i class="fa-solid fa-droplet"></i>
            </div>
            <div class="info-text">
                <strong>Blood Group :</strong>
                <?= htmlspecialchars($donor['blood_group'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <div class="info-row">
            <div class="icon-circle icon-mail">
                <i class="fa-solid fa-envelope"></i>
            </div>
            <div class="info-text">
                <strong>Email :</strong>
                <?= htmlspecialchars($donor['email'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <div class="info-row">
            <div class="icon-circle icon-phone">
                <i class="fa-solid fa-phone"></i>
            </div>
            <div class="info-text">
                <strong>Phone :</strong>
                <?= !empty($phone) ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : 'Not Provided' ?>
            </div>
        </div>

        <div class="info-row">
            <div class="icon-circle icon-status">
                <i class="fa-solid fa-circle"></i>
            </div>
            <div class="info-text">
                <strong>Status :</strong>
                <?php if($is_available): ?>
                    <span class="status-value available">Available</span>
                <?php else: ?>
                    <span class="status-value unavailable">Not Available</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="action-btns">
            <a href="request-blood.php?donor_id=<?= (int)$donor['id'] ?>" class="btn btn-request">
                <i class="fa-solid fa-droplet"></i>
                Request Blood
            </a>

            <?php if(!empty($tel_phone)): ?>
                <a href="tel:<?= htmlspecialchars($tel_phone, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-call">
                    <i class="fa-solid fa-phone"></i>
                    Call Donor
                </a>
            <?php else: ?>
                <span class="btn btn-call btn-disabled">
                    <i class="fa-solid fa-phone"></i>
                    Call Donor
                </span>
            <?php endif; ?>

            <?php if(!empty($wa_phone)): ?>
                <a href="https://wa.me/<?= htmlspecialchars($wa_phone, ENT_QUOTES, 'UTF-8') ?>?text=<?= $message ?>" target="_blank" class="btn btn-whatsapp">
                    <i class="fa-brands fa-whatsapp"></i>
                    WhatsApp Connect
                </a>
            <?php else: ?>
                <span class="btn btn-whatsapp btn-disabled">
                    <i class="fa-brands fa-whatsapp"></i>
                    WhatsApp Connect
                </span>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>