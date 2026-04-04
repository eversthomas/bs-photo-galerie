<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Update;

/**
 * Fragt GitHub nach dem neuesten Release bzw. Tag (öffentliches Repo).
 */
final class GithubReleaseClient
{
    private const REPO = 'eversthomas/bs-photo-galerie';

    private const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private string $projectRoot
    ) {
    }

    /**
     * @return array{
     *   tag: string,
     *   name: string,
     *   body: string,
     *   html_url: string,
     *   zipball_url: string,
     *   published_at: string,
     *   source: 'release'|'tag'
     * }|null
     */
    public function fetchLatestCached(): ?array
    {
        $cacheFile = $this->cachePath();
        if (is_file($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false && $raw !== '') {
                $data = json_decode($raw, true);
                if (is_array($data) && isset($data['fetched_at'], $data['payload']) && is_array($data['payload'])) {
                    if (time() - (int) $data['fetched_at'] < self::CACHE_TTL_SECONDS) {
                        return $this->validatePayload($data['payload']);
                    }
                }
            }
        }

        $payload = $this->fetchLatestFresh();
        if ($payload === null) {
            return null;
        }

        $dir = dirname($cacheFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        try {
            @file_put_contents(
                $cacheFile,
                json_encode(
                    ['fetched_at' => time(), 'payload' => $payload],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                )
            );
        } catch (\JsonException) {
        }

        return $payload;
    }

    /**
     * @return array{tag:string,name:string,body:string,html_url:string,zipball_url:string,published_at:string,source:string}|null
     */
    private function validatePayload(array $p): ?array
    {
        if (! isset($p['tag'], $p['html_url'], $p['zipball_url'], $p['source']) || ! is_string($p['tag'])) {
            return null;
        }

        return [
            'tag' => $p['tag'],
            'name' => is_string($p['name'] ?? null) ? $p['name'] : $p['tag'],
            'body' => is_string($p['body'] ?? null) ? $p['body'] : '',
            'html_url' => (string) $p['html_url'],
            'zipball_url' => (string) $p['zipball_url'],
            'published_at' => is_string($p['published_at'] ?? null) ? $p['published_at'] : '',
            'source' => (string) $p['source'],
        ];
    }

    /**
     * @return array{tag:string,name:string,body:string,html_url:string,zipball_url:string,published_at:string,source:'release'|'tag'}|null
     */
    private function fetchLatestFresh(): ?array
    {
        $release = $this->httpGetJson('https://api.github.com/repos/' . self::REPO . '/releases/latest');
        if (is_array($release) && isset($release['tag_name']) && is_string($release['tag_name']) && $release['tag_name'] !== '') {
            $tag = $release['tag_name'];

            return [
                'tag' => $tag,
                'name' => is_string($release['name'] ?? '') && $release['name'] !== '' ? $release['name'] : $tag,
                'body' => is_string($release['body'] ?? '') ? $release['body'] : '',
                'html_url' => is_string($release['html_url'] ?? '') ? $release['html_url'] : 'https://github.com/' . self::REPO . '/releases',
                'zipball_url' => is_string($release['zipball_url'] ?? '') ? $release['zipball_url'] : $this->fallbackZipUrl($tag),
                'published_at' => is_string($release['published_at'] ?? '') ? $release['published_at'] : '',
                'source' => 'release',
            ];
        }

        return $this->fetchLatestTagOnly();
    }

    /**
     * @return array{tag:string,name:string,body:string,html_url:string,zipball_url:string,published_at:string,source:'tag'}|null
     */
    private function fetchLatestTagOnly(): ?array
    {
        $tags = $this->httpGetJson('https://api.github.com/repos/' . self::REPO . '/tags?per_page=100');
        if (! is_array($tags) || $tags === []) {
            return null;
        }

        $bestTag = null;
        $bestNorm = null;
        foreach ($tags as $row) {
            if (! is_array($row) || ! isset($row['name']) || ! is_string($row['name'])) {
                continue;
            }
            $name = $row['name'];
            $norm = AppVersion::normalize($name);
            if (! preg_match('/^\d+(\.\d+){0,3}/', $norm)) {
                continue;
            }
            if ($bestNorm === null || version_compare($norm, $bestNorm, '>')) {
                $bestNorm = $norm;
                $bestTag = $name;
            }
        }

        if ($bestTag === null || $bestTag === '') {
            return null;
        }

        return [
            'tag' => $bestTag,
            'name' => $bestTag,
            'body' => '',
            'html_url' => 'https://github.com/' . self::REPO . '/releases',
            'zipball_url' => $this->fallbackZipUrl($bestTag),
            'published_at' => '',
            'source' => 'tag',
        ];
    }

    private function fallbackZipUrl(string $tag): string
    {
        $enc = rawurlencode($tag);

        return 'https://github.com/' . self::REPO . '/archive/refs/tags/' . $enc . '.zip';
    }

    private function cachePath(): string
    {
        return rtrim($this->projectRoot, '/') . '/storage/cache/github_latest.json';
    }

    /**
     * @return mixed
     */
    private function httpGetJson(string $url): mixed
    {
        $token = $_ENV['GITHUB_API_TOKEN'] ?? getenv('GITHUB_API_TOKEN');
        $token = is_string($token) ? trim($token) : '';

        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: BSPhotoGalerie-UpdateCheck',
        ];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if (function_exists('curl_init')) {
            return $this->curlGetJson($url, $headers);
        }

        $headerStr = implode("\r\n", $headers);
        $ctx = stream_context_create([
            'http' => [
                'header' => $headerStr . "\r\n",
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || $body === '') {
            return null;
        }

        return json_decode($body, true);
    }

    /**
     * @param list<string> $headers
     */
    private function curlGetJson(string $url, array $headers): mixed
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            return null;
        }

        return json_decode($body, true);
    }
}
