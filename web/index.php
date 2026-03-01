<?php

function p( $print )
{
	print "<pre>\n";
	print_r( $print );
	print "</pre>";
}

ini_set( 'display_errors', 1 );

define( 'REQUEST_TIME', $_SERVER['REQUEST_TIME_FLOAT'] );
define( 'REQUEST_URI', $_SERVER['REQUEST_URI'] );
define( 'RACKS', realpath( '../racks' ) . DIRECTORY_SEPARATOR );

$uri = $_SERVER['REQUEST_URI'];
if( !$uri )
{
	throw new InvalidArgumentException( '!$_SERVER["REQUEST_URI"]' );
}

if( '/' == $uri )
{
	$uri = '/content/page/index';
}

list( $rack, $method, $args ) = explode( '/', substr( $uri, 1 ), 3 );
$rack_class = $rack . "_rack";
$rack_file = RACKS . "{$rack}/{$rack}.rack.php";

define( 'RACK', $rack ?? '' );
define( 'RACK_CLASS', $rack_class ?? '' );
define( 'RACK_FILE', $rack_file ?? '' );
define( 'RACK_METHOD', $method ?? '' );
define( 'RACK_ARGS', $args ?? '' );

if( !file_exists( RACK_FILE ) )
{
	throw new InvalidArgumentException( RACK . " not found" );
}

require_once( RACKS . '/hopper/hopper.rack.php' );

hopper_rack::rack_is_installed( 'hopper' );
hopper_rack::rack_is_installed( RACK );

require_once( RACK_FILE );

// Reflection in future
new $rack_class()->$method();
exit;