<?php

namespace App\racks\hopper\logic;

class logic_logic
{
	public function __construct()
	{
	}

	public function __toString()
	{
		return $this->sanitize_properties();
	}

	public function __debugInfo()
	{
		return json_encode( $this->sanitize_properties() );
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
