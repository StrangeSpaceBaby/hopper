<?php

namespace App\racks\content;

use App\racks\hopper\logic\rack_logic;

class content_rack extends rack_logic
{
	private string $tpl_dir;

	public function __construct()
	{
		parent::__construct();
		$this->tpl_dir = dirname( __FILE__ ) . "/tpl/";
	}

	public function page()
	{
		try
		{
			$tpl = $this->get_tpl( "page/" . $this->get_arg_at_pos( 0 ) );
			print( $tpl );
			exit;
		}
		catch( \InvalidArgumentException $e )
		{
			p( $e->getMessage() );
			exit;
		}
	}

	private function get_tpl( $tpl_name )
	{
		$path = $this->tpl_dir . $tpl_name . ".tpl";
		if( !file_exists( $path ) )
		{
			throw new \InvalidArgumentException( "{$path} not found" );
		}

		return file_get_contents( $path );
	}
}
