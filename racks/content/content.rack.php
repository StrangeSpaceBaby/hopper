<?php

class content_rack extends rack_logic
{
	private string $tpl_dir;

	public function __construct()
	{
		parent::__construct();
		$this->tpl_dir = RACKS . "content/tpl/";
	}

	public function page()
	{
		$tpl = $this->get_tpl( "page/" . $this->get_arg_at_pos( 0 ) );
		print( $tpl );
		exit;
	}

	private function get_tpl( $tpl_name )
	{
		$path = $this->tpl_dir . $tpl_name . ".tpl";
		if( !file_exists( $path ) )
		{
			throw new InvalidArgumentException( "{$path} not found" );
		}

		return file_get_contents( $path );
	}
}