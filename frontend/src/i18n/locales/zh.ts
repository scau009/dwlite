export default {
  // Common
  common: {
    search: '搜索',
    reset: '重置',
    query: '查询',
    add: '新增',
    edit: '编辑',
    view: '查看',
    delete: '删除',
    save: '保存',
    cancel: '取消',
    confirm: '确认',
    back: '返回',
    export: '导出',
    import: '导入',
    batchExport: '批量导出',
    batchImport: '批量导入',
    moreFilters: '更多筛选',
    lessFilters: '收起筛选',
    filters: '个筛选条件',
    selected: '已选择 {{count}} 项',
    operation: '操作',
    status: '状态',
    createdAt: '创建时间',
    updatedAt: '更新时间',
    actions: '操作',
    loading: '加载中...',
    noData: '暂无数据',
    showing: '显示 {{from}}-{{to}} 条，共 {{total}} 条',
    success: '操作成功',
    error: '操作失败',
    required: '必填',
    all: '全部',
  },

  // Navigation
  nav: {
    dashboard: '工作台',
    products: '商品管理',
    inventory: '库存管理',
    pricing: '价格管理',
    orders: '订单管理',
    fulfillment: '履约管理',
    dataCenter: '数据中心',
    settings: '系统设置',
  },

  // Sidebar menu items
  menu: {
    // Products
    productList: '商品列表',
    productCategories: '商品分类',
    productAttributes: '商品属性',
    // Inventory
    inventoryList: '库存列表',
    inventoryAlerts: '库存预警',
    inventoryLogs: '出入库记录',
    // Pricing
    priceList: '价格列表',
    priceRules: '价格规则',
    priceHistory: '调价记录',
    // Orders
    orderList: '订单列表',
    orderPending: '待处理订单',
    orderCompleted: '已完成订单',
    orderRefunds: '退款售后',
    // Fulfillment
    fulfillmentList: '履约列表',
    fulfillmentPending: '待发货',
    fulfillmentShipped: '已发货',
    fulfillmentExceptions: '异常处理',
    // Data Center
    dataOverview: '数据概览',
    salesAnalysis: '销售分析',
    inventoryAnalysis: '库存分析',
    reports: '报表中心',
    // Settings
    generalSettings: '基本设置',
    userManagement: '用户管理',
    roleManagement: '角色权限',
    operationLogs: '操作日志',
  },

  // Header
  header: {
    notifications: '通知',
    switchTheme: '切换主题',
    switchLanguage: '切换语言',
    profile: '个人资料',
    logout: '退出登录',
  },

  // Auth
  auth: {
    login: '登录',
    register: '注册',
    email: '邮箱',
    password: '密码',
    forgotPassword: '忘记密码',
    resetPassword: '重置密码',
    verifyEmail: '验证邮箱',
  },

  // Dashboard
  dashboard: {
    title: '工作台',
    description: '欢迎回来！以下是您的业务概览。',
    todayOrders: '今日订单',
    todaySales: '今日销售额',
    inventoryAlerts: '库存预警',
    fulfillmentExceptions: '履约异常',
    recentOrders: '最近订单',
    topProducts: '热销商品',
    fromLastMonth: '较上月',
    activeListings: '在售商品',
  },

  // Products
  products: {
    title: '商品管理',
    description: '管理您的商品信息',
    productName: '商品名称',
    sku: 'SKU',
    category: '分类',
    price: '价格',
    stock: '库存',
    status: '状态',
    onSale: '在售',
    offSale: '下架',
    addProduct: '新增商品',
    batchOnSale: '批量上架',
    batchOffSale: '批量下架',
    batchUpdateStock: '批量改库存',
    batchUpdatePrice: '批量改价',
  },

  // Orders
  orders: {
    title: '订单管理',
    description: '管理您的订单信息',
    orderNo: '订单号',
    customer: '客户',
    amount: '金额',
    status: '状态',
    pending: '待处理',
    processing: '处理中',
    shipped: '已发货',
    completed: '已完成',
    cancelled: '已取消',
    refunded: '已退款',
    orderTime: '下单时间',
  },

  // Form page
  form: {
    basicInfoDescription: '填写商品的基本信息',
    pricingDescription: '设置商品的价格信息',
    inventoryDescription: '管理库存设置',
    originalPrice: '原价',
  },

  // Detail page
  detail: {
    basicInfo: '基本信息',
    brand: '品牌',
    weight: '重量',
    dimensions: '尺寸',
    description: '描述',
    currentStock: '当前库存',
    stockLogs: '库存记录',
    currentPrice: '当前价格',
  },

  // Status
  status: {
    enabled: '启用',
    disabled: '停用',
    normal: '正常',
    abnormal: '异常',
  },

  // Form validation
  validation: {
    required: '{{field}}不能为空',
    email: '请输入有效的邮箱地址',
    minLength: '{{field}}至少{{min}}个字符',
    maxLength: '{{field}}最多{{max}}个字符',
  },

  // Confirm dialogs
  confirm: {
    deleteTitle: '确认删除',
    deleteMessage: '确定要删除所选项目吗？此操作不可撤销。',
    batchTitle: '批量操作确认',
    batchMessage: '确定要对所选的 {{count}} 项进行此操作吗？',
  },
}