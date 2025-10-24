<?php
/**
 * Base adapter for portal integrations.
 *
 * @package EstateOffice\Portals
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Represent result of a portal export attempt.
 */
class EstateOffice_Portal_Result {

    /**
     * Whether the export succeeded.
     *
     * @var bool
     */
    protected $success;

    /**
     * Human readable message.
     *
     * @var string
     */
    protected $message;

    /**
     * Additional context for logging.
     *
     * @var array<string,mixed>
     */
    protected $context;

    /**
     * Severity of failure (temporary/permanent/throttled).
     *
     * @var string
     */
    protected $severity;

    /**
     * Suggested retry delay in seconds.
     *
     * @var int|null
     */
    protected $retry_after;

    /**
     * Constructor.
     */
    public function __construct( bool $success, string $message = '', array $context = [], string $severity = 'temporary', ?int $retry_after = null ) {
        $this->success     = $success;
        $this->message     = $message;
        $this->context     = $context;
        $this->severity    = $severity;
        $this->retry_after = $retry_after ? max( 0, (int) $retry_after ) : null;
    }

    /**
     * Build success result.
     */
    public static function success( string $message = '', array $context = [] ): self {
        return new self( true, $message, $context, 'success' );
    }

    /**
     * Build failure result.
     */
    public static function failure( string $message = '', array $context = [], string $severity = 'temporary', ?int $retry_after = null ): self {
        $severity = in_array( $severity, [ 'temporary', 'permanent', 'throttled' ], true ) ? $severity : 'temporary';

        return new self( false, $message, $context, $severity, $retry_after );
    }

    /**
     * Whether export succeeded.
     */
    public function is_success(): bool {
        return $this->success;
    }

    /**
     * Retrieve message.
     */
    public function get_message(): string {
        return $this->message;
    }

    /**
     * Retrieve context payload.
     *
     * @return array<string,mixed>
     */
    public function get_context(): array {
        return $this->context;
    }

    /**
     * Retrieve failure severity.
     */
    public function get_severity(): string {
        return $this->severity;
    }

    /**
     * Suggested retry delay.
     */
    public function get_retry_after(): ?int {
        return $this->retry_after;
    }
}

/**
 * Abstract adapter describing a portal integration.
 */
abstract class EstateOffice_Portal_Adapter {

    /**
     * Raw portal record from configuration table.
     *
     * @var array<string,mixed>
     */
    protected $portal;

    /**
     * Adapter constructor.
     *
     * @param array<string,mixed> $portal Portal row.
     */
    public function __construct( array $portal ) {
        $this->portal = $portal;
    }

    /**
     * Return canonical portal slug.
     */
    public function get_slug(): string {
        return sanitize_title( $this->portal['slug'] ?? '' );
    }

    /**
     * Access underlying portal data.
     *
     * @return array<string,mixed>
     */
    public function get_portal(): array {
        return $this->portal;
    }

    /**
     * Send payload to remote integration.
     *
     * @param array<string,mixed> $payload Prepared property payload.
     */
    abstract public function send( array $payload ): EstateOffice_Portal_Result;

    /**
     * Allow adapters to adjust payload prior to sending.
     *
     * @param array<string,mixed> $payload Raw property payload.
     * @return array<string,mixed>
     */
    public function prepare_payload( array $payload ): array {
        return $payload;
    }
}
