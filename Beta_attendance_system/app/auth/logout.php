<?php
session_start();
session_destroy();
header("Location: /Beta_attendance_system/app/auth/login.php");
exit();
