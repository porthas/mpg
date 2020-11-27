<div class="block sidebar-block shadowed">
    <div class="sidebar-block-inner-content">

        <?php
         if (mpg_app()->is_premium()) {
        ?>
            <a class="btn btn-success" href="#"><?php _e('PRO version', 'mpg'); ?></a>

        <?php } else { ?>

            <a class="btn btn-primary upgrade-btn" href="<?php echo mpg_app()->get_upgrade_url(); ?>"><?php _e('Upgrade to PRO', 'mpg'); ?></a>

            <p><?php _e('Your current plan is limited to 50 generated pages and 1 template. Schedule source import is also a PRO feature', 'mpg') ?></p>

        <?php } ?>

    </div>
</div>

<div class="block sidebar-block shadowed">

    <h2><?php _e('Need Help?', 'mpg'); ?></h2>
    <div class="sidebar-block-inner-content">
        <h4><?php _e('Create comprehensible internal links', 'mpg') ?></h4>

        <ul>
            <li>
                <div class="number">1</div>
                <p><?php _e('MPG generates an additional sitemap that you can load in Google Search Console alongside your regular website sitemap', 'mpg'); ?></p>
            </li>

            <li>
                <div class="number">2</div>
                <p><?php _e('Google Search Console supports near limitless number of sitemaps per site, without any downside.', 'mpg'); ?></p>
            </li>

            <li>
                <div class="number">3</div>
                <p><?php _e('When sitemap is loaded into Google Search Console, try to Request Index manually for couple pages, for testing purposes.', 'mpg'); ?></p>
            </li>

            <li>
                <div class="number">4</div>
                <p><?php _e('Check Google Search Index periodically to make sure your pages are being indexed. Make sure to generate in-links using shortcode tab to increase frequency of index.', 'mpg'); ?></p>
            </li>

        </ul>
    </div>
    <a class="sidebar-learn-more" target="_blank" href="https://help.mpgwp.com/en-us/"><?php _e('Learn more', 'mpg'); ?></a>
</div>