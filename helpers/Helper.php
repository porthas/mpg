<?php

require_once(realpath(__DIR__ . '/Constant.php'));

if (!defined('ABSPATH')) exit;

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Common\Type;

class MPG_Helper
{

    // Подключает .mo файл перевода из указанной папки.
    public static function mpg_set_language_folder_path()
    {
        load_plugin_textdomain('mpg', false, dirname(plugin_basename(__DIR__)) . '/lang/');
    }

    // Register additional (monthly) interval for cron because WP hasn't weekly period
    public static function mpg_cron_monthly($schedules)
    {
        $schedules['monthly'] = array(
            'interval' => 60 * 60 * 24 * 30,
            'display' => __('Monthly', 'mpg')
        );

        return $schedules;
    }

    // Register additional (monthly) interval for cron because WP hasn't monthly period
    public static function mpg_cron_weekly($schedules)
    {
        $schedules['weekly'] = array(
            'interval' => 60 * 60 * 24 * 7,
            'display' => __('Weekly', 'mpg')
        );

        return $schedules;
    }


    public static function mpg_activation_events()
    {

        if (is_multisite()) {

            // Если это мультисайт, то для каждого мультисайта создаем в БД
            foreach (get_sites() as $site) {

                $blog_id = intval($site->blog_id);

                // Если индекс = 1, значит это главный сайт. Его файлы ложим в корень, а для дочерних - в подпапки.
                // Делаю так на случай того, если мультисйт переделают в обычный, чтобы остались работать пути для главного сайта
                // (который станет единственным)

                $blog_index = $blog_id === 1 ? '' : $blog_id;

                $uploads_folder_path = realpath(WP_PLUGIN_DIR .  '/..') . '/mpg-uploads/' . $blog_index;

                if (!file_exists($uploads_folder_path)) {
                    mkdir($uploads_folder_path);
                }

                $cache_folder_path = realpath(WP_PLUGIN_DIR .  '/..') . '/mpg-cache/' . $blog_index;

                if (!file_exists($cache_folder_path)) {
                    mkdir($cache_folder_path);
                }

                MPG_ProjectModel::mpg_create_database_tables($blog_index);
            }
        } else {

            if (!file_exists(realpath(WP_PLUGIN_DIR .  '/..') . '/mpg-uploads')) {
                mkdir(realpath(WP_PLUGIN_DIR .  '/..') . '/mpg-uploads');
            }

            if (!file_exists(realpath(WP_PLUGIN_DIR .  '/..') . '/mpg-cache')) {
                mkdir(realpath(WP_PLUGIN_DIR .  '/..') . '/mpg-cache');
            }


            MPG_ProjectModel::mpg_create_database_tables('');
        }
    }

    // Remove cron task when user deactivate plugin
    public static function mpg_set_deactivation_option()
    {
        wp_clear_scheduled_hook('schedule_execution');
    }


    public static function mpg_admin_assets_enqueue($hook_suffix)
    {
        $pages = [
            //'multiple-pages-generator_page_mpg-create-new',
            'toplevel_page_mpg-dataset-library',
            'mpg_page_mpg-project-builder',
        ];

        // Include styles and scripts in MGP plugin pages only

        if (in_array($hook_suffix, $pages)) {

            wp_enqueue_script('mpg_listFilter',                 plugins_url('frontend/libs/jquery.listfilter.min.js', __DIR__), array('jquery'));
            wp_enqueue_script('mpg_datatable_js',               '//cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js');
            wp_enqueue_script('mpg_bootstrap_js',               plugins_url('frontend/libs/bootstrap/bootstrap.min.js', __DIR__), array('jquery'));
            wp_enqueue_script('mpg_datetime_picker',            plugins_url('frontend/libs/datetimepicker/jquery.datetimepicker.full.min.js', __DIR__), array('jquery'));
            wp_enqueue_script('mpg_select2_js',                 plugins_url('frontend/libs/select2/select2.full.min.js', __DIR__), array('jquery'));
            wp_enqueue_script('mpg_toast_js',                   plugins_url('frontend/libs/toast/toast.js', __DIR__), array('jquery'));

            wp_enqueue_script('mpg_tippy_1_js',                 'https://unpkg.com/@popperjs/core@2', array('jquery'));
            wp_enqueue_script('mpg_tippy_2_js',                 'https://unpkg.com/tippy.js@6', array('jquery'));
            wp_enqueue_script('mpg_main_js',                    plugins_url('frontend/js/app.js', __DIR__), array('jquery'));

            wp_localize_script('mpg_main_js', 'backendData', [
                'baseUrl'           => self::mpg_get_base_url(false),
                'datasetLibraryUrl' => admin_url('admin.php?page=mpg-dataset-library'),
                'projectPage'       => admin_url('admin.php?page=mpg-project-builder'),
                'mpgAdminPageUrl'   => admin_url()
            ]);


            wp_enqueue_style('mpg_datatable',                   '//cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css');
            wp_enqueue_style('mpg_bootstrap_css',               plugins_url('frontend/libs/bootstrap/bootstrap.min.css', __DIR__));
            wp_enqueue_style('mpg_datetimepicker_css',          plugins_url('frontend/libs/datetimepicker/jquery.datetimepicker.full.min.css', __DIR__));
            wp_enqueue_style('mpg_toast_css',                   plugins_url('frontend/libs/toast/toast.css', __DIR__));
            wp_enqueue_style('mpg_select2_css',                 plugins_url('frontend/libs/select2/select2.min.css',   __DIR__));

            wp_enqueue_style('mpg_font_awesome_css',            plugins_url('frontend/css/font-awesome.css',   __DIR__));

            wp_enqueue_style('mpg_main_css',                    plugins_url('frontend/css/style.css', __DIR__));
        }
    }

    public static function mpg_add_type_attribute($tag, $handle, $src)
    {
        // if not your script, do nothing and return original $tag
        if ('mpg_js' !== $handle) {
            return $tag;
        }
        // change the script tag by adding type="module" and return it.
        $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
        return $tag;
    }

    public static function mpg_get_site_url()
    {

        global $blog_id;

        if (is_multisite()) {
            $current_blog_details = get_blog_details(array('blog_id' => $blog_id));
            $siteName = $current_blog_details->path === '/' ? 'main' : str_replace('/', '', $current_blog_details->path);
        } else {
            $siteName = str_replace(self::mpg_get_domain(), '', trim(home_url('/', 'relative'), '/'));
        }

        return trim($siteName);
    }

    // Return site URL
    public static function mpg_get_domain()
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    }


    public static function mpg_get_base_url($for_main_site)
    {
        $blog_id = get_current_blog_id();

        if (is_multisite()) {
            $sites =  get_sites();

            $base_url = '';

            if ($for_main_site) {
                $base_url = self::mpg_get_domain() . $sites[0]->path;
            } else {

                $site = array_filter($sites, function ($site) use ($blog_id) {
                    return (int) $site->blog_id === $blog_id;
                });

                if (!function_exists('array_key_first')) {
                    function array_key_first(array $arr)
                    {
                        foreach ($arr as $key => $unused) {
                            return $key;
                        }
                        return NULL;
                    }
                }

                $index = array_key_first($site);
                $base_url = self::mpg_get_domain() . $site[$index]->path;
            }
        } else {
            $base_url = self::mpg_get_domain() . '/' . self::mpg_get_site_url();
        }

        if (substr($base_url, -1) === '/') {
            // Обрежем слеш в конце, если есть
            $base_url = substr($base_url, 0, -1);
        }

        return $base_url;
    }

    // Return the path of URL
    public static function mpg_get_request_uri()
    {
        global $wp;
        $full_url_path = home_url($wp->request);
        $current_url = urldecode(str_ireplace(get_site_url(), "", $full_url_path) . "/");

        return strtolower($current_url);
    }

    public static function mpg_get_extension_by_path($path)
    {

        $regexp = '/format=(xlsx|ods|csv)/s';

        preg_match_all($regexp, $path, $matches, PREG_SET_ORDER, 0);

        // Если это ссылка на Gooole Drive ( шареный документ, то ок), а если нет - то берем из конца строки,
        // то что после последней точки
        if ($matches) {
            return $matches[0][1];
        } else {

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            // Если в расширении есть точка - обрезаем,
            return strpos($ext, '.') === 0 ? ltrim($ext, $ext[0]) : $ext;
        }
    }

    /**
     * Convert a multi-dimensional array into a single-dimensional array.
     * @author Sean Cannon, LitmusBox.com | seanc@litmusbox.com
     * @param  array $array The multi-dimensional array.
     * @return array
     */
    public static function array_flatten($array)
    {
        if (!is_array($array)) {
            return false;
        }
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, self::array_flatten($value));
            } else {
                $result = array_merge($result, array($key => $value));
            }
        }
        return $result;
    }

    public static function mpg_header_code_container()
    {

        $code = '';

        // Типа мини-костыль чтобы скрыть пустой элемент меню
        $code .= '<style>#toplevel_page_mpg-dataset-library .wp-submenu a[href="admin.php?page=mpg-project-builder"] {display: none;}</style>';

        // <!-- Google Tag Manager -->
        $code .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-NRDKGTJ');</script>";

        echo $code;
    }



    public static function mpg_send_data_to_analytics($params_string)
    {
        try {

            $url = 'www.google-analytics.com/collect';
            $ch = curl_init();
            //set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params_string);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            //So that curl_exec returns the contents of the cURL; rather than echoing it
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
        } catch (Exception $e) {
            // Ну, ошибка так ошибка. Позже это надо будет писать в лог.
            // Но это не должно останавливать 
        }
    }


    public static function mpg_get_direct_csv_link($raw_link, $worksheet_id = null)
    {

        // false = substring was not found in target string
        if (strpos($raw_link, 'docs.google.com') !== false or strpos($raw_link, 'drive.google.com') !== false) {

            $documentId = str_replace([
                'https://docs.google.com/spreadsheets/d/',
                'https://drive.google.com/file/d/',
                '/view?usp=sharing',
                '/edit?usp=sharing'
            ], ['', '', '', ''], $raw_link);

            $final_url = 'https://docs.google.com/spreadsheets/d/' . $documentId . '/export?format=csv&id=' . $documentId;

            if ($worksheet_id) {
                $final_url .=  '&gid=' . $worksheet_id;
            }
            return $final_url;
        }

        return $raw_link;
    }

    public static function mpg_get_spout_reader_by_extension($ext)
    {

        if ($ext === 'csv') {
            $reader = ReaderFactory::create(Type::CSV); // for CSV files
        } else if ($ext === 'xlsx') {
            $reader = ReaderFactory::create(Type::XLSX); // for XLSX files
        } elseif ($ext === 'ods') {
            $reader = ReaderFactory::create(Type::ODS); // for ODS files
        } else {
            throw new Exception(__('Unsupported file extension:' . '' . $ext, 'mpg'));
        }

        return $reader;
    }

    public static function mpg_ask_to_leave_review_handler()
    {
        global $current_user;
        $user_id = $current_user->ID;
        /* Check that the user hasn't already clicked to ignore the message */
        if (!get_user_meta($user_id, 'mpg_next_schedule_review_notice_time')) { ?>
            <div class="notice notice-success is-dismissible" style="padding-bottom: 1rem;">
                <p><?php _e('Thank you for using MPG. It would help us a great deal if you could give us your feedback on WP directory. We are hoping we earned your 5-stars! 😉', 'mpg'); ?></p>

                <div style="display: flex; flex-direction: column;">
                    <a data-rate-action="do-rate" href="<?php echo admin_url(); ?>index.php?mpg_review=do" style="font-size: 16px;">
                        <img src="<?php echo plugin_dir_url(__FILE__) . '/../../frontend/images/thumbs-up.svg'; ?>" style="margin-right: 10px; width: 12px;  position: relative; top: 2px;"><?php _e('Sure!', 'mpg') ?>
                    </a>
                    <a data-rate-action="later" href="<?php echo admin_url(); ?>index.php?mpg_review=later">
                        <img src="<?php echo plugin_dir_url(__FILE__) . '/../../frontend/images/later.svg'; ?>" style="margin-right: 10px; width: 12px; position: relative; top: 2px;"><?php _e('No, maybe later', 'mpg') ?>
                    </a>
                </div>
            </div>
    <?php }
    }


    public static function  mpg_review_later_handler()
    {

        $user_id = wp_get_current_user()->ID;

        if ($user_id && isset($_GET['mpg_review'])) {

            if ($_GET['mpg_review'] === 'later') {

                add_user_meta($user_id, 'mpg_next_schedule_review_notice_time', time() + MPG_Constant::MPG_TWO_MONTH_IN_SECONDS, true);
            } elseif ($_GET['mpg_review'] === 'do') {

                add_user_meta($user_id, 'mpg_next_schedule_review_notice_time', time() + MPG_Constant::MPG_SIX_MONTH_IN_SECONDS, true);
                wp_redirect('https://wordpress.org/support/plugin/multiple-pages-generator-by-porthas/reviews/#new-post');
            }
        }
    }


    public static function mpg_get_dataset_array($dataset_path, $project_id)
    {

        $ext = MPG_Helper::mpg_get_extension_by_path($dataset_path);

        $reader = MPG_Helper::mpg_get_spout_reader_by_extension($ext);
        $reader->open($dataset_path);

        $dataset_array = wp_cache_get('dataset_array_' . $project_id, 'mpg');

        if (!$dataset_array) {
            $dataset_array = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if ($row[0] !== NULL) {
                        $dataset_array[] = $row;
                    }
                }
            }

            wp_cache_add('dataset_array_' . $project_id, $dataset_array, 'mpg');
        }

        return $dataset_array;
    }
}
