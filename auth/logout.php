<?php
session_start();
session_unset();
session_destroy();
header('Location: /auction_system/index.php');
exit;
