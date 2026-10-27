<?php
// No WordPress or network access: every invalid source must fail locally.
define( 'ABSPATH', __DIR__ );
class WP_Error {
    public function __construct( public $code, public $message = '', public $data = [] ) {}
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function __( $s, $domain = '' ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( $s ) ); }
function wp_get_attachment_metadata( $id ) { return []; }
function get_attached_file( $id ) { return false; }
function wp_get_attachment_url( $id ) { return $GLOBALS['image_url']; }
function get_the_title( $id ) { return ''; }
function wp_get_attachment_caption( $id ) { return ''; }
function wp_basename( $s ) { return basename( $s ); }
function wp_parse_url( $s, $part = -1 ) { return parse_url( $s, $part ); }
function wp_http_validate_url( $s ) { return preg_match( '#^https?://public\.example/#', $s ) ? $s : false; }
function wp_remote_request( ...$args ) { throw new RuntimeException( 'Invalid input reached the backend' ); }
require dirname( __DIR__, 2 ) . '/includes/class-api-client-v2.php';
$reflection = new ReflectionClass( BeepBeepAI\AltTextGenerator\API_Client_V2::class );
$client = $reflection->newInstanceWithoutConstructor();
foreach ( [ '', 'file:///etc/passwd', 'ftp://public.example/image.png', 'http://127.0.0.1/private.png' ] as $url ) {
    $GLOBALS['image_url'] = $url;
    foreach ( [ $client->build_alt_text_job_item( 1, [] ), $client->review_alt_text( 1, 'Existing alt' ) ] as $result ) {
        if ( ! $result instanceof WP_Error || 'invalid_image_source' !== $result->code ) { throw new RuntimeException( 'Invalid image source was accepted' ); }
    }
}
$method = $reflection->getMethod( 'should_retry_api_error' );
foreach ( [ 'auth_required', 'invalid_license', 'license_required' ] as $code ) {
    if ( $method->invoke( $client, new WP_Error( $code, '', [ 'status_code' => 401 ] ) ) ) { throw new RuntimeException( 'Authentication error retried' ); }
}
if ( $method->invoke( $client, new WP_Error( 'server_error', '', [ 'status_code' => 502, 'retryable' => false ] ) ) ) { throw new RuntimeException( 'Explicit nonretryable failure retried' ); }
echo "PASS: invalid sources blocked for bulk and review; authentication and nonretryable errors are not retried\n";
