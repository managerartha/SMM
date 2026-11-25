<?php

namespace App\Support;

/** Mengubah nilai di berkas .env (dipakai installer web, supaya GM tidak perlu mengedit .env manual). */
class EnvFile
{
    public static function set(string $path, array $values): void
    {
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content)
                : rtrim($content)."\n".$line."\n";
        }
        file_put_contents($path, $content, LOCK_EX);
        @chmod($path, 0600);
    }

    public static function quote(string $v): string
    {
        if ($v === '' || preg_match('/^[A-Za-z0-9_.@:\/+-]+$/', $v)) {
            return $v;
        }
        if (! str_contains($v, "'")) {
            return "'".$v."'"; // kutip tunggal = literal (tanpa interpolasi)
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $v).'"';
    }
}
