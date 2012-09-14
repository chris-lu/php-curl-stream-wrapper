<?php

/**
 * HTTPCurlStream http/https stream wrapper for PHP core
 * Handle curl requests with standard calls (fileopen, file_get_contents, etc.)
 */

/**
 * 
 * Authors :		Christophe Dri 
 * Inspired by:		Thomas Rabaix 
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */
 
class HTTPCurlStream {

	private $path;
	private $mode;
	private $options;
	private $opened_path;
	private $buffer;
	private $pos;
  public $context;

	/**
	 * Open the stream 
	 *
	 * @param unknown_type $path
	 * @param unknown_type $mode
	 * @param unknown_type $options
	 * @param unknown_type $opened_path
	 * @return unknown
	 */
	public function stream_open($path, $mode, $options, &$opened_path) {
		$this->path = $path;
		$this->mode = $mode;
		$this->options = $options;
		$this->opened_path = $opened_path;
		$this->createBuffer($path);
		return true;
	}

	/**
	 * Close the stream
	 *
	 */
	public function stream_close() {
		curl_close($this->ch);
	}

	/**
	 * Read the stream
	 *
	 * @param int $count number of bytes to read
	 * @return content from pos to count
	 */
	public function stream_read($count) {
		if (strlen($this->buffer) == 0) {
			return false;
		}
		$read = substr($this->buffer, $this->pos, $count);
		$this->pos += $count;
		return $read;
	}

	/**
	 * write the stream
	 *
	 * @param int $count number of bytes to read
	 * @return content from pos to count
	 */
	public function stream_write($data) {
		if (strlen($this->buffer) == 0) {
			return false;
		}
		return true;
	}

	/**
	 *
	 * @return true if eof else false
	 */
	public function stream_eof() {
		if ($this->pos >= strlen($this->buffer)) {
			return true;
		}
		return false;
	}

	/**
	 * @return int the position of the current read pointer
	 */
	public function stream_tell() {
		return $this->pos;
	}

	/**
	 * Flush stream data
	 */
	public function stream_flush() {
		$this->buffer = null;
		$this->pos = null;
	}

	/**
	 * Stat the file, return only the size of the buffer
	 *
	 * @return array stat information
	 */
	public function stream_stat() {
		$this->createBuffer($this->path);
		$stat = array(
			'size' => strlen($this->buffer),
		);
		return $stat;
	}

	/**
	 * Stat the url, return only the size of the buffer
	 *
	 * @return array stat information
	 */
	public function url_stat($path, $flags) {
		$this->createBuffer($path);
		$stat = array(
			'size' => strlen($this->buffer),
		);
		return $stat;
	}

	/**
	 * Create the buffer by requesting the url through cURL
	 *
	 * @param unknown_type $path
	 */
	private function createBuffer($path) {
		if ($this->buffer)
			return;

		$options = stream_context_get_options($this->context);

		if (!empty($options['http']['curl_options']) && is_array($options['http']['curl_options']))
			$curl_options = $options['http']['curl_options'];
		else
			$curl_options = array();

		$curl_options = array_replace(array(
			CURLOPT_FAILONERROR => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => 'PHP / HTTPCurlStream',
			CURLOPT_URL => $path,
			), $curl_options);

		if (defined('USE_PROXY') && USE_PROXY) {
			$curl_options[CURLOPT_HTTPPROXYTUNNEL] = true;
			$curl_options[CURLOPT_PROXY] = USE_PROXY;
		}

		$this->ch = curl_init();
		curl_setopt_array($this->ch, $curl_options);

		$this->buffer = curl_exec($this->ch);
		$this->pos = 0;
	}

}

$wrappers = stream_get_wrappers();

// Not specifying the STREAM_IS_URL parameters allow us to bypass limitations of allow_url_fopen = 0
if (array_search('http', $wrappers) !== false)
	stream_wrapper_unregister('http');
stream_wrapper_register('http', 'HTTPCurlStream') or die("Failed to register HTTP protocol.");

if (array_search('https', $wrappers) !== false)
	stream_wrapper_unregister('https');
stream_wrapper_register('https', 'HTTPCurlStream') or die("Failed to register HTTPS protocol.");
