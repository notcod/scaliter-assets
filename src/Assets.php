<?php

namespace Scaliter;

class Assets
{
    public static string $root_dir;

    public static array $styles;
    public static array $scripts;

    public static function config(string $file): void
    {
        $assets = json_decode(file_get_contents($file), true);

        self::$styles = isset($assets['styles']) && is_array($assets['styles']) ? $assets['styles'] : [];
        self::$scripts = isset($assets['scripts']) && is_array($assets['scripts']) ? $assets['scripts'] : [];
    }

    public static function root(string $dir): void
    {
        self::$root_dir = $dir;
    }

    public static function get(string $type = 'styles', array $include = [], string $view = NULL, string $page = NULL, string $line_break = PHP_EOL, string $output = ''): string
    {
        if ($type != 'styles' && $type != 'scripts') return '';

        $assets = self::render($type, $include, $view, $page);

        if ($type == 'styles')
            foreach ($assets as $link)
                $output .=  '<link defer rel="stylesheet" href="' . $link . '">' . $line_break;

        if ($type == 'scripts')
            foreach ($assets as $link)
                $output .=  '<script defer src="' . $link . '"></script>' . $line_break;

        return $output;
    }

    public static function render(string $type = 'styles', array $include = [], string $view = NULL, string $page = NULL): array
    {
        if ($type != 'styles' && $type != 'scripts') return [];
        if ($type == 'styles')
            return self::compile(self::$styles, $include, 'css', $view, $page);
        return self::compile(self::$scripts, $include, 'js', $view, $page);
    }

    public static function compile($assets, $include, $extension, $view, $page, $output = []): array
    {
        $view = strtolower($view);
        $page = strtolower($page);
        $inc = $assets['init'] ?? [];
        $include[] = $view;
        foreach ($include as $asset)
            $inc = array_merge($inc, $assets[$asset] ?? []);
        if ($view != NULL) $inc[] = self::create("/$extension/$view.$extension");
        if ($view != NULL && $page != NULL) $inc[] = self::create("/$extension/$view/$page.$extension");
        $inc = array_unique($inc);
        foreach ($inc as $asset) {
            $asset = self::external($asset) ? $asset : self::cache($asset);
            if ($asset != false)
                $output[] = $asset;
        }
        return $output;
    }

    public static function cache(string $url): string|bool
    {
        $file = self::$root_dir . $url;
        return self::isReadable($file) ? $url . '?' . filemtime($file) : false;
    }

    public static function isReadable(string $file): bool
    {
        return is_file($file) && is_readable($file) && filesize($file) != 0;
    }

    public static function create(string $file): string|bool
    {
        $sfile = self::$root_dir . $file;
        if (file_exists($sfile))
            return $file;

        $path = dirname($sfile);
        if (!file_exists($path))
            mkdir($path, 0777, true);

        touch($sfile);
        chmod($sfile, 0777);

        return file_exists($sfile) ? $file : false;
    }

    public static function external(string $url) : bool
    {
        $parse = parse_url($url);
        return !empty($parse['host']) && strcasecmp($parse['host'], Request::server('SERVER_NAME')->value ? Request::server('SERVER_NAME')->value : 'localhost');
    }
}
