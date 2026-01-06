<?php
/**
 * Componente de Navegação do Admin
 * Mostra links de navegação entre painéis
 */

$current_page = basename($_SERVER['PHP_SELF']);
$username = getLoggedUsername();
?>
<style>
.admin-nav {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 51, 51, 0.2);
    border-radius: 16px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-nav-links {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.admin-nav-link {
    padding: 0.6rem 1.2rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 51, 51, 0.2);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-nav-link:hover {
    background: rgba(255, 51, 51, 0.15);
    border-color: var(--primary);
    color: var(--light);
    transform: translateY(-2px);
}

.admin-nav-link.active {
    background: var(--gradient);
    border-color: var(--primary);
    color: var(--light);
}

.admin-nav-user {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-nav-username {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.admin-nav-logout {
    padding: 0.6rem 1.2rem;
    background: rgba(255, 51, 51, 0.15);
    border: 1px solid rgba(255, 51, 51, 0.3);
    border-radius: 8px;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-nav-logout:hover {
    background: rgba(255, 51, 51, 0.25);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .admin-nav {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .admin-nav-links {
        width: 100%;
        flex-direction: column;
    }
    
    .admin-nav-link {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="admin-nav">
    <div class="admin-nav-links">
        <a href="dashboard.php" class="admin-nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="admin.php" class="admin-nav-link <?= $current_page === 'admin.php' ? 'active' : '' ?>">
            <i class="fas fa-truck"></i> Rastreamento
        </a>
        <a href="admin_bot_config.php" class="admin-nav-link <?= $current_page === 'admin_bot_config.php' ? 'active' : '' ?>">
            <i class="fas fa-robot"></i> Bot WhatsApp
        </a>
    </div>
    <div class="admin-nav-user">
        <span class="admin-nav-username">
            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?>
        </span>
        <a href="dashboard.php?logout=1" class="admin-nav-logout">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </div>
</div>

