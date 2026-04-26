<?php
session_start();
session_destroy();
header('Location: ' . 'http://localhost/library/auth/login.php');
exit;
