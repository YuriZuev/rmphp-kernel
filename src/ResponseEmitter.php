<?php

namespace Rmphp\Kernel;
use Psr\Http\Message\ResponseInterface;

class ResponseEmitter {

	private int $responseChunkSize;

	/**
	 * ResponseEmitter constructor.
	 * @param int $responseChunkSize
	 */
	public function __construct(int $responseChunkSize = 4096)
	{
		$this->responseChunkSize = $responseChunkSize;
	}

	/**
	 * @param ResponseInterface $response
	 */
	public function emit(ResponseInterface $response): void
	{
		$isEmpty = $this->isResponseEmpty($response);
		if (headers_sent() === false) {
			$this->emitStatusLine($response);
			$this->emitHeaders($response);
		}

		if (!$isEmpty) {
			$this->emitBody($response);
		}
	}

	/**
	 * @param ResponseInterface $response
	 */
	private function emitHeaders(ResponseInterface $response): void
	{
		foreach ($response->getHeaders() as $name => $values) {
			$first = strtolower($name) !== 'set-cookie';
			foreach ($values as $value) {
				$header = sprintf('%s: %s', $name, $value);
				header($header, $first);
				$first = false;
			}
		}
	}

	/**
	 * @param ResponseInterface $response
	 */
	private function emitStatusLine(ResponseInterface $response): void
	{
		$statusLine = sprintf(
			'HTTP/%s %s %s',
			$response->getProtocolVersion(),
			$response->getStatusCode(),
			$response->getReasonPhrase()
		);
		header($statusLine, true, $response->getStatusCode());
	}

	/**
	 * @param ResponseInterface $response
	 */
	private function emitBody(ResponseInterface $response): void
	{
		$body = $response->getBody();
		if ($body->isSeekable()) {
			$body->rewind();
		}

		$amountToRead = (int) $response->getHeaderLine('Content-Length');
		if (!$amountToRead) {
			$amountToRead = $body->getSize();
		}

		if ($amountToRead) {
			while ($amountToRead > 0 && !$body->eof()) {
				$length = min($this->responseChunkSize, $amountToRead);
				$data = $body->read($length);
				echo $data;

				$amountToRead -= strlen($data);

				if (connection_status() !== CONNECTION_NORMAL) {
					break;
				}
			}
		} else {
			while (!$body->eof()) {
				echo $body->read($this->responseChunkSize);
				if (connection_status() !== CONNECTION_NORMAL) {
					break;
				}
			}
		}
	}

	/**
	 * @param ResponseInterface $response
	 * @return bool
	 */
	public function isResponseEmpty(ResponseInterface $response): bool
	{
		if (in_array($response->getStatusCode(), [204, 205, 304], true)) {
			return true;
		}
		$stream = $response->getBody();
		$seekable = $stream->isSeekable();
		if ($seekable) {
			$stream->rewind();
		}
		return $seekable ? $stream->read(1) === '' : $stream->eof();
	}
}