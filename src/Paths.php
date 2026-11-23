<?php

namespace Atwx\ErrorReporting;

/**
 * Derives stable names for an installation from its release directory, so events
 * of all releases of one site carry the same tags. The installation path matches
 * the path in the ATW hosting register.
 */
final class Paths
{
    /**
     * Installation root: the directory above Envoyer's releases/<id> or ploi's
     * <site>-deploy/<site>/<id>; the path itself for plain checkouts.
     */
    public static function installation(string $base): string
    {
        $base = rtrim(self::canonical($base), '/');
        if (preg_match('#^(.+)/releases/[^/]+$#', $base, $m)) {
            return $m[1];
        }
        if (preg_match('#^(/home/[^/]+)/([^/]+)-deploy/\2/[^/]+$#', $base, $m)) {
            return $m[1] . '/' . $m[2];
        }
        return $base;
    }

    /**
     * Short site name: "<login>/<dir>" on froxlor servers, the site directory on
     * ploi servers, else the last path segment.
     */
    public static function site(string $installation): string
    {
        if (preg_match('#^/var/customers/webs/([^/]+)/([^/]+)$#', $installation, $m)) {
            return $m[1] . '/' . $m[2];
        }
        if (preg_match('#^/home/[^/]+/([^/]+)$#', $installation, $m)) {
            return $m[1];
        }
        return basename($installation);
    }

    /**
     * Release directory name (Envoyer/ploi timestamp), null for plain checkouts.
     */
    public static function release(string $base): ?string
    {
        $base = rtrim(self::canonical($base), '/');
        return self::installation($base) === $base ? null : basename($base);
    }

    /**
     * froxlor's customer homes are reachable as /var/customers/webs and, as
     * realpath, /var/www/kunden/webs; the register uses the former.
     */
    private static function canonical(string $path): string
    {
        return preg_replace('#^/var/www/kunden/webs/#', '/var/customers/webs/', $path);
    }
}
