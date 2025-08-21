<?php
session_start();
require_once '../logger.php';
require_once '../role_manager.php';
require_once '../role_helper.php';
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

log_action("Accessed home page", $_SESSION['id'] ?? null);

// Handle role switching via query param (full redirect links from sidebar)
if (isset($_GET['switch_role'])) {
    $requested_role = $_GET['switch_role'];
    $user_id = $_SESSION['id'] ?? null;
    if ($user_id) {
        if ($requested_role === 'assistant') {
            // Any HOD with assistant can switch down, or assistants remain assistant
            if (user_can_access_role($user_id, 'assistant')) {
                $_SESSION['role'] = 'assistant';
                log_action('Switched role to assistant', $user_id);
                header('Location: home.php');
                exit;
            }
        } elseif ($requested_role === 'hod') {
            // Only users who actually have HOD role can switch up
            if (user_can_access_role($user_id, 'hod')) {
                $_SESSION['role'] = 'hod';
                log_action('Switched role to hod', $user_id);
                header('Location: home.php');
                exit;
            } else {
                $_SESSION['access_denied'] = "You do not have the privilege to switch to HOD.";
                log_action('Blocked role switch to hod - insufficient privilege', $user_id);
                header('Location: home.php');
                exit;
            }
        }
    }
}

// Get user's role and navigation menu
$user_role = $_SESSION['role'] ?? 'employee';
$navigation_menu = get_navigation_menu($user_role);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uttarakhand Election Portal</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #385185;
            --primary-hover: #22396dff;
            --secondary-color: #fff;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --bg-dark: #000;
            --text-dark: #000;
            --text-light: #000;
            --text-white: #ffffff;
            --border-color: #385185;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --gradient: linear-gradient(135deg, #000 0%, #385185 100%);
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
        }

        body.light {
            --bg-primary: var(--bg-light);
            --bg-secondary: var(--primary-color);
            --text-primary: var(--text-dark);
            --text-secondary: var(--text-light);
            --border: var(--border-color);
        }
        
        body.light .welcome-title {
            background-color: black;
        }

        body.dark {
            --bg-primary: #000;
            --bg-secondary: #385185;
            --text-primary: var(--text-white);
            --text-secondary: #fff;
            --border: var(--border-color);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary, var(--bg-light));
            color: var(--text-primary, var(--text-dark));
            transition: all 0.3s ease;
            overflow-x: hidden;
        }

        /* Separate Banner */
        .banner {
            background: var(--gradient);
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: var(--shadow-lg);
            z-index: 100;
        }

        .banner-content {
            text-align: center;
            color: white;
        }

        .banner-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .banner-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Theme Toggle */
        .theme-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 10px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .theme-toggle:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }

        /* Separate Hamburger Menu */
        .menu-container {
            position: fixed;
            top: 140px;
            left: 20px;
            z-index: 1000;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .menu-container.shifted {
            left: 220px; 
        }

        .hamburger-btn {
            background: var(--primary-color);
            border: none;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
        }

        .hamburger-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(79, 70, 229, 0.4);
        }

        .hamburger {
            width: 24px;
            height: 18px;
            position: relative;
            transform: rotate(0deg);
            transition: 0.3s ease-in-out;
        }

        .hamburger span {
            display: block;
            position: absolute;
            height: 3px;
            width: 100%;
            background: white;
            border-radius: 2px;
            opacity: 1;
            left: 0;
            transform: rotate(0deg);
            transition: 0.25s ease-in-out;
        }

        .hamburger span:nth-child(1) { top: 0px; }
        .hamburger span:nth-child(2), .hamburger span:nth-child(3) { top: 7px; }
        .hamburger span:nth-child(4) { top: 14px; }

        .hamburger.open span:nth-child(1) {
            top: 7px;
            width: 0%;
            left: 50%;
        }

        .hamburger.open span:nth-child(2) {
            transform: rotate(45deg);
        }

        .hamburger.open span:nth-child(3) {
            transform: rotate(-45deg);
        }

        .hamburger.open span:nth-child(4) {
            top: 7px;
            width: 0%;
            left: 50%;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 120px;
            left: -280px;
            width: 280px;
            height: calc(100vh - 120px);
            background: var(--bg-secondary, var(--bg-white));
            border-right: 1px solid var(--border, var(--border-color));
            box-shadow: var(--shadow-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
            overflow-y: auto;
        }

        .sidebar.open {
            left: 0;
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1.5rem;
            border-bottom: 1px solid var(--border, var(--border-color));
        }

        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .sidebar-subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary, var(--text-light));
        }

        /* Navigation Items */
        .nav-menu {
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--text-secondary, var(--text-light));
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .nav-item:before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: transparent;
            margin-right: 12px;
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            background: rgba(22, 49, 113, 0.1);
            color:  rgba(220, 219, 245, 0.1);
            border-left-color: var(--primary-color);
            transform: translateX(5px);
        }

        .nav-item:hover:before {
            background: var(--primary-color);
            box-shadow: 0 0 8px rgba(79, 70, 229, 0.4);
        }

        .nav-item.active {
            background: rgba(22, 49, 113, 0.25);
            color: black;
            border-left-color: var(--primary-color);
            font-weight: 600;
        }

        .nav-item.active:before {
            background: var(--primary-color);
            box-shadow: 0 0 12px rgba(79, 70, 229, 0.6);
        }

        .nav-item.logout {
            margin-top: 2rem;
            border-top: 1px solid var(--border, var(--border-color));
            color: #ef4444;
        }

        .nav-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: #ef4444;
            color: #ef4444;
        }

        /* Main Content */
        .main-wrapper {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-left: 0;
            min-height: calc(100vh - 120px);
        }

        .main-wrapper.shifted {
            margin-left: 280px;
        }

        .main-content {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-section {
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: white;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary, var(--text-light));
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .content-card {
            background: var(--bg-secondary, var(--bg-white));
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border, var(--border-color));
            transition: all 0.3s ease;
        }

        .content-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .card-meta {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .role-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .permissions {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .feature-list {
            list-style: none;
            margin-top: 1rem;
        }
        
        .feature-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }
        
        .feature-list li i {
            color: var(--primary-color);
            width: 16px;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .card-description {
            color: var(--text-secondary, var(--text-light));
            line-height: 1.6;
        }

        /* Dynamic Content Area */
        .dynamic-content {
            background: transparent;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border, var(--border-color));
            margin-top: 2rem;
            min-height: 400px;
        }

        /* Form Styles */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-subtitle {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-white);
            color: var(--text-dark);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(56, 81, 133, 0.1);
        }

        .form-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }

        .form-submit:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .form-submit:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }

        /* Loading States */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            color: var(--text-secondary);
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Overlay for mobile */
        .overlay {
            position: fixed;
            top: 120px;
            left: 0;
            width: 100vw;
            height: calc(100vh - 120px);
            background: rgba(0,0,0,0.5);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 998;
            backdrop-filter: blur(2px);
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .banner-title { font-size: 1.8rem; }
            .banner-subtitle { font-size: 1rem; }
            .main-content { padding: 1rem; }
            .main-wrapper.shifted { margin-left: 0; }
            .sidebar { width: 100%; left: -100%; }
            .welcome-title { font-size: 2rem; }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Loading spinner */
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(56, 81, 133, 0.1);
            border-left: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Alert styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
    </style>
</head>
<body class="dark">
    <header class="banner">
        <div class="banner-content">
            <h1 class="banner-title">🗳️ Uttarakhand Election Portal</h1>
            <p class="banner-subtitle">Administrative Dashboard System</p>
        </div>
        <button id="themeToggle" class="theme-toggle" aria-label="Toggle theme">🌙</button>
        
    </header>

    <div class="menu-container">
        <button id="menuToggle" class="hamburger-btn" aria-label="Toggle menu">
            <div class="hamburger"><span></span><span></span><span></span><span></span></div>
        </button>
    </div>

    <nav id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">Navigation Menu</div>
            <div class="sidebar-subtitle">Election Portal Sections</div>
        </div>
        <div class="nav-menu">
            <?php foreach ($navigation_menu as $menu_item): ?>
                <?php 
                    $is_switch_link = (strpos($menu_item['url'], 'switch_role=') !== false);
                    if ($menu_item['name'] === 'Dashboard') {
                ?>
                    <a href="#" class="nav-item active" data-section="home"><span><?php echo $menu_item['name']; ?></span></a>
                <?php } elseif ($is_switch_link) { ?>
                    <a href="<?php echo htmlspecialchars($menu_item['url']); ?>" class="nav-item"><span><?php echo $menu_item['name']; ?></span></a>
                <?php } else { 
                    // Map menu names to actual file names
                    $section_mapping = [
                        'Admin Logs' => 'admin_logs',
                        'HOD Details' => 'hod-details',
                        'Employee Details' => 'employee-details',
                        'Assistant Management' => 'assistant-details',
                        'Assistant Profile' => 'assistant-profile',
                        'My Profile' => 'assistant-profile',
                    ];
                    $section_name = $section_mapping[$menu_item['name']] ?? strtolower(str_replace(' ', '-', $menu_item['name']));
                ?>
                    <a href="#" class="nav-item" data-section="<?php echo $section_name; ?>"><span><?php echo $menu_item['name']; ?></span></a>
                <?php } ?>
            <?php endforeach; ?>
            <a href="logout.php" class="nav-item logout"><span>Logout</span></a>
        </div>
    </nav>

    <div id="overlay" class="overlay"></div>

    <div id="mainWrapper" class="main-wrapper">
        <main class="main-content">
            <div id="defaultContent" class="welcome-section">
                <h1 class="welcome-title fade-in">Welcome to Uttarakhand Election Portal</h1>
                <p class="welcome-subtitle fade-in">Hello, <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?> (<?php echo get_role_display_name($user_role); ?>)</p>
                
                <?php if (isset($_SESSION['access_denied'])): ?>
                    <div class="alert alert-error fade-in">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($_SESSION['access_denied']); ?>
                    </div>
                    <?php unset($_SESSION['access_denied']); ?>
                <?php endif; ?>
                
                <div class="content-grid fade-in">
                    <div class="content-card">
                        <h3 class="card-title">Dashboard Overview</h3>
                        <p class="card-description">Access comprehensive analytics and real-time data insights for election management and administrative oversight.</p>
                        <div class="card-meta">
                            <span class="role-badge"><?php echo get_role_display_name($user_role); ?></span>
                        </div>
                    </div>
                    <div class="content-card">
                        <h3 class="card-title">Role Information</h3>
                        <p class="card-description"><?php echo get_role_description($user_role); ?></p>
                        <div class="card-meta">
                            <span class="permissions">Access Level: <?php echo ucfirst($user_role); ?></span>
                        </div>
                    </div>
                    <div class="content-card">
                        <h3 class="card-title">Available Features</h3>
                        <p class="card-description">Based on your role, you have access to the following features:</p>
                        <ul class="feature-list">
                            <?php foreach ($navigation_menu as $menu_item): ?>
                                <?php if ($menu_item['name'] !== 'Dashboard'): ?>
                                    <li><i class="<?php echo $menu_item['icon']; ?>"></i> <?php echo $menu_item['name']; ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div id="dynamicContent" class="dynamic-content" style="display: none;"></div>
        </main>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const mainWrapper = document.getElementById('mainWrapper');
        const hamburger = document.querySelector('.hamburger');
        const menuContainer = document.querySelector('.menu-container');
        const navItems = document.querySelectorAll('.nav-item[data-section]');
        const defaultContent = document.getElementById('defaultContent');
        const dynamicContent = document.getElementById('dynamicContent');

        // Theme Management
        const savedTheme = localStorage.getItem('theme') || 'dark';
        body.className = savedTheme;
        themeToggle.textContent = savedTheme === 'dark' ? '🌙' : '☀️';
        themeToggle.addEventListener('click', () => {
            const newTheme = body.className === 'dark' ? 'light' : 'dark';
            body.className = newTheme;
            themeToggle.textContent = newTheme === 'dark' ? '🌙' : '☀️';
            localStorage.setItem('theme', newTheme);
        });

        // Sidebar Management
        const toggleSidebar = () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            hamburger.classList.toggle('open');
            menuContainer.classList.toggle('shifted');
            if (window.innerWidth > 768) mainWrapper.classList.toggle('shifted');
        };
        menuToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Navigation
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                navItems.forEach(nav => nav.classList.remove('active'));
                item.classList.add('active');
                loadSection(item.dataset.section);
                if (window.innerWidth <= 768) toggleSidebar();
            });
        });

        // Content Loading
        window.loadSection = async function(section) {
            const sectionName = section.split('?')[0];
            if (sectionName === 'home') {
                defaultContent.style.display = 'block';
                dynamicContent.style.display = 'none';
                return;
            }
            
            defaultContent.style.display = 'none';
            dynamicContent.style.display = 'block';
            dynamicContent.innerHTML = '<div class="loading"><div class="spinner"></div>Loading...</div>';
            
            try {
                // Map section names to actual file paths
                const fileMapping = {
                    'hod-details': 'hod-details.php',
                    'employee-details': 'employee-details.php',
                    'employee': 'employee-details.php',
                    'assistant-details': 'assistant-details.php',
                    'assistant-profile': 'assistant-profile.php',
                    'admin_logs': '../admin_logs.php',
                };
                
                const fileName = fileMapping[sectionName] || `${sectionName}.php`;
                const response = await fetch(`${fileName}?${section.split('?')[1] || ''}`);
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                dynamicContent.innerHTML = await response.text();
            } catch (error) {
                dynamicContent.innerHTML = `<div class="alert alert-error">Error loading section: ${sectionName}. Please check if the file exists.</div>`;
                console.error('Error:', error);
            }
        }

        // --- EVENT HANDLING FOR DYNAMIC CONTENT ---

        // Listener for CLICK events
        dynamicContent.addEventListener('click', function(e) {
            // Assistant Management: Deactivate assistant
            const deactivateBtn = e.target.closest('.btn-deactivate-assistant');
            if (deactivateBtn) {
                e.preventDefault();
                const assistantId = deactivateBtn.getAttribute('data-assistant-id');
                if (!assistantId) return;
                if (!confirm('Are you sure you want to deactivate this assistant?')) return;
                fetch('deactivate-assistant.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'assistant_id=' + encodeURIComponent(assistantId)
                })
                .then(r => r.json())
                .then(data => {
                    alert(data.message || (data.success ? 'Assistant deactivated.' : 'Failed to deactivate assistant'));
                    if (data.success) {
                        // Reload Assistant Management section to reflect status change
                        loadSection('assistant-details');
                    }
                })
                .catch(() => alert('Network error while deactivating assistant'));
                return;
            }
            // For Assistant Page: Show registration form
            if (e.target && e.target.id === 'showAssistantFormBtn') {
                e.preventDefault();
                const formContainer = document.getElementById('assistantFormContainer');
                if (formContainer) {
                    formContainer.style.display = 'block';
                    e.target.style.display = 'none';
                    formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            }
            // For Employee Page: Handle edit button
            if (e.target && e.target.id === 'editSummaryBtn') {
                e.preventDefault();
                loadSection('employee?action=clear');
            }
            // For HOD Page: Handle edit button
            if (e.target && e.target.id === 'editHodBtn') {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('HOD Edit button clicked!');
                
                const summaryContainer = document.querySelector('.summary-overview-container');
                const hodFormContainer = document.getElementById('hodFormContainer');
                
                console.log('summaryContainer:', summaryContainer);
                console.log('hodFormContainer:', hodFormContainer);
                
                if (summaryContainer) {
                    console.log('Hiding summary container');
                    summaryContainer.style.display = 'none';
                }
                
                if (hodFormContainer) {
                    console.log('Showing form container');
                    hodFormContainer.style.setProperty('display', 'block', 'important');
                    hodFormContainer.style.setProperty('opacity', '1', 'important');
                    hodFormContainer.style.setProperty('visibility', 'visible', 'important');
                    
                    console.log('Form container display:', hodFormContainer.style.display);
                    console.log('Form container opacity:', hodFormContainer.style.opacity);
                    console.log('Form container visibility:', hodFormContainer.style.visibility);
                    
                    setTimeout(() => {
                        hodFormContainer.scrollIntoView({ behavior: 'smooth' });
                    }, 100);
                    
                    setTimeout(() => {
                        const addressTypeSelect = document.getElementById('addressType');
                        if (addressTypeSelect && addressTypeSelect.value) {
                            // Trigger the change event to show location details
                            const event = new Event('change', { bubbles: true });
                            addressTypeSelect.dispatchEvent(event);
                        }
                    }, 200);
                } else {
                    console.log('hodFormContainer not found!');
                }
            }
            // For HOD Page: Handle back to summary button
            if (e.target && e.target.id === 'backToSummaryBtn') {
                e.preventDefault();
                
                const hodFormContainer = document.getElementById('hodFormContainer');
                const summaryContainer = document.querySelector('.summary-overview-container');
                
                if (hodFormContainer) {
                    hodFormContainer.style.display = 'none';
                }
                
                if (summaryContainer) {
                    summaryContainer.style.display = 'block';
                    summaryContainer.scrollIntoView({ behavior: 'smooth' });
                }
            }
            // For Assistant Profile Page: Handle edit button
            if (e.target && e.target.id === 'editAssistantBtn') {
                e.preventDefault();
                
                console.log('Assistant Edit button clicked!');
                
                const summaryContainer = document.querySelector('.summary-overview-container');
                const assistantFormContainer = document.getElementById('assistantFormContainer');
                
                console.log('summaryContainer:', summaryContainer);
                console.log('assistantFormContainer:', assistantFormContainer);
                
                if (summaryContainer) {
                    console.log('Hiding summary container');
                    summaryContainer.style.display = 'none';
                }
                
                if (assistantFormContainer) {
                    console.log('Showing form container');
                    assistantFormContainer.style.setProperty('display', 'block', 'important');
                    assistantFormContainer.style.setProperty('opacity', '1', 'important');
                    assistantFormContainer.style.setProperty('visibility', 'visible', 'important');
                    
                    console.log('Form container display:', assistantFormContainer.style.display);
                    console.log('Form container opacity:', assistantFormContainer.style.opacity);
                    console.log('Form container visibility:', assistantFormContainer.style.visibility);
                    
                    setTimeout(() => {
                        assistantFormContainer.scrollIntoView({ behavior: 'smooth' });
                    }, 100);
                } else {
                    console.log('assistantFormContainer not found!');
                }
            }
            // For Assistant Profile Page: Handle back to summary button
            if (e.target && e.target.id === 'backToSummaryBtn') {
                e.preventDefault();
                
                const assistantFormContainer = document.getElementById('assistantFormContainer');
                const summaryContainer = document.querySelector('.summary-overview-container');
                
                if (assistantFormContainer) {
                    assistantFormContainer.style.display = 'none';
                }
                
                if (summaryContainer) {
                    summaryContainer.style.display = 'block';
                    summaryContainer.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });

        // Listener for SUBMIT events
        dynamicContent.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('Form submit event triggered!');
            
            const form = e.target;
            console.log('Form ID:', form.id);
            
            const submitBtn = form.querySelector('.form-submit');
            if (!submitBtn) {
                console.log('Submit button not found!');
                return;
            }
            
            console.log('Submit button found, disabling...');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            let formType = '';
            let processFile = '';

            if (form.id === 'hodForm') {
                 formType = 'hod-details';
                 processFile = 'process-hod.php'; // Make sure this file exists
            } else if (form.id === 'assistantForm') {
                formType = 'assistant-details';
                processFile = 'process-assistant-registration.php';
            } else if (form.id === 'assistantProfileForm') {
                formType = 'assistant-profile';
                processFile = 'process-assistant-profile.php';
            } else if (form.id === 'employeeSummaryForm') {
                formType = 'employee';
                processFile = 'process-summary.php';
            }

            if (!processFile) {
                submitBtn.disabled = false;
                return;
            }

            try {
                const formData = new FormData(form);
                console.log('Submitting form data:', Object.fromEntries(formData));
                console.log('Process file:', processFile);
                console.log('Form type:', formType);
                
                const response = await fetch(processFile, { method: 'POST', body: formData });
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                const responseText = await response.text();
                console.log('Response text:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                    console.log('Response result:', result);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response text:', responseText);
                    alert('Server returned invalid response. Please check console for details.');
                    return;
                }
                
                if (result.success) {
                    console.log('Form submission successful, reloading page...');
                    // Add a timestamp to prevent caching and ensure fresh data
                    const timestamp = new Date().getTime();
                    loadSection(`${formType}?t=${timestamp}`);
                } else {
                    console.log('Form submission failed:', result.message);
                    alert('Error: ' + (result.message || 'An unknown error occurred.'));
                }
            } catch (error) {
                console.error('Form submission error:', error);
                alert('A network error occurred: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Submit HOD Details';
            }
        });

               // Listener for CHANGE events (for dropdowns)
       dynamicContent.addEventListener('change', function(e) {
           // For HOD Page: Show/hide address sections
           if (e.target && e.target.id === 'addressType') {
               const urbanDetails = document.getElementById('urbanDetails');
               const ruralDetails = document.getElementById('ruralDetails');
               const urbanCity = document.getElementById('urbanCity');
               const urbanWard = document.getElementById('urbanWard');
               const ruralDistrict = document.getElementById('ruralDistrict');
               const ruralTehsil = document.getElementById('ruralTehsil');
               
               if (urbanDetails && ruralDetails) {
                   if (e.target.value === 'urban') {
                       urbanDetails.style.display = 'grid';
                       ruralDetails.style.display = 'none';
                       
                       // Add required to urban fields, remove from rural fields
                       if (urbanCity) urbanCity.setAttribute('required', 'required');
                       if (urbanWard) urbanWard.setAttribute('required', 'required');
                       if (ruralDistrict) ruralDistrict.removeAttribute('required');
                       if (ruralTehsil) ruralTehsil.removeAttribute('required');
                   } else if (e.target.value === 'rural') {
                       urbanDetails.style.display = 'none';
                       ruralDetails.style.display = 'grid';
                       
                       // Add required to rural fields, remove from urban fields
                       if (urbanCity) urbanCity.removeAttribute('required');
                       if (urbanWard) urbanWard.removeAttribute('required');
                       if (ruralDistrict) ruralDistrict.setAttribute('required', 'required');
                       if (ruralTehsil) ruralTehsil.setAttribute('required', 'required');
                   } else {
                       // No selection - hide both and remove required from all
                       urbanDetails.style.display = 'none';
                       ruralDetails.style.display = 'none';
                       if (urbanCity) urbanCity.removeAttribute('required');
                       if (urbanWard) urbanWard.removeAttribute('required');
                       if (ruralDistrict) ruralDistrict.removeAttribute('required');
                       if (ruralTehsil) ruralTehsil.removeAttribute('required');
                   }
               }
               
               // Also handle the showLocationDetails function if it exists
               if (typeof showLocationDetails === 'function') {
                   showLocationDetails();
               }
               
               // Add phone input validation
               const phoneInput = document.getElementById('phone');
               if (phoneInput) {
                   phoneInput.addEventListener('input', function() {
                       if (typeof validatePhoneInput === 'function') {
                           validatePhoneInput(this);
                       }
                   });
               }
               
               // Add post/position dropdown handling
               const postSelect = document.getElementById('postSearch');
               if (postSelect) {
                   postSelect.addEventListener('change', function() {
                       if (typeof handlePostPositionChange === 'function') {
                           handlePostPositionChange();
                       }
                   });
                   
                   // Initialize on load
                   if (typeof handlePostPositionChange === 'function') {
                       handlePostPositionChange();
                   }
               }
           }
       });

        // Initial Load
        loadSection('home');
    </script>
</body>
</html>
