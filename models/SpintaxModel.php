<?php

class MPG_SpintaxModel
{

    public static function mpg_generate_spintax_string($spintax_string)
    {
        try {

            // 1. Разберем строку на групы по фигурным скобкам
            $re = '/{((?<=(?<!\{)\{)[^{}]*(?=\}(?!\})))}/m';

            preg_match_all($re, $spintax_string, $matches, PREG_SET_ORDER, 0);

            $what_replace = [];
            $replace_to = [];

            foreach ($matches as $match) {
                $pipe_divided_string =  $match[1];

                if ($pipe_divided_string) {
                    // 2. Разабъем строку по вертикальному слешу в массив
                    $words = explode('|', $pipe_divided_string);

                    $key = array_rand($words, 1);

                    $what_replace[] = $match[0];
                    $replace_to[] =  $words[$key];
                }
            }

            $final_string = str_replace($what_replace, $replace_to, $spintax_string);

            return stripslashes($final_string);

            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function flush_cache_by_project_id($project_id){

        try{

            global $wpdb;

            if (!$project_id) {
                throw new Exception(__('Project ID is missing', 'mpg'));
            }

            $table_name = $wpdb->prefix . MPG_Constant::MPG_SPINTAX_TABLE;
            
            $wpdb->delete($table_name, ['project_id' => $project_id]);

            return true;

        }catch(Exception $e){
            return new Exception($e->getMessage());
        }
    }
}
