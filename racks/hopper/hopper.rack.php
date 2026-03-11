<?php

require_once( RACKS . '/hopper/logic/rack.logic.php' );
require_once( RACKS . '/hopper/logic/logic.logic.php' );

final class hopper_rack
{
	private string $db_host = '';
	private string $db_user = '';
	private string $db_pass = '';
	private string $db_name = '';

	public function __construct()
	{
		$this->check_uniqueness();
		$this->protect_hopper_secrets();
	}

	private function check_uniqueness()
	{
		if( defined( 'HPR_INSTANTIATED' ) )
		{
			throw new RuntimeException( 'Hopper already instantiated' );
		}

		define( 'HPR_INSTANTIATED', TRUE );
	}

	private function protect_hopper_secrets()
	{
		foreach([ 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME' ] as $param )
		{
			$property = strtolower( $param );
			$this->$property = $_SERVER['HPR_' . $param] ?? '';

			unset( $_SERVER['HPR_' . $param] );
			unset( $_SERVER['REDIRECT_HPR_' . $param] ); // Only present on rewritten requests
		}

		foreach([ 'HTTP_HOST', 'SERVER_NAME', 'SCRIPT_FILENAME', 'HPR_PEPPER' ] as $secret )
		{
			define( $secret, $_SERVER[$secret] );
		}

		foreach([ 'SERVER_SIGNATURE', 'SERVER_SOFTWARE', 'SCRIPT_FILENAME', 'HPR_PEPPER', 'PATH' ] as $secret )
		{
			unset( $_SERVER[$secret] );
		}
	}

	public static function rack_is_installed( $rack )
	{
		$manifest_file = RACKS . "{$rack}/{$rack}.manifest.json";
		if( !file_exists( $manifest_file ) )
		{
			throw new RuntimeException( "{$rack} not installed. Run: hopper rack:install {$rack}" );
		}

		return TRUE;
	}

	public function __toString()
	{
		return json_encode( $this->sanitize_properties() );
	}

	public function __debugInfo()
	{
		return $this->sanitize_properties();
	}

	private function sanitize_properties()
	{
		$sanitized = [];
		$thus = new ReflectionClass( $this );
		foreach( $thus->getProperties( ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED ) as $prop )
		{
			$prop_name = $prop->getName();
			$sanitized[$prop_name] = $this->$prop_name;
		}

		return $sanitized;
	}
}