<?php

if (!defined('ABSPATH')) exit;

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Common\Type;

require_once(realpath(__DIR__ . '/../helpers/Constant.php'));

class MPG_DatasetModel
{

    public static function download_file($link, $destination_path)
    {
        try {
            $agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36';
            $browser = curl_init();
            curl_setopt($browser, CURLOPT_URL, $link);
            // curl_setopt($browser, CURLOPT_REFERER, $referer);
            curl_setopt($browser, CURLOPT_USERAGENT, $agent);
            curl_setopt($browser, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($browser, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($browser, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($browser, CURLOPT_CONNECTTIMEOUT, 10); //times out after 11s
            curl_setopt($browser, CURLOPT_TIMEOUT, 50); //times out after 51s
            curl_setopt($browser, CURLOPT_COOKIEJAR, '../');
            curl_setopt($browser, CURLOPT_COOKIEFILE, '../');
            curl_setopt($browser, CURLOPT_FOLLOWLOCATION, true);
            $content = curl_exec($browser);
            curl_close($browser);
            unset($browser);

            $open_handler = fopen($destination_path, 'w+');

            fwrite($open_handler, $content);
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }


    public static function get_dataset_path_by_project_id($project_id)
    {

        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT source_path FROM {$wpdb->prefix}" .  MPG_Constant::MPG_PROJECTS_TABLE . " WHERE id=%d", $project_id)
        );

        return $results[0]->source_path;
    }

    public static function mpg_read_dataset_hub()
    {
        $path_to_dataset_hub = plugin_dir_path(__DIR__) . 'temp/dataset_hub.xlsx';

        $download_result = MPG_DatasetModel::download_file(MPG_Constant::DATASET_SPREADSHEET_CSV_URL, $path_to_dataset_hub);

        if (!$download_result) {
            throw '';
        }

        $reader = ReaderFactory::create(Type::XLSX); // for XLSX files
        // Мы знаем, что датасет-хаб всегда будет xlsx;

        $reader->open($path_to_dataset_hub);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                if ($row[0] !== NULL) {
                    $dataset_array[] = $row;
                }
            }
        }

        $reader->close();

        return $dataset_array;
    }
}
