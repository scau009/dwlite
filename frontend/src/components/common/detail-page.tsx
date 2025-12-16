"use client"

import { ArrowLeft } from "lucide-react"
import { useNavigate } from "react-router"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"

interface DetailHeaderProps {
  backUrl: string
  children?: React.ReactNode
}

export function DetailHeader({ backUrl, children }: DetailHeaderProps) {
  const navigate = useNavigate()
  const { t } = useTranslation()

  return (
    <div className="flex items-center justify-between pb-2">
      <Button
        variant="ghost"
        size="sm"
        className="-ml-2 h-8 text-muted-foreground hover:text-foreground"
        onClick={() => navigate(backUrl)}
      >
        <ArrowLeft className="mr-1.5 h-4 w-4" />
        {t('common.back')}
      </Button>
      {children && <div className="flex items-center gap-2">{children}</div>}
    </div>
  )
}

interface InfoCardProps {
  title: string
  description?: string
  children: React.ReactNode
}

export function InfoCard({ title, description, children }: InfoCardProps) {
  return (
    <div className="rounded-lg bg-muted/30 p-4">
      <div className="mb-3">
        <h3 className="text-sm font-medium text-foreground">{title}</h3>
        {description && <p className="text-xs text-muted-foreground mt-0.5">{description}</p>}
      </div>
      <div>{children}</div>
    </div>
  )
}

interface InfoRowProps {
  label: string
  value: React.ReactNode
}

export function InfoRow({ label, value }: InfoRowProps) {
  return (
    <div className="grid grid-cols-3 gap-4 py-2 border-b border-border/50 last:border-0">
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="text-sm col-span-2 text-foreground">{value}</dd>
    </div>
  )
}

interface DetailTabsProps {
  tabs: {
    value: string
    label: string
    content: React.ReactNode
  }[]
  defaultValue?: string
}

export function DetailTabs({ tabs, defaultValue }: DetailTabsProps) {
  return (
    <Tabs defaultValue={defaultValue || tabs[0]?.value} className="w-full">
      <TabsList className="h-9 bg-muted/50">
        {tabs.map((tab) => (
          <TabsTrigger
            key={tab.value}
            value={tab.value}
            className="text-xs data-[state=active]:bg-background"
          >
            {tab.label}
          </TabsTrigger>
        ))}
      </TabsList>
      {tabs.map((tab) => (
        <TabsContent key={tab.value} value={tab.value} className="mt-4">
          {tab.content}
        </TabsContent>
      ))}
    </Tabs>
  )
}