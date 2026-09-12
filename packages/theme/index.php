<?php
/**
 * Blocky Theme — index.php (fallback for non-FSE environments)
 *
 * @package Blocky
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header>
    <?php get_template_part('parts/header'); ?>
</header>

<main id="main">
    <?php
    if (have_posts()) :
        while (have_posts()) :
            the_post();
            the_content();
        endwhile;
    endif;
    ?>
</main>

<footer>
    <?php get_template_part('parts/footer'); ?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
