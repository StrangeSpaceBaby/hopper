<?php

ini_set( 'display_errors', 1 );

function p( $print )
{
	$now = microtime( TRUE );
	print "\n<pre>{$now} --- ";
	print_r( $print );
	print "</pre>\n";
}

spl_autoload_register(
	function( $class )
	{
		$class_file = str_replace( "App\\", '', $class );
		$parts = explode( "\\", $class_file );
		$class_name = array_pop( $parts );
		$path = HPR_BASE_DIR . implode( "/", $parts ) . "/" . str_replace( '_', '.', $class_name ) . '.php';

		if( !file_exists( $path ) )
		{
			throw new Exception( "{$path} for {$class} ({$class_name}) !exist" );
		}

		require_once( $path );
	}
);

define( 'HPR_REQUEST_TIME', $_SERVER['REQUEST_TIME_FLOAT'] );
define( 'HPR_REQUEST_URI', $_SERVER['REQUEST_URI'] );
define( 'HPR_BASE_DIR', realpath( '../') . "/" );

$request = explode( "/", HPR_REQUEST_URI );
array_shift( $request ); // removes leading /
$rack_name = $request[0];
$rack_method = $request[1];

$rack_class = "App\\racks\\{$rack_name}\\{$rack_name}_rack";

// Reflection in future
new $rack_class()->$rack_method();
exit;
