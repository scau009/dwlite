"use client"

import { useParams } from "react-router"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { StatusBadge } from "@/components/common"
import {
  DetailHeader,
  InfoCard,
  InfoRow,
  DetailTabs,
} from "@/components/common/detail-page"

// Mock product data
const mockProduct = {
  id: '1',
  name: 'Premium Sneakers',
  sku: 'SKU-001',
  category: 'Footwear',
  price: 299,
  originalPrice: 399,
  stock: 150,
  status: 'on_sale' as const,
  description: 'High-quality premium sneakers with exceptional comfort and style.',
  brand: 'Nike',
  weight: '0.8kg',
  dimensions: '30 x 20 x 12 cm',
  createdAt: '2024-01-01 10:00:00',
  updatedAt: '2024-01-15 10:30:00',
}

// Mock inventory logs
const inventoryLogs = [
  { id: '1', type: 'in', quantity: 100, operator: 'Admin', time: '2024-01-15 09:00', remark: 'Initial stock' },
  { id: '2', type: 'out', quantity: 20, operator: 'System', time: '2024-01-14 15:30', remark: 'Order fulfillment' },
  { id: '3', type: 'in', quantity: 50, operator: 'Admin', time: '2024-01-13 10:00', remark: 'Restocking' },
  { id: '4', type: 'out', quantity: 30, operator: 'System', time: '2024-01-12 14:20', remark: 'Order fulfillment' },
]

// Mock price history
const priceHistory = [
  { id: '1', oldPrice: 399, newPrice: 299, operator: 'Admin', time: '2024-01-10 10:00', remark: 'Promotion' },
  { id: '2', oldPrice: 349, newPrice: 399, operator: 'Admin', time: '2024-01-01 09:00', remark: 'New season pricing' },
]

// Mock operation logs
const operationLogs = [
  { id: '1', action: 'Update', field: 'stock', oldValue: '100', newValue: '150', operator: 'Admin', time: '2024-01-15 10:30' },
  { id: '2', action: 'Update', field: 'price', oldValue: '399', newValue: '299', operator: 'Admin', time: '2024-01-10 10:00' },
  { id: '3', action: 'Update', field: 'status', oldValue: 'off_sale', newValue: 'on_sale', operator: 'Admin', time: '2024-01-05 14:00' },
]

export function ProductDetailPage() {
  useParams() // Read params for route matching
  const { t } = useTranslation()
  const product = mockProduct

  const tabs = [
    {
      value: 'basic',
      label: t('detail.basicInfo') || 'Basic Info',
      content: (
        <InfoCard title={t('detail.basicInfo') || 'Basic Information'}>
          <dl>
            <InfoRow label={t('products.productName')} value={product.name} />
            <InfoRow label={t('products.sku')} value={product.sku} />
            <InfoRow label={t('products.category')} value={product.category} />
            <InfoRow label={t('detail.brand') || 'Brand'} value={product.brand} />
            <InfoRow label={t('detail.weight') || 'Weight'} value={product.weight} />
            <InfoRow label={t('detail.dimensions') || 'Dimensions'} value={product.dimensions} />
            <InfoRow label={t('detail.description') || 'Description'} value={product.description} />
            <InfoRow label={t('common.createdAt')} value={product.createdAt} />
            <InfoRow label={t('common.updatedAt')} value={product.updatedAt} />
          </dl>
        </InfoCard>
      ),
    },
    {
      value: 'inventory',
      label: t('nav.inventory'),
      content: (
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{t('detail.currentStock') || 'Current Stock'}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{product.stock}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>{t('detail.stockLogs') || 'Stock Logs'}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                {inventoryLogs.map((log) => (
                  <div key={log.id} className="flex items-center justify-between border-b pb-2 last:border-0">
                    <div className="flex items-center gap-4">
                      <StatusBadge
                        status={log.type === 'in' ? 'success' : 'warning'}
                        label={log.type === 'in' ? '+' + log.quantity : '-' + log.quantity}
                      />
                      <span className="text-sm text-muted-foreground">{log.remark}</span>
                    </div>
                    <div className="text-sm text-muted-foreground">
                      {log.operator} - {log.time}
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      ),
    },
    {
      value: 'pricing',
      label: t('nav.pricing'),
      content: (
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{t('detail.currentPrice') || 'Current Price'}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-baseline gap-4">
                <span className="text-3xl font-bold">${product.price}</span>
                <span className="text-lg text-muted-foreground line-through">${product.originalPrice}</span>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>{t('menu.priceHistory')}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                {priceHistory.map((log) => (
                  <div key={log.id} className="flex items-center justify-between border-b pb-2 last:border-0">
                    <div className="flex items-center gap-4">
                      <span className="text-sm">${log.oldPrice} → ${log.newPrice}</span>
                      <span className="text-sm text-muted-foreground">{log.remark}</span>
                    </div>
                    <div className="text-sm text-muted-foreground">
                      {log.operator} - {log.time}
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      ),
    },
    {
      value: 'logs',
      label: t('menu.operationLogs'),
      content: (
        <Card>
          <CardHeader>
            <CardTitle>{t('menu.operationLogs')}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {operationLogs.map((log) => (
                <div key={log.id} className="flex items-center justify-between border-b pb-2 last:border-0">
                  <div className="flex items-center gap-4">
                    <StatusBadge status="info" label={log.action} />
                    <span className="text-sm">
                      {log.field}: {log.oldValue} → {log.newValue}
                    </span>
                  </div>
                  <div className="text-sm text-muted-foreground">
                    {log.operator} - {log.time}
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      ),
    },
  ]

  return (
    <div className="space-y-4">
      {/* Header with back button and actions */}
      <DetailHeader backUrl="/products">
        <Button variant="outline">{t('common.edit')}</Button>
        <Button variant="destructive">{t('common.delete')}</Button>
      </DetailHeader>

      {/* Core Info Card */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-start justify-between">
            <div>
              <h1 className="text-2xl font-bold">{product.name}</h1>
              <p className="text-muted-foreground">{product.sku}</p>
            </div>
            <StatusBadge
              status={product.status === 'on_sale' ? 'success' : 'default'}
              label={product.status === 'on_sale' ? t('products.onSale') : t('products.offSale')}
            />
          </div>
          <div className="mt-4 grid grid-cols-3 gap-4">
            <div>
              <p className="text-sm text-muted-foreground">{t('products.price')}</p>
              <p className="text-xl font-semibold">${product.price}</p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground">{t('products.stock')}</p>
              <p className="text-xl font-semibold">{product.stock}</p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground">{t('products.category')}</p>
              <p className="text-xl font-semibold">{product.category}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Tabs for detailed info */}
      <DetailTabs tabs={tabs} />
    </div>
  )
}