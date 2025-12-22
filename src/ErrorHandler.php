<?php

namespace ZenithGram\ZenithGram;

use Closure;
use ErrorException;
use Throwable;
use ZenithGram\ZenithGram\Utils\EnvironmentDetector;

trait ErrorHandler
{
    private Closure|null $handler = null;
    private array|null $debug_chat_ids = null;
    private bool $short_trace = true;
    private string $pathFiler = '';
    private bool $isAlreadyExiting = false;

    /**
     * Отображать полный трейт или сокращенный
     *
     * @param bool $short_trace
     *
     * @return ZG
     *
     * @see https://zenithgram.github.io/classes/errorhandler#shortTrace
     */
    public function shortTrace(bool $short_trace): self
    {
        $this->short_trace = $short_trace;

        return $this;
    }

    /**
     * Устанавливает фильтр на путь к файлу, чтобы не отображать его
     *
     * @param string $filter "/path/to/your/project"
     *
     * @return ZG
     *
     * @see https://zenithgram.github.io/classes/errorhandler#setTracePathFilter
     */
    public function setTracePathFilter(string $filter): self
    {
        $this->pathFiler = $filter;

        return $this;
    }

    /**
     * Активирует дебаг режим
     *
     * Чтобы он работал, нужно задать обработчик, либо перечислить id, куда
     * будет отправлены ошибки
     *
     * @return ZG
     *
     * @see https://zenithgram.github.io/classes/errorhandler#enableDebug
     */
    public function enableDebug(): self
    {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleExceptionFatal']);
        register_shutdown_function([$this, 'handleShutdown']);

        return $this;
    }

    /**
     * Устанавливает ID, куда будут отправлены возникшие ошибки
     *
     * @param int|string|array $ids ID пользователя или чата
     *
     * @return ZG
     *
     * @see https://zenithgram.github.io/classes/errorhandler#setSendIds
     */
    public function setSendIds(int|string|array $ids): self
    {
        $this->debug_chat_ids = is_array($ids) ? $ids : [$ids];

        return $this;
    }

    /**
     * Устанавливает обработчик, который срабатывает при возникновении ошибки
     *
     * @param callable $handler Обработчик. Пример: function (ZG $tg, Throwable
     *                          $e)
     *
     * @return ZG
     *
     * @see https://zenithgram.github.io/classes/errorhandler#setHandler
     */
    public function setHandler(callable $handler): self
    {
        $this->handler = $handler(...);

        return $this;
    }

    private function handleError(int $severity, string $message, string $file,
        int $line,
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    private function handleShutdown(): void
    {
        if ($this->isAlreadyExiting) {
            return;
        }
        $error = error_get_last();
        if ($error
            && in_array(
                $error['type'],
                [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR],
            )
        ) {
            $this->handleExceptionFatal(
                new ErrorException(
                    $error['message'], 0, $error['type'], $error['file'],
                    $error['line'],
                ),
            );
        }
    }

    private function handleExceptionFatal(Throwable $e): void
    {
        $this->isAlreadyExiting = true;
        $this->reportException($e);
        exit(1);
    }

    public function reportException(Throwable $e): void
    {
        $className = (new \ReflectionClass($e))->getShortName();
        $message = $e->getMessage();

        [$userFile, $userLine, $isVendorError] = $this->findUserLocation($e);

        $cleanUserFile = $userFile;
        $cleanRealFile = $e->getFile();

        $trace = $this->renderTrace($e);
        $snippet = $this->getCodeSnippet($userFile, $userLine);

        if (EnvironmentDetector::isCli()) {
            $this->renderCliError(
                $className, $message, $cleanUserFile, $userLine, $cleanRealFile,
                $e->getLine(), $snippet, $trace['cli'],
            );
        }

        if ($this->debug_chat_ids) {
            $this->sendTelegramError(
                $className, $message, $cleanUserFile, $userLine, $cleanRealFile,
                $e->getLine(), $snippet, $trace['html'],
            );
        }

        if ($this->handler !== null) {
            ($this->handler)($this, $e);
        }
    }

    private function findUserLocation(Throwable $e): array
    {
        $realFile = $e->getFile();
        $realLine = $e->getLine();

        if (!$this->isVendorPath($realFile)) {
            return [$realFile, $realLine, false];
        }

        foreach ($e->getTrace() as $frame) {
            if (isset($frame['file']) && !$this->isVendorPath($frame['file'])) {
                return [$frame['file'], $frame['line'], true];
            }
        }

        return [$realFile, $realLine, true];
    }

    private function isVendorPath(string $path): bool
    {
        return str_contains(
                $path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR,
            )
            || str_contains($path, '/vendor/')
            || str_contains($path, '\\vendor\\');
    }

    private function renderCliError(string $type, string $msg, string $userFile,
        int $userLine, string $realFile, int $realLine, array $snippet,
        string $fullTrace,
    ): void {
        $reset = "\033[0m";
        $bgRed = "\033[41;1;37m";
        $yellow = "\033[1;33m";
        $cyan = "\033[36m";
        $gray = "\033[90m";
        $boldWhite = "\033[1;37m";

        echo PHP_EOL;

        echo " $bgRed$type $reset$boldWhite$msg$reset".PHP_EOL;

        echo " $cyan$userFile:$userLine$reset".PHP_EOL;

        if ($userFile !== $realFile) {
            echo " $gray(Inside: $realFile:$realLine)$reset".PHP_EOL;
        }

        echo PHP_EOL;

        foreach ($snippet as $num => $codeLine) {
            $cleanCode = str_replace(["\r", "\n"], '', $codeLine);

            if ($num === $userLine) {
                echo sprintf(
                        " %s > %s %s %-80s %s", $bgRed, $num, $reset,
                        $cleanCode, $reset,
                    ).PHP_EOL;
            } else {
                echo sprintf(" $gray   %s %s %s", $num, $reset, $cleanCode)
                    .PHP_EOL;
            }
        }

        echo PHP_EOL."$yellow Stack Trace: $reset".PHP_EOL.$gray.$fullTrace
            .$reset.PHP_EOL;
    }


    private function sendTelegramError(string $type, string $msg,
        string $userFile, int $userLine, string $realFile, int $realLine,
        array $snippet, string $trace,
    ): void {
        $esc = fn($s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE);

        $locationInfo
            = "<u>File:</u> <code>".$this->filteredFile($esc($userFile))
            .":{$userLine}</code>\n";
        if ($userFile !== $realFile) {
            $locationInfo .= "<i><u>Inside:</u><code>".$this->filteredFile(
                    $esc($realFile),
                ).":{$realLine}</code></i>\n";
        }

        $codeBlock = "";
        foreach ($snippet as $num => $codeLine) {
            $marker = ($num === $userLine) ? "👉 " : "   ";
            $codeLine = mb_strimwidth($codeLine, 0, 60, "...");
            $codeBlock .= "$marker$num: ".$esc($codeLine);
        }

        $token = $this->api->getToken();
        $first_chars_token = substr($token, 0, 3).'...';


        $html = "<b>🔥 Fatal Error: {$esc($type)}</b>\n\n".
            "<u>Message:</u> <b>{$esc($msg)}</b>\n".
            $locationInfo."\n".
            "<pre><code class=\"language-php\">".str_replace(
                $token, $first_chars_token, $codeBlock,
            )."</code></pre>\n\n".
            "<b>Stack Trace:</b>\n";

        if (mb_strlen($trace) > 2000) {
            $message_chunk = str_split($trace, 2000);
            $html .= "<pre>{$message_chunk[0]}</pre>";
            unset($message_chunk[0]);

            $chunks = array_chunk($message_chunk, 2);

            $mergedArray = [];
            foreach ($chunks as $chunk) {
                if (count($chunk) === 2) {
                    $mergedArray[] = $chunk[0].' '.$chunk[1];
                } else {
                    $mergedArray[] = $chunk[0];
                }
            }


        } else {
            $html .= "<pre><code class=\"language-bash\">{$trace}</code></pre>";
        }

        foreach ($this->debug_chat_ids as $chatId) {
            try {
                $this->api->callAPI(
                    'sendMessage', ['chat_id'    => $chatId, 'text' => $html,
                                    'parse_mode' => 'HTML'],
                );
                if (!empty($mergedArray)) {
                    foreach ($mergedArray as $message) {
                        $this->api->callAPI(
                            'sendMessage', ['chat_id'    => $chatId,
                                            'text'       => "<pre><code class=\"language-bash\">"
                                                .$message."</code></pre>",
                                            'parse_mode' => 'HTML'],
                        );
                    }
                }
            } catch (Throwable $t) {
                fwrite(STDERR, "Log send fail: ".$t->getMessage());
            }
        }
    }

    private function getCodeSnippet(string $file, int $line, int $padding = 5,
    ): array {
        if (!is_readable($file)) {
            return [];
        }
        $lines = file($file);
        $start = max(0, $line - $padding - 1);
        $slice = array_slice($lines, $start, ($line + $padding) - $start, true);
        $result = [];
        foreach ($slice as $i => $content) {
            $result[$i + 1] = $content;
        }

        return $result;
    }

    private function renderTrace(Throwable $e): array
    {
        $trace = "";
        $i = 0;
        foreach ($e->getTrace() as $item) {
            if ($this->checkTraceForCleaning($item) === true) {
                continue;
            }

            $fileRaw = $item['file'] ?? null;
            if ($fileRaw === null) {
                $file = '[internal]';
                $lineStr = '';
            } else {
                $file = $this->pathFiler !== '' ? $this->filteredFile($fileRaw)
                    : $fileRaw;
                $lineStr = "(".($item['line'] ?? '?').")";
            }

            $class = $item['class'] ?? '';
            $type = $item['type'] ?? '';
            $function = $item['function'];

            if (str_contains($function, '{closure')) {
                $function = '{closure}';
            }

            $separator = $lineStr ? " " : ": ";

            $trace .= "#".$i.' '.$file.$lineStr.$separator
                .$class.$type.$function."()\n";

            $i++;
        }

        if (empty($trace) && $this->short_trace) {
            $trace
                = "All stack frames were inside /vendor/ (Internal framework error). \nSet ->shortTrace(false) to see full details.";
        }

        return ['html' => htmlspecialchars($trace), 'cli' => $trace];
    }

    private function checkTraceForCleaning(array $item): bool
    {
        if ($this->short_trace === false) {
            return false;
        }

        $file = $item['file'] ?? '';
        $class = $item['class'] ?? '';

        if ($file !== ''
            && (stripos($file, 'vendor/') !== false
                || stripos(
                    $file, 'vendor\\',
                ) !== false)
        ) {
            return true;
        }

        if (str_starts_with($class, 'Revolt\\')
            || (str_starts_with(
                    $class, 'ZenithGram\ZenithGram\\',
                )
                && $file === '')
            || str_starts_with($class, 'Amp\\')
            || str_contains($class, 'Fiber')
        ) {
            return true;
        }

        if (str_ends_with($class, 'ErrorHandler')) {
            return true;
        }

        return false;
    }

    private function filteredFile(string|null $file): string
    {
        if ($file === null) {
            return '?';
        }

        return str_replace($this->pathFiler, '...', $file);
    }

}