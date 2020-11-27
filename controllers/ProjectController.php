<?php

require_once(realpath(__DIR__) . '/../controllers/DatasetController.php');

require_once(realpath(__DIR__ . '/../views/project-builder/index.php'));

require_once(realpath(__DIR__) . '/../models/ProjectModel.php');
require_once(realpath(__DIR__) . '/../models/SitemapModel.php');
require_once(realpath(__DIR__) . '/../models/DatasetModel.php');


class MPG_ProjectController
{

    public static function builder()
    {
        // Сначала даем возможность пользователю выбрать тип сущности, с которой он хочет работать,
        // а уже потом, когда он выберет, ajax'ом подгрузим записи которые в нем есть,
        // чтобы не создавать зависимых списков

        $entities_array = MPG_ProjectModel::mpg_get_custom_types();

        MPG_ProjectBuilderView::render($entities_array);
    }


    public static function mpg_upsert_project_main()
    {

        // 1. человек заходит на проект, запонляет текстовые поля
        // 2. Загружает файл, идет запрос на сервак. ложим его в папку temp, даем имя - unlinked_file.ext
        // 3. Возаращаем путь и расширение на фронт, ставим в localstorage (state)
        // 4. Когда человек кликает save, создаем ему проект в БД, получаем project_id.
        // 5. Перемещаем пользовательский файл в /uploads/ и даем ему имя - project_id
        // 6. Обновляем запись в БД, а именно - source_path
        // 7. На фронт возвращаем project_id, а при получении, на фронте - очищаем source_path с localStorage

        try {

            if (isset($_POST['projectName']) && isset($_POST['entityType']) && isset($_POST['templateId'])) {

                $project_id   =      isset($_POST['projectId'])      ?        (int) $_POST['projectId'] : null;
                $project_name =      $_POST['projectName']           ?        sanitize_text_field($_POST['projectName']) :  __('New project', 'mpg');
                $entity_type  =      sanitize_text_field($_POST['entityType']);
                $template_id  =      (int) $_POST['templateId'];
                // Приводим строку к Boolean типу.
                if (isset($_POST['excludeInRobots'])) {
                    $exclude_in_robots = filter_var($_POST['excludeInRobots'], FILTER_VALIDATE_BOOLEAN);
                } else {
                    $exclude_in_robots = false;
                }


                MPG_ProjectModel::mpg_processing_robots_txt($exclude_in_robots, $template_id);

                // Если с фронта пришел project_id - значит это update, если null - значит создаем новый проект

                if ($project_id) {

                    $project = MPG_ProjectModel::mpg_get_project_by_id($project_id);
                    $current_template_id = $project ? $project[0]->template_id : null;
                    $cache_type = $project ? $project[0]->cache_type : null;

                    if ((int) $current_template_id !== $template_id && $cache_type) {
                        // Значит человек изменил шаблон. Надо сбросить кеш.
                        MPG_CacheController::mpg_flush_core($project_id, $cache_type);
                    }


                    $fields_array = [
                        'name' => $project_name,
                        'entity_type' => $entity_type,
                        'template_id' => $template_id,
                        'exclude_in_robots' => $exclude_in_robots
                    ];

                    MPG_ProjectModel::mpg_update_project_by_id($project_id, $fields_array);

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'projectId' => $project_id
                        ]
                    ]);
                } else {

                    // Ставим дефолтное название проекту, задаем created_at и updated_at время, и другие нужные данные

                    $project_id = MPG_ProjectModel::mpg_create_base_carcass($project_name, $entity_type, $template_id, $exclude_in_robots);


                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'projectId' => $project_id
                        ]
                    ]);
                }
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        wp_die();
    }


    public static function mpg_upsert_project_source_block()
    {

        try {

            $project_id =       isset($_POST['projectId']) ? (int) $_POST['projectId'] : null;
            $type =             isset($_POST['type']) ? sanitize_text_field($_POST['type']) : null;
            $folder_path =      isset($_POST['path']) ? sanitize_text_field($_POST['path']) : null;

            $ext = MPG_Helper::mpg_get_extension_by_path($folder_path);

            if (!$folder_path) {
                throw new Exception(__('Missing temprory file path', 'mpg'));
            }

            $new_path = realpath(__DIR__ . '/../../../mpg-uploads/') . '/' . $project_id . '.' . $ext;

            // Перемещаем файл в /../mpg-uploads/
            rename($folder_path, $new_path);

            // Суть  в том, что у нас датасеты называются как project_id.
            // Если человек загружал в один и тот же проект, скажем только .csv то старый будет перезаписыватся новым.
            // А если сначала .csv, потом .xls - то будет два файла с одинаковыми именами, но разными форматами. А это мусор.
            $files = glob(realpath(__DIR__ . '/../../../mpg-uploads/') . '/' . $project_id . '.*');
            foreach ($files as $project_file) {
                if ($project_file !== $new_path) {
                    unlink($project_file);
                }
            }

            $project = MPG_ProjectModel::mpg_get_project_by_id($project_id);
            $url_structure = $project[0]->url_structure;

            // Имея путь к файлу с данными, надо "нарезать" его на хедеры и взять Х строк данных для превью.
            $headers = MPG_DatasetController::get_headers($new_path);

            $rows = MPG_DatasetController::get_rows($new_path, 5);

            $fields_array = [
                'source_type' => $type,
                'source_path' => $new_path,
                'headers' => json_encode($headers)
            ];

            MPG_ProjectModel::mpg_update_project_by_id($project_id, $fields_array);

            echo json_encode([
                'success' => true,
                'data' => [
                    'headers'   => $headers,
                    'rows'      => $rows['rows'],
                    'totalRows' => $rows['total_rows'],
                    'projectId' => $project_id,
                    'path'      => $new_path, // В процессе сохранения проекта, мы перемещаем датасет с temp в uploads.
                    // Надо этот новый путь передать на фронт, чтобы с ним можно было работать в следующих вкладках
                    'is_trimmed' => $rows['is_trimmed'],
                    'url_structure' => $url_structure
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }



    // Работает с нижней кнопкой save
    public static function mpg_upsert_project_url_block()
    {
        try {

            $project_id =         isset($_POST['projectId']) ? (int) $_POST['projectId'] : null;
            $url_structure =      isset($_POST['urlStructure']) ? $_POST['urlStructure'] : null;
            $space_replacer =     isset($_POST['replacer']) ? sanitize_text_field(($_POST['replacer'])) : MPG_Constant::DEFAULT_SPACE_REPLACER;

            $direct_link =        isset($_POST['directLink']) ? esc_url_raw($_POST['directLink']) : null;
            $periodicity =        isset($_POST['periodicity']) ? sanitize_text_field($_POST['periodicity']) : null;

            $timezone =           isset($_POST['timezone']) ? sanitize_text_field($_POST['timezone']) : null;
            $fetch_date_time =    isset($_POST['fetchDateTime']) ? sanitize_text_field($_POST['fetchDateTime']) : null;

            $notificate_about =   isset($_POST['notificateAbout']) ? sanitize_text_field($_POST['notificateAbout']) : null;
            $notification_email = isset($_POST['notificationEmail']) ? sanitize_email($_POST['notificationEmail']) : null;

            $source_type =        isset($_POST['sourceType']) ? sanitize_text_field($_POST['sourceType']) : null;
            $worksheet_id =       isset($_POST['worksheetId']) ? (int) $_POST['worksheetId'] : null;

            if (!$project_id) {
                throw new Exception(__('Project ID is missing', 'mog'));
            }

            $fields_array = [
                'url_structure' => str_replace(' ', '_', $url_structure),
                'space_replacer' => $space_replacer
            ];

            if ($source_type) {
                $fields_array['source_type'] = $source_type;
            }

            if ($worksheet_id) {
                $fields_array['worksheet_id'] = $worksheet_id;
            }

            MPG_ProjectModel::mpg_update_project_by_id($project_id, $fields_array);

            // Имея загруженный dataset, заменитель пробелов и структуру URL'ов, можно собрать массив из url с реальными данными
            $project = MPG_ProjectModel::mpg_get_project_by_id($project_id);

            if (!$project[0]) {
                throw new Exception(__('Can\'t get project', 'mpg'));
            }

            $dataset_path = $project[0]->source_path;

            $urls_array = MPG_ProjectModel::mpg_generate_urls_from_dataset($dataset_path, $url_structure, $space_replacer);
            // JSON_UNESCAPED_UNICODE - делает так, чтобы символы турецкого, немецкого и т.п. алфавита не конвертировались в юникод, а вставлялись в базу как есть.
            $update_options_array = ['urls_array' => json_encode($urls_array, JSON_UNESCAPED_UNICODE)];


            // =============================  Schedule ==========================
            // С какими параметрами крон-задача ставится, с такими ее надо и отключать. Поэтому храним это в базе
            // Это список аргументов которые надо передеать в хук.


            // now - это для тех случаев, когда человке хочет применить файл сейчас. И ему не нужно заводить крон-таб



            if ($direct_link && $fetch_date_time && $periodicity !== 'now' && $notificate_about && $notification_email) {

                $datetime = DateTime::createFromFormat('Y/m/d H:i', $fetch_date_time, new DateTimeZone($timezone));
                $hook_execution_time = $datetime->getTimestamp();

                $data_for_hook = [$project_id, $direct_link, $notificate_about, $periodicity, $notification_email];

                if ($periodicity === 'once') {
                    // So, all expressions worked fine, and now we can schedule applying template.
                    if (!wp_next_scheduled('mpg_schedule_execution')) {

                        wp_schedule_single_event($hook_execution_time, 'mpg_schedule_execution', $data_for_hook);
                    }
                } elseif (in_array($periodicity, ['hourly', 'twicedaily', 'daily', 'weekly', 'monthly'])) {
                    if (!wp_next_scheduled('mpg_schedule_execution')) {

                        wp_schedule_event($hook_execution_time, $periodicity, 'mpg_schedule_execution', $data_for_hook);
                    }
                }

                $update_options_array = [
                    'schedule_source_link' => $direct_link,
                    'schedule_periodicity' => $periodicity,
                    'schedule_notificate_about' => $notificate_about,
                    'schedule_notification_email' => $notification_email
                ];
            }

            MPG_ProjectModel::mpg_update_project_by_id($project_id, $update_options_array);

            // При сохранении нового файла - скидать кеш
            MPG_CacheController::mpg_flush_core($project_id, $project[0]->cache_type);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }

    // чтобы получить объект из базы по определенному project_id.
    public static function mpg_get_project()
    {

        try {

            $project_id = isset($_POST['projectId']) ? $_POST['projectId'] : null;

            if (!$project_id) {
                throw new Exception(__('Missing project ID', 'mpg'));
            }

            $project = MPG_ProjectModel::mpg_get_project_by_id($project_id);

            if (!$project) {
                throw new Exception(__('Project not found', 'mpg'));
            }

            $response = (array) $project[0];

            if ($project[0]->schedule_periodicity && $project[0]->schedule_source_link && $project[0]->schedule_notificate_about) {

                $response['nextExecutionTimestamp'] = wp_next_scheduled('mpg_schedule_execution', [
                    (int) $project_id,
                    $project[0]->schedule_source_link,
                    $project[0]->schedule_notificate_about,
                    $project[0]->schedule_periodicity,
                    $project[0]->schedule_notification_email
                ]);
            }

            if ($project[0]->source_path) {

                $rows = MPG_DatasetController::get_rows($project[0]->source_path, 5);

                $response['rows'] = $rows['rows'];
                $response['totalRows'] = $rows['total_rows'];
                $response['is_trimmed'] = $rows['is_trimmed'];

                $response['spintax_cached_records_count'] = MPG_SpintaxController::get_cached_records_count($project_id);

                echo json_encode([
                    'success' => true,
                    'data' => $response
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => $response
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        wp_die();
    }

    public static function mpg_delete_project()
    {
        try {

            $project_id = isset($_POST['projectId']) ? $_POST['projectId'] : null;

            if (!$project_id) {
                throw new Exception(__('Project ID is missing', 'mpg'));
            }

            $project = (array) MPG_ProjectModel::mpg_get_project_by_id($project_id);

            if ($project[0]->source_path) {
                $dataset_path = $project[0]->source_path;
                // Удалим датасет проекта
                MPG_ProjectModel::deleteFileByPath($dataset_path);
            }

            if ($project[0]->sitemap_filename) {
                // Удалим карту сайта


                // Это удаляется главный файл (либо он единственный, либо ...-index).
                foreach ([
                    $_SERVER['DOCUMENT_ROOT'] . '/' . MPG_Helper::mpg_get_site_url() . '/' . $project[0]->sitemap_filename . '.xml',
                    $_SERVER['DOCUMENT_ROOT'] . '/' . MPG_Helper::mpg_get_site_url() . '/' . $project[0]->sitemap_filename . '-index.xml'
                ] as $path) {

                    if (file_exists($path)) {
                        unlink($path);
                    }
                }

                // Но если есть ...-index, то сделовательно, есть и дочерние файлы, которые тоже надо "подчистить"
                $name = str_replace('-index', '', $project[0]->sitemap_filename);

                foreach (glob($_SERVER['DOCUMENT_ROOT'] . '/' . MPG_Helper::mpg_get_site_url() . '/' . $name . '*.xml') as $path) {
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }

            // Удаляем крон-задачу, если есть
            if ($project[0]->schedule_source_link && $project[0]->schedule_notificate_about && $project[0]->schedule_periodicity && $project[0]->schedule_notification_email) {

                MPG_ProjectModel::mpg_remove_cron_task_by_project_id($project_id, $project);
            }

            if ($project[0]->exclude_in_robots) {
                // Удаляем ссылку на страницу-шаблон, если она есть.
                MPG_ProjectModel::mpg_processing_robots_txt(false, $project[0]->template_id);
            }

            if ($project[0]->sitemap_url) {
                // Удалим карту сайта из robots.txt
                MPG_ProjectModel::mpg_remove_sitemap_from_robots($project[0]->sitemap_url);
            }

            // Удалим проект с БД
            MPG_ProjectModel::deleteProjectFromDb($project_id);

            // Удаляем все строки для текущего проекта из БД (Spintax)
            MPG_SpintaxModel::flush_cache_by_project_id($project_id);

            // Удалим кеш для данного проекта
            if ($project[0]->cache_type !== 'none') {
                MPG_CacheController::mpg_flush_core($project_id, $project[0]->cache_type);
            }

            echo json_encode([
                'success' => true
            ]);
        } catch (Exception $e) {

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        wp_die();
    }

    // ============ Permalink structure ==============

    public static function mpg_get_permalink_structure()
    {
        try {

            echo json_encode([
                'success' => true,
                'data' => get_option('permalink_structure')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }



    public static function mpg_change_permalink_structure()
    {
        try {

            if (update_option('permalink_structure', '/%postname%/')) {
                echo json_encode([
                    'success' => true,
                    'data' => __('Permalink structure was changed to /postname/', 'mpg')
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => __('Permalink structure was not changed', 'mpg')
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }



    // ================== Sitemap   ==================

    public static function mpg_check_is_sitemap_name_is_uniq()
    {

        try {
            $filename = isset($_POST['filename']) ? esc_sql($_POST['filename']) : null;

            $sitemap_path = $_SERVER['DOCUMENT_ROOT'] . '/' . MPG_Helper::mpg_get_site_url() . '/' . $filename . '.xml';

            echo json_encode([
                'success' => true,
                'unique' => !is_file($sitemap_path)
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => __('Can\'t create sitemap, due to: ', 'mpg') . $e->getMessage()
            ]);
        }

        wp_die();
    }



    public static function mpg_generate_sitemap()
    {

        try {

            $project_id            = isset($_POST['projectId'])            ? (int) $_POST['projectId'] : null;
            $filename              = isset($_POST['filename'])             ? esc_sql($_POST['filename']) : null;
            $max_url               = isset($_POST['maxUrlPerFile'])        ? esc_sql($_POST['maxUrlPerFile']) : 50000;
            $update_freq           = isset($_POST['frequency'])            ? esc_sql($_POST['frequency']) : null;
            $add_to_robots         = isset($_POST['addToRobotsTxt'])       ? filter_var($_POST['addToRobotsTxt'], FILTER_VALIDATE_BOOLEAN) : false;
            $previous_sitemap_name = isset($_POST['previousSitemapName'])  ? esc_sql($_POST['previousSitemapName']) : null;

            MPG_ProjectModel::mpg_update_project_by_id($project_id, [
                'sitemap_filename' => $filename,
                'sitemap_max_url' => $max_url,
                'sitemap_update_frequency' => $update_freq,
                'sitemap_add_to_robots' => $add_to_robots
            ]);

            $project = MPG_ProjectModel::mpg_get_project_by_id($project_id);

            $raw_urls_list = isset($project[0]) ? $project[0]->urls_array : null;

            if (!$raw_urls_list) {
                throw new Exception(__('Project hasn\'t URLs for creating sitemap', 'mpg'));
            }

            $urls_list = json_decode($raw_urls_list, true);


            if (!empty($previous_sitemap_name)) {
                foreach ([
                    $_SERVER['DOCUMENT_ROOT'] . '/' . MPG_Helper::mpg_get_site_url() . '/' . $previous_sitemap_name . '.xml',
                    $_SERVER['DOCUMENT_ROOT'] . '/' . MPG_Helper::mpg_get_site_url() . '/' . $previous_sitemap_name . '-index.xml'
                ] as $main_file_path) {

                    if (file_exists($main_file_path)) {
                        // Это удаляется главный файл (либо он единственный, либо ...-index).
                        unlink($main_file_path);
                    }

                    // Но если есть ...-index, то сделовательно, есть и дочерние файлы, которые тоже надо "подчистить"
                    $name = str_replace('-index', '', $previous_sitemap_name);

                    foreach (glob($_SERVER['DOCUMENT_ROOT'] . '/' . MPG_Helper::mpg_get_site_url() . '/' . $name . '*.xml') as $path) {
                        if (file_exists($path)) {
                            unlink($path);
                        }
                    }

                    // Когда генерируем новый sitemap нужно в robots удалять старый
                    $sitemap_url = MPG_Helper::mpg_get_base_url(true) . '/' . $previous_sitemap_name . '.xml';
                    MPG_ProjectModel::mpg_remove_sitemap_from_robots($sitemap_url);
                }
            }

            MPG_SitemapGenerator::run($urls_list, $filename, $max_url, $update_freq, $add_to_robots, $project_id);

            if (count($urls_list) >= $max_url) {
                $sitemap_filename = $filename ? $filename . '-index.xml' : 'multipage-sitemap-index.xml';
            } else {
                $sitemap_filename = $filename ? $filename . '.xml' : 'multipage-sitemap.xml';
            }

            $sitemap_full_path = MPG_Helper::mpg_get_base_url(true) . '/' . $sitemap_filename;

            MPG_ProjectModel::mpg_update_project_by_id($project_id, ['sitemap_url' => $sitemap_full_path]);

            echo json_encode([
                'success' => true,
                'data' => $sitemap_full_path
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => __('Can\'t create sitemap, due to: ', 'mpg') . $e->getMessage()
            ]);
        }

        wp_die();
    }


    public static function mpg_scheduled_cron_handler($project_id, $link, $notificate_about, $periodicity, $notification_email)
    {

        try {

            $project = MPG_ProjectModel::mpg_get_project_by_id($project_id);


            if (!$project[0] or !$project[0]->source_path) {
                throw new Exception(__('Your project has not properly configured source file', 'mpg'));
            }

            $source_path = $project[0]->source_path;

            // Имея путь к файлу, мы можем его открыть и перезаписать содержимое.
            // Но сначала надо скачать файл (получить содержимое), который пользователь хочет применить
            $direct_link = MPG_Helper::mpg_get_direct_csv_link($link);

            MPG_DatasetModel::download_file($direct_link, $source_path);

            // Теперь, когда мы заменили файл с данными на тот, что пользователь указал по ссылке пользователь
            if ($notificate_about === 'every-time') {
                wp_mail(
                    $notification_email,
                    __('MPG schedule execution report: ok', 'mpg'),
                    __('Hi. <br>Scheduled task was completed successfully. File was deployed: ', 'mpg') . $direct_link
                );
            }

            // При срабатывании крон-задачи -  скидать кеш
            MPG_CacheController::mpg_flush_core($project_id, $project[0]->cache_type);
        } catch (Exception $e) {

            if ($notificate_about === 'errors-only') {
                 wp_mail(
                    $notification_email,
                    __('MPG schedule execution report: failed', 'mpg'),
                    __('Hi. <br>In process of execution the next error occurred: ', 'mpg') . $e->getMessage()
                );
            }

            MPG_LogsController::mpg_write($project_id, 'warning', __('Exception in scheduled execution: ', 'mpg') . $e->getMessage());
        }

        // If cron task is repetitive - we should't delete this option. Because task still isn't completed
        // А если это была одиночная задача, то хук удалится сам, а в БД подчистим вручную.
        if ($periodicity === 'once') {

            MPG_ProjectModel::mpg_update_project_by_id($project_id, [
                'schedule_periodicity' => null,
                'schedule_source_link' => null,
                'schedule_notificate_about' => null,
                'schedule_notification' => null
            ]);
        }
    }

    public static function mpg_unschedule_cron_task()
    {

        try {

            $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : null;

            $project = (array) MPG_ProjectModel::mpg_get_project_by_id($project_id);

            MPG_ProjectModel::mpg_remove_cron_task_by_project_id($project_id, $project);

            echo json_encode([
                'success' => true
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => true,
                'error' => $e->getMessage()
            ]);
        }

        wp_die();
    }
}
