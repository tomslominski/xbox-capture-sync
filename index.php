<?php
/**
 * Simple script for syncing Xbox screenshots and gameclips to a local folder.
 */

/**
 * Log but don't display errors.
 */
ini_set( 'display_errors', 0 );
ini_set( 'log_errors', 1 );

/**
 * Main class responsible for querying and downloading captures.
 */
class Xbox_Capture_Sync {
	/**
	 * @var string Output directory.
	 */
	public string $output_directory;

	/**
	 * @var int Number of successfully downloaded files.
	 */
	public int $count = 0;

	/**
	 * Run script.
	 */
	public function __construct() {
		$this->init_directories();

		try {
			foreach( Type::cases() as $type ) {
				$this->download_captures( $type );
			}

			$this->return_json( 200, sprintf( '%d new captures downloaded.', $this->count ) );
		} catch( Exception $error ) {
			$this->return_json( 500, $error->getMessage() );
		}
	}

	/**
	 * Return JSON response.
	 *
	 * @param int $code HTTP response code.
	 * @param string $message Response message.
	 * @return void
	 */
	public function return_json( int $code, string $message ): void {
		http_response_code( $code );
		header( 'Content-Type: application/json' );
		echo json_encode( [
			'code' => $code,
			'message' => $message,
		] );
		exit;
	}

	/**
	 * Create output directory if necessary.
	 *
	 * @return void
	 */
	public function init_directories(): void {
		$this->output_directory = getcwd() . '/Captures';

		if( !file_exists( $this->output_directory ) ) {
			mkdir( $this->output_directory );
		}
	}

	/**
	 * Get a list of captures to download.
	 *
	 * @param Type $type Screenshots or gameclips.
	 * @return ?array List of captures returned by the API.
	 * @throws Exception
	 */
	public function get_captures( Type $type ): ?array {
		if( $api_key = preg_replace( '/[^a-z\d]/i', '', getenv( 'API_KEY' ) ) ) {
			$request = file_get_contents( 'https://xbl.io/api/v2/dvr/' . $type->get_api_path() . '/', false, stream_context_create( [
				'http' => [
					'method' => 'GET',
					'header' => "X-Authorization: {$api_key}\r\n",
				],
			] ) );

			if( $request ) {
				$response = json_decode( $request );
				$property = $type->get_response_property();

				if( isset( $response->$property ) && is_array( $response->$property ) ) {
					return $response->$property;
				} else {
					throw new Exception( sprintf( 'Failed to decode %s response.', $type->name ) );
				}
			} else {
				throw new Exception( sprintf( 'Failed to retrieve %s from remote URL.', $type->name ) );
			}
		} else {
			throw new Exception( 'API key not set.' );
		}
	}

	/**
	 * Download screenshots and gameclips from API.
	 *
	 * @param Type $type Screenshots or gameclips.
	 * @throws Exception
	 */
	public function download_captures( Type $type ): void {
		if( $captures = $this->get_captures( $type ) ) {
			foreach( $captures as $capture ) {
				$property = $type->get_uri_property();

				if( isset( $capture->$property[0]->uri ) && ( $filename = $this->get_file_name( $capture, $type ) ) ) {
					$path = $this->output_directory . '/' . $filename;

					if( !file_exists( $path ) ) {
						error_log( sprintf( 'Downloading %s', $filename ) );
						copy( $capture->$property[0]->uri, $path );
						$this->count++;
					} else {
						error_log( sprintf( '%s already exists.', $filename ) );
					}
				}
			}
		}
	}

	/**
	 * Get name of a screenshot or gameclip.
	 *
	 * @param stdClass $capture Individual file returned from API.
	 * @param Type $type Screenshots or gameclips.
	 * @return string|null Name of file, or null if date cannot be parsed.
	 */
	public function get_file_name( stdClass $capture, Type $type ): ?string {
		$property = $type->get_date_property();
		$time = DateTime::createFromFormat( 'Y-m-d\TH:i:s\Z', $capture->$property );

		return $time ? $type->get_filename( $time ) : null;
	}
}

/**
 * Enum describing available capture types.
 */
enum Type {
	/**
	 * Screenshot.
	 */
	case Screenshot;

	/**
	 * Gameclip.
	 */
	case GameClip;

	/**
	 * @return string Noun to use in API path.
	 */
	public function get_api_path(): string {
		return match( $this ) {
			Type::Screenshot => 'screenshots',
			Type::GameClip => 'gameClips',
		};
	}

	/**
	 * @return string Property name to use in main response.
	 */
	public function get_response_property(): string {
		return match( $this ) {
			Type::Screenshot => 'screenshots',
			Type::GameClip => 'gameClips',
		};
	}

	/**
	 * @return string Property name to use for URIs in individual captures.
	 */
	public function get_uri_property(): string {
		return match( $this ) {
			Type::Screenshot => 'screenshotUris',
			Type::GameClip => 'gameClipUris',
		};
	}

	/**
	 * @return string Property name to use for dates in individual captures.
	 */
	public function get_date_property(): string {
		return match( $this ) {
			Type::Screenshot => 'dateTaken',
			Type::GameClip => 'dateRecorded',
		};
	}

	/**
	 * @param DateTime $time Capture time.
	 * @return string Filename for capture.
	 */
	public function get_filename( DateTime $time ): string {
		return match( $this ) {
			Type::Screenshot => "{$time->format( 'Y-m-d-H-i-s' )}-screenshot.png",
			Type::GameClip => "{$time->format( 'Y-m-d-H-i-s' )}-gameclip.mp4",
		};
	}
}

new Xbox_Capture_Sync();
