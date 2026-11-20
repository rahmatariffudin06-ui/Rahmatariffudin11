<?php
include "auth.php";
header("Location: " . (isset($_SESSION['user_id']) ? "dashboard.php" : "login.php"));
exit;
