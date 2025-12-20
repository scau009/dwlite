import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Upload, Image, App, Popconfirm, Tooltip } from 'antd';
import {
  PlusOutlined,
  DeleteOutlined,
  StarOutlined,
  LoadingOutlined,
  HolderOutlined,
} from '@ant-design/icons';
import type { RcFile } from 'antd/es/upload';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  rectSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { productApi, type ProductImage } from '@/lib/product-api';

interface ProductImagesProps {
  productId: string;
  images: ProductImage[];
  onUpdate?: () => void; // Optional: only called for operations affecting other page sections
  disabled?: boolean;
}

const MAX_IMAGES = 10;
const IMAGE_SIZE = 100;

// Sortable Image Item Component
interface SortableImageProps {
  image: ProductImage;
  disabled?: boolean;
  onPreview: (url: string) => void;
  onSetPrimary: (imageId: string, e: React.MouseEvent) => void;
  onDelete: (imageId: string) => void;
  onDeleteClick: (e: React.MouseEvent) => void;
  t: (key: string) => string;
}

function SortableImage({
  image,
  disabled,
  onPreview,
  onSetPrimary,
  onDelete,
  onDeleteClick,
  t,
}: SortableImageProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: image.id, disabled });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    width: IMAGE_SIZE,
    height: IMAGE_SIZE,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`relative group rounded-lg overflow-hidden border transition-colors ${
        isDragging
          ? 'border-blue-500 shadow-lg z-10'
          : 'border-gray-200 dark:border-gray-700 hover:border-blue-400'
      }`}
    >
      <img
        src={image.thumbnailUrl || image.url}
        alt=""
        className="w-full h-full object-cover cursor-pointer"
        onClick={() => onPreview(image.url)}
      />
      {/* Primary badge */}
      {image.isPrimary && (
        <div className="absolute top-1 left-1 bg-yellow-500 text-white p-0.5 rounded">
          <StarOutlined style={{ fontSize: 10 }} />
        </div>
      )}
      {/* Drag handle */}
      {!disabled && (
        <div
          {...attributes}
          {...listeners}
          className="absolute top-1 right-1 w-6 h-6 rounded bg-black/50 flex items-center justify-center cursor-grab active:cursor-grabbing opacity-0 group-hover:opacity-100 transition-opacity"
        >
          <HolderOutlined className="text-white" style={{ fontSize: 12 }} />
        </div>
      )}
      {/* Hover actions */}
      {!disabled && (
        <div className="absolute bottom-0 left-0 right-0 bg-black/60 py-1 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
          {!image.isPrimary && (
            <Tooltip title={t('products.setAsPrimary')}>
              <div
                className="w-6 h-6 rounded-full bg-white/90 flex items-center justify-center cursor-pointer hover:bg-white"
                onClick={(e) => onSetPrimary(image.id, e)}
              >
                <StarOutlined className="text-yellow-500" style={{ fontSize: 12 }} />
              </div>
            </Tooltip>
          )}
          <Popconfirm
            title={t('products.confirmDeleteImage')}
            onConfirm={() => onDelete(image.id)}
            okText={t('common.confirm')}
            cancelText={t('common.cancel')}
          >
            <Tooltip title={t('common.delete')}>
              <div
                className="w-6 h-6 rounded-full bg-white/90 flex items-center justify-center cursor-pointer hover:bg-white"
                onClick={onDeleteClick}
              >
                <DeleteOutlined className="text-red-500" style={{ fontSize: 12 }} />
              </div>
            </Tooltip>
          </Popconfirm>
        </div>
      )}
    </div>
  );
}

export function ProductImages({ productId, images: propImages, onUpdate, disabled }: ProductImagesProps) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  // Local state for images - avoids full page refresh
  const [localImages, setLocalImages] = useState<ProductImage[]>(propImages);
  const [uploading, setUploading] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewImage, setPreviewImage] = useState('');

  // Sync with prop when it changes from outside (e.g., initial load)
  useEffect(() => {
    setLocalImages(propImages);
  }, [propImages]);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleUpload = useCallback(async (file: RcFile) => {
    if (localImages.length >= MAX_IMAGES) {
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
      const result = await productApi.uploadImage(productId, file);
      message.success(t('products.imageUploaded'));
      // Update local state with the new image
      setLocalImages((prev) => [...prev, result.image]);
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    } finally {
      setUploading(false);
    }

    return false;
  }, [localImages.length, message, productId, t]);

  const handleDeleteClick = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
  }, []);

  const confirmDelete = useCallback(async (imageId: string) => {
    const imageToDelete = localImages.find((img) => img.id === imageId);
    const wasPrimary = imageToDelete?.isPrimary;

    try {
      await productApi.deleteImage(productId, imageId);
      message.success(t('products.imageDeleted'));
      // Update local state
      setLocalImages((prev) => prev.filter((img) => img.id !== imageId));
      // If deleted image was primary, notify parent to refresh (affects product listing)
      if (wasPrimary && onUpdate) {
        onUpdate();
      }
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  }, [localImages, message, onUpdate, productId, t]);

  const handleSetPrimary = useCallback(async (imageId: string, e: React.MouseEvent) => {
    e.stopPropagation();
    try {
      await productApi.setImagePrimary(productId, imageId);
      message.success(t('products.imagePrimarySet'));
      // Update local state - set new primary, unset old primary
      setLocalImages((prev) =>
        prev.map((img) => ({
          ...img,
          isPrimary: img.id === imageId,
        }))
      );
      // Notify parent - primary change affects product listing thumbnail
      if (onUpdate) {
        onUpdate();
      }
    } catch (error) {
      const err = error as { error?: string };
      message.error(err.error || t('common.error'));
    }
  }, [message, onUpdate, productId, t]);

  const handlePreview = useCallback((url: string) => {
    setPreviewImage(url);
    setPreviewOpen(true);
  }, []);

  const handleDragEnd = useCallback(async (event: DragEndEvent) => {
    const { active, over } = event;

    if (over && active.id !== over.id) {
      const oldIndex = localImages.findIndex((img) => img.id === active.id);
      const newIndex = localImages.findIndex((img) => img.id === over.id);
      const newOrder = arrayMove(localImages, oldIndex, newIndex);

      // Update local state immediately (optimistic update)
      setLocalImages(newOrder);

      const imageIds = newOrder.map((img) => img.id);

      try {
        await productApi.sortImages(productId, imageIds);
        message.success(t('products.imagesSorted'));
        // No need to call onUpdate - sort doesn't affect other page sections
      } catch (error) {
        const err = error as { error?: string };
        message.error(err.error || t('common.error'));
        // Revert on error
        setLocalImages(localImages);
      }
    }
  }, [localImages, message, productId, t]);

  return (
    <div className="flex flex-wrap gap-3">
      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragEnd={handleDragEnd}
      >
        <SortableContext
          items={localImages.map((img) => img.id)}
          strategy={rectSortingStrategy}
        >
          {localImages.map((image) => (
            <SortableImage
              key={image.id}
              image={image}
              disabled={disabled}
              onPreview={handlePreview}
              onSetPrimary={handleSetPrimary}
              onDelete={confirmDelete}
              onDeleteClick={handleDeleteClick}
              t={t}
            />
          ))}
        </SortableContext>
      </DndContext>

      {/* Upload button - only show if less than MAX_IMAGES and not disabled */}
      {!disabled && localImages.length < MAX_IMAGES && (
        <Upload
          accept="image/jpeg,image/png,image/gif,image/webp"
          showUploadList={false}
          beforeUpload={handleUpload}
          disabled={uploading}
        >
          <div
            className="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg flex flex-col items-center justify-center cursor-pointer hover:border-blue-400 transition-colors"
            style={{ width: IMAGE_SIZE, height: IMAGE_SIZE }}
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

      {/* Preview modal */}
      <Image
        style={{ display: 'none' }}
        preview={{
          visible: previewOpen,
          src: previewImage,
          onVisibleChange: (visible) => setPreviewOpen(visible),
        }}
      />
    </div>
  );
}
