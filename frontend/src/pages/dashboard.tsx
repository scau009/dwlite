import {
  ArrowDownRight,
  ArrowUpRight,
  DollarSign,
  Package,
  ShoppingCart,
  Users,
} from "lucide-react"

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"

// Stat card component
interface StatCardProps {
  title: string
  value: string
  description: string
  icon: React.ElementType
  trend: "up" | "down"
  trendValue: string
}

function StatCard({
  title,
  value,
  description,
  icon: Icon,
  trend,
  trendValue,
}: StatCardProps) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        <Icon className="h-4 w-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
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
      </CardContent>
    </Card>
  )
}

// Recent orders component
function RecentOrders() {
  const orders = [
    {
      id: "ORD-001",
      customer: "John Doe",
      product: "Premium Sneakers",
      amount: "$299.00",
      status: "Completed",
    },
    {
      id: "ORD-002",
      customer: "Jane Smith",
      product: "Designer Jacket",
      amount: "$450.00",
      status: "Processing",
    },
    {
      id: "ORD-003",
      customer: "Bob Johnson",
      product: "Limited Edition Watch",
      amount: "$1,299.00",
      status: "Pending",
    },
    {
      id: "ORD-004",
      customer: "Alice Brown",
      product: "Vintage Bag",
      amount: "$189.00",
      status: "Completed",
    },
    {
      id: "ORD-005",
      customer: "Charlie Wilson",
      product: "Street Style Hoodie",
      amount: "$129.00",
      status: "Shipped",
    },
  ]

  const getStatusColor = (status: string) => {
    switch (status) {
      case "Completed":
        return "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300"
      case "Processing":
        return "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300"
      case "Pending":
        return "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300"
      case "Shipped":
        return "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300"
      default:
        return "bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300"
    }
  }

  return (
    <Card className="col-span-full lg:col-span-2">
      <CardHeader>
        <CardTitle>Recent Orders</CardTitle>
        <CardDescription>Latest orders from your store</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {orders.map((order) => (
            <div
              key={order.id}
              className="flex items-center justify-between border-b pb-4 last:border-0 last:pb-0"
            >
              <div className="space-y-1">
                <p className="text-sm font-medium">{order.customer}</p>
                <p className="text-xs text-muted-foreground">{order.product}</p>
              </div>
              <div className="flex items-center gap-4">
                <span className="text-sm font-medium">{order.amount}</span>
                <span
                  className={`rounded-full px-2 py-1 text-xs font-medium ${getStatusColor(
                    order.status
                  )}`}
                >
                  {order.status}
                </span>
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
  const products = [
    { name: "Premium Sneakers", sales: 234, revenue: "$70,026" },
    { name: "Designer Jacket", sales: 189, revenue: "$85,050" },
    { name: "Limited Edition Watch", sales: 156, revenue: "$202,644" },
    { name: "Vintage Bag", sales: 142, revenue: "$26,838" },
    { name: "Street Style Hoodie", sales: 128, revenue: "$16,512" },
  ]

  return (
    <Card>
      <CardHeader>
        <CardTitle>Top Products</CardTitle>
        <CardDescription>Best sellers this month</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {products.map((product, index) => (
            <div key={product.name} className="flex items-center gap-4">
              <span className="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-sm font-medium">
                {index + 1}
              </span>
              <div className="flex-1 space-y-1">
                <p className="text-sm font-medium">{product.name}</p>
                <p className="text-xs text-muted-foreground">
                  {product.sales} sales
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
  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
        <p className="text-muted-foreground">
          Welcome back! Here's an overview of your store.
        </p>
      </div>

      {/* Stats Grid */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="Total Revenue"
          value="$45,231.89"
          description="from last month"
          icon={DollarSign}
          trend="up"
          trendValue="+20.1%"
        />
        <StatCard
          title="Orders"
          value="2,350"
          description="from last month"
          icon={ShoppingCart}
          trend="up"
          trendValue="+15.2%"
        />
        <StatCard
          title="Products"
          value="1,234"
          description="active listings"
          icon={Package}
          trend="up"
          trendValue="+12"
        />
        <StatCard
          title="Customers"
          value="12,543"
          description="from last month"
          icon={Users}
          trend="down"
          trendValue="-2.4%"
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
