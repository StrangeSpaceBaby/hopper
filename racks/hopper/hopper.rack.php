<?php

namespace App\racks\hopper;

final class hopper_rack
{
	private string $db_host = '';
	private string $db_user = '';
	private string $db_pass = '';
	private string $db_name = '';

	private string $hpr_base_dir = '';
	private string $hpr_racks_dir = '';
	private string $uri = '';
	private string $request_rack = '';
	private string $request_method = '';
	private array $request_args = [];

	public function __construct()
	{
		$this->check_uniqueness();
		$this->protect_hopper_secrets();
		$this->bootstrap();
	}

	public function get_request()
	{
		return
		[
			'uri' => $this->uri,
			'rack' => $this->request_rack,
			'method' => $this->request_method,
			'args' => $this->request_args,
			'args-string' => implode( '/', $this->request_args )
		];
	}

	private function bootstrap() : void
	{
		// Need to check if hopper is "installed" by checking for a manifest

		$this->hpr_racks_dir = dirname( dirname( __FILE__ ) ) . "/";
		$this->uri = HPR_REQUEST_URI;

		if( '/' == $this->uri || !$this->uri )
		{
			$this->uri = '/content/page/index';
		}

		$request = explode( '/', substr( $this->uri, 1 ) );
		$this->request_rack = array_shift( $request );
		$this->request_method = array_shift( $request );
		$this->request_args = $request;

		$this->rack_class = "App\\racks\\{$this->request_rack}\\{$this->request_rack}_rack";
		$this->rack_file = $this->hpr_racks_dir . "{$this->request_rack}/{$this->request_rack}.rack.php";

		if( !file_exists( $this->rack_file ) )
		{
			throw new \InvalidArgumentException( $this->rack_file . " not found" );
		}
	}

	private function check_uniqueness() : void
	{
		if( defined( 'HPR_INSTANTIATED' ) )
		{
			throw new \RuntimeException( 'Hopper already instantiated' );
		}

		define( 'HPR_INSTANTIATED', TRUE );
	}

	private function protect_hopper_secrets() : void
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

	private function rack_is_installed( $rack )
	{
		$manifest_file = $this->hpr_racks_dir . "{$rack}/{$rack}.manifest.json";
		if( !file_exists( $manifest_file ) )
		{
			throw new \RuntimeException( "{$rack} not installed. Run: hopper rack:install {$rack}" );
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
		$thus = new \ReflectionClass( $this );
		foreach( $thus->getProperties( \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED ) as $prop )
		{
			$prop_name = $prop->getName();
			$sanitized[$prop_name] = $this->$prop_name;
		}

		return $sanitized;
	}
}
