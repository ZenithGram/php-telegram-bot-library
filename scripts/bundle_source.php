<?php

$directoriesToScan = ['src', 'Tests'];
$outputFile = 'source-code.txt';
$allowedExtensions = ['php'];
$ignoreFiles = ['.DS_Store'];

$rootDir = dirname(__DIR__);
$outputPath = $rootDir.DIRECTORY_SEPARATOR.$outputFile;

$content = "";

echo "🚀 Начинаем сборку исходного кода...\n";

foreach ($directoriesToScan as $dir) {
    $dirPath = $rootDir.DIRECTORY_SEPARATOR.$dir;

    if (!is_dir($dirPath)) {
        echo "⚠️ Папка не найдена: $dir\n";
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $dirPath, RecursiveDirectoryIterator::SKIP_DOTS,
        ),
    );

    foreach ($iterator as $file) {
        if (!in_array($file->getExtension(), $allowedExtensions)) {
            continue;
        }

        if (in_array($file->getFilename(), $ignoreFiles)) {
            continue;
        }

        $relativePath = str_replace([$rootDir.DIRECTORY_SEPARATOR, '\\'],
            ['', '/'], $file->getPathname());

        echo "📄 Обработка: $relativePath\n";

        $fileContent = file_get_contents($file->getPathname());

        $content .= "===\n";
        $content .= "File: $relativePath\n";
        $content .= "===\n";
        $content .= $fileContent."\n\n";
    }
}

if (file_put_contents($outputPath, trim($content))) {
    echo "✅ Успешно создан файл: $outputFile\n";
    echo "📏 Размер: ".round(filesize($outputPath) / 1024, 2)." KB\n";
} else {
    echo "❌ Ошибка записи файла!\n";
    exit(1);
}