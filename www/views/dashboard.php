<?php
$page_title = 'Dashboard - Sistema de Votaciones';
$additional_css = ['assets/css/dashboard.css'];
$additional_js = ['assets/js/dashboard.js'];
include 'templates/header.php';
?>

<div class="dashboard-container">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light dashboard-navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-vote-yea"></i> Sistema de Votaciones
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars(getUserName()); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Bienvenida -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="fas fa-home"></i> Bienvenido, <?php echo htmlspecialchars($user->nombre . ' ' . $user->apellido); ?>
                        </h2>
                        <p class="card-text">
                            <i class="fas fa-info-circle"></i> 
                            Rol: <span class="badge bg-primary"><?php echo ucfirst($user->rol); ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4><?php echo $stats['usuarios']; ?></h4>
                        <p class="mb-0">Usuarios Registrados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-vote-yea fa-2x mb-2"></i>
                        <h4><?php echo $stats['votaciones_activas']; ?></h4>
                        <p class="mb-0">Votaciones Activas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-user-tie fa-2x mb-2"></i>
                        <h4><?php echo $stats['candidatos']; ?></h4>
                        <p class="mb-0">Candidatos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-bar fa-2x mb-2"></i>
                        <h4><?php echo $stats['participacion']; ?></h4>
                        <p class="mb-0">Participación</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones principales -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="mb-0"><i class="fas fa-vote-yea"></i> Votaciones</h5>
                    </div>
                    <div class="dashboard-card-body">
                        <p>Participa en las votaciones activas y ejerce tu derecho al voto.</p>
                        <a href="votaciones.php" class="dashboard-btn">
                            <i class="fas fa-vote-yea"></i> Ver Votaciones
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="mb-0"><i class="fas fa-user-tie"></i> Candidatos</h5>
                    </div>
                    <div class="dashboard-card-body">
                        <p>Conoce a los candidatos y sus propuestas para las elecciones.</p>
                        <a href="candidatos.php" class="dashboard-btn">
                            <i class="fas fa-user-tie"></i> Ver Candidatos
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Panel de administración -->
        <div class="row">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs"></i> Panel de Administración</h5>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <a href="admin/usuarios.php" class="dashboard-btn-outline w-100 d-block text-center">
                                    <i class="fas fa-users"></i> Gestionar Usuarios
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="admin/candidatos.php" class="dashboard-btn-outline w-100 d-block text-center">
                                    <i class="fas fa-user-tie"></i> Gestionar Candidatos
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="admin/votaciones.php" class="dashboard-btn-outline w-100 d-block text-center">
                                    <i class="fas fa-vote-yea"></i> Gestionar Votaciones
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
