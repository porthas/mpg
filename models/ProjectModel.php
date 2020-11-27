<?php

if (!defined('ABSPATH')) exit;

require_once(realpath(__DIR__) . '/../helpers/Constant.php');

class MPG_ProjectModel
{

    public static function mpg_create_database_tables($blog_index)
    {
        try {

            global $wpdb;

            if (is_int($blog_index)) {
                $table_prefix = $wpdb->base_prefix . $blog_index . '_';
            } else {
                $table_prefix = $wpdb->base_prefix;
            }

            $mpg_projects_table = $table_prefix . MPG_Constant::MPG_PROJECTS_TABLE;
            $mpg_spintax_table = $table_prefix . MPG_Constant::MPG_SPINTAX_TABLE;
            $mpg_cache_table = $table_prefix . MPG_Constant::MPG_CACHE_TABLE;
            $mpg_logs_table = $table_prefix . MPG_Constant::MPG_LOGS_TABLE;

            require_once(ABSPATH . '/wp-admin/includes/upgrade.php');

            #Check to see if the table exists already, if not, then create it
            if ($wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$mpg_projects_table'") != $mpg_projects_table) {

                $sql = "CREATE TABLE `" . $mpg_projects_table . "` ( ";

                $sql .= " `id` int(128) NOT NULL AUTO_INCREMENT, ";

                $sql .= " `name` varchar(200) NOT NULL DEFAULT 'New Template', ";
                $sql .= " `entity_type` varchar(50) DEFAULT NULL, ";
                $sql .= " `template_id` int(10) DEFAULT NULL, ";
                $sql .= " `exclude_in_robots` BOOLEAN DEFAULT FALSE,";

                $sql .= " `source_type` text DEFAULT NULL, ";
                $sql .= " `source_path` text DEFAULT NULL,";
                $sql .= " `worksheet_id` int(20) DEFAULT NULL, ";
                $sql .= " `original_file_url`  varchar(250) DEFAULT NULL,";

                $sql .= " `headers` text DEFAULT NULL, ";
                $sql .= " `url_structure` text DEFAULT NULL, ";
                $sql .= " `urls_array` MEDIUMTEXT DEFAULT NULL, ";
                $sql .= " `space_replacer` varchar(10) NOT NULL DEFAULT '-', ";

                $sql .= " `sitemap_url` varchar(255) DEFAULT NULL, ";
                $sql .= " `sitemap_filename` varchar(255) DEFAULT NULL, ";
                $sql .= " `sitemap_max_url` int(10) DEFAULT NULL, ";
                $sql .= " `sitemap_update_frequency` varchar(200) DEFAULT NULL, ";
                $sql .= " `sitemap_add_to_robots` BOOLEAN DEFAULT NULL, ";

                $sql .= " `schedule_source_link` text DEFAULT NULL, ";
                $sql .= " `schedule_periodicity` varchar(255) DEFAULT NULL, ";
                $sql .= " `schedule_notificate_about` varchar(255) DEFAULT NULL, ";
                $sql .= " `schedule_notification_email` varchar(255) DEFAULT NULL, ";

                $sql .= " `cache_type` varchar(255) NOT NULL DEFAULT 'disk', ";

                $sql .= " `created_at` int(20) DEFAULT NULL, ";
                $sql .= " `updated_at` int(20) DEFAULT NULL, ";

                $sql .= "  PRIMARY KEY (`id`) ";
                $sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ; ";

                dbDelta($sql);
                $sql = null;
            }

            if ($wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$mpg_spintax_table'") != $mpg_spintax_table) {


                $sql  = "CREATE TABLE  `" . $mpg_spintax_table . "` ( ";
                $sql .= "`id` INT(10) NOT NULL AUTO_INCREMENT , ";
                $sql .= "`project_id` INT(10) NULL ,";
                $sql .= "`url` TEXT NOT NULL ,";
                $sql .= "`spintax_string` TEXT NULL ,";
                $sql .= "PRIMARY KEY (`id`), INDEX `url` (`url`(100))) ENGINE = MyISAM DEFAULT CHARSET=utf8mb4;";

                dbDelta($sql);
            }

            $is_block_id_column_exist = $wpdb->get_results("SELECT `block_id` FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '" . $mpg_spintax_table . "' AND column_name = 'block_id'");

            if (empty($is_block_id_column_exist)) {
                $wpdb->query("ALTER TABLE `" . $mpg_spintax_table . "` ADD `block_id` varchar(255) NOT NULL default '1' AFTER `project_id`");
            }


            if ($wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$mpg_cache_table'") != $mpg_cache_table) {

                $sql  = "CREATE TABLE  `" . $mpg_cache_table . "` ( ";
                $sql .= "`id` INT(10) NOT NULL AUTO_INCREMENT , ";
                $sql .= "`project_id` INT(10) NOT NULL,";
                $sql .= "`url` TEXT NOT NULL ,";
                $sql .= "`cached_string` mediumtext NOT NULL,";
                $sql .= "PRIMARY KEY (`id`), INDEX `url` (`url`(500))) ENGINE = MyISAM DEFAULT CHARSET=utf8mb4;";

                dbDelta($sql);
            }


            if ($wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$mpg_logs_table'") != $mpg_logs_table) {

                $sql  = "CREATE TABLE  `" . $mpg_logs_table . "` ( ";
                $sql .= "`project_id` INT(10) NOT NULL, ";
                $sql .= "`level` varchar(20) NOT NULL,";
                $sql .= "`url`  varchar(250) DEFAULT NULL, ";
                $sql .= "`message` text NOT NULL, ";
                $sql .= "`datetime` date NOT NULL, ";
                $sql .= "ENGINE = MyISAM DEFAULT CHARSET=utf8mb4;";

                dbDelta($sql);
            }

            $is_cache_column_exist = $wpdb->get_results("SELECT `cache_type` FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '" . $mpg_projects_table . "' AND column_name = 'cache_type'");

            if (empty($is_cache_column_exist)) {
                $wpdb->query("ALTER TABLE `" . $mpg_projects_table . "` ADD `cache_type` varchar(255) NOT NULL default 'none' AFTER `schedule_notification_email`");
            }
        } catch (Exception $e) {

            echo $e->getMessage();
        }
    }

    public static function mpg_create_base_carcass($project_name, $entity_type, $template_id, $exclude_in_robots = false)
    {
        global  $wpdb;

        $current_time_in_unix = time();
        $wpdb->insert($wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE, array(

            'name' => $project_name,
            'entity_type' => $entity_type,
            'template_id' => $template_id,
            'exclude_in_robots' => $exclude_in_robots,
            'created_at' => $current_time_in_unix,
            'updated_at' => $current_time_in_unix
        ));

        return $wpdb->insert_id;
    }


    // Возвращает массив названий и типов сущностей зарегистрированіх в WordPress
    public static function mpg_get_custom_types()
    {
        $storage = [];
        $args = array('public' => true);
        $output = 'objects'; // names or objects, note names is the default

        foreach (get_post_types($args, $output) as $post_type) {
            if ($post_type->name === 'attachment') {
                continue;
            }
            array_push($storage, array('name' => $post_type->name, 'label' => $post_type->label));
        }

        return $storage;
    }

    public static function mpg_get_posts_by_custom_type()
    {

        try {
            $custom_type_name = sanitize_text_field($_POST['custom_type_name']);

            $query_object = new WP_Query(
                array(
                    'post_type' => $custom_type_name,
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                )
            );

            // Свойство posts есть у всех типов, даж если єто page или какой-то кастом. тип.
            if (!$query_object->posts) {
                echo json_encode(array('success' => true, 'data' => []));
                wp_die();
            }

            $storage = [];
            $front_page_id = get_option('page_on_front');

            foreach ($query_object->posts as $post) {

                $entity = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'is_home' => false
                );

                if ($custom_type_name === 'page') {
                    if ($post->ID == $front_page_id) {
                        $entity['is_home'] = true;
                    }
                }

                array_push($storage, $entity);
            }

            echo json_encode(array('success' => true, 'data' => $storage));
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        wp_die();
    }

    public static function mpg_upload_file()
    {
        try {

            if (isset($_FILES['file'])) {

                $project_id = $_POST['projectId'];

                $filename      = $_FILES['file']['name'];
                $temp_filename = $_FILES['file']['tmp_name'];

                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                // send data to Analytics
                $tid = 'UA-136971726-2';
                $cid = $_COOKIE['_ga'] ? explode('.', $_COOKIE['_ga'])[2] . '.' . explode('.', $_COOKIE['_ga'])[3] : rand(1000000000, 2147483647) . '.' . time();

                $label = 'Extension: ' . $ext . ' Type: file';
                $params_string = 'v=1&t=event&tid=' . $tid . '&cid=' . $cid . '&el=' . $label . '&ec=' . 'Upload source file' . '&ea=' . $label;

                MPG_Helper::mpg_send_data_to_analytics($params_string);

                if (!in_array($ext, ['csv', 'xls', 'xlsx', 'ods'])) {
                    throw __('Unsupported file extension', 'mpg');
                }

                $destination = realpath(__DIR__ . '/../temp/') . '/unlinked_file.' . $ext;

                $move = move_uploaded_file($temp_filename, $destination);

                if ($move) {

                    MPG_ProjectModel::mpg_update_project_by_id($project_id, ['original_file_url' => $filename]);

                    echo json_encode(['success' => true, 'data' => ['path' => $destination, 'original_file_url' => $filename]]);
                    wp_die();
                } else {
                    throw $_FILES["file"]["error"];
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            wp_die();
        }
    }


    public static function mpg_generate_urls_from_dataset($dataset_path, $url_structure, $space_replacer)
    {

        $ext = MPG_Helper::mpg_get_extension_by_path($dataset_path);

        $reader = MPG_Helper::mpg_get_spout_reader_by_extension($ext);

        $reader->open($dataset_path);

        $dataset_array = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                if ($row[0] !== NULL) {
                    $dataset_array[] = $row;
                }
            }
            break; // читаем только первую книгу
        }

        $reader->close();

        // 1. Берем первый ряд, тоесть тот что содержит заголовки
        $headers = $dataset_array[0];

        $shortcodes = [];
        foreach ($headers as $raw_header) {
            // В хедерах всегда пробелы заменяем на _, а в самих URL'ах - уже на то что выбрал пользователь ($space_replacer)
            $header = str_replace(' ', '_', $raw_header);

            if (strpos($header, 'mpg_') === 0) {
                $shortcodes[] = '{{' . strtolower($header) . '}}';
            } else {
                $shortcodes[] = '{{mpg_' . strtolower($header) . '}}';
            }
        }

        $re = '/{{(.*?)}}/';
        // 2. Проходимся по ним циклом, приводим в вид шорткода
        // "my Custom Text" => {{mpg_my_custom_text}}
        $url_structure = str_replace(' ', $space_replacer, $url_structure);
        preg_match_all($re, $url_structure, $matches, PREG_SET_ORDER, 0);

        // Тут будут номера столбцов, которым соответсвуюш шорткоды

        $indexes = [];
        foreach ($matches as $match) {
            // Находим номер столбца по шорткоду.
            $index = array_search($match[0], $shortcodes);
            if (is_int($index)) {
                $indexes[] = $index;
            }
        }

        $urls_array = [];

        if (!empty($indexes)) {

            // Всего столбцов в датасете может быть 10, и в $shortcodes будет 10 элементов,
            // но для str_replace() первый и второй массив для замены должен содержать одинаковое колличество элементов
            // Поскольку по индексам из ряда было получено, ну, скажем 3 элемента (url состоит из 3-х пилов), то и шорткодов должно быть 3
            $needed_shortcodes = [];
            foreach ($indexes as $column_number) {
                $needed_shortcodes[] = $shortcodes[$column_number];
            }

            // Удалим из массива первый элемент (заголовки)
            array_shift($dataset_array);

            $is_pro = mpg_app()->is_premium();
            foreach ($dataset_array  as $iteration => $row) {

                $line = [];
                foreach ($indexes as $index) {

                    $ceil_value = (string) $row[$index]; // (string) - для случаев если там будет null или false
                    $line[] = self::mpg_processing_special_chars($ceil_value, $space_replacer);
                }

                // Заменяем шорткоды на реальные значения из колонок
                $urls_array[] = '/' . str_replace($needed_shortcodes, $line, $url_structure) . '/';

                // Если пользователь не Pro, и у него в файле > 49 рядов, то генерируем урлы только на первых 49.
                if (!$is_pro && $iteration > 49) {
                    break;
                }
            }
        }

        return $urls_array;
    }


    public static function mpg_get_project_by_id($project_id)
    {

        global $wpdb;

        try {

            $project = wp_cache_get('project_id_' . $project_id, 'mpg');

            if (!$project) {

                $project = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}" .  MPG_Constant::MPG_PROJECTS_TABLE . " WHERE id=%d", $project_id)
                );

                wp_cache_add('project_id_' . $project_id, $project, 'mpg');
            }

            return count($project) ? $project : null;
        } catch (Exception $e) {
            throw new Exception(__('Can\'t getproject by id. Details:', 'mpg') . $e->getMessage());
        }
    }


    public static function mpg_update_project_by_id($project_id, $fields_array)
    {

        global $wpdb;

        try {

            $fields_array['updated_at'] = time();


            $wpdb->update($wpdb->prefix .  MPG_Constant::MPG_PROJECTS_TABLE, $fields_array, ['id' => $project_id]);

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            return true;
        } catch (Exception $e) {
            throw new Exception(__('Can\'t update project by ID. Details:', 'mpg') . $e->getMessage());
        }
    }


    public static function mpg_get_all()
    {

        try {
            global $wpdb;

            $projects = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE);

            echo json_encode([
                'success' => true,
                'data' => count($projects) ? $projects : null
            ]);
        } catch (Exception $e) {
            throw new Exception(__('Can\'t get all projects Details:', 'mpg') . $e->getMessage());
        }

        wp_die();
    }

    public static function deleteProjectFromDb($project_id)
    {

        try {
            global $wpdb;

            //  It returns the number of rows updated, or false on error.
            return $wpdb->delete($wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE, ['id' => $project_id], ['%d']);
        } catch (Exception $e) {
            throw new Exception(__('Can\'t delete project. Details:', 'mpg') . $e->getMessage());
        }
    }

    public static function deleteFileByPath($path)
    {

        try {

            if (file_exists($path)) {

                if (!unlink($path)) {
                    throw new Exception(__('Can\'t delete file.', 'mpg'));
                }
            }

            return true;
        } catch (Exception $e) {
            throw new Exception(__('Details: ' . $e->getMessage()));
        }
    }

    public static function mpg_copy_dataset_file($source_path)
    {

        $ext = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));

        $destination = realpath(__DIR__ . '/../../../mpg-uploads/') . '/' . rand(1000000, 9999999) . '.' . $ext;

        copy($source_path, $destination);

        return $destination;
    }

    public static function mpg_remove_cron_task_by_project_id($project_id, $project)
    {

        try {

            if ($project[0]->schedule_source_link && $project[0]->schedule_notificate_about && $project[0]->schedule_periodicity && $project[0]->schedule_notification_email) {

                $cron_arguments = [
                    (int) $project_id,
                    $project[0]->schedule_source_link,
                    $project[0]->schedule_notificate_about,
                    $project[0]->schedule_periodicity,
                    $project[0]->schedule_notification_email
                ];

                wp_clear_scheduled_hook('mpg_schedule_execution', $cron_arguments);

                MPG_ProjectModel::mpg_update_project_by_id($project_id, [
                    'schedule_periodicity' => null,
                    'schedule_source_link' => null,
                    'schedule_notificate_about' => null,
                    'schedule_notification_email' => null
                ]);


                return true;
            } else {
                throw new Exception(__('Some of needed values is missing, please, recreate task.', 'mpg'));
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function mpg_processing_robots_txt($exclude_in_robots, $template_id)
    {
        $path = ABSPATH . '/robots.txt';
        $handle = fopen($path, 'r');

        // Add each line to an array
        if ($handle) {


            $template_entity_url = get_permalink($template_id);
            $robots_string = explode("\n", fread($handle, 2048));

            // Надо добавить строку-исключение. Смотрим, нет ли там ее уже (ну а вдруг)
            if ($exclude_in_robots) {

                // То есть той строки которую хотим добавить - еще нет. Добавляем.
                if (!in_array('Disallow: ' . $template_entity_url, $robots_string)) {
                    $robots_string[] = 'Disallow: ' . $template_entity_url;
                }

                // Надо удалить строку из robots.
            } else {
                // Если она там есть - удаляем, иначе нет смысла что-либо делать
                $index = array_search('Disallow: ' . $template_entity_url, $robots_string);

                if ($index !== false) {
                    unset($robots_string[$index]);
                }
            }

            $file_content = implode("\n", $robots_string);

            file_put_contents($path, $file_content);
        }
    }

    public static function mpg_remove_sitemap_from_robots($sitemap_url)
    {
        $handle = fopen(ABSPATH . '/robots.txt', 'r');

        if ($handle) {

            $robots_string = explode("\n", fread($handle, 2048));

            $index = array_search('Sitemap: ' . $sitemap_url, $robots_string);

            if ($index !== false) {
                unset($robots_string[$index]);
            }
            $file_content = implode("\n", $robots_string);

            file_put_contents(ABSPATH . '/robots.txt', $file_content);
        }
    }

    public static function mpg_processing_special_chars($ceil_value, $space_replacer)
    {

        //Обрезаем слеши только вначале и в конце строки.
        $start_end_slashes_trimed = ltrim(rtrim(strtolower($ceil_value), '/'), '/');
        // Перед удалением всех спецсимволов - заменяем пробел на строку, иначе пробелы будут удалены регуляркой ниже.
        $escaped_spaces = preg_replace(
            ['/\s+/u', '/\//', '/\./', '/\-/', '/\_/', '/\~/'],
            ['mpgspaceholder', 'mpgslashholder', 'mpgdotholder', 'mpgdashholder', 'mpglodashholder', 'mpgtildaholder'],
            $start_end_slashes_trimed
        );

        // Заменяем все спец. символы, типа ' & * @ # $ % в 
        $special_chars_trimmed =  preg_replace('/\W/mu', '', $escaped_spaces);

        // То что раньше было пробелом - заменяем на space_replacer
        $back_to_allowed_chars = str_replace(
            ['mpgspaceholder', 'mpgslashholder', 'mpgdotholder', 'mpgdashholder', 'mpglodashholder', 'mpgtildaholder'],
            [$space_replacer, '/', '.', '-', '_', '~'],
            $special_chars_trimmed
        );

        return $back_to_allowed_chars;
    }


    public static function mpg_get_lastmod_date($project_id)
    {

        $project = MPG_ProjectModel::mpg_get_project_by_id($project_id);
        $template_id = isset($project[0]) ? $project[0]->template_id : null;

        if ($template_id) {
            return get_the_modified_date('Y-m-d', $template_id);
        }

        // Если нет (по какой-то причине) даты когда был изменен шаблон, то веернем сегодняшнюю дату.
        return date('Y-m-d');
    }
}
