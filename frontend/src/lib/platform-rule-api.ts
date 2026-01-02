import { apiFetch } from './api-client';

// Types
export interface PlatformRule {
  id: string;
  code: string;
  name: string;
  description: string | null;
  type: 'pricing' | 'stock_priority' | 'settlement_fee';
  category: 'markup' | 'discount' | 'priority' | 'fee_rate';
  expression: string;
  conditionExpression: string | null;
  priority: number;
  config: Record<string, unknown> | null;
  isSystem: boolean;
  isActive: boolean;
  createdBy: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface PlatformRuleAssignment {
  id: string;
  scopeType: 'merchant' | 'channel_product';
  scopeId: string;
  scopeName: string;
  priorityOverride: number | null;
  configOverride: Record<string, unknown> | null;
  effectivePriority: number;
  isActive: boolean;
  createdAt: string;
}

export interface PlatformRuleAssignmentWithRule {
  id: string;
  rule: {
    id: string;
    code: string;
    name: string;
    type: string;
    category: string;
  };
  priorityOverride: number | null;
  configOverride: Record<string, unknown> | null;
  effectivePriority: number;
  isActive: boolean;
  createdAt: string;
}

export interface PlatformRuleDetail extends PlatformRule {
  assignments: PlatformRuleAssignment[];
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface PlatformRuleListParams {
  page?: number;
  limit?: number;
  type?: string;
  search?: string;
}

export interface CreatePlatformRuleParams {
  code: string;
  name: string;
  description?: string;
  type: 'pricing' | 'stock_priority' | 'settlement_fee';
  category: 'markup' | 'discount' | 'priority' | 'fee_rate';
  expression: string;
  conditionExpression?: string;
  priority?: number;
  config?: Record<string, unknown>;
  isActive?: boolean;
}

export interface UpdatePlatformRuleParams {
  name?: string;
  description?: string;
  category?: 'markup' | 'discount' | 'priority' | 'fee_rate';
  expression?: string;
  conditionExpression?: string;
  priority?: number;
  config?: Record<string, unknown>;
  isActive?: boolean;
}

export interface AssignPlatformRuleParams {
  scopeType: 'merchant' | 'channel_product';
  scopeId: string;
  priorityOverride?: number;
  configOverride?: Record<string, unknown>;
  isActive?: boolean;
}

export interface ValidateExpressionParams {
  expression: string;
  type: 'pricing' | 'stock_priority' | 'settlement_fee';
}

export interface ValidateResult {
  valid: boolean;
  error?: string;
}

export interface TestRuleParams {
  expression: string;
  conditionExpression?: string;
  type: 'pricing' | 'stock_priority' | 'settlement_fee';
  testContext?: Record<string, unknown>;
}

export interface TestResult {
  conditionResult: boolean;
  result: unknown;
  context: Record<string, unknown>;
  error?: string;
}

export interface RuleVariable {
  name: string;
  type: string;
  description: string;
}

export interface RuleFunction {
  name: string;
  signature: string;
  description: string;
  example: string;
}

export interface RuleReference {
  variables: RuleVariable[];
  functions: RuleFunction[];
}

export const platformRuleApi = {
  /**
   * Get platform rules list
   */
  getRules: async (params: PlatformRuleListParams = {}): Promise<PaginatedResponse<PlatformRule>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.type) searchParams.set('type', params.type);
    if (params.search) searchParams.set('search', params.search);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<PlatformRule>>(
      `/api/admin/platform-rules${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get rule detail
   */
  getRule: async (id: string): Promise<{ data: PlatformRuleDetail }> => {
    return apiFetch<{ data: PlatformRuleDetail }>(`/api/admin/platform-rules/${id}`);
  },

  /**
   * Create rule
   */
  createRule: async (data: CreatePlatformRuleParams): Promise<{ message: string; data: PlatformRule }> => {
    return apiFetch('/api/admin/platform-rules', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Update rule
   */
  updateRule: async (
    id: string,
    data: UpdatePlatformRuleParams
  ): Promise<{ message: string; data: PlatformRule }> => {
    return apiFetch(`/api/admin/platform-rules/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * Delete rule
   */
  deleteRule: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/platform-rules/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * Validate expression
   */
  validateExpression: async (data: ValidateExpressionParams): Promise<ValidateResult> => {
    return apiFetch('/api/admin/platform-rules/validate', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Test rule execution
   */
  testRule: async (data: TestRuleParams): Promise<TestResult> => {
    return apiFetch('/api/admin/platform-rules/test', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Get available variables and functions
   */
  getReference: async (type: 'pricing' | 'stock_priority' | 'settlement_fee' = 'pricing'): Promise<RuleReference> => {
    const response = await apiFetch<{
      variables: Record<string, { type: string; description: string }>;
      functions: Record<string, { signature: string; description: string; example: string }>;
    }>(`/api/admin/platform-rules/reference?type=${type}`);

    // Transform object format to array format
    return {
      variables: Object.entries(response.variables).map(([name, v]) => ({
        name,
        type: v.type,
        description: v.description,
      })),
      functions: Object.entries(response.functions).map(([name, f]) => ({
        name,
        signature: f.signature,
        description: f.description,
        example: f.example,
      })),
    };
  },

  /**
   * Get rule assignments
   */
  getAssignments: async (ruleId: string): Promise<{ data: PlatformRuleAssignment[] }> => {
    return apiFetch<{ data: PlatformRuleAssignment[] }>(`/api/admin/platform-rules/${ruleId}/assignments`);
  },

  /**
   * Get assignments by scope
   */
  getAssignmentsByScope: async (
    scopeType: 'merchant' | 'channel_product',
    scopeId: string
  ): Promise<{ data: PlatformRuleAssignmentWithRule[] }> => {
    return apiFetch<{ data: PlatformRuleAssignmentWithRule[] }>(
      `/api/admin/platform-rules/assignments/scope/${scopeType}/${scopeId}`
    );
  },

  /**
   * Assign rule
   */
  assignRule: async (
    ruleId: string,
    data: AssignPlatformRuleParams
  ): Promise<{ message: string; data: PlatformRuleAssignment }> => {
    return apiFetch(`/api/admin/platform-rules/${ruleId}/assignments`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Remove rule assignment
   */
  unassignRule: async (assignmentId: string): Promise<{ message: string }> => {
    return apiFetch(`/api/admin/platform-rules/assignments/${assignmentId}`, {
      method: 'DELETE',
    });
  },

  /**
   * Toggle assignment status
   */
  toggleAssignment: async (
    assignmentId: string
  ): Promise<{ message: string; data: PlatformRuleAssignment }> => {
    return apiFetch(`/api/admin/platform-rules/assignments/${assignmentId}/toggle`, {
      method: 'PUT',
    });
  },
};
