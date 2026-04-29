<?php

namespace App\racks\hopper\logic;

use App\racks\hopper\hopper_rack;

/**
 * rack_logic is the core functionality for racks to interface with hopper.
 * $this->hpr is instantiated first in rack_logic so that hopper can be loaded
 * hoist the framework.  All racks must extend rack_logic to have access to the hopper object
 */
class rack_logic extends logic_logic
{
	protected object $hpr;

	public function __construct()
	{
		parent::__construct();
		$this->hpr = new hopper_rack();
	}

	/**
	 * Returns the argument at the index of the url segments after the rack and method.
	 *
	 * Ex. https://domain.com/rack/method/arg0/arg1/named-arg:named-arg-val
	 * 0 = arg0, 1 = arg1, etc.
	 *
	 * @param int $param
	 * @return string $arg
	 */
	protected function get_arg_at_pos( int $position ) :string
	{
		if( !$this->hpr->get_request()['args-string'] )
		{
			return NULL;
		}

		$uri = explode( "/", $this->hpr->get_request()['args-string'] );
		$arg = $uri[$position];

		if( !$arg || str_starts_with( $arg, '.' ) )
		{
			return NULL;
		}

		return $arg;
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
