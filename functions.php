<?php
/**
Plugin Name: Accelerate Patterns
Plugin URI: https://ampwptools.com/accelerate-patterns/
Description: This plugin allows you to create, manage and edit your own WordPress patterns using any blocks - core blocks as well as premium blocks.
Version:  1.0.2
Author: AMP Publisher
Author URI: https://ampwptools.com/
License: GPLv3
 *
 * @package AMP Publisher
 */

?>
<?PHP defined( 'ABSPATH' ) || die(); ?>
<?php
require_once dirname( __FILE__ ) . '/class-tacwp-accpatterns.php';

$accpatterns = new Tacwp_Accpatterns( 'accpatterns', dirname( __FILE__ ) );

$accpatterns->init();


