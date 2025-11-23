<?php
session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] !== "Cashier")) exit();
echo count($_SESSION['cart']);
