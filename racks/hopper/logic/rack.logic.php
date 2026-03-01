<?php

class rack_logic
{
	protected object $hpr;

	public function __construct()
	{
		$this->hpr = new hopper_rack();
	}

	protected function get_arg_at_pos( $position )
	{
		if( !RACK_ARGS )
		{
			return NULL;
		}

		$uri = explode( "/", RACK_ARGS );
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
		$thus = new ReflectionClass( $this );
		foreach( $thus->getProperties( ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED ) as $prop )
		{
			$prop_name = $prop->getName();
			$sanitized[$prop_name] = $this->$prop_name;
		}

		return $sanitized;
	}
}