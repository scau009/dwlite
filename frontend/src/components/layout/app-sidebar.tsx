"use client"

import {
  Package,
  Warehouse,
  DollarSign,
  ShoppingCart,
  Truck,
  BarChart3,
  Settings,
  LayoutDashboard,
} from "lucide-react"
import { useTranslation } from "react-i18next"

import { NavMain, type NavItem } from "@/components/layout/nav-main"
import { NavUser } from "@/components/layout/nav-user"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
  SidebarSeparator,
} from "@/components/ui/sidebar"

// Logo component
function Logo() {
  return (
    <div className="flex items-center gap-2 px-2">
      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
        <Package className="h-4 w-4" />
      </div>
      <div className="grid flex-1 text-left text-sm leading-tight">
        <span className="truncate font-semibold">DWLite</span>
        <span className="truncate text-xs text-muted-foreground">
          Admin Console
        </span>
      </div>
    </div>
  )
}

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { t } = useTranslation()

  // Navigation data based on UI.md menu structure
  const navItems: NavItem[] = [
    {
      title: t('nav.dashboard'),
      url: "/dashboard",
      icon: LayoutDashboard,
    },
    {
      title: t('nav.products'),
      url: "/products",
      icon: Package,
      items: [
        { title: t('menu.productList'), url: "/products" },
        { title: t('menu.productCategories'), url: "/products/categories" },
        { title: t('menu.productAttributes'), url: "/products/attributes" },
      ],
    },
    {
      title: t('nav.inventory'),
      url: "/inventory",
      icon: Warehouse,
      items: [
        { title: t('menu.inventoryList'), url: "/inventory" },
        { title: t('menu.inventoryAlerts'), url: "/inventory/alerts" },
        { title: t('menu.inventoryLogs'), url: "/inventory/logs" },
      ],
    },
    {
      title: t('nav.pricing'),
      url: "/pricing",
      icon: DollarSign,
      items: [
        { title: t('menu.priceList'), url: "/pricing" },
        { title: t('menu.priceRules'), url: "/pricing/rules" },
        { title: t('menu.priceHistory'), url: "/pricing/history" },
      ],
    },
    {
      title: t('nav.orders'),
      url: "/orders",
      icon: ShoppingCart,
      items: [
        { title: t('menu.orderList'), url: "/orders" },
        { title: t('menu.orderPending'), url: "/orders/pending" },
        { title: t('menu.orderCompleted'), url: "/orders/completed" },
        { title: t('menu.orderRefunds'), url: "/orders/refunds" },
      ],
    },
    {
      title: t('nav.fulfillment'),
      url: "/fulfillment",
      icon: Truck,
      items: [
        { title: t('menu.fulfillmentList'), url: "/fulfillment" },
        { title: t('menu.fulfillmentPending'), url: "/fulfillment/pending" },
        { title: t('menu.fulfillmentShipped'), url: "/fulfillment/shipped" },
        { title: t('menu.fulfillmentExceptions'), url: "/fulfillment/exceptions" },
      ],
    },
    {
      title: t('nav.dataCenter'),
      url: "/data",
      icon: BarChart3,
      items: [
        { title: t('menu.dataOverview'), url: "/data" },
        { title: t('menu.salesAnalysis'), url: "/data/sales" },
        { title: t('menu.inventoryAnalysis'), url: "/data/inventory" },
        { title: t('menu.reports'), url: "/data/reports" },
      ],
    },
    {
      title: t('nav.settings'),
      url: "/settings",
      icon: Settings,
      items: [
        { title: t('menu.generalSettings'), url: "/settings" },
        { title: t('menu.userManagement'), url: "/settings/users" },
        { title: t('menu.roleManagement'), url: "/settings/roles" },
        { title: t('menu.operationLogs'), url: "/settings/logs" },
      ],
    },
  ]

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <Logo />
      </SidebarHeader>
      <SidebarSeparator />
      <SidebarContent>
        <NavMain items={navItems} />
      </SidebarContent>
      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}