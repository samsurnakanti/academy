<?php
require_once __DIR__ . '/includes/functions.php';
clear_remembered_device();
unset($_SESSION['user_id']);
flash('success', 'You have been logged out.');
redirect('index.php');
