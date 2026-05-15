<?php
require_once __DIR__ . '/../includes/functions.php';
unset($_SESSION['admin_id']);
flash('success', 'Admin logged out.');
redirect('../index.php');
