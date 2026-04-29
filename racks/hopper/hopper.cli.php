#!/usr/bin/env php
<?php

/**
 * Hopper CLI
 * 
 * Usage: hopper <scope:command> [arguments]
 * 
 * Commands:
 *   rack:install <rack-name>     Scan and install a rack
 *   rack:generate <rack-name>    Generate a new rack skeleton
 */

define( 'RACKS_DIR', dirname( __FILE__, 2 ) . "/" );

// Dangerous patterns to flag during scan (regex patterns)
const DANGEROUS_PATTERNS = [
	'/\beval\s*\(/i' => 'Code execution via eval',
	'/\bexec\s*\(/i' => 'Shell command execution',
	'/\bshell_exec\s*\(/i' => 'Shell command execution',
	'/\bsystem\s*\(/i' => 'Shell command execution',
	'/\bpassthru\s*\(/i' => 'Shell command execution',
	'/\bproc_open\s*\(/i' => 'Process execution',
	'/\bpopen\s*\(/i' => 'Process execution',
	'/\bcurl_init\s*\(/i' => 'Outbound HTTP requests',
	'/\bcurl_exec\s*\(/i' => 'Outbound HTTP requests',
	'/\bfsockopen\s*\(/i' => 'Socket connections',
	'/\bstream_socket_client\s*\(/i' => 'Socket connections',
	'/\bReflectionProperty\b/i' => 'Reflection access to properties',
	'/\bReflectionClass\b/i' => 'Reflection access to classes',
	'/new\s+PDO\s*\(/i' => 'Direct database instantiation',
	'/new\s+mysqli\s*\(/i' => 'Direct database instantiation',
	'/\bmysqli_connect\s*\(/i' => 'Direct database instantiation',
	'/\$_SERVER\s*\[/i' => 'Direct server variable access',
	'/\$_ENV\s*\[/i' => 'Direct environment variable access',
	'/\bgetenv\s*\(/i' => 'Environment variable access',
	'/\bputenv\s*\(/i' => 'Environment variable modification',
	'/\bfile_get_contents\s*\(\s*[\'"]https?:/i' => 'Outbound HTTP via file_get_contents',
	'/SET\s+@/i' => 'SQL session variable manipulation',
	'/\binclude\s*\(\s*\$/i' => 'Dynamic include',
	'/\binclude_once\s*\(\s*\$/i' => 'Dynamic include_once',
	'/\brequire\s*\(\s*\$/i' => 'Dynamic require',
	'/\brequire_once\s*\(\s*\$/i' => 'Dynamic require_once',
];

// Valid skills to add to rack capabilities
// @TODO db fetch values
const VALID_SKILLS = [ 'db', 'mail', 'payment' ];

class HopperCLI
{
	private array $args;
	private string $scope;
	private string $command;
	private array $params;

	public function __construct( array $argv )
	{
		$this->args = $argv;
		$this->parseCommand();
	}

	private function parseCommand() : void
	{
		if ( !isset( $this->args[1] ) )
		{
			$this->showUsage();
			exit( 1 );
		}

		$scopeCommand = $this->args[1];
		
		if ( !str_contains( $scopeCommand, ':' ) )
		{
			$this->error( "Invalid command format. Use scope:command (e.g., rack:install)" );
			exit( 1 );
		}

		list( $this->scope, $this->command ) = explode( ':', $scopeCommand, 2 );
		$this->params = array_slice( $this->args, 2 );
	}

	public function run() : void
	{
		$method = "cmd_{$this->scope}_{$this->command}";

		if ( !method_exists( $this, $method ) )
		{
			$this->error( "Unknown command: {$this->scope}:{$this->command}" );
			exit( 1 );
		}

		$this->$method();
	}

	private function cmd_rack_generate() : void
	{
		if ( !isset( $this->params[0] ) )
		{
			$this->error( "Usage: hopper rack:generate <rack-name>" );
			exit( 1 );
		}

		$rackName = $this->params[0];
		$rackDir = RACKS_DIR . $rackName . DIRECTORY_SEPARATOR;

		// Check rack doesn't already exist
		if ( is_dir( $rackDir ) )
		{
			$this->error( "Rack already exists: {$rackDir}" );
			exit( 1 );
		}

		// Create directory
		if ( !mkdir( $rackDir, 0755, true ) )
		{
			$this->error( "Failed to create directory: {$rackDir}" );
			exit( 1 );
		}

		// Create metadata
		$metadata = [
			'rack' => $rackName,
			'name' => '',
			'desc' => '',
			'author' => '',
			'version' => '0.0.1',
			'version_date' => date( 'Y-m-d' ),
			'skills' => []
		];

		$metadataFile = $rackDir . $rackName . '.metadata.json';
		$written = file_put_contents(
			$metadataFile,
			json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);

		if ( FALSE == $written )
		{
			$this->error( "Failed to write metadata file" );
			exit( 1 );
		}

		// Create rack class
		$rackClass = <<<PHP
<?php

class {$rackName}_rack extends rack_logic
{
	public function __construct()
	{
		parent::__construct();
	}
}
PHP;

		// Create model class
		$rackClass = <<<PHP
<?php

class {$rackName}_model extends model_logic
{
	public function __construct()
	{
		parent::__construct();
	}
}
PHP;

		$rackFile = $rackDir . $rackName . '.rack.php';
		$written = file_put_contents( $rackFile, $rackClass );

		if ( FALSE == $written )
		{
			$this->error( "Failed to write rack file" );
			exit( 1 );
		}

		$rackFile = $rackDir . $rackName . '.model.php';
		$written = file_put_contents( $rackFile, $rackClass );

		if ( FALSE == $written )
		{
			$this->error( "Failed to write model file" );
			exit( 1 );
		}

		$this->success( "Rack '{$rackName}' generated successfully." );
		$this->info( "{$rackDir}" );
		$this->info( "{$rackDir}/{$rackName}.metadata.json" );
		$this->info( "{$rackDir}/{$rackName}.rack.php" );
		$this->info( "" );
		$this->info( "Edit metadata to add necessary skills and run:" );
		$this->info( "hopper rack:install {$rackName}" );
	}

	private function cmd_rack_install() : void
	{
		if ( !isset( $this->params[0] ) )
		{
			$this->error( "Usage: hopper rack:install <rack-name>" );
			exit( 1 );
		}

		$rackName = $this->params[0];
		$rackDir = RACKS_DIR . $rackName . DIRECTORY_SEPARATOR;

		// Check rack exists
		if ( !is_dir( $rackDir ) )
		{
			$this->error( "Rack not found: {$rackDir}" );
			exit( 1 );
		}

		// Check for metadata
		$metadataFile = $rackDir . $rackName . '.metadata.json';
		if ( !file_exists( $metadataFile ) )
		{
			$this->error( "Missing {$rackName}.metadata.json" );
			exit( 1 );
		}

		$metadata = json_decode( file_get_contents( $metadataFile ), true );
		if ( JSON_ERROR_NONE !== json_last_error() )
		{
			$this->error( "Invalid {$rackName}.metadata.json: " . json_last_error_msg() );
			exit( 1 );
		}

		$this->info( "Installing rack: {$rackName}" );
		$this->info( "Name: " . ( $metadata['name'] ?? 'Unknown' ) );
		$this->info( "Description: " . ( $metadata['desc'] ?? '' ) );
		$this->info( "Author: " . ( $metadata['author'] ?? 'Unknown' ) );
		$this->info( "Version: " . ( $metadata['version'] ?? 'Unknown' ) );
		$this->info( "" );

		// Display and validate skills
		$skills = $metadata['skills'] ?? [];
		$invalidSkills = [];

		if ( empty( $skills ) )
		{
			$this->info( "Skills: none requested" );
		}
		else
		{
			$this->warn( "Skills requested:" );
			foreach ( $skills as $skill )
			{
				if ( in_array( $skill, VALID_SKILLS ) )
				{
					$this->info( "  - {$skill}" );
				}
				else
				{
					$this->error( "  - {$skill} (INVALID)" );
					$invalidSkills[] = $skill;
				}
			}
		}
		$this->info( "" );

		// Reject if invalid skills requested
		if ( !empty( $invalidSkills ) )
		{
			$this->error( "Rack requests invalid skills. Installation aborted." );
			exit( 1 );
		}

		// Get all files, hash them, and scan for dangerous patterns
		$allFiles = $this->getAllFiles( $rackDir );
		$fileHashes = [];
		$findings = [];

		foreach ( $allFiles as $file )
		{
			$relativePath = str_replace( $rackDir, '', $file );
			$content = file_get_contents( $file );
			$fileHashes[$relativePath] = 'sha256:' . hash( 'sha256', $content );

			// Scan all files for dangerous patterns
			$fileFindings = $this->scanFile( $file, $content );
			if ( !empty( $fileFindings ) )
			{
				$findings[$relativePath] = $fileFindings;
			}
		}

		// Report findings
		if ( empty( $findings ) )
		{
			$this->success( "No dangerous patterns detected." );
		}
		else
		{
			$this->warn( "Dangerous patterns detected:" );
			$this->info( "" );

			foreach ( $findings as $file => $patterns )
			{
				$this->warn( "  {$file}:" );
				foreach ( $patterns as $pattern )
				{
					$this->info( "    - {$pattern['description']}" );
					$this->info( "      Match: {$pattern['match']}" );
					$this->info( "      Line {$pattern['line']}: {$pattern['context']}" );
				}
				$this->info( "" );
			}
		}

		// Prompt for approval
		$this->info( "Total files: " . count( $allFiles ) );
		$this->info( "" );
		
		$response = $this->prompt( "Proceed with installation? (y/n)" );

		if ( 'y' !== strtolower( $response ) )
		{
			$this->info( "Installation cancelled." );
			exit( 0 );
		}

		// Generate manifest with skills
		$manifest = [
			'rack' => $rackName,
			'installed_at' => date( 'c' ),
			'skills' => $skills,
			'files' => $fileHashes
		];

		$manifestFile = $rackDir . $rackName . '.manifest.json';
		$written = file_put_contents( 
			$manifestFile, 
			json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) 
		);

		if ( FALSE == $written )
		{
			$this->error( "Failed to write manifest file" );
			exit( 1 );
		}

		$this->success( "Rack '{$rackName}' installed successfully." );
		$this->info( "Manifest written to: {$manifestFile}" );
	}

	private function getAllFiles( string $dir ) : array
	{
		$files = [];
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file )
		{
			if ( $file->isFile() )
			{
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	private function scanFile( string $filepath, string $content ) : array
	{
		$findings = [];
		$lines = explode( "\n", $content );

		foreach ( $lines as $lineNum => $line )
		{
			foreach ( DANGEROUS_PATTERNS as $pattern => $description )
			{
				if ( preg_match_all( $pattern, $line, $matches ) )
				{
					foreach ( $matches[0] as $match )
					{
						$findings[] = [
							'pattern' => $pattern,
							'match' => $match,
							'description' => $description,
							'line' => $lineNum + 1,
							'context' => trim( $line )
						];
					}
				}
			}
		}

		return $findings;
	}

	private function prompt( string $message ) : string
	{
		echo $message . " ";
		return trim( fgets( STDIN ) );
	}

	private function showUsage() : void
	{
		echo "Hopper CLI\n";
		echo "\n";
		echo "Usage: hopper <scope:command> [arguments]\n";
		echo "\n";
		echo "Commands:\n";
		echo "  rack:generate <rack-name>   Generate a new rack skeleton\n";
		echo "  rack:install <rack-name>    Scan and install a rack\n";
		echo "\n";
	}

	private function info( string $message ) : void
	{
		echo $message . "\n";
	}

	private function success( string $message ) : void
	{
		echo "\033[32m{$message}\033[0m\n";
	}

	private function warn( string $message ) : void
	{
		echo "\033[33m{$message}\033[0m\n";
	}

	private function error( string $message ) : void
	{
		echo "\033[31mError: {$message}\033[0m\n";
	}
}

// Run
new HopperCLI( $argv )->run();