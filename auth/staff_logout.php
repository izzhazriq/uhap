<?php
session_start();
session_destroy();
header("Location: ../auth/staff_login.php");
exit;
