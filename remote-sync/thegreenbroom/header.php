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
    
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">

    <header id="masthead" class="site-header">
        <div class="header-inner">

            <div class="site-branding">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                    <picture>
                        <?php gb_picture_sources( 'https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-main-logo.png' ); ?>
                    <img class="logo-desktop" src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-main-logo.png" width="974" height="139" alt="The Green Broom">
                    </picture>
                </a>
            </div>

            <nav id="site-navigation" class="main-navigation">
                <?php
                // MENU STRUCTURE
                // Home sits at the top level rather than inside About: the
                // client asked for the homepage in the menu, and "About >
                // Home" reads oddly. One line to move it if they prefer.
                $header_menu = [
                    ['label' => 'Home', 'url' => '/'],
                    [
                        'label' => 'About',
                        'url' => null, // parent-only, not clickable
                        'children' => [
                            ['label' => 'What Cleaners Do', 'url' => '/what-cleaners-do'],
                            ['label' => 'The Green Broom Standard', 'url' => '/the-green-broom-standard'],
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
                        <?php $submenu_id = 'menu-' . sanitize_title($item['label']); ?>
                        <li class="<?php echo $has_children ? 'menu-item-has-children' : ''; ?>">
                            <?php if( empty($item['url']) ): ?>
                            <button class="menu-parent-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr($submenu_id); ?>">
                                <?php echo esc_html( $item['label'] ); ?>
                                <?php if($has_children): ?><span class="dropdown-arrow" aria-hidden="true">▼</span><?php endif; ?>
                            </button>
                            <?php else: ?>
                            <a href="<?php echo esc_url( home_url($item['url']) ); ?>">
                                <?php echo esc_html( $item['label'] ); ?>
                                <?php if($has_children): ?><span class="dropdown-arrow">▼</span><?php endif; ?>
                            </a>
                            <?php endif; ?>
                            
                            <?php if($has_children): ?>
                                <ul id="<?php echo esc_attr($submenu_id); ?>" class="sub-menu">
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
                            $dashboard_url  = function_exists( 'gb_get_post_login_redirect_url' ) ? gb_get_post_login_redirect_url( $current_user ) : home_url('/my-account/');
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
