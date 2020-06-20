<?php

namespace PhpMimeMailParser;

/**
 * Mime Part
 * Represents the results of mailparse_msg_get_part_data()
 *
 * Note ArrayAccess::offsetSet() cannot modify deeply nestated arrays.
 * When modifying use getPart() and setPart() for deep nested data modification
 *
 * @example
 *
 *     $MimePart['headers']['from'] = 'modified@example.com' // fails
 *
 *     // correct
 *     $part = $MimePart->getPart();
 *     $part['headers']['from'] = 'modified@example.com';
 *     $MimePart->setPart($part);
 */
final class MimePart implements \ArrayAccess
{
    /**
     * Internal mime part
     *
     * @var array
     */
    protected $part = [];

    /**
     * Immutable Part Id
     *
     * @var string
     */
    private $id;
    private $stream;
    private $data;

    /**
     * Create a mime part
     *
     * @param array $part
     * @param string $id
     */
    public function __construct($id, array $part, $stream = null, $data = null)
    {
        $this->part = $part;
        $this->id = $id;
        $this->stream = $stream;
        $this->data = $data;
        $this->charset = new Charset();
        $this->ctDecoder = new ContentTransferDecoder();
        $this->headerDecoder = new MimeHeaderDecoder($this->charset, $this->ctDecoder);
    }

    /**
     * Retrieve the part Id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Retrieve the part data
     *
     * @return array
     */
    public function getPart()
    {
        return $this->part;
    }

    /**
     * Set the mime part data
     *
     * @param array $part
     * @return void
     */
    public function setPart(array $part)
    {
        $this->part = $part;
    }

    /**
     * ArrayAccess
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->part[] = $value;
            return;
        }
        $this->part[$offset] = $value;
    }

    /**
     * ArrayAccess
     */
    public function offsetExists($offset)
    {
        return isset($this->part[$offset]);
    }

    /**
     * ArrayAccess
     */
    public function offsetUnset($offset)
    {
        unset($this->part[$offset]);
    }

    /**
     * ArrayAccess
     */
    public function offsetGet($offset)
    {
        return isset($this->part[$offset]) ? $this->part[$offset] : null;
    }

    private function getField($type)
    {
        if (array_key_exists($type, $this->part)) {
            return $this->part[$type];
        }

        return null;
    }

    public function getDispositionFileName()
    {
        return $this->getField('disposition-filename');
    }

    public function getContentName()
    {
        return $this->getField('content-name');
    }

    public function getContentType()
    {
        return $this->getField('content-type');
    }

    public function getContentDisposition()
    {
        return $this->getField('content-disposition');
    }

    public function getContentId()
    {
        return $this->getField('content-id');
    }

    public function getContentTransferEncoding()
    {
        return $this->getField('transfer-encoding');
    }

    public function getHeaders()
    {
        $headers = $this->getHeadersRaw();
        array_walk_recursive($headers, function (&$value) {
            $value = $this->headerDecoder->decodeHeader($value);
        });
        return $headers;
    }

    public function getHeadersRaw()
    {
        return $this->getField('headers');
    }

    public function getHeader($name)
    {
        $raw = $this->getHeaderRaw($name);
        if ($raw == null) {
            return null;
        }
        return $this->headerDecoder->decodeHeader($raw);
    }

    public function getHeaderRaw($name)
    {
        $name = strtolower($name);
        $headers = $this->getField('headers');

        if (!array_key_exists($name, $headers)) {
            return null;
        }

        if (\is_array($headers[$name])) {
            return $headers[$name][0];
        }

        return $headers[$name];
    }

    public function getStartingPositionBody()
    {
        return $this->getField('starting-pos-body');
    }

    public function getEndingPositionBody()
    {
        return $this->getField('ending-pos-body');
    }

    public function getStartingPosition()
    {
        return $this->getField('starting-pos');
    }

    public function getEndingPosition()
    {
        return $this->getField('ending-pos');
    }

    public function getCharset()
    {
        return $this->getField('charset');
    }

    public function setCharsetManager($charsetManager)
    {
        $this->charset = $charsetManager;
    }

    public function setContentTransferEncodingManager($contentTransferEncodingManager)
    {
        $this->ctDecoder = $contentTransferEncodingManager;
    }

    public function setMimeHeaderEncodingManager($mimeHeaderEncodingManager)
    {
        $this->headerDecoder = $mimeHeaderEncodingManager;
    }


    public function getCompleteBody()
    {
        $start = $this->getStartingPosition();
        $end = $this->getEndingPosition();

        if ($start >= $end) {
            return '';
        }

        if ($this->stream) {
            fseek($this->stream, $start, SEEK_SET);

            return fread($this->stream, $end - $start);
        }

        return substr($this->data, $start, $end - $start);
    }

    public function getBody()
    {
        $start = $this->getStartingPositionBody();
        $end = $this->getEndingPositionBody();

        if ($start >= $end) {
            return '';
        }

        if ($this->stream) {
            fseek($this->stream, $start, SEEK_SET);

            return fread($this->stream, $end - $start);
        }

        return substr($this->data, $start, $end - $start);
    }

    public function isTextMessage($subType)
    {
        $disposition = $this->getContentDisposition();
        $contentType = $this->getContentType();

        if ($disposition == 'inline' || empty($disposition)) {
            if ($contentType == 'text/'.$subType) {
                return true;
            }
        }
        return false;
    }

    public function decoded()
    {
        $undecodedBody = $this->ctDecoder->decodeContentTransfer(
            $this->getBody(),
            $this->getContentTransferEncoding()
        );
        return $this->charset->decodeCharset($undecodedBody, $this->getCharset());
    }
}
