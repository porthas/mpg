<?php

/**
 * Plugin Name: Multiple Pages Generator by Porthas
 * Plugin URI: https://www.porthas.com/
 * Description: Plugin for generation of multiple frontend pages from CSV data file.
 * Author: <a href='https://www.porthas.com/'>Porthas inc</a>
 * Author URI: https://www.porthas.com/
 * Version: 2.7
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( function_exists( 'mpg_app' ) ) {
    mpg_app()->set_basename( false, __FILE__ );
} else {
    // DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
    
    if ( !function_exists( 'mpg_app' ) ) {
        // Create a helper function for easy SDK access.
        function mpg_app()
        {
            global  $mpg_app ;
            
            if ( !isset( $mpg_app ) ) {
                // Activate multisite network integration.
                if ( !defined( 'WP_FS__PRODUCT_2867_MULTISITE' ) ) {
                    define( 'WP_FS__PRODUCT_2867_MULTISITE', true );
                }
                // Include Freemius SDK.
                require_once realpath( __DIR__ . '/vendor/freemius/wordpress-sdk/start.php' );
                $mpg_app = fs_dynamic_init( array(
                    'id'              => '2867',
                    'slug'            => 'multi-pages-plugin',
                    'type'            => 'plugin',
                    'public_key'      => 'pk_0c4166c5c398b9b750cf04df59173',
                    'is_premium'      => false,
                    'premium_suffix'  => 'Pro',
                    'has_addons'      => false,
                    'has_paid_plans'  => true,
                    'has_affiliation' => 'customers',
                    'menu'            => array(
                    'slug'       => 'mpg-dataset-library',
                    'first-path' => 'admin.php?page=mpg-dataset-library',
                    'support'    => false,
                ),
                    'is_live'         => true,
                ) );
            }
            
            return $mpg_app;
        }
        
        // Init Freemius.
        mpg_app();
        // Signal that SDK was initiated.
        do_action( 'mpg_app_loaded' );
    }
    
    // ... Your plugin's main file logic ...
    require_once 'controllers/CoreController.php';
    require_once 'controllers/HookController.php';
    require_once 'controllers/MenuController.php';
    // Запуск базового функционала подмены данных
    MPG_HookController::init_replacement();
    // Запуск всяких actions, hooks, filters
    MPG_HookController::init_base();
    // Запуск хуков для ajax. Связываем роуты и функции
    MPG_HookController::init_ajax();
    // Инициализация бокового меню в WordPress
    MPG_MenuController::init();
}
