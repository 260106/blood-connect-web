<?php
require_once 'config/session.php';
session_unset();
session_destroy();
redirect('login.php?msg=logged_out');
