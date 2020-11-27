<?php

class MPG_LogsController
{

    public static function mpg_write($project_id, $level, $message)
    {
        global $wpdb;

        $requested_url = MPG_Helper::mpg_get_request_uri();

        if (!$project_id) {
            $redirect_rules = MPG_CoreModel::mpg_get_redirect_rules($requested_url);
            $project_id = $redirect_rules['project_id'];
        }

        $wpdb->insert($wpdb->prefix . MPG_Constant::MPG_LOGS_TABLE, [
            'project_id' => $project_id,
            'level' => $level,
            'url' => $requested_url,
            'message' => $message,
            'datetime' => date('Y-m-d H:i:s')
        ]);
    }

    public static function mpg_clear_log_by_project_id()
    {

        try {

            global $wpdb;

            $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : null;

            if (!$project_id) {
                throw new Exception('Project ID is missing');
            }

            $table_name = $wpdb->prefix . MPG_Constant::MPG_LOGS_TABLE;

            $wpdb->delete($table_name, ['project_id' => $project_id]);

            echo json_encode([
                'success' => true
            ]);

            wp_die();
        } catch (Exception $e) {

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);

            wp_die();
        }
    }

    public static function mpg_get_log_by_project_id()
    {

        try {

            global $wpdb;

            $project_id = (int) $_GET['projectId'];

            $draw = isset($_POST['draw']) ? (int) $_POST['draw'] : 0;
            $start = isset($_POST['start']) ? (int) $_POST['start'] : 1;
            $length = isset($_POST['length']) ? (int) $_POST['length'] : 10;


            $dataset_array = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . MPG_Constant::MPG_LOGS_TABLE . ' WHERE project_id=' . $project_id);
            $dataset_array = array_slice($dataset_array, $start, $length);


            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => count($dataset_array),
                'recordsFiltered' => count($dataset_array),
                'data' => $dataset_array,
                'headers' =>  ['id', 'project_id', 'level', 'message', 'datetime']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }
}
