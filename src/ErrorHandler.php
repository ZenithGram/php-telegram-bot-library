<?php

namespace ZenithGram\ZenithGram;

use ErrorException;
use Throwable;
use ZenithGram\ZenithGram\Utils\EnvironmentDetector;

trait ErrorHandler
{
    private array|null $debug_chat_ids = null;
    private bool $short_trace = true;
    private bool $isAlreadyExiting = false;

    public function enableDebug(int|array $adminIds): self
    {
        $this->setDebugIds($adminIds);

        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleExceptionFatal']);
        register_shutdown_function([$this, 'handleShutdown']);

        return $this;
    }

    public function setDebugIds(int|array $adminIds): self
    {
        $this->debug_chat_ids = is_array($adminIds) ? $adminIds : [$adminIds];
        return $this;
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleShutdown(): void
    {
        if ($this->isAlreadyExiting) return;
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleExceptionFatal(new ErrorException(
                $error['message'], 0, $error['type'], $error['file'], $error['line']
            ));
        }
    }

    public function handleExceptionFatal(Throwable $e): void
    {
        $this->isAlreadyExiting = true;
        $this->reportException($e);
        exit(1);
    }

    public function reportException(Throwable $e): void
    {
        $className = (new \ReflectionClass($e))->getShortName();
        $message = $e->getMessage();

        // --- ГЛАВНОЕ ИЗМЕНЕНИЕ ---
        // Ищем место вызова в пользовательском коде
        [$userFile, $userLine, $isVendorError] = $this->findUserLocation($e);

        // Чистим пути для красоты
        $cleanUserFile = $this->cleanPath($userFile);
        $cleanRealFile = $this->cleanPath($e->getFile());

        $trace = $this->renderTrace($e);
        // Сниппет берем ИЗ ПОЛЬЗОВАТЕЛЬСКОГО ФАЙЛА
        $snippet = $this->getCodeSnippet($userFile, $userLine);

        if (EnvironmentDetector::isCli()) {
            $this->renderCliError($className, $message, $cleanUserFile, $userLine, $cleanRealFile, $e->getLine(), $snippet, $e->getTraceAsString());
        }

        if ($this->debug_chat_ids) {
            $this->sendTelegramError($className, $message, $cleanUserFile, $userLine, $cleanRealFile, $e->getLine(), $snippet, $trace);
        }
    }

    /**
     * Ищет первый файл в трейсе, который НЕ находится в папке vendor.
     * Возвращает [файл, строка, ошибка_ли_в_вендоре]
     */
    private function findUserLocation(Throwable $e): array
    {
        $realFile = $e->getFile();
        $realLine = $e->getLine();

        // Если сама ошибка произошла НЕ в vendor, возвращаем её как есть
        if (!$this->isVendorPath($realFile)) {
            return [$realFile, $realLine, false];
        }

        // Если ошибка внутри vendor, ищем, кто её вызвал из нашего кода
        foreach ($e->getTrace() as $frame) {
            if (isset($frame['file']) && !$this->isVendorPath($frame['file'])) {
                return [$frame['file'], $frame['line'], true];
            }
        }

        // Если не нашли (например, ошибка глубоко в vendor и вызвана фреймворком), возвращаем оригинал
        return [$realFile, $realLine, true];
    }

    private function isVendorPath(string $path): bool
    {
        return str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
            || str_contains($path, '/vendor/')
            || str_contains($path, '\\vendor\\');
    }

    private function renderCliError(string $type, string $msg, string $userFile, int $userLine, string $realFile, int $realLine, array $snippet, string $fullTrace): void
    {
        // ЦВЕТОВАЯ ПАЛИТРА
        $reset      = "\033[0m";
        // Красный фон + Жирный белый текст (максимальный контраст)
        $bgRed      = "\033[41;1;37m";
        // Ярко-желтый
        $yellow     = "\033[1;33m";
        // Голубой
        $cyan       = "\033[36m";
        // Темно-серый (для неактивного кода)
        $gray       = "\033[90m";
        // Жирный белый (для текста ошибки)
        $boldWhite  = "\033[1;37m";

        echo PHP_EOL;
        // Заголовок ошибки: Красный фон с белым текстом
        echo " $bgRed$type $reset$boldWhite$msg$reset" . PHP_EOL;

        // Файл и строка
        echo " $cyan$userFile:$userLine$reset" . PHP_EOL;

        // Если ошибка реально произошла в библиотеке
        if ($userFile !== $realFile) {
            echo " $gray(Inside: $realFile:$realLine)$reset" . PHP_EOL;
        }

        echo PHP_EOL;

        // Вывод кода
        foreach ($snippet as $num => $codeLine) {
            // Убираем переносы строк из самого кода
            $cleanCode = str_replace(["\r", "\n"], '', $codeLine);

            if ($num === $userLine) {
                // АКТИВНАЯ СТРОКА: Красный фон, жирный белый текст
                echo sprintf(" %s > %s %s %-80s %s", $bgRed, $num, $reset, $cleanCode, $reset) . PHP_EOL;
            } else {
                // ОБЫЧНАЯ СТРОКА: Серый номер, обычный код
                echo sprintf(" $gray   %s %s %s", $num, $reset, $cleanCode) . PHP_EOL;
            }
        }

        echo PHP_EOL . "$yellow Stack Trace: $reset" . PHP_EOL . $gray . $fullTrace . $reset . PHP_EOL;
    }


    private function sendTelegramError(string $type, string $msg, string $userFile, int $userLine, string $realFile, int $realLine, array $snippet, string $trace): void
    {
        $esc = fn($s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE);

        $locationInfo = "<u>File:</u> <code>{$esc($userFile)}:{$userLine}</code>\n";
        if ($userFile !== $realFile) {
            $locationInfo .= "<i>(Inside: {$esc($realFile)}:{$realLine})</i>\n";
        }

        $codeBlock = "";
        foreach ($snippet as $num => $codeLine) {
            $marker = ($num === $userLine) ? "👉 " : "   ";
            $codeLine = mb_strimwidth($codeLine, 0, 60, "...");
            $codeBlock .= "$marker$num: " . $esc($codeLine);
        }

        $html = "<b>🔥 Fatal Error: {$esc($type)}</b>\n\n" .
            "<u>Message:</u> <b>{$esc($msg)}</b>\n" .
            $locationInfo . "\n" .
            "<pre><code class=\"language-php\">{$codeBlock}</code></pre>\n\n" .
            "<b>Stack Trace:</b>\n<pre>{$trace}</pre>";

        if (mb_strlen($html) > 4000) $html = mb_substr($html, 0, 3900) . "\n\n... (truncated)";

        foreach ($this->debug_chat_ids as $chatId) {
            try {
                $this->api->callAPI('sendMessage', ['chat_id' => $chatId, 'text' => $html, 'parse_mode' => 'HTML']);
            } catch (Throwable $t) { fwrite(STDERR, "Log send fail: " . $t->getMessage()); }
        }
    }

    private function getCodeSnippet(string $file, int $line, int $padding = 5): array
    {
        if (!is_readable($file)) return [];
        $lines = file($file);
        $start = max(0, $line - $padding - 1);
        $slice = array_slice($lines, $start, ($line + $padding) - $start, true);
        $result = [];
        foreach ($slice as $i => $content) $result[$i + 1] = $content;
        return $result;
    }

    private function renderTrace(Throwable $e): string
    {
        $trace = ""; $i = 0;
        foreach ($e->getTrace() as $item) {
            if ($this->short_trace && str_contains($item['file'] ?? '', '/vendor/')) continue;
            $file = isset($item['file']) ? $this->cleanPath($item['file']) : '[internal]';
            $trace .= "#$i $file(" . ($item['line'] ?? '?') . "): " . ($item['class'] ?? '') . ($item['type'] ?? '') . $item['function'] . "()\n";
        }
        return htmlspecialchars($trace);
    }

    private function cleanPath(string $path): string { return str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $path); }
}