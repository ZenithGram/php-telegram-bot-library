<?php

namespace ZenithGram\ZenithGram;

//Динамическое подключение классов, только при его использовании
spl_autoload_register(static function ($class) {
    if (str_starts_with($class, 'ZenithGram\\ZenithGram')) {
        $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        $class = str_replace(['ZenithGram\\ZenithGram\\', '\\'],
            ['', DIRECTORY_SEPARATOR], $class);
        $file = $baseDir . "$class.php";
        if (file_exists($file)) {
            require $file;
        }
    }
});