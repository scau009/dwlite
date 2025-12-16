"use client"

import { useState } from "react"
import { useParams, useNavigate } from "react-router"
import { useTranslation } from "react-i18next"

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import {
  FormHeader,
  FormSection,
  FormField,
  FormActions,
  FormInput,
} from "@/components/common/form-page"

interface ProductFormData {
  name: string
  sku: string
  category: string
  brand: string
  price: string
  originalPrice: string
  stock: string
  weight: string
  dimensions: string
  description: string
  status: string
}

interface FormErrors {
  [key: string]: string
}

export function ProductFormPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { t } = useTranslation()
  const isEdit = Boolean(id)

  // Form state
  const [formData, setFormData] = useState<ProductFormData>({
    name: isEdit ? 'Premium Sneakers' : '',
    sku: isEdit ? 'SKU-001' : '',
    category: isEdit ? 'Footwear' : '',
    brand: isEdit ? 'Nike' : '',
    price: isEdit ? '299' : '',
    originalPrice: isEdit ? '399' : '',
    stock: isEdit ? '150' : '',
    weight: isEdit ? '0.8' : '',
    dimensions: isEdit ? '30 x 20 x 12' : '',
    description: isEdit ? 'High-quality premium sneakers with exceptional comfort and style.' : '',
    status: isEdit ? 'on_sale' : 'off_sale',
  })

  const [errors, setErrors] = useState<FormErrors>({})
  const [loading, setLoading] = useState(false)

  const handleChange = (key: keyof ProductFormData, value: string) => {
    setFormData((prev) => ({ ...prev, [key]: value }))
    // Clear error when user starts typing
    if (errors[key]) {
      setErrors((prev) => ({ ...prev, [key]: '' }))
    }
  }

  const validate = (): boolean => {
    const newErrors: FormErrors = {}

    if (!formData.name.trim()) {
      newErrors.name = t('validation.required', { field: t('products.productName') })
    }
    if (!formData.sku.trim()) {
      newErrors.sku = t('validation.required', { field: t('products.sku') })
    }
    if (!formData.category) {
      newErrors.category = t('validation.required', { field: t('products.category') })
    }
    if (!formData.price || isNaN(Number(formData.price))) {
      newErrors.price = t('validation.required', { field: t('products.price') })
    }

    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSave = async () => {
    if (!validate()) return

    setLoading(true)
    try {
      // Simulate API call
      await new Promise((resolve) => setTimeout(resolve, 1000))
      console.log('Save product:', formData)
      navigate('/products')
    } catch (error) {
      console.error('Save failed:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleCancel = () => {
    navigate('/products')
  }

  return (
    <div className="space-y-6 max-w-2xl">
      <FormHeader
        title={isEdit ? t('common.edit') + ' ' + t('nav.products') : t('products.addProduct')}
        backUrl="/products"
      />

      {/* Basic Information */}
      <FormSection
        title={t('detail.basicInfo')}
        description={t('form.basicInfoDescription') || 'Enter the basic information for this product'}
      >
        <FormField
          label={t('products.productName')}
          required
          error={errors.name}
        >
          <FormInput
            value={formData.name}
            onChange={(e) => handleChange('name', e.target.value)}
            placeholder={t('products.productName')}
            error={Boolean(errors.name)}
          />
        </FormField>

        <FormField
          label={t('products.sku')}
          required
          error={errors.sku}
        >
          <FormInput
            value={formData.sku}
            onChange={(e) => handleChange('sku', e.target.value)}
            placeholder={t('products.sku')}
            error={Boolean(errors.sku)}
          />
        </FormField>

        <FormField
          label={t('products.category')}
          required
          error={errors.category}
        >
          <Select
            value={formData.category}
            onValueChange={(value: string) => handleChange('category', value)}
          >
            <SelectTrigger>
              <SelectValue placeholder={t('common.all')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="Footwear">Footwear</SelectItem>
              <SelectItem value="Apparel">Apparel</SelectItem>
              <SelectItem value="Accessories">Accessories</SelectItem>
            </SelectContent>
          </Select>
        </FormField>

        <FormField label={t('detail.brand')}>
          <FormInput
            value={formData.brand}
            onChange={(e) => handleChange('brand', e.target.value)}
            placeholder={t('detail.brand')}
          />
        </FormField>

        <FormField label={t('detail.description')}>
          <Textarea
            value={formData.description}
            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => handleChange('description', e.target.value)}
            placeholder={t('detail.description')}
            rows={4}
          />
        </FormField>
      </FormSection>

      {/* Pricing */}
      <FormSection
        title={t('nav.pricing')}
        description={t('form.pricingDescription') || 'Set the pricing for this product'}
      >
        <FormField
          label={t('products.price')}
          required
          error={errors.price}
        >
          <FormInput
            type="number"
            value={formData.price}
            onChange={(e) => handleChange('price', e.target.value)}
            placeholder="0.00"
            error={Boolean(errors.price)}
          />
        </FormField>

        <FormField label={t('form.originalPrice') || 'Original Price'}>
          <FormInput
            type="number"
            value={formData.originalPrice}
            onChange={(e) => handleChange('originalPrice', e.target.value)}
            placeholder="0.00"
          />
        </FormField>
      </FormSection>

      {/* Inventory */}
      <FormSection
        title={t('nav.inventory')}
        description={t('form.inventoryDescription') || 'Manage inventory settings'}
      >
        <FormField label={t('products.stock')}>
          <FormInput
            type="number"
            value={formData.stock}
            onChange={(e) => handleChange('stock', e.target.value)}
            placeholder="0"
          />
        </FormField>

        <FormField label={t('detail.weight') + ' (kg)'}>
          <FormInput
            type="number"
            step="0.1"
            value={formData.weight}
            onChange={(e) => handleChange('weight', e.target.value)}
            placeholder="0.0"
          />
        </FormField>

        <FormField label={t('detail.dimensions') + ' (cm)'}>
          <FormInput
            value={formData.dimensions}
            onChange={(e) => handleChange('dimensions', e.target.value)}
            placeholder="L x W x H"
          />
        </FormField>
      </FormSection>

      {/* Status */}
      <FormSection title={t('products.status')}>
        <FormField label={t('products.status')}>
          <Select
            value={formData.status}
            onValueChange={(value: string) => handleChange('status', value)}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="on_sale">{t('products.onSale')}</SelectItem>
              <SelectItem value="off_sale">{t('products.offSale')}</SelectItem>
            </SelectContent>
          </Select>
        </FormField>
      </FormSection>

      {/* Form Actions */}
      <FormActions
        onSave={handleSave}
        onCancel={handleCancel}
        loading={loading}
      />
    </div>
  )
}