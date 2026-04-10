<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/session.php';
requireLogin();

require_once 'config/db.php';

$activePage = 'search';

function sanitize($data){
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$results = [];
$searched = false;

$blood_group = '';
$city = '';

$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$total_rows = 0;
$total_pages = 0;

$allowed_groups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

/* DEFAULT IMAGE */
$default_image = "assets/images/default-profile.png";

/* =====================
   FETCH CITY LIST
===================== */
$cities = [];

$cityQuery = $conn->query("
    SELECT DISTINCT city
    FROM users
    WHERE city IS NOT NULL
      AND city != ''
    ORDER BY city ASC
");

while($row = $cityQuery->fetch_assoc()){
    $cities[] = $row['city'];
}

/* =====================
   SEARCH
===================== */
if(isset($_GET['blood_group'], $_GET['city'])){

    $blood_group = sanitize($_GET['blood_group']);
    $city        = sanitize($_GET['city']);
    $searched    = true;

    if($blood_group && $city && in_array($blood_group, $allowed_groups, true)){

        $city_like = "%" . $city . "%";

        $countStmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM users
            WHERE blood_group = ?
              AND city LIKE ?
              AND role = 'user'
              AND availability = 'Available'
        ");

        $countStmt->bind_param("ss", $blood_group, $city_like);
        $countStmt->execute();
        $total_rows = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
        $countStmt->close();

        $total_pages = ($total_rows > 0) ? (int)ceil($total_rows / $limit) : 0;

        if($page > $total_pages && $total_pages > 0){
            $page = $total_pages;
            $offset = ($page - 1) * $limit;
        }

        $stmt = $conn->prepare("
            SELECT id, name, blood_group, city, profile_pic, availability
            FROM users
            WHERE blood_group = ?
              AND city LIKE ?
              AND role = 'user'
              AND availability = 'Available'
            ORDER BY id DESC
            LIMIT ?, ?
        ");

        $stmt->bind_param("ssii", $blood_group, $city_like, $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        while($row = $result->fetch_assoc()){
            $results[] = $row;
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Find Donors - BloodConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root{
            --primary-red: #ef3340;
            --primary-red-dark: #d81f32;
            --soft-bg-1: #fff4f6;
            --soft-bg-2: #fde7ec;
            --soft-bg-3: #f9dce4;
            --card-bg: rgba(255,255,255,0.72);
            --text-dark: #241f31;
            --text-muted: #6f6578;
            --shadow-soft: 0 12px 30px rgba(232, 84, 110, 0.12);
            --shadow-btn: 0 10px 22px rgba(239, 51, 64, 0.28);
            --radius-xl: 26px;
            --radius-lg: 22px;
            --radius-md: 16px;
        }

        *{
            box-sizing: border-box;
        }

        body{
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text-dark);
            background:
                radial-gradient(circle at top left, rgba(255,255,255,0.9), transparent 28%),
                radial-gradient(circle at top right, rgba(255,214,224,0.55), transparent 30%),
                linear-gradient(135deg, var(--soft-bg-1) 0%, var(--soft-bg-2) 45%, var(--soft-bg-3) 100%);
            min-height: 100vh;
        }

        .page-wrap{
            padding: 42px 0 60px;
        }

        .find-layout{
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 34px;
            align-items: start;
        }

        .search-panel{
            background: rgba(255,255,255,0.58);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 34px 30px;
            box-shadow: var(--shadow-soft);
        }

        .search-panel h2{
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 30px;
            color: #20192d;
        }

        .field-group{
            margin-bottom: 24px;
        }

        .field-group label{
            display: block;
            font-size: 17px;
            font-weight: 500;
            color: #5c5267;
            margin-bottom: 12px;
        }

        .custom-select{
            width: 100%;
            height: 62px;
            border: 1px solid #e6dbe0;
            border-radius: 12px;
            background: rgba(255,255,255,0.95);
            padding: 0 18px;
            font-size: 18px;
            color: #2b2330;
            outline: none;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.55);
        }

        .custom-select:focus{
            border-color: #f05b69;
            box-shadow: 0 0 0 4px rgba(239, 51, 64, 0.10);
        }

        .search-btn{
            width: 100%;
            height: 62px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(180deg, #ff4a56 0%, var(--primary-red) 100%);
            color: #fff;
            font-size: 20px;
            font-weight: 800;
            box-shadow: var(--shadow-btn);
            transition: 0.2s ease;
        }

        .search-btn:hover{
            transform: translateY(-1px);
            background: linear-gradient(180deg, #ff3e4b 0%, var(--primary-red-dark) 100%);
        }

        .list-title{
            font-size: 30px;
            font-weight: 800;
            margin: 12px 0 24px;
            color: #241c2f;
        }

        .donor-list{
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .donor-card{
            background: rgba(255,255,255,0.52);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 22px 26px;
            box-shadow: var(--shadow-soft);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .donor-left{
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
            flex: 1;
        }

        .donor-avatar{
            width: 92px;
            height: 92px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ef4b58;
            background: #f4f4f4;
            flex-shrink: 0;
        }

        .donor-details{
            min-width: 0;
        }

        .donor-name{
            margin: 0 0 6px;
            font-size: 28px;
            line-height: 1.1;
            font-weight: 800;
            color: #231d2d;
        }

        .donor-meta{
            font-size: 16px;
            color: #6b6171;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .status-line{
            font-size: 16px;
            color: #6b6171;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-dot{
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #f7be57;
            display: inline-block;
            flex-shrink: 0;
        }

        .donor-right{
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }

        .blood-badge{
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(180deg, #ff5964 0%, #ef3340 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
            box-shadow: 0 8px 18px rgba(239, 51, 64, 0.22);
        }

        .request-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 122px;
            height: 56px;
            padding: 0 24px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(180deg, #ff4a56 0%, var(--primary-red) 100%);
            color: #fff;
            text-decoration: none;
            font-size: 17px;
            font-weight: 800;
            box-shadow: var(--shadow-btn);
            transition: 0.2s ease;
        }

        .request-btn:hover{
            background: linear-gradient(180deg, #ff3e4b 0%, var(--primary-red-dark) 100%);
            color: #fff;
            transform: translateY(-1px);
        }

        .empty-box{
            background: rgba(255,255,255,0.55);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 40px 24px;
            text-align: center;
            box-shadow: var(--shadow-soft);
            color: #5f5668;
            font-size: 18px;
            font-weight: 600;
        }

        .pagination-wrap{
            margin-top: 26px;
        }

        .pagination .page-link{
            color: var(--primary-red-dark);
            border: none;
            margin: 0 4px;
            border-radius: 10px;
            min-width: 42px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .pagination .page-item.active .page-link{
            background: var(--primary-red);
            color: #fff;
        }

        .helper-note{
            margin-top: 10px;
            font-size: 14px;
            color: #7b6f80;
        }

        @media (max-width: 991px){
            .find-layout{
                grid-template-columns: 1fr;
            }

            .search-panel{
                padding: 26px 22px;
            }

            .list-title{
                margin-top: 0;
            }
        }

        @media (max-width: 767px){
            .page-wrap{
                padding: 24px 0 40px;
            }

            .donor-card{
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
            }

            .donor-left{
                width: 100%;
            }

            .donor-right{
                width: 100%;
                justify-content: space-between;
            }

            .donor-name{
                font-size: 24px;
            }

            .search-panel h2,
            .list-title{
                font-size: 26px;
            }

            .blood-badge{
                width: 64px;
                height: 64px;
                font-size: 20px;
            }

            .request-btn{
                min-width: 110px;
                height: 52px;
            }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="container page-wrap">
    <div class="find-layout">

        <!-- LEFT SEARCH PANEL -->
        <div class="search-panel">
            <h2>Find Donors</h2>

            <form method="GET" action="">
                <div class="field-group">
                    <label for="blood_group">Select Blood Group</label>
                    <select name="blood_group" id="blood_group" class="custom-select" required>
                        <option value="">Select Blood Group</option>
                        <?php foreach($allowed_groups as $group): ?>
                            <option value="<?= $group ?>" <?= ($blood_group === $group) ? 'selected' : '' ?>>
                                <?= $group ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-group">
                    <label for="city">Select City</label>
                    <select name="city" id="city" class="custom-select" required>
                        <option value="">Select City</option>
                        <?php foreach($cities as $c): ?>
                            <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>" <?= ($city === $c) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>

        <!-- RIGHT DONOR LIST -->
        <div>
            <h2 class="list-title">Donor List</h2>

            <?php if($searched): ?>
                <?php if(count($results) > 0): ?>
                    <div class="donor-list">
                        <?php foreach($results as $row): ?>
                            <?php
                                if(!empty($row['profile_pic']) && file_exists("uploads/" . $row['profile_pic'])){
                                    $img = "uploads/" . $row['profile_pic'];
                                } else {
                                    $img = $default_image;
                                }
                            ?>

                            <div class="donor-card">
                                <div class="donor-left">
                                    <img src="<?= $img ?>" alt="Donor" class="donor-avatar">

                                    <div class="donor-details">
                                        <h3 class="donor-name"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></h3>

                                        <div class="donor-meta">
                                            <span>📍</span>
                                            <span><?= htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>

                                        <div class="status-line">
                                            <span class="status-dot"></span>
                                            <span>Available</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="donor-right">
                                    <div class="blood-badge">
                                        <?= htmlspecialchars($row['blood_group'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>

                                    <!-- ONLY ONE REQUEST BUTTON -->
                                    <a href="donor-profile.php?id=<?= (int)$row['id'] ?>" class="request-btn">
                                        Request
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if($total_pages > 1): ?>
                        <div class="pagination-wrap">
                            <nav>
                                <ul class="pagination justify-content-center">

                                    <?php
                                    $prevPage = $page - 1;
                                    $nextPage = $page + 1;
                                    $baseQuery = '&blood_group=' . urlencode($blood_group) . '&city=' . urlencode($city);
                                    ?>

                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $prevPage . $baseQuery ?>">Prev</a>
                                    </li>

                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i . $baseQuery ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $nextPage . $baseQuery ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-box">
                        No donors found for the selected blood group and city.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-box">
                    Select blood group and city, then click search.
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>