<?php

namespace Illuminate\Http\Client;

use Illuminate\Http\Response;

/**
 * Chainable HTTP request builder that mimics Laravel's PendingRequest.
 *
 * All calls eventually delegate to wp_remote_post() / wp_remote_get().
 *
 * @author  Knit Pay
 * @version 1.0.0
 */
class PendingRequest {

	/** @var array Headers. */
	private $headers = [];

	/** @var string|null Bearer token. */
	private $token;

	/** @var bool Whether to disable SSL verification. */
	private $withoutVerifying = false;

	/** @var array Additional WordPress HTTP args. */
	private $options = [];

	/** @var string|null Raw body content. */
	private $body = null;

	/** @var string|null Content type for raw body. */
	private $contentType = null;

	/** @var bool Whether to send as application/x-www-form-urlencoded. */
	private $asForm = false;

	/**
	 * Add headers to the request.
	 *
	 * @param array $headers
	 * @return $this
	 */
	public function withHeaders( $headers ) {
		$this->headers = array_merge( $this->headers, $headers );
		return $this;
	}

	/**
	 * Set a Bearer token.
	 *
	 * @param string $token
	 * @return $this
	 */
	public function withToken( $token ) {
		$this->token = $token;
		return $this;
	}

	/**
	 * Disable SSL verification.
	 *
	 * @return $this
	 */
	public function withoutVerifying() {
		$this->withoutVerifying = true;
		return $this;
	}

	/**
	 * Set WordPress HTTP options.
	 *
	 * @param array $options
	 * @return $this
	 */
	public function withOptions( $options ) {
		$this->options = array_merge( $this->options, $options );
		return $this;
	}

	/**
	 * Set a raw body payload.
	 *
	 * @param string $body
	 * @param string $contentType
	 * @return $this
	 */
	public function withBody( $body, $contentType ) {
		$this->body        = $body;
		$this->contentType = $contentType;
		return $this;
	}

	/**
	 * Mark the request as form-encoded.
	 *
	 * @return $this
	 */
	public function asForm() {
		$this->asForm = true;
		return $this;
	}

	/**
	 * Send a POST request.
	 *
	 * @param string $url
	 * @param array|string $data
	 * @return Response
	 */
	public function post( $url, $data = [] ) {
		$args = $this->buildArgs( 'POST', $data );
		return $this->send( 'POST', $url, $args );
	}

	/**
	 * Send a GET request.
	 *
	 * @param string $url
	 * @param array|string $query
	 * @return Response
	 */
	public function get( $url, $query = [] ) {
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}
		$args = $this->buildArgs( 'GET' );
		return $this->send( 'GET', $url, $args );
	}

	// ----------------------------------------------------------------
	// Internals
	// ----------------------------------------------------------------

	/**
	 * Build WordPress HTTP args array.
	 *
	 * @param string       $method
	 * @param array|string $data
	 * @return array
	 */
	private function buildArgs( $method, $data = [] ) {
		$args = array_merge(
			[
				'timeout'     => 30,
				'redirection' => 0,
				'sslverify'   => ! $this->withoutVerifying,
			],
			$this->options
		);

		$args['method'] = $method;

		$headers = $this->headers;

		if ( null !== $this->token ) {
			$headers['Authorization'] = 'Bearer ' . $this->token;
		}

		if ( null !== $this->body ) {
			$args['body'] = $this->body;
			if ( null !== $this->contentType ) {
				$headers['Content-Type'] = $this->contentType;
			}
		} elseif ( $this->asForm && is_array( $data ) ) {
			$args['body'] = $data; // WordPress handles form-encoding for array bodies
		} elseif ( is_array( $data ) && ! empty( $data ) ) {
				// If the caller already set Content-Type to JSON we must encode the body.
			if ( isset( $headers['Content-Type'] ) && false !== strpos( $headers['Content-Type'], 'application/json' ) ) {
				$args['body'] = json_encode( $data );
			} else {
				$args['body'] = $data;
			}
		} elseif ( is_string( $data ) ) {
			$args['body'] = $data;
		}

		if ( ! isset( $headers['Content-Type'] ) && is_array( $data ) && ! empty( $data ) && null === $this->body ) {
			if ( $this->asForm ) {
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
			} else {
				$args['body']            = json_encode( $data );
				$headers['Content-Type'] = 'application/json';
			}
		}

		if ( ! empty( $headers ) ) {
			$args['headers'] = $headers;
		}

		return $args;
	}

	/**
	 * Execute the request via WordPress HTTP API.
	 *
	 * @param string $method
	 * @param string $url
	 * @param array  $args
	 * @return Response
	 */
	private function send( $method, $url, $args ) {
		// Normalize URL to remove accidental double slashes (e.g. https://host//path).
		$url = preg_replace( '#(?<!:)/+#', '/', $url );

		if ( 'GET' === $method ) {
			$response = wp_remote_get( $url, $args );
		} else {
			$response = wp_remote_post( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			throw new \Exception(
				'Nafezly HTTP request failed: ' . $response->get_error_message()
			);
		}

		$body    = wp_remote_retrieve_body( $response );
		$headers = wp_remote_retrieve_headers( $response );
		$status  = wp_remote_retrieve_response_code( $response );

		return new Response(
			$body,
			is_array( $headers ) ? $headers : [],
			(int) $status
		);
	}
}
