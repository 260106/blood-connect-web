<?php

require_once 'config/session.php';
requireLogin();
require_once 'config/db.php';

$lat = $_GET['lat'];
$lon = $_GET['lon'];

$sql = "
SELECT id,name,blood_group,city,profile_pic,availability,

( 6371 *
acos(
cos(radians(?)) *
cos(radians(latitude)) *
cos(radians(longitude) - radians(?)) +
sin(radians(?)) *
sin(radians(latitude))
)
) AS distance

FROM users
WHERE role='user'
HAVING distance < 50
ORDER BY distance
LIMIT 20
";

$stmt = $conn->prepare($sql);

$stmt->bind_param("ddd",$lat,$lon,$lat);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<title>Nearby Donors</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<?php include 'includes/navbar.php'; ?>

<div class="container py-5">

<h3 class="mb-4 text-danger fw-bold">
Nearby Blood Donors
</h3>

<div class="row">

<?php while($row = $result->fetch_assoc()): ?>

<div class="col-md-6 mb-3">

<a href="donor-profile.php?id=<?= $row['id'] ?>" style="text-decoration:none;color:black">

<div class="card shadow-sm">

<div class="card-body d-flex gap-3">

<img
src="uploads/<?= $row['profile_pic'] ?: 'default.png' ?>"
style="width:70px;height:70px;border-radius:50%;object-fit:cover">

<div>

<h6 class="fw-bold">
<?= htmlspecialchars($row['name']) ?>
</h6>

<p class="mb-1">
Blood: <?= $row['blood_group'] ?>
</p>

<p class="mb-1">
<?= $row['city'] ?>
</p>

<span class="badge bg-success">

<?= $row['availability'] ?>

</span>

</div>

</div>

</div>

</a>

</div>

<?php endwhile; ?>

</div>

</div>

</body>
</html>