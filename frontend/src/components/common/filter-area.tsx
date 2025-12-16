"use client"

import { useState } from "react"
import { ChevronDown, ChevronUp, Search, RotateCcw } from "lucide-react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"

export interface FilterField {
  key: string
  label: string
  type: 'text' | 'select' | 'date' | 'dateRange'
  placeholder?: string
  options?: { label: string; value: string }[]
}

interface FilterAreaProps {
  fields: FilterField[]
  values: Record<string, string>
  onChange: (key: string, value: string) => void
  onSearch: () => void
  onReset: () => void
  defaultExpanded?: boolean
  moreFields?: FilterField[]
}

export function FilterArea({
  fields,
  values,
  onChange,
  onSearch,
  onReset,
  defaultExpanded = true,
  moreFields,
}: FilterAreaProps) {
  const { t } = useTranslation()
  const [isExpanded, setIsExpanded] = useState(defaultExpanded)
  const [showMore, setShowMore] = useState(false)

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      onSearch()
    }
  }

  const renderField = (field: FilterField) => {
    switch (field.type) {
      case 'select':
        return (
          <Select
            value={values[field.key] || ''}
            onValueChange={(value: string) => onChange(field.key, value)}
          >
            <SelectTrigger className="h-9 bg-background">
              <SelectValue placeholder={field.placeholder || t('common.all')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('common.all')}</SelectItem>
              {field.options?.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )
      case 'text':
      default:
        return (
          <Input
            className="h-9 bg-background"
            placeholder={field.placeholder}
            value={values[field.key] || ''}
            onChange={(e) => onChange(field.key, e.target.value)}
            onKeyDown={handleKeyDown}
          />
        )
    }
  }

  return (
    <Collapsible open={isExpanded} onOpenChange={setIsExpanded}>
      <div className="rounded-lg bg-muted/30">
        <CollapsibleTrigger asChild>
          <div className="flex cursor-pointer items-center justify-between px-4 py-3 hover:bg-muted/50 transition-colors rounded-lg">
            <span className="text-sm font-medium text-foreground">{t('common.search')}</span>
            <div className="flex items-center gap-2 text-muted-foreground">
              {!isExpanded && Object.keys(values).filter(k => values[k]).length > 0 && (
                <span className="text-xs">
                  {Object.keys(values).filter(k => values[k]).length} {t('common.filters')}
                </span>
              )}
              {isExpanded ? (
                <ChevronUp className="h-4 w-4" />
              ) : (
                <ChevronDown className="h-4 w-4" />
              )}
            </div>
          </div>
        </CollapsibleTrigger>
        <CollapsibleContent>
          <div className="px-4 pb-4">
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              {fields.map((field) => (
                <div key={field.key} className="space-y-1.5">
                  <label className="text-xs font-medium text-muted-foreground">
                    {field.label}
                  </label>
                  {renderField(field)}
                </div>
              ))}
            </div>

            {moreFields && moreFields.length > 0 && (
              <>
                <Button
                  variant="ghost"
                  size="sm"
                  className="mt-3 h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                  onClick={() => setShowMore(!showMore)}
                >
                  {showMore ? t('common.lessFilters') : t('common.moreFilters')}
                  {showMore ? (
                    <ChevronUp className="ml-1 h-3 w-3" />
                  ) : (
                    <ChevronDown className="ml-1 h-3 w-3" />
                  )}
                </Button>
                {showMore && (
                  <div className="mt-3 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {moreFields.map((field) => (
                      <div key={field.key} className="space-y-1.5">
                        <label className="text-xs font-medium text-muted-foreground">
                          {field.label}
                        </label>
                        {renderField(field)}
                      </div>
                    ))}
                  </div>
                )}
              </>
            )}

            <div className="mt-4 flex gap-2">
              <Button size="sm" onClick={onSearch} className="h-8">
                <Search className="mr-1.5 h-3.5 w-3.5" />
                {t('common.query')}
              </Button>
              <Button variant="outline" size="sm" onClick={onReset} className="h-8">
                <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                {t('common.reset')}
              </Button>
            </div>
          </div>
        </CollapsibleContent>
      </div>
    </Collapsible>
  )
}