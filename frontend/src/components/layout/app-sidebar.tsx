"use client"

import {
  LayoutDashboard,
  ShoppingBag,
  Users,
  Package,
  BarChart3,
  Settings,
  Tag,
  Truck,
  CreditCard,
  FileText,
} from "lucide-react"

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
        <ShoppingBag className="h-4 w-4" />
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

// Navigation data
const mainNavItems: NavItem[] = [
  {
    title: "Dashboard",
    url: "/dashboard",
    icon: LayoutDashboard,
  },
  {
    title: "Products",
    url: "/products",
    icon: Package,
    items: [
      { title: "All Products", url: "/products" },
      { title: "Categories", url: "/products/categories" },
      { title: "Inventory", url: "/products/inventory" },
      { title: "Reviews", url: "/products/reviews" },
    ],
  },
  {
    title: "Orders",
    url: "/orders",
    icon: ShoppingBag,
    items: [
      { title: "All Orders", url: "/orders" },
      { title: "Pending", url: "/orders/pending" },
      { title: "Completed", url: "/orders/completed" },
      { title: "Refunds", url: "/orders/refunds" },
    ],
  },
  {
    title: "Customers",
    url: "/customers",
    icon: Users,
    items: [
      { title: "All Customers", url: "/customers" },
      { title: "Segments", url: "/customers/segments" },
      { title: "Reviews", url: "/customers/reviews" },
    ],
  },
]

const commerceNavItems: NavItem[] = [
  {
    title: "Promotions",
    url: "/promotions",
    icon: Tag,
    items: [
      { title: "Coupons", url: "/promotions/coupons" },
      { title: "Campaigns", url: "/promotions/campaigns" },
      { title: "Flash Sales", url: "/promotions/flash-sales" },
    ],
  },
  {
    title: "Shipping",
    url: "/shipping",
    icon: Truck,
  },
  {
    title: "Payments",
    url: "/payments",
    icon: CreditCard,
  },
]

const analyticsNavItems: NavItem[] = [
  {
    title: "Analytics",
    url: "/analytics",
    icon: BarChart3,
    items: [
      { title: "Overview", url: "/analytics" },
      { title: "Sales", url: "/analytics/sales" },
      { title: "Traffic", url: "/analytics/traffic" },
      { title: "Conversions", url: "/analytics/conversions" },
    ],
  },
  {
    title: "Reports",
    url: "/reports",
    icon: FileText,
  },
]

const settingsNavItems: NavItem[] = [
  {
    title: "Settings",
    url: "/settings",
    icon: Settings,
    items: [
      { title: "General", url: "/settings" },
      { title: "Store", url: "/settings/store" },
      { title: "Notifications", url: "/settings/notifications" },
      { title: "Integrations", url: "/settings/integrations" },
    ],
  },
]

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <Logo />
      </SidebarHeader>
      <SidebarSeparator />
      <SidebarContent>
        <NavMain items={mainNavItems} label="Main" />
        <NavMain items={commerceNavItems} label="Commerce" />
        <NavMain items={analyticsNavItems} label="Analytics" />
        <NavMain items={settingsNavItems} label="System" />
      </SidebarContent>
      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
