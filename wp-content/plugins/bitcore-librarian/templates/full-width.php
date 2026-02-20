<?php
/**
 * Template Name: BITCORE Full Width
 * Description: Full width template without theme wrapper
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('bitcore-full-width'); ?>>
    <?php
    // Start the loop
    if (have_posts()) :
        while (have_posts()) : the_post();
            // Output just the content - no theme wrapper
            the_content();
        endwhile;
    endif;
    ?>
    <?php wp_footer(); ?>
</body>
</html>
