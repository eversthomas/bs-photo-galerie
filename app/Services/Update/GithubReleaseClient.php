<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Update;

/**
 * GitHub: Release, Tags oder VERSION-Datei auf dem Standard-Branch — mit Diagnose bei Fehlern.
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
     * Leert nur den Erfolgs-Cache (z. B. nach neuem Push).
     */
    public function clearCache(): void
    {
        $f = $this->cachePath();
        if (is_file($f)) {
            @unlink($f);
        }
    }

    /**
     * @return array{
     *     remote: array{
     *       tag:string,
     *       name:string,
     *       body:string,
     *       html_url:string,
     *       zipball_url:string,
     *       published_at:string,
     *       source:string,
     *       git_mode:string,
     *       git_ref:string
     *     }|null,
     *     error: string|null,
     *     diagnostic: string|null
     * }
     */
    public function fetchLatestCached(): array
    {
        $cacheFile = $this->cachePath();
        if (is_file($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false && $raw !== '') {
                $data = json_decode($raw, true);
                if (is_array($data) && isset($data['fetched_at'], $data['payload']) && is_array($data['payload'])) {
                    if (time() - (int) $data['fetched_at'] < self::CACHE_TTL_SECONDS) {
                        $payload = $this->validatePayload($data['payload']);
                        if ($payload !== null) {
                            return ['remote' => $payload, 'error' => null, 'diagnostic' => null];
                        }
                    }
                }
            }
        }

        $fresh = $this->fetchLatestFreshDetailed();
        if ($fresh['remote'] !== null) {
            $dir = dirname($cacheFile);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            try {
                @file_put_contents(
                    $cacheFile,
                    json_encode(
                        ['fetched_at' => time(), 'payload' => $fresh['remote']],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                    )
                );
            } catch (\JsonException) {
            }
        }

        return $fresh;
    }

    /**
     * @param array<string, mixed> $p
     * @return array{
     *   tag:string,
     *   name:string,
     *   body:string,
     *   html_url:string,
     *   zipball_url:string,
     *   published_at:string,
     *   source:string,
     *   git_mode:string,
     *   git_ref:string
     * }|null
     */
    private function validatePayload(array $p): ?array
    {
        if (! isset($p['tag'], $p['html_url'], $p['zipball_url'], $p['source']) || ! is_string($p['tag'])) {
            return null;
        }
        $gitMode = isset($p['git_mode']) && is_string($p['git_mode']) ? $p['git_mode'] : 'tag';
        $gitRef = isset($p['git_ref']) && is_string($p['git_ref']) ? $p['git_ref'] : $p['tag'];

        return [
            'tag' => $p['tag'],
            'name' => is_string($p['name'] ?? null) ? $p['name'] : $p['tag'],
            'body' => is_string($p['body'] ?? null) ? $p['body'] : '',
            'html_url' => (string) $p['html_url'],
            'zipball_url' => (string) $p['zipball_url'],
            'published_at' => is_string($p['published_at'] ?? null) ? $p['published_at'] : '',
            'source' => (string) $p['source'],
            'git_mode' => $gitMode,
            'git_ref' => $gitRef,
        ];
    }

    /**
     * @return array{remote: array<string, string>|null, error: string|null, diagnostic: string|null}
     */
    private function fetchLatestFreshDetailed(): array
    {
        $diag = [];

        $releaseResp = $this->httpRequest('https://api.github.com/repos/' . self::REPO . '/releases/latest');
        $diag[] = 'releases/latest HTTP ' . $releaseResp['code'];
        $release = $releaseResp['data'];
        if (is_array($release) && isset($release['tag_name']) && is_string($release['tag_name']) && $release['tag_name'] !== '') {
            $tag = $release['tag_name'];

            return [
                'remote' => [
                    'tag' => $tag,
                    'name' => is_string($release['name'] ?? '') && $release['name'] !== '' ? $release['name'] : $tag,
                    'body' => is_string($release['body'] ?? '') ? $release['body'] : '',
                    'html_url' => is_string($release['html_url'] ?? '') ? $release['html_url'] : 'https://github.com/' . self::REPO . '/releases',
                    'zipball_url' => is_string($release['zipball_url'] ?? '') ? $release['zipball_url'] : $this->fallbackZipUrl($tag),
                    'published_at' => is_string($release['published_at'] ?? '') ? $release['published_at'] : '',
                    'source' => 'release',
                    'git_mode' => 'tag',
                    'git_ref' => $tag,
                ],
                'error' => null,
                'diagnostic' => null,
            ];
        }

        if ($releaseResp['code'] === 404) {
            $diag[] = 'Kein GitHub-Release „latest“ (normal, wenn noch keins existiert).';
        } elseif ($releaseResp['code'] >= 400 && is_array($release) && isset($release['message'])) {
            $diag[] = 'GitHub (Release): ' . (string) $release['message'];
        }

        $tagsResp = $this->httpRequest('https://api.github.com/repos/' . self::REPO . '/tags?per_page=100');
        $diag[] = 'tags HTTP ' . $tagsResp['code'];
        $tags = $tagsResp['data'];
        if (is_array($tags) && $tags !== []) {
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
            if ($bestTag !== null && $bestTag !== '') {
                return [
                    'remote' => [
                        'tag' => $bestTag,
                        'name' => $bestTag,
                        'body' => '',
                        'html_url' => 'https://github.com/' . self::REPO . '/releases',
                        'zipball_url' => $this->fallbackZipUrl($bestTag),
                        'published_at' => '',
                        'source' => 'tag',
                        'git_mode' => 'tag',
                        'git_ref' => $bestTag,
                    ],
                    'error' => null,
                    'diagnostic' => null,
                ];
            }
            $diag[] = 'Kein semver-Tag (z. B. v0.1.2) gefunden — legen Sie Tags an oder pflegen Sie die Datei VERSION auf dem Standard-Branch.';
        } elseif ($tagsResp['code'] >= 400 && is_array($tags) && isset($tags['message'])) {
            $diag[] = 'GitHub (Tags): ' . (string) $tags['message'];
        }

        $repoResp = $this->httpRequest('https://api.github.com/repos/' . self::REPO);
        $diag[] = 'repo HTTP ' . $repoResp['code'];
        $repoMeta = $repoResp['data'];
        if (! is_array($repoMeta) || ! isset($repoMeta['default_branch']) || ! is_string($repoMeta['default_branch'])) {
            $msg = null;
            if (is_array($repoMeta) && isset($repoMeta['message'])) {
                $msg = (string) $repoMeta['message'];
            }

            return [
                'remote' => null,
                'error' => 'GitHub-Repository konnte nicht gelesen werden. Repo öffentlich und Name „' . self::REPO . '“ korrekt?',
                'diagnostic' => $msg ?? implode("\n", $diag),
            ];
        }

        $branch = $repoMeta['default_branch'];
        $verUrl = 'https://api.github.com/repos/' . self::REPO . '/contents/VERSION?ref=' . rawurlencode($branch);
        $verResp = $this->httpRequest($verUrl);
        $diag[] = 'VERSION?ref=' . $branch . ' HTTP ' . $verResp['code'];
        $verData = $verResp['data'];

        if (is_array($verData) && ($verData['type'] ?? '') === 'file' && isset($verData['content']) && is_string($verData['content'])) {
            $rawVer = (string) base64_decode(str_replace(["\n", "\r"], '', $verData['content']), true);
            $norm = AppVersion::normalize($rawVer);
            if ($norm !== '' && $norm !== '0.0.0') {
                return [
                    'remote' => [
                        'tag' => $norm,
                        'name' => $norm . ' (Branch ' . $branch . ')',
                        'body' => 'Stand laut VERSION-Datei auf GitHub (Branch „' . $branch . '“). Kein Release/Tag nötig.',
                        'html_url' => 'https://github.com/' . self::REPO . '/blob/' . rawurlencode($branch) . '/VERSION',
                        'zipball_url' => 'https://github.com/' . self::REPO . '/archive/refs/heads/' . rawurlencode($branch) . '.zip',
                        'published_at' => '',
                        'source' => 'branch_file',
                        'git_mode' => 'branch',
                        'git_ref' => $branch,
                    ],
                    'error' => null,
                    'diagnostic' => null,
                ];
            }
        }

        if ($verResp['code'] === 404) {
            $diag[] = 'Keine VERSION-Datei auf Branch „' . $branch . '“ gefunden.';
        }

        return [
            'remote' => null,
            'error' => 'Keine Versionsinfo von GitHub (weder Release, noch passender Tag, noch VERSION auf dem Standard-Branch).',
            'diagnostic' => implode("\n", $diag),
        ];
    }

    private function fallbackZipUrl(string $tag): string
    {
        return 'https://github.com/' . self::REPO . '/archive/refs/tags/' . rawurlencode($tag) . '.zip';
    }

    private function cachePath(): string
    {
        return rtrim($this->projectRoot, '/') . '/storage/cache/github_latest.json';
    }

    /**
     * @return array{code: int, data: mixed, raw: string}
     */
    private function httpRequest(string $url): array
    {
        $token = $_ENV['GITHUB_API_TOKEN'] ?? getenv('GITHUB_API_TOKEN');
        $token = is_string($token) ? trim($token) : '';

        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: BSPhotoGalerie-UpdateCheck/1.1',
        ];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if (function_exists('curl_init')) {
            return $this->curlRequest($url, $headers);
        }

        $headerStr = implode("\r\n", $headers) . "\r\n";
        $ctx = stream_context_create([
            'http' => [
                'header' => $headerStr,
                'timeout' => 25,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $http_response_header = [];
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && is_string($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        if ($raw === false || $raw === '') {
            return ['code' => $code, 'data' => null, 'raw' => ''];
        }

        $decoded = json_decode($raw, true);

        return ['code' => $code, 'data' => $decoded, 'raw' => $raw];
    }

    /**
     * @param list<string> $headers
     * @return array{code: int, data: mixed, raw: string}
     */
    private function curlRequest(string $url, array $headers): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['code' => 0, 'data' => null, 'raw' => ''];
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $raw === '') {
            return ['code' => $code, 'data' => null, 'raw' => ''];
        }
        $decoded = json_decode($raw, true);

        return ['code' => $code, 'data' => $decoded, 'raw' => $raw];
    }
}
