<?php

if (!defined('ABSPATH')) exit;

require_once(realpath(__DIR__ . '/DatasetController.php'));
require_once(realpath(__DIR__ . '/ProjectController.php'));


class MPG_MenuController
{
    public static function init()
    {
        add_action('admin_menu', 'mpg_main_sidebar_menu', 9, 0);

        function mpg_main_sidebar_menu()
        {

            $role = 'edit_pages';

            add_menu_page(__('MPG', 'mpg'), __('MPG', 'mpg'), $role, 'mpg-dataset-library', array('MPG_DatasetController', 'get_all'),  plugin_dir_url(__FILE__) . '/../../frontend/images/logo_mpg.svg');

            add_submenu_page('mpg-dataset-library', __('Create new', 'mpg'), __('Create New +', 'mpg'),  $role, 'mpg-dataset-library', array('MPG_DatasetController', 'get_all'));
            add_submenu_page('mpg-dataset-library', __('Project builder', 'mpg'), null, $role, 'mpg-project-builder', array('MPG_ProjectController', 'builder'));

            global $wpdb;
            $projects = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}" .  MPG_Constant::MPG_PROJECTS_TABLE);

            if ($projects) {
                foreach ($projects as $project) {
                    add_submenu_page('mpg-dataset-library', __($project->name, 'mpg'), '- &nbsp;' . $project->name, $role, 'mpg-project-builder&id=' . $project->id,'__return_null');  
                }
            }
        }
    }
}
