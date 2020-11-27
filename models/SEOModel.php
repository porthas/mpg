<?php

class MPG_SEOModel
{

    public static function mpg_yoast($project_id)
    {
        // Disable canonical url in Yoast
        add_filter('wpseo_canonical', function () {
            return false;
        }, 1, 1);


        // Over overiding canonical
        add_filter('wpseo_opengraph_url', function () {
            global $wp;
            return home_url($wp->request);
        }, 1, 1);

        // Скрываем блок с JSON LD, потому что нет возможности его переопределить, и выкинуть оттуда
        add_filter('wpseo_json_ld_output', function ($data) {
            return [];
        });

        // Заменяем шорткоды в <title>...</title>
        add_filter('wpseo_title', function ($title) use ($project_id) {
            return  MPG_CoreModel::mpg_shortcode_replacer($title, $project_id);
        });

        add_filter('wpseo_metadesc', function ($description) use ($project_id) {

            $post_id = get_queried_object_id();
            $description = get_post_meta($post_id, '_yoast_wpseo_metadesc',  true);

            return  MPG_CoreModel::mpg_shortcode_replacer($description, $project_id);
        });

        // Переписывает свойство <meta property="og:title">
        add_filter('wpseo_opengraph_title', function ($title) use ($project_id) {
            return  MPG_CoreModel::mpg_shortcode_replacer($title, $project_id);
        });

        // Переписывает свойство <meta property="og:description">
        add_filter('wpseo_opengraph_desc', function ($description) use ($project_id) {
            $post_id = get_queried_object_id();
            $description = get_post_meta($post_id, '_yoast_wpseo_metadesc',  true);

            return  MPG_CoreModel::mpg_shortcode_replacer($description, $project_id);
        });

        add_filter('wpseo_twitter_title', function ($description) use ($project_id) {
            return  MPG_CoreModel::mpg_shortcode_replacer($description, $project_id);
        });

        add_filter('wpseo_twitter_description', function ($description) use ($project_id) {
            return  MPG_CoreModel::mpg_shortcode_replacer($description, $project_id);
        });
    }

    public static function mpg_all_in_one_seo_pack($project_id)
    {

        add_filter('aioseop_title',  function ($title) use ($project_id) {
            return  MPG_CoreModel::mpg_shortcode_replacer($title, $project_id);
        });

        add_filter('aioseop_description_override',  function ($description) use ($project_id) {
            return  MPG_CoreModel::mpg_shortcode_replacer($description, $project_id);
        });

        add_filter('aioseop_canonical_url', function () {
            return false;
        }, 10, 1);
    }

    public static function mpg_rank_math($post, $project_id)
    {
        // RankMath SEO Plugin fix. Filter to change the page title
        add_filter('rank_math/frontend/title', function ($title) use ($post, $project_id) {
            return MPG_CoreModel::mpg_shortcode_replacer($post->post_title, $project_id);
        });


        add_filter('rank_math/frontend/description', function ($description) use ($project_id) {

            if ($description) {

                // This description is a global for all pages or posts.
                return  MPG_CoreModel::mpg_shortcode_replacer($description, $project_id);
            } else {

                global $post;
                $desc = RankMath\Post::get_meta('description', $post->ID);
                if (!$desc) {
                    $desc = RankMath\Helper::get_settings("titles.pt_{$post->post_type}_description");
                    if ($desc) {
                        return MPG_CoreModel::mpg_shortcode_replacer(RankMath\Helper::replace_vars($desc, $post), $project_id);
                    }
                }

                return  MPG_CoreModel::mpg_shortcode_replacer($desc, $project_id);
            }
        });

        add_filter('rank_math/frontend/robots', function () {
            // https://porthas.atlassian.net/browse/MPGWP-54
            $rank_math_settings = get_option('rank-math-options-titles');
            $robots_options = ['follow'];
            if ($rank_math_settings && isset($rank_math_settings['robots_global']) && is_array($rank_math_settings['robots_global'])) {
                $robots_options = array_merge($rank_math_settings['robots_global'], $robots_options);
            }

            return  $robots_options;
        });
    }

    public static function mpg_seopress($project_id)
    {

        add_filter('seopress_titles_title', function ($title) use ($project_id) {
            return  MPG_CoreModel::mpg_shortcode_replacer($title, $project_id);
        });

        add_filter('seopress_titles_desc', function ($description) use ($project_id) {
            return  MPG_CoreModel::mpg_shortcode_replacer($description, $project_id);
        });
    }
}
