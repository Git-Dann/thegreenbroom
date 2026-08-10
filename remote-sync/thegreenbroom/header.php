<?php
/**
 * The header for our theme
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
    
    <style>
        /* CRITICAL HEADER CSS (Self-contained) */
        .site-header { background: white; border-bottom: 1px solid #eee; position: relative; z-index: 1000; }
        .header-inner { max-width: 1200px; margin: 0 auto; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; }
        
        /* Logos */
        .logo-mobile { display: none !important; }
        .logo-desktop { display: block; height: 45px; width: auto; max-width: calc(100vw - 120px); }
        
        /* Desktop Menu */
        .main-navigation ul.nav-menu { 
            list-style: none; margin: 0; padding: 0; 
            display: flex; gap: 30px; align-items: center; 
        }
        .main-navigation a { 
            text-decoration: none; color: #333; font-weight: 600; font-size: 15px; 
            display: block; padding: 10px 0; 
        }
        .main-navigation a:hover { color: #10b981; }
        .main-navigation .menu-parent-label {
            color: #333; font-weight: 600; font-size: 15px;
            display: block; padding: 10px 0; cursor: default; user-select: none;
        }

        /* --- DROPDOWN LOGIC (FORCED) --- */
        .menu-item-has-children { position: relative !important; }
        
        .sub-menu {
            display: none !important; 
            position: absolute !important;
            top: 100% !important;
            left: -15px !important;
            min-width: 200px !important;
            background: white !important;
            border: 1px solid #eee !important;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
            border-radius: 8px !important;
            padding: 10px 0 !important;
            flex-direction: column !important;
            gap: 0 !important;
            z-index: 99999 !important;
        }
        
        .menu-item-has-children:hover .sub-menu { display: flex !important; }
        
        .sub-menu li { width: 100% !important; display: block !important; }
        .sub-menu a { 
            padding: 10px 20px !important; font-size: 14px !important; 
            white-space: nowrap !important; border-bottom: 1px solid #f9f9f9 !important;
            width: 100% !important;
        }
        .sub-menu a:hover { background: #f9f9f9 !important; color: #2e7d32 !important; }
        .dropdown-arrow { font-size: 10px; margin-left: 5px; color: #999; }

        /* Buttons */
        .header-actions { display: flex; align-items: center; gap: 15px; }
        .nav-login { text-decoration: none; color: #333; font-weight: 600; font-size: 15px; }
        .nav-cta { background: #10b981; color: white !important; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 15px; transition: 0.2s; }
        
        /* Mobile Toggle */
        .menu-toggle { display: none; background: none; border: none; cursor: pointer; padding: 5px; }
        .hamburger-box { width: 24px; height: 20px; display: inline-block; position: relative; }
        .hamburger-inner { display: block; top: 50%; margin-top: -1px; width: 100%; height: 2px; background-color: #333; position: absolute; }
        .hamburger-inner::before, .hamburger-inner::after { content: ""; display: block; width: 100%; height: 2px; background-color: #333; position: absolute; }
        .hamburger-inner::before { top: -8px; }
        .hamburger-inner::after { bottom: -8px; }

        /* MOBILE RESPONSIVE */
        @media (max-width: 900px) {
            .header-inner { padding: 12px 16px; }
            .site-branding { min-width: 0; }
            .logo-desktop { display: block; height: 32px; max-width: calc(100vw - 132px); }
            .logo-mobile { display: none !important; }
            
            .main-navigation { position: absolute; top: 100%; left: 0; right: 0; background: white; padding: 20px; border-bottom: 1px solid #eee; display: none; flex-direction: column; box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
            .main-navigation.toggled { display: flex; }
            
            .main-navigation ul.nav-menu { flex-direction: column; gap: 0; align-items: flex-start; }
            .main-navigation li { width: 100%; border-bottom: 1px solid #f9f9f9; }
            
            .sub-menu { 
                display: block !important; position: relative !important; top: 0 !important; left: 0 !important; 
                box-shadow: none !important; border: none !important; padding-left: 20px !important; 
                width: 100% !important; background: none !important;
            }
            
            .header-actions { display: flex; flex-shrink: 0; gap: 10px; }
            .nav-login { display: none; }
            .menu-toggle { display: block; }
        }
    </style>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">

    <header id="masthead" class="site-header">
        <div class="header-inner">

            <div class="site-branding">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                    <img class="logo-desktop" src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-main-logo.png" width="974" height="139" alt="The Green Broom">
                </a>
            </div>

            <nav id="site-navigation" class="main-navigation">
                <?php
                // MENU STRUCTURE
                $header_menu = [
                    [
                        'label' => 'About',
                        'url' => null, // parent-only, not clickable
                        'children' => [
                            ['label' => 'How it Works', 'url' => '/how-it-works'],
                            ['label' => 'What We Do', 'url' => '/what-we-do'],
                            ['label' => 'How We Do It', 'url' => '/how-we-do-it'],
                            ['label' => 'Eco Products', 'url' => '/eco-products']
                        ]
                    ],
                    ['label' => 'Cleaner Listings', 'url' => '/cleaner-listings'],
                    ['label' => 'Blog', 'url' => '/blog'],
                    ['label' => 'Create Account', 'url' => '/register']
                ];
                ?>
                <ul class="nav-menu">
                    <?php foreach ( $header_menu as $item ) : 
                        $has_children = isset($item['children']) && !empty($item['children']);
                    ?>
                        <li class="<?php echo $has_children ? 'menu-item-has-children' : ''; ?>">
                            <?php if( empty($item['url']) ): ?>
                            <span class="menu-parent-label">
                                <?php echo esc_html( $item['label'] ); ?>
                                <?php if($has_children): ?><span class="dropdown-arrow">▼</span><?php endif; ?>
                            </span>
                            <?php else: ?>
                            <a href="<?php echo esc_url( home_url($item['url']) ); ?>">
                                <?php echo esc_html( $item['label'] ); ?>
                                <?php if($has_children): ?><span class="dropdown-arrow">▼</span><?php endif; ?>
                            </a>
                            <?php endif; ?>
                            
                            <?php if($has_children): ?>
                                <ul class="sub-menu">
                                    <?php foreach($item['children'] as $sub): ?>
                                        <li>
                                            <a href="<?php echo esc_url( home_url($sub['url']) ); ?>">
                                                <?php echo esc_html( $sub['label'] ); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="header-actions">
                <?php if ( is_user_logged_in() ) : ?>
                    
                    <?php 
                        $current_user = wp_get_current_user(); 
                        $roles = (array) $current_user->roles;

                        // --- IMPROVED ROLE LOGIC ---
                        if ( in_array( 'gb_cleaner', $roles ) ) {
                            // 1. Cleaners see "Dashboard"
                            $dashboard_url  = function_exists( 'gb_get_post_login_redirect_url' ) ? gb_get_post_login_redirect_url( $current_user ) : home_url('/cleaner-dashboard');
                            $dashboard_text = 'Dashboard';
                        } elseif ( in_array( 'gb_manager', $roles ) || in_array( 'administrator', $roles ) ) {
                            // 2. Managers & Admins see "Manager"
                            $dashboard_url  = function_exists( 'gb_get_post_login_redirect_url' ) ? gb_get_post_login_redirect_url( $current_user ) : home_url('/manager-dashboard');
                            $dashboard_text = 'Manager';
                        } else {
                            // 3. Customers (and everyone else) see "My Account"
                            $dashboard_url  = function_exists( 'gb_get_post_login_redirect_url' ) ? gb_get_post_login_redirect_url( $current_user ) : home_url('/my-account');
                            $dashboard_text = 'My Account';
                        }
                    ?>
                    
                    <a href="<?php echo esc_url($dashboard_url); ?>" class="nav-login">
                        <?php echo esc_html($dashboard_text); ?>
                    </a>
                    
                    <a href="<?php echo wp_logout_url( home_url() ); ?>" class="nav-cta" style="background-color: #f1f5f9; color: #333 !important; border:1px solid #e2e8f0;">
                        Log out
                    </a>

                <?php else : ?>

                    <a href="<?php echo esc_url( home_url('/login') ); ?>" class="nav-login">
                        Log in
                    </a>

                <?php endif; ?>

                <button class="menu-toggle" type="button" aria-controls="site-navigation" aria-expanded="false" aria-label="Open navigation">
                    <span class="hamburger-box">
                        <span class="hamburger-inner"></span>
                    </span>
                </button>
            </div>

        </div>
    </header>

<div id="content" class="site-content">
