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
        string  $secretId,
        string  $secretKey,
        string  $region,
        string  $bucket,
        ?string $cdnDomain = null,
    )
    {
        $this->region = $region;
        $this->bucket = $bucket;
        $this->cdnDomain = $cdnDomain ?: null;

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
     * Upload a file to COS
     *
     * @param UploadedFile $file The uploaded file
     * @param string $directory Directory path in COS (e.g., 'products/images')
     * @return array{cosKey: string, url: string, thumbnailUrl: string|null, fileSize: int, width: int|null, height: int|null}
     */
    public function uploadFile(UploadedFile $file, string $directory = 'uploads'): array
    {
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
        $filename = (string)new Ulid() . '.' . $extension;
        $date = date('Y/m');
        $cosKey = trim($directory, '/') . '/' . $date . '/' . $filename;

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
            $thumbnailUrl = $url . '?imageMogr2/thumbnail/300x300>';
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
     * Delete a file from COS
     */
    public function deleteFile(string $cosKey): void
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $cosKey,
            ]);
        } catch (\Exception $e) {
            // Log error but don't throw - file might not exist
        }
    }

    /**
     * Get the full URL for a COS key (unsigned, for public buckets or CDN)
     */
    public function getUrl(string $cosKey): string
    {
        if ($this->cdnDomain) {
            return 'https://' . $this->cdnDomain . '/' . $cosKey;
        }

        return 'https://' . $this->bucket . '.cos.' . $this->region . '.myqcloud.com/' . $cosKey;
    }

    /**
     * Get a signed URL for reading from private bucket
     *
     * @param string $cosKey The object key
     * @param int $expires Expiration time in seconds (default 1 hour)
     * @param string|null $imageParams Optional image processing params (e.g., 'imageMogr2/thumbnail/300x300>')
     */
    public function getSignedUrl(string $cosKey, int $expires = 3600, ?string $imageParams = null): string
    {
        // If using CDN with signed URL, the CDN should handle authentication
        // For direct COS access, generate pre-signed URL
        $signedUrl = $this->client->getPresignedUrl('getObject', [
            'Bucket' => $this->bucket,
            'Key' => $cosKey,
        ], '+' . $expires . ' seconds');

        // Append image processing params if provided
        if ($imageParams) {
            $signedUrl .= '&' . $imageParams;
        }

        return $signedUrl;
    }

    /**
     * Generate a pre-signed URL for direct upload from client
     */
    public function getPresignedUrl(string $cosKey, int $expires = 3600): string
    {
        return $this->client->getPresignedUrl('putObject', [
            'Bucket' => $this->bucket,
            'Key' => $cosKey,
        ], '+' . $expires . ' seconds');
    }
}
