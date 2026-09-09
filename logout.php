<?php

session_start();

$_SESSION = [];
session_destroy();

header("Location: index.php?status=success&message=" . urlencode("You have been logged out."));
exit;