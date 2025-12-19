import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Upload, Image, App, Button, Popconfirm, Tooltip } from 'antd';
import {
  PlusOutlined,
  DeleteOutlined,
  StarOutlined,
  LoadingOutlined,
  PictureOutlined,
} from '@ant-design/icons';
import type { RcFile } from 'antd/es/upload';
import { productApi, type ProductImage } from '@/lib/product-api';

interface ProductImagesProps {
  productId: string;
  images: ProductImage[];
  onUpdate: () => void;
  disabled?: boolean;
}

const MAX_IMAGES = 10;

export function ProductImages({ productId, images, onUpdate, disabled }: ProductImagesProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [uploading, setUploading] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [previewOpen, setPreviewOpen] = useState(false);

  // Reset selected index when images change
  useEffect(() => {
    if (selectedIndex >= images.length && images.length > 0) {
      setSelectedIndex(0);
    }
  }, [images.length, selectedIndex]);

  const selectedImage = images[selectedIndex] || null;

  const handleUpload = async (file: RcFile) => {
    if (images.length >= MAX_IMAGES) {
      message.error(t('products.maxImagesReached', { max: MAX_IMAGES }));
      return false;
    }

    const isImage = file.type.startsWith('image/');
    if (!isImage) {
      message.error(t('products.invalidImageType'));
      return false;
    }

    const isLt5M = file.size / 1024 / 1024 < 5;
    if (!isLt5M) {
      message.error(t('products.imageTooLarge'));
      return false;
    }

    setUploading(true);
    try {
      await productApi.uploadImage(productId, file);
      message.success(t('products.imageUploaded'));
      onUpdate();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setUploading(false);
    }

    return false;
  };

  const handleDelete = async (imageId: string) => {
    try {
      await productApi.deleteImage(productId, imageId);
      message.success(t('products.imageDeleted'));
      onUpdate();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  };

  const handleSetPrimary = async (imageId: string) => {
    try {
      await productApi.setImagePrimary(productId, imageId);
      message.success(t('products.imagePrimarySet'));
      onUpdate();
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  };

  return (
    <div className="flex gap-4">
      {/* Left: Large Preview */}
      <div className="flex-1">
        <div
          className="relative w-full bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden flex items-center justify-center"
          style={{ aspectRatio: '1/1' }}
        >
          {selectedImage ? (
            <>
              <img
                src={selectedImage.url}
                alt=""
                className="w-full h-full object-contain cursor-pointer"
                onClick={() => setPreviewOpen(true)}
              />
              {/* Primary badge */}
              {selectedImage.isPrimary && (
                <div className="absolute top-2 left-2 bg-yellow-500 text-white text-xs px-2 py-0.5 rounded">
                  {t('products.primaryImage')}
                </div>
              )}
              {/* Action buttons */}
              {!disabled && (
                <div className="absolute top-2 right-2 flex gap-1">
                  {!selectedImage.isPrimary && (
                    <Tooltip title={t('products.setAsPrimary')}>
                      <Button
                        size="small"
                        icon={<StarOutlined />}
                        onClick={() => handleSetPrimary(selectedImage.id)}
                      />
                    </Tooltip>
                  )}
                  <Popconfirm
                    title={t('products.confirmDeleteImage')}
                    onConfirm={() => handleDelete(selectedImage.id)}
                    okText={t('common.confirm')}
                    cancelText={t('common.cancel')}
                  >
                    <Button
                      size="small"
                      danger
                      icon={<DeleteOutlined />}
                    />
                  </Popconfirm>
                </div>
              )}
              {/* Image info */}
              {selectedImage.dimensions && (
                <div className="absolute bottom-0 left-0 right-0 bg-black/60 text-white text-xs px-2 py-1 text-center">
                  {selectedImage.dimensions} · {selectedImage.humanFileSize}
                </div>
              )}
            </>
          ) : (
            <div className="flex flex-col items-center justify-center text-gray-400">
              <PictureOutlined style={{ fontSize: 48 }} />
              <span className="mt-2 text-sm">{t('products.noImages')}</span>
            </div>
          )}
        </div>
      </div>

      {/* Right: Upload + Thumbnails */}
      <div className="w-24 flex flex-col gap-2">
        {/* Upload button */}
        {!disabled && images.length < MAX_IMAGES && (
          <Upload
            accept="image/jpeg,image/png,image/gif,image/webp"
            showUploadList={false}
            beforeUpload={handleUpload}
            disabled={uploading}
          >
            <div
              className="w-24 h-24 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg flex flex-col items-center justify-center cursor-pointer hover:border-blue-500 transition-colors"
            >
              {uploading ? (
                <LoadingOutlined className="text-xl text-gray-400" />
              ) : (
                <>
                  <PlusOutlined className="text-xl text-gray-400" />
                  <span className="mt-1 text-xs text-gray-500">{t('common.add')}</span>
                </>
              )}
            </div>
          </Upload>
        )}

        {/* Thumbnails */}
        <div className="flex flex-col gap-2 overflow-y-auto" style={{ maxHeight: 320 }}>
          {images.map((image, index) => (
            <div
              key={image.id}
              className={`relative w-24 h-24 rounded-lg overflow-hidden cursor-pointer border-2 transition-colors ${
                index === selectedIndex
                  ? 'border-blue-500'
                  : 'border-transparent hover:border-gray-300'
              }`}
              onClick={() => setSelectedIndex(index)}
            >
              <img
                src={image.thumbnailUrl || image.url}
                alt=""
                className="w-full h-full object-cover"
              />
              {image.isPrimary && (
                <div className="absolute top-1 left-1 bg-yellow-500 text-white p-0.5 rounded">
                  <StarOutlined style={{ fontSize: 10 }} />
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Preview modal */}
      <Image
        style={{ display: 'none' }}
        preview={{
          visible: previewOpen,
          src: selectedImage?.url,
          onVisibleChange: (visible) => setPreviewOpen(visible),
        }}
      />
    </div>
  );
}
