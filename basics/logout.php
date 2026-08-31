<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';

session_unset();
session_destroy();
redirect('/basics/index.php');
