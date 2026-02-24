<?php

namespace App\Service;

use Qcloud\Cos\Client;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

class CosService
{
    private Client $client;
    private string $bucket;
    private string $region;
    private ?string $cdnDomain;

    public function __construct(
        string $secretId,
        string $secretKey,
        string $region,
        string $bucket,
        ?string $cdnDomain = null,
    ) {
        $this->region = $region;
        $this->bucket = $bucket;
        $this->cdnDomain = $this->normalizeCdnDomain($cdnDomain);

        $this->client = new Client([
            'region' => $region,
            'schema' => 'https',
            'credentials' => [
                'secretId' => $secretId,
                'secretKey' => $secretKey,
            ],
        ]);
    }

    /**
     * Upload a file to COS.
     *
     * @param UploadedFile $file The uploaded file
     * @param string $directory Directory path in COS (e.g., 'products/images')
     *
     * @return array{cosKey: string, url: string, thumbnailUrl: string|null, fileSize: int, width: int|null, height: int|null}
     */
    public function uploadFile(UploadedFile $file, string $directory = 'uploads'): array
    {
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
        $filename = (string) new Ulid().'.'.$extension;
        $date = date('Y/m');
        $cosKey = trim($directory, '/').'/'.$date.'/'.$filename;

        // Upload to COS
        $this->client->upload(
            $this->bucket,
            $cosKey,
            fopen($file->getPathname(), 'rb')
        );

        // Build URLs
        $url = $this->getUrl($cosKey);
        $thumbnailUrl = null;

        // Get image dimensions if it's an image
        $width = null;
        $height = null;
        $mimeType = $file->getMimeType();
        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            $imageSize = @getimagesize($file->getPathname());
            if ($imageSize) {
                $width = $imageSize[0];
                $height = $imageSize[1];
            }
            // Generate thumbnail URL using COS image processing
            $thumbnailUrl = $url.'?imageMogr2/thumbnail/300x300>';
        }

        return [
            'cosKey' => $cosKey,
            'url' => $url,
            'thumbnailUrl' => $thumbnailUrl,
            'fileSize' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Delete a file from COS.
     */
    public function deleteFile(string $cosKey): void
    {
        $normalizedCosKey = $this->normalizeCosKey($cosKey);

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $normalizedCosKey,
            ]);
        } catch (\Exception $e) {
            // Log error but don't throw - file might not exist
        }
    }

    /**
     * Get the full URL for a COS key (unsigned, for public buckets or CDN).
     */
    public function getUrl(string $cosKey): string
    {
        $normalizedCosKey = $this->normalizeCosKey($cosKey);

        if ($this->cdnDomain) {
            return rtrim($this->cdnDomain, '/').'/'.$normalizedCosKey;
        }

        return 'https://'.$this->bucket.'.cos.'.$this->region.'.myqcloud.com/'.$normalizedCosKey;
    }

    /**
     * Get a signed URL for reading from private bucket.
     *
     * @param string $cosKey The object key
     * @param int $expires Expiration time in seconds (default 1 hour)
     * @param string|null $imageParams Optional image processing params (e.g., 'imageMogr2/thumbnail/300x300>')
     * @param bool $inline If true, set Content-Disposition to inline for browser display
     */
    public function getSignedUrl(string $cosKey, int $expires = 3600, ?string $imageParams = null, bool $inline = false): string
    {
        $normalizedCosKey = $this->normalizeCosKey($cosKey);
        $args = [];

        // Add response-content-disposition for inline display
        if ($inline) {
            $args['ResponseContentDisposition'] = 'inline';
        }

        // Use getObjectUrl which properly handles ResponseContentDisposition
        $signedUrl = $this->client->getObjectUrl(
            $this->bucket,
            $normalizedCosKey,
            '+'.$expires.' seconds',
            $args
        );

        // Append image processing params if provided
        if ($imageParams) {
            $signedUrl .= '&'.$imageParams;
        }

        return $signedUrl;
    }

    /**
     * Generate a pre-signed URL for direct upload from client.
     */
    public function getPresignedUrl(string $cosKey, int $expires = 3600): string
    {
        $normalizedCosKey = $this->normalizeCosKey($cosKey);

        return $this->client->getPresignedUrl('putObject', [
            'Bucket' => $this->bucket,
            'Key' => $normalizedCosKey,
        ], '+'.$expires.' seconds');
    }

    /**
     * Normalize COS object key by removing leading slash.
     */
    private function normalizeCosKey(string $cosKey): string
    {
        return ltrim($cosKey, '/');
    }

    /**
     * Normalize CDN domain to base URL (scheme + host [+port]).
     *
     * Examples:
     * - cdn.example.com -> https://cdn.example.com
     * - https://cdn.example.com -> https://cdn.example.com
     * - https://cdn.example.com/path -> https://cdn.example.com
     */
    private function normalizeCdnDomain(?string $cdnDomain): ?string
    {
        if ($cdnDomain === null) {
            return null;
        }

        $trimmed = trim($cdnDomain);
        if ($trimmed === '') {
            return null;
        }

        // Allow values without schema.
        $value = $trimmed;
        if (!str_starts_with($value, 'http://') && !str_starts_with($value, 'https://')) {
            $value = 'https://'.$value;
        }

        $parsed = parse_url($value);
        if (!is_array($parsed) || !isset($parsed['host']) || $parsed['host'] === '') {
            return null;
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = strtolower($parsed['host']);
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    /**
     * Download an image from URL and upload to COS.
     *
     * @param string $url       The source image URL
     * @param string $directory Directory path in COS (e.g., 'products/images')
     *
     * @return array{cosKey: string, url: string, thumbnailUrl: string|null, fileSize: int, width: int|null, height: int|null}|null
     *               Returns null if download or upload fails
     */
    public function uploadFromUrl(string $url, string $directory = 'products/images'): ?array
    {
        // Create temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'cos_upload_');
        if ($tempFile === false) {
            return null;
        }

        try {
            // Download image with timeout and size limit
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'DWLite/1.0',
                    'follow_location' => true,
                    'max_redirects' => 3,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $imageData = @file_get_contents($url, false, $context);
            if ($imageData === false) {
                return null;
            }

            // Check file size (max 10MB)
            $fileSize = strlen($imageData);
            if ($fileSize > 10 * 1024 * 1024) {
                return null;
            }

            // Write to temp file
            if (file_put_contents($tempFile, $imageData) === false) {
                return null;
            }

            // Detect image type and get dimensions
            $imageInfo = @getimagesize($tempFile);
            if ($imageInfo === false) {
                // Not a valid image
                return null;
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mimeType = $imageInfo['mime'] ?? null;

            // Determine extension from mime type
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
                default => 'jpg',
            };

            // Generate COS key
            $filename = (string) new Ulid().'.'.$extension;
            $date = date('Y/m');
            $cosKey = trim($directory, '/').'/'.$date.'/'.$filename;

            // Upload to COS
            $this->client->upload(
                $this->bucket,
                $cosKey,
                fopen($tempFile, 'rb')
            );

            // Build URLs
            $cosUrl = $this->getUrl($cosKey);
            $thumbnailUrl = $cosUrl.'?imageMogr2/thumbnail/300x300>';

            return [
                'cosKey' => $cosKey,
                'url' => $cosUrl,
                'thumbnailUrl' => $thumbnailUrl,
                'fileSize' => $fileSize,
                'width' => $width,
                'height' => $height,
            ];
        } catch (\Exception $e) {
            // Log error but return null
            return null;
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}
