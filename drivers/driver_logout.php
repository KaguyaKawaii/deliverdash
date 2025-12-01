<?php
session_start();
session_destroy();
header("Location: driver_login.php");
exit();