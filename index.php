<?php
require_once 'config/db.php';
require_once 'config/session.php';

$activePage = 'home';
$logged_in = isLoggedIn();

/* -------------------------
   Stats Section
-------------------------- */
$donors = 0;
$reqs   = 0;
$cities = 0;
$bloodGroups = 0;

/* Available Donors */
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE role='user' AND availability='Available'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$donors = $row['c'] ?? 0;
$stmt->close();

/* Total Requests */
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM requests");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$reqs = $row['c'] ?? 0;
$stmt->close();

/* Cities Covered */
$stmt = $conn->prepare("SELECT COUNT(DISTINCT city) as c FROM users WHERE role='user'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$cities = $row['c'] ?? 0;
$stmt->close();

/* Blood Groups */
$stmt = $conn->prepare("SELECT COUNT(DISTINCT blood_group) as c FROM users WHERE role='user'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$bloodGroups = $row['c'] ?? 0;
$stmt->close();

/* -------------------------
   Emergency Request Banner
-------------------------- */
$emergency = null;

$stmt = $conn->prepare("
    SELECT blood_group, city, contact_number
    FROM requests
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $emergency = $res->fetch_assoc();
}
$stmt->close();

/* -------------------------
   Recent Requests
-------------------------- */
$stmt = $conn->prepare("
    SELECT blood_group, hospital_name, city, contact_number, created_at
    FROM requests
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BloodConnect — Home</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root{
            --primary:#df2f37;
            --primary-dark:#bc1f28;
            --soft-bg:#f6f4f6;
            --soft-red:#ffe8eb;
            --soft-red-2:#ffd9df;
            --text-dark:#171717;
            --text-muted:#5f6368;
            --card-border:#f2d8dc;
            --card-shadow:0 10px 24px rgba(214,40,40,0.10);
        }

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color:var(--text-dark);
            background:linear-gradient(180deg, #f4f4f6 0%, #fbfbfc 100%);
        }

        a{
            text-decoration:none;
        }

        .navbar-brand .brand{
            display:flex;
            align-items:center;
            gap:8px;
        }

        .navbar-brand .brand img{
            height:42px;
            width:auto;
            object-fit:contain;
        }

        .page-wrap{
            padding-top:30px;
            padding-bottom:50px;
        }

        .emergency-banner{
            margin-bottom:20px;
            border-radius:16px;
            padding:14px 18px;
            background:linear-gradient(135deg, #8b0000, #c1121f);
            color:#fff;
            box-shadow:0 12px 24px rgba(139,0,0,0.22);
        }

        .emergency-banner .btn{
            border-radius:10px;
            font-weight:600;
        }

        .hero-section{
            position:relative;
            overflow:hidden;
            padding:55px 38px 28px;
            border-radius:28px;
            background:
                radial-gradient(circle at 78% 42%, rgba(255,60,60,0.30), transparent 18%),
                radial-gradient(circle at 88% 72%, rgba(214,40,40,0.18), transparent 20%),
                linear-gradient(120deg, #fffefe 0%, #fff4f6 36%, #ffdce2 100%);
            box-shadow:0 14px 38px rgba(0,0,0,0.08);
        }

        .hero-section::before{
            content:"";
            position:absolute;
            right:-120px;
            top:-60px;
            width:560px;
            height:560px;
            border-radius:50%;
            background:radial-gradient(circle, rgba(255,100,100,0.16) 0%, rgba(255,100,100,0.05) 42%, transparent 72%);
            pointer-events:none;
        }

        .hero-content{
            position:relative;
            z-index:2;
        }

        .hero-title{
            font-size:clamp(2.2rem, 5vw, 4rem);
            font-weight:800;
            line-height:1.12;
            margin-bottom:16px;
            letter-spacing:-0.8px;
        }

        .hero-title .accent{
            color:var(--primary);
        }

        .hero-subtitle{
            font-size:1.04rem;
            color:#555;
            max-width:520px;
            line-height:1.8;
            margin-bottom:26px;
        }

        .hero-actions{
            display:flex;
            gap:14px;
            flex-wrap:wrap;
        }

        .btn-hero-primary{
            display:inline-block;
            background:linear-gradient(135deg, #df2f37, #f0454e);
            color:#fff;
            border:none;
            border-radius:14px;
            padding:13px 22px;
            font-weight:700;
            box-shadow:0 10px 18px rgba(214,40,40,0.18);
        }

        .btn-hero-primary:hover{
            color:#fff;
            background:linear-gradient(135deg, #c9272f, #dc3f47);
        }

        .btn-hero-light{
            display:inline-block;
            background:#fff;
            color:#d62828;
            border:1px solid #f0d3d7;
            border-radius:14px;
            padding:13px 22px;
            font-weight:700;
        }

        .btn-hero-light:hover{
            background:#fff5f6;
            color:#b71c1c;
        }

        .hero-visual{
            position:relative;
            min-height:360px;
            display:flex;
            align-items:center;
            justify-content:center;
            z-index:2;
        }

        .hero-visual::before{
            content:"";
            position:absolute;
            width:420px;
            height:420px;
            border-radius:50%;
            background:radial-gradient(circle, rgba(255,0,0,0.18) 0%, rgba(255,0,0,0.08) 38%, transparent 72%);
            filter:blur(12px);
            z-index:1;
        }

        .pulse-svg{
            position:absolute;
            width:100%;
            max-width:440px;
            top:50%;
            left:50%;
            transform:translate(-50%, -50%);
            z-index:1;
            opacity:0.72;
        }

        .pulse-svg polyline{
            stroke:#fff;
            stroke-width:4;
            fill:none;
            stroke-linecap:round;
            stroke-linejoin:round;
            filter:
                drop-shadow(0 0 4px rgba(255,255,255,0.95))
                drop-shadow(0 0 10px rgba(255,255,255,0.75));
        }

        .hero-image{
            position:relative;
            z-index:2;
            width:290px;
            max-width:100%;
            object-fit:contain;
            filter:drop-shadow(0 20px 30px rgba(180,0,0,0.26));
        }

        .stats-section{
            margin-top:26px;
            position:relative;
            z-index:3;
        }

        .stat-card{
            height:100%;
            background:rgba(255,255,255,0.92);
            border:1px solid var(--card-border);
            border-radius:18px;
            box-shadow:var(--card-shadow);
            padding:20px 18px;
            transition:0.25s ease;
        }

        .stat-card:hover{
            transform:translateY(-4px);
        }

        .stat-inner{
            display:flex;
            align-items:center;
            gap:14px;
        }

        .stat-icon{
            width:54px;
            height:54px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:16px;
            background:linear-gradient(135deg, #ffe7ea, #fff8f8);
            box-shadow:inset 0 0 0 1px #f5d8dd;
            font-size:28px;
            flex-shrink:0;
        }

        .stat-number{
            margin:0;
            font-size:2rem;
            font-weight:800;
            line-height:1;
        }

        .stat-label{
            margin:6px 0 0;
            color:var(--text-muted);
            font-size:14px;
            font-weight:500;
        }

        .section-block{
            margin-top:42px;
            background:#fff;
            border:1px solid #f3e4e7;
            border-radius:24px;
            box-shadow:0 12px 34px rgba(0,0,0,0.06);
            padding:28px;
        }

        .section-title{
            font-size:1.7rem;
            font-weight:800;
            margin-bottom:6px;
        }

        .section-subtitle{
            color:var(--text-muted);
            margin-bottom:22px;
        }

        .custom-table{
            overflow:hidden;
            border-radius:18px;
            border:1px solid #f0e4e6;
        }

        .custom-table table{
            margin-bottom:0;
        }

        .custom-table thead th{
            background:linear-gradient(135deg, #d62828, #ef5350);
            color:#fff;
            border:none;
            font-size:14px;
            padding:14px;
            white-space:nowrap;
        }

        .custom-table tbody td{
            vertical-align:middle;
            padding:14px;
            border-color:#f3e5e7;
        }

        .custom-table tbody tr:hover{
            background:#fff7f8;
        }

        .badge-blood{
            display:inline-block;
            background:#ffe6ea;
            color:#b71c1c;
            font-weight:700;
            padding:7px 12px;
            border-radius:999px;
            font-size:13px;
        }

        .btn-wa{
            background:#198754;
            border:none;
            color:#fff;
            border-radius:10px;
            padding:8px 14px;
            font-weight:600;
        }

        .btn-wa:hover{
            background:#157347;
            color:#fff;
        }

        footer{
            margin-top:40px;
            background:#111;
            color:#fff;
            text-align:center;
            padding:18px 10px;
        }

        @media (max-width: 991.98px){
            .hero-section{
                padding:38px 22px 24px;
            }

            .hero-visual{
                min-height:280px;
                margin-top:22px;
            }

            .hero-image{
                width:220px;
            }

            .pulse-svg{
                max-width:340px;
            }

            .hero-visual::before{
                width:320px;
                height:320px;
            }
        }

        @media (max-width: 575.98px){
            .page-wrap{
                padding-top:20px;
                padding-bottom:35px;
            }

            .hero-section{
                border-radius:22px;
                padding:30px 16px 18px;
            }

            .hero-title{
                font-size:2.15rem;
            }

            .hero-subtitle{
                font-size:0.96rem;
            }

            .hero-visual{
                min-height:220px;
            }

            .pulse-svg{
                max-width:280px;
            }

            .hero-image{
                width:170px;
            }

            .stat-card{
                padding:16px;
            }

            .stat-icon{
                width:48px;
                height:48px;
                font-size:24px;
            }

            .stat-number{
                font-size:1.6rem;
            }

            .section-block{
                padding:18px 14px;
            }

            .custom-table{
                border-radius:14px;
            }
        }
    </style>
</head>

<body>

<?php include 'includes/navbar.php'; ?>

<div class="container page-wrap">

    <?php if($emergency): ?>
        <div class="emergency-banner d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                🚨 <strong>URGENT:</strong>
                <strong><?= htmlspecialchars($emergency['blood_group']) ?></strong>
                blood needed in
                <strong><?= htmlspecialchars($emergency['city']) ?></strong>
            </div>

            <a
                href="https://wa.me/91<?= htmlspecialchars($emergency['contact_number']) ?>?text=Hi%20I%20saw%20your%20blood%20request"
                class="btn btn-light btn-sm px-3"
                target="_blank"
            >
                Contact via WhatsApp
            </a>
        </div>
    <?php endif; ?>

    <section class="hero-section">
        <div class="row align-items-center">
            <div class="col-lg-6 hero-content">
                <h1 class="hero-title">
                    Find Blood <span class="accent">Donors</span><br>
                    & Save <span class="accent">Lives</span>
                </h1>

                <p class="hero-subtitle">
                    Connect blood donors to people in urgent need through a clean, fast, and reliable blood donation network.
                </p>

                <div class="hero-actions">
                    <?php if(!$logged_in): ?>
                        <a href="register.php" class="btn-hero-primary">Become a Donor</a>
                        <a href="login.php" class="btn-hero-light">Login Now</a>
                    <?php else: ?>
                        <a href="search.php" class="btn-hero-primary">Find Donor</a>
                        <a href="request_blood.php" class="btn-hero-light">Request Blood</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="hero-visual">
                    <svg class="pulse-svg" viewBox="0 0 600 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <polyline points="0,60 75,60 98,60 120,28 142,86 165,60 220,60 245,18 270,102 295,60 360,60 387,60 410,28 432,86 455,60 520,60 545,60 565,42 585,72 600,60"></polyline>
                    </svg>

                    <img src="assets/images/blood-drop.png" alt="Blood Drop" class="hero-image">
                </div>
            </div>
        </div>

        <div class="row g-3 stats-section">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-inner">
                        <div class="stat-icon">🩸</div>
                        <div>
                            <h3 class="stat-number"><?= $donors ?></h3>
                            <p class="stat-label">Available Donors</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-inner">
                        <div class="stat-icon">📢</div>
                        <div>
                            <h3 class="stat-number"><?= $reqs ?></h3>
                            <p class="stat-label">Blood Requests</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-inner">
                        <div class="stat-icon">📍</div>
                        <div>
                            <h3 class="stat-number"><?= $cities ?></h3>
                            <p class="stat-label">Cities Covered</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-inner">
                        <div class="stat-icon">🧪</div>
                        <div>
                            <h3 class="stat-number"><?= $bloodGroups ?></h3>
                            <p class="stat-label">Blood Groups</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if($recent->num_rows > 0): ?>
        <section class="section-block">
            <h3 class="section-title">Recent Blood Requests</h3>
            <p class="section-subtitle">Latest urgent requests from hospitals and patients.</p>

            <div class="table-responsive custom-table">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Blood</th>
                            <th>Hospital</th>
                            <th>City</th>
                            <th>Contact</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($r = $recent->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="badge-blood"><?= htmlspecialchars($r['blood_group']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($r['hospital_name']) ?></td>
                                <td><?= htmlspecialchars($r['city']) ?></td>
                                <td><?= htmlspecialchars($r['contact_number']) ?></td>
                                <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <a
                                        href="https://wa.me/91<?= htmlspecialchars($r['contact_number']) ?>?text=Hi%20I%20saw%20your%20blood%20request"
                                        class="btn btn-wa btn-sm"
                                        target="_blank"
                                    >
                                        WhatsApp
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

</div>

<footer>
    © <?= date('Y') ?> BloodConnect — Saving Lives Together.
</footer>

</body>
</html>