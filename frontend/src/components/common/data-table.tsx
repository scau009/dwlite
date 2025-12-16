"use client"

import { useTranslation } from "react-i18next"
import { ArrowUpDown, MoreHorizontal } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination"

export interface Column<T> {
  key: keyof T | 'actions'
  header: string
  sortable?: boolean
  render?: (row: T) => React.ReactNode
  width?: string
}

export interface RowAction<T> {
  key: string
  label: string
  onClick: (row: T) => void
  danger?: boolean
}

interface DataTableProps<T extends { id: string | number }> {
  columns: Column<T>[]
  data: T[]
  rowActions?: RowAction<T>[]
  selectedIds: (string | number)[]
  onSelectChange: (ids: (string | number)[]) => void
  sortKey?: keyof T
  onSort?: (key: keyof T) => void
  page: number
  pageSize: number
  total: number
  onPageChange: (page: number) => void
  loading?: boolean
}

export function DataTable<T extends { id: string | number }>({
  columns,
  data,
  rowActions,
  selectedIds,
  onSelectChange,
  sortKey,
  onSort,
  page,
  pageSize,
  total,
  onPageChange,
  loading,
}: DataTableProps<T>) {
  const { t } = useTranslation()
  const totalPages = Math.ceil(total / pageSize)

  const isAllSelected = data.length > 0 && data.every((row) => selectedIds.includes(row.id))
  const isSomeSelected = data.some((row) => selectedIds.includes(row.id)) && !isAllSelected

  const handleSelectAll = () => {
    if (isAllSelected) {
      onSelectChange(selectedIds.filter((id) => !data.some((row) => row.id === id)))
    } else {
      const newIds = [...selectedIds]
      data.forEach((row) => {
        if (!newIds.includes(row.id)) {
          newIds.push(row.id)
        }
      })
      onSelectChange(newIds)
    }
  }

  const handleSelectRow = (id: string | number) => {
    if (selectedIds.includes(id)) {
      onSelectChange(selectedIds.filter((selectedId) => selectedId !== id))
    } else {
      onSelectChange([...selectedIds, id])
    }
  }

  const renderSortHeader = (column: Column<T>) => {
    if (!column.sortable || column.key === 'actions') {
      return <span className="text-xs font-medium text-muted-foreground">{column.header}</span>
    }

    const isActive = sortKey === column.key
    return (
      <Button
        variant="ghost"
        size="sm"
        className="-ml-3 h-7 px-2 text-xs font-medium text-muted-foreground hover:text-foreground data-[state=active]:text-foreground"
        data-state={isActive ? 'active' : undefined}
        onClick={() => onSort?.(column.key as keyof T)}
      >
        {column.header}
        <ArrowUpDown className="ml-1 h-3 w-3" />
      </Button>
    )
  }

  return (
    <div className="space-y-3">
      <div className="rounded-lg border bg-card">
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="w-10 h-10">
                <Checkbox
                  checked={isAllSelected}
                  ref={(el: HTMLButtonElement | null) => {
                    if (el) {
                      el.dataset.indeterminate = isSomeSelected ? 'true' : 'false'
                    }
                  }}
                  onCheckedChange={handleSelectAll}
                  aria-label="Select all"
                />
              </TableHead>
              {columns.map((column) => (
                <TableHead
                  key={String(column.key)}
                  className="h-10"
                  style={column.width ? { width: column.width } : undefined}
                >
                  {renderSortHeader(column)}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={columns.length + 1} className="h-20 text-center">
                  <span className="text-sm text-muted-foreground">{t('common.loading')}</span>
                </TableCell>
              </TableRow>
            ) : data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={columns.length + 1} className="h-20 text-center">
                  <span className="text-sm text-muted-foreground">{t('common.noData')}</span>
                </TableCell>
              </TableRow>
            ) : (
              data.map((row) => (
                <TableRow
                  key={row.id}
                  className="h-12"
                  data-state={selectedIds.includes(row.id) ? 'selected' : undefined}
                >
                  <TableCell className="py-2">
                    <Checkbox
                      checked={selectedIds.includes(row.id)}
                      onCheckedChange={() => handleSelectRow(row.id)}
                      aria-label={`Select row ${row.id}`}
                    />
                  </TableCell>
                  {columns.map((column) => (
                    <TableCell key={String(column.key)} className="py-2 text-sm">
                      {column.key === 'actions' && rowActions ? (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-7 w-7">
                              <MoreHorizontal className="h-4 w-4" />
                              <span className="sr-only">{t('common.actions')}</span>
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            {rowActions.map((action) => (
                              <DropdownMenuItem
                                key={action.key}
                                onClick={() => action.onClick(row)}
                                className={action.danger ? 'text-destructive' : undefined}
                              >
                                {action.label}
                              </DropdownMenuItem>
                            ))}
                          </DropdownMenuContent>
                        </DropdownMenu>
                      ) : column.render ? (
                        column.render(row)
                      ) : (
                        String(row[column.key as keyof T] ?? '')
                      )}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {totalPages > 1 && (
        <div className="flex items-center justify-between">
          <p className="text-xs text-muted-foreground">
            {t('common.showing', { from: (page - 1) * pageSize + 1, to: Math.min(page * pageSize, total), total })}
          </p>
          <Pagination>
            <PaginationContent>
              <PaginationItem>
                <PaginationPrevious
                  onClick={() => page > 1 && onPageChange(page - 1)}
                  className={page <= 1 ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
                />
              </PaginationItem>
              {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                let pageNum: number
                if (totalPages <= 5) {
                  pageNum = i + 1
                } else if (page <= 3) {
                  pageNum = i + 1
                } else if (page >= totalPages - 2) {
                  pageNum = totalPages - 4 + i
                } else {
                  pageNum = page - 2 + i
                }
                return (
                  <PaginationItem key={pageNum}>
                    <PaginationLink
                      onClick={() => onPageChange(pageNum)}
                      isActive={page === pageNum}
                      className="cursor-pointer h-8 w-8 text-xs"
                    >
                      {pageNum}
                    </PaginationLink>
                  </PaginationItem>
                )
              })}
              <PaginationItem>
                <PaginationNext
                  onClick={() => page < totalPages && onPageChange(page + 1)}
                  className={page >= totalPages ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
                />
              </PaginationItem>
            </PaginationContent>
          </Pagination>
        </div>
      )}
    </div>
  )
}