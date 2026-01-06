import { apiFetch } from './api-client';

// Types
export interface MerchantRule {
  id: string;
  code: string;
  name: string;
  description: string | null;
  type: 'pricing' | 'stock_allocation';
  category: 'markup' | 'discount' | 'ratio' | 'limit';
  expression: string;
  conditionExpression: string | null;
  priority: number;
  config: Record<string, unknown> | null;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface MerchantRuleAssignment {
  id: string;
  merchantSalesChannel: {
    id: string;
    salesChannel: {
      id: string;
      code: string;
      name: string;
    };
  };
  priorityOverride: number | null;
  configOverride: Record<string, unknown> | null;
  effectivePriority: number;
  isActive: boolean;
  createdAt: string;
}

export interface MerchantRuleDetail extends MerchantRule {
  assignments: MerchantRuleAssignment[];
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface MerchantRuleListParams {
  page?: number;
  limit?: number;
  type?: string;
  search?: string;
}

export interface CreateMerchantRuleParams {
  code: string;
  name: string;
  description?: string;
  type: 'pricing' | 'stock_allocation';
  category: 'markup' | 'discount' | 'ratio' | 'limit';
  expression: string;
  conditionExpression?: string;
  priority?: number;
  config?: Record<string, unknown>;
  isActive?: boolean;
}

export interface UpdateMerchantRuleParams {
  name?: string;
  description?: string;
  category?: 'markup' | 'discount' | 'ratio' | 'limit';
  expression?: string;
  conditionExpression?: string;
  priority?: number;
  config?: Record<string, unknown>;
  isActive?: boolean;
}

export interface AssignMerchantRuleParams {
  merchantSalesChannelId: string;
  priorityOverride?: number;
  configOverride?: Record<string, unknown>;
  isActive?: boolean;
}

export interface ValidateExpressionParams {
  expression: string;
  type: 'pricing' | 'stock_allocation';
}

export interface ValidateResult {
  valid: boolean;
  error?: string;
}

export interface TestRuleParams {
  expression: string;
  conditionExpression?: string;
  type: 'pricing' | 'stock_allocation';
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

export const merchantRuleApi = {
  /**
   * Get merchant rules list
   */
  getRules: async (params: MerchantRuleListParams = {}): Promise<PaginatedResponse<MerchantRule>> => {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set('page', String(params.page));
    if (params.limit) searchParams.set('limit', String(params.limit));
    if (params.type) searchParams.set('type', params.type);
    if (params.search) searchParams.set('search', params.search);

    const query = searchParams.toString();
    return apiFetch<PaginatedResponse<MerchantRule>>(
      `/api/merchant/rules${query ? `?${query}` : ''}`
    );
  },

  /**
   * Get rule detail
   */
  getRule: async (id: string): Promise<{ data: MerchantRuleDetail }> => {
    return apiFetch<{ data: MerchantRuleDetail }>(`/api/merchant/rules/${id}`);
  },

  /**
   * Create rule
   */
  createRule: async (data: CreateMerchantRuleParams): Promise<{ message: string; data: MerchantRule }> => {
    return apiFetch('/api/merchant/rules', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Update rule
   */
  updateRule: async (
    id: string,
    data: UpdateMerchantRuleParams
  ): Promise<{ message: string; data: MerchantRule }> => {
    return apiFetch(`/api/merchant/rules/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  /**
   * Delete rule
   */
  deleteRule: async (id: string): Promise<{ message: string }> => {
    return apiFetch(`/api/merchant/rules/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * Validate expression
   */
  validateExpression: async (data: ValidateExpressionParams): Promise<ValidateResult> => {
    return apiFetch('/api/merchant/rules/validate', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Test rule execution
   */
  testRule: async (data: TestRuleParams): Promise<TestResult> => {
    return apiFetch('/api/merchant/rules/test', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Get available variables and functions
   */
  getReference: async (type: 'pricing' | 'stock_allocation' = 'pricing'): Promise<RuleReference> => {
    const response = await apiFetch<{
      variables: Record<string, { type: string; description: string }>;
      functions: Record<string, { signature: string; description: string; example: string }>;
    }>(`/api/merchant/rules/reference?type=${type}`);

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
  getAssignments: async (ruleId: string): Promise<{ data: MerchantRuleAssignment[] }> => {
    return apiFetch<{ data: MerchantRuleAssignment[] }>(`/api/merchant/rules/${ruleId}/assignments`);
  },

  /**
   * Assign rule to channel
   */
  assignRule: async (
    ruleId: string,
    data: AssignMerchantRuleParams
  ): Promise<{ message: string; data: MerchantRuleAssignment }> => {
    return apiFetch(`/api/merchant/rules/${ruleId}/assignments`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Remove rule assignment
   */
  unassignRule: async (assignmentId: string): Promise<{ message: string }> => {
    return apiFetch(`/api/merchant/rules/assignments/${assignmentId}`, {
      method: 'DELETE',
    });
  },

  /**
   * Toggle assignment status
   */
  toggleAssignment: async (
    assignmentId: string
  ): Promise<{ message: string; data: MerchantRuleAssignment }> => {
    return apiFetch(`/api/merchant/rules/assignments/${assignmentId}/toggle`, {
      method: 'PUT',
    });
  },
};
