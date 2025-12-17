<?php

namespace App\Entity;

use App\Repository\ProductImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ProductImageRepository::class)]
#[ORM\Table(name: 'product_images')]
#[ORM\Index(name: 'idx_image_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_image_cos_key', columns: ['cos_key'])]
class ProductImage
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private Product $product;

    #[ORM\Column(length: 255, name: 'cos_key')]
    private string $cosKey;  // COS 对象键，如：products/2024/01/abc123.jpg

    #[ORM\Column(length: 500)]
    private string $url;  // 完整 CDN URL

    #[ORM\Column(length: 500, nullable: true, name: 'thumbnail_url')]
    private ?string $thumbnailUrl = null;  // 缩略图 URL（COS 图片处理）

    #[ORM\Column(type: 'integer', options: ['default' => 0], name: 'sort_order')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'is_primary')]
    private bool $isPrimary = false;

    #[ORM\Column(type: 'integer', nullable: true, name: 'file_size')]
    private ?int $fileSize = null;  // 文件大小（字节）

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $width = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $height = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;
        return $this;
    }

    public function getCosKey(): string
    {
        return $this->cosKey;
    }

    public function setCosKey(string $cosKey): static
    {
        $this->cosKey = $cosKey;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(?string $thumbnailUrl): static
    {
        $this->thumbnailUrl = $thumbnailUrl;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): static
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): static
    {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): static
    {
        $this->height = $height;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * 获取人性化的文件大小
     */
    public function getHumanFileSize(): ?string
    {
        if ($this->fileSize === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * 获取图片尺寸描述
     */
    public function getDimensions(): ?string
    {
        if ($this->width === null || $this->height === null) {
            return null;
        }

        return $this->width . 'x' . $this->height;
    }
}
