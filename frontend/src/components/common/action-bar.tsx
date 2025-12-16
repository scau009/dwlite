"use client"

import { useTranslation } from "react-i18next"
import { Plus, Upload, Download } from "lucide-react"

import { Button } from "@/components/ui/button"

export interface ActionButton {
  key: string
  label: string
  icon?: React.ReactNode
  variant?: 'default' | 'outline' | 'secondary' | 'destructive' | 'ghost'
  onClick: () => void
  showOnSelect?: boolean
  hideOnSelect?: boolean
}

interface ActionBarProps {
  selectedCount: number
  actions?: ActionButton[]
  onAdd?: () => void
  onImport?: () => void
  onExport?: () => void
}

export function ActionBar({
  selectedCount,
  actions = [],
  onAdd,
  onImport,
  onExport,
}: ActionBarProps) {
  const { t } = useTranslation()

  const defaultActions = actions.filter(
    (action) => !action.showOnSelect || (action.showOnSelect && selectedCount > 0)
  ).filter(
    (action) => !action.hideOnSelect || (action.hideOnSelect && selectedCount === 0)
  )

  return (
    <div className="flex items-center justify-between py-2">
      <div className="flex items-center gap-2">
        {selectedCount > 0 && (
          <span className="text-sm text-muted-foreground">
            {t('common.selected', { count: selectedCount })}
          </span>
        )}
      </div>
      <div className="flex items-center gap-2">
        {defaultActions.map((action) => (
          <Button
            key={action.key}
            variant={action.variant || 'outline'}
            size="sm"
            className="h-8"
            onClick={action.onClick}
          >
            {action.icon}
            {action.label}
          </Button>
        ))}

        {onImport && selectedCount === 0 && (
          <Button variant="outline" size="sm" className="h-8" onClick={onImport}>
            <Upload className="mr-1.5 h-3.5 w-3.5" />
            {t('common.batchImport')}
          </Button>
        )}
        {onExport && (
          <Button variant="outline" size="sm" className="h-8" onClick={onExport}>
            <Download className="mr-1.5 h-3.5 w-3.5" />
            {selectedCount > 0 ? t('common.export') : t('common.batchExport')}
          </Button>
        )}
        {onAdd && selectedCount === 0 && (
          <Button size="sm" className="h-8" onClick={onAdd}>
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            {t('common.add')}
          </Button>
        )}
      </div>
    </div>
  )
}