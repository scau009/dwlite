"use client"

import { Moon, Sun, Languages } from "lucide-react"
import { Link, useLocation } from "react-router"
import { useEffect, useState } from "react"
import { useTranslation } from "react-i18next"

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"

function ThemeToggle() {
  const [theme, setTheme] = useState<"light" | "dark">("light")
  const { t } = useTranslation()

  useEffect(() => {
    const isDark = document.documentElement.classList.contains("dark")
    setTheme(isDark ? "dark" : "light")
  }, [])

  const toggleTheme = () => {
    const newTheme = theme === "light" ? "dark" : "light"
    setTheme(newTheme)
    document.documentElement.classList.toggle("dark", newTheme === "dark")
    localStorage.setItem("theme", newTheme)
  }

  return (
    <Button variant="ghost" size="icon" onClick={toggleTheme}>
      {theme === "light" ? (
        <Moon className="h-4 w-4" />
      ) : (
        <Sun className="h-4 w-4" />
      )}
      <span className="sr-only">{t('header.switchTheme')}</span>
    </Button>
  )
}

function LanguageSwitcher() {
  const { i18n, t } = useTranslation()

  const changeLanguage = (lang: string) => {
    i18n.changeLanguage(lang)
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon">
          <Languages className="h-4 w-4" />
          <span className="sr-only">{t('header.switchLanguage')}</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onClick={() => changeLanguage('zh')}>
          中文
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => changeLanguage('en')}>
          English
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

export function AppHeader() {
  const location = useLocation()
  const { t } = useTranslation()
  const pathSegments = location.pathname.split("/").filter(Boolean)

  // Breadcrumb mapping with i18n
  const getBreadcrumbLabel = (segment: string): string => {
    const breadcrumbMap: Record<string, string> = {
      dashboard: t('nav.dashboard'),
      products: t('nav.products'),
      categories: t('menu.productCategories'),
      attributes: t('menu.productAttributes'),
      inventory: t('nav.inventory'),
      alerts: t('menu.inventoryAlerts'),
      logs: t('menu.inventoryLogs'),
      pricing: t('nav.pricing'),
      rules: t('menu.priceRules'),
      history: t('menu.priceHistory'),
      orders: t('nav.orders'),
      pending: t('menu.orderPending'),
      completed: t('menu.orderCompleted'),
      refunds: t('menu.orderRefunds'),
      fulfillment: t('nav.fulfillment'),
      shipped: t('menu.fulfillmentShipped'),
      exceptions: t('menu.fulfillmentExceptions'),
      data: t('nav.dataCenter'),
      sales: t('menu.salesAnalysis'),
      reports: t('menu.reports'),
      settings: t('nav.settings'),
      users: t('menu.userManagement'),
      roles: t('menu.roleManagement'),
      profile: t('header.profile'),
    }
    return breadcrumbMap[segment] || segment
  }

  return (
    <header className="flex h-14 shrink-0 items-center gap-2 border-b bg-background px-4">
      {/* Left: Sidebar trigger + Breadcrumb */}
      <div className="flex items-center gap-2">
        <SidebarTrigger className="-ml-1" />
        <Separator orientation="vertical" className="mr-2 h-4" />
        <Breadcrumb>
          <BreadcrumbList>
            {pathSegments.map((segment, index) => {
              const isLast = index === pathSegments.length - 1
              const path = "/" + pathSegments.slice(0, index + 1).join("/")
              const label = getBreadcrumbLabel(segment)

              return (
                <BreadcrumbItem key={path}>
                  {!isLast ? (
                    <>
                      <BreadcrumbLink asChild>
                        <Link to={path}>{label}</Link>
                      </BreadcrumbLink>
                      <BreadcrumbSeparator />
                    </>
                  ) : (
                    <BreadcrumbPage>{label}</BreadcrumbPage>
                  )}
                </BreadcrumbItem>
              )
            })}
          </BreadcrumbList>
        </Breadcrumb>
      </div>

      {/* Right: Global actions only (no search per UI.md) */}
      <div className="ml-auto flex items-center gap-1">
        {/* Language Switcher */}
        <LanguageSwitcher />

        {/* Theme Toggle */}
        <ThemeToggle />
      </div>
    </header>
  )
}