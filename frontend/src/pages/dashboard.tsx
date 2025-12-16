"use client"

import { useNavigate } from "react-router"
import { useTranslation } from "react-i18next"
import {
  ArrowDownRight,
  ArrowUpRight,
  ShoppingCart,
  DollarSign,
  AlertTriangle,
  Truck,
} from "lucide-react"

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { StatusBadge } from "@/components/common"

// Stat card component - clickable per UI.md spec
interface StatCardProps {
  title: string
  value: string
  description: string
  icon: React.ElementType
  trend?: "up" | "down"
  trendValue?: string
  onClick?: () => void
}

function StatCard({
  title,
  value,
  description,
  icon: Icon,
  trend,
  trendValue,
  onClick,
}: StatCardProps) {
  return (
    <Card
      className={onClick ? "cursor-pointer transition-colors hover:bg-muted/50" : ""}
      onClick={onClick}
    >
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        <Icon className="h-4 w-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
        {trend && trendValue && (
          <div className="flex items-center text-xs text-muted-foreground">
            {trend === "up" ? (
              <ArrowUpRight className="mr-1 h-4 w-4 text-green-500" />
            ) : (
              <ArrowDownRight className="mr-1 h-4 w-4 text-red-500" />
            )}
            <span className={trend === "up" ? "text-green-500" : "text-red-500"}>
              {trendValue}
            </span>
            <span className="ml-1">{description}</span>
          </div>
        )}
        {!trend && <p className="text-xs text-muted-foreground">{description}</p>}
      </CardContent>
    </Card>
  )
}

// Recent orders component
function RecentOrders() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const orders = [
    { id: "ORD-001", customer: "John Doe", amount: "$299.00", status: "completed" },
    { id: "ORD-002", customer: "Jane Smith", amount: "$450.00", status: "processing" },
    { id: "ORD-003", customer: "Bob Johnson", amount: "$1,299.00", status: "pending" },
    { id: "ORD-004", customer: "Alice Brown", amount: "$189.00", status: "completed" },
    { id: "ORD-005", customer: "Charlie Wilson", amount: "$129.00", status: "shipped" },
  ]

  const getStatusType = (status: string): "success" | "warning" | "error" | "info" | "default" => {
    switch (status) {
      case "completed": return "success"
      case "processing": return "info"
      case "pending": return "warning"
      case "shipped": return "info"
      default: return "default"
    }
  }

  const getStatusLabel = (status: string) => {
    switch (status) {
      case "completed": return t('orders.completed')
      case "processing": return t('orders.processing')
      case "pending": return t('orders.pending')
      case "shipped": return t('orders.shipped')
      default: return status
    }
  }

  return (
    <Card className="col-span-full lg:col-span-2">
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle>{t('dashboard.recentOrders')}</CardTitle>
          <CardDescription>{t('orders.description')}</CardDescription>
        </div>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {orders.map((order) => (
            <div
              key={order.id}
              className="flex items-center justify-between border-b pb-4 last:border-0 last:pb-0 cursor-pointer hover:bg-muted/50 -mx-2 px-2 py-2 rounded"
              onClick={() => navigate(`/orders?id=${order.id}`)}
            >
              <div className="space-y-1">
                <p className="text-sm font-medium">{order.customer}</p>
                <p className="text-xs text-muted-foreground">{order.id}</p>
              </div>
              <div className="flex items-center gap-4">
                <span className="text-sm font-medium">{order.amount}</span>
                <StatusBadge
                  status={getStatusType(order.status)}
                  label={getStatusLabel(order.status)}
                />
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}

// Top products component
function TopProducts() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const products = [
    { id: "1", name: "Premium Sneakers", sales: 234, revenue: "$70,026" },
    { id: "2", name: "Designer Jacket", sales: 189, revenue: "$85,050" },
    { id: "3", name: "Limited Edition Watch", sales: 156, revenue: "$202,644" },
    { id: "4", name: "Vintage Bag", sales: 142, revenue: "$26,838" },
    { id: "5", name: "Street Style Hoodie", sales: 128, revenue: "$16,512" },
  ]

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('dashboard.topProducts')}</CardTitle>
        <CardDescription>{t('products.description')}</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {products.map((product, index) => (
            <div
              key={product.id}
              className="flex items-center gap-4 cursor-pointer hover:bg-muted/50 -mx-2 px-2 py-2 rounded"
              onClick={() => navigate(`/products/${product.id}`)}
            >
              <span className="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-sm font-medium">
                {index + 1}
              </span>
              <div className="flex-1 space-y-1">
                <p className="text-sm font-medium">{product.name}</p>
                <p className="text-xs text-muted-foreground">
                  {product.sales} {t('dashboard.activeListings')}
                </p>
              </div>
              <span className="text-sm font-medium">{product.revenue}</span>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}

export function DashboardPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{t('dashboard.title')}</h1>
        <p className="text-muted-foreground">{t('dashboard.description')}</p>
      </div>

      {/* Stats Grid - per UI.md: clickable metrics that link to list pages */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title={t('dashboard.todayOrders')}
          value="128"
          description={t('dashboard.fromLastMonth')}
          icon={ShoppingCart}
          trend="up"
          trendValue="+15.2%"
          onClick={() => navigate('/orders?date=today')}
        />
        <StatCard
          title={t('dashboard.todaySales')}
          value="$12,450"
          description={t('dashboard.fromLastMonth')}
          icon={DollarSign}
          trend="up"
          trendValue="+20.1%"
          onClick={() => navigate('/data/sales?date=today')}
        />
        <StatCard
          title={t('dashboard.inventoryAlerts')}
          value="23"
          description={t('menu.inventoryAlerts')}
          icon={AlertTriangle}
          onClick={() => navigate('/inventory/alerts')}
        />
        <StatCard
          title={t('dashboard.fulfillmentExceptions')}
          value="5"
          description={t('menu.fulfillmentExceptions')}
          icon={Truck}
          onClick={() => navigate('/fulfillment/exceptions')}
        />
      </div>

      {/* Charts & Tables Grid */}
      <div className="grid gap-4 lg:grid-cols-3">
        <RecentOrders />
        <TopProducts />
      </div>
    </div>
  )
}