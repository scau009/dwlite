"use client"

import { Bell, Search, Moon, Sun } from "lucide-react"
import { Link, useLocation } from "react-router"
import { useEffect, useState } from "react"

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"

// Breadcrumb mapping
const breadcrumbMap: Record<string, string> = {
  dashboard: "Dashboard",
  products: "Products",
  categories: "Categories",
  inventory: "Inventory",
  reviews: "Reviews",
  orders: "Orders",
  pending: "Pending",
  completed: "Completed",
  refunds: "Refunds",
  customers: "Customers",
  segments: "Segments",
  promotions: "Promotions",
  coupons: "Coupons",
  campaigns: "Campaigns",
  "flash-sales": "Flash Sales",
  shipping: "Shipping",
  payments: "Payments",
  analytics: "Analytics",
  sales: "Sales",
  traffic: "Traffic",
  conversions: "Conversions",
  reports: "Reports",
  settings: "Settings",
  store: "Store",
  notifications: "Notifications",
  integrations: "Integrations",
  profile: "Profile",
}

function ThemeToggle() {
  const [theme, setTheme] = useState<"light" | "dark">("light")

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
      <span className="sr-only">Toggle theme</span>
    </Button>
  )
}

export function AppHeader() {
  const location = useLocation()
  const pathSegments = location.pathname.split("/").filter(Boolean)

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
              const label = breadcrumbMap[segment] || segment

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

      {/* Right: Search + Actions */}
      <div className="ml-auto flex items-center gap-2">
        {/* Search */}
        <div className="relative hidden md:block">
          <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input
            type="search"
            placeholder="Search..."
            className="w-64 pl-8"
          />
        </div>

        {/* Notifications */}
        <Button variant="ghost" size="icon" className="relative">
          <Bell className="h-4 w-4" />
          <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-destructive text-[10px] font-medium text-destructive-foreground">
            3
          </span>
          <span className="sr-only">Notifications</span>
        </Button>

        {/* Theme Toggle */}
        <ThemeToggle />
      </div>
    </header>
  )
}
