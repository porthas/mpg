<?php
require_once(realpath(__DIR__) . '/../controllers/LogsController.php');

require_once(realpath(__DIR__) . '/../models/CoreModel.php');
require_once(realpath(__DIR__) . '/../models/SEOModel.php');

require_once(realpath(__DIR__) . '/../helpers/Validators.php');

class MPG_CoreController
{

    public static $href_regexp = '/href=\\\\?".*?\\\\?"/m';


    public static function core($redirect_rules, $post, $template_post_id, $path)
    {

        global  $wp_query;

        // do changes in title and content
        $project_id = $redirect_rules['project_id'];

        add_action('elementor/frontend/element/before_render', function ($post) use ($project_id) {
            return MPG_CoreModel::mpg_shortcode_replacer($post->post_content, $project_id);
        });

        // Override canonical
        remove_action('wp_head', 'rel_canonical');
        remove_action('template_redirect', 'redirect_canonical');

        add_action('wp_head', function () {
            global $wp;
            echo '<link rel="canonical" href="' . home_url($wp->request) . '/">';
        }, 1, 1);

        MPG_SEOModel::mpg_all_in_one_seo_pack($project_id);

        MPG_SEOModel::mpg_yoast($project_id);

        MPG_SEOModel::mpg_rank_math($post, $project_id);

        MPG_SEOModel::mpg_seopress($project_id);


        // // Решает проблему с тем, что не заменяются шорткоды в Elementor.
        add_action('wp_head', function () use ($project_id, $path) {
            MPG_CoreModel::mpg_header_handler($project_id, $path);
        }, 9, 0);

        add_action('wp_footer', function () use ($project_id, $path) {
            MPG_CoreModel::mpg_footer_handler($project_id, $path);
        }, 10, 0);

        // Решает проблему с соц. плагинами. (правильные ссылки на страницы без шорткодов)
        $post->post_title = MPG_CoreModel::mpg_shortcode_replacer($post->post_title, $project_id);
        $post->post_content = MPG_CoreModel::mpg_shortcode_replacer($post->post_content, $project_id);

        // setup template post as global, this is needed for the_title(), the_permalink()
        setup_postdata($GLOBALS['post'] = &$post);
        // set the post as cached, it is necessary for the get_post () function, it will return the replaced data when this function is called
        wp_cache_set($post->ID, $post, 'posts');
        // set status code 200, because on default this page not exist and return 404 code

        // Перезаписывает URL (основной) для поста\кастом поста\страницы. Это решать проблему с заменой шорткодов в УРЛах
        // если у пользователя есть плагины, например для шаринга в соц. сети, то там неправильные ссылки,
        // или с точки зрения SEO: <link rel="alternate" с этим хуком становится правильным.

        foreach (['post', 'page', 'post_type'] as $type) {
            add_filter($type . '_link', function ($url, $post_id, $sample) use ($type) {
                return apply_filters('wpse_link', $url, $post_id, $sample, $type);
            }, 1, 3);
        }

        $permalink = get_permalink($template_post_id);

        add_filter('wpse_link', function ($url) use ($permalink) {
            global $wp;
            if ($url === $permalink) {
                return home_url($wp->request);
            } else {
                return  $url;
            }
        }, 1, 4);

        status_header(200);
        // set important settings for page query
        $wp_query->queried_object = $post;
        $wp_query->is_single = false;
        $wp_query->is_404 = false;
        $wp_query->queried_object_id = $post->ID;
        $wp_query->post_count = 1;
        $wp_query->current_post = -1;
        $wp_query->posts = array($post);
        $wp_query->is_author = false;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
    }


    // Create the virtual page with content from template and set settings for view this page like normal WP page
    public static function mpg_view_multipages_elementor($false, $wp_query) // $wp_query не удалять
    {

        $path = MPG_Helper::mpg_get_request_uri(); // это та часть что идет после папки установки WP. тпиа wp.com/xxx
        $redirect_rules = MPG_CoreModel::mpg_get_redirect_rules($path);

        if ($redirect_rules) {
            // If requested URL is available in array of all URL's

            $template_post_id = $redirect_rules['template_id'];
            $post = get_post($template_post_id);

            self::core($redirect_rules, $post, $template_post_id, $path);
        } else {
            MPG_LogsController::mpg_write($redirect_rules['project_id'], 'warning', __('URL generated in MPG and some page or post slug is equal.', 'mpg'));
        }
    }

    // Create the virtual page with content from template and set settings for view this page like normal WP page
    public static function mpg_view_multipages_standard()
    {
        $path = MPG_Helper::mpg_get_request_uri(); // это та часть что идет после папки установки WP. тпиа wp.com/xxx
        $redirect_rules = MPG_CoreModel::mpg_get_redirect_rules($path);

        if ($redirect_rules) {
            // If requested URL is available in array of all URL's

            $template_post_id = $redirect_rules['template_id'];
            $post = get_post($template_post_id);

            if (is_404() && $post->post_status !== 'draft') {
                self::core($redirect_rules, $post, $template_post_id, $path);
            } else {
                MPG_LogsController::mpg_write($redirect_rules['project_id'], 'warning', __('URL generated in MPG and some page or post slug is equal.', 'mpg'));
            }
        }
    }


    // For ajax call
    public static function mpg_shortcode_ajax()
    {

        try {

            $content = isset($_POST['content']) ? $_POST['content'] : null;

            $project_id = isset($_POST['projectId']) ? sanitize_text_field($_POST['projectId']) : null;
            $where = isset($_POST['where']) ? sanitize_text_field($_POST['where']) : null;
            $operator = isset($_POST['operator']) ? sanitize_text_field($_POST['operator']) : null;
            $limit = isset($_POST['limit']) ? sanitize_text_field($_POST['limit']) : 4; // так как отсчет начинается с 0

            $atts = [
                'project-id' => $project_id,
                'where' => $where,
                'operator' => $operator,
                'limit' => $limit
            ];

            $results = self::mpg_shortcode_core($atts, $content);

            echo  '{"success": true, "data":"' . str_replace("\n", '<br>', $results) . '"}';
            wp_die();
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => __('Can\'t show preview due to error. Details: ' . $e->getMessage())
            ]);
            wp_die();
        }
    }


    // For inner call
    public static function mpg_shortcode($atts = array(), $content = null)
    {

        try {
            if (is_admin()) {
                return false;
            }

            // normalize attribute keys, lowercase
            $atts = array_change_key_case((array) $atts, CASE_LOWER);

            if (isset($atts['limit'])) {
                // фикс из-за того, что человек пишет лимит = 2, а получает 3 результата, ведь отсчет в массивах начинается с 0
                $atts['limit'] = (int) $atts['limit'] - 1;
            }

            return self::mpg_shortcode_core($atts, $content);
        } catch (Exception $e) {

            $path = MPG_Helper::mpg_get_request_uri(); // это та часть что идет после папки установки WP. тпиа wp.com/xxx
            $redirect_rules = MPG_CoreModel::mpg_get_redirect_rules($path);

            if ($redirect_rules && isset($redirect_rules['project_id'])) {
                MPG_LogsController::mpg_write($redirect_rules['project_id'], 'error', __('Exception in [mpg_shortcode]: ', 'mpg') . $e->getMessage());
            }
        }
    }


    public static function mpg_match($atts = array(), $content = null)
    {

        try {
            if (is_admin()) {
                return false;
            }

            // normalize attribute keys, lowercase
            $atts = array_change_key_case((array) $atts, CASE_LOWER);

            if (isset($atts['limit'])) {
                // фикс из-за того, что человек пишет лимит = 2, а получает 3 результата, ведь отсчет в массивах начинается с 0
                $atts['limit'] = (int) $atts['limit'] - 1;
            }

            return self::mpg_match_core($atts, $content);
        } catch (Exception $e) {

            $path = MPG_Helper::mpg_get_request_uri(); // это та часть что идет после папки установки WP. тпиа wp.com/xxx
            $redirect_rules = MPG_CoreModel::mpg_get_redirect_rules($path);

            if ($redirect_rules && isset($redirect_rules['project_id'])) {
                MPG_LogsController::mpg_write($redirect_rules['project_id'], 'error', __('Exception in [mpg_match]: ', 'mpg') . $e->getMessage());
            }
        }
    }

    public static function mpg_match_core($atts, $content)
    {

        try {
            $current_project_id   = isset($atts['current-project-id']) ? (int) $atts['current-project-id'] : null;
            $search_in_project_id = isset($atts['search-in-project-id']) ? (int) $atts['search-in-project-id'] : null;

            $current_header       = isset($atts['current-header']) ? $atts['current-header'] : null;
            $match_with           = isset($atts['match-with']) ? $atts['match-with'] : null;

            $limit                = isset($atts['limit']) ? (int) $atts['limit']  : null;
            $order_by             = isset($atts['order-by']) ? $atts['order-by'] : null;
            $direction            = isset($atts['direction']) ? $atts['direction'] : null;


            MPG_Validators::mpg_match($current_project_id, $search_in_project_id, $current_header, $match_with);
            MPG_Validators::mpg_order_params($order_by, $direction);

            // 1. Возьмем текущий проект
            $current_project = MPG_ProjectModel::mpg_get_project_by_id($current_project_id);

            // 2. Надо получить значение шорткода $current_shortcode для текущего УРЛа. Типа mpg_city => Los Angeles
            $current_dataset_path = isset($current_project[0]) ? $current_project[0]->source_path : null;
            $current_dataset_array = MPG_Helper::mpg_get_dataset_array($current_dataset_path, $current_project_id);
            $current_header_value = MPG_CoreModel::mpg_get_ceil_value_by_header($current_project, $current_dataset_array, $current_header);

            // 3. Теперь мы знаем что надо искать в связаном проекте. Это ок, теперь надо прочитать этот связанный проект ($seaarch_in_project_id)
            $search_in_project = MPG_ProjectModel::mpg_get_project_by_id($search_in_project_id);
            $search_in_dataset_path = isset($search_in_project[0]) ? $search_in_project[0]->source_path : null;
            $search_in_dataset_array = MPG_Helper::mpg_get_dataset_array($search_in_dataset_path, $search_in_project_id);

            $url_column_index = null;
            $headers_has_prefix = false;
            $headers = $search_in_dataset_array[0];

            foreach ($headers as $index => $header) {
                if (strpos($header, 'mpg_') === 0) {
                    $headers_has_prefix = true;
                }

                $shortcode_lowercase = strtolower($header);

                if ($shortcode_lowercase === 'mpg_url' || $shortcode_lowercase === 'url') {
                    $url_column_index = $index;
                }
            }

            // Если просто слова - добавляем префикс.
            if (!$headers_has_prefix) {
                $search_in_dataset_array[0] = [];
                foreach ($headers as $header) {
                    $search_in_dataset_array[0][] =  'mpg_' . strtolower($header);
                }
            }

            $header_index = array_search($match_with, $search_in_dataset_array[0]);

            if (!$header_index) {
                throw new Exception(__('Headers not matched in projects', 'mpg'));
            }


            $storage = [];
            foreach ($search_in_dataset_array as $index => $row) {

                preg_match("/$current_header_value/", $row[$header_index], $matches);

                if (!empty($matches)) {
                    $storage[] = ['row' => $row, 'index' => $index - 1];
                }
            }

            if (!empty($storage)) {

                $strings = [];
                $data_string = '';

                $urls_array = $search_in_project[0]->urls_array ? json_decode($search_in_project[0]->urls_array) : [];
                $short_codes = MPG_CoreModel::mpg_shortcodes_composer($headers);

                $space_replacer = $search_in_project[0]->space_replacer;

                // Находим все атрибуты href в шорткоде, и берем их значения
                $re = '/href=\\\\?".*?\\\\?"/m';
                preg_match_all($re, $content, $href_matches, PREG_SET_ORDER, 0);

                $placeholders = [];
                foreach ($href_matches as $index => $href) {
                    $placeholders[] = '/placeholder_href_' . $index . '/';
                    $content = str_replace($href[0], '/placeholder_href_' . $index . '/', $content);
                }

                if ($direction) {
                    $storage = MPG_CoreModel::mpg_order($storage, $search_in_dataset_array[0], $direction, $order_by);
                }

                // Теперь внутри всех href = заглушки.
                foreach ($storage as  $index => $row) {
                    // Массив с рядом ячеек с сорс-файла
                    $strings = $row['row'];

                    if ($url_column_index !== null) {
                        // @todo: тут может возникнуть два слеша: mpg_get_site_url может вернуть один, и один приконкатенирован
                        $strings[$url_column_index] = MPG_Helper::mpg_get_domain() . '/' . MPG_Helper::mpg_get_site_url() . $urls_array[$row['index']];
                    }

                    if ($href_matches) {
                        $data_string .= MPG_CoreModel::mpg_processing_href_matches($content, $short_codes, $href_matches, $strings, $space_replacer, $placeholders, $url_column_index);
                    } else {
                        // Это значит что ссылкок в шорткоде нет, и можно просто заменять "всё на всё" без экранирования.
                        $data_string .= preg_replace($short_codes, $strings, $content);
                    }

                    if (!is_null($limit) && $index === $limit) {
                        break;
                    }
                }

                return $data_string;
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public static function mpg_shortcode_core($atts, $content)
    {
        try {
            $project_id = isset($atts['project-id']) ? (int) $atts['project-id'] : null;

            $where_params = isset($atts['where']) ? explode(';', $atts['where']) : [];
            $operator     = isset($atts['operator']) ? $atts['operator'] : 'or';
            $limit        = isset($atts['limit']) ? (int) $atts['limit']  : null;

            $order_by     = isset($atts['order-by']) ? $atts['order-by'] : null;
            $direction    = isset($atts['direction']) ? $atts['direction'] : null;

            MPG_Validators::mpg_order_params($order_by, $direction);

            if (!$project_id) {
                return  __('Project id is not specified in [mpg] shortcode', 'mpg');
            }

            $project = MPG_ProjectModel::mpg_get_project_by_id($project_id);
            $dataset_path = isset($project[0]) ? $project[0]->source_path : null;
            $dataset_array = MPG_Helper::mpg_get_dataset_array($dataset_path, $project_id);

            $headers = $dataset_array[0];

            // Проверяем, заголовки это шорткоды (mpg_name), или просто слова
            $url_column_index = null;
            $headers_has_prefix = false;
            foreach ($headers as $index => $header) {
                if (strpos($header, 'mpg_') === 0) {
                    $headers_has_prefix = true;
                }

                $shortcode_lowercase = strtolower($header);

                if ($shortcode_lowercase === 'mpg_url' || $shortcode_lowercase === 'url') {
                    $url_column_index = $index;
                }
            }

            // Если просто слова - добавляем префикс.
            if (!$headers_has_prefix) {
                $dataset_array[0] = [];
                foreach ($headers as $header) {
                    $dataset_array[0][] =  'mpg_' . strtolower($header);
                }
            }

            if (!$dataset_array) {
                return false;
            }

            // Приводим заголовки в нижний регистр
            $column_names = array_map(function ($column) {
                return strtolower($column);
            }, (array) $dataset_array[0]);

            $where_storage = MPG_CoreModel::mpg_prepare_where_condition($project, $where_params, $dataset_array, $column_names);

            // Обрабатываем where=""
            if (!empty($where_storage)) {
                $where_filter_results = [];
                foreach ($dataset_array as $row_index => $row) {

                    if ($operator === 'or') {

                        foreach ($where_storage as $condition) {
                            $column_index = array_keys($condition)[0];
                            $serching_value = array_values($condition)[0];

                            preg_match("/$serching_value/", strtolower($row[$column_index]), $matches);

                            if (!empty($matches)) {
                                // Это сделано для того, чтобы передать индекс ряда, корорый попал в where, нужно для правильной подмены УРЛа
                                $where_filter_results[] = ['row' => $row, 'index' => $row_index - 1];
                                break; // если нашли по первому попавшемуся совпадению - значит можно поиск прекращать (ведь это "or")
                            }
                        }
                    } elseif ($operator === 'and') {

                        // Суть в том, чтобы создать пустой массив, где будет столько же элементов, сколько и условий в where
                        // При каждой итерации ряда, мы сравниваем устовия со значениями в столбцах датасета, и если оно совпало - записываем значение true в массив.
                        // Можно было бы сделать просто флаг $is_matched = true|false, но если 4 из 5 условий дадут false, а последнее true, 
                        // то в выдачу попадет этот ряд, хотя совпало только одно условие. И наоборот. Короче последняя итерация "решает", а это неправильно.

                        $mask = array_fill(0, count($where_storage), false);

                        foreach ($where_storage as $index => $condition) {
                            $column_index = array_keys($condition)[0]; //   mpg_city
                            $serching_value = strtolower(trim(array_values($condition)[0]));  //  Cleveland
                            $cell_value = strtolower(trim($row[$column_index]));

                            preg_match("/$serching_value/", strtolower($cell_value), $matches);

                            $mask[$index] = !empty($matches); // переопределяем булевое значение: если есть совпадение - ставим true
                        }

                        $unique_mask = array_unique($mask);
                        // Делаем массив уникальным, и если там остался один элемент. и он true, значит все были true
                        if (count($unique_mask) === 1 && $unique_mask[0] === true) {
                            // Это сделано для того, чтобы передать индекс ряда, корорый попал в where, нужно для правильной подмены УРЛа
                            $where_filter_results[] = ['row' => $row, 'index' => $row_index - 1];
                        }
                    }

                    // Если результатов насобиралось соответственно с лимитом, то прекращаем итерации.
                    if (!is_null($limit) && count($where_filter_results) === $limit) {
                        break;
                    }
                }
            }


            // Если ничего не нафильтровалось, но where="" есть, значит возвращаем пустоту, т.е. шорткод [mpg][/mpg] ничего не выведет
            if (empty($where_filter_results) && !empty($where_params)) {
                return __('where=\"\" condition return zero rows. No results found', 'mpg');
            }

            $urls_array = $project[0]->urls_array ? json_decode($project[0]->urls_array) : [];
            $short_codes = MPG_CoreModel::mpg_shortcodes_composer($headers);
            $space_replacer = $project[0]->space_replacer;


            // весь набор данных (без заголовков)
            if (empty($where_filter_results)) {
                array_shift($dataset_array);

                $strings = [];
                $data_string = '';

                // Находим все атрибуты href в шорткоде, и берем их значения
                preg_match_all(self::$href_regexp, $content, $href_matches, PREG_SET_ORDER, 0);

                $placeholders = [];
                foreach ($href_matches as $index => $href) {
                    $placeholders[] = '/placeholder_href_' . $index . '/';
                    $content = str_replace($href[0], '/placeholder_href_' . $index . '/', $content);
                } // Теперь внутри всех href = заглушки.

                if ($direction) {
                    $dataset_array = MPG_CoreModel::mpg_order($dataset_array, $column_names, $direction, $order_by);
                }
                
                foreach ($urls_array as $index => $row) {
                    // Массив с рядом ячеек с сорс-файла
                    $strings = $dataset_array[$index];
                    if ($url_column_index !== null) {

                        // @todo: тут может возникнуть два слеша: mpg_get_site_url может вернуть один, и один приконкатенирован
                        $strings[$url_column_index] = MPG_Helper::mpg_get_domain() . '/' . MPG_Helper::mpg_get_site_url() . $urls_array[$index];
                    }

                    // Если шорткод находится между href=" и " - значит его надо экранировать. Если нет - значит просто заменять "все на все", как обычно.
                    // Для реализации этого - лучше всего вырезать то что внутри href=, заменить его на заглушку
                    // Сделать замену по обычному сценарию, потом сделать замену шорткодов по "спец" сценарию того что в атрибуте.
                    // Потом - заглушку заменить на обработаную строку взятую ранее из href.

                    if ($href_matches) {
                        $data_string .= MPG_CoreModel::mpg_processing_href_matches($content, $short_codes, $href_matches, $strings, $space_replacer, $placeholders, $url_column_index);
                    } else {
                        // Это значит что ссылкок в шорткоде нет, и можно просто заменять "всё на всё" без экранирования.
                        $data_string .= preg_replace($short_codes, $strings, $content);
                    }

                    if (!is_null($limit) && $index === $limit) {
                        break;
                    }
                }

                return $data_string;
            }


            // Если не вышли из функции до этого места ( в куске кода о where), значит обрабатываем код ниже, для всего датасета
            // $where_filter_results  - это просто массив с рядами из файла, только не все подряд, а отобранные согласно условию where
            if (!empty($where_filter_results)) {

                $strings = [];
                $data_string = '';

                // Находим все атрибуты href в шорткоде, и берем их значения
                preg_match_all(self::$href_regexp, $content, $href_matches, PREG_SET_ORDER, 0);

                $placeholders = [];
                foreach ($href_matches as $index => $href) {
                    $placeholders[] = '/placeholder_href_' . $index . '/';
                    $content = str_replace($href[0], '/placeholder_href_' . $index . '/', $content);
                }
                // Теперь внутри всех href = заглушки.

                if ($direction) {
                    $where_filter_results = MPG_CoreModel::mpg_order($where_filter_results, $column_names, $direction, $order_by);
                }

                foreach ($where_filter_results as  $index => $row) {
                    // Массив с рядом ячеек с сорс-файла
                    $strings = $row['row'];

                    if ($url_column_index !== null) {
                        // @todo: тут может возникнуть два слеша: mpg_get_site_url может вернуть один, и один приконкатенирован
                        $strings[$url_column_index] = MPG_Helper::mpg_get_domain() . '/' . MPG_Helper::mpg_get_site_url() . $urls_array[$row['index']];
                    }

                    if ($href_matches) {

                        $data_string .= MPG_CoreModel::mpg_processing_href_matches($content, $short_codes, $href_matches, $strings, $space_replacer, $placeholders, $url_column_index);
                    } else {
                        // Это значит что ссылкок в шорткоде нет, и можно просто заменять "всё на всё" без экранирования.
                        $data_string .= preg_replace($short_codes, $strings, $content);
                    }

                    if (!is_null($limit) && $index === $limit) {
                        break;
                    }
                }

                return $data_string;
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
