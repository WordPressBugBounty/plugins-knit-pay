<?php

namespace Illuminate\Contracts\View;

/**
 * Minimal WordPress-compatible View object.
 *
 * Replaces Blade.  It stores a file path + data array and renders
 * the file on demand via render().
 *
 * @author  Knit Pay
 * @version 1.0.0
 */
class View {

	/** @var string Absolute path to the PHP template. */
	private $file;

	/** @var array Data array. */
	private $data;

	/**
	 * @param string $file
	 * @param array  $data
	 */
	public function __construct( $file, $data = [] ) {
		$this->file = $file;
		$this->data = $data;
	}

	/**
	 * Render the template and return the output string.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! file_exists( $this->file ) ) {
			throw new \RuntimeException( 'Missing Nafezly template: ' . $this->file );
		}

		ob_start();
		extract( $this->data, EXTR_SKIP );
		include $this->file;
		return ob_get_clean();
	}
}
