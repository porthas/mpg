<?php

require_once(realpath(__DIR__ . '/../helpers/Helper.php'));
// require_once(realpath(__DIR__ . '/../helpers/Constant.php'));
require_once(realpath(__DIR__ . '/../models/ProjectModel.php'));
require_once(realpath(__DIR__ . '/../controllers/CoreController.php'));
require_once(realpath(__DIR__ . '/../controllers/DatasetController.php'));
require_once(realpath(__DIR__ . '/../controllers/ProjectController.php'));
require_once(realpath(__DIR__ . '/../controllers/SpintaxController.php'));
require_once(realpath(__DIR__ . '/../controllers/CacheController.php'));

class MPG_HookController
{

    public static function init_base()
    {

        $mpg_index_file = plugin_dir_path(__DIR__) . 'porthas-multi-pages-generator.php';

        // Подключает .mo файл перевода из указанной папки. 
        add_action('plugins_loaded', array('MPG_Helper', 'mpg_set_language_folder_path'));

        // Register additional (weekly) interval for cron because WP hasn't weekly period
        add_filter('cron_schedules', array('MPG_Helper', 'mpg_cron_weekly'));
        // Register additional (monthly) interval for cron because WP hasn't monthly period
        add_filter('cron_schedules', array('MPG_Helper', 'mpg_cron_monthly'));

        // Создаем хук, который будет вызывать функция wp_schedule_event, и wp_schedule_single_event, в момент 
        // когда наступает время скачки и развертывания файла по расписанию
        add_action('mpg_schedule_execution', ['MPG_ProjectController', 'mpg_scheduled_cron_handler'], 10, 5);


        // Remove cron task when user deactivate plugin
        register_deactivation_hook($mpg_index_file,  array('MPG_Helper', 'mpg_set_deactivation_option'));

        // Создаем таблицу для проектов (если ее еще нет) при активации хука.
        register_activation_hook($mpg_index_file,  array('MPG_Helper', 'mpg_activation_events'));

        // Include styles and scripts in MGP plugin pages only
        add_action('admin_enqueue_scripts', array('MPG_Helper', 'mpg_admin_assets_enqueue'));

        // https://stackoverflow.com/questions/58931144/enqueue-javascript-with-type-module
        add_filter('script_loader_tag', array('MPG_Helper', 'mpg_add_type_attribute'), 10, 3);

        // Other
        add_action('wp_ajax_mpg_get_permalink_structure', ['MPG_ProjectController', 'mpg_get_permalink_structure']);
        add_action('wp_ajax_mpg_change_permalink_structure', ['MPG_ProjectController', 'mpg_change_permalink_structure']);

        add_action('admin_head', ['MPG_Helper', 'mpg_header_code_container']);

        add_action('admin_init', function(){

            $user_id = wp_get_current_user()->ID;

            if (time() -  MPG_Constant::MPG_WEEK_IN_SECONDS > (int) get_user_meta($user_id, 'mpg_next_schedule_review_notice_time') ) {
                add_action('admin_notices', ['MPG_Helper', 'mpg_ask_to_leave_review_handler']);
            }
    
        });

        add_action('admin_init', ['MPG_Helper', 'mpg_review_later_handler']);


    }

    public static function init_ajax()
    {

        // Dataset library
        add_action('wp_ajax_mpg_deploy_dataset', ['MPG_DatasetController', 'mpg_deploy']);

        // Main tab
        add_action('wp_ajax_mpg_get_posts_by_custom_type', array('MPG_ProjectModel', 'mpg_get_posts_by_custom_type'));

        add_action('wp_ajax_mpg_upload_file', array('MPG_ProjectModel', 'mpg_upload_file'));

        add_action('wp_ajax_mpg_upsert_project_main', ['MPG_ProjectController', 'mpg_upsert_project_main']);
        add_action('wp_ajax_mpg_upsert_project_source_block', ['MPG_ProjectController', 'mpg_upsert_project_source_block']);

        add_action('wp_ajax_mpg_upsert_project_url_block', ['MPG_ProjectController', 'mpg_upsert_project_url_block']);

        add_action('wp_ajax_mpg_get_data_for_preview', ['MPG_DatasetController', 'mpg_get_data_for_preview']);

        add_action('wp_ajax_mpg_preview_all_urls', ['MPG_DatasetController', 'mpg_preview_all_urls']);

        add_action('wp_ajax_mpg_get_all_projects', ['MPG_ProjectModel', 'mpg_get_all']);

        add_action('wp_ajax_mpg_get_project', ['MPG_ProjectController', 'mpg_get_project']);

        add_action('wp_ajax_mpg_download_file_by_url', ['MPG_DatasetController', 'mpg_download_file_by_link']);

        add_action('wp_ajax_mpg_get_unique_rows_in_column', ['MPG_DatasetController', 'mpg_get_unique_rows_in_column']);

        add_action('wp_ajax_mpg_delete_project', ['MPG_ProjectController', 'mpg_delete_project']);

        add_action('wp_ajax_mpg_unschedule_cron_task', ['MPG_ProjectController', 'mpg_unschedule_cron_task']);


        // Shortcodes tab
        add_action('wp_ajax_mpg_shortcode', ['MPG_CoreController', 'mpg_shortcode_ajax']);

        //Sitemap tab
        add_action('wp_ajax_mpg_generate_sitemap', ['MPG_ProjectController', 'mpg_generate_sitemap']);
        add_action('wp_ajax_mpg_check_is_sitemap_name_is_uniq', ['MPG_ProjectController', 'mpg_check_is_sitemap_name_is_uniq']);


        // Spintax tab
        add_action('wp_ajax_mpg_generate_spintax', ['MPG_SpintaxController', 'mpg_generate_spintax']);

        add_action('wp_ajax_mpg_flush_spintax_cache', ['MPG_SpintaxController', 'mpg_flush_spintax_cache']);


        // Cache tab
        add_action('wp_ajax_mpg_enable_cache', ['MPG_CacheController', 'mpg_enable_cache']);

        add_action('wp_ajax_mpg_disable_cache', ['MPG_CacheController', 'mpg_disable_cache']);

        add_action('wp_ajax_mpg_flush_cache', ['MPG_CacheController', 'mpg_flush_cache']);

        add_action('wp_ajax_mpg_cache_statistic', ['MPG_CacheController', 'mpg_cache_statistic']);

        // Работает для постов и страниц
        add_action('save_post', ['MPG_CacheController', 'mpg_flush_cache_on_template_update'], 10, 3);

        // Logs tab

        add_action('wp_ajax_mpg_get_log_by_project_id', ['MPG_LogsController', 'mpg_get_log_by_project_id']);

        add_action('wp_ajax_mpg_clear_log_by_project_id', ['MPG_LogsController', 'mpg_clear_log_by_project_id']);
    }

    public static function init_replacement()
    {
        // отвечает за замену {{шорткодов}} в тексте (например в теле поста, или заголовке)

        if (defined('ELEMENTOR_PRO_VERSION') && defined('MPG_EXPERIMENTAL_FEATURES') && MPG_EXPERIMENTAL_FEATURES === true) {
            add_action('pre_handle_404', ['MPG_CoreController', 'mpg_view_multipages_elementor'], 1, 2);
        } else {
            add_action('template_redirect', ['MPG_CoreController', 'mpg_view_multipages_standard']);
        }


        // отвечает за замену {{шорткодов}} в шорткоде wp и where. Например так [mpg where="" project-id=""] {{mpg_some}} [/mpg]
        add_shortcode('mpg', ['MPG_CoreController', 'mpg_shortcode']);

        add_shortcode('mpg_match', ['MPG_CoreController', 'mpg_match']);
        // Отвечает за Spintax функционал
        add_shortcode('mpg_spintax', ['MPG_SpintaxController', 'mpg_spintax_shortcode']);
    }
}
