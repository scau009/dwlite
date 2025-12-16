"use client"

import { useState, useCallback } from "react"
import { useTranslation } from "react-i18next"
import { useNavigate } from "react-router"
import { ArrowUp, ArrowDown } from "lucide-react"

import {
  PageHeader,
  FilterArea,
  ActionBar,
  DataTable,
  StatusBadge,
  ConfirmDialog,
  type FilterField,
  type Column,
  type RowAction,
  type ActionButton,
  type StatusType,
} from "@/components/common"

// Product type
interface Product {
  id: string
  name: string
  sku: string
  category: string
  price: number
  stock: number
  status: 'on_sale' | 'off_sale'
  updatedAt: string
}

// Mock data
const mockProducts: Product[] = [
  { id: '1', name: 'Premium Sneakers', sku: 'SKU-001', category: 'Footwear', price: 299, stock: 150, status: 'on_sale', updatedAt: '2024-01-15 10:30' },
  { id: '2', name: 'Designer Jacket', sku: 'SKU-002', category: 'Apparel', price: 450, stock: 80, status: 'on_sale', updatedAt: '2024-01-14 14:20' },
  { id: '3', name: 'Limited Edition Watch', sku: 'SKU-003', category: 'Accessories', price: 1299, stock: 25, status: 'on_sale', updatedAt: '2024-01-13 09:15' },
  { id: '4', name: 'Vintage Bag', sku: 'SKU-004', category: 'Accessories', price: 189, stock: 200, status: 'on_sale', updatedAt: '2024-01-12 16:45' },
  { id: '5', name: 'Street Style Hoodie', sku: 'SKU-005', category: 'Apparel', price: 129, stock: 0, status: 'off_sale', updatedAt: '2024-01-11 11:00' },
  { id: '6', name: 'Classic Sunglasses', sku: 'SKU-006', category: 'Accessories', price: 79, stock: 300, status: 'on_sale', updatedAt: '2024-01-10 08:30' },
  { id: '7', name: 'Running Shoes', sku: 'SKU-007', category: 'Footwear', price: 199, stock: 120, status: 'on_sale', updatedAt: '2024-01-09 13:20' },
  { id: '8', name: 'Casual T-Shirt', sku: 'SKU-008', category: 'Apparel', price: 39, stock: 500, status: 'on_sale', updatedAt: '2024-01-08 15:10' },
  { id: '9', name: 'Leather Wallet', sku: 'SKU-009', category: 'Accessories', price: 89, stock: 180, status: 'on_sale', updatedAt: '2024-01-07 10:00' },
  { id: '10', name: 'Winter Coat', sku: 'SKU-010', category: 'Apparel', price: 599, stock: 45, status: 'off_sale', updatedAt: '2024-01-06 09:45' },
]

export function ProductsListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  // Filter state
  const [filters, setFilters] = useState<Record<string, string>>({})

  // Selection state
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([])

  // Sort state
  const [sortKey, setSortKey] = useState<keyof Product | undefined>()
  const [, setSortOrder] = useState<'asc' | 'desc'>('desc')

  // Pagination state
  const [page, setPage] = useState(1)
  const pageSize = 10

  // Confirm dialog state
  const [confirmDialog, setConfirmDialog] = useState<{
    open: boolean
    title: string
    description: string
    onConfirm: () => void
    danger?: boolean
  }>({ open: false, title: '', description: '', onConfirm: () => {} })

  // Filter fields
  const filterFields: FilterField[] = [
    {
      key: 'keyword',
      label: t('products.productName') + ' / ' + t('products.sku'),
      type: 'text',
      placeholder: t('common.search') + '...',
    },
    {
      key: 'category',
      label: t('products.category'),
      type: 'select',
      options: [
        { label: 'Footwear', value: 'Footwear' },
        { label: 'Apparel', value: 'Apparel' },
        { label: 'Accessories', value: 'Accessories' },
      ],
    },
    {
      key: 'status',
      label: t('products.status'),
      type: 'select',
      options: [
        { label: t('products.onSale'), value: 'on_sale' },
        { label: t('products.offSale'), value: 'off_sale' },
      ],
    },
  ]

  // Table columns
  const columns: Column<Product>[] = [
    {
      key: 'name',
      header: t('products.productName'),
      sortable: true,
    },
    {
      key: 'sku',
      header: t('products.sku'),
      width: '120px',
    },
    {
      key: 'category',
      header: t('products.category'),
      width: '120px',
    },
    {
      key: 'price',
      header: t('products.price'),
      sortable: true,
      width: '100px',
      render: (row) => `$${row.price.toFixed(2)}`,
    },
    {
      key: 'stock',
      header: t('products.stock'),
      sortable: true,
      width: '100px',
      render: (row) => (
        <span className={row.stock === 0 ? 'text-destructive' : ''}>
          {row.stock}
        </span>
      ),
    },
    {
      key: 'status',
      header: t('products.status'),
      width: '100px',
      render: (row) => {
        const statusMap: Record<Product['status'], { type: StatusType; label: string }> = {
          on_sale: { type: 'success', label: t('products.onSale') },
          off_sale: { type: 'default', label: t('products.offSale') },
        }
        const { type, label } = statusMap[row.status]
        return <StatusBadge status={type} label={label} />
      },
    },
    {
      key: 'updatedAt',
      header: t('common.updatedAt'),
      sortable: true,
      width: '160px',
    },
    {
      key: 'actions',
      header: t('common.actions'),
      width: '80px',
    },
  ]

  // Row actions
  const rowActions: RowAction<Product>[] = [
    {
      key: 'edit',
      label: t('common.edit'),
      onClick: (row) => navigate(`/products/${row.id}/edit`),
    },
    {
      key: 'view',
      label: t('common.view') || 'View',
      onClick: (row) => navigate(`/products/${row.id}`),
    },
    {
      key: 'delete',
      label: t('common.delete'),
      onClick: (row) => {
        setConfirmDialog({
          open: true,
          title: t('confirm.deleteTitle'),
          description: t('confirm.deleteMessage'),
          danger: true,
          onConfirm: () => {
            console.log('Delete product:', row.id)
            setConfirmDialog((prev) => ({ ...prev, open: false }))
          },
        })
      },
      danger: true,
    },
  ]

  // Action bar actions
  const actions: ActionButton[] = [
    {
      key: 'batchOnSale',
      label: t('products.batchOnSale'),
      icon: <ArrowUp className="mr-2 h-4 w-4" />,
      showOnSelect: true,
      onClick: () => {
        setConfirmDialog({
          open: true,
          title: t('confirm.batchTitle'),
          description: t('confirm.batchMessage', { count: selectedIds.length }),
          onConfirm: () => {
            console.log('Batch on sale:', selectedIds)
            setSelectedIds([])
            setConfirmDialog((prev) => ({ ...prev, open: false }))
          },
        })
      },
    },
    {
      key: 'batchOffSale',
      label: t('products.batchOffSale'),
      icon: <ArrowDown className="mr-2 h-4 w-4" />,
      showOnSelect: true,
      onClick: () => {
        setConfirmDialog({
          open: true,
          title: t('confirm.batchTitle'),
          description: t('confirm.batchMessage', { count: selectedIds.length }),
          onConfirm: () => {
            console.log('Batch off sale:', selectedIds)
            setSelectedIds([])
            setConfirmDialog((prev) => ({ ...prev, open: false }))
          },
        })
      },
    },
  ]

  // Handlers
  const handleFilterChange = useCallback((key: string, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value }))
  }, [])

  const handleSearch = useCallback(() => {
    console.log('Search with filters:', filters)
    setPage(1)
  }, [filters])

  const handleReset = useCallback(() => {
    setFilters({})
    setPage(1)
  }, [])

  const handleSort = useCallback((key: keyof Product) => {
    if (sortKey === key) {
      setSortOrder((prev) => (prev === 'asc' ? 'desc' : 'asc'))
    } else {
      setSortKey(key)
      setSortOrder('asc')
    }
  }, [sortKey])

  const handleAdd = useCallback(() => {
    navigate('/products/new')
  }, [navigate])

  const handleExport = useCallback(() => {
    console.log('Export products:', selectedIds.length > 0 ? selectedIds : 'all')
  }, [selectedIds])

  return (
    <div className="space-y-4">
      {/* Page Header */}
      <PageHeader
        title={t('products.title')}
        description={t('products.description')}
      />

      {/* Filter Area */}
      <FilterArea
        fields={filterFields}
        values={filters}
        onChange={handleFilterChange}
        onSearch={handleSearch}
        onReset={handleReset}
      />

      {/* Action Bar */}
      <ActionBar
        selectedCount={selectedIds.length}
        actions={actions}
        onAdd={handleAdd}
        onExport={handleExport}
      />

      {/* Data Table */}
      <DataTable
        columns={columns}
        data={mockProducts}
        rowActions={rowActions}
        selectedIds={selectedIds}
        onSelectChange={setSelectedIds}
        sortKey={sortKey}
        onSort={handleSort}
        page={page}
        pageSize={pageSize}
        total={mockProducts.length}
        onPageChange={setPage}
      />

      {/* Confirm Dialog */}
      <ConfirmDialog
        open={confirmDialog.open}
        onOpenChange={(open) => setConfirmDialog((prev) => ({ ...prev, open }))}
        title={confirmDialog.title}
        description={confirmDialog.description}
        onConfirm={confirmDialog.onConfirm}
        danger={confirmDialog.danger}
      />
    </div>
  )
}