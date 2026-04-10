<?php require_once 'config/db.php'; ?>

<form method="POST">

<input type="text" name="name" placeholder="Patient Name" required>

<select name="blood_group" required>
<option value="">Select Blood Group</option>
<option>A+</option>
<option>B+</option>
<option>O+</option>
<option>AB+</option>
</select>

<input type="text" name="city" placeholder="City" required>

<input type="text" name="phone" placeholder="Phone">

<textarea name="message" placeholder="Details"></textarea>

<button type="submit">Submit Emergency Request</button>

</form>
<?php

if($_SERVER['REQUEST_METHOD']=="POST"){

$name=$_POST['name'];
$blood=$_POST['blood_group'];
$city=$_POST['city'];
$phone=$_POST['phone'];
$msg=$_POST['message'];

$stmt=$conn->prepare("INSERT INTO emergency_requests(name,blood_group,city,phone,message) VALUES (?,?,?,?,?)");

$stmt->bind_param("sssss",$name,$blood,$city,$phone,$msg);

$stmt->execute();

echo "Request submitted";

}

?>