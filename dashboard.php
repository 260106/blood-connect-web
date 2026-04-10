<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/session.php';
requireLogin();

require_once 'config/db.php';

$activePage = 'dashboard';
$user_id = $_SESSION['user_id'];

/* ===================================
   DELETE REQUEST (SAFE)
=================================== */
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];

    if ($delete_id > 0) {
        $stmt = $conn->prepare("
            DELETE FROM requests
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $delete_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: dashboard.php?deleted=1");
    exit();
}

/* ===================================
   FETCH USER
=================================== */
$stmt = $conn->prepare("
    SELECT id, name, email, city, blood_group, role
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

/* ===================================
   DASHBOARD STATS
=================================== */
$total_donors   = 0;
$total_requests = 0;
$city_covered   = 0;

/* TOTAL DONORS */
$result = $conn->query("
    SELECT COUNT(*) AS c
    FROM users
    WHERE role = 'user'
");
if ($result) {
    $total_donors = (int)($result->fetch_assoc()['c'] ?? 0);
    $result->free();
}

/* TOTAL REQUESTS */
$result = $conn->query("
    SELECT COUNT(*) AS c
    FROM requests
");
if ($result) {
    $total_requests = (int)($result->fetch_assoc()['c'] ?? 0);
    $result->free();
}

/* TOTAL DISTINCT CITIES COVERED BY ALL DONORS */
$result = $conn->query("
    SELECT COUNT(DISTINCT city) AS c
    FROM users
    WHERE role = 'user'
      AND city IS NOT NULL
      AND city != ''
");
if ($result) {
    $city_covered = (int)($result->fetch_assoc()['c'] ?? 0);
    $result->free();
}

/* USER REQUESTS */
$stmt = $conn->prepare("
    SELECT id, blood_group, city, hospital_name, contact_number, status, created_at
    FROM requests
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BloodConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root{
            --primary-red: #dc3545;
            --primary-red-dark: #b52a37;
            --soft-text: #6b7280;
            --heading-text: #2d2d2d;
            --card-border: #f1d7db;
            --card-shadow: 0 10px 30px rgba(220, 53, 69, 0.08);
            --card-shadow-soft: 0 6px 18px rgba(0,0,0,0.06);
        }

        *{
            box-sizing: border-box;
        }

        body{
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #fff6f7;
            color: #2d2d2d;
            min-height: 100vh;
        }

        .dashboard-page{
            padding-top: 120px;
            padding-bottom: 80px;
        }

        .dashboard-container{
            max-width: 1280px;
            margin: 0 auto;
            padding-left: 28px;
            padding-right: 28px;
        }

        .welcome-block{
            margin-bottom: 28px;
        }

        .welcome-title{
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: var(--heading-text);
            line-height: 1.2;
        }

        .welcome-title .user-name{
            color: var(--primary-red);
            font-weight: 800;
            text-transform: capitalize;
        }

        .welcome-subtitle{
            margin-top: 8px;
            margin-bottom: 0;
            font-size: 1rem;
            color: var(--soft-text);
            font-weight: 500;
        }

        .stats-row{
            margin-bottom: 34px;
        }

        .stats-card{
            background: rgba(255,255,255,0.97);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            box-shadow: var(--card-shadow);
            min-height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px 16px;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .stats-card:hover{
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(220, 53, 69, 0.12);
        }

        .stats-number{
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1;
            color: var(--primary-red);
            margin-bottom: 10px;
        }

        .stats-label{
            font-size: 1rem;
            color: #5c5c5c;
            font-weight: 600;
        }

        .content-card{
            background: rgba(255,255,255,0.97);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow-soft);
            height: 100%;
        }

        .content-header{
            background: linear-gradient(90deg, var(--primary-red), #ef4c5d);
            color: #fff;
            padding: 14px 20px;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .content-body{
            padding: 20px;
            background: #fff;
        }

        .empty-text{
            margin: 0;
            color: #827a83;
            font-size: 1rem;
            font-weight: 500;
        }

        .request-list{
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .request-item{
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding: 16px 18px;
            border: 1px solid #f0e2e7;
            border-radius: 12px;
            background: #fff;
        }

        .request-main{
            flex: 1;
            min-width: 0;
        }

        .request-top{
            font-size: 0.98rem;
            color: #4f4952;
            font-weight: 600;
            line-height: 1.6;
            word-break: break-word;
        }

        .request-meta{
            margin-top: 6px;
            font-size: 0.88rem;
            color: #8b808a;
        }

        .request-actions{
            flex-shrink: 0;
        }

        .delete-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 92px;
            height: 42px;
            border-radius: 10px;
            background: #fff4f6;
            color: #dc3545;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
            border: 1px solid #ffd9df;
            transition: all .2s ease;
        }

        .delete-btn:hover{
            background: #ffe8ec;
            color: #b61d2e;
        }

        .status-badge{
            display: inline-block;
            margin-left: 10px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            vertical-align: middle;
        }

        .status-pending{
            background: #fff3cd;
            color: #856404;
        }

        .status-approved{
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-other{
            background: #e2e3e5;
            color: #41464b;
        }

        .top-alert{
            max-width: 1280px;
            margin: 100px auto 0;
            padding-left: 28px;
            padding-right: 28px;
        }

        .summary-box{
            background: #fff8f8;
            border: 1px solid #f3d9dd;
            border-radius: 14px;
            padding: 20px;
        }

        .summary-box h5{
            margin: 0 0 14px 0;
            color: var(--primary-red);
            font-size: 1.1rem;
            font-weight: 700;
        }

        .summary-item{
            margin-bottom: 12px;
            padding: 12px 14px;
            background: #fff;
            border: 1px solid #f6d9de;
            border-radius: 10px;
        }

        .summary-item:last-child{
            margin-bottom: 0;
        }

        .summary-label{
            display: block;
            font-size: 0.9rem;
            color: #7a6d75;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .summary-value{
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary-red);
        }

        .summary-note{
            margin-top: 16px;
            font-size: 0.95rem;
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 991px){
            .dashboard-page{
                padding-top: 100px;
            }

            .welcome-title{
                font-size: 1.75rem;
            }

            .stats-number{
                font-size: 2.3rem;
            }
        }

        @media (max-width: 767px){
            .dashboard-container{
                padding-left: 16px;
                padding-right: 16px;
            }

            .top-alert{
                padding-left: 16px;
                padding-right: 16px;
            }

            .welcome-title{
                font-size: 1.5rem;
            }

            .welcome-subtitle{
                font-size: 0.95rem;
            }

            .request-item{
                flex-direction: column;
                align-items: flex-start;
            }

            .request-actions{
                width: 100%;
            }

            .delete-btn{
                width: 100%;
                height: 44px;
            }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="top-alert">
        <div class="alert alert-success border-0 shadow-sm">
            Request deleted successfully.
        </div>
    </div>
<?php endif; ?>

<div class="dashboard-page">
    <div class="dashboard-container">

        <div class="welcome-block">
            <h1 class="welcome-title">
                Welcome,
                <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
            </h1>
            <p class="welcome-subtitle">Your BloodConnect dashboard overview</p>
        </div>

        <div class="row g-4 stats-row">
            <div class="col-lg-4 col-md-6">
                <div class="stats-card">
                    <div>
                        <div class="stats-number"><?= $total_donors ?></div>
                        <div class="stats-label">Total Donors</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="stats-card">
                    <div>
                        <div class="stats-number"><?= $total_requests ?></div>
                        <div class="stats-label">Total Requests</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="stats-card">
                    <div>
                        <div class="stats-number"><?= $city_covered ?></div>
                        <div class="stats-label">Cities Covered by Donors</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="content-card">
                    <div class="content-header">My Recent Requests</div>

                    <div class="content-body">
                        <?php if ($requests && $requests->num_rows > 0): ?>
                            <div class="request-list">
                                <?php while ($r = $requests->fetch_assoc()): ?>
                                    <div class="request-item">
                                        <div class="request-main">
                                            <div class="request-top">
                                                <strong><?= htmlspecialchars($r['blood_group']) ?></strong>
                                                |
                                                <?= htmlspecialchars($r['city']) ?>
                                                |
                                                <?= htmlspecialchars($r['hospital_name']) ?>
                                                |
                                                <?= htmlspecialchars($r['contact_number']) ?>

                                                <?php if ($r['status'] === 'Pending'): ?>
                                                    <span class="status-badge status-pending">Pending</span>
                                                <?php elseif ($r['status'] === 'Approved'): ?>
                                                    <span class="status-badge status-approved">Approved</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-other">
                                                        <?= htmlspecialchars($r['status']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="request-meta">
                                                <?= date('M d, Y', strtotime($r['created_at'])) ?>
                                            </div>
                                        </div>

                                        <div class="request-actions">
                                            <a
                                                class="delete-btn"
                                                href="dashboard.php?delete_id=<?= (int)$r['id'] ?>"
                                                onclick="return confirm('Delete this request?')"
                                                title="Delete"
                                            >
                                                Delete
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="empty-text">No requests posted yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="content-card">
                    <div class="content-header">Donor Coverage Summary</div>
                    <div class="content-body">
                        <div class="summary-box">
                            <h5>Overall Donor Reach</h5>

                            <div class="summary-item">
                                <span class="summary-label">Total Donors</span>
                                <div class="summary-value"><?= $total_donors ?></div>
                            </div>

                            <div class="summary-item">
                                <span class="summary-label">Cities Covered</span>
                                <div class="summary-value"><?= $city_covered ?></div>
                            </div>

                            <p class="summary-note">
                                Total donors are currently spread across
                                <strong><?= $city_covered ?></strong> different cities.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>