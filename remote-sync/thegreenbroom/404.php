<?php
/**
 * The template for displaying 404 pages (not found)
 */

get_header(); 
?>

<style>
    #gb-404-page {
        background-color: #f8f9fa !important;
        min-height: 70vh; /* Takes up most of the screen */
        width: 100vw;
        position: relative;
        left: 50%;
        right: 50%;
        margin-left: -50vw; /* Break out of theme container */
        margin-right: -50vw;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 60px 20px;
        font-family: sans-serif;
    }

    .gb-404-content {
        max-width: 600px;
        background: white;
        padding: 50px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        border: 1px solid #eee;
    }

    .gb-404-icon {
        font-size: 80px;
        margin-bottom: 20px;
        display: block;
    }

    .gb-404-title {
        font-size: 3rem;
        font-weight: 800;
        color: #1b5e20;
        margin: 0 0 15px;
        line-height: 1.1;
    }

    .gb-404-text {
        font-size: 1.1rem;
        color: #666;
        margin-bottom: 30px;
        line-height: 1.6;
    }

    .gb-404-btn {
        background-color: #2e7d32;
        color: white;
        padding: 15px 30px;
        font-size: 1rem;
        font-weight: 700;
        text-decoration: none;
        border-radius: 50px;
        transition: all 0.2s;
        display: inline-block;
    }
    .gb-404-btn:hover {
        background-color: #1b5e20;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(46, 125, 50, 0.2);
    }

    .gb-404-links {
        margin-top: 30px;
        font-size: 0.9rem;
        color: #888;
    }
    .gb-404-links a { color: #2e7d32; text-decoration: none; font-weight: 600; }
    .gb-404-links a:hover { text-decoration: underline; }
</style>

<div id="gb-404-page">
    <div class="gb-404-content">
        <span class="gb-404-icon">🧹</span>
        
        <h1 class="gb-404-title">We missed a spot!</h1>
        
        <p class="gb-404-text">
            Oops! It looks like the page you are looking for has been swept away or doesn't exist anymore.
        </p>

        <a href="<?php echo home_url(); ?>" class="gb-404-btn">Back to Home</a>

        <div class="gb-404-links">
            Or try looking for a <a href="/cleaner-listings">Cleaner</a> instead?
        </div>
    </div>
</div>

<?php get_footer(); ?>