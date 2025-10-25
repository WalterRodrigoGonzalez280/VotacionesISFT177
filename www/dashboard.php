<?php
require_once 'controllers/DashboardController.php';

$dashboardController = new DashboardController();
$user = $dashboardController->index();
$stats = $dashboardController->getStats();

include 'views/dashboard.php';
?>
