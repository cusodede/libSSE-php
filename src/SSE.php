<?php
declare(strict_types=1);

namespace cusodede\sse;

use Yii;
use yii\web\Request;
use yii\web\Response;

/**
 * Class SSE
 * @package cusodede\sse
 */
class SSE
{
    /**
     * Seconds to sleep after the data has been sent.
     * @var float
     */
    public float $sleepTime = 0.5;
    /**
     * The time limit of the script in seconds.
     * @var float|int
     */
    public float $execLimit = 600;
    /**
     * The time client to reconnect after connection has lost in seconds.
     * @var int
     */
    public int $reconnectTime = 1;
    /**
     * The interval of sending a signal to keep the connection alive.
     * @var int
     */
    public int $connectionSignalInterval = 300;
    /**
     * Allow Cross-Origin Access.
     * @var bool
     */
    public bool $allowCors = false;
    /**
     * Allow chunked encoding.
     * @var bool
     */
    public bool $chunkedEncoding = false;
    /**
     * @var int
     */
    private int $_id = 0;
    /**
     * @var int
     */
    private int $_start = 0;
    /**
     * Flag indicates whether the user reconnects.
     * @var bool
     */
    private bool $_isReconnect;
    /**
     * @var Event[]
     */
    private array $_handlers = [];

    /**
     * SSE constructor.
     * @param Request|null $request
     */
    public function __construct(Request $request = null)
    {
        //if the HTTP header 'Last-Event-ID' is set
        //then it's a reconnect from the client

        if ($request === null) {
            $request = Yii::$app->request;
        }

        if (null !== $lastId = $request->headers->get('Last-Event-ID')) {
            $this->_id = (int) $lastId;
            $this->_isReconnect = true;
        }
    }

    /**
     * @param string $name
     * @param Event $event
     */
    final public function addEventListener(string $name, Event $event): void
    {
        $this->_handlers[$name] = $event;
    }

    /**
     * @param string $name
     */
    final public function removeEventListener(string $name): void
    {
        unset($this->_handlers[$name]);
    }

    /**
     * @return Event[]
     */
    final public function getEventListeners(): array
    {
        return $this->_handlers;
    }

    /**
     * @return bool
     */
    final public function hasEventListeners(): bool
    {
        return [] !== $this->_handlers;
    }

    /**
     * @return bool
     */
    final public function getIsReconnect(): bool
    {
        return $this->_isReconnect;
    }

    public function start(): void
    {
        $response = $this->createResponse();
        $response->send();
    }

    /**
     * @return Response
     */
    public function createResponse(): Response
    {
        $this->init();

        $callback = function () {
            $this->setStart(time());

            echo 'retry: ' . ($this->reconnectTime * 1000) . "\n";    // Set the retry interval for the client
            while (true) {
                // Leave the loop if there are no more handlers
                if (!$this->hasEventListeners()) {
                    break;
                }

                if ($this->isTick()) {
                    // No updates needed, send a comment to keep the connection alive.
                    // From https://developer.mozilla.org/en-US/docs/Server-sent_events/Using_server-sent_events
                    echo ': ' . sha1((string)mt_rand()) . "\n\n";
                }

                // Start to check for updates
                foreach ($this->getEventListeners() as $event => $handler) {
                    if ($handler->check()) { // Check if the data is available
                        $this->sendBlock($this->getNewId(), $handler->update(), $event);

                        // Make sure the data has been sent to the client
                        $this->flush();
                    }
                }

                // Break if the time exceed the limit
                if ($this->execLimit !== 0 && $this->getUptime() > $this->execLimit) {
                    break;
                }

                $this->sleep();
            }
        };

        $response = new Response();
        $response->stream = $callback;
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        if ($this->allowCors) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->chunkedEncoding) {
            $response->headers->set('Transfer-encoding', 'chunked');
        }

        return $response;
    }

    private function setStart(int $value): void
    {
        $this->_start = $value;
    }

    private function init(): void
    {
        set_time_limit(0); // Disable time limit

        // Prevent buffering
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', 1);
        }

        ini_set('zlib.output_compression', '0');
        ini_set('implicit_flush', '1');

        while (ob_get_level() !== 0) {
            ob_end_flush();
        }

        ob_implicit_flush();
    }

    private function flush(): void
    {
        @ob_flush();
        @flush();
    }

    /**
     * @param string $content
     */
    private function send(string $content): void
    {
        print($content);
    }

    /**
     * @param int $id
     * @param string $data
     * @param string|null $name
     */
    private function sendBlock(int $id, string $data, ?string $name = null): void
    {
        $this->send("id: $id\n");
        if (!empty($name)) {
            $this->send("event: $name\n");
        }

        $this->send($this->wrapData($data) . "\n\n");
    }

    /**
     * @param string $string
     * @return string
     */
    private function wrapData(string $string): string
    {
        return 'data:' . str_replace("\n", "\ndata: ", $string);
    }

    /**
     * @return int
     */
    private function getUptime(): int
    {
        return time() - $this->_start;
    }

    /**
     * @return bool
     */
    private function isTick(): bool
    {
        return $this->getUptime() % $this->connectionSignalInterval === 0;
    }

    private function sleep(): void
    {
        usleep((int) ($this->sleepTime * 1000000));
    }

    /**
     * @return int
     */
    private function getNewId(): int
    {
        return ++$this->_id;
    }
}
