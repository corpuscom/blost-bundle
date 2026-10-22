<?php

declare(strict_types=1);

// No root vendor/autoload.php: consumers must use only each package's runtime dependencies.
$root = dirname(__DIR__);
$temporary = sys_get_temp_dir().'/blost-consumers-'.bin2hex(random_bytes(6));
mkdir($temporary, 0777, true);

function run(array $command, string $directory): void
{
    $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $directory);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Consumer command failed: '.implode(' ', $command));
    }
}

try {
    foreach (['laravel', 'symfony'] as $framework) {
        $directory = $temporary.'/'.$framework;
        mkdir($directory);
        $package = 'corpuscom/blost-'.$framework;
        $manifest = [
            'name' => 'blost/test-consumer',
            'repositories' => [
                ['type' => 'path', 'url' => $root.'/packages/'.$framework, 'options' => ['symlink' => false, 'versions' => [$package => 'dev-main']]],
                ['type' => 'path', 'url' => $root, 'options' => ['symlink' => false, 'versions' => ['corpuscom/blost-bundle' => 'dev-main']]],
            ],
            'require' => [$package => 'dev-main', 'corpuscom/blost-bundle' => 'dev-main'],
            'config' => ['allow-plugins' => false],
        ];
        file_put_contents($directory.'/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        run(['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress'], $directory);
        $code = 'require "vendor/autoload.php";';
        $classes = $framework === 'laravel'
            ? [\Corpuscom\Blost\LaravelBlog\Post::class, \Corpuscom\Blost\LaravelBlog\BlostBlogServiceProvider::class]
            : [\Corpuscom\Blost\SymfonyBlog\Entity\Post::class, \Corpuscom\Blost\SymfonyBlog\BlostBlogBundle::class];
        foreach ($classes as $class) {
            $code .= 'if (!class_exists('.var_export($class, true).')) { throw new RuntimeException("Package autoload failed."); }';
        }
        $excluded = $framework === 'laravel' ? 'doctrine/orm' : 'laravel/framework';
        $code .= 'if (Composer\\InstalledVersions::isInstalled('.var_export($excluded, true).')) { throw new RuntimeException("Unexpected framework dependency."); }';
        run([PHP_BINARY, '-r', $code], $directory);
        echo $package." installs independently.\n";
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($temporary);
}
